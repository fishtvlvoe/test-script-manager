<?php
/**
 * Version Service class.
 *
 * Handles version history CRUD operations for test scripts.
 * Automatic version snapshots are created before each script update.
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
 * Version Service class.
 *
 * Provides version control operations:
 * - Create: Snapshot script code with user attribution
 * - Read: Fetch version history for a script
 * - Restore: Rollback script to previous version
 * - Delete: Clean up versions when script is deleted
 */
class VersionService {

	/**
	 * Create a version snapshot.
	 *
	 * @param int    $script_id Script ID.
	 * @param string $code      Code content to snapshot.
	 * @return int|false Version ID on success, false on failure.
	 */
	public static function create_version( $script_id, $code ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

		$result = $wpdb->insert(
			$table_name,
			array(
				'script_id'  => $script_id,
				'code'       => $code,
				'created_at' => current_time( 'mysql' ),
				'created_by' => get_current_user_id() ?: null,
			),
			array( '%d', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get a single version by ID.
	 *
	 * @param int $version_id Version ID.
	 * @return array|null Version data or null if not found.
	 */
	public static function get_version( $version_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT v.*, u.display_name as author_name
				 FROM {$table_name} v
				 LEFT JOIN {$wpdb->users} u ON v.created_by = u.ID
				 WHERE v.id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$version_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get all versions for a script.
	 *
	 * @param int $script_id Script ID.
	 * @param int $limit     Maximum versions to return (default: 50).
	 * @return array Array of version data.
	 */
	public static function get_versions( $script_id, $limit = 50 ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.id, v.script_id, v.created_at, v.created_by,
				        u.display_name as author_name,
				        LENGTH(v.code) as code_length
				 FROM {$table_name} v
				 LEFT JOIN {$wpdb->users} u ON v.created_by = u.ID
				 WHERE v.script_id = %d
				 ORDER BY v.created_at DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$script_id,
				$limit
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Count versions for a script.
	 *
	 * @param int $script_id Script ID.
	 * @return int Version count.
	 */
	public static function count_versions( $script_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE script_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$script_id
			)
		);
	}

	/**
	 * Restore script to a specific version.
	 * Creates a snapshot of current code before restoring.
	 *
	 * @param int $script_id  Script ID.
	 * @param int $version_id Version ID to restore.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function restore_version( $script_id, $version_id ) {
		// Get version to restore.
		$version = self::get_version( $version_id );
		if ( null === $version ) {
			return new \WP_Error(
				'tsm_version_not_found',
				__( 'Version not found.', 'test-script-manager' )
			);
		}

		// Verify version belongs to this script.
		if ( (int) $version['script_id'] !== (int) $script_id ) {
			return new \WP_Error(
				'tsm_version_mismatch',
				__( 'Version does not belong to this script.', 'test-script-manager' )
			);
		}

		// Get full version code.
		global $wpdb;
		$table_name   = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );
		$version_code = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT code FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$version_id
			)
		);

		if ( null === $version_code ) {
			return new \WP_Error(
				'tsm_version_code_not_found',
				__( 'Version code not found.', 'test-script-manager' )
			);
		}

		// Get current script.
		$script = ScriptService::get( $script_id );
		if ( null === $script ) {
			return new \WP_Error(
				'tsm_script_not_found',
				__( 'Script not found.', 'test-script-manager' )
			);
		}

		// Create snapshot of current code before restore.
		self::create_version( $script_id, $script['code'] );

		// Update script with version code (bypass normal update to avoid double versioning).
		$scripts_table = Database::get_table_name( Database::TABLE_SCRIPTS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$scripts_table,
			array(
				'code'       => $version_code,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $script_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'tsm_restore_failed',
				__( 'Failed to restore script.', 'test-script-manager' )
			);
		}

		// Sync to filesystem.
		StorageService::write_to_filesystem( $script['slug'], $version_code );

		return true;
	}

	/**
	 * Delete all versions for a script.
	 * Called when script is deleted.
	 *
	 * @param int $script_id Script ID.
	 * @return int Number of versions deleted.
	 */
	public static function delete_all_versions( $script_id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->delete(
			$table_name,
			array( 'script_id' => $script_id ),
			array( '%d' )
		);
	}
}
