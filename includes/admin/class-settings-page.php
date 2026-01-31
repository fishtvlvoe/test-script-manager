<?php
/**
 * Settings Page class for WordPress Settings API.
 *
 * Provides admin interface for plugin configuration including
 * execution timeout and IP whitelist security controls.
 *
 * @package TestScriptManager
 */

namespace TSM\Admin;

use TSM\Services\SettingsService;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Page class.
 *
 * Handles WordPress Settings API registration and page rendering.
 * Provides tabbed interface with:
 * - General tab: Execution settings (timeout, output limit)
 * - Security tab: IP whitelist configuration
 */
class Settings_Page {

	/**
	 * Page slug for the settings page.
	 */
	const PAGE_SLUG = 'test-script-manager-settings';

	/**
	 * Constructor.
	 *
	 * Register hooks on instantiation.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page as submenu.
	 *
	 * Registers a submenu item under Test Script Manager menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'test-script-manager',                        // Parent slug.
			__( 'Settings', 'test-script-manager' ),      // Page title.
			__( 'Settings', 'test-script-manager' ),      // Menu title.
			'manage_options',                             // Capability.
			self::PAGE_SLUG,                              // Menu slug.
			array( $this, 'render_page' )                 // Callback.
		);
	}

	/**
	 * Register settings with WordPress Settings API.
	 *
	 * Sets up sections and fields for both General and Security tabs.
	 */
	public function register_settings() {
		// ========================================
		// GENERAL TAB SETTINGS
		// ========================================

		// Register timeout setting.
		register_setting(
			'tsm_general',
			SettingsService::OPTION_TIMEOUT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( 'TSM\Services\SettingsService', 'sanitize_timeout' ),
				'default'           => SettingsService::DEFAULT_TIMEOUT,
			)
		);

