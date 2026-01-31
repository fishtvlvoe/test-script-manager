<?php
/**
 * Template Service class.
 *
 * Handles template library management and script creation from templates.
 *
 * @package TestScriptManager
 */

namespace TSM\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template Service class.
 *
 * Provides template operations:
 * - List all built-in script templates
 * - Get specific template by ID
 * - Create new script from template
 *
 * Templates are stored in templates/script-templates.json file.
 */
class TemplateService {

	/**
	 * Get all templates.
	 *
	 * Reads and parses the templates JSON file.
	 *
	 * @return array Array of templates keyed by template ID.
	 */
	public static function get_templates() {
		$templates_file = TSM_PLUGIN_DIR . 'templates/script-templates.json';

		if ( ! file_exists( $templates_file ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $templates_file );

		if ( false === $contents ) {
			return array();
		}

		$templates = json_decode( $contents, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array();
		}

		return $templates;
	}

	/**
	 * Get a specific template by ID.
	 *
	 * @param string $template_id Template ID (key in JSON file).
	 * @return array|null Template data or null if not found.
	 */
	public static function get_template( $template_id ) {
		$templates = self::get_templates();

		if ( ! isset( $templates[ $template_id ] ) ) {
			return null;
		}

		return $templates[ $template_id ];
	}

	/**
	 * Create a new script from a template.
	 *
	 * @param string $template_id Template ID.
	 * @param string $name        Script name.
	 * @return int|\WP_Error Script ID on success, WP_Error on failure.
	 */
	public static function create_from_template( $template_id, $name ) {
		// Get the template.
		$template = self::get_template( $template_id );

		if ( null === $template ) {
			return new \WP_Error(
				'tsm_template_not_found',
				sprintf(
					/* translators: %s: Template ID */
					__( 'Template "%s" not found.', 'test-script-manager' ),
					$template_id
				)
			);
		}

		// Validate name.
		if ( empty( $name ) ) {
			return new \WP_Error(
				'tsm_invalid_script_name',
				__( 'Script name is required.', 'test-script-manager' )
			);
		}

		// Generate slug from name.
		$slug = sanitize_title( $name );

		// Get template code and language.
		$code     = isset( $template['code'] ) ? $template['code'] : '';
		$language = isset( $template['language'] ) ? $template['language'] : 'php';

		// Create the script using ScriptService.
		return ScriptService::create( $name, $slug, $code, $language );
	}
}
