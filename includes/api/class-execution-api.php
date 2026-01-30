<?php
/**
 * Execution REST API class.
 *
 * Provides REST API endpoints for execution history management.
 *
 * @package TestScriptManager
 */

namespace TSM\API;

use TSM\Security;
use TSM\Database;
use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execution API class.
 *
 * Registers and handles REST API endpoints for execution logs:
 * - GET    /executions      - List execution history (with filters)
 * - GET    /executions/{id} - Get a single execution log
 * - DELETE /executions/{id} - Delete an execution log
 *
 * All endpoints require manage_options capability.
 */
class Execution_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'test-script-manager/v1';

	/**
	 * Register REST API routes.
	 *
	 * Called via rest_api_init hook.
	 */
	public function register_routes() {
		// GET /executions - List execution history.
		register_rest_route(
			self::NAMESPACE,
			'/executions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_executions' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'script_id' => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'status'    => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'     => array(
						'default'           => 50,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'offset'    => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /executions/{id} - Get single execution log.
		register_rest_route(
			self::NAMESPACE,
			'/executions/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_execution' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// DELETE /executions/{id} - Delete execution log.
		register_rest_route(
			self::NAMESPACE,
			'/executions/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_execution' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle GET /executions - List execution history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with executions array.
	 */
	public function get_executions( WP_REST_Request $request ) {
		global $wpdb;

		$script_id = $request->get_param( 'script_id' );
		$status    = $request->get_param( 'status' );
		$limit     = $request->get_param( 'limit' );
		$offset    = $request->get_param( 'offset' );

		// Cap limit at 100 to prevent excessive data transfer.
		$limit = min( $limit, 100 );

		$logs_table    = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );
		$scripts_table = Database::get_table_name( Database::TABLE_SCRIPTS );

		// Build query.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT e.*, s.name as script_name
				FROM $logs_table e
				LEFT JOIN $scripts_table s ON e.script_id = s.id
				WHERE 1=1";

		$params = array();

		if ( $script_id > 0 ) {
			$sql     .= ' AND e.script_id = %d';
			$params[] = $script_id;
		}

		if ( ! empty( $status ) ) {
			$sql     .= ' AND e.status = %s';
			$params[] = $status;
		}

		$sql .= ' ORDER BY e.executed_at DESC';
		$sql .= ' LIMIT %d OFFSET %d';

		$params[] = $limit;
		$params[] = $offset;

		// Execute query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$executions = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		if ( null === $executions ) {
			$executions = array();
		}

		// Decode error JSON and convert types for each execution.
		foreach ( $executions as &$execution ) {
			$execution['error_data']      = json_decode( $execution['error'], true );
			$execution['execution_time']  = (float) $execution['execution_time'];
			$execution['memory_usage']    = (int) $execution['memory_usage'];
		}

		// Count total matching executions.
		$total = $this->count_executions( $script_id, $status );

		return new WP_REST_Response(
			array(
				'executions' => $executions,
				'total'      => $total,
				'limit'      => $limit,
				'offset'     => $offset,
			),
			200
		);
	}

	/**
	 * Handle GET /executions/{id} - Get single execution log.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with execution data or error.
	 */
	public function get_execution( WP_REST_Request $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$logs_table    = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );
		$scripts_table = Database::get_table_name( Database::TABLE_SCRIPTS );

		// Query with script name join.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$execution = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT e.*, s.name as script_name
				FROM $logs_table e
				LEFT JOIN $scripts_table s ON e.script_id = s.id
				WHERE e.id = %d",
				$id
			),
			ARRAY_A
		);

		if ( null === $execution ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Execution log not found.', 'test-script-manager' ),
					'code'    => 'tsm_execution_not_found',
				),
				404
			);
		}

		// Decode error JSON and convert types.
		$execution['error_data']      = json_decode( $execution['error'], true );
		$execution['execution_time']  = (float) $execution['execution_time'];
		$execution['memory_usage']    = (int) $execution['memory_usage'];

		return new WP_REST_Response(
			array(
				'success'   => true,
				'execution' => $execution,
			),
			200
		);
	}

	/**
	 * Handle DELETE /executions/{id} - Delete execution log.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with success status or error.
	 */
	public function delete_execution( WP_REST_Request $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$logs_table = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// Check if execution exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $logs_table WHERE id = %d",
				$id
			)
		);

		if ( null === $exists ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Execution log not found.', 'test-script-manager' ),
					'code'    => 'tsm_execution_not_found',
				),
				404
			);
		}

		// Delete execution log.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->delete(
			$logs_table,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Failed to delete execution log.', 'test-script-manager' ),
					'code'    => 'tsm_delete_failed',
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Count total executions matching filters.
	 *
	 * @param int    $script_id Script ID filter (0 for all).
	 * @param string $status    Status filter (empty for all).
	 * @return int Total count.
	 */
	private function count_executions( $script_id, $status ) {
		global $wpdb;

		$logs_table = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql    = "SELECT COUNT(*) FROM $logs_table WHERE 1=1";
		$params = array();

		if ( $script_id > 0 ) {
			$sql     .= ' AND script_id = %d';
			$params[] = $script_id;
		}

		if ( ! empty( $status ) ) {
			$sql     .= ' AND status = %s';
			$params[] = $status;
		}

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}
}
