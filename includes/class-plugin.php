<?php
/**
 * Plugin main class.
 *
 * Singleton pattern loader for Test Script Manager plugin.
 *
 * @package TestScriptManager
 */

namespace TSM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class.
 *
 * Main plugin loader implementing singleton pattern.
 * Responsible for loading dependencies and registering hooks.
 */
class Plugin {

	/**
	 * Single instance of the class.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get single instance of the class.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		// Private constructor for singleton pattern.
	}

	/**
	 * Initialize the plugin.
	 *
	 * Main entry point called on plugins_loaded hook.
	 */
	public function init() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Load plugin dependencies.
	 *
	 * Note: Database class is already loaded in main plugin file
	 * because it's needed for the activation hook.
	 */
	private function load_dependencies() {
		// Database class is loaded in test-script-manager.php before activation hook.

		// Security utilities (Phase 1, Plan 02).
		require_once TSM_PLUGIN_DIR . 'includes/class-security.php';

		// Code scanner for dangerous function detection (Phase 1, Plan 02).
		require_once TSM_PLUGIN_DIR . 'includes/class-code-scanner.php';

		// Services (Phase 2, Plan 01).
		require_once TSM_PLUGIN_DIR . 'includes/services/class-storage-service.php';
		require_once TSM_PLUGIN_DIR . 'includes/services/class-script-service.php';

		// Execution services (Phase 4, Plan 01).
		require_once TSM_PLUGIN_DIR . 'includes/services/class-execution-service.php';
		require_once TSM_PLUGIN_DIR . 'includes/services/class-output-formatter.php';

		// API endpoints (Phase 2, Plan 02 + Phase 4, Plan 02).
		require_once TSM_PLUGIN_DIR . 'includes/api/class-scripts-api.php';
		require_once TSM_PLUGIN_DIR . 'includes/api/class-execution-api.php';

		// Admin page (Phase 1, Plan 02).
		require_once TSM_PLUGIN_DIR . 'includes/admin/class-admin-page.php';
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Initializes REST API, admin page, and other components.
	 * Future phases will add:
	 * - Script enqueueing (Phase 5)
	 */
	private function register_hooks() {
		// Register REST API routes.
		$scripts_api = new API\Scripts_API();
		add_action( 'rest_api_init', array( $scripts_api, 'register_routes' ) );

		$execution_api = new API\Execution_API();
		add_action( 'rest_api_init', array( $execution_api, 'register_routes' ) );

		// Initialize admin page (only in admin context).
		if ( is_admin() ) {
			new Admin_Page();
		}

		// Future hooks:
		// - Frontend script enqueueing (Phase 5)
	}

	/**
	 * Deactivation callback.
	 *
	 * Note: We do NOT drop tables here to preserve user data.
	 * Use uninstall.php for complete cleanup if needed.
	 */
	public static function deactivate() {
		// Intentionally left empty.
		// Tables are NOT dropped on deactivation.
		// This preserves user scripts and version history.
		// Cleanup should only happen on explicit uninstall.
	}
}
