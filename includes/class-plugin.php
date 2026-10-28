<?php
/**
 * Main plugin orchestrator.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads;

use TemplateSell\GatedDownloads\Core\Post_Type;
use TemplateSell\GatedDownloads\Admin\Admin;
use TemplateSell\GatedDownloads\Frontend\Frontend;
use TemplateSell\GatedDownloads\Frontend\Locker;
use TemplateSell\GatedDownloads\Frontend\Auto_Insert;
use TemplateSell\GatedDownloads\Frontend\Elementor;
use TemplateSell\GatedDownloads\Frontend\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Singleton that wires every service together and boots the plugin.
 */
final class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether run() has already executed.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Boot all services.
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Core.
		( new Post_Type() )->register_hooks();
		( new Core\Stats_Repository() )->register_hooks();
		( new Core\Lead_Mailer() )->register_hooks();

		// Shared assets registration.
		( new Assets() )->register_hooks();

		// Editor block (registers in both admin and front-end contexts).
		( new Block() )->register_hooks();

		// Elementor widget — the hook only ever fires when Elementor is active.
		( new Elementor() )->register_hooks();

		// Admin only.
		if ( is_admin() ) {
			( new Admin() )->register_hooks();
		}

		// Front-end only (REST requests also run this branch, which the
		// Locker's unlock endpoint relies on).
		if ( ! is_admin() ) {
			$frontend = new Frontend();
			$frontend->register_hooks();
			( new Locker( $frontend ) )->register_hooks();
			( new Auto_Insert( $frontend ) )->register_hooks();
		}

		// Run any pending upgrade routines.
		$this->maybe_upgrade();

		/**
		 * Fires after Gated Downloads has booted. Pro and third parties hook here.
		 *
		 * @since 1.0.0
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'tslgd_init', $this );
	}

	/**
	 * Run DB/version upgrade routines when the stored version differs.
	 *
	 * @return void
	 */
	private function maybe_upgrade() {
		if ( get_option( 'tslgd_version' ) === TSLGD_VERSION ) {
			return;
		}
		Activator::activate();
		update_option( 'tslgd_version', TSLGD_VERSION );
	}
}
