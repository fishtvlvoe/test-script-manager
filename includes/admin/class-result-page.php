<?php
/**
 * Result Page class for execution result display.
 *
 * Provides a standalone result page that opens in a new tab
 * showing formatted execution output, errors, and stats.
 *
 * @package TestScriptManager
 */

namespace TSM\Admin;

use TSM\Security;
use TSM\Database;
use TSM\Services\ScriptService;
use TSM\Services\OutputFormatter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Result_Page class.
 *
 * Handles the execution result display page.
 * Accessed via admin.php?page=tsm-result&execution_id=X
 */
class Result_Page {

	/**
	 * Initialize the result page.
	 *
	 * Registers the standalone page endpoint.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_result_page' ) );
		add_action( 'load-admin_page_tsm-result', array( __CLASS__, 'handle_result_page' ) );
	}

	/**
	 * Register the result page in admin menu (hidden).
	 */
	public static function register_result_page() {
		add_submenu_page(
			null, // No parent = hidden from menu.
			__( 'Execution Result', 'test-script-manager' ),
			__( 'Execution Result', 'test-script-manager' ),
			'manage_options',
			'tsm-result',
			array( __CLASS__, 'render_result_page' )
		);
	}

	/**
	 * Render the result page.
	 *
	 * Called by WordPress when tsm-result page is accessed.
	 */
	public static function render_result_page() {
		// Get execution ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$execution_id = isset( $_GET['execution_id'] ) ? absint( $_GET['execution_id'] ) : 0;
		if ( ! $execution_id ) {
			wp_die( esc_html__( 'Invalid execution ID.', 'test-script-manager' ) );
		}

		// Load execution data.
		$execution = self::get_execution( $execution_id );
		if ( ! $execution ) {
			wp_die( esc_html__( 'Execution not found.', 'test-script-manager' ) );
		}

		// Get script info.
		$script = ScriptService::get( $execution['script_id'] );

		// Enqueue assets.
		self::enqueue_assets();

		// Render the page.
		self::render( $execution, $script );
		exit;
	}

	/**
	 * Handle the result page request (for hook compatibility).
	 *
	 * @deprecated Use render_result_page() instead.
	 */
	public static function handle_result_page() {
		self::render_result_page();
	}

	/**
	 * Get execution data from database.
	 *
	 * @param int $id Execution ID.
	 * @return array|null Execution data or null if not found.
	 */
	private static function get_execution( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . Database::TABLE_EXECUTION_LOGS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		if ( $row ) {
			$row['error'] = json_decode( $row['error'], true );
		}

		return $row;
	}

	/**
	 * Enqueue CSS and JS for result page.
	 */
	private static function enqueue_assets() {
		wp_enqueue_style(
			'tsm-execution-result',
			TSM_PLUGIN_URL . 'assets/css/execution-result.css',
			array(),
			TSM_VERSION
		);

		wp_enqueue_script(
			'tsm-execution-result',
			TSM_PLUGIN_URL . 'assets/js/execution-result.js',
			array(),
			TSM_VERSION,
			true
		);
	}

	/**
	 * Render the result page.
	 *
	 * @param array      $execution Execution data.
	 * @param array|null $script    Script data or null if not found.
	 */
	public static function render( $execution, $script ) {
		$script_name = $script ? $script['name'] : __( 'Unknown Script', 'test-script-manager' );

		// Prepare data for template.
		$data = array(
			'script_name'    => $script_name,
			'status'         => $execution['status'],
			'output'         => $execution['output'],
			'errors'         => $execution['error']['errors'] ?? array(),
			'exception'      => $execution['error']['exception'] ?? null,
			'fatal'          => $execution['error']['fatal'] ?? null,
			'execution_time' => $execution['execution_time'],
			'memory_usage'   => $execution['memory_usage'],
			'executed_at'    => $execution['executed_at'],
		);

		// Detect output type for special formatting.
		$data['output_type'] = OutputFormatter::detect_output_type( $execution['output'] );

		// Attempt table formatting for JSON array data (database query results).
		$data['table_html'] = '';
		if ( 'json' === $data['output_type'] ) {
			$decoded = json_decode( $execution['output'], true );
			if ( is_array( $decoded ) && OutputFormatter::is_table_data( $decoded ) ) {
				$data['table_html'] = OutputFormatter::format_as_table( $decoded );
				$data['output_type'] = 'table'; // Switch type to table for template.
			}
		}

		include TSM_PLUGIN_DIR . 'includes/admin/views/execution-result.php';
	}
}
