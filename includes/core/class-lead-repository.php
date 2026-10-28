<?php
/**
 * Data access for captured leads.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lead_Repository
 *
 * All queries use $wpdb->prepare()/insert(). The table name is built only from
 * $wpdb->prefix, never from user input.
 */
class Lead_Repository {

	/**
	 * DB schema version for this table.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Unqualified table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE = 'tslgd_leads';

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or upgrade the leads table via dbDelta.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		if ( get_option( 'tslgd_db_version' ) === self::DB_VERSION ) {
			return;
		}

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			gate_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name          VARCHAR(191)    NOT NULL DEFAULT '',
			email         VARCHAR(191)    NOT NULL DEFAULT '',
			phone         VARCHAR(50)     NOT NULL DEFAULT '',
			file_title    VARCHAR(191)    NOT NULL DEFAULT '',
			file_category VARCHAR(191)    NOT NULL DEFAULT '',
			file_url      TEXT            NOT NULL,
			extra         LONGTEXT        NULL,
			source_url    VARCHAR(255)    NOT NULL DEFAULT '',
			ip_hash       VARCHAR(64)     NOT NULL DEFAULT '',
			consent       TINYINT(1)      NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY gate_id (gate_id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'tslgd_db_version', self::DB_VERSION );
	}

	/**
	 * Insert a lead.
	 *
	 * @param array $data Sanitized lead data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$row = array(
			'gate_id'       => isset( $data['gate_id'] ) ? absint( $data['gate_id'] ) : 0,
			'name'          => isset( $data['name'] ) ? $data['name'] : '',
			'email'         => isset( $data['email'] ) ? $data['email'] : '',
			'phone'         => isset( $data['phone'] ) ? $data['phone'] : '',
			'file_title'    => isset( $data['file_title'] ) ? $data['file_title'] : '',
			'file_category' => isset( $data['file_category'] ) ? $data['file_category'] : '',
			'file_url'      => isset( $data['file_url'] ) ? $data['file_url'] : '',
			'extra'         => isset( $data['extra'] ) ? wp_json_encode( $data['extra'] ) : null,
			'source_url'    => isset( $data['source_url'] ) ? $data['source_url'] : '',
			'ip_hash'       => isset( $data['ip_hash'] ) ? $data['ip_hash'] : '',
			'consent'       => ! empty( $data['consent'] ) ? 1 : 0,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert( self::table(), $row, $formats );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Count total leads (optionally filtered by gate).
	 *
	 * @param int $gate_id Optional gate id filter.
	 * @return int
	 */
	public static function count( $gate_id = 0 ) {
		global $wpdb;
		$table = self::table();

		if ( $gate_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE gate_id = %d', $table, $gate_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Fetch a page of leads, newest first.
	 *
	 * @param int $per_page Items per page.
	 * @param int $offset   Offset.
	 * @return array
	 */
	public static function get_page( $per_page = 20, $offset = 0 ) {
		return self::query(
			array(
				'per_page' => $per_page,
				'offset'   => $offset,
			)
		);
	}

	/**
	 * Query leads with search, ordering, and pagination.
	 *
	 * @param array $args {
	 *     Optional query args.
	 *     @type string $search   Search term (name/email/phone/file_title).
	 *     @type string $orderby  Column to order by. Default 'created_at'.
	 *     @type string $order    ASC|DESC. Default 'DESC'.
	 *     @type int    $per_page Items per page. Default 20. Use -1 for all.
	 *     @type int    $offset   Offset. Default 0.
	 * }
	 * @return array
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = self::table();

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'gate_id'  => 0,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'offset'   => 0,
			)
		);

		$allowed_orderby = array( 'id', 'created_at', 'name', 'email', 'file_title' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Table name (%i) and order column (%i) are passed as identifiers to
		// prepare(); $order is a validated ASC|DESC literal.
		$clauses      = array();
		$where_params = array();
		if ( '' !== $args['search'] ) {
			$like           = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$clauses[]      = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR file_title LIKE %s)';
			$where_params[] = $like;
			$where_params[] = $like;
			$where_params[] = $like;
			$where_params[] = $like;
		}
		if ( (int) $args['gate_id'] > 0 ) {
			$clauses[]      = 'gate_id = %d';
			$where_params[] = (int) $args['gate_id'];
		}

		foreach ( self::extra_where( $args ) as $extra ) {
			$clauses[]    = $extra['sql'];
			$where_params = array_merge( $where_params, $extra['params'] );
		}

		$where_sql = $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';

		$limit_sql    = '';
		$limit_params = array();
		if ( (int) $args['per_page'] > 0 ) {
			$limit_sql    = ' LIMIT %d OFFSET %d';
			$limit_params = array( (int) $args['per_page'], (int) $args['offset'] );
		}

		$sql    = "SELECT * FROM %i{$where_sql} ORDER BY %i {$order}{$limit_sql}";
		$params = array_merge( array( $table ), $where_params, array( $orderby ), $limit_params );

		// Fully parameterized: table/column via %i identifiers, values via %s/%d; $order is a validated ASC|DESC literal.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Additional WHERE clauses contributed by add-ons.
	 *
	 * Each entry is [ 'sql' => string, 'params' => array ]. The `sql` must
	 * contain only fixed column names and %s/%d placeholders — every value has
	 * to travel in `params` so it is bound by prepare().
	 *
	 * @since 1.3.0
	 * @param array $args Query args.
	 * @return array<int,array{sql:string,params:array}>
	 */
	private static function extra_where( $args ) {
		/**
		 * Filter extra WHERE clauses for lead queries (Pro list filtering).
		 *
		 * @since 1.3.0
		 * @param array $clauses Array of [ 'sql' => string, 'params' => array ].
		 * @param array $args    Query args.
		 */
		$clauses = apply_filters( 'tslgd_leads_where', array(), $args );

		$out = array();
		foreach ( (array) $clauses as $clause ) {
			if ( ! empty( $clause['sql'] ) && is_string( $clause['sql'] ) ) {
				$out[] = array(
					'sql'    => $clause['sql'],
					'params' => isset( $clause['params'] ) ? array_values( (array) $clause['params'] ) : array(),
				);
			}
		}
		return $out;
	}

	/**
	 * Count leads matching an optional search term.
	 *
	 * @param string $search Search term.
	 * @return int
	 */
	public static function count_search( $search = '', $gate_id = 0 ) {
		global $wpdb;
		$table   = self::table();
		$gate_id = (int) $gate_id;

		$clauses = array();
		$params  = array( $table );

		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR file_title LIKE %s)';
			$params    = array_merge( $params, array( $like, $like, $like, $like ) );
		}
		if ( $gate_id > 0 ) {
			$clauses[] = 'gate_id = %d';
			$params[]  = $gate_id;
		}
		foreach ( self::extra_where(
			array(
				'search'  => $search,
				'gate_id' => $gate_id,
			)
		) as $extra ) {
			$clauses[] = $extra['sql'];
			$params    = array_merge( $params, $extra['params'] );
		}

		if ( ! $clauses ) {
			return self::count( $gate_id );
		}

		// Clauses are built only from string literals (fixed column names and %s/%d placeholders); every value is bound via prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ' . implode( ' AND ', $clauses ), $params ) );
	}

