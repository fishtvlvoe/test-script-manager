<?php
/**
 * Plugin Name: Test Script Manager
 * Plugin URI: https://github.com/fishtvlvoe/test-script-manager
 * Description: WordPress 後台測試腳本管理工具，讓開發者可以直接在後台編寫、管理、執行測試腳本。
 * Version: 1.0.0
 * Author: Fish TV
 * Author URI: https://test.buygo.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: test-script-manager
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 *
 * @package TestScriptManager
 */

namespace TSM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 */
define( 'TSM_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 * Uses plugin_dir_path() which is symlink-safe.
 */
define( 'TSM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin URL.
 */
define( 'TSM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename for hooks.
 */
define( 'TSM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load Action Scheduler EARLY (before plugins_loaded).
 *
 * Action Scheduler needs to initialize at plugins_loaded priority 0.
 * We load the Composer autoloader here to ensure Action Scheduler
 * is available before our plugin initializes at priority 20.
 */
if ( file_exists( TSM_PLUGIN_DIR . 'includes/libraries/autoload.php' ) ) {
	require_once TSM_PLUGIN_DIR . 'includes/libraries/autoload.php';
}

/**
 * Load dependencies BEFORE registering activation hook.
 *
 * Important: Database class must be loaded first because activation hook
 * needs to call Database::create_tables().
 */
require_once TSM_PLUGIN_DIR . 'includes/class-database.php';
require_once TSM_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Register activation hook.
 * Creates database tables on plugin activation.
 *
 * Note: This must be called AFTER class-database.php is loaded.
 */
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Database', 'create_tables' ) );

/**
 * Deactivation hook.
 * Note: We do NOT drop tables on deactivation to preserve user data.
 * Tables will only be dropped if user explicitly uninstalls the plugin.
 */
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'deactivate' ) );

/**
 * Initialize the plugin.
 *
 * Using priority 20 to ensure WordPress core is fully loaded,
 * consistent with other BuyGo plugins.
 */
add_action(
	'plugins_loaded',
	function () {
		Plugin::instance()->init();
	},
	20
);
