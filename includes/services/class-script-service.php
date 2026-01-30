<?php
/**
 * Script Service class.
 *
 * Handles CRUD operations for test scripts with dual-write storage.
 * Scripts are saved to both database and filesystem.
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
 * Script Service class.
 *
 * Provides CRUD operations for test scripts:
 * - Create: Insert to database + write to filesystem
 * - Read: Fetch from database
 * - Update: Update database + sync filesystem
 * - Delete: Remove from database + delete file
 * - Search: Query scripts by keyword/language
 *
 * Implements rollback mechanism: if filesystem write fails,
 * database changes are reverted.
 */
class ScriptService {

	/**
	 * Create a new script.
	 *
	 * Inserts script into database and writes to filesystem.
	 * If filesystem write fails, database record is deleted (rollback).
	 *
	 * @param string $name     Script display name.
	 * @param string $slug     Script slug (used as filename).
	 * @param string $code     Script code content.
	 * @param string $language Script language (default: 'php').
	 * @return int|\WP_Error Script ID on success, WP_Error on failure.
	 */
	public static function create( $name, $slug, $code, $language = 'php' ) {
		global $wpdb;

		// Validate inputs.
		$validation = self::validate_script_data( $name, $slug, $code );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Sanitize inputs.
		$name     = sanitize_text_field( $name );
		$slug     = sanitize_title( $slug );
		$language = sanitize_text_field( $language );

		// Check if slug already exists.
		$existing = self::get_by_slug( $slug );
		if ( null !== $existing ) {
			return new \WP_Error(
				'tsm_duplicate_slug',
				sprintf(
					/* translators: %s: Script slug */
					__( 'A script with slug "%s" already exists.', 'test-script-manager' ),
					$slug
				)
			);
		}

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );
		$now        = current_time( 'mysql' );

		// Insert into database.
		$result = $wpdb->insert(
			$table_name,
			array(
				'name'       => $name,
				'slug'       => $slug,
				'code'       => $code,
				'language'   => $language,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'tsm_db_insert_failed',
				__( 'Failed to insert script into database.', 'test-script-manager' )
			);
		}

		$script_id = $wpdb->insert_id;

		// Write to filesystem.
		$file_result = StorageService::write_to_filesystem( $slug, $code );

