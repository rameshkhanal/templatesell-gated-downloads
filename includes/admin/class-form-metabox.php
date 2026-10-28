<?php
/**
 * Per-form configuration meta box on the gated-file edit screen.
 *
 * Renders three cards — Form Fields (sortable), Messages & Behavior, and
 * Notifications — that write a single `_tslgd_form` override bundle. Every
 * card is guarded by an "override" toggle; when off, the form inherits the
 * global Settings, so existing forms are unaffected.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Core\Field_Catalog;
use TemplateSell\GatedDownloads\Core\Form_Config;
use TemplateSell\GatedDownloads\Admin\Pro\Upgrade_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Form_Metabox
 */
class Form_Metabox {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'tslgd_form_config',
			__( 'Form Fields & Behavior', 'templatesell-gated-downloads' ),
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
		wp_nonce_field( 'tslgd_save_form', 'tslgd_form_nonce' );

		$meta = get_post_meta( $post->ID, Form_Config::META, true );
		$meta = is_array( $meta ) ? $meta : array();

		$override_fields   = ! empty( $meta['override_fields'] );
		$override_messages = ! empty( $meta['override_messages'] );
		$override_notify   = ! empty( $meta['override_notify'] );
		$rows              = $this->ordered_rows( $meta );
		?>
		<div class="tslgd-card tslgd-formcfg">

			<div class="tslgd-formcfg-card">
				<label class="tslgd-formcfg-toggle">
					<input type="checkbox" name="tslgd_form[override_fields]" value="1" <?php checked( $override_fields ); ?> data-toggle="fields" />
					<strong><?php esc_html_e( 'Customize the fields for this form', 'templatesell-gated-downloads' ); ?></strong>
				</label>
				<p class="description"><?php esc_html_e( 'When off, this form uses the global field settings. Turn on to choose, reorder, and require fields for this form only. Drag to reorder.', 'templatesell-gated-downloads' ); ?></p>

				<ul class="tslgd-fb-list" data-section="fields" <?php echo $override_fields ? '' : 'data-disabled="1"'; ?>>
					<?php
					foreach ( $rows as $row ) {
						$this->render_field_row( $row['key'], $row['def'], $row['enabled'], $row['required'] );
					}
					/**
					 * Append extra field-builder rows (Pro custom fields hook here).
					 *
					 * @since 1.1.0
					 * @param \WP_Post $post Current post.
					 * @param array    $meta Per-form meta bundle.
					 */
					do_action( 'tslgd_form_fields_rows', $post, $meta );

					// Show what Pro unlocks, right where the need is felt. Hidden
					// once Pro is active (it renders its real field-type rows above).
					if ( ! Upgrade_Page::is_pro() ) {
						$this->render_locked_field_teasers();
					}
					?>
				</ul>
			</div>

			<div class="tslgd-formcfg-card">
				<label class="tslgd-formcfg-toggle">
					<input type="checkbox" name="tslgd_form[override_messages]" value="1" <?php checked( $override_messages ); ?> data-toggle="messages" />
					<strong><?php esc_html_e( 'Customize messages & behavior for this form', 'templatesell-gated-downloads' ); ?></strong>
				</label>
				<p class="description"><?php esc_html_e( 'Leave a field blank to inherit the global default.', 'templatesell-gated-downloads' ); ?></p>

				<div class="tslgd-formcfg-fields" data-section="messages" <?php echo $override_messages ? '' : 'data-disabled="1"'; ?>>
					<p>
						<label class="tslgd-field-label" for="tslgd_form_success"><?php esc_html_e( 'Success message', 'templatesell-gated-downloads' ); ?></label>
						<input type="text" id="tslgd_form_success" class="widefat" name="tslgd_form[success_message]" value="<?php echo esc_attr( isset( $meta['success_message'] ) ? $meta['success_message'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Thank you! Your download will begin shortly.', 'templatesell-gated-downloads' ); ?>" />
					</p>
					<p>
						<label class="tslgd-field-label" for="tslgd_form_submit"><?php esc_html_e( 'Submit button text', 'templatesell-gated-downloads' ); ?></label>
						<input type="text" id="tslgd_form_submit" class="regular-text" name="tslgd_form[submit_text]" value="<?php echo esc_attr( isset( $meta['submit_text'] ) ? $meta['submit_text'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Download', 'templatesell-gated-downloads' ); ?>" />
					</p>
					<p>
						<label class="tslgd-field-label" for="tslgd_form_redirect"><?php esc_html_e( 'Redirect URL after submit (optional)', 'templatesell-gated-downloads' ); ?></label>
						<input type="url" id="tslgd_form_redirect" class="widefat" name="tslgd_form[redirect_url]" value="<?php echo esc_attr( isset( $meta['redirect_url'] ) ? $meta['redirect_url'] : '' ); ?>" placeholder="https://example.com/thank-you/" />
						<span class="description"><?php esc_html_e( 'If set, the visitor is sent here after submitting instead of the file download starting.', 'templatesell-gated-downloads' ); ?></span>
					</p>
				</div>
			</div>

