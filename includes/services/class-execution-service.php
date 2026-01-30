<?php
/**
 * Execution Service class.
 *
 * Handles script execution with comprehensive output buffering,
 * tri-layer error capture, and performance metrics.
 *
 * @package TestScriptManager
 */

namespace TSM\Services;

use TSM\Database;
use TSM\CodeScanner;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execution Service class.
 *
 * Provides script execution functionality:
 * - Execute PHP scripts with full output capture
 * - Tri-layer error capture (error handler, exception handler, shutdown)
 * - Performance metrics (execution time, memory usage)
 * - Result logging to database
 *
 * Implements safety measures:
 * - Code validation before execution
 * - Configurable timeout (1-300 seconds)
 * - Output size limits (10MB max)
 * - Buffer level tracking and cleanup
 */
class ExecutionService {

	/**
	 * Maximum output size in bytes (10MB).
	 */
	const MAX_OUTPUT_SIZE = 10485760;

	/**
	 * Maximum timeout in seconds.
	 */
	const MAX_TIMEOUT = 300;

	/**
	 * Default timeout in seconds.
	 */
	const DEFAULT_TIMEOUT = 30;

	/**
	 * Captured errors during execution.
	 *
	 * @var array
	 */
	private static $captured_errors = array();

	/**
	 * Captured exception during execution.
	 *
	 * @var array|null
	 */
	private static $captured_exception = null;

	/**
	 * Execute a script by ID.
	 *
	 * Runs the script with full output buffering, error capture,
	 * and performance tracking.
	 *
	 * @param int $script_id Script ID to execute.
	 * @param int $timeout   Execution timeout in seconds (1-300, default 30).
	 * @return array|\WP_Error Execution result array or WP_Error on failure.
	 */
	public static function execute( $script_id, $timeout = self::DEFAULT_TIMEOUT ) {
		// Reset captured data.
		self::$captured_errors    = array();
		self::$captured_exception = null;

		// Validate timeout.
		$timeout = max( 1, min( (int) $timeout, self::MAX_TIMEOUT ) );

		// Get script data.
		$script = ScriptService::get( $script_id );
		if ( null === $script ) {
			return new \WP_Error(
				'tsm_script_not_found',
				__( 'Script not found.', 'test-script-manager' )
			);
		}

		// Get file path.
		$file_path = StorageService::get_script_path( $script['slug'] );
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error(
				'tsm_file_not_found',
				__( 'Script file not found on filesystem.', 'test-script-manager' )
			);
		}

		// Validate code for dangerous functions.
		$validation = CodeScanner::validate_code( $script['code'] );
		if ( ! $validation['valid'] ) {
			return new \WP_Error(
				'tsm_dangerous_code',
				$validation['message']
			);
		}

		// Store original settings.
		$original_timeout     = ini_get( 'max_execution_time' );
		$original_buffer_level = ob_get_level();
		$original_error_handler = null;
		$original_exception_handler = null;

		// Track start metrics.
		$start_time   = microtime( true );
		$start_memory = memory_get_usage( true );

		// Initialize result.
		$output    = '';
		$status    = 'success';
		$fatal     = null;
		$exception = null;

		try {
			// Set up error handler.
			$original_error_handler = set_error_handler( array( __CLASS__, 'error_handler' ) );

			// Set up exception handler.
			$original_exception_handler = set_exception_handler( array( __CLASS__, 'exception_handler' ) );

			// Set timeout.
			set_time_limit( $timeout );

			// Start output buffering.
			ob_start();

			// Execute the script.
			include $file_path;

			// Capture output.
			$output = ob_get_clean();

		} catch ( \Throwable $e ) {
			// Capture exception.
			$exception = array(
				'type'    => get_class( $e ),
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
				'trace'   => $e->getTraceAsString(),
			);
			$status = 'error';

			// Clean output buffer.
			if ( ob_get_level() > $original_buffer_level ) {
				$output = ob_get_clean();
			}
		}

		// Restore error handler.
		restore_error_handler();

		// Restore exception handler.
		restore_exception_handler();

		// Restore timeout.
		if ( false !== $original_timeout ) {
			set_time_limit( (int) $original_timeout );
		}

		// Clean any extra output buffers.
		while ( ob_get_level() > $original_buffer_level ) {
			ob_end_clean();
		}

