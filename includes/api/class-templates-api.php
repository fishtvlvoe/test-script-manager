<?php
/**
 * Templates REST API class.
 *
 * Provides REST API endpoints for template management.
 *
 * @package TestScriptManager
 */

namespace TSM\API;

use TSM\Services\TemplateService;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Templates API class.
 *
 * Registers REST API endpoints for:
 * - Listing all templates
 * - Getting single template
 * - Creating script from template
 */
class Templates_API {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'test-script-manager/v1';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// GET /templates - List all templates.
		register_rest_route(
			self::NAMESPACE,
			'/templates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_templates' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// GET /templates/{id} - Get single template.
		register_rest_route(
			self::NAMESPACE,
			'/templates/(?P<id>[a-z0-9-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_template' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// POST /templates/{id}/create - Create script from template.
		register_rest_route(
			self::NAMESPACE,
			'/templates/(?P<id>[a-z0-9-]+)/create',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_from_template' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'name' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check if user has permission.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all templates.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_templates() {
		$templates = TemplateService::get_templates();

		// Convert to array format with IDs for frontend consumption.
		$result = array();
		foreach ( $templates as $id => $template ) {
			$result[] = array_merge( array( 'id' => $id ), $template );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get a single template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_template( $request ) {
		$id = $request->get_param( 'id' );

		$template = TemplateService::get_template( $id );

		if ( null === $template ) {
			return new \WP_Error(
				'tsm_template_not_found',
				__( 'Template not found.', 'test-script-manager' ),
				array( 'status' => 404 )
			);
		}

		// Include ID in response.
		$template['id'] = $id;

		return rest_ensure_response( $template );
	}

	/**
	 * Create a script from a template.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_from_template( $request ) {
		$template_id = $request->get_param( 'id' );
		$name        = $request->get_param( 'name' );

		$result = TemplateService::create_from_template( $template_id, $name );

		if ( is_wp_error( $result ) ) {
			$status = 'tsm_template_not_found' === $result->get_error_code() ? 404 : 400;
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		// Get the created script.
		$script = \TSM\Services\ScriptService::get( $result );

		return rest_ensure_response(
			array(
				'success'   => true,
				'script_id' => $result,
				'script'    => $script,
			)
		);
	}
}
