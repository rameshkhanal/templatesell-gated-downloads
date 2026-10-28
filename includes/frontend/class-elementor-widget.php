<?php
/**
 * The Elementor widget itself.
 *
 * Loaded only by Frontend\Elementor::register_widget(), which runs inside
 * Elementor's own registration hook — so \Elementor\Widget_Base is guaranteed
 * to exist by the time this file is parsed.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Elementor_Widget
 */
class Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'tslgd_download';
	}

	/**
	 * Widget title shown in the editor panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Gated Downloads', 'templatesell-gated-downloads' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-download-button';
	}

	/**
	 * Editor categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Editor search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'download', 'gate', 'lead', 'form', 'file', 'opt-in' );
	}

	/**
	 * Register the widget controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'tslgd_content',
			array(
				'label' => __( 'Gated Download', 'templatesell-gated-downloads' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$gates = Elementor::gate_options();

		$this->add_control(
			'gate_id',
			array(
				'label'       => __( 'Gated file', 'templatesell-gated-downloads' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $gates,
				'default'     => (string) key( $gates ),
				'description' => $gates
					? __( 'Pick the file this button unlocks.', 'templatesell-gated-downloads' )
					: __( 'No published gated files yet — create one under Gated Downloads.', 'templatesell-gated-downloads' ),
			)
		);

		$this->add_control(
			'label',
			array(
				'label'       => __( 'Button label', 'templatesell-gated-downloads' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Leave empty to use the default', 'templatesell-gated-downloads' ),
			)
		);

		$this->add_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'templatesell-gated-downloads' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'templatesell-gated-downloads' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'templatesell-gated-downloads' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'templatesell-gated-downloads' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}}' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget on the front end (and in the editor preview).
	 *
	 * Delegates to the shortcode so markup, modal queueing, and analytics are
	 * identical to every other placement.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$gate_id  = isset( $settings['gate_id'] ) ? absint( $settings['gate_id'] ) : 0;

		if ( ! $gate_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p>' . esc_html__( 'Select a gated file to show its download button.', 'templatesell-gated-downloads' ) . '</p>';
			}
			return;
		}

		$atts = 'id="' . $gate_id . '"';
		if ( ! empty( $settings['label'] ) ) {
			$atts .= ' label="' . esc_attr( $settings['label'] ) . '"';
		}

		// Shortcode output is assembled and escaped by Frontend::render_shortcode().
		echo do_shortcode( '[tslgd_download ' . $atts . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
