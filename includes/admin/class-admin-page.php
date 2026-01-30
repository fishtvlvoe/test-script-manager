<?php
/**
 * Admin Page class for WordPress admin menu.
 *
 * Registers the "Test Script Manager" menu and renders the admin interface.
 *
 * @package TestScriptManager
 */

namespace TSM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin_Page class.
 *
 * Handles WordPress admin menu registration and page rendering.
 * Only accessible to users with 'manage_options' capability.
 */
class Admin_Page {

	/**
	 * Menu slug for the admin page.
	 */
	const MENU_SLUG = 'test-script-manager';

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * Register hooks on instantiation.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the admin menu page.
	 *
	 * Registers a top-level menu item in WordPress admin sidebar.
	 */
	public function add_menu_page() {
		$this->hook_suffix = add_menu_page(
			__( '測試腳本', 'test-script-manager' ),    // Page title.
			__( '測試腳本', 'test-script-manager' ),    // Menu title.
			'manage_options',                           // Capability.
			self::MENU_SLUG,                            // Menu slug.
			array( $this, 'render_page' ),              // Callback.
			'dashicons-editor-code',                    // Icon.
			80                                          // Position.
		);
	}

	/**
	 * Enqueue admin assets (CSS/JS).
	 *
	 * Only loads on the plugin's admin page.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only enqueue on our admin page.
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		// Placeholder for future CSS/JS assets.
		// Phase 5 will add Monaco Editor and custom styles.
		// Example:
		// wp_enqueue_style( 'tsm-admin', TSM_PLUGIN_URL . 'assets/css/admin.css', array(), TSM_VERSION );
		// wp_enqueue_script( 'tsm-admin', TSM_PLUGIN_URL . 'assets/js/admin.js', array(), TSM_VERSION, true );
	}

	/**
	 * Render the admin page.
	 *
	 * Outputs the HTML structure for the test script manager interface.
	 */
	public function render_page() {
		// Double-check permission (defense in depth).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'test-script-manager' ),
				esc_html__( 'Forbidden', 'test-script-manager' ),
				array( 'response' => 403 )
			);
		}

		// Get REST API nonce for future AJAX calls.
		$rest_nonce = Security::get_rest_nonce();
		$rest_url   = esc_url( rest_url( 'tsm/v1/' ) );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( '測試腳本管理', 'test-script-manager' ); ?></h1>

			<p><?php esc_html_e( '外掛安裝成功！後續 Phase 將加入腳本列表和編輯器。', 'test-script-manager' ); ?></p>

			<div class="tsm-container" style="display: flex; gap: 20px; margin-top: 20px;">
				<div class="tsm-sidebar" style="width: 250px; background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
					<h2><?php esc_html_e( '腳本列表', 'test-script-manager' ); ?></h2>
					<p class="description"><?php esc_html_e( '（等待實作）', 'test-script-manager' ); ?></p>
				</div>
				<div class="tsm-main" style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
					<h2><?php esc_html_e( '編輯器區', 'test-script-manager' ); ?></h2>
					<p class="description"><?php esc_html_e( '（等待實作）', 'test-script-manager' ); ?></p>

					<!-- Debug info (only visible in development) -->
					<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
					<hr style="margin: 20px 0;">
					<h3><?php esc_html_e( '除錯資訊', 'test-script-manager' ); ?></h3>
					<table class="widefat" style="max-width: 500px;">
						<tr>
							<td><strong>REST URL:</strong></td>
							<td><code><?php echo esc_html( $rest_url ); ?></code></td>
						</tr>
						<tr>
							<td><strong>REST Nonce:</strong></td>
							<td><code><?php echo esc_html( $rest_nonce ); ?></code></td>
						</tr>
						<tr>
							<td><strong>WP_DEBUG:</strong></td>
							<td><?php echo WP_DEBUG ? 'true' : 'false'; ?></td>
						</tr>
						<tr>
							<td><strong>TSM_VERSION:</strong></td>
							<td><?php echo esc_html( TSM_VERSION ); ?></td>
						</tr>
					</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the admin page URL.
	 *
	 * @return string Admin page URL.
	 */
	public static function get_admin_url() {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG );
	}
}
