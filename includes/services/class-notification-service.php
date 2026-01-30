<?php
/**
 * Notification Service class.
 *
 * Handles notifications for background execution completion.
 * Provides Admin Notice and Email notification functionality.
 *
 * @package TestScriptManager
 */

namespace TSM\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification Service class.
 *
 * Provides notification functionality for background executions:
 * - Admin notices stored via transients (displayed on next admin page load)
 * - Email notifications with execution summary
 *
 * Called by BackgroundExecutionService after execution completes.
 */
class NotificationService {

	/**
	 * Transient prefix for admin notices.
	 */
	const TRANSIENT_PREFIX = 'tsm_admin_notices_';

	/**
	 * Transient expiry in seconds (1 hour).
	 */
	const TRANSIENT_EXPIRY = HOUR_IN_SECONDS;

	/**
	 * Initialize notification hooks.
	 *
	 * Called in admin context to display stored notices.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'display_admin_notices' ) );
	}

	/**
	 * Send completion notification (Admin Notice + Email).
	 *
	 * Called by BackgroundExecutionService after execution completes.
	 *
	 * @param int    $execution_id Execution log ID.
	 * @param string $status       'success' or 'failed'.
	 * @param int    $user_id      User ID who initiated execution.
	 */
	public static function send_completion_notification( $execution_id, $status, $user_id ) {
		// Store admin notice.
		self::store_admin_notice( $user_id, $execution_id, $status );

		// Send email notification.
		self::send_email_notification( $execution_id, $status, $user_id );
	}

	/**
	 * Store admin notice using transients.
	 *
	 * Notices are stored per-user and displayed on next admin page load.
	 *
	 * @param int    $user_id      User ID.
	 * @param int    $execution_id Execution log ID.
	 * @param string $status       Execution status.
	 */
	private static function store_admin_notice( $user_id, $execution_id, $status ) {
		$transient_key = self::TRANSIENT_PREFIX . $user_id;
		$notices       = get_transient( $transient_key );

		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'execution_id' => $execution_id,
			'status'       => $status,
			'time'         => time(),
		);

		set_transient( $transient_key, $notices, self::TRANSIENT_EXPIRY );
	}

	/**
	 * Display stored admin notices.
	 *
	 * Retrieves notices for current user from transient and displays them.
	 * Notices are cleared after display.
	 */
	public static function display_admin_notices() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$transient_key = self::TRANSIENT_PREFIX . $user_id;
		$notices       = get_transient( $transient_key );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$execution = ExecutionService::get_execution_log( $notice['execution_id'] );
			if ( ! $execution ) {
				continue;
			}

			$class = ( $notice['status'] === 'success' ) ? 'notice-success' : 'notice-error';
			$url   = admin_url( 'admin.php?page=test-script-manager-result&execution_id=' . $notice['execution_id'] );

			// Get script name from execution log via JOIN query.
			$script_name = self::get_script_name_for_execution( $notice['execution_id'] );

			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
				<p>
					<?php if ( $notice['status'] === 'success' ) : ?>
						<strong><?php esc_html_e( 'Background execution completed:', 'test-script-manager' ); ?></strong>
						<?php echo esc_html( $script_name ); ?>
					<?php else : ?>
						<strong><?php esc_html_e( 'Background execution failed:', 'test-script-manager' ); ?></strong>
						<?php echo esc_html( $script_name ); ?>
					<?php endif; ?>
					&mdash;
					<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View Result', 'test-script-manager' ); ?></a>
				</p>
			</div>
			<?php
		}

		// Clear notices after displaying.
		delete_transient( $transient_key );
	}

	/**
	 * Send email notification.
	 *
	 * Plain text email with script name, status, execution time,
	 * memory usage, and result URL.
	 *
	 * @param int    $execution_id Execution log ID.
	 * @param string $status       Execution status.
	 * @param int    $user_id      User ID.
	 */
	private static function send_email_notification( $execution_id, $status, $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		$execution = ExecutionService::get_execution_log( $execution_id );
		if ( ! $execution ) {
			return;
		}

		// Get script name.
		$script_name = self::get_script_name_for_execution( $execution_id );

		// Build email content.
		$site_name  = get_bloginfo( 'name' );
		$result_url = admin_url( 'admin.php?page=test-script-manager-result&execution_id=' . $execution_id );

		if ( $status === 'success' ) {
			/* translators: 1: Site name 2: Script name */
			$subject = sprintf( __( '[%1$s] Script Execution Completed: %2$s', 'test-script-manager' ), $site_name, $script_name );
		} else {
			/* translators: 1: Site name 2: Script name */
			$subject = sprintf( __( '[%1$s] Script Execution Failed: %2$s', 'test-script-manager' ), $site_name, $script_name );
		}

		// Plain text email (better deliverability, per RESEARCH.md).
		$message = sprintf(
			/* translators: 1: Script name 2: Status 3: Execution time 4: Memory usage 5: Result URL */
			__( "Script: %1\$s\nStatus: %2\$s\nExecution Time: %3\$.2f seconds\nMemory Usage: %4\$s\n\nView Full Result:\n%5\$s", 'test-script-manager' ),
			$script_name,
			ucfirst( $status ),
			(float) $execution['execution_time'],
			size_format( (int) $execution['memory_usage'] ),
			$result_url
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Get script name for an execution log.
	 *
	 * @param int $execution_id Execution log ID.
	 * @return string Script name or 'Unknown Script'.
	 */
	private static function get_script_name_for_execution( $execution_id ) {
		global $wpdb;

		$logs_table    = \TSM\Database::get_table_name( \TSM\Database::TABLE_EXECUTION_LOGS );
		$scripts_table = \TSM\Database::get_table_name( \TSM\Database::TABLE_SCRIPTS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$script_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT s.name FROM $logs_table e LEFT JOIN $scripts_table s ON e.script_id = s.id WHERE e.id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$execution_id
			)
		);

		return $script_name ? $script_name : __( 'Unknown Script', 'test-script-manager' );
	}
}
