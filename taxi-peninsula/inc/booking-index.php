<?php
/**
 * Fast trip index for reports. Every booking gets one row in {prefix}tp_trips
 * (date, status, payment, fare, driver, vehicle, customer, partner…), kept in
 * step whenever a booking's details change. Reports, the finance list and
 * statements read this table instead of joining thousands of post-meta rows,
 * so they stay quick as bookings build up.
 *
 * The table is filled in the background the first time (500 bookings per
 * admin page load) and only used once it is complete.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_TRIPS_DB = 1;

function tp_trips_table() {
	global $wpdb;
	return $wpdb->prefix . 'tp_trips';
}

/**
 * Meta keys copied into the index: meta key => column.
 */
function tp_trips_columns() {
	return array(
		'_tp_date'           => 'date',
		'_tp_time'           => 'time',
		'_tp_status'         => 'status',
		'_tp_payment'        => 'payment',
		'_tp_invoice_status' => 'invoice_status',
		'_tp_fare'           => 'fare',
		'_tp_paid'           => 'paid',
		'_tp_driver_id'      => 'driver_id',
		'_tp_fleet_id'       => 'fleet_id',
		'_tp_customer_id'    => 'customer_id',
		'_tp_venue_id'       => 'venue_id',
		'_tp_source'         => 'source',
		'_tp_invoice_email'  => 'invoice_email',
	);
}

add_action(
	'init',
	static function () {
		if ( (int) get_option( 'tp_trips_db' ) === TP_TRIPS_DB ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = tp_trips_table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				post_id bigint(20) unsigned NOT NULL,
				date date NULL,
				time varchar(5) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT '',
				payment varchar(20) NOT NULL DEFAULT '',
				invoice_status varchar(20) NOT NULL DEFAULT '',
				fare decimal(10,2) NULL,
				paid decimal(10,2) NOT NULL DEFAULT 0,
				driver_id bigint(20) unsigned NOT NULL DEFAULT 0,
				fleet_id bigint(20) unsigned NOT NULL DEFAULT 0,
				customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				venue_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source varchar(60) NOT NULL DEFAULT '',
				invoice_email varchar(190) NOT NULL DEFAULT '',
				PRIMARY KEY  (post_id),
				KEY date (date),
				KEY status_date (status,date),
				KEY driver_date (driver_id,date),
				KEY customer_id (customer_id),
				KEY venue_id (venue_id),
				KEY invoice_email (invoice_email)
			) {$charset};"
		);
		update_option( 'tp_trips_db', TP_TRIPS_DB, false );
		delete_option( 'tp_trips_ready' );
		update_option( 'tp_trips_backfill', 0, false );
	},
	6
);

/**
 * Write one booking's row (or remove it).
 */
