<?php
/**
 * Front-end controller: shortcode, footer modals, and submission endpoint.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Frontend;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Core\Lead_Repository;
use TemplateSell\GatedDownloads\Core\Stats_Repository;
use TemplateSell\GatedDownloads\Core\Field_Catalog;
use TemplateSell\GatedDownloads\Core\Form_Config;
use TemplateSell\GatedDownloads\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 */
class Frontend {

	/**
	 * Modals queued by shortcode calls, flushed in wp_footer.
	 *
	 * @var array
	 */
	private $queued_modals = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'tslgd_download', array( $this, 'render_shortcode' ) );
		// Short alias so [tsgd id="X"] also works.
		add_shortcode( 'tslgd', array( $this, 'render_shortcode' ) );
		// Inline text trigger: [tslgd_link id="X"]any wording[/tslgd_link].
		add_shortcode( 'tslgd_link', array( $this, 'render_link_shortcode' ) );
		add_action( 'wp_footer', array( $this, 'render_footer_modals' ), 99 );

		// Public modal-queue API: $uid = apply_filters( 'tslgd_queue_modal', '', $gate_id ).
		// Lets companions (Pro popup triggers, custom themes) request the standard
		// capture modal for a gate without rendering the shortcode button.
		add_filter( 'tslgd_queue_modal', array( $this, 'filter_queue_modal' ), 10, 2 );

		// Submission endpoints: REST (primary) + admin-ajax (fallback).
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_action( 'wp_ajax_tslgd_submit', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_tslgd_submit', array( $this, 'handle_ajax' ) );
	}

	/**
	 * Load a gate (gated file) by post ID.
	 *
	 * Public so companion services (Locker, Pro) can resolve gates the same way.
	 *
	 * @param int $post_id Gated file post ID.
	 * @return array|null
	 */
	public function get_gate( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$terms    = get_the_terms( $post_id, Post_Type::TAXONOMY );
		$category = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';

		return array(
			'id'       => $post_id,
			'title'    => $post->post_title,
			'category' => $category,
			'url'      => (string) get_post_meta( $post_id, '_tslgd_file_url', true ),
		);
	}

	/**
	 * Shortcode: [tslgd_download id="123" label="Download"].
	 *
	 * Outputs only the trigger button; the modal is queued for wp_footer so it
	 * renders directly inside <body>, immune to theme overflow/z-index wrappers.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$settings = Settings::get();
		$atts     = shortcode_atts(
			array(
				'id'    => '',
				'label' => isset( $settings['button_label'] ) ? $settings['button_label'] : __( 'Download', 'templatesell-gated-downloads' ),
			),
			$atts,
			'tslgd_download'
		);

		$gate = $this->get_gate( absint( $atts['id'] ) );
		if ( ! $gate ) {
			return '';
		}

		wp_enqueue_style( 'tslgd-public' );
		wp_enqueue_script( 'tslgd-public' );

		$uid = $this->queue_modal( $gate );

		ob_start();
		?>
		<div class="tslgd-btn-wrap">
			<button
				type="button"
				class="tslgd-btn"
				data-modal="<?php echo esc_attr( $uid ); ?>"
				data-gate="<?php echo esc_attr( $gate['id'] ); ?>"
			>
<?php if ( '' !== (string) $gate['url'] ) : ?>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 16l-5-5h3V4h4v7h3l-5 5zm-7 2h14v2H5v-2z"/></svg>
				<?php else : ?>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"/></svg>
				<?php endif; ?>
				<span class="tslgd-btn-label"><?php echo esc_html( $atts['label'] ); ?></span>
			</button>
			<?php echo $this->download_count_html( $gate['id'], $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in download_count_html(). ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode: [tslgd_link id="123"]Download the price list[/tslgd_link].
	 *
	 * Turns any wording — inline, mid-sentence, or an existing styled link —
	 * into a gate trigger, for sites that don't want our button.
	 *
	 * @since 1.3.0
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed link text.
	 * @return string
	 */
	public function render_link_shortcode( $atts, $content = '' ) {
		$settings = Settings::get();
		$atts     = shortcode_atts(
			array(
				'id'    => '',
				'class' => '',
			),
			$atts,
			'tslgd_link'
		);

		$gate = $this->get_gate( absint( $atts['id'] ) );
		if ( ! $gate ) {
			return '';
		}

		$text = trim( wp_strip_all_tags( (string) $content ) );
		if ( '' === $text ) {
			$text = isset( $settings['button_label'] ) ? $settings['button_label'] : __( 'Download', 'templatesell-gated-downloads' );
		}

		wp_enqueue_style( 'tslgd-public' );
		wp_enqueue_script( 'tslgd-public' );

		$uid = $this->queue_modal( $gate );

		return sprintf(
			'<a href="#" class="tslgd-link %1$s" data-modal="%2$s" data-gate="%3$s" role="button">%4$s</a>',
			esc_attr( $atts['class'] ),
			esc_attr( $uid ),
			esc_attr( $gate['id'] ),
			esc_html( $text )
		);
	}

	/**
	 * "Downloaded N times" markup for a gate, or '' when disabled or unused.
	 *
	 * @since 1.3.0
	 * @param int   $gate_id  Gate post ID.
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function download_count_html( $gate_id, $settings ) {
		if ( empty( $settings['show_download_count'] ) ) {
			return '';
		}

		$count = Stats_Repository::download_count( $gate_id );
		if ( $count < 1 ) {
			return ''; // Never advertise a zero.
		}

		return '<span class="tslgd-dl-count">' . esc_html(
			sprintf(
				/* translators: %s: formatted download count. */
				_n( 'Downloaded %s time', 'Downloaded %s times', $count, 'templatesell-gated-downloads' ),
				number_format_i18n( $count )
			)
		) . '</span>';
	}

	/**
	 * Filter callback backing the 'tslgd_queue_modal' API.
	 *
	 * Queues the standard capture modal for a gate (and enqueues the public
	 * assets) and returns its element id, or '' when the gate is invalid.
	 *
	 * @since 1.3.0
	 * @param string $uid     Incoming value (ignored; pass '').
	 * @param int    $gate_id Gate (gated file) post ID.
	 * @return string Modal element id for a trigger's data-modal attribute, or ''.
	 */
	public function filter_queue_modal( $uid, $gate_id ) {
		$gate = $this->get_gate( absint( $gate_id ) );
		if ( ! $gate ) {
			return '';
		}
		wp_enqueue_style( 'tslgd-public' );
		wp_enqueue_script( 'tslgd-public' );
		return $this->queue_modal( $gate );
	}

	/**
	 * Queue a modal for a gate; it is flushed into <body> at wp_footer.
	 *
	 * Used by the shortcode and by companion services (Locker) that need the
	 * standard capture modal for their own trigger buttons.
	 *
	 * @param array $gate Gate data from get_gate().
	 * @return string The modal element id to reference from a trigger's data-modal.
	 */
	public function queue_modal( $gate ) {
		$uid                         = 'tslgd-modal-' . $gate['id'] . '-' . wp_rand( 1000, 9999 );
		$this->queued_modals[ $uid ] = array_merge( $gate, array( 'uid' => $uid ) );
		return $uid;
	}

	/**
	 * Render all queued modals directly into <body> via wp_footer.
	 *
	 * @return void
	 */
	public function render_footer_modals() {
		if ( empty( $this->queued_modals ) ) {
			return;
		}

		foreach ( $this->queued_modals as $modal ) {
			$config = Form_Config::for_gate( $modal['id'] );
			$this->render_modal( $modal, $config );
		}
	}

	/**
	 * Render a single modal from a resolved per-form config.
	 *
	 * @param array $modal  Modal data.
	 * @param array $config Resolved form config (see Form_Config::for_gate).
	 * @return void
	 */
	private function render_modal( $modal, $config ) {
		$uid      = $modal['uid'];
		$category = isset( $modal['category'] ) ? $modal['category'] : '';
		$btn_text = $config['submit_text'];
		// When no file is attached, the form acts as a simple email opt-in / subscribe.
		$has_file = '' !== (string) ( isset( $modal['url'] ) ? $modal['url'] : '' );
		?>
		<div class="tslgd-overlay" id="<?php echo esc_attr( $uid ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title" hidden>
			<div class="tslgd-modal" role="document">
				<form class="tslgd-form" novalidate>

					<header class="tslgd-modal__header">
						<span class="tslgd-modal__icon" aria-hidden="true">
							<?php if ( $has_file ) : ?>
								<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 16l-5-5h3V4h4v7h3l-5 5zm-7 2h14v2H5v-2z"/></svg>
							<?php else : ?>
								<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"/></svg>
							<?php endif; ?>
						</span>
						<div class="tslgd-modal__heading">
							<?php if ( '' !== $category ) : ?>
								<p class="tslgd-modal__eyebrow"><?php echo esc_html( $category ); ?></p>
							<?php endif; ?>
							<h2 class="tslgd-modal__title" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo esc_html( $modal['title'] ); ?></h2>
							<p class="tslgd-modal__subtitle">
								<?php
								echo esc_html(
									$has_file
										? __( 'Fill in your details to download', 'templatesell-gated-downloads' )
										: __( 'Fill in your details to continue', 'templatesell-gated-downloads' )
								);
								?>
							</p>
						</div>
						<button type="button" class="tslgd-modal__close" aria-label="<?php esc_attr_e( 'Close', 'templatesell-gated-downloads' ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
						</button>
					</header>

					<div class="tslgd-modal__body">
						<input type="hidden" name="gate_id" value="<?php echo esc_attr( $modal['id'] ); ?>" />

						<?php // Honeypot: invisible to humans (moved off-screen, tabindex -1), tempting to bots. ?>
						<div class="tslgd-hp" aria-hidden="true">
							<label>
								<?php esc_html_e( 'Leave this field empty', 'templatesell-gated-downloads' ); ?>
								<input type="text" name="tslgd_hp" value="" tabindex="-1" autocomplete="off" />
							</label>
						</div>

						<?php foreach ( $config['fields'] as $field ) : ?>
							<?php $this->render_field( $uid, $field ); ?>
						<?php endforeach; ?>

						<?php
						/**
						 * Inject additional form fields (Pro custom fields hook here).
						 *
						 * @since 1.0.0
						 * @param array $modal Modal/gate data.
						 */
						do_action( 'tslgd_render_form_fields', $modal );
						?>

						<?php if ( ! empty( $config['consent_enabled'] ) ) : ?>
							<div class="tslgd-field tslgd-field--consent">
								<label>
									<input type="checkbox" name="consent" value="1" required />
									<span><?php echo esc_html( $config['consent_text'] ); ?></span>
								</label>
							</div>
						<?php endif; ?>

						<div class="tslgd-form__message" role="alert" aria-live="polite"></div>
					</div>

					<footer class="tslgd-modal__footer">
						<p class="tslgd-privacy-note">
							<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1l9 4v6c0 5-3.8 9.7-9 11-5.2-1.3-9-6-9-11V5l9-4zm0 2.2L5 6v5c0 4 2.9 7.8 7 9 4.1-1.2 7-5 7-9V6l-7-2.8z"/></svg>
							<?php esc_html_e( 'Your information is kept private and never shared.', 'templatesell-gated-downloads' ); ?>
						</p>
						<button type="submit" class="tslgd-submit">
							<span class="tslgd-submit__loader" aria-hidden="true"></span>
							<span class="tslgd-submit__text"><?php echo esc_html( $btn_text ); ?></span>
						</button>
					</footer>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one capture field from its resolved definition.
	 *
	 * @param string $uid   Modal unique id (for input ids).
	 * @param array  $field Hydrated field (key/type/label/autocomplete/…).
	 * @return void
	 */
	private function render_field( $uid, $field ) {
		$id       = $uid . '-' . $field['key'];
		$required = ! empty( $field['required'] );
		?>
		<div class="tslgd-field">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $field['label'] ); ?><?php echo $required ? ' <span class="tslgd-req" aria-hidden="true">*</span>' : ''; ?>
			</label>
			<input
				type="<?php echo esc_attr( $field['type'] ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $field['input_name'] ); ?>"
				<?php echo '' !== $field['autocomplete'] ? 'autocomplete="' . esc_attr( $field['autocomplete'] ) . '"' : ''; ?>
				<?php echo '' !== $field['placeholder'] ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
				<?php echo $required ? 'required aria-required="true"' : ''; ?>
			/>
			<span class="tslgd-field-error" aria-live="polite"></span>
		</div>
		<?php
	}

	/**
	 * Register the REST submission route.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			'tslgd/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_rest' ),
				'permission_callback' => '__return_true',
			)
		);

		// Anonymous analytics beacon (view / open counters). Public by design:
		// pages are often served from cache, so nonces would arrive stale.
		// It accepts only whitelisted events for published gates and stores
		// bare daily counters — no personal data.
		register_rest_route(
			'tslgd/v1',
			'/beacon',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_beacon' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST beacon handler: count a view or open for a gate.
	 *
	 * @since 1.2.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_beacon( $request ) {
		$gate_id = absint( $request->get_param( 'gate_id' ) );
		$event   = sanitize_key( (string) $request->get_param( 'event' ) );
		$metric  = array(
			'view'     => 'views',
			'open'     => 'opens',
			'download' => 'downloads',
		);

		if ( isset( $metric[ $event ] ) && $gate_id && $this->get_gate( $gate_id ) ) {
			Stats_Repository::record( $gate_id, $metric[ $event ] );
		}

		// Always 204: the beacon is fire-and-forget, nothing to report back.
		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * REST submission handler.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_rest( $request ) {
		$result = $this->process_submission( $request->get_params() );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array( 'message' => $result->get_error_message() ),
				400
			);
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * admin-ajax fallback handler.
	 *
	 * @return void
	 */
	public function handle_ajax() {
		$result = $this->process_submission( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Validate, sanitize, store, and prepare a submission response.
	 *
	 * @param array $raw Raw request params.
	 * @return array|\WP_Error
	 */
	private function process_submission( $raw ) {
		// Nonce verification (wp_rest nonce for both transports).
		$nonce = isset( $raw['nonce'] ) ? sanitize_text_field( wp_unslash( $raw['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'tslgd_nonce', __( 'Security check failed. Please refresh and try again.', 'templatesell-gated-downloads' ) );
		}

		// Honeypot: humans never see the field, so any value means a bot.
		if ( ! empty( $raw['tslgd_hp'] ) ) {
			return new \WP_Error( 'tslgd_spam', __( 'Something went wrong. Please try again.', 'templatesell-gated-downloads' ) );
		}

		$gate = $this->get_gate( isset( $raw['gate_id'] ) ? absint( $raw['gate_id'] ) : 0 );
		if ( ! $gate ) {
			return new \WP_Error( 'tslgd_gate', __( 'This download is no longer available.', 'templatesell-gated-downloads' ) );
		}

		$config = Form_Config::for_gate( $gate['id'] );

		$data = array(
			'gate_id'       => $gate['id'],
			'name'          => '',
			'email'         => '',
			'phone'         => '',
			'file_title'    => $gate['title'],
			'file_category' => $gate['category'],
			'file_url'      => $gate['url'],
			'source_url'    => isset( $raw['source_url'] ) ? esc_url_raw( wp_unslash( $raw['source_url'] ) ) : '',
			'consent'       => ! empty( $raw['consent'] ) ? 1 : 0,
			'ip_hash'       => $this->hash_ip(),
		);

		// Extra (non-column) field values, keyed by field slug.
		$raw_extra     = isset( $raw['tslgd_field'] ) ? (array) wp_unslash( $raw['tslgd_field'] ) : array();
		$extra_fields  = array();
		$errors        = array();

		foreach ( $config['fields'] as $field ) {
			$key      = $field['key'];
			$required = ! empty( $field['required'] );

			if ( 'column' === $field['store'] ) {
				$value        = isset( $raw[ $key ] ) ? wp_unslash( $raw[ $key ] ) : '';
				$value        = ( 'email' === $key ) ? sanitize_email( $value ) : sanitize_text_field( $value );
				$data[ $key ] = $value;

				if ( 'email' === $key ) {
					if ( ( $required || '' !== $value ) && ! is_email( $value ) ) {
						$errors['email'] = __( 'Please enter a valid email address.', 'templatesell-gated-downloads' );
					}
				} elseif ( $required && '' === $value ) {
					$errors[ $key ] = $this->required_message( $key, $field['label'] );
				}
			} else {
				$value = isset( $raw_extra[ $key ] ) && is_scalar( $raw_extra[ $key ] ) ? wp_unslash( $raw_extra[ $key ] ) : '';
				$value = ( 'url' === $field['type'] ) ? esc_url_raw( $value ) : sanitize_text_field( $value );

				if ( $required && '' === $value ) {
					$errors[ $key ] = $this->required_message( $key, $field['label'] );
				}
				if ( '' !== $value ) {
					$extra_fields[ $key ] = $value;
				}
			}
		}

		/**
		 * Filter submission validation errors (Pro spam/recaptcha hooks here).
		 *
		 * @since 1.0.0
		 * @param array $errors Field => message.
		 * @param array $data   Sanitized data.
		 */
		$errors = apply_filters( 'tslgd_validate_submission', $errors, $data );

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'tslgd_validation', reset( $errors ) );
		}

		// Persist standard extra fields inside the shared JSON payload.
		if ( ! empty( $extra_fields ) ) {
			$existing          = isset( $data['extra'] ) && is_array( $data['extra'] ) ? $data['extra'] : array();
			$existing['fields'] = $extra_fields;
			$data['extra']     = $existing;
		}

		// Traffic attribution (utm_* + external referrer), when the visit had any.
		$attribution = $this->sanitize_attribution( isset( $raw['tslgd_attr'] ) ? $raw['tslgd_attr'] : array() );
		if ( ! empty( $attribution ) ) {
			$existing        = isset( $data['extra'] ) && is_array( $data['extra'] ) ? $data['extra'] : array();
			$existing['utm'] = $attribution;
			$data['extra']   = $existing;
		}

		/**
		 * Filter lead data before it is stored.
		 *
		 * @since 1.0.0
		 * @param array $data Sanitized data.
		 */
		$data = apply_filters( 'tslgd_lead_data', $data );

		/**
		 * Fires before a lead is stored.
		 *
		 * @since 1.0.0
		 * @param array $data Sanitized data.
		 */
		do_action( 'tslgd_before_store_lead', $data );

		$lead_id = Lead_Repository::insert( $data );
		if ( false === $lead_id ) {
			// Never leak DB internals to the visitor.
			return new \WP_Error( 'tslgd_store', __( 'We could not save your details. Please try again.', 'templatesell-gated-downloads' ) );
		}

		/**
		 * Fires after a lead is created. Pro integrations hook here.
		 *
		 * @since 1.0.0
		 * @param int   $lead_id New lead ID.
		 * @param array $data    Sanitized data.
		 */
		do_action( 'tslgd_lead_created', $lead_id, $data );

		// Unlock any [tslgd_lock] content gated behind this form for this visitor.
		Locker::set_unlock_cookie( $gate['id'] );

		$this->maybe_notify_admin( $data, $config['notify'] );

		/**
		 * Filter the delivery URL (Pro swaps in signed/expiring links here).
		 *
		 * @since 1.0.0
		 * @param string $url  File URL.
		 * @param array  $gate Gate data.
		 * @param array  $data Lead data.
		 */
		$delivery_url = apply_filters( 'tslgd_delivery_url', $gate['url'], $gate, $data );

		// Match the download URL's scheme to the current request so an HTTPS page
		// never triggers a browser "insecure download blocked" warning on a
		// same-site file. External URLs are left exactly as the admin set them.
		$delivery_url = $this->match_request_scheme( $delivery_url );

		$response = array(
			'file_url' => esc_url_raw( $delivery_url ),
			'delivery' => $config['delivery_mode'],
			'message'  => $config['success_message'],
			'redirect' => '' !== $config['redirect_url'] ? esc_url_raw( $config['redirect_url'] ) : '',
		);

		/**
		 * Filter the successful-submission response sent back to the browser.
		 *
		 * Lets add-ons change what happens after capture — for example holding a
		 * download back behind an email confirmation step.
		 *
		 * @since 1.3.0
		 * @param array $response Response payload (file_url, delivery, message, redirect).
		 * @param array $data     Sanitized lead data.
		 * @param array $gate     Gate data.
		 */
		return apply_filters( 'tslgd_submission_response', $response, $data, $gate );
	}

	/**
	 * Align a download URL's scheme with the current request.
	 *
	 * Prevents a browser "insecure download blocked" warning when the page is
	 * served over HTTPS but a same-site file URL was stored as HTTP (or vice
	 * versa). Only same-host URLs are touched; external URLs the admin entered
	 * are returned untouched so an http-only external host is never broken.
	 *
	 * @param string $url Delivery URL.
	 * @return string
	 */
	private function match_request_scheme( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return $url;
		}

		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $url_host && $home_host && strtolower( $url_host ) === strtolower( $home_host ) ) {
			// set_url_scheme() with no scheme picks https when is_ssl(), else http.
			return set_url_scheme( $url );
		}

		return $url;
	}

	/**
	 * Sanitize submitted traffic attribution.
	 *
	 * Only the five standard UTM parameters and an external referrer are kept;
	 * anything else a client posts is discarded. Values are length-capped so a
	 * crafted request cannot bloat the row.
	 *
	 * @since 1.3.0
	 * @param mixed $raw Raw `tslgd_attr` input.
	 * @return array
	 */
	private function sanitize_attribution( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$allowed = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'referrer' );
		$out     = array();

		foreach ( $allowed as $key ) {
			if ( ! isset( $raw[ $key ] ) || ! is_scalar( $raw[ $key ] ) ) {
				continue;
			}
			$value = wp_unslash( $raw[ $key ] );
			$value = ( 'referrer' === $key ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
			$value = mb_substr( (string) $value, 0, 300 );

			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Human-friendly "required" message for a field.
	 *
	 * @param string $key   Field slug.
	 * @param string $label Field label.
	 * @return string
	 */
	private function required_message( $key, $label ) {
		switch ( $key ) {
			case 'name':
				return __( 'Please enter your name.', 'templatesell-gated-downloads' );
			case 'phone':
				return __( 'Please enter your phone number.', 'templatesell-gated-downloads' );
			default:
				/* translators: %s: field label */
				return sprintf( __( 'Please fill in the %s field.', 'templatesell-gated-downloads' ), $label );
		}
	}

	/**
	 * Send an admin notification email if enabled for this form.
	 *
	 * @param array $data   Lead data.
	 * @param array $notify Resolved notify config [ enabled(bool), email(string) ].
	 * @return void
	 */
	private function maybe_notify_admin( $data, $notify ) {
		if ( empty( $notify['enabled'] ) ) {
			return;
		}

		$to = ! empty( $notify['email'] ) ? $notify['email'] : get_option( 'admin_email' );

		/**
		 * Filter notification recipients (Pro adds multiple recipients here).
		 *
		 * @since 1.0.0
		 * @param array $emails Recipients.
		 * @param array $data   Lead data.
		 */
		$recipients = apply_filters( 'tslgd_email_recipients', array( $to ), $data );

		$subject = sprintf(
			/* translators: %s: file title */
			__( 'New lead: %s', 'templatesell-gated-downloads' ),
			$data['file_title']
		);

		$lines = array(
			sprintf( /* translators: %s: name */ __( 'Name: %s', 'templatesell-gated-downloads' ), $data['name'] ),
			sprintf( /* translators: %s: email */ __( 'Email: %s', 'templatesell-gated-downloads' ), $data['email'] ),
			sprintf( /* translators: %s: phone */ __( 'Phone: %s', 'templatesell-gated-downloads' ), $data['phone'] ),
		);

		// Append any captured standard extra fields (Company, Job Title, …).
		if ( isset( $data['extra']['fields'] ) && is_array( $data['extra']['fields'] ) ) {
			foreach ( $data['extra']['fields'] as $key => $value ) {
				$def     = Field_Catalog::get( $key );
				$label   = $def ? $def['label'] : $key;
				$lines[] = $label . ': ' . $value;
			}
		}

		$lines[] = sprintf( /* translators: %s: file title */ __( 'File: %s', 'templatesell-gated-downloads' ), $data['file_title'] );

		wp_mail( $recipients, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Return a salted hash of the visitor IP (privacy-preserving).
	 *
	 * @return string
	 */
	private function hash_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return '';
		}
		return hash( 'sha256', $ip . wp_salt() );
	}
}
