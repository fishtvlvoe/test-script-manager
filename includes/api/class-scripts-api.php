<?php
/**
 * Scripts REST API class.
 *
 * Provides REST API endpoints for script CRUD operations.
 *
 * @package TestScriptManager
 */

namespace TSM\API;

use TSM\Security;
use TSM\Services\ScriptService;
use TSM\Services\ExecutionService;
use TSM\Services\CategoryService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scripts API class.
 *
 * Registers and handles REST API endpoints for script management:
 * - POST   /scripts      - Create a new script
 * - GET    /scripts      - List all scripts (with optional search)
 * - GET    /scripts/{id} - Get a single script
 * - PUT    /scripts/{id} - Update a script
 * - DELETE /scripts/{id} - Delete a script
 *
 * All endpoints require manage_options capability.
 */
class Scripts_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'test-script-manager/v1';

	/**
	 * Register REST API routes.
	 *
	 * Called via rest_api_init hook.
	 */
	public function register_routes() {
		// POST /scripts - Create a new script.
		register_rest_route(
			self::NAMESPACE,
			'/scripts',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_script' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'name'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'slug'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
					'code'     => array(
						'required' => true,
						'type'     => 'string',
					),
					'language' => array(
						'default'           => 'php',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /scripts - List scripts (with optional search).
		register_rest_route(
			self::NAMESPACE,
			'/scripts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_scripts' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'search'   => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'language' => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'    => array(
						'default'           => 100,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'offset'   => array(
						'default'           => 0,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /scripts/{id} - Get a single script.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_script' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// PUT /scripts/{id} - Update a script.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_script' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id'       => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'name'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'slug'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
					'code'     => array(
						'type' => 'string',
					),
					'language' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// DELETE /scripts/{id} - Delete a script.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_script' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// POST /scripts/{id}/execute - Execute a script.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'execute_script' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'timeout' => array(
						'default'           => 30,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'minimum'           => 1,
						'maximum'           => 300,
					),
				),
			)
		);

		// POST /scripts/bulk - Bulk operations on multiple scripts.
		register_rest_route(
			self::NAMESPACE,
			'/scripts/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_action' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'action'      => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'delete', 'set_category' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'script_ids'  => array(
						'required'          => true,
						'type'              => 'array',
						'items'             => array( 'type' => 'integer' ),
						'sanitize_callback' => function ( $ids ) {
							return array_map( 'absint', (array) $ids );
						},
					),
					'category_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle POST /scripts - Create a new script.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with script ID or error.
	 */
	public function create_script( WP_REST_Request $request ) {
		$name     = $request->get_param( 'name' );
		$slug     = $request->get_param( 'slug' );
		$code     = $request->get_param( 'code' );
		$language = $request->get_param( 'language' );

		$result = ScriptService::create( $name, $slug, $code, $language );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'script_id' => $result,
			),
			201
		);
	}

	/**
	 * Handle GET /scripts - List scripts.
	 *
	 * If search parameter is provided, searches scripts.
	 * Otherwise, lists all scripts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with scripts array.
	 */
	public function get_scripts( WP_REST_Request $request ) {
		$search   = $request->get_param( 'search' );
		$language = $request->get_param( 'language' );
		$limit    = $request->get_param( 'limit' );
		$offset   = $request->get_param( 'offset' );

		// Use search if keyword provided, otherwise list all.
		if ( ! empty( $search ) || ! empty( $language ) ) {
			$scripts = ScriptService::search( $search, $language, $limit, $offset );
			$total   = ScriptService::count( $search, $language );
		} else {
			$scripts = ScriptService::list_all( $limit, $offset );
			$total   = ScriptService::count();
		}

		return new WP_REST_Response(
			array(
				'scripts' => $scripts,
				'total'   => $total,
				'limit'   => $limit,
				'offset'  => $offset,
			),
			200
		);
	}

	/**
	 * Handle GET /scripts/{id} - Get a single script.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with script data or error.
	 */
	public function get_script( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$script = ScriptService::get( $id );

		if ( null === $script ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Script not found.', 'test-script-manager' ),
					'code'    => 'tsm_script_not_found',
				),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'script'  => $script,
			),
			200
		);
	}

	/**
	 * Handle PUT /scripts/{id} - Update a script.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with success status or error.
	 */
	public function update_script( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		// Build update data from provided parameters.
		$data = array();

		$fields = array( 'name', 'slug', 'code', 'language' );
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = $value;
			}
		}

		if ( empty( $data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'No fields to update.', 'test-script-manager' ),
					'code'    => 'tsm_no_update_fields',
				),
				400
			);
		}

		$result = ScriptService::update( $id, $data );

		if ( is_wp_error( $result ) ) {
			// Determine HTTP status code based on error.
			$status = 400;
			if ( 'tsm_script_not_found' === $result->get_error_code() ) {
				$status = 404;
			}

			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				$status
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Handle DELETE /scripts/{id} - Delete a script.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with success status or error.
	 */
	public function delete_script( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$result = ScriptService::delete( $id );

		if ( is_wp_error( $result ) ) {
			// Determine HTTP status code based on error.
			$status = 400;
			if ( 'tsm_script_not_found' === $result->get_error_code() ) {
				$status = 404;
			}

			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				$status
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Handle POST /scripts/{id}/execute - Execute a script.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with execution result or error.
	 */
	public function execute_script( WP_REST_Request $request ) {
		$id      = $request->get_param( 'id' );
		$timeout = $request->get_param( 'timeout' );

		// Execute script.
		$result = ExecutionService::execute( $id, $timeout );

		// Handle WP_Error from ExecutionService.
		if ( is_wp_error( $result ) ) {
			$status = 400;
			if ( 'tsm_script_not_found' === $result->get_error_code() ||
				 'tsm_file_not_found' === $result->get_error_code() ) {
				$status = 404;
			}

			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				$status
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'result'  => $result,
			),
			200
		);
	}

	/**
	 * Handle POST /scripts/bulk - Bulk operations on multiple scripts.
	 *
	 * Supports actions:
	 * - delete: Delete multiple scripts
	 * - set_category: Assign a category to multiple scripts
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with success/failed counts or error.
	 */
	public function bulk_action( WP_REST_Request $request ) {
		$action     = $request->get_param( 'action' );
		$script_ids = $request->get_param( 'script_ids' );

		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		if ( empty( $script_ids ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'No scripts selected.', 'test-script-manager' ),
					'code'    => 'tsm_no_scripts',
				),
				400
			);
		}

		switch ( $action ) {
			case 'delete':
				foreach ( $script_ids as $id ) {
					$result = ScriptService::delete( $id );
					if ( is_wp_error( $result ) ) {
						$results['failed']++;
						$results['errors'][] = $id . ': ' . $result->get_error_message();
					} else {
						$results['success']++;
					}
				}
				break;

			case 'set_category':
				$category_id = $request->get_param( 'category_id' );
				if ( ! $category_id ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'error'   => __( 'Category ID is required.', 'test-script-manager' ),
							'code'    => 'tsm_no_category',
						),
						400
					);
				}

				foreach ( $script_ids as $id ) {
					$result = CategoryService::set_script_categories( $id, array( $category_id ) );
					if ( is_wp_error( $result ) ) {
						$results['failed']++;
						$results['errors'][] = $id . ': ' . $result->get_error_message();
					} else {
						$results['success']++;
					}
				}
				break;
		}

		return new WP_REST_Response( $results, 200 );
	}
}
