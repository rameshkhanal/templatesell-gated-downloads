<?php
/**
 * Content Locker: hide post content behind the capture form.
 *
 * The locked HTML is never printed in the page source. The shortcode renders a
 * placeholder with the standard gate trigger button; after a successful form
 * submission a signed per-gate cookie is set and the locked HTML is fetched
 * over REST and swapped in. Because the page itself always renders locked,
 * full-page caching can never leak the protected content.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Locker
 */
class Locker {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'tslgd_lock';

	/**
	 * Unlock cookie name prefix; the gate ID is appended.
	 */
	const COOKIE_PREFIX = 'tslgd_unlock_';

	/**
	 * Frontend controller (gate loading + modal queue).
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Constructor.
	 *
	 * @param Frontend $frontend Frontend controller instance.
	 */
	public function __construct( Frontend $frontend ) {
		$this->frontend = $frontend;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Unlock cookie
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Signed unlock token for a gate. Ties the value to this site's salts so
	 * visitors cannot forge it.
	 *
	 * @param int $gate_id Gate (gated file) post ID.
	 * @return string
	 */
	public static function token( $gate_id ) {
		return hash_hmac( 'sha256', 'tslgd-unlock|' . absint( $gate_id ), wp_salt( 'auth' ) );
	}

	/**
	 * Set the unlock cookie after a successful submission for a gate.
	 *
	 * Deliberately not HttpOnly: the front-end JS reads only the cookie's
	 * presence (never trusts its value) to know it should request the
	 * unlocked content; the server re-validates the token on every request.
	 *
	 * @param int $gate_id Gate post ID.
	 * @return void
	 */
	public static function set_unlock_cookie( $gate_id ) {
		if ( headers_sent() ) {
			return;
		}

		/**
		 * Filter whether to unlock locked content for this visitor now.
		 *
		 * Add-ons that hold delivery back until an email confirmation return
		 * false here and call this method again once the lead is confirmed.
		 *
		 * @since 1.3.0
		 * @param bool $unlock  Whether to set the unlock cookie. Default true.
		 * @param int  $gate_id Gate post ID.
		 */
		if ( ! apply_filters( 'tslgd_set_unlock_cookie', true, $gate_id ) ) {
			return;
		}

		/**
		 * Filter how long (seconds) unlocked content stays unlocked for a visitor.
		 *
		 * @since 1.3.0
		 * @param int $ttl     Lifetime in seconds. Default 30 days.
		 * @param int $gate_id Gate post ID.
		 */
		$ttl = (int) apply_filters( 'tslgd_unlock_cookie_ttl', 30 * DAY_IN_SECONDS, $gate_id );

		setcookie(
			self::COOKIE_PREFIX . absint( $gate_id ),
			self::token( $gate_id ),
			time() + max( HOUR_IN_SECONDS, $ttl ),
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN,
			is_ssl(),
			false
		);
	}

	/**
	 * Does the current request carry a valid unlock cookie for this gate?
	 *
	 * @param int $gate_id Gate post ID.
	 * @return bool
	 */
	public static function is_unlocked( $gate_id ) {
		$name = self::COOKIE_PREFIX . absint( $gate_id );
		if ( empty( $_COOKIE[ $name ] ) ) {
			return false;
		}
		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		return hash_equals( self::token( $gate_id ), $value );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Shortcode
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Shortcode: [tslgd_lock id="123"]protected content[/tslgd_lock].
	 *
	 * Always renders the locked placeholder — the protected HTML is only ever
	 * delivered by the REST unlock endpoint, so cached pages stay safe.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed (protected) content.
	 * @return string
	 */
	public function render_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'id'      => '',
				'message' => __( 'This content is locked', 'templatesell-gated-downloads' ),
				'hint'    => __( 'Fill in your details to unlock it instantly.', 'templatesell-gated-downloads' ),
				'button'  => __( 'Unlock content', 'templatesell-gated-downloads' ),
				'teaser'  => '',
			),
			$atts,
			self::SHORTCODE
		);

		$gate = $this->frontend->get_gate( absint( $atts['id'] ) );
		if ( ! $gate ) {
			// Fail closed: a missing/unpublished gate must never expose the content.
			return '';
		}

		wp_enqueue_style( 'tslgd-public' );
		wp_enqueue_script( 'tslgd-public' );

		$uid = $this->frontend->queue_modal( $gate );

