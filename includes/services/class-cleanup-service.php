<?php
/**
 * Cleanup Service class.
 *
 * Handles version history cleanup with retention policy.
 * Automatically cleans up old versions via WP-Cron.
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
 * Cleanup Service class.
 *
 * Provides version cleanup operations:
 * - Retention policy: Keep last 50 versions OR last 30 days (whichever keeps more)
 * - Configurable via WordPress filters
 * - Scheduled via WP-Cron (daily)
 */
class CleanupService {

	/**
	 * Clean up old versions for a script.
	 * Keeps last 50 versions OR versions from last 30 days (whichever keeps more).
	 *
	 * @param int $script_id Script ID.
	 * @return int Number of versions deleted.
	 */
	public static function cleanup_versions( $script_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

		// Configurable via filters.
		$keep_count  = apply_filters( 'tsm_versions_to_keep', 50, $script_id );
		$keep_days   = apply_filters( 'tsm_version_retention_days', 30, $script_id );
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$keep_days} days" ) );

		// Delete versions that are:
		// - NOT in the last N versions (by count)
		// - AND older than retention period (by time)
		// This keeps versions that satisfy EITHER condition.
		$sql = "DELETE FROM {$table_name}
		        WHERE script_id = %d
		        AND id NOT IN (
		            SELECT id FROM (
		                SELECT id FROM {$table_name}
		                WHERE script_id = %d
		                ORDER BY created_at DESC
		                LIMIT %d
		            ) AS keep_by_count
		        )
		        AND created_at < %s";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare( $sql, $script_id, $script_id, $keep_count, $cutoff_date )
		);

		return (int) $deleted;
	}

	/**
	 * Clean up versions for all scripts.
	 *
	 * @return int Total versions deleted.
	 */
	public static function cleanup_all_scripts() {
		$scripts       = ScriptService::list_all( 999999 );
		$total_deleted = 0;

		foreach ( $scripts as $script ) {
			$deleted        = self::cleanup_versions( $script['id'] );
			$total_deleted += $deleted;
		}

		return $total_deleted;
	}

	/**
	 * Register WP-Cron cleanup event.
	 */
	public static function register_cron() {
		if ( ! wp_next_scheduled( 'tsm_cleanup_versions' ) ) {
			wp_schedule_event( time(), 'daily', 'tsm_cleanup_versions' );
		}

		add_action( 'tsm_cleanup_versions', array( __CLASS__, 'run_scheduled_cleanup' ) );
	}

	/**
	 * Unregister WP-Cron cleanup event.
	 */
	public static function unregister_cron() {
		$timestamp = wp_next_scheduled( 'tsm_cleanup_versions' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsm_cleanup_versions' );
		}
	}

	/**
	 * Run scheduled cleanup (called by WP-Cron).
	 */
	public static function run_scheduled_cleanup() {
		$deleted = self::cleanup_all_scripts();

		if ( $deleted > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[TSM] Version cleanup: deleted %d old versions',
					$deleted
				)
			);
		}
	}
}
