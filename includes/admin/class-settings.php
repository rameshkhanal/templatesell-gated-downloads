<?php
/**
 * Settings page (Settings API) and sanitization.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'tslgd_settings';

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	const GROUP = 'tslgd_settings_group';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the setting with a sanitize callback.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'fields'                   => array(
				'name'  => array(
					'enabled'  => true,
					'required' => true,
				),
				'email' => array(
					'enabled'  => true,
					'required' => true,
				),
				'phone' => array(
					'enabled'  => true,
					'required' => false,
				),
			),
			'consent_enabled'          => true,
			'consent_text'             => __( 'I agree to be contacted about this download.', 'templatesell-gated-downloads' ),
			'success_message'          => __( 'Thank you! Your download will begin shortly.', 'templatesell-gated-downloads' ),
			'button_label'             => __( 'Download', 'templatesell-gated-downloads' ),
			'primary_color'            => '#2563eb',
			'header_color'             => '#111827',
			'notify_admin'             => true,
			'notify_email'             => get_option( 'admin_email' ),
			'lead_email_enabled'       => true,
			'delivery_mode'            => 'download',
			'delete_data_on_uninstall' => false,
			'auto_insert_enabled'      => false,
			'auto_insert_gate_id'      => 0,
			'auto_insert_position'     => 'after',
			'auto_insert_post_types'   => array( 'post' ),
			'auto_insert_categories'   => array(),
			'auto_insert_exclude_ids'  => '',
			'auto_insert_heading'      => '',
			'show_download_count'      => false,
		);

		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$clean['fields'] = array();
		foreach ( array( 'name', 'email', 'phone' ) as $field ) {
			$clean['fields'][ $field ] = array(
				'enabled'  => ! empty( $input['fields'][ $field ]['enabled'] ),
				'required' => ! empty( $input['fields'][ $field ]['required'] ),
			);
		}
		// Email is always captured, keep it enabled.
		$clean['fields']['email']['enabled'] = true;

		$clean['consent_enabled']          = ! empty( $input['consent_enabled'] );
		$clean['consent_text']             = isset( $input['consent_text'] ) ? sanitize_text_field( $input['consent_text'] ) : '';
		$clean['success_message']          = isset( $input['success_message'] ) ? sanitize_text_field( $input['success_message'] ) : '';
		$clean['button_label']             = isset( $input['button_label'] ) ? sanitize_text_field( $input['button_label'] ) : __( 'Download', 'templatesell-gated-downloads' );

		$primary                = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '';
		$clean['primary_color'] = $primary ? $primary : '#2563eb';
		$header                 = isset( $input['header_color'] ) ? sanitize_hex_color( $input['header_color'] ) : '';
		$clean['header_color']  = $header ? $header : '#111827';

		$clean['notify_admin']             = ! empty( $input['notify_admin'] );
		$clean['lead_email_enabled']       = ! empty( $input['lead_email_enabled'] );
		$clean['notify_email']             = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : get_option( 'admin_email' );
		$clean['delivery_mode']            = ( isset( $input['delivery_mode'] ) && 'new_tab' === $input['delivery_mode'] ) ? 'new_tab' : 'download';
		$clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		$clean['auto_insert_enabled']     = ! empty( $input['auto_insert_enabled'] );
		$clean['auto_insert_gate_id']     = isset( $input['auto_insert_gate_id'] ) ? absint( $input['auto_insert_gate_id'] ) : 0;
		$clean['auto_insert_position']    = ( isset( $input['auto_insert_position'] ) && 'before' === $input['auto_insert_position'] ) ? 'before' : 'after';
		$clean['auto_insert_post_types']  = isset( $input['auto_insert_post_types'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $input['auto_insert_post_types'] ), array_keys( self::injectable_post_types() ) ) ) : array();
		$clean['auto_insert_categories']  = isset( $input['auto_insert_categories'] ) ? array_values( array_filter( array_map( 'absint', (array) $input['auto_insert_categories'] ) ) ) : array();
		$clean['auto_insert_exclude_ids'] = isset( $input['auto_insert_exclude_ids'] ) ? implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $input['auto_insert_exclude_ids'] ) ) ) ) : '';
		$clean['auto_insert_heading']     = isset( $input['auto_insert_heading'] ) ? sanitize_text_field( $input['auto_insert_heading'] ) : '';
		$clean['show_download_count']    = ! empty( $input['show_download_count'] );

		/**
		 * Filter sanitized settings (Pro persists its own settings here).
		 *
		 * @since 1.0.0
		 * @param array $clean Sanitized settings.
		 * @param array $input Raw input.
		 */
		return apply_filters( 'tslgd_settings_sanitized', $clean, $input );
	}

	/**
	 * Public post types a gate button can be injected into.
	 *
	 * @return array<string,string> Slug => label.
	 */
	public static function injectable_post_types() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		$out = array();
		foreach ( $types as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'tslgd_file' ), true ) ) {
				continue;
			}
			$out[ $type->name ] = $type->labels->name;
		}
		return $out;
	}

	/**
	 * Published gated files, for the "which file" dropdowns.
	 *
	 * @return array<int,string> ID => title.
	 */
	public static function gate_choices() {
		$out = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'tslgd_file',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $gate ) {
			$out[ $gate->ID ] = '' !== $gate->post_title ? $gate->post_title : ( '#' . $gate->ID );
		}
		return $out;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'templatesell-gated-downloads' ) );
		}

		$s = self::get();
		?>
		<div class="wrap tslgd-wrap">
			<h1 class="tslgd-page-title"><?php esc_html_e( 'Gated Downloads Settings', 'templatesell-gated-downloads' ); ?></h1>

			<p class="tslgd-settings-intro description">
				<?php esc_html_e( 'These are the global defaults. Any form can override its fields, messages, and notification on the form’s own edit screen (“Form Fields & Behavior”); forms that don’t override inherit the settings below.', 'templatesell-gated-downloads' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<div class="tslgd-card">
					<h2><?php esc_html_e( 'Default Form Fields', 'templatesell-gated-downloads' ); ?></h2>
					<p class="description"><?php esc_html_e( 'The fields new forms start with, and the fallback for forms that don’t customize their own. Choose which appear and which are required.', 'templatesell-gated-downloads' ); ?></p>
					<table class="tslgd-fields-table widefat">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Field', 'templatesell-gated-downloads' ); ?></th>
								<th><?php esc_html_e( 'Show', 'templatesell-gated-downloads' ); ?></th>
								<th><?php esc_html_e( 'Required', 'templatesell-gated-downloads' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$labels = array(
								'name'  => __( 'Name', 'templatesell-gated-downloads' ),
								'email' => __( 'Email', 'templatesell-gated-downloads' ),
								'phone' => __( 'Phone', 'templatesell-gated-downloads' ),
							);
							foreach ( $labels as $key => $label ) :
								$enabled  = ! empty( $s['fields'][ $key ]['enabled'] );
								$required = ! empty( $s['fields'][ $key ]['required'] );
								$is_email = ( 'email' === $key );
								?>
								<tr>
									<td><strong><?php echo esc_html( $label ); ?></strong><?php echo $is_email ? ' <em>(' . esc_html__( 'always on', 'templatesell-gated-downloads' ) . ')</em>' : ''; ?></td>
									<td>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[fields][<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> <?php disabled( $is_email ); ?> />
										<?php if ( $is_email ) : ?>
											<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[fields][email][enabled]" value="1" />
										<?php endif; ?>
									</td>
									<td>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[fields][<?php echo esc_attr( $key ); ?>][required]" value="1" <?php checked( $required ); ?> />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="tslgd-card">
					<h2><?php esc_html_e( 'Consent & Messaging', 'templatesell-gated-downloads' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Consent checkbox', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[consent_enabled]" value="1" <?php checked( ! empty( $s['consent_enabled'] ) ); ?> />
									<?php esc_html_e( 'Show a consent checkbox in the form', 'templatesell-gated-downloads' ); ?>
								</label>
								<p><input type="text" class="large-text" name="<?php echo esc_attr( self::OPTION ); ?>[consent_text]" value="<?php echo esc_attr( $s['consent_text'] ); ?>" /></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_success"><?php esc_html_e( 'Success message', 'templatesell-gated-downloads' ); ?></label></th>
							<td><input type="text" id="tslgd_success" class="large-text" name="<?php echo esc_attr( self::OPTION ); ?>[success_message]" value="<?php echo esc_attr( $s['success_message'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_btn"><?php esc_html_e( 'Default button label', 'templatesell-gated-downloads' ); ?></label></th>
							<td><input type="text" id="tslgd_btn" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[button_label]" value="<?php echo esc_attr( $s['button_label'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Download count', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_download_count]" value="1" <?php checked( ! empty( $s['show_download_count'] ) ); ?> />
									<?php esc_html_e( 'Show “Downloaded N times” under the download button', 'templatesell-gated-downloads' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Social proof for popular files. Hidden automatically until a file has at least one download.', 'templatesell-gated-downloads' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_delivery"><?php esc_html_e( 'After submit', 'templatesell-gated-downloads' ); ?></label></th>
							<td>
								<select id="tslgd_delivery" name="<?php echo esc_attr( self::OPTION ); ?>[delivery_mode]">
									<option value="download" <?php selected( $s['delivery_mode'], 'download' ); ?>><?php esc_html_e( 'Start file download', 'templatesell-gated-downloads' ); ?></option>
									<option value="new_tab" <?php selected( $s['delivery_mode'], 'new_tab' ); ?>><?php esc_html_e( 'Open file in a new tab', 'templatesell-gated-downloads' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="tslgd-card">
					<h2><?php esc_html_e( 'Automatic Placement', 'templatesell-gated-downloads' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Show a download button on posts automatically, without pasting a shortcode into each one. Any post can opt out or pick a different file from its own edit screen.', 'templatesell-gated-downloads' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-insert', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_insert_enabled]" value="1" <?php checked( ! empty( $s['auto_insert_enabled'] ) ); ?> />
									<?php esc_html_e( 'Add a download button to post content automatically', 'templatesell-gated-downloads' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_ai_gate"><?php esc_html_e( 'File to offer', 'templatesell-gated-downloads' ); ?></label></th>
							<td>
								<?php $gates = self::gate_choices(); ?>
								<select id="tslgd_ai_gate" name="<?php echo esc_attr( self::OPTION ); ?>[auto_insert_gate_id]">
									<option value="0"><?php esc_html_e( '— Select a gated file —', 'templatesell-gated-downloads' ); ?></option>
									<?php foreach ( $gates as $gate_id => $title ) : ?>
										<option value="<?php echo esc_attr( $gate_id ); ?>" <?php selected( (int) $s['auto_insert_gate_id'], $gate_id ); ?>><?php echo esc_html( $title ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( empty( $gates ) ) : ?>
									<p class="description"><?php esc_html_e( 'No published gated files yet — create one first.', 'templatesell-gated-downloads' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_ai_pos"><?php esc_html_e( 'Position', 'templatesell-gated-downloads' ); ?></label></th>
							<td>
								<select id="tslgd_ai_pos" name="<?php echo esc_attr( self::OPTION ); ?>[auto_insert_position]">
									<option value="after" <?php selected( $s['auto_insert_position'], 'after' ); ?>><?php esc_html_e( 'After the content', 'templatesell-gated-downloads' ); ?></option>
									<option value="before" <?php selected( $s['auto_insert_position'], 'before' ); ?>><?php esc_html_e( 'Before the content', 'templatesell-gated-downloads' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Show on', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<?php
								$selected_types = (array) $s['auto_insert_post_types'];
								foreach ( self::injectable_post_types() as $slug => $label ) :
									?>
									<label style="margin-right:14px;">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_insert_post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_types, true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Limit to categories', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<?php
								$cats     = get_categories( array( 'hide_empty' => false, 'number' => 100 ) );
								$selected = array_map( 'absint', (array) $s['auto_insert_categories'] );
								if ( $cats ) :
									?>
									<div style="max-height:150px;overflow:auto;border:1px solid #dcdcde;padding:8px;max-width:420px;">
										<?php foreach ( $cats as $cat ) : ?>
											<label style="display:block;">
												<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_insert_categories][]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( in_array( (int) $cat->term_id, $selected, true ) ); ?> />
												<?php echo esc_html( $cat->name ); ?>
											</label>
										<?php endforeach; ?>
									</div>
									<p class="description"><?php esc_html_e( 'Leave all unchecked to show on every post. Applies to blog posts only.', 'templatesell-gated-downloads' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'No categories found.', 'templatesell-gated-downloads' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_ai_heading"><?php esc_html_e( 'Heading above the button', 'templatesell-gated-downloads' ); ?></label></th>
							<td>
								<input type="text" id="tslgd_ai_heading" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[auto_insert_heading]" value="<?php echo esc_attr( $s['auto_insert_heading'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Get the free checklist', 'templatesell-gated-downloads' ); ?>" />
								<p class="description"><?php esc_html_e( 'Optional. Leave empty for just the button.', 'templatesell-gated-downloads' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_ai_exclude"><?php esc_html_e( 'Exclude post IDs', 'templatesell-gated-downloads' ); ?></label></th>
							<td>
								<input type="text" id="tslgd_ai_exclude" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[auto_insert_exclude_ids]" value="<?php echo esc_attr( $s['auto_insert_exclude_ids'] ); ?>" placeholder="12, 34, 56" />
								<p class="description"><?php esc_html_e( 'Posts that already place the same file with a shortcode or block are skipped automatically.', 'templatesell-gated-downloads' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="tslgd-card">
					<h2><?php esc_html_e( 'Appearance', 'templatesell-gated-downloads' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Match the capture form to your brand. Colors apply everywhere the gate appears.', 'templatesell-gated-downloads' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="tslgd_primary_color"><?php esc_html_e( 'Primary color', 'templatesell-gated-downloads' ); ?></label></th>
							<td>
								<input type="text" id="tslgd_primary_color" class="tslgd-color" name="<?php echo esc_attr( self::OPTION ); ?>[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" data-default-color="#2563eb" />
								<p class="description"><?php esc_html_e( 'Download &amp; submit buttons, links, focus highlights, and the modal header icon.', 'templatesell-gated-downloads' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tslgd_header_color"><?php esc_html_e( 'Modal header color', 'templatesell-gated-downloads' ); ?></label></th>
							<td>
								<input type="text" id="tslgd_header_color" class="tslgd-color" name="<?php echo esc_attr( self::OPTION ); ?>[header_color]" value="<?php echo esc_attr( $s['header_color'] ); ?>" data-default-color="#111827" />
								<p class="description"><?php esc_html_e( 'Background of the modal header band (a subtle gradient is generated automatically).', 'templatesell-gated-downloads' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="tslgd-card">
					<h2><?php esc_html_e( 'Notifications', 'templatesell-gated-downloads' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Email notification', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[notify_admin]" value="1" <?php checked( ! empty( $s['notify_admin'] ) ); ?> />
									<?php esc_html_e( 'Email me when a new lead is captured', 'templatesell-gated-downloads' ); ?>
								</label>
								<p><input type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" /></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Download email', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[lead_email_enabled]" value="1" <?php checked( ! empty( $s['lead_email_enabled'] ) ); ?> />
									<?php esc_html_e( 'Email each lead their download link automatically', 'templatesell-gated-downloads' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'A clean, ready-made message with the file name and link. Skipped for no-file subscribe forms.', 'templatesell-gated-downloads' ); ?>
									<?php if ( ! Pro\Upgrade_Page::is_pro() ) : ?>
										<?php esc_html_e( 'Want your own subject line, message, and placeholders? That’s the Pro autoresponder.', 'templatesell-gated-downloads' ); ?>
									<?php endif; ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="tslgd-card">
					<h2><?php esc_html_e( 'Advanced', 'templatesell-gated-downloads' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'On uninstall', 'templatesell-gated-downloads' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $s['delete_data_on_uninstall'] ) ); ?> />
									<?php esc_html_e( 'Delete all plugin data (gated files and leads) when the plugin is deleted', 'templatesell-gated-downloads' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>

			<?php
			/**
			 * Render inline upsell (Pro upgrade card) at the foot of the page.
			 *
			 * @since 1.0.4
			 */
			do_action( 'tslgd_admin_upsell' );
			?>
		</div>
		<?php
	}
}
