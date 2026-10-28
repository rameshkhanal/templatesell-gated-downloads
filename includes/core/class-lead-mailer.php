<?php
/**
 * Basic download email: sends each lead their download link.
 *
 * Pro's autoresponder replaces it when enabled, so a lead never gets two emails.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Core;

use TemplateSell\GatedDownloads\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lead_Mailer
 */
class Lead_Mailer {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'tslgd_lead_created', array( $this, 'send' ), 20, 2 );
	}

	/**
	 * Email the download link to a newly captured lead.
	 *
	 * @param int   $lead_id Lead ID (unused).
	 * @param array $data    Lead data.
	 * @return void
	 */
	public function send( $lead_id, $data ) {
		unset( $lead_id );

		$settings = Settings::get();
		if ( empty( $settings['lead_email_enabled'] ) ) {
			return;
		}

		$email    = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$file_url = isset( $data['file_url'] ) ? (string) $data['file_url'] : '';
		if ( ! is_email( $email ) || '' === $file_url ) {
			return; // Nothing to deliver.
		}

		// The Pro autoresponder sends a customizable version of this email;
		// when it is active and enabled, the free mailer stays silent.
		if ( $this->pro_autoresponder_active() ) {
			return;
		}

		/**
		 * Short-circuit the free download email.
		 *
		 * @since 1.2.0
		 * @param bool  $send Whether to send. Default true.
		 * @param array $data Lead data.
		 */
		if ( ! apply_filters( 'tslgd_send_lead_email', true, $data ) ) {
			return;
		}

		$name  = isset( $data['name'] ) && '' !== $data['name'] ? $data['name'] : __( 'there', 'templatesell-gated-downloads' );
		$file  = isset( $data['file_title'] ) ? $data['file_title'] : '';
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: %s: file title. */
			__( 'Your download: %s', 'templatesell-gated-downloads' ),
			$file
		);
		$body = sprintf(
			/* translators: 1: lead name, 2: file title, 3: file URL, 4: site name. */
			__( "Hi %1\$s,\n\nThanks for your interest! Here is your download link for %2\$s:\n\n%3\$s\n\n— %4\$s", 'templatesell-gated-downloads' ),
			$name,
			$file,
			$file_url,
			$site
		);

		/**
		 * Filter the free download email subject / body.
		 *
		 * @since 1.2.0
		 * @param string $subject Subject line.
		 * @param array  $data    Lead data.
		 */
		$subject = apply_filters( 'tslgd_lead_email_subject', $subject, $data );
		$body    = apply_filters( 'tslgd_lead_email_body', $body, $data );

		wp_mail( $email, $subject, $body );
	}

	/**
	 * Is the Pro add-on active with its autoresponder switched on?
	 *
	 * Read through the public tslgd_is_pro signal first so this stays cheap
	 * and correct when Pro is absent.
	 *
	 * @return bool
	 */
	private function pro_autoresponder_active() {
		if ( ! apply_filters( 'tslgd_is_pro', false ) ) {
			return false;
		}
		$pro = get_option( 'tslgd_pro_settings', array() );
		return is_array( $pro ) && ! empty( $pro['autoresponder_enabled'] );
	}
}
