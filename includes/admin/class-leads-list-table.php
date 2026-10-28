<?php
/**
 * Leads list table (search, sort, paginate, bulk delete).
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Admin;

use TemplateSell\GatedDownloads\Core\Lead_Repository;
use TemplateSell\GatedDownloads\Core\Field_Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Leads_List_Table
 */
class Leads_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'lead',
				'plural'   => 'leads',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'            => '<input type="checkbox" />',
			'name'          => __( 'Name', 'templatesell-gated-downloads' ),
			'email'         => __( 'Email', 'templatesell-gated-downloads' ),
			'phone'         => __( 'Phone', 'templatesell-gated-downloads' ),
			'details'       => __( 'Details', 'templatesell-gated-downloads' ),
			'file_title'    => __( 'Form', 'templatesell-gated-downloads' ),
			'file_category' => __( 'Category', 'templatesell-gated-downloads' ),
			'created_at'    => __( 'Date', 'templatesell-gated-downloads' ),
		);

		/**
		 * Filter the Leads table columns (add-ons append their own).
		 *
		 * @since 1.3.0
		 * @param array $columns Column key => label.
		 */
		return apply_filters( 'tslgd_leads_columns', $columns );
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'       => array( 'name', false ),
			'email'      => array( 'email', false ),
			'file_title' => array( 'file_title', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'templatesell-gated-downloads' ) );
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lead[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * Default column renderer (escaped).
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		$value = isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';

		/**
		 * Filter the rendered content of a Leads table cell.
		 *
		 * Add-ons that register a column via 'tslgd_leads_columns' return their own
		 * markup here. The value passed in is already escaped, callbacks must
		 * escape their own output.
		 *
		 * @since 1.3.0
		 * @param string $value       Escaped cell value ('' for unknown columns).
		 * @param string $column_name Column key.
		 * @param array  $item        Lead row.
		 */
		return apply_filters( 'tslgd_leads_column_content', $value, $column_name, $item );
	}

	/**
	 * File column with a link to the download URL.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_file_title( $item ) {
		if ( ! empty( $item['file_url'] ) ) {
			return sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $item['file_url'] ),
				esc_html( $item['file_title'] )
			);
		}
		return esc_html( $item['file_title'] );
	}

	/**
	 * Details column: extra standard fields plus any Pro custom fields.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_details( $item ) {
		$extra = $this->decode_extra( $item );
		if ( empty( $extra ) ) {
			return '<span aria-hidden="true">—</span>';
		}

		$out = array();
		foreach ( $extra as $label => $value ) {
			$out[] = '<span class="tslgd-lead-extra"><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</span>';
		}
		return implode( '<br />', $out );
	}

	/**
	 * Decode a lead's `extra` JSON into a flat [ label => value ] map, resolving
	 * standard field slugs to their catalog labels.
	 *
	 * @param array $item Row.
	 * @return array
	 */
	private function decode_extra( $item ) {
		if ( empty( $item['extra'] ) ) {
			return array();
		}
		$extra = json_decode( (string) $item['extra'], true );
		if ( ! is_array( $extra ) ) {
			return array();
		}

		$flat = array();

		if ( isset( $extra['fields'] ) && is_array( $extra['fields'] ) ) {
			foreach ( $extra['fields'] as $key => $value ) {
				$def                 = Field_Catalog::get( $key );
				$label               = $def ? $def['label'] : ucwords( str_replace( '_', ' ', (string) $key ) );
				$flat[ $label ]      = is_scalar( $value ) ? (string) $value : '';
			}
		}

		// Pro custom fields are already stored keyed by their display label.
		if ( isset( $extra['custom_fields'] ) && is_array( $extra['custom_fields'] ) ) {
			foreach ( $extra['custom_fields'] as $label => $value ) {
				if ( is_scalar( $value ) ) {
					$flat[ (string) $label ] = (string) $value;
				}
			}
		}

		// Traffic attribution, shown last so it reads as metadata.
		if ( isset( $extra['utm'] ) && is_array( $extra['utm'] ) ) {
			foreach ( self::attribution_labels() as $key => $label ) {
				if ( ! empty( $extra['utm'][ $key ] ) ) {
					$flat[ $label ] = (string) $extra['utm'][ $key ];
				}
			}
		}

		return $flat;
	}

	/**
	 * Display labels for the captured attribution keys.
	 *
	 * @since 1.3.0
	 * @return array<string,string>
	 */
	public static function attribution_labels() {
		return array(
			'utm_source'   => __( 'Source', 'templatesell-gated-downloads' ),
			'utm_medium'   => __( 'Medium', 'templatesell-gated-downloads' ),
			'utm_campaign' => __( 'Campaign', 'templatesell-gated-downloads' ),
			'utm_term'     => __( 'Term', 'templatesell-gated-downloads' ),
			'utm_content'  => __( 'Ad content', 'templatesell-gated-downloads' ),
			'referrer'     => __( 'Referrer', 'templatesell-gated-downloads' ),
		);
	}

	/**
	 * Process a bulk delete action (nonce-checked).
	 *
	 * @return void
	 */
	public function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ids = isset( $_REQUEST['lead'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['lead'] ) ) : array();
		if ( ! empty( $ids ) ) {
			Lead_Repository::delete( $ids );
		}
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$per_page = 20;
		$current  = $this->get_pagenum();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$gate = isset( $_REQUEST['tslgd_gate'] ) ? absint( wp_unslash( $_REQUEST['tslgd_gate'] ) ) : 0;

		$total = Lead_Repository::count_search( $search, $gate );

		$this->items = Lead_Repository::query(
			array(
				'search'   => $search,
				'gate_id'  => $gate,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'offset'   => ( $current - 1 ) * $per_page,
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * "Filter by form" dropdown above the table.
	 *
	 * @param string $which Tablenav position (top|bottom).
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		/**
		 * Render extra controls above the leads table (add-on filters).
		 *
		 * Callbacks echo their own form controls; the surrounding <form> submits
		 * them with the table's other query args.
		 *
		 * @since 1.3.0
		 */
		do_action( 'tslgd_leads_tablenav' );

		$forms = Lead_Repository::forms_with_leads();
		if ( count( $forms ) < 2 ) {
			return; // Nothing to filter between.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_REQUEST['tslgd_gate'] ) ? absint( wp_unslash( $_REQUEST['tslgd_gate'] ) ) : 0;
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="tslgd_gate"><?php esc_html_e( 'Filter by form', 'templatesell-gated-downloads' ); ?></label>
			<select name="tslgd_gate" id="tslgd_gate">
				<option value="0"><?php esc_html_e( 'All forms', 'templatesell-gated-downloads' ); ?></option>
				<?php foreach ( $forms as $form ) : ?>
					<option value="<?php echo esc_attr( $form['gate_id'] ); ?>" <?php selected( $current, (int) $form['gate_id'] ); ?>>
						<?php echo esc_html( ( '' !== $form['file_title'] ? $form['file_title'] : __( '(untitled)', 'templatesell-gated-downloads' ) ) . ' (' . number_format_i18n( $form['n'] ) . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'templatesell-gated-downloads' ), 'button', 'tslgd-filter', false ); ?>
		</div>
		<?php
	}

	/**
	 * Empty-state message.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No leads yet. Records appear here after visitors submit a capture form.', 'templatesell-gated-downloads' );
	}
}
