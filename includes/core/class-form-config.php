<?php
/**
 * Per-form configuration resolver.
 *
 * Each gated file (form) may store an optional `_tslgd_form` meta bundle that
 * overrides the global Settings for that form only. This class merges the
 * per-form overrides over the global defaults and returns the single *effective*
 * config used to render the modal, validate the submission, and deliver.
 *
 * A form with no meta (or with its override toggles off) resolves to the global
 * settings verbatim — so existing forms behave exactly as before.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Core;

use TemplateSell\GatedDownloads\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Form_Config
 */
class Form_Config {

	/**
	 * Post-meta key holding the per-form override bundle.
	 *
	 * @var string
	 */
	const META = '_tslgd_form';

	/**
	 * Resolve the effective configuration for a gated file.
	 *
	 * @param int $post_id Gated file post ID.
	 * @return array {
	 *     @type array  $fields          Ordered list of enabled, hydrated fields.
	 *     @type bool   $consent_enabled Whether to show the consent checkbox.
	 *     @type string $consent_text    Consent label.
	 *     @type string $success_message Post-submit message.
	 *     @type string $submit_text     Submit button label.
	 *     @type string $redirect_url    Optional post-submit redirect.
	 *     @type string $delivery_mode   'download' | 'new_tab'.
	 *     @type array  $notify          [ enabled(bool), email(string) ].
	 * }
	 */
	public static function for_gate( $post_id ) {
		$settings = Settings::get();
		$meta     = get_post_meta( (int) $post_id, self::META, true );
		$meta     = is_array( $meta ) ? $meta : array();

		$config = array(
			'fields'          => self::resolve_fields( $meta, $settings ),
			'consent_enabled' => ! empty( $settings['consent_enabled'] ),
			'consent_text'    => isset( $settings['consent_text'] ) ? $settings['consent_text'] : '',
			'success_message' => self::resolve_message( $meta, $settings ),
			'submit_text'     => self::resolve_submit( $meta, $settings ),
			'redirect_url'    => self::resolve_redirect( $meta ),
			'delivery_mode'   => isset( $settings['delivery_mode'] ) ? $settings['delivery_mode'] : 'download',
			'notify'          => self::resolve_notify( $meta, $settings ),
		);

		/**
		 * Filter the resolved per-form configuration.
		 *
		 * @since 1.1.0
		 * @param array $config  Effective config.
		 * @param int   $post_id Gated file post ID.
		 * @param array $meta    Raw per-form meta bundle.
		 */
		return apply_filters( 'tslgd_form_config', $config, (int) $post_id, $meta );
	}

	/**
	 * Build the ordered, hydrated list of enabled fields.
	 *
	 * @param array $meta     Per-form meta.
	 * @param array $settings Global settings.
	 * @return array
	 */
	private static function resolve_fields( $meta, $settings ) {
		$catalog = Field_Catalog::all();
		$list    = array();

		if ( ! empty( $meta['override_fields'] ) && ! empty( $meta['fields'] ) && is_array( $meta['fields'] ) ) {
			// Per-form override: honor the stored order + enabled/required flags.
			foreach ( $meta['fields'] as $row ) {
				$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
				if ( '' === $key || ! isset( $catalog[ $key ] ) || isset( $list[ $key ] ) ) {
					continue;
				}
				$enabled = ( 'email' === $key ) ? true : ! empty( $row['enabled'] );
				if ( ! $enabled ) {
					continue;
				}
				$list[ $key ] = self::hydrate( $key, $catalog[ $key ], ! empty( $row['required'] ) );
			}

			// Email is mandatory even if it was dropped from the override list.
			if ( ! isset( $list['email'] ) && isset( $catalog['email'] ) ) {
				$email_required = ! empty( $settings['fields']['email']['required'] );
				$list           = array( 'email' => self::hydrate( 'email', $catalog['email'], $email_required ) ) + $list;
			}
		} else {
			// Inherit global: Name / Email / Phone in catalog order.
			$global = isset( $settings['fields'] ) ? $settings['fields'] : array();
			foreach ( array( 'name', 'email', 'phone' ) as $key ) {
				if ( ! isset( $catalog[ $key ] ) ) {
					continue;
				}
				$enabled = ( 'email' === $key ) ? true : ! empty( $global[ $key ]['enabled'] );
				if ( ! $enabled ) {
					continue;
				}
				$list[ $key ] = self::hydrate( $key, $catalog[ $key ], ! empty( $global[ $key ]['required'] ) );
			}
		}

		return array_values( $list );
	}

