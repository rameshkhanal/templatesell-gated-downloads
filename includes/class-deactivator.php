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
	 * Only flushes rewrite rules. User data is removed by uninstall.php.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
