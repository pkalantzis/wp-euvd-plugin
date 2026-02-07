<?php
namespace EUVD\Vuln;

if (!defined('ABSPATH')) {
	exit;
}

final class EUVD_Client {

	/**
	 * API base (per OpenAPI server URL).
	 * @see https://euvd.enisa.europa.eu/apidoc
	 */
	private const API_BASE = 'https://euvdservices.enisa.europa.eu/api';

	/** Conservative HTTP args (v0.2.1). */
	private static function http_args(): array {
		return [
			'timeout'     => 10,
			'redirection' => 3,
			'headers'     => [
				'Accept' => 'application/json',
			],
			'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
		];
	}

	/**
	 * Fetch /search (returns object: { items: [...], total: N }).
	 *
	 * Security/Best Practices:
	 * - WP HTTP API
	 * - Stable cache keys by normalized query args
	 * - Caches successful JSON parses
	 *
	 * @param array $params Query params for /search (e.g. size, page, exploited, fromScore, toScore, ...)
	 * @param int   $cache_ttl
	 * @return array{items: array, total: int, error: string|null}
	 */
	public static function search(array $params, int $cache_ttl): array {
		$url = self::API_BASE . '/search';

		$params = self::normalize_search_params($params);
		if (!empty($params)) {
			ksort($params);
			$url = add_query_arg($params, $url);
		}

		$cache_key = 'euvd_vuln_' . md5($url);

		$cached = get_transient($cache_key);
		if (is_array($cached) && isset($cached['items']) && is_array($cached['items'])) {
			return [
				'items' => $cached['items'],
				'total' => isset($cached['total']) ? absint($cached['total']) : 0,
				'error' => null,
			];
		}

		$response = wp_remote_get($url, self::http_args());
		if (is_wp_error($response)) {
			return ['items' => [], 'total' => 0, 'error' => $response->get_error_message()];
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);

		if ($code < 200 || $code >= 300) {
			return ['items' => [], 'total' => 0, 'error' => 'HTTP ' . $code];
		}

		$json = json_decode($body, true);
		if (!is_array($json)) {
			return ['items' => [], 'total' => 0, 'error' => 'Invalid JSON'];
		}

		$items = (isset($json['items']) && is_array($json['items'])) ? $json['items'] : [];
		$total = isset($json['total']) ? absint($json['total']) : 0;

		set_transient($cache_key, ['items' => $items, 'total' => $total], $cache_ttl);

		return ['items' => $items, 'total' => $total, 'error' => null];
	}

	/**
	 * "Latest" = most recent results via /search.
	 * Note: if the API supports explicit sorting params you can add them here.
	 */
	public static function latest(int $count, int $cache_ttl): array {
		return self::search(
			[
				'page' => 0,
				'size' => $count,
			],
			$cache_ttl
		);
	}

	/** "Critical" = CVSS baseScore 9.0–10.0 */
	public static function critical(int $count, int $cache_ttl): array {
		return self::search(
			[
				'fromScore' => 9,
				'toScore'   => 10,
				'page'      => 0,
				'size'      => $count,
			],
			$cache_ttl
		);
	}

	/** "Exploited" = exploited=true */
	public static function exploited(int $count, int $cache_ttl): array {
		return self::search(
			[
				'exploited' => true,
				'page'      => 0,
				'size'      => $count,
			],
			$cache_ttl
		);
	}

	/**
	 * Normalize /search parameters defensively.
	 * - size max 100 per API docs
	 * - page min 0
	 */
	private static function normalize_search_params(array $params): array {
		$out = [];

		if (isset($params['size'])) {
			$out['size'] = max(1, min(100, absint($params['size'])));
		}

		if (isset($params['page'])) {
			$out['page'] = max(0, absint($params['page']));
		} else {
			$out['page'] = 0;
		}

		// Booleans
		if (array_key_exists('exploited', $params)) {
			$out['exploited'] = !empty($params['exploited']) ? 'true' : 'false';
		}

		// Numeric filters
		if (isset($params['fromScore'])) {
			$out['fromScore'] = (string) floatval($params['fromScore']);
		}
		if (isset($params['toScore'])) {
			$out['toScore'] = (string) floatval($params['toScore']);
		}

		// Optional query string (full text)
		if (isset($params['q'])) {
			$out['q'] = sanitize_text_field((string) $params['q']);
		}

		return $out;
	}
}