<?php
/**
 * Gated-file edit screen meta box: file source + button label.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Core\Stats_Repository;
use TemplateSell\GatedDownloads\Admin\Pro\Upgrade_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gate_Metabox
 */
class Gate_Metabox {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'add_shortcode_column' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'render_shortcode_column' ), 10, 2 );
	}

	/**
	 * Add a "Shortcode" column to the gated-files list table (before Date).
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_shortcode_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['tslgd_shortcode'] = __( 'Shortcode', 'templatesell-gated-downloads' );
				$new['tslgd_perf']      = __( 'Last 30 days', 'templatesell-gated-downloads' );
			}
			$new[ $key ] = $label;
		}
		// If there is no Date column, append at the end.
		if ( ! isset( $new['tslgd_shortcode'] ) ) {
			$new['tslgd_shortcode'] = __( 'Shortcode', 'templatesell-gated-downloads' );
			$new['tslgd_perf']      = __( 'Last 30 days', 'templatesell-gated-downloads' );
		}
		return $new;
	}

	/**
	 * Render the shortcode cell with a click-to-copy control.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_shortcode_column( $column, $post_id ) {
		if ( 'tslgd_shortcode' === $column ) {
			$shortcode = '[tslgd_download id="' . absint( $post_id ) . '"]';
			echo '<code class="tslgd-shortcode tslgd-copy" role="button" tabindex="0" data-clipboard="' . esc_attr( $shortcode ) . '" title="' . esc_attr__( 'Click to copy', 'templatesell-gated-downloads' ) . '">' . esc_html( $shortcode ) . '</code>';
			return;
		}

		if ( 'tslgd_perf' !== $column ) {
			return;
		}

		$all    = Stats_Repository::per_gate_totals( 30 );
		$totals = isset( $all[ $post_id ] ) ? $all[ $post_id ] : array(
			'views'     => 0,
			'opens'     => 0,
			'submits'   => 0,
			'downloads' => 0,
		);
		$conv   = Stats_Repository::conversion( $totals );

		printf(
			'<span class="tslgd-perf-leads">%s</span>',
			esc_html(
				sprintf(
					/* translators: %s: number of leads. */
					_n( '%s lead', '%s leads', $totals['submits'], 'templatesell-gated-downloads' ),
					number_format_i18n( $totals['submits'] )
				)
			)
		);
		if ( null !== $conv ) {
			printf(
				'<br /><span class="tslgd-perf-conv" title="%s">%s</span>',
				esc_attr(
					sprintf(
						/* translators: 1: views, 2: opens. */
						__( '%1$s views · %2$s form opens', 'templatesell-gated-downloads' ),
						number_format_i18n( $totals['views'] ),
						number_format_i18n( $totals['opens'] )
					)
				),
				esc_html(
					sprintf(
						/* translators: %s: conversion percentage. */
						__( '%s%% conversion', 'templatesell-gated-downloads' ),
						$conv
					)
				)
			);
		} else {
			echo '<br /><span class="tslgd-perf-conv tslgd-perf-none">' . esc_html__( 'no views yet', 'templatesell-gated-downloads' ) . '</span>';
		}

		// All-time deliveries.
		$downloads = Stats_Repository::download_count( $post_id );
		if ( $downloads > 0 ) {
			printf(
				'<br /><span class="tslgd-perf-dl">%s</span>',
				esc_html(
					sprintf(
						/* translators: %s: number of downloads. */
						_n( '%s download all time', '%s downloads all time', $downloads, 'templatesell-gated-downloads' ),
						number_format_i18n( $downloads )
					)
				)
			);
		}
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'tslgd_file_settings',
			__( 'Download File & Button', 'templatesell-gated-downloads' ),
			array( $this, 'render' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		wp_nonce_field( 'tslgd_save_gate', 'tslgd_gate_nonce' );

		$source     = get_post_meta( $post->ID, '_tslgd_source', true );
		$source     = in_array( $source, array( 'upload', 'url' ), true ) ? $source : 'url';
		$file_url   = get_post_meta( $post->ID, '_tslgd_file_url', true );
		$attach_id  = (int) get_post_meta( $post->ID, '_tslgd_attachment_id', true );
		$btn_label  = get_post_meta( $post->ID, '_tslgd_button_label', true );
		?>
		<div class="tslgd-card tslgd-metabox">
			<div class="tslgd-field-row">
				<label class="tslgd-field-label"><?php esc_html_e( 'File Source', 'templatesell-gated-downloads' ); ?></label>
				<div class="tslgd-source-toggle">
					<label><input type="radio" name="tslgd_source" value="url" <?php checked( $source, 'url' ); ?> /> <?php esc_html_e( 'External URL', 'templatesell-gated-downloads' ); ?></label>
					<label><input type="radio" name="tslgd_source" value="upload" <?php checked( $source, 'upload' ); ?> /> <?php esc_html_e( 'Media Library', 'templatesell-gated-downloads' ); ?></label>
				</div>
			</div>

			<div class="tslgd-field-row tslgd-source-url" <?php echo 'url' === $source ? '' : 'style="display:none"'; ?>>
				<label class="tslgd-field-label" for="tslgd_file_url"><?php esc_html_e( 'File URL', 'templatesell-gated-downloads' ); ?></label>
				<input type="url" id="tslgd_file_url" name="tslgd_file_url" class="widefat" value="<?php echo esc_attr( $file_url ); ?>" placeholder="https://example.com/file.pdf" />
			</div>

			<div class="tslgd-field-row tslgd-source-upload" <?php echo 'upload' === $source ? '' : 'style="display:none"'; ?>>
				<label class="tslgd-field-label"><?php esc_html_e( 'Uploaded File', 'templatesell-gated-downloads' ); ?></label>
				<input type="hidden" id="tslgd_attachment_id" name="tslgd_attachment_id" value="<?php echo esc_attr( $attach_id ); ?>" />
				<p class="tslgd-file-preview" id="tslgd_file_preview">
					<?php
					if ( $attach_id ) {
						echo esc_html( wp_basename( (string) wp_get_attachment_url( $attach_id ) ) );
					} else {
						esc_html_e( 'No file selected.', 'templatesell-gated-downloads' );
					}
					?>
				</p>
				<button type="button" class="button" id="tslgd_select_file"><?php esc_html_e( 'Select / Upload File', 'templatesell-gated-downloads' ); ?></button>
				<button type="button" class="button-link tslgd-remove-file" id="tslgd_remove_file" <?php echo $attach_id ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'templatesell-gated-downloads' ); ?></button>
			</div>

			<p class="description tslgd-file-hint">
				<?php esc_html_e( 'File is optional. Leave it empty to use this as a simple email opt-in / subscribe form — the success message (or redirect) shows after submit.', 'templatesell-gated-downloads' ); ?>
			</p>

			<?php $this->render_pro_tips(); ?>

			<div class="tslgd-field-row">
				<label class="tslgd-field-label" for="tslgd_button_label"><?php esc_html_e( 'Button Label', 'templatesell-gated-downloads' ); ?></label>
				<input type="text" id="tslgd_button_label" name="tslgd_button_label" class="regular-text" value="<?php echo esc_attr( $btn_label ); ?>" placeholder="<?php esc_attr_e( 'Download', 'templatesell-gated-downloads' ); ?>" />
			</div>

			<?php if ( $post->ID && 'auto-draft' !== $post->post_status ) : ?>
				<div class="tslgd-field-row tslgd-shortcode-row">
					<label class="tslgd-field-label"><?php esc_html_e( 'Shortcode', 'templatesell-gated-downloads' ); ?></label>
					<code class="tslgd-shortcode tslgd-copy" role="button" tabindex="0" data-clipboard="[tslgd_download id=&quot;<?php echo esc_attr( $post->ID ); ?>&quot;]" title="<?php esc_attr_e( 'Click to copy', 'templatesell-gated-downloads' ); ?>">[tslgd_download id="<?php echo esc_attr( $post->ID ); ?>"]</code>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Pro tips under the file field. Shown only while Pro is inactive,
	 * on this plugin's own edit screen.
	 *
	 * @since 1.1.2
	 * @return void
	 */
	private function render_pro_tips() {
		if ( Upgrade_Page::is_pro() ) {
			return;
		}
		$trial = Upgrade_Page::trial_url();
		?>
		<div class="tslgd-metabox-pro-tips">
			<p class="tslgd-pro-tip tslgd-pro-tip-protect">
				<span class="dashicons dashicons-unlock" aria-hidden="true"></span>
				<span>
					<strong><?php esc_html_e( 'This file link is shareable.', 'templatesell-gated-downloads' ); ?></strong>
					<?php esc_html_e( 'Anyone who receives it can pass it around and skip your form. Pro replaces it with expiring, signed download links.', 'templatesell-gated-downloads' ); ?>
				</span>
			</p>
			<p class="tslgd-pro-tip tslgd-pro-tip-subscribe">
				<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
				<span>
					<strong><?php esc_html_e( 'Building a subscriber list?', 'templatesell-gated-downloads' ); ?></strong>
					<?php esc_html_e( 'Pro syncs every subscriber straight to Mailchimp or Brevo the moment they sign up — no CSV exports.', 'templatesell-gated-downloads' ); ?>
					<a href="<?php echo esc_url( $trial ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Try Pro free for 7 days →', 'templatesell-gated-downloads' ); ?></a>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		// Nonce.
		if ( ! isset( $_POST['tslgd_gate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tslgd_gate_nonce'] ) ), 'tslgd_save_gate' ) ) {
			return;
		}

		// Autosave / revisions / capability.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$source = isset( $_POST['tslgd_source'] ) ? sanitize_key( wp_unslash( $_POST['tslgd_source'] ) ) : 'url';
		$source = in_array( $source, array( 'upload', 'url' ), true ) ? $source : 'url';
		update_post_meta( $post_id, '_tslgd_source', $source );

		if ( 'upload' === $source ) {
			$attach_id = isset( $_POST['tslgd_attachment_id'] ) ? absint( wp_unslash( $_POST['tslgd_attachment_id'] ) ) : 0;
			update_post_meta( $post_id, '_tslgd_attachment_id', $attach_id );
			$url = $attach_id ? wp_get_attachment_url( $attach_id ) : '';
			update_post_meta( $post_id, '_tslgd_file_url', esc_url_raw( (string) $url ) );
		} else {
			$url = isset( $_POST['tslgd_file_url'] ) ? esc_url_raw( wp_unslash( $_POST['tslgd_file_url'] ) ) : '';
			update_post_meta( $post_id, '_tslgd_file_url', $url );
			update_post_meta( $post_id, '_tslgd_attachment_id', 0 );
		}

		$label = isset( $_POST['tslgd_button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['tslgd_button_label'] ) ) : '';
		update_post_meta( $post_id, '_tslgd_button_label', $label );
	}
}
