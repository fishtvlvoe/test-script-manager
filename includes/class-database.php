<?php
/**
 * Database class.
 *
 * Handles database table creation and schema management.
 *
 * @package TestScriptManager
 */

namespace TSM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class.
 *
 * Creates and manages custom database tables for Test Script Manager.
 * Uses WordPress dbDelta() for safe table creation and updates.
 */
class Database {

	/**
	 * Database schema version.
	 * Increment this when making schema changes.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option key for storing database version.
	 */
	const DB_VERSION_OPTION = 'tsm_db_version';

	/**
	 * Table names (without prefix).
	 */
	const TABLE_SCRIPTS          = 'tsm_scripts';
	const TABLE_SCRIPT_VERSIONS  = 'tsm_script_versions';
	const TABLE_EXECUTION_LOGS   = 'tsm_execution_logs';
	const TABLE_CATEGORIES       = 'tsm_categories';
	const TABLE_SCRIPT_TAGS      = 'tsm_script_tags';

	/**
	 * Create all database tables.
	 *
	 * Called on plugin activation. Uses version checking to avoid
	 * unnecessary table recreation.
	 */
	public static function create_tables() {
		global $wpdb;

		$installed_version = get_option( self::DB_VERSION_OPTION, '0' );

		// Skip if already at current version.
		if ( version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Create all tables.
		self::create_scripts_table( $charset_collate );
		self::create_script_versions_table( $charset_collate );
		self::create_execution_logs_table( $charset_collate );
		self::create_categories_table( $charset_collate );
		self::create_script_tags_table( $charset_collate );

		// Update version.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Get full table name with prefix.
	 *
	 * @param string $table Table name without prefix.
	 * @return string Full table name with WordPress prefix.
	 */
	public static function get_table_name( $table ) {
		global $wpdb;
		return $wpdb->prefix . $table;
	}

	/**
	 * Create scripts table.
	 *
	 * Stores script metadata and code.
	 *
	 * @param string $charset_collate WordPress charset and collation.
	 */
	private static function create_scripts_table( $charset_collate ) {
		global $wpdb;

		$table_name = self::get_table_name( self::TABLE_SCRIPTS );

		// Note: dbDelta requires specific formatting:
		// - Two spaces between PRIMARY KEY and (id)
		// - Each field on its own line
		// - Use KEY not INDEX
		// - No backticks around field names.
		$sql = "CREATE TABLE $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			code longtext NOT NULL,
			language varchar(20) NOT NULL DEFAULT 'php',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_executed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY created_at (created_at),
			KEY last_executed_at (last_executed_at)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create script versions table.
	 *
	 * Stores version history for scripts.
	 *
	 * @param string $charset_collate WordPress charset and collation.
	 */
	private static function create_script_versions_table( $charset_collate ) {
		global $wpdb;

		$table_name = self::get_table_name( self::TABLE_SCRIPT_VERSIONS );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			script_id bigint(20) UNSIGNED NOT NULL,
			code longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY script_id (script_id),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create execution logs table.
	 *
	 * Stores script execution history and results.
	 *
	 * @param string $charset_collate WordPress charset and collation.
	 */
	private static function create_execution_logs_table( $charset_collate ) {
		global $wpdb;

		$table_name = self::get_table_name( self::TABLE_EXECUTION_LOGS );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			script_id bigint(20) UNSIGNED NOT NULL,
			output longtext,
			error text,
			execution_time float NOT NULL DEFAULT 0,
			memory_usage bigint(20) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'success',
			executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY script_id (script_id),
			KEY executed_at (executed_at),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create categories table.
	 *
	 * Stores script categories/tags.
	 *
	 * @param string $charset_collate WordPress charset and collation.
	 */
	private static function create_categories_table( $charset_collate ) {
		global $wpdb;

		$table_name = self::get_table_name( self::TABLE_CATEGORIES );

		$sql = "CREATE TABLE $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create script tags table.
	 *
	 * Many-to-many relationship between scripts and categories.
	 *
	 * @param string $charset_collate WordPress charset and collation.
	 */
	private static function create_script_tags_table( $charset_collate ) {
		global $wpdb;

		$table_name = self::get_table_name( self::TABLE_SCRIPT_TAGS );

		$sql = "CREATE TABLE $table_name (
			script_id bigint(20) UNSIGNED NOT NULL,
			category_id bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (script_id, category_id),
			KEY category_id (category_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Drop all database tables.
	 *
	 * Used for complete cleanup on uninstall.
	 * Note: This is NOT called on deactivation to preserve user data.
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			self::TABLE_SCRIPT_TAGS,
			self::TABLE_EXECUTION_LOGS,
			self::TABLE_SCRIPT_VERSIONS,
			self::TABLE_CATEGORIES,
			self::TABLE_SCRIPTS,
		);

		foreach ( $tables as $table ) {
			$table_name = self::get_table_name( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		}

		// Remove version option.
		delete_option( self::DB_VERSION_OPTION );
	}
}