	/**
	 * Expand a catalog definition into a render-ready field.
	 *
	 * @param string $key      Field slug.
	 * @param array  $def      Catalog definition.
	 * @param bool   $required Whether the field is required.
	 * @return array
	 */
	private static function hydrate( $key, $def, $required ) {
		$store = isset( $def['store'] ) ? $def['store'] : 'extra';
		return array(
			'key'          => $key,
			'type'         => isset( $def['type'] ) ? $def['type'] : 'text',
			'label'        => isset( $def['label'] ) ? $def['label'] : $key,
			'autocomplete' => isset( $def['autocomplete'] ) ? $def['autocomplete'] : '',
			'placeholder'  => isset( $def['placeholder'] ) ? $def['placeholder'] : '',
			'store'        => $store,
			'input_name'   => 'column' === $store ? $key : 'tslgd_field[' . $key . ']',
			'required'     => (bool) $required,
		);
	}

	/**
	 * Resolve the success message (blank per-form value inherits the global).
	 *
	 * @param array $meta     Per-form meta.
	 * @param array $settings Global settings.
	 * @return string
	 */
	private static function resolve_message( $meta, $settings ) {
		if ( ! empty( $meta['override_messages'] ) && isset( $meta['success_message'] ) && '' !== trim( (string) $meta['success_message'] ) ) {
			return (string) $meta['success_message'];
		}
		return isset( $settings['success_message'] ) ? $settings['success_message'] : __( 'Thank you! Your download will begin shortly.', 'templatesell-gated-downloads' );
	}

	/**
	 * Resolve the submit button label (blank per-form value inherits the global).
	 *
	 * @param array $meta     Per-form meta.
	 * @param array $settings Global settings.
	 * @return string
	 */
	private static function resolve_submit( $meta, $settings ) {
		if ( ! empty( $meta['override_messages'] ) && isset( $meta['submit_text'] ) && '' !== trim( (string) $meta['submit_text'] ) ) {
			return (string) $meta['submit_text'];
		}
		return isset( $settings['button_label'] ) ? $settings['button_label'] : __( 'Download', 'templatesell-gated-downloads' );
	}

	/**
	 * Resolve the optional post-submit redirect URL (per-form only).
	 *
	 * @param array $meta Per-form meta.
	 * @return string
	 */
	private static function resolve_redirect( $meta ) {
		if ( ! empty( $meta['override_messages'] ) && ! empty( $meta['redirect_url'] ) ) {
			return (string) $meta['redirect_url'];
		}
		return '';
	}

	/**
	 * Resolve the admin-notification target.
	 *
	 * @param array $meta     Per-form meta.
	 * @param array $settings Global settings.
	 * @return array [ enabled(bool), email(string) ]
	 */
	private static function resolve_notify( $meta, $settings ) {
		$global_email = isset( $settings['notify_email'] ) ? $settings['notify_email'] : get_option( 'admin_email' );

		if ( ! empty( $meta['override_notify'] ) ) {
			$email = ( isset( $meta['notify_email'] ) && '' !== trim( (string) $meta['notify_email'] ) ) ? $meta['notify_email'] : $global_email;
			return array(
				'enabled' => ! empty( $meta['notify_enabled'] ),
				'email'   => $email,
			);
		}

		return array(
			'enabled' => ! empty( $settings['notify_admin'] ),
			'email'   => $global_email,
		);
	}
}