	/**
	 * Distinct forms (gates) that have captured at least one lead.
	 *
	 * @return array<int,array{gate_id:int,file_title:string,n:int}>
	 */
	public static function forms_with_leads() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT gate_id, file_title, COUNT(*) AS n FROM %i WHERE gate_id > 0 GROUP BY gate_id, file_title ORDER BY file_title ASC', $table ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Headline stats for the leads dashboard.
	 *
	 * @return array{total:int,last7:int,last30:int,top_file:string}
	 */
	public static function stats() {
		global $wpdb;
		$table = self::table();
		$d7    = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$d30   = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last7 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s', $table, $d7 ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last30 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s', $table, $d30 ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$top = (string) $wpdb->get_var( $wpdb->prepare( "SELECT file_title FROM %i WHERE file_title <> '' GROUP BY file_title ORDER BY COUNT(*) DESC LIMIT 1", $table ) );

		return array(
			'total'    => self::count(),
			'last7'    => $last7,
			'last30'   => $last30,
			'top_file' => $top,
		);
	}

	/**
	 * Delete leads by ID.
	 *
	 * @param int[] $ids Lead IDs.
	 * @return int Number of rows deleted.
	 */
	public static function delete( array $ids ) {
		global $wpdb;
		$table = self::table();

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "DELETE FROM %i WHERE id IN ({$placeholders})";
		$params       = array_merge( array( $table ), $ids );
		// Fully parameterized: table via %i identifier; every id is a %d placeholder ($placeholders is built only from array_fill).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->query( $wpdb->prepare( $sql, $params ) );
	}
}
