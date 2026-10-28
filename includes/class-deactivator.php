<?php
/**
 * Fired on plugin deactivation.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * Note: deactivation must NOT delete user data — that belongs to uninstall.php
	 * and only when the user has opted in. Here we just flush rewrite rules.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
