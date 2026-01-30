<?php
/**
 * Background Execution Service class.
 *
 * Handles background script execution using Action Scheduler.
 * Provides scheduling, execution, retry, and cancellation functionality.
 *
 * @package TestScriptManager
 */

namespace TSM\Services;

use TSM\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background Execution Service class.
 *
 * Integrates with Action Scheduler to enable:
 * - Asynchronous script execution without browser timeout
 * - Automatic retry for recoverable failures (max 3 retries)
 * - Fatal error detection to skip retries
 * - Cancellation of pending/running executions
 */
class BackgroundExecutionService {

	/**
	 * Hook name for background execution.
	 */
	const HOOK_NAME = 'tsm_execute_script_background';

	/**
	 * Action Scheduler group name.
	 */
	const GROUP_NAME = 'test-script-manager';

	/**
	 * Maximum retry attempts.
	 */
	const MAX_RETRIES = 3;

	/**
	 * Retry delay in seconds (5 minutes).
	 */
	const RETRY_DELAY = 300;

	/**
	 * Non-retryable error types (fatal errors).
	 *
	 * @var array
	 */
	const FATAL_ERROR_TYPES = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR );

	/**
	 * Initialize background execution hooks.
	 *
	 * Called on 'init' hook at priority 20 to ensure Action Scheduler is ready.
	 */
	public static function init() {
		add_action( self::HOOK_NAME, array( __CLASS__, 'execute_callback' ), 10, 1 );
	}

	/**
	 * Schedule a script for background execution.
	 *
	 * Creates a pending execution log entry and schedules the job
	 * with Action Scheduler.
	 *
	 * @param int      $script_id Script ID to execute.
	 * @param int|null $user_id   User ID who initiated execution (default: current user).
	 * @return int|\WP_Error Execution ID on success, WP_Error on failure.
	 */
	public static function schedule( $script_id, $user_id = null ) {
		// Verify Action Scheduler is available.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return new \WP_Error(
				'tsm_no_scheduler',
				__( 'Action Scheduler is not available.', 'test-script-manager' )
			);
		}

		// Use current user if not specified.
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// Verify script exists.
		$script = ScriptService::get( $script_id );
		if ( null === $script ) {
			return new \WP_Error(
				'tsm_script_not_found',
				__( 'Script not found.', 'test-script-manager' )
			);
		}

		// Create pending execution log.
		$execution_id = self::create_pending_execution( $script_id, $user_id );
		if ( ! $execution_id ) {
			return new \WP_Error(
				'tsm_execution_failed',
				__( 'Failed to create execution log entry.', 'test-script-manager' )
			);
		}

		// Schedule with Action Scheduler.
		$action_id = as_schedule_single_action(
			time(),
			self::HOOK_NAME,
			array(
				'execution_id' => $execution_id,
				'script_id'    => $script_id,
			),
			self::GROUP_NAME
		);

		if ( 0 === $action_id ) {
			// Rollback: delete execution log.
			self::delete_execution( $execution_id );
			return new \WP_Error(
				'tsm_schedule_failed',
				__( 'Failed to schedule background execution.', 'test-script-manager' )
			);
		}

		// Update execution log with action_id.
		self::update_execution_status(
			$execution_id,
			'pending',
			array( 'action_id' => $action_id )
		);

		return $execution_id;
	}

	/**
	 * Execute callback for Action Scheduler.
	 *
	 * This method is called by Action Scheduler when the job runs.
	 * It handles execution, error capture, and retry logic.
	 *
	 * @param array $args Arguments containing execution_id and script_id.
	 */
	public static function execute_callback( $args ) {
		$execution_id = $args['execution_id'] ?? 0;
		$script_id    = $args['script_id'] ?? 0;

		if ( ! $execution_id || ! $script_id ) {
			return;
		}

		// Check if cancelled before starting.
		if ( self::is_cancelled( $execution_id ) ) {
			return;
		}

		// Mark as running.
		self::update_execution_status(
			$execution_id,
			'running',
			array( 'started_at' => current_time( 'mysql' ) )
		);

		// Execute the script using existing ExecutionService.
		$result = ExecutionService::execute( $script_id );

		// Check cancellation after execution.
		if ( self::is_cancelled( $execution_id ) ) {
			// Save partial result if available.
			if ( ! is_wp_error( $result ) && ! empty( $result['output'] ) ) {
				self::save_execution_result( $execution_id, $result );
			}
			return;
		}

		// Handle result.
		if ( is_wp_error( $result ) ) {
			self::handle_failure(
				$execution_id,
				0, // Error type 0 for WP_Error
				$result->get_error_message()
			);
			return;
		}

		// Check for errors in result.
		if ( 'fatal_error' === $result['status'] ) {
			$error_type = $result['fatal']['type'] ?? E_ERROR;
			$error_message = $result['fatal']['message'] ?? 'Unknown fatal error';
			self::handle_failure( $execution_id, $error_type, $error_message );
			return;
		}

		if ( 'error' === $result['status'] ) {
			$error_message = $result['exception']['message'] ?? 'Unknown error';
			self::handle_failure( $execution_id, 0, $error_message );
			return;
		}

		// Success - save result and update status.
		self::save_execution_result( $execution_id, $result );
		self::update_execution_status( $execution_id, 'success' );
	}

	/**
	 * Handle execution failure.
	 *
	 * Determines whether to retry based on error type and retry count.
	 * Fatal errors (E_ERROR, E_PARSE, etc.) do not retry.
	 *
	 * @param int    $execution_id Execution log ID.
	 * @param int    $error_type   PHP error type constant (E_ERROR, etc.) or 0 for general errors.
	 * @param string $error_message Error message.
	 */
	public static function handle_failure( $execution_id, $error_type, $error_message ) {
		$execution = self::get_execution( $execution_id );
		if ( ! $execution ) {
			return;
		}

		$retry_count = (int) $execution['retry_count'];
		$script_id   = (int) $execution['script_id'];

		// Check if this is a fatal error (should not retry).
		if ( self::is_fatal_error( $error_type ) ) {
			self::update_execution_status(
				$execution_id,
				'fatal_error',
				array(
					'error' => wp_json_encode(
						array(
							'type'    => $error_type,
							'message' => $error_message,
							'fatal'   => true,
						)
					),
				)
			);
			return;
		}

		// Check if we've exhausted retries.
		if ( $retry_count >= self::MAX_RETRIES ) {
			self::update_execution_status(
				$execution_id,
				'error',
				array(
					'error' => wp_json_encode(
						array(
							'type'    => $error_type,
							'message' => $error_message,
							'retries' => $retry_count,
						)
					),
				)
			);
			return;
		}

		// Schedule retry.
		$new_retry_count = $retry_count + 1;

		// Update status to 'retry'.
		self::update_execution_status(
			$execution_id,
			'retry',
			array(
				'retry_count' => $new_retry_count,
				'error'       => wp_json_encode(
					array(
						'type'    => $error_type,
						'message' => $error_message,
						'attempt' => $retry_count + 1,
					)
				),
			)
		);

		// Schedule retry with Action Scheduler.
		$action_id = as_schedule_single_action(
			time() + self::RETRY_DELAY,
			self::HOOK_NAME,
			array(
				'execution_id' => $execution_id,
				'script_id'    => $script_id,
			),
			self::GROUP_NAME
		);

		// Update action_id for the retry.
		if ( $action_id ) {
			self::update_execution_status(
				$execution_id,
				'retry',
				array( 'action_id' => $action_id )
			);
		}
	}

	/**
	 * Cancel a background execution.
	 *
	 * @param int $execution_id Execution log ID.
	 * @return bool True if cancelled successfully, false otherwise.
	 */
	public static function cancel( $execution_id ) {
		$execution = self::get_execution( $execution_id );
		if ( ! $execution ) {
			return false;
		}

		$status = $execution['status'];

		// Only allow cancellation of pending, running, or retry status.
		if ( ! in_array( $status, array( 'pending', 'running', 'retry' ), true ) ) {
			return false;
		}

		// Mark as cancelled.
		self::update_execution_status( $execution_id, 'cancelled' );

		// Unschedule from Action Scheduler if pending/retry.
		if ( in_array( $status, array( 'pending', 'retry' ), true ) ) {
			$action_id = $execution['action_id'];
			if ( $action_id && function_exists( 'as_unschedule_action' ) ) {
				as_unschedule_action(
					self::HOOK_NAME,
					array(
						'execution_id' => $execution_id,
						'script_id'    => (int) $execution['script_id'],
					),
					self::GROUP_NAME
				);
			}
		}

		return true;
	}

	/**
	 * Check if an execution has been cancelled.
	 *
	 * @param int $execution_id Execution log ID.
	 * @return bool True if cancelled, false otherwise.
	 */
	public static function is_cancelled( $execution_id ) {
		$execution = self::get_execution( $execution_id );
		if ( ! $execution ) {
			return true; // Treat missing execution as cancelled.
		}

		return 'cancelled' === $execution['status'];
	}

	/**
	 * Create a pending execution log entry.
	 *
	 * @param int $script_id Script ID.
	 * @param int $user_id   User ID.
	 * @return int|false Execution ID or false on failure.
	 */
	private static function create_pending_execution( $script_id, $user_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'script_id'      => $script_id,
				'status'         => 'pending',
				'execution_mode' => 'background',
				'scheduled_at'   => current_time( 'mysql' ),
				'user_id'        => $user_id,
				'retry_count'    => 0,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update execution status and optional data.
	 *
	 * @param int    $execution_id Execution log ID.
	 * @param string $status       New status.
	 * @param array  $data         Additional data to update (optional).
	 * @return bool True on success, false on failure.
	 */
	private static function update_execution_status( $execution_id, $status, $data = array() ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		$update_data = array_merge( array( 'status' => $status ), $data );
		$format      = array( '%s' );

		// Build format array for additional data.
		foreach ( $data as $key => $value ) {
			if ( is_int( $value ) ) {
				$format[] = '%d';
			} elseif ( is_float( $value ) ) {
				$format[] = '%f';
			} else {
				$format[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $execution_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get execution log by ID.
	 *
	 * @param int $execution_id Execution log ID.
	 * @return array|null Execution data or null if not found.
	 */
	private static function get_execution( $execution_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$execution_id
			),
			ARRAY_A
		);
	}

	/**
	 * Delete execution log entry.
	 *
	 * Used for rollback when scheduling fails.
	 *
	 * @param int $execution_id Execution log ID.
	 * @return bool True on success, false on failure.
	 */
	private static function delete_execution( $execution_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $execution_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Save execution result to database.
	 *
	 * @param int   $execution_id Execution log ID.
	 * @param array $result       Execution result from ExecutionService.
	 */
	private static function save_execution_result( $execution_id, $result ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// Prepare error data.
		$error_data = array(
			'errors'    => $result['errors'] ?? array(),
			'exception' => $result['exception'] ?? null,
			'fatal'     => $result['fatal'] ?? null,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table_name,
			array(
				'output'         => $result['output'] ?? '',
				'error'          => wp_json_encode( $error_data ),
				'execution_time' => $result['execution_time'] ?? 0,
				'memory_usage'   => $result['memory_usage'] ?? 0,
				'executed_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $execution_id ),
			array( '%s', '%s', '%f', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Check if error type is fatal (should not retry).
	 *
	 * @param int $error_type PHP error type constant.
	 * @return bool True if fatal, false otherwise.
	 */
	private static function is_fatal_error( $error_type ) {
		return in_array( $error_type, self::FATAL_ERROR_TYPES, true );
	}
}
