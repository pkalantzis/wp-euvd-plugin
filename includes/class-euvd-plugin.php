<?php
namespace EUVD\Vuln;

if (!defined('ABSPATH')) {
	exit;
}

require_once EUVD_VULN_PLUGIN_DIR . 'includes/class-euvd-client.php';
require_once EUVD_VULN_PLUGIN_DIR . 'includes/shortcodes.php';
require_once EUVD_VULN_PLUGIN_DIR . 'includes/blocks.php';
require_once EUVD_VULN_PLUGIN_DIR . 'includes/widgets/class-euvd-latest-widget.php';

/**
 * Main plugin bootstrap class.
 *
 * v0.1.0 – Search-only architecture + CSS configuration
 * v0.2.0 – Admin menu + Tools (clear cache + API test) + clear cache on settings save
 * v0.2.1 – Minor file versioning fix
*/
final class EUVD_Plugin {

	private static $instance = null;

	/** Option name for plugin settings. */
	const OPTION_NAME = 'euvd_vuln_settings';

	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/** Default settings. */
	public static function default_settings(): array {
		return [
			'default_count'        => 5,
			'cache_ttl'            => 10 * MINUTE_IN_SECONDS,
			'load_css'             => 1,
			'css_container_class'  => '',
			'clear_cache_on_save'  => 0,
		];
	}

	public function init(): void {
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_menu', [$this, 'register_admin_menu']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

		// Admin CSS (menu icon tweaks) - load only on plugin pages.
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

		// Tools handlers
		add_action('admin_post_euvd_vuln_clear_cache', [$this, 'handle_clear_cache']);
		add_action('admin_post_euvd_vuln_test_api', [$this, 'handle_test_api']);
		add_action('admin_notices', [$this, 'admin_notices']);

		// Optional: clear cache automatically after settings save
		add_action('update_option_' . self::OPTION_NAME, [$this, 'maybe_clear_cache_on_settings_save'], 10, 2);

		// Shortcodes
		Shortcodes::register();

		// Blocks
		Blocks::register();

		// Widget
		add_action('widgets_init', static function () {
			register_widget(\EUVD\Vuln\Widgets\EUVD_Latest_Widget::class);
		});
	}

	/**
	 * Retrieve sanitized settings.
	 */
	public static function get_settings(): array {
		$stored   = get_option(self::OPTION_NAME, []);
		$defaults = self::default_settings();
		$settings = is_array($stored) ? array_merge($defaults, $stored) : $defaults;

		$settings['default_count']       = max(1, min(100, absint($settings['default_count'])));
		$settings['cache_ttl']           = max(30, absint($settings['cache_ttl']));
		$settings['load_css']            = !empty($settings['load_css']) ? 1 : 0;
		$settings['css_container_class'] = sanitize_html_class((string) $settings['css_container_class']);
		$settings['clear_cache_on_save'] = !empty($settings['clear_cache_on_save']) ? 1 : 0;

		return $settings;
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		register_setting(
			'euvd_vuln_settings_group',
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [$this, 'sanitize_settings'],
				'default'           => self::default_settings(),
			]
		);

		add_settings_section(
			'euvd_vuln_main_section',
			__('EUVD Settings', 'euvd-vuln'),
			function () {
				echo '<p>' . esc_html__(
					'Configure how EUVD vulnerabilities are displayed and cached.',
					'euvd-vuln'
				) . '</p>';
			},
			'euvd-vuln'
		);

		add_settings_field(
			'default_count',
			__('Default number to show', 'euvd-vuln'),
			[$this, 'render_default_count_field'],
			'euvd-vuln',
			'euvd_vuln_main_section'
		);

		add_settings_field(
			'cache_ttl',
			__('Cache TTL (seconds)', 'euvd-vuln'),
			[$this, 'render_cache_ttl_field'],
			'euvd-vuln',
			'euvd_vuln_main_section'
		);

