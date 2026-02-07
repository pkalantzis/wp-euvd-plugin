<?php
/**
 * Plugin Name: WP EUVD Vulnerabilities
 * Description: Displays latest/critical/exploited vulnerabilities from ENISA EUVD via shortcodes, Gutenberg block, and widget.
 * Version: 0.1.0
 * Author: Panagiotis Kalantzis
 * License: GPLv2 or later
 * Requires PHP: 7.4
 *
 * Changelog:
 * - 0.1.0: Initial version.
 */

if (!defined('ABSPATH')) {
	exit;
}

define('EUVD_VULN_VERSION', '0.1');
define('EUVD_VULN_PLUGIN_FILE', __FILE__);
define('EUVD_VULN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EUVD_VULN_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EUVD_VULN_PLUGIN_DIR . 'includes/class-euvd-plugin.php';

add_action('plugins_loaded', static function () {
	\EUVD\Vuln\EUVD_Plugin::instance()->init();
});