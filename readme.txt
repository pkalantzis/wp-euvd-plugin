=== EUVD Vulnerabilities ===
Contributors: pkalantzis
Tags: security, vulnerabilities, euvd, enisa, cve, cybersecurity, eu
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Displays public vulnerability information from the ENISA European Union Vulnerability Database (EUVD).

The plugin uses the official EUVD Search API and provides Gutenberg blocks, shortcodes, and widgets with configurable caching and admin tools.

This plugin is not affiliated with or endorsed by ENISA.

== Features ==
* EUVD Search API integration (read-only)
* Gutenberg block
* Shortcodes and widget
* Configurable number of vulnerabilities
* Transient-based caching with configurable TTL
* Admin tools:
  * Clear cache
  * Test API connection (HTTP status + latency)
* Optional frontend CSS
* No tracking, no cookies

== Shortcodes ==
[euvd_vulnerabilities type="latest|critical|exploited" count="5"]
[euvd_latest count="5"]
[euvd_critical count="5"]
[euvd_exploited count="5"]

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/
2. Activate the plugin through the Plugins menu
3. Configure settings under EUVD Vulnerabilities → Settings

== Changelog ==
= 0.2.1 =
* Minor file versioning fix

= 0.2.0 =
* Added dedicated admin menu with Dashboard, Settings, Tools, and About pages
* Added Tools page with cache clearing and API connection test
* Migrated to search-only API architecture
* Improved caching, configuration, and admin UX

= 0.1.0 =
* Initial release