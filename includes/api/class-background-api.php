<?php
/**
 * Background REST API class.
 *
 * Provides REST API endpoints for background execution control.
 *
 * @package TestScriptManager
 */

namespace TSM\API;

use TSM\Security;
use TSM\Database;
use TSM\Services\BackgroundExecutionService;
use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background API class.
 *
 * Registers and handles REST API endpoints for background execution:
 * - POST /scripts/{id}/execute-background - Schedule background execution
 * - POST /executions/{id}/cancel          - Cancel execution
 * - GET  /executions/{id}/status          - Get execution status (polling)
 *
 * All endpoints require manage_options capability.
 */
class Background_API {

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
		// POST /scripts/{id}/execute-background - Schedule background execution.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/execute-background',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'schedule_background' ),
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

		// POST /executions/{id}/cancel - Cancel execution.
		register_rest_route(
			self::NAMESPACE,
			'/executions/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_execution' ),
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

		// GET /executions/{id}/status - Get execution status (polling endpoint).
		register_rest_route(
			self::NAMESPACE,
			'/executions/(?P<id>\d+)/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_execution_status' ),
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
	 * Handle POST /scripts/{id}/execute-background
	 *
	 * Schedules a script for background execution.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with execution_id or error.
	 */
	public function schedule_background( WP_REST_Request $request ) {
		$script_id = $request->get_param( 'id' );
		$user_id   = get_current_user_id();

		$result = BackgroundExecutionService::schedule( $script_id, $user_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'execution_id' => $result,
				'message'      => __( 'Script scheduled for background execution.', 'test-script-manager' ),
			),
			200
		);
	}

	/**
	 * Handle POST /executions/{id}/cancel
	 *
	 * Cancels a pending, running, or retry execution.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with success status or error.
	 */
	public function cancel_execution( WP_REST_Request $request ) {
		$execution_id = $request->get_param( 'id' );

		$result = BackgroundExecutionService::cancel( $execution_id );

		if ( ! $result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Unable to cancel execution. It may have already completed or been cancelled.', 'test-script-manager' ),
					'code'    => 'tsm_cancel_failed',
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Execution cancelled.', 'test-script-manager' ),
			),
			200
		);
	}

	/**
	 * Handle GET /executions/{id}/status
	 *
	 * Lightweight endpoint for polling execution status.
	 * Returns current status, retry count, and cancellability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with execution status or error.
	 */
	public function get_execution_status( WP_REST_Request $request ) {
		global $wpdb;

		$execution_id = $request->get_param( 'id' );

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$execution = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, execution_mode, retry_count, execution_time, memory_usage, executed_at, started_at
				FROM $table_name WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$execution_id
			),
			ARRAY_A
		);

		if ( null === $execution ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Execution not found.', 'test-script-manager' ),
					'code'    => 'tsm_execution_not_found',
				),
				404
			);
		}

		// Format status display.
		$status_display = $execution['status'];
		if ( 'retry' === $execution['status'] && $execution['retry_count'] > 0 ) {
			/* translators: 1: Current retry count 2: Max retries */
			$status_display = sprintf( __( 'Retry %1$d/%2$d', 'test-script-manager' ), $execution['retry_count'], 3 );
		}

		return new WP_REST_Response(
			array(
				'success'        => true,
				'execution_id'   => (int) $execution['id'],
				'status'         => $execution['status'],
				'status_display' => $status_display,
				'execution_mode' => $execution['execution_mode'],
				'retry_count'    => (int) $execution['retry_count'],
				'execution_time' => (float) $execution['execution_time'],
				'memory_usage'   => (int) $execution['memory_usage'],
				'is_cancellable' => in_array( $execution['status'], array( 'pending', 'running', 'retry' ), true ),
			),
			200
		);
	}
}
