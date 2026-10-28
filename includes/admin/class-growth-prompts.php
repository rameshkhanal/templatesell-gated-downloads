<?php
/**
 * Growth prompts: a review request and a contextual Pro/trial upsell, both
 * triggered by how many leads the site has actually captured so they appear at
 * a moment of proven value rather than nagging on activation.
 *
 * @package TemplateSell\GatedDownloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Core\Lead_Repository;
use TemplateSell\GatedDownloads\Admin\Pro\Upgrade_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Growth_Prompts
 */
class Growth_Prompts {

	const REVIEW_THRESHOLD = 10;
	const UPSELL_THRESHOLD  = 25;
	const SYNC_THRESHOLD    = 5;

	/**
	 * Lead counts at which a dismissed upsell is shown again.
	 *
	 * @since 1.1.2
	 * @var int[]
	 */
	const UPSELL_MILESTONES = array( 100, 250, 500, 1000 );

	const OPT_REVIEW = 'tslgd_review_state';   // 0 = none, -1 = done/never, >0 = snooze-until timestamp.
	const OPT_UPSELL = 'tslgd_upsell_dismissed';
	const OPT_SYNC   = 'tslgd_sync_nudge_dismissed';

	const REVIEW_URL = 'https://wordpress.org/support/plugin/templatesell-gated-downloads/reviews/#new-post';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_notices', array( $this, 'review_notice' ) );
		add_action( 'admin_post_tslgd_review_action', array( $this, 'handle_review_action' ) );
		add_action( 'admin_post_tslgd_upsell_dismiss', array( $this, 'handle_upsell_dismiss' ) );
		add_action( 'admin_post_tslgd_sync_dismiss', array( $this, 'handle_sync_dismiss' ) );
	}

	/**
	 * Current lead total (cached per request).
	 *
	 * @return int
	 */
	private function lead_count() {
		static $count = null;
		if ( null === $count ) {
			$count = (int) Lead_Repository::count();
		}
		return $count;
	}

	/**
	 * Whether the current screen belongs to this plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		if ( isset( $screen->post_type ) && Post_Type::POST_TYPE === $screen->post_type ) {
			return true;
		}
		return isset( $screen->id ) && false !== strpos( (string) $screen->id, 'tslgd' );
	}

	/* --------------------------------------------------------------------- *
	 * Review request.
	 * --------------------------------------------------------------------- */

	/**
	 * Ask for a review once the site has captured enough leads to see value.
	 *
	 * @return void
	 */
	public function review_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->is_plugin_screen() ) {
			return;
		}
		if ( $this->lead_count() < self::REVIEW_THRESHOLD ) {
			return;
		}
		$state = (int) get_option( self::OPT_REVIEW, 0 );
		if ( -1 === $state ) {
			return; // Already reviewed / opted out.
		}
		if ( $state > 0 && $state > time() ) {
			return; // Snoozed.
		}

		$rate  = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_review_action&do=rate' ), 'tslgd_review_action' );
		$later = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_review_action&do=later' ), 'tslgd_review_action' );
		$never = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_review_action&do=never' ), 'tslgd_review_action' );
		?>
		<div class="notice notice-info is-dismissible tslgd-review-notice">
			<p>
				<strong><?php esc_html_e( 'Enjoying Gated Downloads?', 'templatesell-gated-downloads' ); ?></strong>
				<?php
				printf(
					/* translators: %d: number of leads captured. */
					esc_html__( "You've already captured %d leads. A quick ★★★★★ review really helps us keep it free and improving.", 'templatesell-gated-downloads' ),
					(int) $this->lead_count()
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $rate ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Leave a review', 'templatesell-gated-downloads' ); ?></a>
				<a class="button" href="<?php echo esc_url( $later ); ?>"><?php esc_html_e( 'Maybe later', 'templatesell-gated-downloads' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( $never ); ?>"><?php esc_html_e( 'Already did / don’t ask again', 'templatesell-gated-downloads' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the review notice actions.
	 *
	 * @return void
	 */
	public function handle_review_action() {
		check_admin_referer( 'tslgd_review_action' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'templatesell-gated-downloads' ) );
		}
		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';

		if ( 'later' === $do ) {
			update_option( self::OPT_REVIEW, time() + ( 14 * DAY_IN_SECONDS ) );
			$dest = wp_get_referer();
		} elseif ( 'never' === $do ) {
			update_option( self::OPT_REVIEW, -1 );
			$dest = wp_get_referer();
		} else { // rate.
			update_option( self::OPT_REVIEW, -1 );
			$dest = self::REVIEW_URL;
		}

		wp_safe_redirect( $dest ? $dest : admin_url() );
		exit;
	}

	/* Sync nudge strip. */

	/**
	 * Render the quantified "these leads weren't auto-synced" nudge.
	 *
	 * Appears once the site has a handful of leads but before the full upsell
	 * card takes over at UPSELL_THRESHOLD, so the two never show together.
	 *
	 * @return void
	 */
	public function render_sync_nudge() {
		if ( Upgrade_Page::is_pro() ) {
			return;
		}
		$count = $this->lead_count();
		if ( $count < self::SYNC_THRESHOLD || $count >= self::UPSELL_THRESHOLD ) {
			return;
		}
		if ( get_option( self::OPT_SYNC ) ) {
			return;
		}

		$trial   = Upgrade_Page::trial_url();
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_sync_dismiss' ), 'tslgd_sync_dismiss' );
		?>
		<div class="tslgd-sync-nudge">
			<span class="tslgd-sync-ic dashicons dashicons-update" aria-hidden="true"></span>
			<p class="tslgd-sync-copy">
				<strong>
				<?php
				printf(
					/* translators: %d: number of leads captured. */
					esc_html__( 'All %d of these leads were captured by hand.', 'templatesell-gated-downloads' ),
					(int) $count
				);
				?>
				</strong>
				<?php esc_html_e( 'Pro auto-syncs every new lead to Mailchimp or Brevo and sends it to Zapier the moment it is captured — no exports, no copy-paste.', 'templatesell-gated-downloads' ); ?>
			</p>
			<a class="button button-primary tslgd-sync-cta" href="<?php echo esc_url( $trial ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Automate free for 7 days', 'templatesell-gated-downloads' ); ?></a>
			<button type="button" class="tslgd-sync-x" onclick="window.location.href='<?php echo esc_url( $dismiss ); ?>'" aria-label="<?php esc_attr_e( 'Dismiss', 'templatesell-gated-downloads' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Dismiss the sync nudge.
	 *
	 * @return void
	 */
	public function handle_sync_dismiss() {
		check_admin_referer( 'tslgd_sync_dismiss' );
		if ( current_user_can( 'manage_options' ) ) {
			update_option( self::OPT_SYNC, 1 );
		}
		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=tslgd-leads' ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Contextual Pro/trial upsell (rendered on the Leads page).
	 * --------------------------------------------------------------------- */

	/**
	 * Render the "automate your leads" upsell once leads pile up.
	 *
	 * @return void
	 */
	public function render_upsell() {
		if ( Upgrade_Page::is_pro() ) {
			return;
		}
		$count = $this->lead_count();
		if ( $count < self::UPSELL_THRESHOLD ) {
			return;
		}
		$dismissed_at = (int) get_option( self::OPT_UPSELL, 0 );
		if ( $dismissed_at > 0 && ! $this->milestone_reached( $dismissed_at, $count ) ) {
			return;
		}

		$trial   = Upgrade_Page::trial_url();
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_upsell_dismiss' ), 'tslgd_upsell_dismiss' );
		?>
		<div class="tslgd-card tslgd-upsell-trigger">
			<button type="button" class="tslgd-upsell-x" onclick="window.location.href='<?php echo esc_url( $dismiss ); ?>'" aria-label="<?php esc_attr_e( 'Dismiss', 'templatesell-gated-downloads' ); ?>">&times;</button>
			<h2>
				<?php
				printf(
					/* translators: %d: number of leads captured. */
					esc_html__( "You've captured %d leads — now put them to work.", 'templatesell-gated-downloads' ),
					(int) $this->lead_count()
				);
				?>
			</h2>
			<p><?php esc_html_e( 'Gated Downloads Pro sends your own branded download emails, syncs every lead to Mailchimp or Brevo, connects to Zapier and Make via webhooks, and protects your downloads — automatically. Try it free for 7 days, no credit card.', 'templatesell-gated-downloads' ); ?></p>
			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $trial ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Start 7-day free trial', 'templatesell-gated-downloads' ); ?></a>
				<a class="button button-hero" href="<?php echo esc_url( Upgrade_Page::page_url() ); ?>"><?php esc_html_e( 'See what Pro adds', 'templatesell-gated-downloads' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Has the lead count crossed a milestone the user hasn't dismissed yet?
	 *
	 * True when some milestone lies above the count stored at the last
	 * dismissal and the site has now reached it.
	 *
	 * @since 1.1.2
	 * @param int $dismissed_at Lead count when the card was last dismissed.
	 * @param int $count        Current lead count.
	 * @return bool
	 */
	private function milestone_reached( $dismissed_at, $count ) {
		foreach ( self::UPSELL_MILESTONES as $milestone ) {
			if ( $milestone > $dismissed_at && $count >= $milestone ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Dismiss the upsell.
	 *
	 * @return void
	 */
	public function handle_upsell_dismiss() {
		check_admin_referer( 'tslgd_upsell_dismiss' );
		if ( current_user_can( 'manage_options' ) ) {
			// Store the lead count at dismissal so the card can earn one more
			// showing at each later milestone (see UPSELL_MILESTONES).
			update_option( self::OPT_UPSELL, max( 1, $this->lead_count() ) );
		}
		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=tslgd-leads' ) );
		exit;
	}
}
