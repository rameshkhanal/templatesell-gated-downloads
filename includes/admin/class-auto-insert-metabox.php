<?php
/**
 * Per-post override for the auto-inserted download button.
 *
 * Shown only on post types that automatic placement targets.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Frontend\Auto_Insert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Auto_Insert_Metabox
 */
class Auto_Insert_Metabox {

	/**
	 * Nonce action/field name.
	 */
	const NONCE = 'tslgd_auto_insert_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the metabox on targeted post types.
	 *
	 * @return void
	 */
	public function add_box() {
		$settings = Settings::get();
		if ( empty( $settings['auto_insert_enabled'] ) ) {
			return;
		}

		foreach ( (array) $settings['auto_insert_post_types'] as $post_type ) {
			add_meta_box(
				'tslgd_auto_insert',
				__( 'Gated Downloads', 'templatesell-gated-downloads' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the override control.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		$settings = Settings::get();
		$current  = (string) get_post_meta( $post->ID, Auto_Insert::META_KEY, true );
		$gates    = Settings::gate_choices();
		$default  = absint( $settings['auto_insert_gate_id'] );
		$default_label = isset( $gates[ $default ] ) ? $gates[ $default ] : __( '(none selected)', 'templatesell-gated-downloads' );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p>
			<label>
				<input type="radio" name="tslgd_auto_insert_mode" value="" <?php checked( '' === $current ); ?> />
				<?php
				printf(
					/* translators: %s: the site-wide default file title. */
					esc_html__( 'Use the default: %s', 'templatesell-gated-downloads' ),
					'<strong>' . esc_html( $default_label ) . '</strong>'
				);
				?>
			</label>
		</p>
		<p>
			<label>
				<input type="radio" name="tslgd_auto_insert_mode" value="off" <?php checked( 'off', $current ); ?> />
				<?php esc_html_e( 'Do not show a download button here', 'templatesell-gated-downloads' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="radio" name="tslgd_auto_insert_mode" value="custom" <?php checked( '' !== $current && 'off' !== $current ); ?> />
				<?php esc_html_e( 'Show a different file:', 'templatesell-gated-downloads' ); ?>
			</label>
			<select name="tslgd_auto_insert_gate" style="width:100%;margin-top:4px;">
				<option value="0"><?php esc_html_e( '— Select —', 'templatesell-gated-downloads' ); ?></option>
				<?php foreach ( $gates as $gate_id => $title ) : ?>
					<option value="<?php echo esc_attr( $gate_id ); ?>" <?php selected( absint( $current ), $gate_id ); ?>><?php echo esc_html( $title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Applies to the button placed automatically. Shortcodes and blocks in the content are unaffected.', 'templatesell-gated-downloads' ); ?></p>
		<?php
	}

	/**
	 * Persist the override.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		unset( $post );

		$mode = isset( $_POST['tslgd_auto_insert_mode'] ) ? sanitize_key( wp_unslash( $_POST['tslgd_auto_insert_mode'] ) ) : '';

		if ( 'off' === $mode ) {
			update_post_meta( $post_id, Auto_Insert::META_KEY, 'off' );
			return;
		}

		if ( 'custom' === $mode ) {
			$gate_id = isset( $_POST['tslgd_auto_insert_gate'] ) ? absint( wp_unslash( $_POST['tslgd_auto_insert_gate'] ) ) : 0;
			if ( $gate_id ) {
				update_post_meta( $post_id, Auto_Insert::META_KEY, (string) $gate_id );
				return;
			}
		}

		delete_post_meta( $post_id, Auto_Insert::META_KEY );
	}
}
