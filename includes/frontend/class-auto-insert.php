<?php
/**
 * Auto-insert: place a gate button in post content without a shortcode.
 *
 * Appends (or prepends) the standard trigger button to matching posts, so a
 * site can gate hundreds of posts from one setting instead of pasting a
 * shortcode into each one. Each post can opt out or choose a different file.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Frontend;

use TemplateSell\GatedDownloads\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Auto_Insert
 */
class Auto_Insert {

	/**
	 * Post meta holding a per-post override ('' | 'off' | a gate ID).
	 */
	const META_KEY = '_tslgd_auto_insert';

	/**
	 * Frontend controller (renders the trigger + queues the modal).
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Guard against re-entry when the_content runs inside our own render.
	 *
	 * @var bool
	 */
	private $rendering = false;

	/**
	 * Constructor.
	 *
	 * @param Frontend $frontend Frontend controller instance.
	 */
	public function __construct( Frontend $frontend ) {
		$this->frontend = $frontend;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Priority 20: after wpautop (10) and the shortcode pass (11).
		add_filter( 'the_content', array( $this, 'inject' ), 20 );
	}

	/**
	 * Append or prepend the gate button on matching singular views.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function inject( $content ) {
		if ( $this->rendering || ! $this->should_inject() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$gate_id = $this->gate_for_post( $post_id );
		if ( ! $gate_id ) {
			return $content;
		}

		// A post that already shows this gate by shortcode or block gets nothing
		// extra — no accidental duplicate buttons.
		if ( $this->already_has_gate( $post_id, $gate_id ) ) {
			return $content;
		}

		$settings = Settings::get();
		$button   = $this->render_button( $gate_id, $settings );
		if ( '' === $button ) {
			return $content;
		}

		return ( 'before' === $settings['auto_insert_position'] )
			? $button . $content
			: $content . $button;
	}

	/**
	 * Is this request a place where injection is allowed at all?
	 *
	 * @return bool
	 */
	private function should_inject() {
		$settings = Settings::get();

		if ( empty( $settings['auto_insert_enabled'] ) ) {
			return false;
		}
		// Only the real post body of a singular view — never archives, feeds,
		// excerpts, or secondary loops (widgets, related-posts blocks).
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() || is_feed() ) {
			return false;
		}

		$post_type = get_post_type();
		$types     = (array) $settings['auto_insert_post_types'];
		if ( ! in_array( $post_type, $types, true ) ) {
			return false;
		}

		$post_id  = get_the_ID();
		$excluded = array_filter( array_map( 'absint', explode( ',', (string) $settings['auto_insert_exclude_ids'] ) ) );
		if ( $excluded && in_array( (int) $post_id, $excluded, true ) ) {
			return false;
		}

		// Category filter applies to posts only (the taxonomy other types lack).
		$categories = array_filter( array_map( 'absint', (array) $settings['auto_insert_categories'] ) );
		if ( $categories && 'post' === $post_type && ! has_category( $categories, $post_id ) ) {
			return false;
		}

		/**
		 * Filter whether the auto-inserted button may appear on this post.
		 *
		 * @since 1.3.0
		 * @param bool $inject  Whether to inject.
		 * @param int  $post_id Current post ID.
		 */
		return (bool) apply_filters( 'tslgd_auto_insert', true, $post_id );
	}

	/**
	 * Resolve which gate this post should show, honoring its override.
	 *
	 * @param int $post_id Post ID.
	 * @return int Gate ID, or 0 for none.
	 */
	private function gate_for_post( $post_id ) {
		$override = (string) get_post_meta( $post_id, self::META_KEY, true );

		if ( 'off' === $override ) {
			return 0;
		}
		if ( '' !== $override && absint( $override ) ) {
			return absint( $override );
		}

		$settings = Settings::get();
		return absint( $settings['auto_insert_gate_id'] );
	}

	/**
	 * Does the post already place this gate itself (shortcode or block)?
	 *
	 * @param int $post_id Post ID.
	 * @param int $gate_id Gate ID.
	 * @return bool
	 */
	private function already_has_gate( $post_id, $gate_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$content = (string) $post->post_content;
		if ( false === strpos( $content, 'tslgd' ) ) {
			return false; // Fast path: no gate markup of any kind.
		}

		// Match [tslgd_download id="N"], [tslgd id="N"], and the block's id attr.
		return (bool) preg_match( '/\bid=["\']?' . (int) $gate_id . '\b/', $content )
			|| (bool) preg_match( '/"id"\s*:\s*' . (int) $gate_id . '\b/', $content );
	}

	/**
	 * Render the wrapped trigger button for a gate.
	 *
	 * @param int   $gate_id  Gate ID.
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function render_button( $gate_id, $settings ) {
		$this->rendering = true;
		$button          = $this->frontend->render_shortcode( array( 'id' => $gate_id ) );
		$this->rendering = false;

		if ( '' === $button ) {
			return ''; // Gate missing or unpublished.
		}

		$heading = trim( (string) $settings['auto_insert_heading'] );

		$html  = '<div class="tslgd-auto-insert">';
		$html .= '' !== $heading ? '<p class="tslgd-auto-insert__heading">' . esc_html( $heading ) . '</p>' : '';
		$html .= $button;
		$html .= '</div>';

		/**
		 * Filter the auto-inserted markup.
		 *
		 * @since 1.3.0
		 * @param string $html    Wrapper markup including the trigger button.
		 * @param int    $gate_id Gate ID.
		 */
		return (string) apply_filters( 'tslgd_auto_insert_html', $html, $gate_id );
	}
}
