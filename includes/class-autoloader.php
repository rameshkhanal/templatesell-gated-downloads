<?php
/**
 * PSR-4-ish autoloader mapping the Gated Downloads\ namespace to includes/.
 *
 * Namespace segments map to lower-cased sub-directories, class names to
 * WordPress-standard file names, e.g. Gated Downloads\Core\Lead_Repository
 * becomes includes/core/class-lead-repository.php
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Namespace prefix this autoloader handles.
	 *
	 * @var string
	 */
	private const PREFIX = 'TemplateSell\\GatedDownloads\\';

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Autoload a class within the Gated Downloads namespace.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	public static function load( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );

		$path = TSLGD_PATH . 'includes/';
		foreach ( $parts as $segment ) {
			$path .= strtolower( $segment ) . '/';
		}

		$file = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

		$full = $path . $file;
		if ( is_readable( $full ) ) {
			require_once $full;
		}
	}
}
