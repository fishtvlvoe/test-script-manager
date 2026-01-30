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
	 * Monaco Editor version.
	 */
	const MONACO_VERSION = '0.55.1';

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

		// Enqueue CSS.
		wp_enqueue_style(
			'tsm-admin-page',
			TSM_PLUGIN_URL . 'assets/css/admin-page.css',
			array(),
			TSM_VERSION
		);

		// Monaco Editor CDN loader.
		wp_enqueue_script(
			'monaco-loader',
			'https://cdn.jsdelivr.net/npm/monaco-editor@' . self::MONACO_VERSION . '/min/vs/loader.js',
			array(),
			self::MONACO_VERSION,
			true
		);

		// Monaco Editor initialization script.
		wp_enqueue_script(
			'tsm-monaco-loader',
			TSM_PLUGIN_URL . 'assets/js/monaco-loader.js',
			array( 'monaco-loader' ),
			TSM_VERSION,
			true
		);

		// Localize Monaco data.
		wp_localize_script(
			'tsm-monaco-loader',
			'tsmMonaco',
			array(
				'cdnPath'      => 'https://cdn.jsdelivr.net/npm/monaco-editor@' . self::MONACO_VERSION . '/min/vs',
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'tsm_editor' ),
				'defaultTheme' => 'vs-dark',
			)
		);

		// Enqueue main admin JS.
		wp_enqueue_script(
			'tsm-admin-page',
			TSM_PLUGIN_URL . 'assets/js/admin-page.js',
			array( 'jquery', 'tsm-monaco-loader' ),
			TSM_VERSION,
			true
		);

		// Localize script with REST API data.
		wp_localize_script(
			'tsm-admin-page',
			'tsmAdmin',
			array(
				'restUrl' => rest_url( 'test-script-manager/v1' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
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
		?>
		<div class="wrap tsm-admin-page">
			<h1>
				<?php esc_html_e( '測試腳本管理', 'test-script-manager' ); ?>
				<button type="button" class="page-title-action" id="tsm-new-script">
					<?php esc_html_e( '新增腳本', 'test-script-manager' ); ?>
				</button>
			</h1>

			<div class="tsm-container">
				<!-- Sidebar -->
				<div class="tsm-sidebar">
					<!-- Search box -->
					<div class="tsm-search-box">
						<input type="text" id="tsm-search" placeholder="<?php esc_attr_e( '搜尋腳本...', 'test-script-manager' ); ?>">
					</div>

					<!-- Script list -->
					<div class="tsm-script-list" id="tsm-script-list">
						<div class="tsm-loading"><?php esc_html_e( '載入中...', 'test-script-manager' ); ?></div>
					</div>
				</div>

				<!-- Main content -->
				<div class="tsm-main-content">
					<!-- Create form (hidden by default) -->
					<div class="tsm-create-form" id="tsm-create-form" style="display: none;">
						<h2><?php esc_html_e( '建立新腳本', 'test-script-manager' ); ?></h2>

						<div class="tsm-form-group">
							<label for="tsm-script-name"><?php esc_html_e( '腳本名稱', 'test-script-manager' ); ?></label>
							<input type="text" id="tsm-script-name" required>
						</div>

						<div class="tsm-form-group">
							<label for="tsm-script-slug"><?php esc_html_e( 'Slug', 'test-script-manager' ); ?></label>
							<input type="text" id="tsm-script-slug" required>
							<p class="description"><?php esc_html_e( '只能使用小寫字母、數字和連字號', 'test-script-manager' ); ?></p>
						</div>

						<div class="tsm-form-group">
							<label for="tsm-script-code"><?php esc_html_e( '程式碼', 'test-script-manager' ); ?></label>
							<p class="description"><?php esc_html_e( '以 <?php 開頭編寫 PHP 程式碼', 'test-script-manager' ); ?></p>
							<div id="monaco-editor-create" class="tsm-monaco-editor"></div>
							<input type="hidden" id="tsm-script-code" name="code" />
						</div>

						<div class="tsm-form-actions">
							<button type="button" class="button button-primary" id="tsm-save-script">
								<?php esc_html_e( '儲存腳本', 'test-script-manager' ); ?>
							</button>
							<button type="button" class="button" id="tsm-cancel-create">
								<?php esc_html_e( '取消', 'test-script-manager' ); ?>
							</button>
						</div>

						<div class="tsm-message" id="tsm-message" style="display: none;"></div>
					</div>

					<!-- Edit section (hidden by default) -->
					<div class="tsm-edit-section" id="tsm-edit-section" style="display: none;">
						<div class="tsm-edit-header">
							<h2 id="tsm-edit-title"><?php esc_html_e( '編輯腳本', 'test-script-manager' ); ?></h2>
							<button type="button" class="button" id="tsm-back-to-list">
								<?php esc_html_e( '返回列表', 'test-script-manager' ); ?>
							</button>
						</div>

						<div class="tsm-form-group">
							<label><?php esc_html_e( '程式碼', 'test-script-manager' ); ?></label>
							<div id="monaco-editor-edit" class="tsm-monaco-editor"></div>
							<input type="hidden" id="tsm-edit-script-id" />
						</div>

						<div class="tsm-form-actions">
							<button type="button" class="button button-primary" id="tsm-update-script">
								<?php esc_html_e( '更新腳本', 'test-script-manager' ); ?>
							</button>
							<button type="button" class="button button-secondary" id="tsm-execute-script">
								<?php esc_html_e( '執行', 'test-script-manager' ); ?>
							</button>
						</div>

						<div class="tsm-message" id="tsm-edit-message" style="display: none;"></div>
					</div>

					<!-- Welcome message (shown by default) -->
					<div class="tsm-welcome" id="tsm-welcome">
						<h2><?php esc_html_e( '歡迎使用測試腳本管理', 'test-script-manager' ); ?></h2>
						<p><?php esc_html_e( '從左側選擇腳本或建立新的腳本。', 'test-script-manager' ); ?></p>
					</div>
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
