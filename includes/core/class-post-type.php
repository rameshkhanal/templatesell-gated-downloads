<?php
/**
 * Registers the gated-file custom post type and its category taxonomy.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post_Type
 */
class Post_Type {

	/**
	 * Post type key.
	 *
	 * @var string
	 */
	const POST_TYPE = 'tslgd_file';

	/**
	 * Taxonomy key.
	 *
	 * @var string
	 */
	const TAXONOMY = 'tslgd_category';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the CPT and taxonomy.
	 *
	 * @return void
	 */
	public static function register() {
		self::register_taxonomy();
		self::register_post_type();
	}

	/**
	 * Register the gated file post type.
	 *
	 * @return void
	 */
	private static function register_post_type() {
		$labels = array(
			'name'               => _x( 'Gated Files', 'post type general name', 'templatesell-gated-downloads' ),
			'singular_name'      => _x( 'Gated File', 'post type singular name', 'templatesell-gated-downloads' ),
			'menu_name'          => _x( 'Gated Downloads', 'admin menu', 'templatesell-gated-downloads' ),
			'add_new'            => __( 'Add New', 'templatesell-gated-downloads' ),
			'add_new_item'       => __( 'Add New Gated File', 'templatesell-gated-downloads' ),
			'edit_item'          => __( 'Edit Gated File', 'templatesell-gated-downloads' ),
			'new_item'           => __( 'New Gated File', 'templatesell-gated-downloads' ),
			'view_item'          => __( 'View Gated File', 'templatesell-gated-downloads' ),
			'search_items'       => __( 'Search Gated Files', 'templatesell-gated-downloads' ),
			'not_found'          => __( 'No gated files found.', 'templatesell-gated-downloads' ),
			'not_found_in_trash' => __( 'No gated files found in Trash.', 'templatesell-gated-downloads' ),
			'all_items'          => __( 'Gated Files', 'templatesell-gated-downloads' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-download',
			'menu_position'       => 30,
			'capability_type'     => 'post',
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_in_rest'        => true,
			'taxonomies'          => array( self::TAXONOMY ),
		);

		/**
		 * Filter the gated-file post type arguments.
		 *
		 * @since 1.0.0
		 * @param array $args Registration args.
		 */
		$args = apply_filters( 'tslgd_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register the generic, user-defined category taxonomy.
	 *
	 * No terms are hardcoded — site owners create whatever categories fit their
	 * content (e.g. Ebooks, Whitepapers, Price Lists, Brochures, Resources…).
	 *
	 * @return void
	 */
	private static function register_taxonomy() {
		$labels = array(
			'name'          => _x( 'Categories', 'taxonomy general name', 'templatesell-gated-downloads' ),
			'singular_name' => _x( 'Category', 'taxonomy singular name', 'templatesell-gated-downloads' ),
			'search_items'  => __( 'Search Categories', 'templatesell-gated-downloads' ),
			'all_items'     => __( 'All Categories', 'templatesell-gated-downloads' ),
			'edit_item'     => __( 'Edit Category', 'templatesell-gated-downloads' ),
			'update_item'   => __( 'Update Category', 'templatesell-gated-downloads' ),
			'add_new_item'  => __( 'Add New Category', 'templatesell-gated-downloads' ),
			'new_item_name' => __( 'New Category Name', 'templatesell-gated-downloads' ),
			'menu_name'     => __( 'Categories', 'templatesell-gated-downloads' ),
		);

		$args = array(
			'labels'            => $labels,
			'public'            => false,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => false,
		);

		/**
		 * Filter the category taxonomy arguments.
		 *
		 * @since 1.0.0
		 * @param array $args Registration args.
		 */
		$args = apply_filters( 'tslgd_taxonomy_args', $args );

		register_taxonomy( self::TAXONOMY, array( self::POST_TYPE ), $args );
	}
}