			<div class="tslgd-formcfg-card">
				<label class="tslgd-formcfg-toggle">
					<input type="checkbox" name="tslgd_form[override_notify]" value="1" <?php checked( $override_notify ); ?> data-toggle="notify" />
					<strong><?php esc_html_e( 'Customize the email notification for this form', 'templatesell-gated-downloads' ); ?></strong>
				</label>

				<div class="tslgd-formcfg-fields" data-section="notify" <?php echo $override_notify ? '' : 'data-disabled="1"'; ?>>
					<p>
						<label>
							<input type="checkbox" name="tslgd_form[notify_enabled]" value="1" <?php checked( ! empty( $meta['notify_enabled'] ) ); ?> />
							<?php esc_html_e( 'Email me when this form captures a lead', 'templatesell-gated-downloads' ); ?>
						</label>
					</p>
					<p>
						<label class="tslgd-field-label" for="tslgd_form_notify_email"><?php esc_html_e( 'Send notifications to', 'templatesell-gated-downloads' ); ?></label>
						<input type="email" id="tslgd_form_notify_email" class="regular-text" name="tslgd_form[notify_email]" value="<?php echo esc_attr( isset( $meta['notify_email'] ) ? $meta['notify_email'] : '' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
					</p>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Build the ordered field rows to render: saved order first, then any
	 * catalog fields not yet stored (so new fields always appear).
	 *
	 * @param array $meta Per-form meta.
	 * @return array List of [ key, def, enabled, required ].
	 */
	private function ordered_rows( $meta ) {
		$catalog = Field_Catalog::all();
		$rows    = array();
		$seen    = array();

		if ( ! empty( $meta['fields'] ) && is_array( $meta['fields'] ) ) {
			foreach ( $meta['fields'] as $row ) {
				$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
				if ( '' === $key || ! isset( $catalog[ $key ] ) || isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$rows[]       = array(
					'key'      => $key,
					'def'      => $catalog[ $key ],
					'enabled'  => ( 'email' === $key ) ? true : ! empty( $row['enabled'] ),
					'required' => ! empty( $row['required'] ),
				);
			}
		}

		// Append catalog fields not present in the saved order. For a brand-new
		// form (no saved order at all) default to Name/Email/Phone enabled.
		$has_saved = ! empty( $seen );
		foreach ( $catalog as $key => $def ) {
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$default_on = in_array( $key, array( 'name', 'email', 'phone' ), true );
			$rows[]     = array(
				'key'      => $key,
				'def'      => $def,
				'enabled'  => ( 'email' === $key ) ? true : ( $has_saved ? false : $default_on ),
				'required' => ( ! $has_saved && in_array( $key, array( 'name', 'email' ), true ) ),
			);
		}

		return $rows;
	}

	/**
	 * Render one sortable field row.
	 *
	 * @param string $key      Field slug.
	 * @param array  $def      Catalog definition.
	 * @param bool   $enabled  Whether enabled.
	 * @param bool   $required Whether required.
	 * @return void
	 */
	private function render_field_row( $key, $def, $enabled, $required ) {
		$is_email = ( 'email' === $key );
		$label    = isset( $def['label'] ) ? $def['label'] : $key;
		?>
		<li class="tslgd-fb-row" data-key="<?php echo esc_attr( $key ); ?>">
			<span class="tslgd-fb-handle dashicons dashicons-menu" aria-hidden="true"></span>
			<input type="hidden" name="tslgd_form[order][]" value="<?php echo esc_attr( $key ); ?>" />
			<label class="tslgd-fb-enable">
				<input type="checkbox" name="tslgd_form[enabled][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $enabled ); ?> <?php disabled( $is_email ); ?> />
				<?php if ( $is_email ) : ?>
					<input type="hidden" name="tslgd_form[enabled][email]" value="1" />
				<?php endif; ?>
				<span class="tslgd-fb-name"><?php echo esc_html( $label ); ?></span>
				<?php if ( $is_email ) : ?>
					<em class="tslgd-fb-note">(<?php esc_html_e( 'always on', 'templatesell-gated-downloads' ); ?>)</em>
				<?php endif; ?>
			</label>
			<label class="tslgd-fb-req">
				<input type="checkbox" name="tslgd_form[required][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $required ); ?> />
				<?php esc_html_e( 'Required', 'templatesell-gated-downloads' ); ?>
			</label>
		</li>
		<?php
	}

	/**
	 * Render disabled "Pro field type" teaser rows below the standard fields.
	 *
	 * These are point-of-need upsells: the user is configuring form fields and
	 * sees exactly which extra input types Pro adds, with a one-click free trial.
	 *
	 * @return void
	 */
	private function render_locked_field_teasers() {
		$teasers = array(
			array( 'plus-alt', __( 'Unlimited custom fields', 'templatesell-gated-downloads' ) ),
			array( 'menu', __( 'Dropdown / select menus', 'templatesell-gated-downloads' ) ),
			array( 'testimonial', __( 'Long-text message boxes', 'templatesell-gated-downloads' ) ),
		);
		$trial = Upgrade_Page::trial_url();
		?>
		<li class="tslgd-fb-row tslgd-fb-locked-head" aria-hidden="true">
			<span class="tslgd-fb-locked-label"><?php esc_html_e( 'Add more with Pro', 'templatesell-gated-downloads' ); ?></span>
		</li>
		<?php foreach ( $teasers as $teaser ) : ?>
			<li class="tslgd-fb-row tslgd-fb-locked">
				<span class="tslgd-fb-handle dashicons dashicons-<?php echo esc_attr( $teaser[0] ); ?>" aria-hidden="true"></span>
				<span class="tslgd-fb-enable">
					<span class="dashicons dashicons-lock tslgd-fb-lockic" aria-hidden="true"></span>
					<span class="tslgd-fb-name"><?php echo esc_html( $teaser[1] ); ?></span>
				</span>
				<span class="tslgd-fb-probadge">Pro</span>
			</li>
		<?php endforeach; ?>
		<li class="tslgd-fb-row tslgd-fb-locked-cta">
			<a href="<?php echo esc_url( $trial ); ?>" target="_blank" rel="noopener">
				<span class="dashicons dashicons-unlock" aria-hidden="true"></span>
				<?php esc_html_e( 'Unlock custom field types — free for 7 days', 'templatesell-gated-downloads' ); ?>
			</a>
		</li>
		<?php
	}

	/**
	 * Persist the per-form override bundle.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['tslgd_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tslgd_form_nonce'] ) ), 'tslgd_save_form' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Nonce is verified above; every value is sanitized individually below
		// (sanitize_text_field / esc_url_raw / sanitize_email / sanitize_key).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['tslgd_form'] ) ? (array) wp_unslash( $_POST['tslgd_form'] ) : array();

		$clean = array(
			'override_fields'   => ! empty( $raw['override_fields'] ),
			'fields'            => $this->sanitize_fields( $raw ),
			'override_messages' => ! empty( $raw['override_messages'] ),
			'success_message'   => isset( $raw['success_message'] ) ? sanitize_text_field( $raw['success_message'] ) : '',
			'submit_text'       => isset( $raw['submit_text'] ) ? sanitize_text_field( $raw['submit_text'] ) : '',
			'redirect_url'      => isset( $raw['redirect_url'] ) ? esc_url_raw( $raw['redirect_url'] ) : '',
			'override_notify'   => ! empty( $raw['override_notify'] ),
			'notify_enabled'    => ! empty( $raw['notify_enabled'] ),
			'notify_email'      => isset( $raw['notify_email'] ) ? sanitize_email( $raw['notify_email'] ) : '',
		);

		update_post_meta( $post_id, Form_Config::META, $clean );
	}

	/**
	 * Sanitize the posted field builder into an ordered [key,enabled,required] list.
	 *
	 * Order comes from the `order[]` array (preserves DOM/drag order); the
	 * enabled/required flags are keyed by slug to avoid checkbox index gaps.
	 *
	 * @param array $raw Raw tslgd_form input.
	 * @return array
	 */
	private function sanitize_fields( $raw ) {
		$catalog  = Field_Catalog::all();
		$order    = isset( $raw['order'] ) ? (array) $raw['order'] : array();
		$enabled  = isset( $raw['enabled'] ) ? (array) $raw['enabled'] : array();
		$required = isset( $raw['required'] ) ? (array) $raw['required'] : array();

		$fields = array();
		$seen   = array();
		foreach ( $order as $key ) {
			$key = sanitize_key( $key );
			if ( '' === $key || ! isset( $catalog[ $key ] ) || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$fields[]     = array(
				'key'      => $key,
				'enabled'  => ( 'email' === $key ) ? 1 : ( ! empty( $enabled[ $key ] ) ? 1 : 0 ),
				'required' => ! empty( $required[ $key ] ) ? 1 : 0,
			);
		}

		return $fields;
	}
}
