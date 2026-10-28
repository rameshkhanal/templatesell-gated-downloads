<?php
/**
 * Gutenberg block: Gated Downloads Download.
 *
 * Server-rendered (dynamic) so it reuses the shortcode/modal pipeline. The
 * editor script is a no-build vanilla script registered on the editor screen.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Frontend;

use TemplateSell\GatedDownloads\Core\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block
 */
class Block {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register the dynamic block from its metadata.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			TSLGD_PATH . 'blocks/download-button',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Enqueue and localize the editor script (gate list for the dropdown).
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'tslgd-block',
			TSLGD_URL . 'blocks/download-button/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			TSLGD_VERSION,
			true
		);

		// Style the editor preview button like the front end.
		wp_enqueue_style(
			'tslgd-public',
			TSLGD_URL . 'assets/css/public.css',
			array(),
			TSLGD_VERSION
		);

		$settings = get_option( 'tslgd_settings', array() );

		$gates = get_posts(
			array(
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$list = array();
		foreach ( $gates as $gate ) {
			$list[] = array(
				'id'    => $gate->ID,
				'title' => $gate->post_title,
			);
		}

		wp_localize_script(
			'tslgd-block',
			'TslgdBlock',
			array(
				'gates'        => $list,
				'defaultLabel' => isset( $settings['button_label'] ) ? $settings['button_label'] : __( 'Download', 'templatesell-gated-downloads' ),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'tslgd-block', 'templatesell-gated-downloads' );
		}
	}

	/**
	 * Server render: reuse the shortcode so the modal pipeline stays single-source.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$gate_id = isset( $attributes['gateId'] ) ? absint( $attributes['gateId'] ) : 0;
		if ( ! $gate_id ) {
			return '';
		}

		$label      = isset( $attributes['label'] ) ? str_replace( '"', '', (string) $attributes['label'] ) : '';
		$shortcode  = '[tslgd_download id="' . $gate_id . '"';
		$shortcode .= '' !== $label ? ' label="' . esc_attr( $label ) . '"' : '';
		$shortcode .= ']';

		return do_shortcode( $shortcode );
	}
}