		ob_start();
		?>
		<div class="tslgd-lock" data-gate="<?php echo esc_attr( $gate['id'] ); ?>" data-post="<?php echo esc_attr( get_the_ID() ); ?>">
			<div class="tslgd-lock__preview" aria-hidden="true">
				<?php if ( '' !== $atts['teaser'] ) : ?>
					<p class="tslgd-lock__teaser"><?php echo esc_html( $atts['teaser'] ); ?></p>
				<?php else : ?>
					<span class="tslgd-lock__bar"></span>
					<span class="tslgd-lock__bar"></span>
					<span class="tslgd-lock__bar tslgd-lock__bar--short"></span>
				<?php endif; ?>
			</div>
			<div class="tslgd-lock__panel">
				<span class="tslgd-lock__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17 9V7a5 5 0 0 0-10 0v2H5v13h14V9h-2zm-8-2a3 3 0 0 1 6 0v2H9V7zm8 13H7v-9h10v9zm-5-3a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
				</span>
				<h3 class="tslgd-lock__title"><?php echo esc_html( $atts['message'] ); ?></h3>
				<p class="tslgd-lock__hint"><?php echo esc_html( $atts['hint'] ); ?></p>
				<button
					type="button"
					class="tslgd-btn"
					data-modal="<?php echo esc_attr( $uid ); ?>"
					data-gate="<?php echo esc_attr( $gate['id'] ); ?>"
				>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm6-8h-1V7a5 5 0 0 0-9.9-1h2.07A3 3 0 0 1 15 7v2H6v13h12V9zm-2 11H8v-9h8v9z"/></svg>
					<span class="tslgd-btn-label"><?php echo esc_html( $atts['button'] ); ?></span>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/*
	 * -----------------------------------------------------------------------
	 * REST unlock endpoint
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Register POST tslgd/v1/unlock.
	 *
	 * Public route by design: pages are often served from cache so a nonce
	 * would arrive stale. Authorization is the signed per-gate unlock cookie,
	 * which only the submission endpoint issues.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			'tslgd/v1',
			'/unlock',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_unlock' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return the rendered locked-content chunks for one gate on one post.
	 *
	 * @param \WP_REST_Request $request Request (gate_id, post_id).
	 * @return \WP_REST_Response
	 */
	public function handle_unlock( $request ) {
		$gate_id = absint( $request->get_param( 'gate_id' ) );
		$post_id = absint( $request->get_param( 'post_id' ) );

		$denied = new \WP_REST_Response(
			array( 'message' => __( 'This content is locked.', 'templatesell-gated-downloads' ) ),
			403
		);

		if ( ! $gate_id || ! $post_id || ! self::is_unlocked( $gate_id ) || ! $this->frontend->get_gate( $gate_id ) ) {
			return $denied;
		}

		$post = get_post( $post_id );
		if (
			! $post
			|| 'publish' !== $post->post_status
			|| '' !== $post->post_password
			|| ! is_post_publicly_viewable( $post )
		) {
			return $denied;
		}

		return new \WP_REST_Response(
			array( 'items' => $this->extract_locked_html( $post->post_content, $gate_id ) ),
			200
		);
	}

	/**
	 * Extract and render every [tslgd_lock] chunk for a gate, in source order.
	 *
	 * @param string $content Raw post content.
	 * @param int    $gate_id Gate post ID.
	 * @return string[] Rendered HTML chunks.
	 */
	private function extract_locked_html( $content, $gate_id ) {
		if ( ! has_shortcode( $content, self::SHORTCODE ) ) {
			return array();
		}

		$items   = array();
		$pattern = '/' . get_shortcode_regex( array( self::SHORTCODE ) ) . '/s';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$parsed = shortcode_parse_atts( isset( $m[3] ) ? $m[3] : '' );
				$parsed = is_array( $parsed ) ? $parsed : array();
				$id     = isset( $parsed['id'] ) ? absint( $parsed['id'] ) : 0;

				if ( $id !== $gate_id ) {
					continue;
				}

				$inner = isset( $m[5] ) ? $m[5] : '';

				/**
				 * Filter the rendered HTML of an unlocked content chunk.
				 *
				 * @since 1.3.0
				 * @param string $html    Rendered chunk.
				 * @param int    $gate_id Gate post ID.
				 */
				$items[] = apply_filters(
					'tslgd_unlocked_content',
					wp_kses_post( do_shortcode( shortcode_unautop( wpautop( $inner ) ) ) ),
					$gate_id
				);
			}
		}

		return $items;
	}
}
