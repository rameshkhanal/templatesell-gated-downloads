<?php
/**
 * Standard form-field catalog.
 *
 * Defines the fixed set of capture fields the free plugin can place on a form
 * (per-form, in any order). Each field declares its input type, label,
 * autocomplete token, and where its value is stored: a dedicated leads-table
 * column ("column") or the shared JSON `extra` payload ("extra").
 *
 * Pro extends this via the `tslgd_field_catalog` filter to register
 * additional field types (dropdown, textarea, checkbox, …).
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Field_Catalog
 */
class Field_Catalog {

	/**
	 * The standard field definitions, keyed by field slug.
	 *
	 * @return array
	 */
	public static function all() {
		$fields = array(
			'name'      => array(
				'type'         => 'text',
				'label'        => __( 'Full Name', 'templatesell-gated-downloads' ),
				'autocomplete' => 'name',
				'placeholder'  => '',
				'store'        => 'column',
			),
			'email'     => array(
				'type'         => 'email',
				'label'        => __( 'Email Address', 'templatesell-gated-downloads' ),
				'autocomplete' => 'email',
				'placeholder'  => '',
				'store'        => 'column',
				'locked'       => true, // Always present — a lead needs an address.
			),
			'phone'     => array(
				'type'         => 'tel',
				'label'        => __( 'Phone Number', 'templatesell-gated-downloads' ),
				'autocomplete' => 'tel',
				'placeholder'  => __( 'Your phone number', 'templatesell-gated-downloads' ),
				'store'        => 'column',
			),
			'company'   => array(
				'type'         => 'text',
				'label'        => __( 'Company', 'templatesell-gated-downloads' ),
				'autocomplete' => 'organization',
				'placeholder'  => '',
				'store'        => 'extra',
			),
			'job_title' => array(
				'type'         => 'text',
				'label'        => __( 'Job Title', 'templatesell-gated-downloads' ),
				'autocomplete' => 'organization-title',
				'placeholder'  => '',
				'store'        => 'extra',
			),
			'website'   => array(
				'type'         => 'url',
				'label'        => __( 'Website', 'templatesell-gated-downloads' ),
				'autocomplete' => 'url',
				'placeholder'  => 'https://',
				'store'        => 'extra',
			),
			'country'   => array(
				'type'         => 'text',
				'label'        => __( 'Country', 'templatesell-gated-downloads' ),
				'autocomplete' => 'country-name',
				'placeholder'  => '',
				'store'        => 'extra',
			),
		);

		/**
		 * Filter the standard field catalog.
		 *
		 * @since 1.1.0
		 * @param array $fields Field definitions keyed by slug.
		 */
		return apply_filters( 'tslgd_field_catalog', $fields );
	}

	/**
	 * A single field definition, or null if unknown.
	 *
	 * @param string $key Field slug.
	 * @return array|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Whether a field exists in the catalog.
	 *
	 * @param string $key Field slug.
	 * @return bool
	 */
	public static function exists( $key ) {
		return null !== self::get( $key );
	}

	/**
	 * Whether a field stores its value in a dedicated leads-table column.
	 *
	 * @param string $key Field slug.
	 * @return bool
	 */
	public static function is_column( $key ) {
		$def = self::get( $key );
		return $def && isset( $def['store'] ) && 'column' === $def['store'];
	}

	/**
	 * The default field order (catalog order).
	 *
	 * @return string[]
	 */
	public static function default_order() {
		return array_keys( self::all() );
	}

	/**
	 * The HTML input name for a field.
	 *
	 * Column fields post as their bare slug (name/email/phone) for backward
	 * compatibility; extra fields are namespaced under tslgd_field[…].
	 *
	 * @param string $key Field slug.
	 * @return string
	 */
	public static function input_name( $key ) {
		return self::is_column( $key ) ? $key : 'tslgd_field[' . $key . ']';
	}
}
