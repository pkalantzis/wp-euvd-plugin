<?php
namespace EUVD\Vuln\Widgets;

use EUVD\Vuln\EUVD_Plugin;
use EUVD\Vuln\Shortcodes;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Widget: Latest Vulnerabilities (EUVD) - v0.1
 *
 * Features:
 * - Uses EUVD /search API via Shortcodes renderer
 * - Optional widget title
 * - Optional data attribution (ENISA EUVD)
 * - Optional developer attribution (widget-only)
 */
final class EUVD_Latest_Widget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'euvd_latest_widget',
			__('EUVD: Latest Vulnerabilities', 'euvd-vuln'),
			[
				'description' => __('Shows latest vulnerabilities from ENISA EUVD.', 'euvd-vuln'),
			]
		);
	}

	public function form($instance) {
		$settings = EUVD_Plugin::get_settings();

		$title              = isset($instance['title']) ? (string) $instance['title'] : __('Latest vulnerabilities', 'euvd-vuln');
		$count              = isset($instance['count']) ? absint($instance['count']) : (int) $settings['default_count'];
		$hide_title         = !empty($instance['hide_title']);

		// Data attribution (default ON)
		$show_data_attr     = !isset($instance['show_data_attr']) || !empty($instance['show_data_attr']);
		$data_attr_text     = isset($instance['data_attr_text'])
			? (string) $instance['data_attr_text']
			: __('Data source: ENISA EU Vulnerability Database (EUVD)', 'euvd-vuln');

		// Developer attribution (default OFF)
		$show_dev_attr      = !empty($instance['show_dev_attr']);
		$dev_attr_text      = isset($instance['dev_attr_text'])
			? (string) $instance['dev_attr_text']
			: __('Widget by Your Name', 'euvd-vuln');
		$dev_attr_url       = isset($instance['dev_attr_url'])
			? (string) $instance['dev_attr_url']
			: '';

		$count = max(1, min(100, $count));
		?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
				<?php esc_html_e('Title:', 'euvd-vuln'); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr($this->get_field_id('title')); ?>"
				name="<?php echo esc_attr($this->get_field_name('title')); ?>"
				type="text"
				value="<?php echo esc_attr($title); ?>"
			/>
		</p>

		<p>
			<input type="checkbox"
				<?php checked($hide_title); ?>
				id="<?php echo esc_attr($this->get_field_id('hide_title')); ?>"
				name="<?php echo esc_attr($this->get_field_name('hide_title')); ?>"
			/>
			<label for="<?php echo esc_attr($this->get_field_id('hide_title')); ?>">
				<?php esc_html_e('Hide title', 'euvd-vuln'); ?>
			</label>
		</p>

		<p>
			<label for="<?php echo esc_attr($this->get_field_id('count')); ?>">
				<?php esc_html_e('Number to show:', 'euvd-vuln'); ?>
			</label>
			<input
				id="<?php echo esc_attr($this->get_field_id('count')); ?>"
				name="<?php echo esc_attr($this->get_field_name('count')); ?>"
				type="number"
				min="1"
				max="100"
				value="<?php echo esc_attr((string) $count); ?>"
				style="width: 90px;"
			/>
		</p>

		<hr />

		<strong><?php esc_html_e('Attribution', 'euvd-vuln'); ?></strong>

		<p>
			<input type="checkbox"
				<?php checked($show_data_attr); ?>
				id="<?php echo esc_attr($this->get_field_id('show_data_attr')); ?>"
				name="<?php echo esc_attr($this->get_field_name('show_data_attr')); ?>"
			/>
			<label for="<?php echo esc_attr($this->get_field_id('show_data_attr')); ?>">
				<?php esc_html_e('Show data source attribution', 'euvd-vuln'); ?>
			</label>
		</p>

		<p>
			<label for="<?php echo esc_attr($this->get_field_id('data_attr_text')); ?>">
				<?php esc_html_e('Data attribution text:', 'euvd-vuln'); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr($this->get_field_id('data_attr_text')); ?>"
				name="<?php echo esc_attr($this->get_field_name('data_attr_text')); ?>"
				type="text"
				value="<?php echo esc_attr($data_attr_text); ?>"
			/>
		</p>

		<hr />

		<p>
			<input type="checkbox"
				<?php checked($show_dev_attr); ?>
				id="<?php echo esc_attr($this->get_field_id('show_dev_attr')); ?>"
				name="<?php echo esc_attr($this->get_field_name('show_dev_attr')); ?>"
			/>
			<label for="<?php echo esc_attr($this->get_field_id('show_dev_attr')); ?>">
				<?php esc_html_e('Show developer attribution', 'euvd-vuln'); ?>
			</label>
		</p>

		<p>
			<label for="<?php echo esc_attr($this->get_field_id('dev_attr_text')); ?>">
				<?php esc_html_e('Developer attribution text:', 'euvd-vuln'); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr($this->get_field_id('dev_attr_text')); ?>"
				name="<?php echo esc_attr($this->get_field_name('dev_attr_text')); ?>"
				type="text"
				value="<?php echo esc_attr($dev_attr_text); ?>"
			/>
		</p>

		<p>
			<label for="<?php echo esc_attr($this->get_field_id('dev_attr_url')); ?>">
				<?php esc_html_e('Developer link (optional):', 'euvd-vuln'); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr($this->get_field_id('dev_attr_url')); ?>"
				name="<?php echo esc_attr($this->get_field_name('dev_attr_url')); ?>"
				type="url"
				value="<?php echo esc_attr($dev_attr_url); ?>"
			/>
		</p>
		<?php
	}

	public function update($new_instance, $old_instance) {
		return [
			'title'          => isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '',
			'count'          => isset($new_instance['count']) ? max(1, min(100, absint($new_instance['count']))) : 5,
			'hide_title'     => !empty($new_instance['hide_title']) ? 1 : 0,

			// Data attribution
			'show_data_attr' => !empty($new_instance['show_data_attr']) ? 1 : 0,
			'data_attr_text' => isset($new_instance['data_attr_text'])
				? sanitize_text_field($new_instance['data_attr_text'])
				: __('Data source: ENISA EU Vulnerability Database (EUVD)', 'euvd-vuln'),

			// Developer attribution
			'show_dev_attr'  => !empty($new_instance['show_dev_attr']) ? 1 : 0,
			'dev_attr_text'  => isset($new_instance['dev_attr_text'])
				? sanitize_text_field($new_instance['dev_attr_text'])
				: '',
			'dev_attr_url'   => isset($new_instance['dev_attr_url'])
				? esc_url_raw($new_instance['dev_attr_url'])
				: '',
		];
	}

	public function widget($args, $instance) {
		$title          = isset($instance['title']) ? (string) $instance['title'] : '';
		$count          = isset($instance['count']) ? absint($instance['count']) : 5;
		$hide_title     = !empty($instance['hide_title']);

		$show_data_attr = !empty($instance['show_data_attr']);
		$data_attr_text = isset($instance['data_attr_text']) ? (string) $instance['data_attr_text'] : '';

		$show_dev_attr  = !empty($instance['show_dev_attr']);
		$dev_attr_text  = isset($instance['dev_attr_text']) ? (string) $instance['dev_attr_text'] : '';
		$dev_attr_url   = isset($instance['dev_attr_url']) ? (string) $instance['dev_attr_url'] : '';

		$count = max(1, min(100, $count));

		echo $args['before_widget']; // phpcs:ignore

		if (!$hide_title && $title !== '') {
			echo $args['before_title'] . esc_html($title) . $args['after_title']; // phpcs:ignore
		}

		echo Shortcodes::render_list([
			'type'  => 'latest',
			'count' => (string) $count,
		]); // phpcs:ignore

		if ($show_data_attr && $data_attr_text !== '') {
			echo '<div class="euvd-vuln__attribution euvd-vuln__attribution--data">';
			echo esc_html($data_attr_text) . ' ';
			echo '<a href="https://euvd.enisa.europa.eu/" target="_blank" rel="noopener noreferrer">';
			echo esc_html__('EUVD', 'euvd-vuln');
			echo '</a></div>';
		}

		if ($show_dev_attr && $dev_attr_text !== '') {
			echo '<div class="euvd-vuln__attribution euvd-vuln__attribution--developer">';
			if ($dev_attr_url) {
				echo '<a href="' . esc_url($dev_attr_url) . '" target="_blank" rel="noopener noreferrer">';
				echo esc_html($dev_attr_text);
				echo '</a>';
			} else {
				echo esc_html($dev_attr_text);
			}
			echo '</div>';
		}

		echo $args['after_widget']; // phpcs:ignore
	}
}