function tp_trips_sync( $post_id ) {
	global $wpdb;
	$post = get_post( $post_id );
	if ( ! $post || 'tp_booking' !== $post->post_type || 'publish' !== $post->post_status ) {
		$wpdb->delete( tp_trips_table(), array( 'post_id' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return;
	}
	$row = array( 'post_id' => (int) $post_id );
	foreach ( tp_trips_columns() as $key => $col ) {
		$v = get_post_meta( $post_id, $key, true );
		if ( 'date' === $col ) {
			$v = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? $v : null;
		} elseif ( 'fare' === $col ) {
			$v = '' === (string) $v ? null : (float) $v;
		} elseif ( in_array( $col, array( 'paid' ), true ) ) {
			$v = (float) $v;
		} elseif ( in_array( $col, array( 'driver_id', 'fleet_id', 'customer_id', 'venue_id' ), true ) ) {
			$v = (int) $v;
		} else {
			$v = mb_substr( strtolower( 'invoice_email' === $col ? trim( (string) $v ) : (string) $v ), 0, 190 );
			if ( 'status' === $col && '' === $v ) {
				$v = 'pending';
			}
			if ( 'payment' === $col && '' === $v ) {
				$v = 'driver';
			}
		}
		$row[ $col ] = $v;
	}
	$wpdb->replace( tp_trips_table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Collect changed bookings and rewrite their rows once, at the end of the request.
function tp_trips_queue( $post_id = null ) {
	static $queue = array();
	static $hooked = false;
	if ( null === $post_id ) {
		$out   = $queue;
		$queue = array();
		return $out;
	}
	$queue[ (int) $post_id ] = true;
	if ( ! $hooked ) {
		$hooked = true;
		add_action(
			'shutdown',
			static function () {
				foreach ( array_keys( tp_trips_queue() ) as $id ) {
					tp_trips_sync( $id );
				}
			},
			5
		);
	}
}

$tp_trips_meta_hook = static function ( $meta_id, $post_id, $key ) {
	if ( isset( tp_trips_columns()[ $key ] ) && 'tp_booking' === get_post_type( $post_id ) ) {
		tp_trips_queue( $post_id );
	}
};
add_action( 'added_post_meta', $tp_trips_meta_hook, 10, 3 );
add_action( 'updated_post_meta', $tp_trips_meta_hook, 10, 3 );
add_action( 'deleted_post_meta', static function ( $ids, $post_id, $key ) use ( $tp_trips_meta_hook ) { $tp_trips_meta_hook( 0, $post_id, $key ); }, 10, 3 );
add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( 'tp_booking' === $post->post_type && $new !== $old ) {
			tp_trips_queue( $post->ID );
		}
	},
	10,
	3
);
add_action(
	'deleted_post',
	static function ( $post_id ) {
		global $wpdb;
		$wpdb->delete( tp_trips_table(), array( 'post_id' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
);

/**
 * Fill the index for existing bookings, a batch at a time.
 */
function tp_trips_backfill( $batch = 500 ) {
	if ( get_option( 'tp_trips_ready' ) ) {
		return true;
	}
	global $wpdb;
	$after = (int) get_option( 'tp_trips_backfill', 0 );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tp_booking' AND ID > %d ORDER BY ID ASC LIMIT %d", $after, $batch ) );
	foreach ( $ids as $id ) {
		tp_trips_sync( (int) $id );
	}
	if ( count( $ids ) < $batch ) {
		update_option( 'tp_trips_ready', 1, false );
		return true;
	}
	update_option( 'tp_trips_backfill', (int) end( $ids ), false );
	return false;
}
add_action(
	'admin_init',
	static function () {
		if ( ! get_option( 'tp_trips_ready' ) && (int) get_option( 'tp_trips_db' ) === TP_TRIPS_DB ) {
			tp_trips_backfill();
		}
	}
);

function tp_trips_ready() {
	return (bool) get_option( 'tp_trips_ready' ) && (int) get_option( 'tp_trips_db' ) === TP_TRIPS_DB;
}

/**
 * Booking IDs from the index.
 *
 * @param array $where Column => value (array for IN). Special: 'from', 'to' (dates), 'paid_gt' (number).
 * @param bool  $or_paid With 'status': also include rows with a payment.
 * @return int[]|null Null when the index is not ready.
 */
function tp_trips_ids( array $where, $or_paid = false ) {
	if ( ! tp_trips_ready() ) {
		return null;
	}
	global $wpdb;
	$sql  = array( '1=1' );
	$args = array();
	if ( ! empty( $where['from'] ) ) {
		$sql[]  = 'date >= %s';
		$args[] = $where['from'];
	}
	if ( ! empty( $where['to'] ) ) {
		$sql[]  = 'date <= %s';
		$args[] = $where['to'];
	}
	unset( $where['from'], $where['to'] );
	$status_sql = '';
	foreach ( $where as $col => $val ) {
		if ( ! in_array( $col, tp_trips_columns(), true ) ) {
			continue;
		}
		if ( is_array( $val ) ) {
			$part = $col . ' IN (' . implode( ',', array_fill( 0, count( $val ), '%s' ) ) . ')';
			$vals = array_values( $val );
		} else {
			$part = $col . ' = %s';
			$vals = array( $val );
		}
		if ( 'status' === $col && $or_paid ) {
			$status_sql = '(' . $part . ' OR paid > 0)';
			$args       = array_merge( $args, $vals );
			$sql[]      = $status_sql;
			continue;
		}
		$sql[] = $part;
		$args  = array_merge( $args, $vals );
	}
	$query = 'SELECT post_id FROM ' . tp_trips_table() . ' WHERE ' . implode( ' AND ', $sql ) . ' ORDER BY date ASC, time ASC LIMIT 5000';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- built from fixed column names and placeholders.
	return array_map( 'intval', $wpdb->get_col( $args ? $wpdb->prepare( $query, $args ) : $query ) );
}

/**
 * Rows for the owner dashboard, or null when the index is not ready.
 */
function tp_trips_owner_rows( $from, $to ) {
	if ( ! tp_trips_ready() ) {
		return null;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $wpdb->get_results( $wpdb->prepare( 'SELECT post_id id, date, time, status, fare, venue_id venue, source FROM ' . tp_trips_table() . ' WHERE date BETWEEN %s AND %s', $from, $to ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
