<?php
/**
 * Gated Downloads uninstall handler.
 *
 * Data is removed only if the site owner opted in via the settings.
 *
 * @package Gated Downloads
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Perform the full Gated Downloads data removal.
 *
 * @return void
 */
function tslgd_run_uninstall() {
	$settings = get_option( 'tslgd_settings', array() );

	// Bail unless the user opted in to full data removal.
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	global $wpdb;

	// Drop the leads + stats tables.
	foreach ( array( 'tslgd_leads', 'tslgd_stats' ) as $unprefixed ) {
		$table = $wpdb->prefix . $unprefixed;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete gated file posts (and their meta).
	$gates = get_posts(
		array(
			'post_type'   => 'tslgd_file',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $gates as $gate_id ) {
		wp_delete_post( $gate_id, true );
	}

	// Remove options.
	delete_option( 'tslgd_settings' );
	delete_option( 'tslgd_version' );
	delete_option( 'tslgd_db_version' );
	delete_option( 'tslgd_stats_db_version' );
}

tslgd_run_uninstall();
