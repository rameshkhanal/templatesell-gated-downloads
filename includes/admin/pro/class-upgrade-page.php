<?php
/**
 * "Upgrade to Pro" admin page and highlighted menu item.
 *
 * Shown only while Pro is inactive. Links to the product page and lists
 * what Pro adds.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin\Pro;

use TemplateSell\GatedDownloads\Core\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Upgrade_Page
 */
class Upgrade_Page {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const SLUG = 'tslgd-upgrade';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'menu_style' ) );
	}

	/**
	 * Is the Gated Downloads Pro add-on active?
	 *
	 * Uses the free plugin's public signal. Safe inside admin_menu and
	 * admin_notices, plugins_loaded has already run.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return (bool) apply_filters( 'tslgd_is_pro', false );
	}

	/**
	 * External product URL for the "Get Pro" call to action.
	 *
	 * @return string
	 */
	public static function buy_url() {
		/**
		 * Filter the Gated Downloads Pro product/checkout URL.
		 *
		 * @since 1.0.4
		 * @param string $url Product URL.
		 */
		return apply_filters( 'tslgd_pro_url', 'https://templatesell.net/gated-downloads/#pricing' );
	}

	/**
	 * Freemius hosted-checkout URL that starts the no-card 7-day free trial.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public static function trial_url() {
		/**
		 * Filter the Gated Downloads Pro free-trial checkout URL.
		 *
		 * @since 1.1.0
		 * @param string $url Trial checkout URL.
		 */
		return apply_filters( 'tslgd_pro_trial_url', 'https://checkout.freemius.com/plugin/34985/plan/57522/?trial=free' );
	}

	/**
	 * Admin URL of this upgrade page.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=' . self::SLUG );
	}

	/**
	 * The Pro feature list, shared by the page and the notice.
	 *
	 * @return array<int,array{0:string,1:string,2:string}> [ dashicon, title, description ].
	 */
	public static function features() {
		return array(
			array( 'forms', __( 'Custom form fields', 'templatesell-gated-downloads' ), __( 'Capture company, job title, or anything else you need.', 'templatesell-gated-downloads' ) ),
			array( 'email-alt', __( 'Autoresponder', 'templatesell-gated-downloads' ), __( 'Email the download link to each lead automatically.', 'templatesell-gated-downloads' ) ),
			array( 'admin-links', __( 'Webhooks', 'templatesell-gated-downloads' ), __( 'POST every new lead to Zapier, Make, or your CRM.', 'templatesell-gated-downloads' ) ),
			array( 'share', __( 'Mailchimp &amp; Brevo', 'templatesell-gated-downloads' ), __( 'Sync new leads straight to your email marketing list.', 'templatesell-gated-downloads' ) ),
			array( 'shield', __( 'reCAPTCHA v3', 'templatesell-gated-downloads' ), __( 'Block spam bots with invisible, score-based filtering.', 'templatesell-gated-downloads' ) ),
			array( 'lock', __( 'Protected links', 'templatesell-gated-downloads' ), __( 'Deliver files through signed, time-limited download links.', 'templatesell-gated-downloads' ) ),
		);
	}

	/**
	 * Register the highlighted "Upgrade to Pro" submenu (only when Pro is off).
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( self::is_pro() ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=' . Post_Type::POST_TYPE,
			__( 'Upgrade to Pro', 'templatesell-gated-downloads' ),
			'<span class="tslgd-go-pro">' . esc_html__( 'Upgrade to Pro', 'templatesell-gated-downloads' ) . '</span>',
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Tint the "Upgrade to Pro" menu item.
	 *
	 * Added as an inline style on the core admin stylesheet.
	 *
	 * @return void
	 */
	public function menu_style() {
		if ( self::is_pro() ) {
			return;
		}

		$css = '#adminmenu .tslgd-go-pro{color:#f0b849;font-weight:600}'
			. '#adminmenu .tslgd-go-pro::before{content:"\f155";font-family:dashicons;vertical-align:-3px;margin-right:3px}';

		wp_add_inline_style( 'common', $css );
	}

	/**
	 * Render the upgrade page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'templatesell-gated-downloads' ) );
		}

		$features  = self::features();
		$buy_url   = self::buy_url();
		$trial_url = self::trial_url();

		require __DIR__ . '/views/section-pro.php';
	}
}
