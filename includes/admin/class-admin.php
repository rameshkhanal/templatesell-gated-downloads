<?php
/**
 * Admin controller: menus, screens, and CSV export.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Core\Lead_Repository;
use TemplateSell\GatedDownloads\Core\Field_Catalog;
use TemplateSell\GatedDownloads\Core\Stats_Repository;
use TemplateSell\GatedDownloads\Admin\Pro\Upgrade_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
class Admin {

	/**
	 * Leads list table instance.
	 *
	 * @var Leads_List_Table|null
	 */
	private $list_table = null;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Onboarding service.
	 *
	 * @var Onboarding
	 */
	private $onboarding;

	/**
	 * Growth prompts service.
	 *
	 * @var Growth_Prompts
	 */
	private $growth;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings   = new Settings();
		$this->onboarding = new Onboarding();
		$this->growth     = new Growth_Prompts();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_tslgd_export', array( $this, 'export_csv' ) );

		( new Gate_Metabox() )->register_hooks();
		( new Form_Metabox() )->register_hooks();
		( new Auto_Insert_Metabox() )->register_hooks();
		$this->settings->register_hooks();
		$this->onboarding->register_hooks();
		$this->growth->register_hooks();
		( new File_Actions() )->register_hooks();

		// Pro upsell (notice and upgrade page), hidden when Pro is active.
		( new Pro\Upgrade_Page() )->register_hooks();
		( new Pro\Pro_Notice() )->register_hooks();
		( new Automation_Teaser() )->register_hooks();
		( new Deactivate_Feedback() )->register_hooks();
	}

	/**
	 * Register the Leads + Settings submenus under the CPT menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$leads_hook = add_submenu_page(
			'edit.php?post_type=' . Post_Type::POST_TYPE,
			__( 'Leads', 'templatesell-gated-downloads' ),
			__( 'Leads', 'templatesell-gated-downloads' ),
			'manage_options',
			'tslgd-leads',
			array( $this, 'render_leads_page' )
		);

		// Instantiate the list table early so Screen Options (per-page) work.
		if ( $leads_hook ) {
			add_action( "load-{$leads_hook}", array( $this, 'init_list_table' ) );
		}

		add_submenu_page(
			'edit.php?post_type=' . Post_Type::POST_TYPE,
			__( 'Settings', 'templatesell-gated-downloads' ),
			__( 'Settings', 'templatesell-gated-downloads' ),
			'manage_options',
			'tslgd-settings',
			array( $this->settings, 'render' )
		);
	}

	/**
	 * Instantiate the list table on the leads screen.
	 *
	 * @return void
	 */
	public function init_list_table() {
		$this->list_table = new Leads_List_Table();
	}

	/**
	 * Render the leads page (old card-style UI).
	 *
	 * @return void
	 */
	public function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'templatesell-gated-downloads' ) );
		}

		$total = absint( Lead_Repository::count() );

		if ( null === $this->list_table ) {
			$this->init_list_table();
		}
		$this->list_table->prepare_items();

		/**
		 * Filter the CSV export URL (add-ons append their active filters so the
		 * export matches what is on screen).
		 *
		 * @since 1.3.0
		 * @param string $url Export URL, already nonced.
		 */
		$export_url = apply_filters(
			'tslgd_export_url',
			wp_nonce_url(
				admin_url( 'admin-post.php?action=tslgd_export' ),
				'tslgd_export',
				'tslgd_export_nonce'
			)
		);
		?>
		<div class="wrap tslgd-wrap">
			<h1 class="tslgd-page-title">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<?php esc_html_e( 'Leads', 'templatesell-gated-downloads' ); ?>
			</h1>

			<?php $this->onboarding->render_checklist(); ?>

			<?php if ( 0 === $total ) : ?>
				<?php $this->render_flow(); ?>
				<div class="tslgd-card">
					<div class="tslgd-empty">
						<h2 class="tslgd-empty-title"><?php esc_html_e( 'No leads captured yet', 'templatesell-gated-downloads' ); ?></h2>
						<p class="tslgd-empty-text"><?php esc_html_e( 'Create your first gated file and add it to any page. Every visitor who unlocks it to download will show up here.', 'templatesell-gated-downloads' ); ?></p>
						<p>
							<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Type::POST_TYPE ) ); ?>">
								<?php esc_html_e( 'Add your first gated file', 'templatesell-gated-downloads' ); ?>
							</a>
						</p>
					</div>
				</div>
			<?php else : ?>
				<?php $this->render_stats(); ?>
				<?php $this->growth->render_sync_nudge(); ?>
				<?php $this->growth->render_upsell(); ?>
				<div class="tslgd-card">
					<div class="tslgd-leads-head">
						<h2 class="tslgd-records-title">
							<span class="dashicons dashicons-groups" aria-hidden="true"></span>
							<?php esc_html_e( 'All leads', 'templatesell-gated-downloads' ); ?>
						</h2>
						<form method="get" class="tslgd-leads-search">
							<input type="hidden" name="post_type" value="<?php echo esc_attr( Post_Type::POST_TYPE ); ?>" />
							<input type="hidden" name="page" value="tslgd-leads" />
							<?php $this->list_table->search_box( __( 'Search leads', 'templatesell-gated-downloads' ), 'tslgd-lead' ); ?>
						</form>
						<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary tslgd-export-btn">
							<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
							<?php esc_html_e( 'Export CSV', 'templatesell-gated-downloads' ); ?>
						</a>
					</div>

					<form method="get">
						<input type="hidden" name="post_type" value="<?php echo esc_attr( Post_Type::POST_TYPE ); ?>" />
						<input type="hidden" name="page" value="tslgd-leads" />
						<?php $this->list_table->display(); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php
			/** This action documented in Settings::render(). */
			do_action( 'tslgd_admin_upsell' );
			?>
		</div>
		<?php
	}

	/**
	 * Render the leads-page stat tiles (total, last 7/30 days, top file).
	 *
	 * @return void
	 */
	private function render_stats() {
		$s = Lead_Repository::stats();
		?>
		<div class="tslgd-stats">
			<div class="tslgd-stat">
				<span class="tslgd-stat-n"><?php echo esc_html( number_format_i18n( $s['total'] ) ); ?></span>
				<span class="tslgd-stat-l"><?php esc_html_e( 'Total leads', 'templatesell-gated-downloads' ); ?></span>
			</div>
			<div class="tslgd-stat">
				<span class="tslgd-stat-n"><?php echo esc_html( number_format_i18n( $s['last7'] ) ); ?></span>
				<span class="tslgd-stat-l"><?php esc_html_e( 'Last 7 days', 'templatesell-gated-downloads' ); ?></span>
			</div>
			<div class="tslgd-stat">
				<span class="tslgd-stat-n"><?php echo esc_html( number_format_i18n( $s['last30'] ) ); ?></span>
				<span class="tslgd-stat-l"><?php esc_html_e( 'Last 30 days', 'templatesell-gated-downloads' ); ?></span>
			</div>
			<div class="tslgd-stat tslgd-stat-wide">
				<span class="tslgd-stat-n tslgd-stat-file"><?php echo esc_html( '' !== $s['top_file'] ? $s['top_file'] : '—' ); ?></span>
				<span class="tslgd-stat-l"><?php esc_html_e( 'Top file', 'templatesell-gated-downloads' ); ?></span>
			</div>
		</div>
		<?php
		$this->render_funnel_strip();
	}

	/**
	 * Views, opens and leads funnel for the last 30 days.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function render_funnel_strip() {
		$t = Stats_Repository::totals( 30 );
		if ( $t['views'] < 1 ) {
			return; // Nothing measured yet.
		}
		$conv = Stats_Repository::conversion( $t );
		?>
		<p class="tslgd-funnel-strip">
			<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
			<?php
			printf(
				/* translators: 1: views, 2: form opens, 3: leads, 4: conversion percentage. */
				esc_html__( 'Last 30 days: %1$s button views → %2$s form opens → %3$s leads (%4$s%% conversion).', 'templatesell-gated-downloads' ),
				esc_html( number_format_i18n( $t['views'] ) ),
				esc_html( number_format_i18n( $t['opens'] ) ),
				esc_html( number_format_i18n( $t['submits'] ) ),
				esc_html( (string) $conv )
			);

			if ( ! Upgrade_Page::is_pro() && $t['views'] >= 100 && ( $t['submits'] / $t['views'] ) < 0.03 ) {
				echo ' ';
				printf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( Upgrade_Page::trial_url() ),
					esc_html__( 'Converting under 3% — Pro’s custom fields and integrations can lift it. Try free for 7 days →', 'templatesell-gated-downloads' )
				);
			}
			?>
		</p>
		<?php
	}

	/**
	 * Render the four-step "how it works" flow used in the empty state.
	 *
	 * @return void
	 */
	private function render_flow() {
		$steps = array(
			array(
				__( 'Create a gated file', 'templatesell-gated-downloads' ),
				__( 'Add a file and attach your PDF or URL.', 'templatesell-gated-downloads' ),
				'<rect class="tslgd-fill" x="9" y="6" width="24" height="32" rx="3"/><path class="tslgd-stroke" d="M9 9a3 3 0 0 1 3-3h14l7 7v23a3 3 0 0 1-3 3H12a3 3 0 0 1-3-3z"/><path class="tslgd-stroke" d="M26 6v7h7"/><circle class="tslgd-accent" cx="31" cy="33" r="8"/><path d="M28 32v-2a3 3 0 0 1 6 0v2" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/><rect x="27" y="32" width="8" height="7" rx="1.5" fill="#fff"/>',
			),
			array(
				__( 'Drop it on a page', 'templatesell-gated-downloads' ),
				__( 'Paste the shortcode or add the block anywhere.', 'templatesell-gated-downloads' ),
				'<rect class="tslgd-fill" x="6" y="10" width="36" height="28" rx="4"/><rect class="tslgd-stroke" x="6" y="10" width="36" height="28" rx="4"/><path class="tslgd-stroke" d="M18 21l-5 5 5 5"/><path class="tslgd-stroke" d="M30 21l5 5-5 5"/><path class="tslgd-accent" d="M25.4 18.5l-3.2 11 1.9.5 3.2-11z"/>',
			),
			array(
				__( 'Visitor unlocks it', 'templatesell-gated-downloads' ),
				__( 'They enter their details to get the download.', 'templatesell-gated-downloads' ),
				'<rect class="tslgd-fill" x="8" y="7" width="32" height="34" rx="4"/><rect class="tslgd-stroke" x="8" y="7" width="32" height="34" rx="4"/><circle class="tslgd-stroke" cx="24" cy="18" r="5"/><path class="tslgd-stroke" d="M15 33c1.5-4 5-6 9-6s7.5 2 9 6"/><path class="tslgd-accent" d="M30 35l2.4 2.4L38 32l1.8 1.8-7.4 7.4L28 37z"/>',
			),
			array(
				__( 'Capture the lead', 'templatesell-gated-downloads' ),
				__( 'Every submission is saved here and exportable.', 'templatesell-gated-downloads' ),
				'<circle class="tslgd-fill" cx="24" cy="24" r="18"/><path class="tslgd-stroke" d="M14 24l7 7 13-14"/><circle class="tslgd-accent" cx="24" cy="24" r="2.5"/>',
			),
		);
		?>
		<div class="tslgd-flow">
			<?php
			$i = 0;
			foreach ( $steps as $step ) :
				++$i;
				?>
				<div class="tslgd-flow-step">
					<div class="tslgd-flow-art">
						<span class="tslgd-flow-num"><?php echo esc_html( number_format_i18n( $i ) ); ?></span>
						<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><?php echo $this->flow_svg( $step[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is sanitized with wp_kses() inside flow_svg(). ?></svg>
					</div>
					<p class="tslgd-flow-title"><?php echo esc_html( $step[0] ); ?></p>
					<p class="tslgd-flow-desc"><?php echo esc_html( $step[1] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Allow only the small, fixed set of SVG shapes/attributes used by the flow
	 * illustrations, so nothing dynamic is ever echoed into the page.
	 *
	 * @param string $svg Inner SVG markup (developer-defined constants above).
	 * @return string Sanitized SVG markup.
	 */
	private function flow_svg( $svg ) {
		$allowed = array(
			'rect'    => array(
				'class'  => true,
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'fill'   => true,
			),
			'circle'  => array(
				'class' => true,
				'cx'    => true,
				'cy'    => true,
				'r'     => true,
				'fill'  => true,
			),
			'path'    => array(
				'class'           => true,
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
		);
		return wp_kses( $svg, $allowed );
	}

	/**
	 * Stream all leads as a CSV download.
	 *
	 * @return void
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export leads.', 'templatesell-gated-downloads' ) );
		}

		check_admin_referer( 'tslgd_export', 'tslgd_export_nonce' );

		$rows = Lead_Repository::query( array( 'per_page' => -1 ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tslgd-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( 'php://output', 'w' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fputcsv(
			$out,
			array(
				__( 'ID', 'templatesell-gated-downloads' ),
				__( 'Date', 'templatesell-gated-downloads' ),
				__( 'Name', 'templatesell-gated-downloads' ),
				__( 'Email', 'templatesell-gated-downloads' ),
				__( 'Phone', 'templatesell-gated-downloads' ),
				__( 'Form', 'templatesell-gated-downloads' ),
				__( 'Category', 'templatesell-gated-downloads' ),
				__( 'File URL', 'templatesell-gated-downloads' ),
				__( 'Details', 'templatesell-gated-downloads' ),
				__( 'Consent', 'templatesell-gated-downloads' ),
				__( 'Source', 'templatesell-gated-downloads' ),
				__( 'Medium', 'templatesell-gated-downloads' ),
				__( 'Campaign', 'templatesell-gated-downloads' ),
				__( 'Referrer', 'templatesell-gated-downloads' ),
			)
		);

		foreach ( $rows as $r ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			fputcsv(
				$out,
				array(
					$this->csv_cell( $r['id'] ),
					$this->csv_cell( $r['created_at'] ),
					$this->csv_cell( $r['name'] ),
					$this->csv_cell( $r['email'] ),
					$this->csv_cell( $r['phone'] ),
					$this->csv_cell( $r['file_title'] ),
					$this->csv_cell( $r['file_category'] ),
					$this->csv_cell( $r['file_url'] ),
					$this->csv_cell( $this->extra_summary( $r ) ),
					! empty( $r['consent'] ) ? '1' : '0',
					$this->csv_cell( $this->attribution_value( $r, 'utm_source' ) ),
					$this->csv_cell( $this->attribution_value( $r, 'utm_medium' ) ),
					$this->csv_cell( $this->attribution_value( $r, 'utm_campaign' ) ),
					$this->csv_cell( $this->attribution_value( $r, 'referrer' ) ),
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out );
		exit;
	}

	/**
	 * Flatten a lead's `extra` JSON into one "Label: value; ..." cell for CSV export.
	 *
	 * @param array $r Lead row.
	 * @return string
	 */
	private function extra_summary( $r ) {
		if ( empty( $r['extra'] ) ) {
			return '';
		}
		$extra = json_decode( (string) $r['extra'], true );
		if ( ! is_array( $extra ) ) {
			return '';
		}

		$parts = array();

		if ( isset( $extra['fields'] ) && is_array( $extra['fields'] ) ) {
			foreach ( $extra['fields'] as $key => $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$def     = Field_Catalog::get( $key );
				$label   = $def ? $def['label'] : ucwords( str_replace( '_', ' ', (string) $key ) );
				$parts[] = $label . ': ' . $value;
			}
		}

		if ( isset( $extra['custom_fields'] ) && is_array( $extra['custom_fields'] ) ) {
			foreach ( $extra['custom_fields'] as $label => $value ) {
				if ( is_scalar( $value ) ) {
					$parts[] = $label . ': ' . $value;
				}
			}
		}

		return implode( '; ', $parts );
	}

	/**
	 * One attribution value from a lead's `extra` JSON, or ''.
	 *
	 * @since 1.3.0
	 * @param array  $r   Lead row.
	 * @param string $key Attribution key (utm_source, referrer, …).
	 * @return string
	 */
	private function attribution_value( $r, $key ) {
		if ( empty( $r['extra'] ) ) {
			return '';
		}
		$extra = json_decode( (string) $r['extra'], true );
		if ( ! is_array( $extra ) || empty( $extra['utm'][ $key ] ) ) {
			return '';
		}
		return (string) $extra['utm'][ $key ];
	}

	/**
	 * Neutralize CSV formula-injection: prefix a leading =,+,-,@ with a quote so
	 * spreadsheet apps treat the cell as text, not a formula.
	 *
	 * @param mixed $value Raw cell value.
	 * @return string
	 */
	private function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
