<?php
/**
 * Centralized asset registration and conditional enqueueing.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads;

use TemplateSell\GatedDownloads\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_public' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin' ) );
	}

	/**
	 * Register (but do not enqueue) public assets.
	 *
	 * Registering here lets the shortcode/block enqueue on demand so the CSS/JS
	 * only load on pages that actually contain a gate.
	 *
	 * @return void
	 */
	public function register_public() {
		wp_register_style(
			'tslgd-public',
			TSLGD_URL . 'assets/css/public.css',
			array(),
			self::asset_version( 'assets/css/public.css' )
		);

		wp_register_script(
			'tslgd-public',
			TSLGD_URL . 'assets/js/public.js',
			array(),
			self::asset_version( 'assets/js/public.js' ),
			true
		);

		wp_localize_script(
			'tslgd-public',
			'TslgdData',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'tslgd/v1/submit' ) ),
				'beaconUrl' => esc_url_raw( rest_url( 'tslgd/v1/beacon' ) ),
				'unlockUrl' => esc_url_raw( rest_url( 'tslgd/v1/unlock' ) ),
				'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'required' => __( 'This field is required.', 'templatesell-gated-downloads' ),
					'email'    => __( 'Please enter a valid email address.', 'templatesell-gated-downloads' ),
					'error'    => __( 'Something went wrong. Please try again.', 'templatesell-gated-downloads' ),
				),
			)
		);

		// Apply the admin-chosen brand colors by overriding the CSS variables.
		// Printed only when the gate stylesheet is actually enqueued on a page.
		$dynamic = self::dynamic_css();
		if ( '' !== $dynamic ) {
			wp_add_inline_style( 'tslgd-public', $dynamic );
		}
	}

	/**
	 * Version string for a bundled asset: its file modification time, or the
	 * plugin version as a fallback.
	 *
	 * @param string $rel_path Path relative to the plugin root.
	 * @return string
	 */
	private static function asset_version( $rel_path ) {
		$file  = TSLGD_PATH . $rel_path;
		$mtime = is_readable( $file ) ? filemtime( $file ) : 0;
		return $mtime ? (string) $mtime : TSLGD_VERSION;
	}

	/**
	 * Build the CSS-variable override from the saved brand colors.
	 *
	 * @return string CSS, or '' if colors match the built-in defaults.
	 */
	private static function dynamic_css() {
		$settings = Settings::get();
		$primary  = isset( $settings['primary_color'] ) ? $settings['primary_color'] : '#2563eb';
		$header   = isset( $settings['header_color'] ) ? $settings['header_color'] : '#111827';

		$primary = self::normalize_hex( $primary );
		$header  = self::normalize_hex( $header );
		if ( '' === $primary && '' === $header ) {
			return '';
		}

		$vars = array();
		if ( '' !== $primary ) {
			$vars[] = '--tslgd-accent:' . $primary;
			$vars[] = '--tslgd-accent-hover:' . self::shade( $primary, -0.15 );
			$vars[] = '--tslgd-accent-soft:' . self::rgba( $primary, 0.12 );
		}
		if ( '' !== $header ) {
			$vars[] = '--tslgd-header-from:' . $header;
			$vars[] = '--tslgd-header-to:' . self::shade( $header, 0.14 );
		}

		// Same selectors public.css scopes the variables to, so this wins by source order.
		return '.tslgd-btn-wrap,.tslgd-overlay,.tslgd-lock{' . implode( ';', $vars ) . '}';
	}

	/**
	 * Normalize a hex color to #rrggbb, or '' if invalid.
	 *
	 * @param string $hex Raw color.
	 * @return string
	 */
	private static function normalize_hex( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ? '#' . strtolower( $hex ) : '';
	}

	/**
	 * Lighten (positive) or darken (negative) a hex color toward white/black.
	 *
	 * @param string $hex    #rrggbb color.
	 * @param float  $amount -1.0..1.0.
	 * @return string
	 */
	private static function shade( $hex, $amount ) {
		list( $r, $g, $b ) = self::rgb( $hex );
		$mix               = $amount < 0 ? 0 : 255;
		$amount            = abs( $amount );
		$r                 = (int) round( $r + ( $mix - $r ) * $amount );
		$g                 = (int) round( $g + ( $mix - $g ) * $amount );
		$b                 = (int) round( $b + ( $mix - $b ) * $amount );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Build an rgba() string from a hex color.
	 *
	 * @param string $hex   #rrggbb color.
	 * @param float  $alpha Opacity 0..1.
	 * @return string
	 */
	private static function rgba( $hex, $alpha ) {
		list( $r, $g, $b ) = self::rgb( $hex );
		return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * Convert #rrggbb to an [r, g, b] array.
	 *
	 * @param string $hex #rrggbb color.
	 * @return int[]
	 */
	private static function rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Enqueue admin assets only on Gated Downloads screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function maybe_enqueue_admin( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'tslgd-admin',
			TSLGD_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			self::asset_version( 'assets/css/admin.css' )
		);

		// The gated-file edit screen needs the Media Library picker.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->post_type ) && 'tslgd_file' === $screen->post_type ) {
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'tslgd-admin',
			TSLGD_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ),
			self::asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'tslgd-admin',
			'TslgdAdmin',
			array(
				'mediaTitle'  => __( 'Select or upload a file', 'templatesell-gated-downloads' ),
				'mediaButton' => __( 'Use this file', 'templatesell-gated-downloads' ),
				'copied'      => __( 'Copied!', 'templatesell-gated-downloads' ),
			)
		);
	}

	/**
	 * Is the current admin screen a Gated Downloads screen?
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private function is_plugin_screen( $hook ) {
		if ( is_string( $hook ) && false !== strpos( $hook, 'templatesell-gated-downloads' ) ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->post_type ) && 'tslgd_file' === $screen->post_type ) {
			return true;
		}

		return false;
	}
}
