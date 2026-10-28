<?php
/**
 * Inline "upgrade to Pro" upsell card.
 *
 * Rendered inside the Gated Downloads admin pages (Leads + Settings) via the
 * `tslgd_admin_upsell` action, styled as one of the plugin's own cards, and
 * only while the Pro add-on is inactive. Page content — not a floating nag — so
 * it stays WordPress.org-friendly.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin\Pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pro_Notice
 */
class Pro_Notice {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'tslgd_admin_upsell', array( $this, 'render_card' ) );
		add_action( 'manage_posts_extra_tablenav', array( $this, 'render_list_upsell' ) );
	}

	/**
	 * Render the upsell below the Gated Files list table.
	 *
	 * @param string $which Tablenav position ('top' or 'bottom').
	 * @return void
	 */
	public function render_list_upsell( $which ) {
		if ( 'bottom' !== $which ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! isset( $screen->post_type ) || 'tslgd_file' !== $screen->post_type ) {
			return;
		}
		echo '<div class="tslgd-list-upsell">';
		$this->render_card();
		echo '</div>';
	}

	/**
	 * Render the inline upsell card.
	 *
	 * @return void
	 */
	public function render_card() {
		if ( ! current_user_can( 'manage_options' ) || Upgrade_Page::is_pro() ) {
			return;
		}

		$features = Upgrade_Page::features();
		?>
		<div class="tslgd-card tslgd-upsell">
			<div class="tslgd-upsell-head">
				<span class="tslgd-upsell-ic dashicons dashicons-star-filled" aria-hidden="true"></span>
				<div class="tslgd-upsell-intro">
					<h2><?php esc_html_e( 'Upgrade to Pro', 'templatesell-gated-downloads' ); ?></h2>
					<p><?php esc_html_e( 'Automate delivery, sync leads to your email list, and protect your files — no code required.', 'templatesell-gated-downloads' ); ?></p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( Upgrade_Page::trial_url() ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Start 7-day free trial', 'templatesell-gated-downloads' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( Upgrade_Page::page_url() ); ?>">
					<?php esc_html_e( 'See Pro features', 'templatesell-gated-downloads' ); ?>
				</a>
			</div>
			<ul class="tslgd-upsell-feats">
				<?php foreach ( $features as $feature ) : ?>
					<li>
						<span class="dashicons dashicons-yes" aria-hidden="true"></span>
						<?php echo wp_kses( $feature[1], array() ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
