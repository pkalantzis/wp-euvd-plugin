<?php
namespace EUVD\Vuln;

if (!defined('ABSPATH')) {
	exit;
}

final class Shortcodes {

	public static function register(): void {
		add_shortcode('euvd_vulnerabilities', [self::class, 'render_list']);
		add_shortcode('euvd_latest', [self::class, 'render_latest']);
		add_shortcode('euvd_critical', [self::class, 'render_critical']);
		add_shortcode('euvd_exploited', [self::class, 'render_exploited']);
	}

	/**
	 * [euvd_vulnerabilities type="latest|critical|exploited" count="10" class="optional-extra-class"]
	 */
	public static function render_list($atts): string {
		$settings = EUVD_Plugin::get_settings();

		$atts = shortcode_atts(
			[
				'type'  => 'latest',
				'count' => (string) $settings['default_count'],
				'class' => '',
			],
			(array) $atts,
			'euvd_vulnerabilities'
		);

		$type  = sanitize_key((string) $atts['type']);
		$count = max(1, min(100, absint($atts['count'])));
		$extra_class = sanitize_html_class((string) $atts['class']);

		$cache_ttl = (int) $settings['cache_ttl'];

		switch ($type) {
			case 'critical':
				$title = __('Critical vulnerabilities', 'euvd-vuln');
				$res = EUVD_Client::critical($count, $cache_ttl);
				break;
			case 'exploited':
				$title = __('Exploited vulnerabilities', 'euvd-vuln');
				$res = EUVD_Client::exploited($count, $cache_ttl);
				break;
			case 'latest':
			default:
				$title = __('Latest vulnerabilities', 'euvd-vuln');
				$res = EUVD_Client::latest($count, $cache_ttl);
				break;
		}

		if (!empty($res['error'])) {
			return '<div class="euvd-vuln euvd-vuln--error">' .
				esc_html__('EUVD data unavailable.', 'euvd-vuln') . ' ' .
				esc_html($res['error']) .
			'</div>';
		}

		$items = array_slice($res['items'], 0, $count);

		// Add configured wrapper classes
		$wrapper_class = trim('euvd-vuln euvd-vuln--list ' . $settings['css_container_class'] . ' ' . $extra_class);

		return self::render_html_list($title, $items, $wrapper_class);
	}

	public static function render_latest($atts): string {
		$atts = (array) $atts;
		$atts['type'] = 'latest';
		return self::render_list($atts);
	}

	public static function render_critical($atts): string {
		$atts = (array) $atts;
		$atts['type'] = 'critical';
		return self::render_list($atts);
	}

	public static function render_exploited($atts): string {
		$atts = (array) $atts;
		$atts['type'] = 'exploited';
		return self::render_list($atts);
	}

	/**
	 * Safe HTML renderer (v0.1)
	 */
	private static function render_html_list(string $title, array $items, string $wrapper_class): string {
		$out  = '<div class="' . esc_attr($wrapper_class) . '">';
		$out .= '<ul class="euvd-vuln__items">';

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$id          = isset($item['id']) ? (string) $item['id'] : '';
			$desc        = isset($item['description']) ? (string) $item['description'] : '';
			$date_pub    = isset($item['datePublished']) ? (string) $item['datePublished'] : '';
			$score       = isset($item['baseScore']) ? (string) $item['baseScore'] : '';
			$aliases_raw = isset($item['aliases']) ? (string) $item['aliases'] : '';
			$aliases     = trim(strtok($aliases_raw, "\n"));

			// Best-effort link: keep, but safe if it changes.
			$view_url = ($id !== '') ? ('https://euvd.enisa.europa.eu/vulnerability/' . rawurlencode($id)) : '';

			$out .= '<li class="euvd-vuln__item">';
			$out .= '<div class="euvd-vuln__top">';

			if ($view_url) {
				$out .= '<strong class="euvd-vuln__id"><a href="' . esc_url($view_url) . '" target="_blank" rel="noopener noreferrer">' .
					esc_html($id) .
				'</a></strong>';
			} else {
				$out .= '<strong class="euvd-vuln__id">' . esc_html($id) . '</strong>';
			}

			if ($score !== '') {
				$out .= ' <span class="euvd-vuln__score">(' . esc_html($score) . ')</span>';
			}

			$out .= '</div>';

			if ($aliases !== '') {
				$out .= '<div class="euvd-vuln__aliases">' . esc_html($aliases) . '</div>';
			}
			if ($date_pub !== '') {
				$out .= '<div class="euvd-vuln__date">' . esc_html($date_pub) . '</div>';
			}
			if ($desc !== '') {
				$out .= '<div class="euvd-vuln__desc">' . esc_html($desc) . '</div>';
			}

			$out .= '</li>';
		}

		$out .= '</ul></div>';
		return $out;
	}
}