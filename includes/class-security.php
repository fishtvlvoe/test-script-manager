<?php
/**
 * Security class for authentication and authorization.
 *
 * Provides static utility methods for permission checking,
 * nonce creation/verification, and error responses.
 *
 * @package TestScriptManager
 */

namespace TSM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security class.
 *
 * Utility class with static methods for security operations.
 * Used by REST API endpoints and admin pages for authentication.
 */
class Security {

	/**
	 * Default nonce lifetime in seconds.
	 * WordPress default is 24 hours; we use 12 hours for added security.
	 */
	const NONCE_LIFETIME = 12 * HOUR_IN_SECONDS;

	/**
	 * Nonce action for REST API.
	 */
	const NONCE_ACTION_REST = 'wp_rest';

	/**
	 * Nonce action for script execution.
	 */
	const NONCE_ACTION_EXECUTE = 'tsm_execute_script';

	/**
	 * Check if current user has admin permission.
	 *
	 * Used as permission_callback for REST API endpoints.
	 *
	 * @return bool True if user has manage_options capability.
	 */
	public static function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Verify a nonce.
	 *
	 * Wrapper around wp_verify_nonce() for consistency.
	 * Note: REST API with cookie auth verifies nonce automatically.
	 * This method is for non-REST scenarios (e.g., form submissions).
	 *
	 * @param string $nonce  Nonce value to verify.
	 * @param string $action Nonce action name.
	 *
	 * @return bool True if nonce is valid.
	 */
	public static function verify_nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Create a nonce for a given action.
	 *
	 * @param string $action Nonce action name.
	 *
	 * @return string The nonce token.
	 */
	public static function create_nonce( $action ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Get nonce for REST API requests.
	 *
	 * Used by frontend JavaScript to authenticate REST API calls.
	 *
	 * @return string REST API nonce.
	 */
	public static function get_rest_nonce() {
		return wp_create_nonce( self::NONCE_ACTION_REST );
	}

	/**
	 * Output 401 Unauthorized response and exit.
	 *
	 * Used for traditional AJAX requests when user is not logged in.
	 */
	public static function die_unauthorized() {
		wp_die(
			esc_html__( 'You must be logged in to perform this action.', 'test-script-manager' ),
			esc_html__( 'Unauthorized', 'test-script-manager' ),
			array( 'response' => 401 )
		);
	}

	/**
	 * Output 403 Forbidden response and exit.
	 *
	 * Used when nonce verification fails or user lacks permission.
	 */
	public static function die_forbidden() {
		wp_die(
			esc_html__( 'You do not have permission to perform this action.', 'test-script-manager' ),
			esc_html__( 'Forbidden', 'test-script-manager' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Check permission and die if unauthorized.
	 *
	 * Convenience method that combines check and die.
	 * Use in non-REST endpoints where immediate termination is desired.
	 */
	public static function require_admin_permission() {
		if ( ! self::check_admin_permission() ) {
			self::die_forbidden();
		}
	}

	/**
	 * Verify nonce and die if invalid.
	 *
	 * Convenience method that combines verify and die.
	 * Use in non-REST endpoints where immediate termination is desired.
	 *
	 * @param string $nonce  Nonce value to verify.
	 * @param string $action Nonce action name.
	 */
	public static function require_valid_nonce( $nonce, $action ) {
		if ( ! self::verify_nonce( $nonce, $action ) ) {
			self::die_forbidden();
		}
	}
}
