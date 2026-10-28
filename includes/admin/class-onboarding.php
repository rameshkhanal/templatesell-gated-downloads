<?php
/**
 * Onboarding: welcome notice, one-click demo content, and a setup checklist.
 *
 * @package TemplateSell\GatedDownloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Core\Lead_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Onboarding
 */
class Onboarding {

	const OPT_DEMO    = 'tslgd_demo_content';
	const OPT_WELCOME = 'tslgd_welcome_dismissed';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_notices', array( $this, 'welcome_notice' ) );
		add_action( 'admin_post_tslgd_demo_create', array( $this, 'handle_demo_create' ) );
		add_action( 'admin_post_tslgd_demo_remove', array( $this, 'handle_demo_remove' ) );
		add_action( 'admin_post_tslgd_welcome_dismiss', array( $this, 'handle_welcome_dismiss' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Progress model.
	 * --------------------------------------------------------------------- */

	/**
	 * The three onboarding steps and whether each is complete.
	 *
	 * @return array<int,array{label:string,done:bool,url:string,cta:string}>
	 */
	public function steps() {
		$has_file  = $this->has_gated_file();
		$has_embed = $this->has_shortcode_on_site();
		$has_lead  = Lead_Repository::count() > 0;

		return array(
			array(
				'label' => __( 'Create a gated file', 'templatesell-gated-downloads' ),
				'done'  => $has_file,
				'url'   => admin_url( 'post-new.php?post_type=' . Post_Type::POST_TYPE ),
				'cta'   => __( 'Add a gated file', 'templatesell-gated-downloads' ),
			),
			array(
				'label' => __( 'Add it to a page', 'templatesell-gated-downloads' ),
				'done'  => $has_embed,
				'url'   => admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE ),
				'cta'   => __( 'Copy the shortcode', 'templatesell-gated-downloads' ),
			),
			array(
				'label' => __( 'Capture your first lead', 'templatesell-gated-downloads' ),
				'done'  => $has_lead,
				'url'   => admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=tslgd-leads' ),
				'cta'   => __( 'View leads', 'templatesell-gated-downloads' ),
			),
		);
	}

	/**
	 * Whether all onboarding steps are complete.
	 *
	 * @return bool
	 */
	public function is_complete() {
		foreach ( $this->steps() as $step ) {
			if ( empty( $step['done'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether at least one gated file exists (any status except trash).
	 *
	 * @return bool
	 */
	private function has_gated_file() {
		$counts = wp_count_posts( Post_Type::POST_TYPE );
		if ( ! $counts ) {
			return false;
		}
		$total = 0;
		foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $status ) {
			$total += isset( $counts->$status ) ? (int) $counts->$status : 0;
		}
		return $total > 0;
	}

	/**
	 * Whether the shortcode appears in any published post/page (cached).
	 *
	 * @return bool
	 */
	private function has_shortcode_on_site() {
		$demo = get_option( self::OPT_DEMO, array() );
		if ( is_array( $demo ) && ! empty( $demo['page'] ) && 'publish' === get_post_status( (int) $demo['page'] ) ) {
			return true;
		}

		$cached = get_transient( 'tslgd_has_shortcode' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		global $wpdb;
		// Lightweight existence check (all literals, no user input), cached for 5 minutes below.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$found = (int) $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ( post_content LIKE '%[tslgd_download%' OR post_content LIKE '%[tslgd %' OR post_content LIKE '%wp:tslgd/%' ) LIMIT 1"
		);
		set_transient( 'tslgd_has_shortcode', $found ? 1 : 0, 5 * MINUTE_IN_SECONDS );
		return $found > 0;
	}

	/* --------------------------------------------------------------------- *
	 * Welcome notice.
	 * --------------------------------------------------------------------- */

	/**
	 * One-time welcome notice on plugin screens.
	 *
	 * @return void
	 */
	public function welcome_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( self::OPT_WELCOME ) || $this->is_complete() ) {
			return;
		}
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		$create = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_demo_create' ), 'tslgd_demo_create' );
		$hide   = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_welcome_dismiss' ), 'tslgd_welcome_dismiss' );
		?>
		<div class="notice notice-info tslgd-welcome">
			<h3><?php esc_html_e( 'Welcome to Gated Downloads 👋', 'templatesell-gated-downloads' ); ?></h3>
			<p><?php esc_html_e( 'Want to see it work right now? We can create a sample gated file and a demo page for you — one click, and you can remove it any time.', 'templatesell-gated-downloads' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $create ); ?>"><?php esc_html_e( 'Create demo content', 'templatesell-gated-downloads' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Type::POST_TYPE ) ); ?>"><?php esc_html_e( 'Start from scratch', 'templatesell-gated-downloads' ); ?></a>
				<a class="tslgd-welcome-hide" href="<?php echo esc_url( $hide ); ?>"><?php esc_html_e( 'Dismiss', 'templatesell-gated-downloads' ); ?></a>
			</p>
		</div>
		<?php
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
	 * Setup checklist (rendered on the Leads page).
	 * --------------------------------------------------------------------- */

	/**
	 * Render the setup checklist card. Hides itself once complete.
	 *
	 * @return void
	 */
	public function render_checklist() {
		if ( $this->is_complete() ) {
			return;
		}
		$steps = $this->steps();
		$done  = 0;
		foreach ( $steps as $s ) {
			$done += $s['done'] ? 1 : 0;
		}
		$pct   = (int) round( ( $done / max( 1, count( $steps ) ) ) * 100 );
		$demo  = get_option( self::OPT_DEMO, array() );
		$create = wp_nonce_url( admin_url( 'admin-post.php?action=tslgd_demo_create' ), 'tslgd_demo_create' );
		?>
		<div class="tslgd-card tslgd-checklist">
			<div class="tslgd-checklist-head">
				<h2><?php esc_html_e( 'Finish setting up', 'templatesell-gated-downloads' ); ?></h2>
				<span class="tslgd-checklist-count">
					<?php
					printf(
						/* translators: 1: completed steps, 2: total steps. */
						esc_html__( '%1$d of %2$d done', 'templatesell-gated-downloads' ),
						(int) $done,
						(int) count( $steps )
					);
					?>
				</span>
			</div>
			<div class="tslgd-progress"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></div>
			<ul class="tslgd-checklist-items">
				<?php foreach ( $steps as $step ) : ?>
					<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
						<span class="tslgd-check" aria-hidden="true"><?php echo $step['done'] ? '✓' : ''; ?></span>
						<span class="tslgd-check-label"><?php echo esc_html( $step['label'] ); ?></span>
						<?php if ( ! $step['done'] ) : ?>
							<a class="button button-small" href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['cta'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( empty( $demo['file'] ) ) : ?>
				<p class="tslgd-checklist-demo">
					<?php esc_html_e( 'In a hurry?', 'templatesell-gated-downloads' ); ?>
					<a href="<?php echo esc_url( $create ); ?>"><?php esc_html_e( 'Create demo content', 'templatesell-gated-downloads' ); ?></a>
					<?php esc_html_e( '— a ready-made sample file and page so you can see it working instantly.', 'templatesell-gated-downloads' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Demo content actions.
	 * --------------------------------------------------------------------- */

	/**
	 * Create a sample gated file + demo page, then open the page.
	 *
	 * @return void
	 */
	public function handle_demo_create() {
		check_admin_referer( 'tslgd_demo_create' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'templatesell-gated-downloads' ) );
		}

		$existing = get_option( self::OPT_DEMO, array() );
		if ( is_array( $existing ) && ! empty( $existing['page'] ) && get_post( (int) $existing['page'] ) ) {
			wp_safe_redirect( get_permalink( (int) $existing['page'] ) );
			exit;
		}

		// 1) The gated file.
		$file_id = wp_insert_post(
			array(
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'Sample: 2026 Pricing Guide', 'templatesell-gated-downloads' ),
			),
			true
		);
		if ( is_wp_error( $file_id ) || ! $file_id ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=tslgd-leads' ) );
			exit;
		}

		update_post_meta( $file_id, '_tslgd_source', 'url' );
		update_post_meta( $file_id, '_tslgd_file_url', esc_url_raw( plugins_url( 'assets/sample/gated-downloads-sample.pdf', TSLGD_FILE ) ) );
		update_post_meta( $file_id, '_tslgd_attachment_id', 0 );
		update_post_meta( $file_id, '_tslgd_button_label', __( 'Download the 2026 Pricing Guide', 'templatesell-gated-downloads' ) );

		// 2) A demo page that embeds it.
		$content = sprintf(
			"<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->[tslgd_download id=\"%d\"]<!-- /wp:shortcode -->",
			esc_html__( 'Click the button below to download our sample guide. Enter your details and the file unlocks instantly — that captured detail becomes a lead in your dashboard.', 'templatesell-gated-downloads' ),
			(int) $file_id
		);
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Gated Download (Sample)', 'templatesell-gated-downloads' ),
				'post_content' => $content,
			),
			true
		);

		$demo = array( 'file' => (int) $file_id );
		if ( ! is_wp_error( $page_id ) && $page_id ) {
			$demo['page'] = (int) $page_id;
		}
		update_option( self::OPT_DEMO, $demo );
		delete_transient( 'tslgd_has_shortcode' );

		if ( ! empty( $demo['page'] ) ) {
			wp_safe_redirect( get_permalink( (int) $demo['page'] ) );
			exit;
		}
		wp_safe_redirect( get_edit_post_link( $file_id, 'url' ) );
		exit;
	}

	/**
	 * Trash the demo content.
	 *
	 * @return void
	 */
	public function handle_demo_remove() {
		check_admin_referer( 'tslgd_demo_remove' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'templatesell-gated-downloads' ) );
		}

		$demo = get_option( self::OPT_DEMO, array() );
		if ( is_array( $demo ) ) {
			foreach ( array( 'file', 'page' ) as $key ) {
				if ( ! empty( $demo[ $key ] ) ) {
					wp_trash_post( (int) $demo[ $key ] );
				}
			}
		}
		delete_option( self::OPT_DEMO );
		delete_transient( 'tslgd_has_shortcode' );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=tslgd-leads' ) );
		exit;
	}

	/**
	 * Dismiss the welcome notice.
	 *
	 * @return void
	 */
	public function handle_welcome_dismiss() {
		check_admin_referer( 'tslgd_welcome_dismiss' );
		if ( current_user_can( 'manage_options' ) ) {
			update_option( self::OPT_WELCOME, 1 );
		}
		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE . '&page=tslgd-leads' ) );
		exit;
	}
}
