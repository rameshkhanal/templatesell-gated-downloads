<?php
/**
 * Deactivation modal that offers the Pro trial.
 *
 * Client-side only, nothing is sent anywhere. Only wired up while Pro is
 * inactive.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Admin\Pro\Upgrade_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivate_Feedback
 */
class Deactivate_Feedback {

	/**
	 * Register hooks (Plugins screen only, Pro inactive only).
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( Upgrade_Page::is_pro() ) {
			return;
		}
		add_filter( 'plugin_action_links_' . TSLGD_BASENAME, array( $this, 'tag_deactivate_link' ) );
		add_action( 'admin_footer-plugins.php', array( $this, 'render_modal' ) );
	}

	/**
	 * Tag the core "Deactivate" link so the modal can intercept just this plugin.
	 *
	 * @param array $actions Row action links.
	 * @return array
	 */
	public function tag_deactivate_link( $actions ) {
		if ( isset( $actions['deactivate'] ) && is_string( $actions['deactivate'] ) ) {
			$actions['deactivate'] = str_replace( '<a ', '<a class="tslgd-deactivate-link" ', $actions['deactivate'] );
		}
		return $actions;
	}

	/**
	 * Print the modal markup, styles, and interception script.
	 *
	 * @return void
	 */
	public function render_modal() {
		$trial = Upgrade_Page::trial_url();
		?>
		<div id="tslgd-deact-modal" class="tslgd-deact-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="tslgd-deact-title">
			<div class="tslgd-deact-box">
				<button type="button" class="tslgd-deact-x" aria-label="<?php esc_attr_e( 'Close', 'templatesell-gated-downloads' ); ?>">&times;</button>
				<h2 id="tslgd-deact-title"><?php esc_html_e( 'Before you deactivate…', 'templatesell-gated-downloads' ); ?></h2>
				<p><?php esc_html_e( 'Tired of exporting and copy-pasting leads by hand? Gated Downloads Pro auto-emails each file and syncs every lead to Mailchimp, Brevo, or your CRM — automatically. Try it free for 7 days, no credit card.', 'templatesell-gated-downloads' ); ?></p>
				<div class="tslgd-deact-actions">
					<a class="button button-primary button-hero" href="<?php echo esc_url( $trial ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Start free trial instead', 'templatesell-gated-downloads' ); ?></a>
					<a class="button tslgd-deact-go" href="#"><?php esc_html_e( 'Deactivate anyway', 'templatesell-gated-downloads' ); ?></a>
				</div>
			</div>
		</div>
		<style>
			.tslgd-deact-overlay{position:fixed;inset:0;z-index:100000;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:20px}
			.tslgd-deact-box{position:relative;background:#fff;border-radius:12px;max-width:460px;width:100%;padding:28px 30px;box-shadow:0 24px 60px -20px rgba(0,0,0,.5);animation:tslgd-deact-in .18s ease}
			@keyframes tslgd-deact-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
			.tslgd-deact-box h2{margin:0 0 10px;font-size:20px}
			.tslgd-deact-box p{margin:0 0 20px;color:#3c434a;font-size:14px;line-height:1.6}
			.tslgd-deact-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
			.tslgd-deact-go{color:#646970}
			.tslgd-deact-x{position:absolute;top:10px;right:12px;border:0;background:transparent;font-size:22px;line-height:1;color:#9aa1a9;cursor:pointer}
			.tslgd-deact-x:hover{color:#1d2327}
		</style>
		<script>
		( function () {
			var link = document.querySelector( '.tslgd-deactivate-link' );
			var modal = document.getElementById( 'tslgd-deact-modal' );
			if ( ! link || ! modal ) { return; }
			var target = link.getAttribute( 'href' );

			function open() { modal.style.display = 'flex'; }
			function close() { modal.style.display = 'none'; }

			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				open();
			} );
			modal.querySelector( '.tslgd-deact-go' ).addEventListener( 'click', function ( e ) {
				e.preventDefault();
				window.location.href = target;
			} );
			modal.querySelector( '.tslgd-deact-x' ).addEventListener( 'click', close );
			modal.addEventListener( 'click', function ( e ) {
				if ( e.target === modal ) { close(); }
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) { close(); }
			} );
		}() );
		</script>
		<?php
	}
}