		// Check for fatal error.
		$last_error = error_get_last();
		if ( null !== $last_error && in_array( $last_error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$fatal = array(
				'type'    => $last_error['type'],
				'message' => $last_error['message'],
				'file'    => $last_error['file'],
				'line'    => $last_error['line'],
			);
			$status = 'fatal_error';
		}

		// Check for captured exception from handler.
		if ( null !== self::$captured_exception ) {
			$exception = self::$captured_exception;
			$status    = 'error';
		}

		// Calculate metrics.
		$end_time     = microtime( true );
		$end_memory   = memory_get_peak_usage( true );
		$execution_time = $end_time - $start_time;
		$memory_usage   = $end_memory - $start_memory;

		// Truncate output if too large.
		$truncated = false;
		if ( strlen( $output ) > self::MAX_OUTPUT_SIZE ) {
			$output    = substr( $output, 0, self::MAX_OUTPUT_SIZE );
			$truncated = true;
		}

		// Build result array.
		$result = array(
			'output'         => $output,
			'errors'         => self::$captured_errors,
			'exception'      => $exception,
			'fatal'          => $fatal,
			'execution_time' => $execution_time,
			'memory_usage'   => $memory_usage,
			'status'         => $status,
			'truncated'      => $truncated,
		);

		// Update last executed timestamp.
		ScriptService::update_last_executed( $script_id );

		// Log execution to database.
		$execution_id = self::log_execution( $script_id, $result );
		$result['execution_id'] = $execution_id;

		return $result;
	}

	/**
	 * Custom error handler to capture warnings/notices.
	 *
	 * @param int    $errno   Error level.
	 * @param string $errstr  Error message.
	 * @param string $errfile File where error occurred.
	 * @param int    $errline Line where error occurred.
	 * @return bool True to prevent PHP's default error handler.
	 */
	public static function error_handler( $errno, $errstr, $errfile, $errline ) {
		self::$captured_errors[] = array(
			'type'    => $errno,
			'level'   => self::error_level_name( $errno ),
			'message' => $errstr,
			'file'    => $errfile,
			'line'    => $errline,
		);

		// Don't execute PHP's default handler.
		return true;
	}

	/**
	 * Custom exception handler for uncaught exceptions.
	 *
	 * @param \Throwable $exception The exception.
	 */
	public static function exception_handler( $exception ) {
		self::$captured_exception = array(
			'type'    => get_class( $exception ),
			'message' => $exception->getMessage(),
			'file'    => $exception->getFile(),
			'line'    => $exception->getLine(),
			'trace'   => $exception->getTraceAsString(),
		);
	}

	/**
	 * Get human-readable error level name.
	 *
	 * @param int $errno Error level constant.
	 * @return string Error level name.
	 */
	public static function error_level_name( $errno ) {
		$levels = array(
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parse Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_STRICT            => 'Strict',
			E_RECOVERABLE_ERROR => 'Recoverable Error',
			E_DEPRECATED        => 'Deprecated',
			E_USER_DEPRECATED   => 'User Deprecated',
		);

		return isset( $levels[ $errno ] ) ? $levels[ $errno ] : 'Unknown';
	}

	/**
	 * Log execution result to database.
	 *
	 * @param int   $script_id Script ID.
	 * @param array $result    Execution result array.
	 * @return int|false Execution log ID or false on failure.
	 */
	public static function log_execution( $script_id, $result ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// Prepare error data for storage.
		$error_data = array(
			'errors'    => $result['errors'],
			'exception' => $result['exception'],
			'fatal'     => $result['fatal'],
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'script_id'      => $script_id,
				'output'         => $result['output'],
				'error'          => wp_json_encode( $error_data ),
				'execution_time' => $result['execution_time'],
				'memory_usage'   => $result['memory_usage'],
				'status'         => $result['status'],
				'executed_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%f', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get execution log by ID.
	 *
	 * @param int $execution_id Execution log ID.
	 * @return array|null Execution log data or null if not found.
	 */
	public static function get_execution_log( $execution_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$execution_id
			),
			ARRAY_A
		);

		if ( null === $log ) {
			return null;
		}

		// Decode error data.
		$log['error_data'] = json_decode( $log['error'], true );

		return $log;
	}

	/**
	 * Get execution history for a script.
	 *
	 * @param int $script_id Script ID.
	 * @param int $limit     Maximum results (default: 10).
	 * @return array Execution log entries.
	 */
	public static function get_script_executions( $script_id, $limit = 10 ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_EXECUTION_LOGS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE script_id = %d ORDER BY executed_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$script_id,
				$limit
			),
			ARRAY_A
		);

		if ( null === $results ) {
			return array();
		}

		// Decode error data for each result.
		foreach ( $results as &$log ) {
			$log['error_data'] = json_decode( $log['error'], true );
		}

		return $results;
	}
}
