<?php
/**
 * Versions REST API class.
 *
 * Provides REST API endpoints for version history operations.
 *
 * @package TestScriptManager
 */

namespace TSM\API;

use TSM\Security;
use TSM\Services\VersionService;
use TSM\Services\ScriptService;
use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versions API class.
 *
 * Registers and handles REST API endpoints for version history:
 * - GET    /scripts/{id}/versions           - List versions
 * - GET    /scripts/{id}/versions/{vid}     - Get single version
 * - GET    /scripts/{id}/versions/compare   - Compare two versions
 * - POST   /scripts/{id}/versions/{vid}/restore - Restore to version
 *
 * All endpoints require manage_options capability.
 */
class Versions_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'test-script-manager/v1';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// GET /scripts/{id}/versions - List versions
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/versions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_versions' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'limit' => array(
						'default'           => 50,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /scripts/{id}/versions/{vid} - Get single version with code
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/versions/(?P<vid>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_version' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'vid' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /scripts/{id}/versions/compare - Compare two versions
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/versions/compare',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'compare_versions' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'v1' => array(
						'required' => true,
						'type'     => 'string', // Can be 'current' or version ID
					),
					'v2' => array(
						'required' => true,
						'type'     => 'string', // Can be 'current' or version ID
					),
				),
			)
		);

		// POST /scripts/{id}/versions/{vid}/restore - Restore to version
		register_rest_route(
			self::NAMESPACE,
			'/scripts/(?P<id>\d+)/versions/(?P<vid>\d+)/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'restore_version' ),
				'permission_callback' => array( 'TSM\Security', 'check_admin_permission' ),
				'args'                => array(
					'id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'vid' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle GET /scripts/{id}/versions - List versions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with versions array.
	 */
	public function get_versions( WP_REST_Request $request ) {
		$script_id = $request->get_param( 'id' );
		$limit     = $request->get_param( 'limit' );

		// Verify script exists
		$script = ScriptService::get( $script_id );
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

		$versions = VersionService::get_versions( $script_id, $limit );
		$total    = VersionService::count_versions( $script_id );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'versions' => $versions,
				'total'    => $total,
			),
			200
		);
	}

	/**
	 * Handle GET /scripts/{id}/versions/{vid} - Get single version.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with version data including code.
	 */
	public function get_version( WP_REST_Request $request ) {
		$script_id  = $request->get_param( 'id' );
		$version_id = $request->get_param( 'vid' );

		$version = VersionService::get_version( $version_id );

		if ( null === $version ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Version not found.', 'test-script-manager' ),
					'code'    => 'tsm_version_not_found',
				),
				404
			);
		}

		// Verify version belongs to this script
		if ( (int) $version['script_id'] !== (int) $script_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Version does not belong to this script.', 'test-script-manager' ),
					'code'    => 'tsm_version_mismatch',
				),
				400
			);
		}

		// Get full code
		global $wpdb;
		$table = \TSM\Database::get_table_name( \TSM\Database::TABLE_SCRIPT_VERSIONS );
		$code  = $wpdb->get_var( $wpdb->prepare(
			"SELECT code FROM {$table} WHERE id = %d",
			$version_id
		) );

		$version['code'] = $code;

		return new WP_REST_Response(
			array(
				'success' => true,
				'version' => $version,
			),
			200
		);
	}

	/**
	 * Handle GET /scripts/{id}/versions/compare - Compare two versions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with both versions' code for diff.
	 */
	public function compare_versions( WP_REST_Request $request ) {
		$script_id = $request->get_param( 'id' );
		$v1        = $request->get_param( 'v1' );
		$v2        = $request->get_param( 'v2' );

		// Get script for 'current' reference
		$script = ScriptService::get( $script_id );
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

		// Helper to get code by version ID or 'current'
		$get_code = function( $v ) use ( $script_id, $script ) {
			if ( 'current' === $v ) {
				return array(
					'code'  => $script['code'],
					'label' => __( 'Current', 'test-script-manager' ),
					'time'  => $script['updated_at'],
				);
			}

			global $wpdb;
			$table = \TSM\Database::get_table_name( \TSM\Database::TABLE_SCRIPT_VERSIONS );
			$row   = $wpdb->get_row( $wpdb->prepare(
				"SELECT v.code, v.created_at, v.script_id, u.display_name as author_name
				 FROM {$table} v
				 LEFT JOIN {$wpdb->users} u ON v.created_by = u.ID
				 WHERE v.id = %d",
				absint( $v )
			), ARRAY_A );

			if ( null === $row || (int) $row['script_id'] !== (int) $script_id ) {
				return null;
			}

			return array(
				'code'  => $row['code'],
				'label' => sprintf( __( 'Version %s', 'test-script-manager' ), $v ),
				'time'  => $row['created_at'],
				'author' => $row['author_name'],
			);
		};

		$code1 = $get_code( $v1 );
		$code2 = $get_code( $v2 );

		if ( null === $code1 || null === $code2 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'One or more versions not found.', 'test-script-manager' ),
					'code'    => 'tsm_version_not_found',
				),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'v1'      => $code1,
				'v2'      => $code2,
			),
			200
		);
	}

	/**
	 * Handle POST /scripts/{id}/versions/{vid}/restore - Restore to version.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with success status.
	 */
	public function restore_version( WP_REST_Request $request ) {
		$script_id  = $request->get_param( 'id' );
		$version_id = $request->get_param( 'vid' );

		$result = VersionService::restore_version( $script_id, $version_id );

		if ( is_wp_error( $result ) ) {
			$status = 400;
			if ( in_array( $result->get_error_code(), array( 'tsm_version_not_found', 'tsm_script_not_found' ), true ) ) {
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

		// Get updated script code to return
		$script = ScriptService::get( $script_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Script restored successfully.', 'test-script-manager' ),
				'code'    => $script ? $script['code'] : null,
			),
			200
		);
	}
}