		// Register output limit setting.
		register_setting(
			'tsm_general',
			SettingsService::OPTION_OUTPUT_LIMIT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( 'TSM\Services\SettingsService', 'sanitize_output_limit' ),
				'default'           => SettingsService::DEFAULT_OUTPUT_LIMIT,
			)
		);

		// Add general settings section.
		add_settings_section(
			'tsm_general_section',
			__( 'Execution Settings', 'test-script-manager' ),
			array( $this, 'render_general_section' ),
			'tsm_general'
		);

		// Add timeout field.
		add_settings_field(
			'tsm_execution_timeout',
			__( 'Execution Timeout', 'test-script-manager' ),
			array( $this, 'render_timeout_field' ),
			'tsm_general',
			'tsm_general_section'
		);

		// Add output limit field.
		add_settings_field(
			'tsm_output_limit',
			__( 'Output Limit', 'test-script-manager' ),
			array( $this, 'render_output_limit_field' ),
			'tsm_general',
			'tsm_general_section'
		);

		// ========================================
		// SECURITY TAB SETTINGS
		// ========================================

		// Register IP whitelist enabled setting.
		register_setting(
			'tsm_security',
			SettingsService::OPTION_IP_WHITELIST_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( 'TSM\Services\SettingsService', 'sanitize_boolean' ),
				'default'           => false,
			)
		);

		// Register IP whitelist setting.
		register_setting(
			'tsm_security',
			SettingsService::OPTION_IP_WHITELIST,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'TSM\Services\SettingsService', 'sanitize_ip_whitelist' ),
				'default'           => '',
			)
		);

		// Add security settings section.
		add_settings_section(
			'tsm_security_section',
			__( 'IP Whitelist', 'test-script-manager' ),
			array( $this, 'render_security_section' ),
			'tsm_security'
		);

		// Add IP whitelist enabled field.
		add_settings_field(
			'tsm_ip_whitelist_enabled',
			__( 'Enable IP Whitelist', 'test-script-manager' ),
			array( $this, 'render_ip_enabled_field' ),
			'tsm_security',
			'tsm_security_section'
		);

		// Add IP whitelist field.
		add_settings_field(
			'tsm_ip_whitelist',
			__( 'Allowed IP Addresses', 'test-script-manager' ),
			array( $this, 'render_ip_whitelist_field' ),
			'tsm_security',
			'tsm_security_section'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * Outputs tabbed interface with form for active tab.
	 */
	public function render_page() {
		// Check permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'test-script-manager' ) );
		}

		// Get active tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$valid_tabs = array( 'general', 'security' );
		if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Test Script Manager Settings', 'test-script-manager' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=general' ) ); ?>"
				   class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'test-script-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=security' ) ); ?>"
				   class="nav-tab <?php echo 'security' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Security', 'test-script-manager' ); ?>
				</a>
			</nav>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'tsm_' . $active_tab );
				do_settings_sections( 'tsm_' . $active_tab );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general section description.
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure script execution behavior.', 'test-script-manager' ) . '</p>';
	}

	/**
	 * Render security section description.
	 */
	public function render_security_section() {
		echo '<p>' . esc_html__( 'Restrict script execution to specific IP addresses.', 'test-script-manager' ) . '</p>';
	}

	/**
	 * Render execution timeout field.
	 */
	public function render_timeout_field() {
		$value = SettingsService::get_timeout();
		?>
		<input type="number"
			   id="<?php echo esc_attr( SettingsService::OPTION_TIMEOUT ); ?>"
			   name="<?php echo esc_attr( SettingsService::OPTION_TIMEOUT ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   min="<?php echo esc_attr( SettingsService::MIN_TIMEOUT ); ?>"
			   max="<?php echo esc_attr( SettingsService::MAX_TIMEOUT ); ?>"
			   class="small-text" />
		<p class="description">
			<?php
			printf(
				/* translators: 1: minimum seconds, 2: maximum seconds */
				esc_html__( 'Maximum execution time in seconds (%1$d-%2$d). Default: 30 seconds.', 'test-script-manager' ),
				SettingsService::MIN_TIMEOUT,
				SettingsService::MAX_TIMEOUT
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render output limit field.
	 */
	public function render_output_limit_field() {
		// Get value in bytes, display in MB.
		$bytes = SettingsService::get_output_limit();
		$mb    = $bytes / 1048576;
		?>
		<input type="number"
			   id="<?php echo esc_attr( SettingsService::OPTION_OUTPUT_LIMIT ); ?>"
			   name="<?php echo esc_attr( SettingsService::OPTION_OUTPUT_LIMIT ); ?>"
			   value="<?php echo esc_attr( $bytes ); ?>"
			   min="1048576"
			   step="1048576"
			   class="regular-text" />
		<p class="description">
			<?php
			printf(
				/* translators: %s: current limit in MB */
				esc_html__( 'Maximum output size in bytes. Current: %s MB. Default: 10 MB (10485760 bytes).', 'test-script-manager' ),
				number_format( $mb, 1 )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render IP whitelist enabled checkbox.
	 */
	public function render_ip_enabled_field() {
		$enabled = SettingsService::is_ip_whitelist_enabled();
		?>
		<label for="<?php echo esc_attr( SettingsService::OPTION_IP_WHITELIST_ENABLED ); ?>">
			<input type="checkbox"
				   id="<?php echo esc_attr( SettingsService::OPTION_IP_WHITELIST_ENABLED ); ?>"
				   name="<?php echo esc_attr( SettingsService::OPTION_IP_WHITELIST_ENABLED ); ?>"
				   value="1"
				   <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Only allow script execution from whitelisted IP addresses', 'test-script-manager' ); ?>
		</label>
		<p class="description">
			<?php
			printf(
				/* translators: %s: current IP address */
				esc_html__( 'Your current IP address: %s', 'test-script-manager' ),
				'<code>' . esc_html( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'Unknown' ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render IP whitelist textarea.
	 */
	public function render_ip_whitelist_field() {
		$whitelist = SettingsService::get_ip_whitelist();
		?>
		<textarea id="<?php echo esc_attr( SettingsService::OPTION_IP_WHITELIST ); ?>"
				  name="<?php echo esc_attr( SettingsService::OPTION_IP_WHITELIST ); ?>"
				  rows="10"
				  cols="50"
				  class="large-text code"
				  placeholder="<?php esc_attr_e( 'One IP per line, supports CIDR (e.g., 192.168.1.0/24)', 'test-script-manager' ); ?>"><?php echo esc_textarea( $whitelist ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Enter one IP address or CIDR range per line. Examples:', 'test-script-manager' ); ?>
			<br><code>192.168.1.100</code> - <?php esc_html_e( 'Single IP', 'test-script-manager' ); ?>
			<br><code>192.168.1.0/24</code> - <?php esc_html_e( 'IP range (192.168.1.0 - 192.168.1.255)', 'test-script-manager' ); ?>
			<br><code>10.0.0.0/8</code> - <?php esc_html_e( 'Class A private network', 'test-script-manager' ); ?>
		</p>
		<?php
	}
}
