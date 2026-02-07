# EUVD Vulnerabilities

WordPress plugin that displays public vulnerability data from the **ENISA European Union Vulnerability Database (EUVD)**.

Uses the official **EUVD Search API** and supports blocks, shortcodes, and widgets with configurable caching and admin tools.

This is **not** an official ENISA plugin.

## Features

- EUVD Search API integration (read-only)
- Gutenberg block, shortcodes, and widget
- Configurable number of vulnerabilities
- Transient-based caching with TTL control
- Admin tools: clear cache, test API connection
- Optional frontend CSS
- No tracking, no cookies

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Copy the plugin directory to `wp-content/plugins/`
2. Activate the plugin in WordPress Admin

## Usage

Shortcode example: [euvd_latest count="10"]

Block name: EUVD Vulnerabilities

## License

GPL v2 or later.