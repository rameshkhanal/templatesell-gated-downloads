<?php
/**
 * Upgrade-to-Pro page view.
 *
 * @package Gated Downloads
 *
 * @var array  $features Feature list from Upgrade_Page::features().
 * @var string $buy_url  External product URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Free vs Pro comparison rows: [ label, in-free ].
$tslgd_rows = array(
	array( __( 'Gated downloads &amp; lead capture', 'templatesell-gated-downloads' ), true ),
	array( __( 'Accessible modal, shortcode &amp; block', 'templatesell-gated-downloads' ), true ),
	array( __( 'Categories &amp; CSV export', 'templatesell-gated-downloads' ), true ),
	array( __( 'Brand colors', 'templatesell-gated-downloads' ), true ),
	array( __( 'Custom form fields', 'templatesell-gated-downloads' ), false ),
	array( __( 'Autoresponder &amp; webhooks', 'templatesell-gated-downloads' ), false ),
	array( __( 'Mailchimp &amp; Brevo sync', 'templatesell-gated-downloads' ), false ),
	array( __( 'reCAPTCHA v3 spam protection', 'templatesell-gated-downloads' ), false ),
	array( __( 'Protected, expiring download links', 'templatesell-gated-downloads' ), false ),
);
?>
<div class="wrap tslgd-wrap tslgd-upgrade">

	<?php // Anchor admin notices here (above the hero) instead of inside it. ?>
	<h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e( 'Upgrade to Pro', 'templatesell-gated-downloads' ); ?></h1>
	<hr class="wp-header-end" />

	<div class="tslgd-up-hero">
		<span class="tslgd-up-badge"><?php esc_html_e( 'Gated Downloads Pro', 'templatesell-gated-downloads' ); ?></span>
		<h1><?php esc_html_e( 'Do more with Gated Downloads Pro', 'templatesell-gated-downloads' ); ?></h1>
		<p><?php esc_html_e( 'You already capture leads. Pro turns them into automated, protected, marketing-ready workflows — without touching a line of code.', 'templatesell-gated-downloads' ); ?></p>
		<p class="tslgd-up-actions">
			<a class="button button-primary button-hero" href="<?php echo esc_url( $trial_url ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Start 7-day free trial', 'templatesell-gated-downloads' ); ?>
			</a>
			<a class="button button-hero button-secondary" href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'See pricing', 'templatesell-gated-downloads' ); ?>
			</a>
		</p>
		<p class="tslgd-up-trialnote description"><?php esc_html_e( 'Full Pro access for 7 days — no credit card required.', 'templatesell-gated-downloads' ); ?></p>
	</div>

	<div class="tslgd-up-grid">
		<?php foreach ( $features as $tslgd_feature ) : ?>
			<div class="tslgd-up-card">
				<span class="tslgd-up-ic dashicons dashicons-<?php echo esc_attr( $tslgd_feature[0] ); ?>" aria-hidden="true"></span>
				<h3><?php echo wp_kses( $tslgd_feature[1], array() ); ?></h3>
				<p><?php echo wp_kses( $tslgd_feature[2], array() ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="tslgd-card tslgd-up-compare">
		<h2><?php esc_html_e( 'Free vs Pro', 'templatesell-gated-downloads' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feature', 'templatesell-gated-downloads' ); ?></th>
					<th class="tslgd-up-col"><?php esc_html_e( 'Free', 'templatesell-gated-downloads' ); ?></th>
					<th class="tslgd-up-col tslgd-up-pro"><?php esc_html_e( 'Pro', 'templatesell-gated-downloads' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tslgd_rows as $tslgd_row ) : ?>
					<tr>
						<td><?php echo wp_kses( $tslgd_row[0], array() ); ?></td>
						<td class="tslgd-up-col">
							<?php if ( $tslgd_row[1] ) : ?>
								<span class="dashicons dashicons-yes tslgd-up-yes" aria-label="<?php esc_attr_e( 'Included', 'templatesell-gated-downloads' ); ?>"></span>
							<?php else : ?>
								<span class="tslgd-up-no" aria-label="<?php esc_attr_e( 'Not included', 'templatesell-gated-downloads' ); ?>">&mdash;</span>
							<?php endif; ?>
						</td>
						<td class="tslgd-up-col tslgd-up-pro">
							<span class="dashicons dashicons-yes tslgd-up-yes" aria-label="<?php esc_attr_e( 'Included', 'templatesell-gated-downloads' ); ?>"></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="tslgd-up-foot">
			<a class="button button-primary" href="<?php echo esc_url( $trial_url ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Start 7-day free trial', 'templatesell-gated-downloads' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Upgrade now', 'templatesell-gated-downloads' ); ?>
			</a>
			<span class="tslgd-up-note"><?php esc_html_e( 'No credit card required. Already purchased? Install and activate the Gated Downloads Pro add-on to unlock these features.', 'templatesell-gated-downloads' ); ?></span>
		</p>
	</div>

</div>
<?php
unset( $tslgd_rows );
