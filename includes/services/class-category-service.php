<?php
/**
 * Category Service class.
 *
 * Handles CRUD operations for script categories and script-category associations.
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
 * Category Service class.
 *
 * Provides CRUD operations for categories:
 * - Create: Insert new category with auto-generated slug
 * - Read: Fetch category by ID or get all categories
 * - Update: Update category name and slug
 * - Delete: Remove category and all script associations
 *
 * Also manages script-category relationships through the tsm_script_tags table.
 */
class CategoryService {

	/**
	 * Create a new category.
	 *
	 * Generates slug from name and inserts into database.
	 *
	 * @param string $name Category name.
	 * @return int|\WP_Error Category ID on success, WP_Error on failure.
	 */
	public static function create( $name ) {
		global $wpdb;

		// Validate name.
		if ( empty( $name ) ) {
			return new \WP_Error(
				'tsm_invalid_category_name',
				__( 'Category name is required.', 'test-script-manager' )
			);
		}

		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $name );

		if ( empty( $slug ) ) {
			return new \WP_Error(
				'tsm_invalid_category_slug',
				__( 'Category name must contain valid characters.', 'test-script-manager' )
			);
		}

		// Check for duplicate name or slug.
		$table_name = Database::get_table_name( Database::TABLE_CATEGORIES );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE name = %s OR slug = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$name,
				$slug
			)
		);

		if ( $existing ) {
			return new \WP_Error(
				'tsm_duplicate_category',
				sprintf(
					/* translators: %s: Category name */
					__( 'A category with name "%s" already exists.', 'test-script-manager' ),
					$name
				)
			);
		}

		// Insert into database.
		$result = $wpdb->insert(
			$table_name,
			array(
				'name'       => $name,
				'slug'       => $slug,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'tsm_db_insert_failed',
				__( 'Failed to create category.', 'test-script-manager' )
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get a category by ID.
	 *
	 * @param int $id Category ID.
	 * @return array|null Category data as associative array, or null if not found.
	 */
	public static function get( $id ) {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_CATEGORIES );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		return $category;
	}

	/**
	 * Get all categories.
	 *
	 * @return array Array of category data, ordered by name ASC.
	 */
	public static function get_all() {
		global $wpdb;

		$table_name = Database::get_table_name( Database::TABLE_CATEGORIES );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$categories = $wpdb->get_results(
			"SELECT * FROM $table_name ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $categories ? $categories : array();
	}

	/**
	 * Update a category.
	 *
	 * @param int    $id   Category ID.
	 * @param string $name New category name.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update( $id, $name ) {
		global $wpdb;

		// Validate name.
		if ( empty( $name ) ) {
			return new \WP_Error(
				'tsm_invalid_category_name',
				__( 'Category name is required.', 'test-script-manager' )
			);
		}

		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $name );

		if ( empty( $slug ) ) {
			return new \WP_Error(
				'tsm_invalid_category_slug',
				__( 'Category name must contain valid characters.', 'test-script-manager' )
			);
		}

		// Check if category exists.
		$existing = self::get( $id );
		if ( null === $existing ) {
			return new \WP_Error(
				'tsm_category_not_found',
				__( 'Category not found.', 'test-script-manager' )
			);
		}

		$table_name = Database::get_table_name( Database::TABLE_CATEGORIES );

		// Check for duplicate name or slug (excluding current category).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE (name = %s OR slug = %s) AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$name,
				$slug,
				$id
			)
		);

		if ( $duplicate ) {
			return new \WP_Error(
				'tsm_duplicate_category',
				sprintf(
					/* translators: %s: Category name */
					__( 'A category with name "%s" already exists.', 'test-script-manager' ),
					$name
				)
			);
		}

		// Update database.
		$result = $wpdb->update(
			$table_name,
			array(
				'name' => $name,
				'slug' => $slug,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'tsm_db_update_failed',
				__( 'Failed to update category.', 'test-script-manager' )
			);
		}

		return true;
	}

	/**
	 * Delete a category.
	 *
	 * Also deletes all script-category associations for this category.
	 *
	 * @param int $id Category ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function delete( $id ) {
		global $wpdb;

		// Check if category exists.
		$existing = self::get( $id );
		if ( null === $existing ) {
			return new \WP_Error(
				'tsm_category_not_found',
				__( 'Category not found.', 'test-script-manager' )
			);
		}

		// Delete all script associations first.
		$tags_table = Database::get_table_name( Database::TABLE_SCRIPT_TAGS );
		$wpdb->delete(
			$tags_table,
			array( 'category_id' => $id ),
			array( '%d' )
		);

		// Delete category.
		$table_name = Database::get_table_name( Database::TABLE_CATEGORIES );
		$result     = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error(
				'tsm_db_delete_failed',
				__( 'Failed to delete category.', 'test-script-manager' )
			);
		}

		return true;
	}

	/**
	 * Get categories for a script.
	 *
	 * @param int $script_id Script ID.
	 * @return array Array of category data.
	 */
	public static function get_script_categories( $script_id ) {
		global $wpdb;

		$tags_table       = Database::get_table_name( Database::TABLE_SCRIPT_TAGS );
		$categories_table = Database::get_table_name( Database::TABLE_CATEGORIES );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.* FROM $categories_table c
				INNER JOIN $tags_table t ON c.id = t.category_id
				WHERE t.script_id = %d
				ORDER BY c.name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$script_id
			),
			ARRAY_A
		);

		return $categories ? $categories : array();
	}

	/**
	 * Set categories for a script.
	 *
	 * Replaces all existing category associations with the provided list.
	 *
	 * @param int   $script_id    Script ID.
	 * @param array $category_ids Array of category IDs.
	 * @return true True on success.
	 */
	public static function set_script_categories( $script_id, $category_ids ) {
		global $wpdb;

		$tags_table = Database::get_table_name( Database::TABLE_SCRIPT_TAGS );

		// Delete all existing associations.
		$wpdb->delete(
			$tags_table,
			array( 'script_id' => $script_id ),
			array( '%d' )
		);

		// Insert new associations.
		if ( ! empty( $category_ids ) ) {
			foreach ( $category_ids as $category_id ) {
				$wpdb->insert(
					$tags_table,
					array(
						'script_id'   => $script_id,
						'category_id' => absint( $category_id ),
					),
					array( '%d', '%d' )
				);
			}
		}

		return true;
	}

	/**
	 * Delete all category associations for a script.
	 *
	 * Used when deleting a script.
	 *
	 * @param int $script_id Script ID.
	 * @return true True on success.
	 */
	public static function delete_script_categories( $script_id ) {
		global $wpdb;

		$tags_table = Database::get_table_name( Database::TABLE_SCRIPT_TAGS );

		$wpdb->delete(
			$tags_table,
			array( 'script_id' => $script_id ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get script IDs by category.
	 *
	 * @param int $category_id Category ID.
	 * @return array Array of script IDs.
	 */
	public static function get_scripts_by_category( $category_id ) {
		global $wpdb;

		$tags_table = Database::get_table_name( Database::TABLE_SCRIPT_TAGS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$script_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT script_id FROM $tags_table WHERE category_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$category_id
			)
		);

		return $script_ids ? $script_ids : array();
	}
}
