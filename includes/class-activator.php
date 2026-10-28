<?php
/**
 * Fired on plugin activation.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Core\Lead_Repository;
use TemplateSell\GatedDownloads\Core\Stats_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run activation tasks: create tables, seed defaults, register rewrite rules.
	 *
	 * @return void
	 */
	public static function activate() {
		Lead_Repository::create_table();
		Stats_Repository::create_table();
		self::seed_default_settings();

		// Register the CPT so its rewrite rules exist, then flush.
		Post_Type::register();
		flush_rewrite_rules();

		update_option( 'tslgd_version', TSLGD_VERSION );
	}

	/**
	 * Seed default settings on first activation only.
	 *
	 * @return void
	 */
	private static function seed_default_settings() {
		if ( false !== get_option( 'tslgd_settings', false ) ) {
			return;
		}

		$defaults = array(
			'fields'              => array(
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
			'consent_enabled'     => true,
			'consent_text'        => __( 'I agree to be contacted about this download.', 'templatesell-gated-downloads' ),
			'success_message'     => __( 'Thank you! Your download will begin shortly.', 'templatesell-gated-downloads' ),
			'button_label'        => __( 'Download', 'templatesell-gated-downloads' ),
			'notify_admin'        => true,
			'notify_email'        => get_option( 'admin_email' ),
			'delivery_mode'       => 'download', // download | new_tab.
			'delete_data_on_uninstall' => false,
		);

		add_option( 'tslgd_settings', $defaults );
	}
}
