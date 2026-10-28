<?php
/**
 * Row actions for gated files (Duplicate).
 *
 * @package TemplateSell\GatedDownloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class File_Actions
 */
class File_Actions {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'post_row_actions', array( $this, 'add_duplicate_action' ), 10, 2 );
		add_action( 'admin_action_tslgd_clone', array( $this, 'handle_clone' ) );
	}

	/**
	 * Add a "Duplicate" row action to gated files.
	 *
	 * @param array<string,string> $actions Existing row actions.
	 * @param \WP_Post              $post    Post object.
	 * @return array<string,string>
	 */
	public function add_duplicate_action( $actions, $post ) {
		if ( Post_Type::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			admin_url( 'admin.php?action=tslgd_clone&post=' . (int) $post->ID ),
			'tslgd_clone_' . (int) $post->ID
		);
		$actions['tslgd_clone'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'templatesell-gated-downloads' ) . '</a>';
		return $actions;
	}

	/**
	 * Duplicate a gated file (draft copy with all settings), then open it.
	 *
	 * @return void
	 */
	public function handle_clone() {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		check_admin_referer( 'tslgd_clone_' . $post_id );

		$source = $post_id ? get_post( $post_id ) : null;
		if ( ! $source || Post_Type::POST_TYPE !== $source->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You cannot duplicate this item.', 'templatesell-gated-downloads' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => Post_Type::POST_TYPE,
				'post_status'  => 'draft',
				/* translators: %s: original gated-file title. */
				'post_title'   => sprintf( __( '%s (copy)', 'templatesell-gated-downloads' ), $source->post_title ),
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
			),
			true
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			wp_die( esc_html__( 'Could not duplicate this gated file.', 'templatesell-gated-downloads' ) );
		}

		// Copy every plugin meta value.
		$meta = get_post_meta( $post_id );
		foreach ( $meta as $key => $values ) {
			if ( 0 !== strpos( $key, '_tslgd' ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		// Copy taxonomy terms.
		$terms = wp_get_object_terms( $post_id, Post_Type::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			wp_set_object_terms( $new_id, $terms, Post_Type::TAXONOMY );
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . (int) $new_id ) );
		exit;
	}
}
