<?php
namespace EUVD\Vuln;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Gutenberg block registration (server-side rendered).
 *
 * Uses /search API indirectly via Shortcodes::render_list().
 */
final class Blocks {

	public static function register(): void {
		add_action('init', [self::class, 'register_vulnerabilities_block']);
	}

	public static function register_vulnerabilities_block(): void {
		register_block_type(
			EUVD_VULN_PLUGIN_DIR . 'blocks/latest-vulnerabilities',
			[
				'render_callback' => [self::class, 'render_block'],
			]
		);
	}

	public static function render_block(array $attributes, string $content): string {
		$settings = EUVD_Plugin::get_settings();

		$type  = isset($attributes['type']) ? sanitize_key((string) $attributes['type']) : 'latest';
		$count = isset($attributes['count']) ? absint($attributes['count']) : (int) $settings['default_count'];
		$count = max(1, min(100, $count));

		return Shortcodes::render_list([
			'type'  => $type,
			'count' => (string) $count,
		]);
	}
}