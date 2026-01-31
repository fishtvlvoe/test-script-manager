<?php
/**
 * Categories REST API class.
 *
 * Provides REST API endpoints for category management.
 *
 * @package TestScriptManager
 */

namespace TSM\API;

use TSM\Services\CategoryService;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Categories API class.
 *
 * Registers REST API endpoints for:
 * - Listing all categories
 * - Creating new categories
 * - Getting single category
 * - Updating categories
 * - Deleting categories
 * - Managing script-category associations
 */
class Categories_API {

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
		// GET /categories - List all categories.
		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /categories - Create new category.
		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_category' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'name' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /categories/{id} - Get single category.
		register_rest_route(
			self::NAMESPACE,
			'/categories/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_category' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// PUT /categories/{id} - Update category.
		register_rest_route(
			self::NAMESPACE,
			'/categories/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_category' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'name' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// DELETE /categories/{id} - Delete category.
		register_rest_route(
			self::NAMESPACE,
			'/categories/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_category' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /scripts/{id}/categories - Get categories for a script.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/categories',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_script_categories' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// PUT /scripts/{id}/categories - Set categories for a script.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/categories',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'set_script_categories' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'           => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'category_ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type' => 'integer',
						),
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
	 * Get all categories.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_categories() {
		$categories = CategoryService::get_all();

		return rest_ensure_response( $categories );
	}

	/**
	 * Create a new category.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_category( $request ) {
		$name = $request->get_param( 'name' );

		$result = CategoryService::create( $name );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$category = CategoryService::get( $result );

		return rest_ensure_response(
			array(
				'success'  => true,
				'category' => $category,
			)
		);
	}

	/**
	 * Get a single category.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_category( $request ) {
		$id = $request->get_param( 'id' );

		$category = CategoryService::get( $id );

		if ( null === $category ) {
			return new \WP_Error(
				'tsm_category_not_found',
				__( 'Category not found.', 'test-script-manager' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $category );
	}

	/**
	 * Update a category.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_category( $request ) {
		$id   = $request->get_param( 'id' );
		$name = $request->get_param( 'name' );

		$result = CategoryService::update( $id, $name );

		if ( is_wp_error( $result ) ) {
			$status = 'tsm_category_not_found' === $result->get_error_code() ? 404 : 400;
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		$category = CategoryService::get( $id );

		return rest_ensure_response(
			array(
				'success'  => true,
				'category' => $category,
			)
		);
	}

	/**
	 * Delete a category.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_category( $request ) {
		$id = $request->get_param( 'id' );

		$result = CategoryService::delete( $id );

		if ( is_wp_error( $result ) ) {
			$status = 'tsm_category_not_found' === $result->get_error_code() ? 404 : 400;
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'deleted' => $id,
			)
		);
	}

	/**
	 * Get categories for a script.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_script_categories( $request ) {
		$script_id = $request->get_param( 'id' );

		$categories = CategoryService::get_script_categories( $script_id );

		return rest_ensure_response( $categories );
	}

	/**
	 * Set categories for a script.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function set_script_categories( $request ) {
		$script_id    = $request->get_param( 'id' );
		$category_ids = $request->get_param( 'category_ids' );

		// Ensure category_ids is an array of integers.
		$category_ids = array_map( 'absint', (array) $category_ids );

		CategoryService::set_script_categories( $script_id, $category_ids );

		$categories = CategoryService::get_script_categories( $script_id );

		return rest_ensure_response(
			array(
				'success'    => true,
				'categories' => $categories,
			)
		);
	}
}
