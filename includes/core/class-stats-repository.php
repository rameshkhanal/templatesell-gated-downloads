<?php
/**
 * Conversion analytics storage: daily view/open/submit counters per gate.
 *
 * One aggregate row per gate per day, no per-visitor data. Views and opens
 * arrive via the public beacon endpoint, submits are counted from
 * `tslgd_lead_created`.
 *
 * @package Gated Downloads
 */

namespace TemplateSell\GatedDownloads\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Stats_Repository
 *
 * All queries use $wpdb->prepare(). The table name is built only from
 * $wpdb->prefix, never from user input.
 */
class Stats_Repository {

	/**
	 * DB schema version for this table.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.1.0';

	/**
	 * Unqualified table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE = 'tslgd_stats';

	/**
	 * Countable metrics (also the column names).
	 *
	 * @var string[]
	 */
	const METRICS = array( 'views', 'opens', 'submits', 'downloads' );

	/**
	 * Post meta holding a gate's cumulative, all-time download count.
	 *
	 * Kept alongside the daily counters so the front end can print a count
	 * without running an aggregate query on every page view.
	 *
	 * @var string
	 */
	const COUNT_META = '_tslgd_downloads';

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
	 * Register hooks: count a submit whenever a lead is stored.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'tslgd_lead_created', array( $this, 'count_submit' ), 10, 2 );
	}

	/**
	 * Count the submit for a newly created lead.
	 *
	 * @param int   $lead_id Lead ID (unused).
	 * @param array $data    Lead data.
	 * @return void
	 */
	public function count_submit( $lead_id, $data ) {
		unset( $lead_id );
		$gate_id = isset( $data['gate_id'] ) ? absint( $data['gate_id'] ) : 0;
		if ( $gate_id ) {
			self::record( $gate_id, 'submits' );
		}
	}

	/**
	 * Create or upgrade the stats table via dbDelta.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		if ( get_option( 'tslgd_stats_db_version' ) === self::DB_VERSION ) {
			return;
		}

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta adds the `downloads` column on upgrade and keeps existing history.
		$sql = "CREATE TABLE {$table} (
			gate_id   BIGINT UNSIGNED NOT NULL,
			stat_date DATE            NOT NULL,
			views     INT UNSIGNED    NOT NULL DEFAULT 0,
			opens     INT UNSIGNED    NOT NULL DEFAULT 0,
			submits   INT UNSIGNED    NOT NULL DEFAULT 0,
			downloads INT UNSIGNED    NOT NULL DEFAULT 0,
			PRIMARY KEY  (gate_id, stat_date),
			KEY stat_date (stat_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'tslgd_stats_db_version', self::DB_VERSION );
	}

	/**
	 * Increment one metric for a gate on today's row.
	 *
	 * @param int    $gate_id Gate (gated file) post ID.
	 * @param string $metric  One of views|opens|submits.
	 * @return void
	 */
	public static function record( $gate_id, $metric ) {
		global $wpdb;

		if ( ! in_array( $metric, self::METRICS, true ) ) {
			return;
		}
		$gate_id = absint( $gate_id );
		if ( ! $gate_id ) {
			return;
		}

		// One literal statement per metric, no dynamic SQL.
		$statements = array(
			'views'     => 'INSERT INTO %i (gate_id, stat_date, views) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE views = views + 1',
			'opens'     => 'INSERT INTO %i (gate_id, stat_date, opens) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE opens = opens + 1',
			'submits'   => 'INSERT INTO %i (gate_id, stat_date, submits) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE submits = submits + 1',
			'downloads' => 'INSERT INTO %i (gate_id, stat_date, downloads) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE downloads = downloads + 1',
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				$statements[ $metric ],
				self::table(),
				$gate_id,
				current_time( 'Y-m-d' )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		// Keep the cheap cumulative counter in step with the daily rows.
		if ( 'downloads' === $metric ) {
			update_post_meta( $gate_id, self::COUNT_META, self::download_count( $gate_id ) + 1 );
		}
	}

	/**
	 * All-time download count for a gate.
	 *
	 * @since 1.3.0
	 * @param int $gate_id Gate post ID.
	 * @return int
	 */
	public static function download_count( $gate_id ) {
		return (int) get_post_meta( absint( $gate_id ), self::COUNT_META, true );
	}

	/**
	 * Summed views/opens/submits over the last N days (all gates, or one).
	 *
	 * @param int $days    Window size in days (today inclusive).
	 * @param int $gate_id Optional gate to filter by.
	 * @return array {views:int, opens:int, submits:int}
	 */
	public static function totals( $days = 30, $gate_id = 0 ) {
		global $wpdb;

		$since = self::since( $days );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $gate_id ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT SUM(views) v, SUM(opens) o, SUM(submits) s, SUM(downloads) d FROM %i WHERE stat_date >= %s AND gate_id = %d',
					self::table(),
					$since,
					absint( $gate_id )
				),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT SUM(views) v, SUM(opens) o, SUM(submits) s, SUM(downloads) d FROM %i WHERE stat_date >= %s', self::table(), $since ),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'views'     => isset( $row['v'] ) ? (int) $row['v'] : 0,
			'opens'     => isset( $row['o'] ) ? (int) $row['o'] : 0,
			'submits'   => isset( $row['s'] ) ? (int) $row['s'] : 0,
			'downloads' => isset( $row['d'] ) ? (int) $row['d'] : 0,
		);
	}

	/**
	 * Per-gate totals over the last N days, keyed by gate_id.
	 *
	 * Used by the files-list columns; fetched once per request.
	 *
	 * @param int $days Window size in days.
	 * @return array<int, array{views:int, opens:int, submits:int}>
	 */
	public static function per_gate_totals( $days = 30 ) {
		global $wpdb;
		static $cache = array();

		if ( isset( $cache[ $days ] ) ) {
			return $cache[ $days ];
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT gate_id, SUM(views) v, SUM(opens) o, SUM(submits) s, SUM(downloads) d FROM %i WHERE stat_date >= %s GROUP BY gate_id',
				self::table(),
				self::since( $days )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['gate_id'] ] = array(
				'views'     => (int) $r['v'],
				'opens'     => (int) $r['o'],
				'submits'   => (int) $r['s'],
				'downloads' => (int) $r['d'],
			);
		}

		$cache[ $days ] = $out;
		return $out;
	}

	/**
	 * Daily totals over the last N days (all gates), oldest first, gap-filled.
	 *
	 * @param int $days Window size in days.
	 * @return array<string, array{views:int, opens:int, submits:int}> Keyed by Y-m-d.
	 */
	public static function daily( $days = 30 ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT stat_date, SUM(views) v, SUM(opens) o, SUM(submits) s, SUM(downloads) d FROM %i WHERE stat_date >= %s GROUP BY stat_date',
				self::table(),
				self::since( $days )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$by_date = array();
		foreach ( (array) $rows as $r ) {
			$by_date[ $r['stat_date'] ] = array(
				'views'     => (int) $r['v'],
				'opens'     => (int) $r['o'],
				'submits'   => (int) $r['s'],
				'downloads' => (int) $r['d'],
			);
		}

		// Gap-fill so charts get a continuous series.
		$out = array();
		$ts  = strtotime( self::since( $days ) . ' 00:00:00' );
		$end = strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' );
		while ( $ts <= $end ) {
			$key         = gmdate( 'Y-m-d', $ts );
			$out[ $key ] = isset( $by_date[ $key ] ) ? $by_date[ $key ] : array(
				'views'     => 0,
				'opens'     => 0,
				'submits'   => 0,
				'downloads' => 0,
			);
			$ts += DAY_IN_SECONDS;
		}
		return $out;
	}

	/**
	 * Conversion rate (submits / views) as a display string, or null when the
	 * sample is too small to be meaningful.
	 *
	 * @param array $totals Totals array (views/submits keys).
	 * @return string|null E.g. "4.2".
	 */
	public static function conversion( $totals ) {
		$views = isset( $totals['views'] ) ? (int) $totals['views'] : 0;
		if ( $views < 1 ) {
			return null;
		}
		$submits = isset( $totals['submits'] ) ? (int) $totals['submits'] : 0;
		return number_format_i18n( min( 100, ( $submits / $views ) * 100 ), 1 );
	}

	/**
	 * First day (Y-m-d, site timezone) of an N-day window ending today.
	 *
	 * @param int $days Window size in days.
	 * @return string
	 */
	private static function since( $days ) {
		$days = max( 1, absint( $days ) );
		return gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' 00:00:00' ) - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
	}
}