		if ( is_wp_error( $file_result ) ) {
			// Rollback: delete from database.
			$wpdb->delete(
				$table_name,
				array( 'id' => $script_id ),
				array( '%d' )
			);

			return new \WP_Error(
				'tsm_file_write_rollback',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to write script file. Database changes rolled back. Error: %s', 'test-script-manager' ),
					$file_result->get_error_message()
				)
			);
		}

		return $script_id;
	}

	/**
	 * Get a script by ID.
	 *
	 * @param int $id Script ID.
	 * @return array|null Script data as associative array, or null if not found.
	 */
	public static function get( $id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$script = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		if ( null === $script ) {
			return null;
		}

		// Add script URL.
		$script['script_url'] = StorageService::get_script_url( $script['slug'] );

		return $script;
	}

	/**
	 * Get a script by slug.
	 *
	 * @param string $slug Script slug.
	 * @return array|null Script data as associative array, or null if not found.
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$script = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE slug = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$slug
			),
			ARRAY_A
		);

		if ( null === $script ) {
			return null;
		}

		// Add script URL.
		$script['script_url'] = StorageService::get_script_url( $script['slug'] );

		return $script;
	}

	/**
	 * Update a script.
	 *
	 * Updates database record and syncs filesystem.
	 * If slug changes, old file is deleted and new file is created.
	 *
	 * @param int   $id   Script ID.
	 * @param array $data Fields to update (name, slug, code, language).
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		// Get existing script.
		$existing = self::get( $id );
		if ( null === $existing ) {
			return new \WP_Error(
				'tsm_script_not_found',
				__( 'Script not found.', 'test-script-manager' )
			);
		}

		$old_slug = $existing['slug'];

		// CREATE VERSION SNAPSHOT (Phase 6, Plan 01).
		// Only create version if code is changing.
		$new_code = isset( $data['code'] ) ? $data['code'] : $existing['code'];
		if ( $new_code !== $existing['code'] ) {
			VersionService::create_version( $id, $existing['code'] );
		}

		// Merge with existing data.
		$name     = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $existing['name'];
		$slug     = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : $existing['slug'];
		$code     = isset( $data['code'] ) ? $data['code'] : $existing['code'];
		$language = isset( $data['language'] ) ? sanitize_text_field( $data['language'] ) : $existing['language'];

		// Validate.
		$validation = self::validate_script_data( $name, $slug, $code );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check if new slug conflicts with another script.
		if ( $slug !== $old_slug ) {
			$slug_exists = self::get_by_slug( $slug );
			if ( null !== $slug_exists ) {
				return new \WP_Error(
					'tsm_duplicate_slug',
					sprintf(
						/* translators: %s: Script slug */
						__( 'A script with slug "%s" already exists.', 'test-script-manager' ),
						$slug
					)
				);
			}
		}

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );
		$now        = current_time( 'mysql' );

		// Update database.
		$result = $wpdb->update(
			$table_name,
			array(
				'name'       => $name,
				'slug'       => $slug,
				'code'       => $code,
				'language'   => $language,
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'tsm_db_update_failed',
				__( 'Failed to update script in database.', 'test-script-manager' )
			);
		}

		// Handle filesystem changes.
		if ( $slug !== $old_slug ) {
			// Slug changed: delete old file, create new file.
			StorageService::delete_from_filesystem( $old_slug );
		}

		// Write new/updated file.
		$file_result = StorageService::write_to_filesystem( $slug, $code );

		if ( is_wp_error( $file_result ) ) {
			// Note: We don't rollback DB changes here because the script
			// is still valid, just the file sync failed. Log the error.
			// In a future version, we could implement a sync status field.
			return new \WP_Error(
				'tsm_file_sync_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Script updated but file sync failed: %s', 'test-script-manager' ),
					$file_result->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Delete a script.
	 *
	 * Removes script from database and deletes the file.
	 *
	 * @param int $id Script ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function delete( $id ) {
		global $wpdb;

		// Get existing script for slug.
		$existing = self::get( $id );
		if ( null === $existing ) {
			return new \WP_Error(
				'tsm_script_not_found',
				__( 'Script not found.', 'test-script-manager' )
			);
		}

		$slug       = $existing['slug'];
		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );

		// Delete all versions for this script (Phase 6, Plan 01).
		VersionService::delete_all_versions( $id );

		// Delete from database.
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'tsm_db_delete_failed',
				__( 'Failed to delete script from database.', 'test-script-manager' )
			);
		}

		// Delete from filesystem.
		$file_result = StorageService::delete_from_filesystem( $slug );

		if ( is_wp_error( $file_result ) ) {
			// Database record is already deleted, log file deletion failure.
			// This is acceptable - the file will be orphaned but won't cause issues.
			return new \WP_Error(
				'tsm_file_delete_warning',
				sprintf(
					/* translators: %s: Error message */
					__( 'Script deleted but file removal failed: %s', 'test-script-manager' ),
					$file_result->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Search scripts by keyword and/or language.
	 *
	 * @param string $keyword  Search keyword (matches name or code).
	 * @param string $language Filter by language (empty for all).
	 * @param int    $limit    Maximum results (default: 100).
	 * @param int    $offset   Offset for pagination (default: 0).
	 * @return array Array of script data.
	 */
	public static function search( $keyword = '', $language = '', $limit = 100, $offset = 0 ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );

		$where_clauses = array();
		$where_values  = array();

		// Keyword search.
		if ( ! empty( $keyword ) ) {
			$like            = '%' . $wpdb->esc_like( $keyword ) . '%';
			$where_clauses[] = '(name LIKE %s OR code LIKE %s)';
			$where_values[]  = $like;
			$where_values[]  = $like;
		}

		// Language filter.
		if ( ! empty( $language ) ) {
			$where_clauses[] = 'language = %s';
			$where_values[]  = $language;
		}

		// Build query.
		$sql = "SELECT * FROM $table_name"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $where_clauses ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		$sql .= ' ORDER BY updated_at DESC LIMIT %d OFFSET %d';

		$where_values[] = $limit;
		$where_values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $where_values ),
			ARRAY_A
		);

		if ( null === $results ) {
			return array();
		}

		// Add script URLs.
		foreach ( $results as &$script ) {
			$script['script_url'] = StorageService::get_script_url( $script['slug'] );
		}

		return $results;
	}

	/**
	 * List all scripts.
	 *
	 * @param int $limit  Maximum results (default: 100).
	 * @param int $offset Offset for pagination (default: 0).
	 * @return array Array of script data.
	 */
	public static function list_all( $limit = 100, $offset = 0 ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name ORDER BY updated_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			),
			ARRAY_A
		);

		if ( null === $results ) {
			return array();
		}

		// Add script URLs.
		foreach ( $results as &$script ) {
			$script['script_url'] = StorageService::get_script_url( $script['slug'] );
		}

		return $results;
	}

	/**
	 * Count total scripts.
	 *
	 * @param string $keyword  Optional search keyword.
	 * @param string $language Optional language filter.
	 * @return int Total count.
	 */
	public static function count( $keyword = '', $language = '' ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );

		$where_clauses = array();
		$where_values  = array();

		if ( ! empty( $keyword ) ) {
			$like            = '%' . $wpdb->esc_like( $keyword ) . '%';
			$where_clauses[] = '(name LIKE %s OR code LIKE %s)';
			$where_values[]  = $like;
			$where_values[]  = $like;
		}

		if ( ! empty( $language ) ) {
			$where_clauses[] = 'language = %s';
			$where_values[]  = $language;
		}

		$sql = "SELECT COUNT(*) FROM $table_name"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $where_clauses ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where_values ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Update script's last executed timestamp.
	 *
	 * @param int $id Script ID.
	 * @return bool True on success.
	 */
	public static function update_last_executed( $id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_SCRIPTS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array( 'last_executed_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Validate script data.
	 *
	 * @param string $name Script name.
	 * @param string $slug Script slug.
	 * @param string $code Script code.
	 * @return true|\WP_Error True if valid, WP_Error if invalid.
	 */
	private static function validate_script_data( $name, $slug, $code ) {
		// Name validation.
		if ( empty( $name ) ) {
			return new \WP_Error(
				'tsm_invalid_name',
				__( 'Script name is required.', 'test-script-manager' )
			);
		}

		if ( strlen( $name ) > 255 ) {
			return new \WP_Error(
				'tsm_name_too_long',
				__( 'Script name must be 255 characters or less.', 'test-script-manager' )
			);
		}

		// Slug validation.
		if ( empty( $slug ) ) {
			return new \WP_Error(
				'tsm_invalid_slug',
				__( 'Script slug is required.', 'test-script-manager' )
			);
		}

		$sanitized_slug = sanitize_title( $slug );
		if ( empty( $sanitized_slug ) ) {
			return new \WP_Error(
				'tsm_invalid_slug_format',
				__( 'Script slug must contain valid characters (a-z, 0-9, hyphens).', 'test-script-manager' )
			);
		}

		// Code validation.
		if ( empty( $code ) ) {
			return new \WP_Error(
				'tsm_invalid_code',
				__( 'Script code is required.', 'test-script-manager' )
			);
		}

		return true;
	}
}