		add_settings_field(
			'load_css',
			__('Load default stylesheet', 'euvd-vuln'),
			[$this, 'render_load_css_field'],
			'euvd-vuln',
			'euvd_vuln_main_section'
		);

		add_settings_field(
			'css_container_class',
			__('Extra container CSS class', 'euvd-vuln'),
			[$this, 'render_css_container_class_field'],
			'euvd-vuln',
			'euvd_vuln_main_section'
		);

		add_settings_field(
			'clear_cache_on_save',
			__('Clear cache on settings save', 'euvd-vuln'),
			[$this, 'render_clear_cache_on_save_field'],
			'euvd-vuln',
			'euvd_vuln_main_section'
		);
	}

	public function sanitize_settings($input): array {
		$defaults = self::default_settings();
		$input = is_array($input) ? $input : [];

		return [
			'default_count' => isset($input['default_count'])
				? max(1, min(100, absint($input['default_count'])))
				: $defaults['default_count'],

			'cache_ttl' => isset($input['cache_ttl'])
				? max(30, absint($input['cache_ttl']))
				: $defaults['cache_ttl'],

			'load_css' => !empty($input['load_css']) ? 1 : 0,

			'css_container_class' => isset($input['css_container_class'])
				? sanitize_html_class((string) $input['css_container_class'])
				: '',

			'clear_cache_on_save' => !empty($input['clear_cache_on_save']) ? 1 : 0,
		];
	}

	/* =========================
	 * Admin Menu
	 * ========================= */

	public function register_admin_menu(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$capability = 'manage_options';
		$slug       = 'euvd-vuln';

		// SVG menu icon (preferred) + local PNG fallback.
		$svg_rel = 'assets/images/vd-icon-20x20.svg';
		$png_rel = 'assets/images/menu-icon-16x16.png';

		$svg_path = EUVD_VULN_PLUGIN_DIR . $svg_rel;
		$icon_url = EUVD_VULN_PLUGIN_URL . $svg_rel;

		if (!file_exists($svg_path)) {
			$icon_url = EUVD_VULN_PLUGIN_URL . $png_rel;
		}

		add_menu_page(
			__('EUVD Vulnerabilities', 'euvd-vuln'),
			__('EUVD Vulnerabilities', 'euvd-vuln'),
			$capability,
			$slug,
			[$this, 'render_dashboard_page'],
			$icon_url,
			65
		);

		add_submenu_page(
			$slug,
			__('Dashboard', 'euvd-vuln'),
			__('Dashboard', 'euvd-vuln'),
			$capability,
			$slug,
			[$this, 'render_dashboard_page']
		);

		add_submenu_page(
			$slug,
			__('Settings', 'euvd-vuln'),
			__('Settings', 'euvd-vuln'),
			$capability,
			$slug . '-settings',
			[$this, 'render_settings_page']
		);

		add_submenu_page(
			$slug,
			__('Tools', 'euvd-vuln'),
			__('Tools', 'euvd-vuln'),
			$capability,
			$slug . '-tools',
			[$this, 'render_tools_page']
		);

		add_submenu_page(
			$slug,
			__('About', 'euvd-vuln'),
			__('About', 'euvd-vuln'),
			$capability,
			$slug . '-about',
			[$this, 'render_about_page']
		);
	}

	public function render_dashboard_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = self::get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('EUVD Vulnerabilities', 'euvd-vuln'); ?></h1>
			<p><?php echo esc_html__('Display EUVD vulnerability data using blocks, shortcodes, and widgets.', 'euvd-vuln'); ?></p>

			<h2><?php echo esc_html__('Quick info', 'euvd-vuln'); ?></h2>
			<ul style="list-style: disc; margin-left: 1.5rem;">
				<li><?php echo esc_html__('Version:', 'euvd-vuln') . ' ' . esc_html(EUVD_VULN_VERSION); ?></li>
				<li><?php echo esc_html__('Default count:', 'euvd-vuln') . ' ' . esc_html((string) $settings['default_count']); ?></li>
				<li><?php echo esc_html__('Cache TTL (seconds):', 'euvd-vuln') . ' ' . esc_html((string) $settings['cache_ttl']); ?></li>
				<li><?php echo esc_html__('Default CSS enabled:', 'euvd-vuln') . ' ' . esc_html(!empty($settings['load_css']) ? __('Yes', 'euvd-vuln') : __('No', 'euvd-vuln')); ?></li>
			</ul>

			<h2><?php echo esc_html__('Usage', 'euvd-vuln'); ?></h2>
			<p><?php echo esc_html__('Shortcodes:', 'euvd-vuln'); ?></p>
			<code>[euvd_latest count="10"]</code><br />
			<code>[euvd_critical count="10"]</code><br />
			<code>[euvd_exploited count="10"]</code><br />
			<code>[euvd_vulnerabilities type="latest" count="10"]</code>

			<p style="margin-top: 1rem;">
				<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=euvd-vuln-settings')); ?>">
					<?php echo esc_html__('Go to Settings', 'euvd-vuln'); ?>
				</a>
				<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=euvd-vuln-tools')); ?>">
					<?php echo esc_html__('Tools', 'euvd-vuln'); ?>
				</a>
				<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=euvd-vuln-about')); ?>">
					<?php echo esc_html__('About / Help', 'euvd-vuln'); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public function render_settings_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('EUVD Vulnerabilities Settings', 'euvd-vuln'); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields('euvd_vuln_settings_group');
				do_settings_sections('euvd-vuln');
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_tools_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('EUVD Vulnerabilities Tools', 'euvd-vuln'); ?></h1>

			<h2><?php echo esc_html__('Cache', 'euvd-vuln'); ?></h2>
			<p><?php echo esc_html__('Clears cached EUVD API responses stored as WordPress transients.', 'euvd-vuln'); ?></p>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('euvd_vuln_clear_cache_action', 'euvd_vuln_nonce'); ?>
				<input type="hidden" name="action" value="euvd_vuln_clear_cache" />
				<?php submit_button(__('Clear cache', 'euvd-vuln'), 'secondary'); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__('API Connection', 'euvd-vuln'); ?></h2>
			<p><?php echo esc_html__('Runs a lightweight request to the EUVD API and reports HTTP status and latency.', 'euvd-vuln'); ?></p>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('euvd_vuln_test_api_action', 'euvd_vuln_test_nonce'); ?>
				<input type="hidden" name="action" value="euvd_vuln_test_api" />
				<?php submit_button(__('Test API connection', 'euvd-vuln'), 'secondary'); ?>
			</form>
		</div>
		<?php
	}

	public function render_about_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('About EUVD Vulnerabilities', 'euvd-vuln'); ?></h1>

			<p><?php echo esc_html__('This plugin displays publicly available vulnerability information from ENISA’s EU Vulnerability Database (EUVD).', 'euvd-vuln'); ?></p>

			<h2><?php echo esc_html__('Version', 'euvd-vuln'); ?></h2>
			<p><?php echo esc_html(EUVD_VULN_VERSION); ?></p>

			<h2><?php echo esc_html__('Data source', 'euvd-vuln'); ?></h2>
			<p>
				<a href="<?php echo esc_url('https://euvd.enisa.europa.eu/apidoc'); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__('ENISA EUVD API Documentation', 'euvd-vuln'); ?>
				</a>
			</p>

			<h2><?php echo esc_html__('Privacy', 'euvd-vuln'); ?></h2>
			<p><?php echo esc_html__('No cookies, no tracking, no personal data collection. Your server queries the EUVD API to retrieve public vulnerability information.', 'euvd-vuln'); ?></p>
		</div>
		<?php
	}

	/* =========================
	 * Settings Field Renderers
	 * ========================= */

	public function render_default_count_field(): void {
		$settings = self::get_settings();
		?>
		<input type="number" min="1" max="100"
			name="<?php echo esc_attr(self::OPTION_NAME); ?>[default_count]"
			value="<?php echo esc_attr((string) $settings['default_count']); ?>"
		/>
		<?php
	}

	public function render_cache_ttl_field(): void {
		$settings = self::get_settings();
		?>
		<input type="number" min="30" step="10"
			name="<?php echo esc_attr(self::OPTION_NAME); ?>[cache_ttl]"
			value="<?php echo esc_attr((string) $settings['cache_ttl']); ?>"
		/>
		<?php
	}

	public function render_load_css_field(): void {
		$settings = self::get_settings();
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr(self::OPTION_NAME); ?>[load_css]"
				value="1"
				<?php checked(!empty($settings['load_css'])); ?>
			/>
			<?php esc_html_e('Enqueue default frontend styles', 'euvd-vuln'); ?>
		</label>
		<?php
	}

	public function render_css_container_class_field(): void {
		$settings = self::get_settings();
		?>
		<input type="text" class="regular-text"
			name="<?php echo esc_attr(self::OPTION_NAME); ?>[css_container_class]"
			value="<?php echo esc_attr((string) $settings['css_container_class']); ?>"
		/>
		<?php
	}

	public function render_clear_cache_on_save_field(): void {
		$settings = self::get_settings();
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr(self::OPTION_NAME); ?>[clear_cache_on_save]"
				value="1"
				<?php checked(!empty($settings['clear_cache_on_save'])); ?>
			/>
			<?php esc_html_e('Automatically clear cached EUVD data when settings are updated.', 'euvd-vuln'); ?>
		</label>
		<?php
	}

	/* =========================
	 * Assets
	 * ========================= */

	public function enqueue_assets(): void {
		$settings = self::get_settings();
		if (empty($settings['load_css'])) {
			return;
		}

		wp_enqueue_style(
			'euvd-vuln',
			EUVD_VULN_PLUGIN_URL . 'assets/css/euvd-vuln.css',
			[],
			EUVD_VULN_VERSION
		);
	}

	/**
	 * Admin-only CSS (menu icon spacing/contrast/hover).
	 */
	public function enqueue_admin_assets(string $hook): void {
		// Load only on this plugin's admin pages.
		if (strpos($hook, 'euvd-vuln') === false) {
			return;
		}

		wp_enqueue_style(
			'euvd-vuln-admin',
			EUVD_VULN_PLUGIN_URL . 'assets/css/admin-menu.css',
			[],
			EUVD_VULN_VERSION
		);
	}

	/* =========================
	 * Tools: Clear cache + API test
	 * ========================= */

	public function handle_clear_cache(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'euvd-vuln'));
		}

		$nonce = isset($_POST['euvd_vuln_nonce']) ? sanitize_text_field((string) $_POST['euvd_vuln_nonce']) : '';
		if (!wp_verify_nonce($nonce, 'euvd_vuln_clear_cache_action')) {
			wp_die(esc_html__('Security check failed.', 'euvd-vuln'));
		}

		$deleted = $this->delete_plugin_transients('euvd_vuln_');

		$url = add_query_arg(
			[
				'page'    => 'euvd-vuln-tools',
				'cleared' => '1',
				'deleted' => (string) absint($deleted),
			],
			admin_url('admin.php')
		);

		wp_safe_redirect($url);
		exit;
	}

	public function handle_test_api(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'euvd-vuln'));
		}

		$nonce = isset($_POST['euvd_vuln_test_nonce']) ? sanitize_text_field((string) $_POST['euvd_vuln_test_nonce']) : '';
		if (!wp_verify_nonce($nonce, 'euvd_vuln_test_api_action')) {
			wp_die(esc_html__('Security check failed.', 'euvd-vuln'));
		}

		$result = $this->run_api_connection_test();

		$url = add_query_arg(
			[
				'page'        => 'euvd-vuln-tools',
				'api_test'    => '1',
				'api_ok'      => $result['ok'] ? '1' : '0',
				'api_http'    => (string) $result['http_code'],
				'api_latency' => (string) $result['latency_ms'],
			],
			admin_url('admin.php')
		);

		if (!empty($result['error'])) {
			$url = add_query_arg(['api_err' => rawurlencode($result['error'])], $url);
		}

		wp_safe_redirect($url);
		exit;
	}

	public function admin_notices(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		if ($page !== 'euvd-vuln-tools') {
			return;
		}

		// Cache cleared notice
		$cleared = isset($_GET['cleared']) ? sanitize_text_field((string) $_GET['cleared']) : '';
		if ($cleared === '1') {
			$deleted = isset($_GET['deleted']) ? absint($_GET['deleted']) : 0;

			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(sprintf(__('Cache cleared. Deleted %d cached entries.', 'euvd-vuln'), $deleted));
			echo '</p></div>';
		}

		// API test notice
		$api_test = isset($_GET['api_test']) ? sanitize_text_field((string) $_GET['api_test']) : '';
		if ($api_test === '1') {
			$ok      = isset($_GET['api_ok']) && (string) $_GET['api_ok'] === '1';
			$http    = isset($_GET['api_http']) ? absint($_GET['api_http']) : 0;
			$latency = isset($_GET['api_latency']) ? absint($_GET['api_latency']) : 0;
			$err     = isset($_GET['api_err']) ? rawurldecode((string) $_GET['api_err']) : '';

			$klass = $ok ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';

			echo '<div class="' . esc_attr($klass) . '"><p>';
			if ($ok) {
				echo esc_html(sprintf(__('API OK (HTTP %d) — %d ms', 'euvd-vuln'), $http, $latency));
			} else {
				$msg = $err !== '' ? $err : __('API request failed.', 'euvd-vuln');
				echo esc_html(sprintf(__('API FAILED (%s) — %d ms', 'euvd-vuln'), $msg, $latency));
			}
			echo '</p></div>';
		}
	}

	public function maybe_clear_cache_on_settings_save($old_value, $value): void {
		if (!is_array($value) || empty($value['clear_cache_on_save'])) {
			return;
		}
		$this->delete_plugin_transients('euvd_vuln_');
	}

	/**
	 * Delete transients by prefix (DB-backed transient store).
	 *
	 * @return int Best-effort number of deleted transients.
	 */
	private function delete_plugin_transients(string $prefix): int {
		global $wpdb;

		$like         = $wpdb->esc_like('_transient_' . $prefix) . '%';
		$timeout_like = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		$deleted = 0;

		if (is_array($names)) {
			foreach ($names as $option_name) {
				$key = preg_replace('/^_transient_/', '', (string) $option_name);
				if ($key) {
					delete_transient($key);
					$deleted++;
				}
			}
		}

		// Cleanup any leftovers
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like,
				$timeout_like
			)
		);

		return $deleted;
	}

	/**
	 * Minimal API request to measure connectivity/latency (admin-only tool).
	 *
	 * @return array{ok: bool, http_code: int, latency_ms: int, error: string}
	 */
	private function run_api_connection_test(): array {
		$api_url = 'https://euvdservices.enisa.europa.eu/api/search';
		$api_url = add_query_arg(['page' => 0, 'size' => 1], $api_url);

		$start = microtime(true);

		$response = wp_remote_get(
			$api_url,
			[
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => ['Accept' => 'application/json'],
				'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
			]
		);

		$latency_ms = (int) round((microtime(true) - $start) * 1000);

		if (is_wp_error($response)) {
			return [
				'ok'         => false,
				'http_code'  => 0,
				'latency_ms' => $latency_ms,
				'error'      => $response->get_error_message(),
			];
		}

		$http_code = (int) wp_remote_retrieve_response_code($response);
		$ok = ($http_code >= 200 && $http_code < 300);

		return [
			'ok'         => $ok,
			'http_code'  => $http_code,
			'latency_ms' => $latency_ms,
			'error'      => $ok ? '' : ('HTTP ' . $http_code),
		];
	}
}