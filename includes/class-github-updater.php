<?php
/**
 * GitHub Updater Class.
 *
 * Enables automatic plugin updates from GitHub releases.
 * Checks for new versions and provides update notifications in WordPress admin.
 *
 * @package TestScriptManager
 */

namespace TSM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GitHub_Updater
 *
 * Handles automatic updates from GitHub releases.
 */
class GitHub_Updater {

	/**
	 * GitHub username.
	 *
	 * @var string
	 */
	private $github_user = 'fishtv';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private $github_repo = 'test-script-manager';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * GitHub API response cache.
	 *
	 * @var object|null
	 */
	private $github_response = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_slug     = 'test-script-manager';
		$this->plugin_basename = TSM_PLUGIN_BASENAME;
		$this->current_version = TSM_VERSION;
	}

	/**
	 * Initialize the updater.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

		// Add "Check for updates" link on plugins page.
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_action_links' ) );
	}

	/**
	 * Get GitHub release info.
	 *
	 * @return object|null Release info or null on failure.
	 */
	private function get_github_release() {
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		// Check cache first.
		$cached = get_transient( 'tsm_github_release' );
		if ( false !== $cached ) {
			$this->github_response = $cached;
			return $this->github_response;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			$this->github_user,
			$this->github_repo
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) || ! isset( $data->tag_name ) ) {
			return null;
		}

		// Cache for 6 hours.
		set_transient( 'tsm_github_release', $data, 6 * HOUR_IN_SECONDS );

		$this->github_response = $data;
		return $this->github_response;
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient WordPress update transient.
	 * @return object Modified transient.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_github_release();

		if ( null === $release ) {
			return $transient;
		}

		// Parse version from tag (remove 'v' prefix if present).
		$latest_version = ltrim( $release->tag_name, 'v' );

		// Compare versions.
		if ( version_compare( $this->current_version, $latest_version, '<' ) ) {
			// Find the zip asset.
			$download_url = $this->get_download_url( $release );

			if ( $download_url ) {
				$transient->response[ $this->plugin_basename ] = (object) array(
					'slug'        => $this->plugin_slug,
					'plugin'      => $this->plugin_basename,
					'new_version' => $latest_version,
					'url'         => $release->html_url,
					'package'     => $download_url,
					'icons'       => array(
						'default' => TSM_PLUGIN_URL . 'assets/images/icon-128x128.png',
					),
					'banners'     => array(
						'low'  => TSM_PLUGIN_URL . 'assets/images/banner-772x250.png',
						'high' => TSM_PLUGIN_URL . 'assets/images/banner-1544x500.png',
					),
					'tested'      => '6.7',
					'requires'    => '6.4',
					'requires_php' => '8.0',
				);
			}
		}

		return $transient;
	}

	/**
	 * Get download URL from release.
	 *
	 * @param object $release GitHub release object.
	 * @return string|null Download URL or null.
	 */
	private function get_download_url( $release ) {
		// First, try to find a zip asset.
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( preg_match( '/\.zip$/i', $asset->name ) ) {
					return $asset->browser_download_url;
				}
			}
		}

		// Fallback to zipball URL.
		if ( ! empty( $release->zipball_url ) ) {
			return $release->zipball_url;
		}

		return null;
	}

	/**
	 * Plugin information for the "View Details" popup.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin info or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		$release = $this->get_github_release();

		if ( null === $release ) {
			return $result;
		}

		$latest_version = ltrim( $release->tag_name, 'v' );

		return (object) array(
			'name'              => 'Test Script Manager',
			'slug'              => $this->plugin_slug,
			'version'           => $latest_version,
			'author'            => '<a href="https://test.buygo.me">Fish TV</a>',
			'author_profile'    => 'https://test.buygo.me',
			'homepage'          => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
			'short_description' => 'WordPress 後台測試腳本管理工具',
			'sections'          => array(
				'description'  => $this->get_readme_section( 'description' ),
				'installation' => $this->get_readme_section( 'installation' ),
				'changelog'    => $this->parse_changelog( $release->body ?? '' ),
			),
			'download_link'     => $this->get_download_url( $release ),
			'requires'          => '6.4',
			'tested'            => '6.7',
			'requires_php'      => '8.0',
			'last_updated'      => $release->published_at ?? '',
			'downloaded'        => 0,
			'banners'           => array(
				'low'  => TSM_PLUGIN_URL . 'assets/images/banner-772x250.png',
				'high' => TSM_PLUGIN_URL . 'assets/images/banner-1544x500.png',
			),
		);
	}

	/**
	 * Get readme section content.
	 *
	 * @param string $section Section name.
	 * @return string Section content.
	 */
	private function get_readme_section( $section ) {
		switch ( $section ) {
			case 'description':
				return '<p>Test Script Manager 讓開發者可以在 WordPress 後台直接編寫和執行測試腳本。</p>
				<h4>功能特色</h4>
				<ul>
					<li>Monaco Editor 程式碼編輯器（VS Code 同款）</li>
					<li>即時執行或背景執行腳本</li>
					<li>格式化的輸出結果（表格、JSON、HTML）</li>
					<li>版本控制和一鍵還原</li>
					<li>匯出結果（CSV、JSON、Excel）</li>
					<li>分類和範本管理</li>
					<li>批次操作</li>
				</ul>';

			case 'installation':
				return '<ol>
					<li>上傳外掛到 <code>/wp-content/plugins/test-script-manager</code> 目錄</li>
					<li>在 WordPress 後台啟用外掛</li>
					<li>前往「工具 → Test Script Manager」開始使用</li>
				</ol>';

			default:
				return '';
		}
	}

	/**
	 * Parse changelog from release body.
	 *
	 * @param string $body Release body (Markdown).
	 * @return string Formatted changelog.
	 */
	private function parse_changelog( $body ) {
		if ( empty( $body ) ) {
			return '<p>查看 <a href="https://github.com/' . $this->github_user . '/' . $this->github_repo . '/releases">GitHub Releases</a> 了解更新內容。</p>';
		}

		// Convert Markdown to HTML (basic conversion).
		$html = wpautop( esc_html( $body ) );
		$html = preg_replace( '/^- /m', '• ', $html );

		return $html;
	}

	/**
	 * After plugin install, rename folder to match plugin slug.
	 *
	 * @param bool  $response   Install response.
	 * @param array $hook_extra Extra hook info.
	 * @param array $result     Install result.
	 * @return array Modified result.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
			return $result;
		}

		$plugin_folder = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
		$wp_filesystem->move( $result['destination'], $plugin_folder );
		$result['destination'] = $plugin_folder;

		// Reactivate plugin.
		activate_plugin( $this->plugin_basename );

		return $result;
	}

	/**
	 * Add action links to plugins page.
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_action_links( $links ) {
		$check_link = sprintf(
			'<a href="%s">%s</a>',
			wp_nonce_url( admin_url( 'update-core.php?force-check=1' ), 'upgrade-core' ),
			__( '檢查更新', 'test-script-manager' )
		);

		array_unshift( $links, $check_link );

		return $links;
	}

	/**
	 * Clear update cache.
	 *
	 * Call this when manually checking for updates.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		delete_transient( 'tsm_github_release' );
		delete_site_transient( 'update_plugins' );
	}
}
