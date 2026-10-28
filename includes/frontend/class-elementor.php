<?php
/**
 * Elementor integration.
 *
 * Registers the Gated Downloads widget when Elementor is active. The widget
 * file is only required inside the registration callback.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Elementor
 */
class Elementor {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Elementor 3.5+ signature; the widgets manager is passed in.
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	/**
	 * Register the widget with Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once TSLGD_PATH . 'includes/frontend/class-elementor-widget.php';

		$widget = new Elementor_Widget();
		if ( method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( $widget );
		} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( $widget ); // Elementor < 3.5.
		}
	}

	/**
	 * Published gated files as [ id => title ], for the widget's picker.
	 *
	 * @return array<int,string>
	 */
	public static function gate_options() {
		$out = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'tslgd_file',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $gate ) {
			$out[ $gate->ID ] = '' !== $gate->post_title ? $gate->post_title : ( '#' . $gate->ID );
		}
		return $out;
	}
}
