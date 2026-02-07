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
 * v0.1 – Search-only architecture + CSS configuration.
 */
final class EUVD_Plugin {

	private static $instance = null;

	/** Option name for plugin settings. */
	const OPTION_NAME = 'euvd_vuln_settings';

	/** Default settings (v0.1). */
	public static function default_settings(): array {
		return [
			'default_count'       => 5,
			'cache_ttl'           => 10 * MINUTE_IN_SECONDS,
			'load_css'            => 1,
			'css_container_class' => '',
		];
	}

	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

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

		$settings['default_count'] = max(1, min(100, absint($settings['default_count'])));
		$settings['cache_ttl']     = max(30, absint($settings['cache_ttl']));
		$settings['load_css']      = !empty($settings['load_css']) ? 1 : 0;
		$settings['css_container_class'] = sanitize_html_class((string) $settings['css_container_class']);

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
		];
	}

	public function add_settings_page(): void {
		add_options_page(
			__('EUVD Vulnerabilities', 'euvd-vuln'),
			__('EUVD Vulnerabilities', 'euvd-vuln'),
			'manage_options',
			'euvd-vuln',
			[$this, 'render_settings_page']
		);
	}

	public function render_settings_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('EUVD Vulnerabilities (v0.1)', 'euvd-vuln'); ?></h1>
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

	/**
	 * Enqueue frontend CSS if enabled.
	 */
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
}