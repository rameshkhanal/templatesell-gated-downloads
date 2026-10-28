<?php
/**
 * "Automation" teaser screen.
 *
 * Shows the integrations Pro unlocks (Mailchimp, Brevo, webhooks,
 * autoresponder) as locked cards. Only registered while Pro is inactive.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Admin\Pro\Upgrade_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Automation_Teaser
 */
class Automation_Teaser {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const SLUG = 'tslgd-automation';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 98 );
	}

	/**
	 * Register the "Automation" submenu (only while Pro is inactive).
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( Upgrade_Page::is_pro() ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=' . Post_Type::POST_TYPE,
			__( 'Automation', 'templatesell-gated-downloads' ),
			__( 'Automation', 'templatesell-gated-downloads' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * The locked integration cards shown on the screen.
	 *
	 * @return array<int,array{icon:string,name:string,desc:string,field:string,placeholder:string}>
	 */
	private function cards() {
		return array(
			array(
				'icon'        => 'email-alt',
				'name'        => __( 'Mailchimp', 'templatesell-gated-downloads' ),
				'desc'        => __( 'Add every new lead to a Mailchimp audience automatically.', 'templatesell-gated-downloads' ),
				'field'       => __( 'API key', 'templatesell-gated-downloads' ),
				'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxx-us21',
			),
			array(
				'icon'        => 'buddicons-activity',
				'name'        => __( 'Brevo (Sendinblue)', 'templatesell-gated-downloads' ),
				'desc'        => __( 'Sync leads to a Brevo list and trigger your automations.', 'templatesell-gated-downloads' ),
				'field'       => __( 'API key', 'templatesell-gated-downloads' ),
				'placeholder' => 'xkeysib-xxxxxxxxxxxxxxxxxxxx',
			),
			array(
				'icon'        => 'admin-links',
				'name'        => __( 'Webhook', 'templatesell-gated-downloads' ),
				'desc'        => __( 'POST each lead to Zapier, Make, n8n, or your own CRM endpoint.', 'templatesell-gated-downloads' ),
				'field'       => __( 'Endpoint URL', 'templatesell-gated-downloads' ),
				'placeholder' => 'https://hooks.zapier.com/hooks/catch/…',
			),
			array(
				'icon'        => 'email',
				'name'        => __( 'Custom autoresponder', 'templatesell-gated-downloads' ),
				'desc'        => __( 'Write your own download email — subject, message, and {name} {file} {file_url} placeholders.', 'templatesell-gated-downloads' ),
				'field'       => __( 'Subject', 'templatesell-gated-downloads' ),
				'placeholder' => __( 'Here is your download: {file}', 'templatesell-gated-downloads' ),
			),
		);
	}

	/**
	 * Render the teaser screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'templatesell-gated-downloads' ) );
		}

		$trial = Upgrade_Page::trial_url();
		$cards = $this->cards();
		?>
		<div class="wrap tslgd-wrap">
			<h1 class="tslgd-page-title">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Automation', 'templatesell-gated-downloads' ); ?>
			</h1>

			<div class="tslgd-card tslgd-auto-intro">
				<div>
					<h2><?php esc_html_e( 'Put your leads on autopilot', 'templatesell-gated-downloads' ); ?></h2>
					<p><?php esc_html_e( 'Right now every lead is captured and exported by hand. Connect an integration below and Gated Downloads Pro delivers files and syncs contacts automatically — the instant someone submits.', 'templatesell-gated-downloads' ); ?></p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $trial ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Start 7-day free trial', 'templatesell-gated-downloads' ); ?>
				</a>
			</div>

			<div class="tslgd-auto-grid">
				<?php foreach ( $cards as $card ) : ?>
					<div class="tslgd-card tslgd-auto-card">
						<div class="tslgd-auto-head">
							<span class="tslgd-auto-ic dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
							<h3><?php echo esc_html( $card['name'] ); ?></h3>
							<span class="tslgd-auto-pro">Pro</span>
						</div>
						<p class="tslgd-auto-desc"><?php echo esc_html( $card['desc'] ); ?></p>
						<label class="tslgd-auto-flabel"><?php echo esc_html( $card['field'] ); ?></label>
						<div class="tslgd-auto-field">
							<input type="text" value="" placeholder="<?php echo esc_attr( $card['placeholder'] ); ?>" disabled aria-hidden="true" />
							<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						</div>
						<a class="button tslgd-auto-connect" href="<?php echo esc_url( $trial ); ?>" target="_blank" rel="noopener">
							<span class="dashicons dashicons-unlock" aria-hidden="true"></span>
							<?php esc_html_e( 'Connect with Pro', 'templatesell-gated-downloads' ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>

			<p class="tslgd-auto-foot">
				<?php esc_html_e( 'No credit card required. Cancel anytime during the trial.', 'templatesell-gated-downloads' ); ?>
				<a href="<?php echo esc_url( Upgrade_Page::page_url() ); ?>"><?php esc_html_e( 'See everything in Pro →', 'templatesell-gated-downloads' ); ?></a>
			</p>
		</div>
		<?php
	}
}
