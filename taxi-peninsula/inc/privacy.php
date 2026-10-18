<?php
/**
 * Privacy tools (Australian Privacy Principles).
 *
 * - Plugs bookings, account profiles and messages into WordPress's own
 *   Tools → Export / Erase Personal Data, so requests are confirmed by email
 *   and handled in one place.
 * - Customers can download their data or delete their account from My
 *   Account; anyone can request a copy or deletion with [tp_privacy_request].
 * - A daily clean-up removes personal details from old trips, deletes old
 *   messages and trims the activity log (Bookings → Settings → Privacy).
 * - [tp_privacy_policy] writes a privacy policy that matches the settings.
 *
 * Erasing never deletes the trip itself: the reference, date, suburbs, fare
 * and payment stay, and account invoice details are kept for 5 years (ATO).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_privacy_email() {
	return tp_setting( 'privacy_email' ) ?: tp_opt( 'email' );
}

function tp_privacy_contact() {
	return tp_setting( 'privacy_contact' ) ?: get_bloginfo( 'name' );
}

/**
 * Bookings that belong to an email address (passenger, invoice contact or account).
 *
 * @param string $email   Email.
 * @param int    $user_id Account ID, if any.
 * @return int[]
 */
function tp_privacy_booking_ids( $email, $user_id = 0 ) {
	if ( ! is_email( $email ) && ! $user_id ) {
		return array();
	}
	$meta = array( 'relation' => 'OR' );
	if ( is_email( $email ) ) {
		$meta[] = array( 'key' => '_tp_email', 'value' => $email );
		$meta[] = array( 'key' => '_tp_invoice_email', 'value' => $email );
	}
	if ( $user_id ) {
		$meta[] = array( 'key' => '_tp_customer_id', 'value' => (int) $user_id );
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'   => 'tp_booking',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		)
	);
}

/**
 * Upcoming, still-active trip? These are kept until the trip is over.
 */
function tp_privacy_is_upcoming( $post_id ) {
	$status = get_post_meta( $post_id, '_tp_status', true );
	$at     = get_post_meta( $post_id, '_tp_pickup_at', true );
	return in_array( $status, tp_active_statuses(), true ) && $at >= wp_date( 'Y-m-d H:i', time() - DAY_IN_SECONDS );
}

/**
 * Everything we hold about one booking, as name/value pairs.
 */
function tp_privacy_booking_data( $post_id ) {
	$b      = tp_get_booking( $post_id );
	$fields = tp_booking_fields();
	$data   = array(
		array( 'name' => __( 'Reference', 'taxi-peninsula' ), 'value' => $b['reference'] ),
		array( 'name' => __( 'Status', 'taxi-peninsula' ), 'value' => tp_statuses()[ $b['status'] ] ?? $b['status'] ),
	);
	foreach ( $fields as $key => $field ) {
		if ( '' === (string) $b[ $key ] || ( 'checkbox' === $field[1] && ! $b[ $key ] ) ) {
			continue;
		}
		$data[] = array( 'name' => $field[0], 'value' => tp_activity_value( '_tp_' . $key, $b[ $key ] ) );
	}
	if ( '' !== (string) $b['fare'] ) {
		$data[] = array( 'name' => __( 'Fare', 'taxi-peninsula' ), 'value' => tp_money( $b['fare'] ) );
	}
	if ( $b['paid'] ) {
		$data[] = array( 'name' => __( 'Paid online', 'taxi-peninsula' ), 'value' => tp_money( $b['paid'] ) );
	}
	if ( $b['rating'] ) {
		$data[] = array( 'name' => __( 'Your rating', 'taxi-peninsula' ), 'value' => $b['rating'] . '★ ' . get_post_meta( $post_id, '_tp_rating_comment', true ) );
	}
	if ( $b['request'] ) {
		$data[] = array( 'name' => __( 'Change request', 'taxi-peninsula' ), 'value' => is_array( $b['request'] ) ? implode( ' — ', array_map( 'strval', array_filter( $b['request'], 'is_scalar' ) ) ) : (string) $b['request'] );
	}
	$consent = get_post_meta( $post_id, '_tp_consent_at', true );
	if ( $consent ) {
		$data[] = array( 'name' => __( 'Consent given', 'taxi-peninsula' ), 'value' => $consent );
	}
	if ( $b['driver_id'] && ( $driver = get_userdata( $b['driver_id'] ) ) ) {
		$data[] = array( 'name' => __( 'Driver', 'taxi-peninsula' ), 'value' => $driver->first_name ?: $driver->display_name );
	}
	return $data;
}

/**
 * Keep only "Suburb STATE postcode" from an address.
 */
function tp_privacy_suburb_only( $address ) {
	$address = trim( (string) $address );
	if ( '' === $address ) {
		return '';
	}
	$parts = array_map( 'trim', explode( ',', $address ) );
	$last  = end( $parts );
	if ( count( $parts ) > 1 && preg_match( '/\b(VIC|NSW|QLD|SA|WA|TAS|NT|ACT)\b/i', $last ) ) {
		return $last;
	}
	if ( preg_match( '/([A-Za-z][A-Za-z \'-]+\s(?:VIC|NSW|QLD|SA|WA|TAS|NT|ACT)\s?\d{0,4})\s*$/i', $address, $m ) ) {
		// Drop any street part: everything up to the last street-type word.
		return trim( preg_replace( '/^.*\b(St|Street|Rd|Road|Ave|Avenue|Ct|Court|Dr|Drive|Pde|Parade|Hwy|Highway|Cres|Crescent|Pl|Place|Way|Lane|Ln|Bvd|Boulevard|Cl|Close|Gr|Grove|Tce|Terrace)\b\.?\s+/i', '', $m[1] ) );
	}
	return __( '(removed)', 'taxi-peninsula' );
}

/**
 * Remove personal details from one booking, keeping what the business must
 * keep (reference, date, suburbs, fare, payment, invoice details for 5 years).
 *
 * @param int    $post_id Booking ID.
 * @param string $reason  Why (shown in the activity log).
 * @return bool True when anything was removed.
 */
function tp_privacy_anonymise_booking( $post_id, $reason ) {
	if ( 'tp_booking' !== get_post_type( $post_id ) ) {
		return false;
	}
	tp_activity_quiet( true );
	$b           = tp_get_booking( $post_id );
	$at          = get_post_meta( $post_id, '_tp_pickup_at', true );
	$tax_cutoff  = wp_date( 'Y-m-d', strtotime( '-5 years' ) );
	$keep_tax    = 'account' === $b['payment'] && $at >= $tax_cutoff;
	$changes     = array(
		'_tp_name'           => __( '(removed)', 'taxi-peninsula' ),
		'_tp_phone'          => '',
		'_tp_email'          => '',
		'_tp_pickup'         => tp_privacy_suburb_only( $b['pickup'] ),
		'_tp_dropoff'        => tp_privacy_suburb_only( $b['dropoff'] ),
		'_tp_notes'          => '',
		'_tp_admin_notes'    => '',
		'_tp_rating_comment' => '',
	);
	if ( ! $keep_tax ) {
		$changes['_tp_invoice_name']  = '';
		$changes['_tp_invoice_email'] = '';
		$changes['_tp_ndis_number']   = '';
	}
	foreach ( $changes as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}
	delete_post_meta( $post_id, '_tp_request' );

	// Keep the status history, drop free-text notes (they can hold names or comments).
	$log = array_values(
		array_filter(
			(array) get_post_meta( $post_id, '_tp_log', true ),
			static function ( $e ) {
				return is_array( $e ) && ! empty( $e['to'] );
			}
		)
	);
	$log[] = array(
		'time'   => current_time( 'mysql' ),
		'user'   => '',
		'from'   => '',
		'to'     => '',
		'note'   => __( 'Personal details removed', 'taxi-peninsula' ),
		'notify' => false,
	);
	update_post_meta( $post_id, '_tp_log', $log );
	update_post_meta( $post_id, '_tp_anonymised', current_time( 'mysql' ) );

	global $wpdb;
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		tp_activity_table(),
		array( 'summary' => __( '(details removed for privacy)', 'taxi-peninsula' ) ),
		array( 'booking_id' => $post_id ),
		array( '%s' ),
		array( '%d' )
	);

	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_title'   => $b['reference'] . ' — ' . __( '(removed)', 'taxi-peninsula' ),
			'post_excerpt' => '',
		)
	);
	tp_activity_quiet( false );
	tp_activity( 'privacy', $reason, $post_id );
	return true;
}

/* -------------------------------------------------------------------------
 * Tools → Export / Erase Personal Data.
 * ---------------------------------------------------------------------- */

add_filter(
	'wp_privacy_personal_data_exporters',
	static function ( $exporters ) {
		$exporters['taxi-peninsula-bookings'] = array(
			'exporter_friendly_name' => __( 'Taxi bookings', 'taxi-peninsula' ),
			'callback'               => 'tp_privacy_export_bookings',
		);
		$exporters['taxi-peninsula-account']  = array(
			'exporter_friendly_name' => __( 'Taxi account: passengers, clients and addresses', 'taxi-peninsula' ),
			'callback'               => 'tp_privacy_export_account',
		);
		$exporters['taxi-peninsula-messages'] = array(
			'exporter_friendly_name' => __( 'Messages and driver applications', 'taxi-peninsula' ),
			'callback'               => 'tp_privacy_export_messages',
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	static function ( $erasers ) {
		$erasers['taxi-peninsula-bookings'] = array(
			'eraser_friendly_name' => __( 'Taxi bookings', 'taxi-peninsula' ),
			'callback'             => 'tp_privacy_erase_bookings',
		);
		$erasers['taxi-peninsula-account']  = array(
			'eraser_friendly_name' => __( 'Taxi account: passengers, clients and addresses', 'taxi-peninsula' ),
			'callback'             => 'tp_privacy_erase_account',
		);
		$erasers['taxi-peninsula-messages'] = array(
			'eraser_friendly_name' => __( 'Messages and driver applications', 'taxi-peninsula' ),
			'callback'             => 'tp_privacy_erase_messages',
		);
		return $erasers;
	}
);

function tp_privacy_user_id( $email ) {
	$user = get_user_by( 'email', $email );
	return $user ? (int) $user->ID : 0;
}

function tp_privacy_export_bookings( $email, $page = 1 ) {
	$ids   = tp_privacy_booking_ids( $email, tp_privacy_user_id( $email ) );
	$per   = 50;
	$chunk = array_slice( $ids, ( $page - 1 ) * $per, $per );
	$items = array();
	foreach ( $chunk as $id ) {
		$items[] = array(
			'group_id'          => 'tp-bookings',
			'group_label'       => __( 'Taxi bookings', 'taxi-peninsula' ),
			'group_description' => __( 'Trips booked by you, for you, or invoiced to you.', 'taxi-peninsula' ),
			'item_id'           => 'tp-booking-' . $id,
			'data'              => tp_privacy_booking_data( $id ),
		);
	}
	return array(
		'data' => $items,
		'done' => count( $ids ) <= $page * $per,
	);
}

function tp_privacy_export_account( $email, $page = 1 ) {
	$user_id = tp_privacy_user_id( $email );
	$items   = array();
	if ( $user_id ) {
		$details = array();
		foreach ( array( 'tp_mobile' => __( 'Mobile', 'taxi-peninsula' ), 'tp_org' => __( 'Organisation', 'taxi-peninsula' ), 'tp_invoice_name' => __( 'Send invoices to', 'taxi-peninsula' ), 'tp_invoice_email' => __( 'Invoice email', 'taxi-peninsula' ) ) as $key => $label ) {
			$value = get_user_meta( $user_id, $key, true );
			if ( '' !== (string) $value ) {
				$details[] = array( 'name' => $label, 'value' => $value );
			}
		}
		if ( $details ) {
			$items[] = array(
				'group_id'    => 'tp-account',
				'group_label' => __( 'Taxi account details', 'taxi-peninsula' ),
				'item_id'     => 'tp-account-' . $user_id,
				'data'        => $details,
			);
		}
		$fields = tp_passenger_fields();
		foreach ( tp_passengers( $user_id ) as $pid => $p ) {
			$data = array();
			foreach ( $fields as $key => $field ) {
				if ( ! empty( $p[ $key ] ) ) {
					$data[] = array( 'name' => $field[0], 'value' => 'aid' === $key ? ( tp_mobility_aids()[ $p[ $key ] ] ?? $p[ $key ] ) : $p[ $key ] );
				}
			}
			$items[] = array(
				'group_id'    => 'tp-passengers',
				'group_label' => __( 'Saved passengers and clients', 'taxi-peninsula' ),
				'item_id'     => 'tp-passenger-' . $pid,
				'data'        => $data,
			);
		}
		foreach ( tp_saved_addresses( $user_id ) as $aid => $a ) {
			$items[] = array(
				'group_id'    => 'tp-addresses',
				'group_label' => __( 'Saved addresses', 'taxi-peninsula' ),
				'item_id'     => 'tp-address-' . $aid,
				'data'        => array(
					array( 'name' => __( 'Label', 'taxi-peninsula' ), 'value' => $a['label'] ?? '' ),
					array( 'name' => __( 'Address', 'taxi-peninsula' ), 'value' => $a['address'] ?? '' ),
				),
			);
		}
	}
	return array( 'data' => $items, 'done' => true );
}

function tp_privacy_message_ids( $email ) {
	return get_posts(
		array(
			'post_type'   => 'tp_message',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_tp_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
}

function tp_privacy_export_messages( $email, $page = 1 ) {
	$items = array();
	foreach ( tp_privacy_message_ids( $email ) as $id ) {
		$items[] = array(
			'group_id'    => 'tp-messages',
			'group_label' => __( 'Messages and driver applications', 'taxi-peninsula' ),
			'item_id'     => 'tp-message-' . $id,
			'data'        => array(
				array( 'name' => __( 'Sent', 'taxi-peninsula' ), 'value' => get_the_date( 'j M Y g:i a', $id ) ),
				array( 'name' => __( 'Name', 'taxi-peninsula' ), 'value' => get_the_title( $id ) ),
				array( 'name' => __( 'Phone', 'taxi-peninsula' ), 'value' => get_post_meta( $id, '_tp_phone', true ) ),
				array( 'name' => __( 'Message', 'taxi-peninsula' ), 'value' => get_post_field( 'post_content', $id ) ),
			),
		);
	}
	return array( 'data' => $items, 'done' => true );
}

function tp_privacy_erase_bookings( $email, $page = 1 ) {
	$removed  = false;
	$retained = false;
	$messages = array();
	foreach ( tp_privacy_booking_ids( $email, tp_privacy_user_id( $email ) ) as $id ) {
		if ( get_post_meta( $id, '_tp_anonymised', true ) ) {
			continue;
		}
		if ( tp_privacy_is_upcoming( $id ) ) {
			$retained   = true;
			/* translators: %s: booking reference */
			$messages[] = sprintf( __( 'Booking %s is still upcoming, so it was kept. Cancel it, or erase again after the trip.', 'taxi-peninsula' ), get_post_meta( $id, '_tp_reference', true ) );
			continue;
		}
		$removed = tp_privacy_anonymise_booking( $id, __( 'Personal details removed on a privacy request', 'taxi-peninsula' ) ) || $removed;
		if ( 'account' === get_post_meta( $id, '_tp_payment', true ) && get_post_meta( $id, '_tp_invoice_name', true ) ) {
			$retained = true;
		}
	}
	if ( $retained && ! $messages ) {
		$messages[] = __( 'Invoice details on account trips are kept for 5 years, as tax law requires.', 'taxi-peninsula' );
	}
	return array(
		'items_removed'  => $removed,
		'items_retained' => $retained,
		'messages'       => $messages,
		'done'           => true,
	);
}

function tp_privacy_erase_account( $email, $page = 1 ) {
	$user_id = tp_privacy_user_id( $email );
	$removed = false;
	if ( $user_id ) {
		foreach ( array( 'tp_passengers', 'tp_addresses', 'tp_mobile', 'tp_org', 'tp_invoice_name', 'tp_invoice_email' ) as $key ) {
			if ( metadata_exists( 'user', $user_id, $key ) ) {
				delete_user_meta( $user_id, $key );
				$removed = true;
			}
		}
	}
	return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
}

function tp_privacy_erase_messages( $email, $page = 1 ) {
	$removed = false;
	foreach ( tp_privacy_message_ids( $email ) as $id ) {
		$removed = (bool) wp_delete_post( $id, true ) || $removed;
	}
	return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
}

// Record the outcome of requests handled in Tools → Erase Personal Data.
add_action(
	'wp_privacy_personal_data_erased',
	static function ( $request_id ) {
		$request = wp_get_user_request( $request_id );
		if ( $request ) {
			/* translators: %s: masked email */
			tp_activity( 'privacy', sprintf( __( 'Personal data erased for %s', 'taxi-peninsula' ), tp_privacy_mask_email( $request->email ) ) );
		}
	}
);
add_action(
	'wp_privacy_personal_data_export_file_created',
	static function ( $archive_pathname, $archive_url, $html_report_pathname, $request_id ) {
		$request = wp_get_user_request( $request_id );
		if ( $request ) {
			/* translators: %s: masked email */
			tp_activity( 'privacy', sprintf( __( 'Personal data exported for %s', 'taxi-peninsula' ), tp_privacy_mask_email( $request->email ) ) );
		}
	},
	10,
	4
);

function tp_privacy_mask_email( $email ) {
	$parts = explode( '@', (string) $email );
	if ( 2 !== count( $parts ) ) {
		return '***';
	}
	return mb_substr( $parts[0], 0, 2 ) . '***@' . $parts[1];
}

/* -------------------------------------------------------------------------
 * Automatic clean-up.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		$scheduled = wp_next_scheduled( 'tp_privacy_cleanup' );
		if ( tp_setting( 'privacy_cleanup' ) && ! $scheduled ) {
			wp_schedule_event( strtotime( 'tomorrow 03:15' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'tp_privacy_cleanup' );
		} elseif ( ! tp_setting( 'privacy_cleanup' ) && $scheduled ) {
			wp_clear_scheduled_hook( 'tp_privacy_cleanup' );
		}
	}
);
add_action(
	'tp_privacy_cleanup',
	static function () {
		if ( tp_setting( 'privacy_cleanup' ) ) {
			tp_privacy_cleanup( false );
		}
	}
);

/**
 * Find (and unless $dry, clean) data past its retention period.
 *
 * @param bool $dry Only count.
 * @return array Counts: trips, invoices, messages, activity.
 */
function tp_privacy_cleanup( $dry = true ) {
	global $wpdb;
	$counts = array( 'trips' => 0, 'invoices' => 0, 'messages' => 0, 'activity' => 0 );
	$limit  = $dry ? -1 : 300;

	$months = (int) tp_setting( 'retention_trip_months' );
	if ( $months > 0 ) {
		$cutoff = wp_date( 'Y-m-d H:i', strtotime( '-' . $months . ' months' ) );
		$ids    = get_posts(
			array(
				'post_type'   => 'tp_booking',
				'post_status' => 'any',
				'numberposts' => $limit,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => '_tp_pickup_at', 'value' => $cutoff, 'compare' => '<' ),
					array( 'key' => '_tp_pickup_at', 'value' => '', 'compare' => '!=' ),
					array( 'key' => '_tp_anonymised', 'compare' => 'NOT EXISTS' ),
				),
			)
		);
		$counts['trips'] = count( $ids );
		if ( ! $dry ) {
			foreach ( $ids as $id ) {
				/* translators: %d: months */
				tp_privacy_anonymise_booking( $id, sprintf( __( 'Personal details removed automatically after %d months', 'taxi-peninsula' ), $months ) );
			}
		}
	}

	// Account invoice details, once past the 5-year tax record period.
	$tax_ids = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'post_status' => 'any',
			'numberposts' => $limit,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_tp_anonymised', 'compare' => 'EXISTS' ),
				array( 'key' => '_tp_pickup_at', 'value' => wp_date( 'Y-m-d', strtotime( '-5 years' ) ), 'compare' => '<' ),
				array( 'key' => '_tp_invoice_name', 'value' => '', 'compare' => '!=' ),
			),
		)
	);
	$counts['invoices'] = count( $tax_ids );
	if ( ! $dry ) {
		tp_activity_quiet( true );
		foreach ( $tax_ids as $id ) {
			foreach ( array( '_tp_invoice_name', '_tp_invoice_email', '_tp_ndis_number' ) as $key ) {
				update_post_meta( $id, $key, '' );
			}
		}
		tp_activity_quiet( false );
	}

	$months = (int) tp_setting( 'retention_messages_months' );
	if ( $months > 0 ) {
		$ids = get_posts(
			array(
				'post_type'   => 'tp_message',
				'post_status' => 'any',
				'numberposts' => $limit,
				'fields'      => 'ids',
				'date_query'  => array( array( 'before' => $months . ' months ago' ) ),
			)
		);
		$counts['messages'] = count( $ids );
		if ( ! $dry ) {
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		}
	}

	$months = (int) tp_setting( 'retention_activity_months' );
	if ( $months > 0 && (int) get_option( 'tp_activity_db' ) ) {
		$table  = tp_activity_table();
		$cutoff = wp_date( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$counts['activity'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created < %s", $cutoff ) );
		if ( ! $dry && $counts['activity'] ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created < %s", $cutoff ) );
		}
		// phpcs:enable
	}

	if ( ! $dry ) {
		update_option( 'tp_privacy_last_cleanup', array( 'time' => current_time( 'mysql' ), 'counts' => $counts ), false );
		if ( array_sum( $counts ) ) {
			tp_activity(
				'privacy',
				sprintf(
					/* translators: 1: trips, 2: messages, 3: log entries */
					__( 'Automatic clean-up: %1$d trips anonymised, %2$d messages deleted, %3$d old log entries deleted', 'taxi-peninsula' ),
					$counts['trips'],
					$counts['messages'],
					$counts['activity']
				)
			);
		}
	}
	return $counts;
}

/* -------------------------------------------------------------------------
 * Bookings → Privacy.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			tp_admin_parent( 'settings' ),
			__( 'Privacy', 'taxi-peninsula' ),
			__( 'Privacy', 'taxi-peninsula' ),
			'manage_options',
			'tp-privacy',
			'tp_render_privacy_page'
		);
	},
	41
);

function tp_render_privacy_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$due      = tp_privacy_cleanup( true );
	$last     = get_option( 'tp_privacy_last_cleanup' );
	$policy   = (int) get_option( 'wp_page_for_privacy_policy' );
	$requests = array();
	foreach ( array( 'export_personal_data', 'remove_personal_data' ) as $type ) {
		$requests[ $type ] = count(
			get_posts(
				array(
					'post_type'   => 'user_request',
					'post_name__in' => array( $type ),
					'post_status' => array( 'request-pending', 'request-confirmed' ),
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$ran = isset( $_GET['ran'] );
	?>
	<div class="wrap tp-privacy-admin">
		<h1><?php esc_html_e( 'Privacy', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Tools for the Australian Privacy Principles: give people a copy of their information, delete it on request, and do not keep it longer than you need.', 'taxi-peninsula' ); ?></p>
		<?php if ( $ran ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Clean-up finished.', 'taxi-peninsula' ); ?></p></div>
		<?php endif; ?>

		<div class="tp-privacy-admin__grid">
			<div class="card">
				<h2><?php esc_html_e( 'Requests from people', 'taxi-peninsula' ); ?></h2>
				<p><?php esc_html_e( 'Requests made with the form on your privacy policy page are confirmed by email first, then appear here for you to handle. Bookings, account profiles and messages are included automatically.', 'taxi-peninsula' ); ?></p>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'export-personal-data.php' ) ); ?>">
						<?php
						/* translators: %d: open requests */
						echo esc_html( sprintf( __( 'Export requests (%d open)', 'taxi-peninsula' ), $requests['export_personal_data'] ) );
						?>
					</a>
					<a class="button" href="<?php echo esc_url( admin_url( 'erase-personal-data.php' ) ); ?>">
						<?php
						/* translators: %d: open requests */
						echo esc_html( sprintf( __( 'Deletion requests (%d open)', 'taxi-peninsula' ), $requests['remove_personal_data'] ) );
						?>
					</a>
				</p>
				<p class="description"><?php esc_html_e( 'Deleting never removes the trip itself: the reference, date, suburbs, fare and payment stay, and account invoice details are kept for 5 years for tax. Upcoming trips are kept until they are over.', 'taxi-peninsula' ); ?></p>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Automatic clean-up', 'taxi-peninsula' ); ?></h2>
				<p>
					<?php echo tp_setting( 'privacy_cleanup' ) ? esc_html__( 'On — runs once a day.', 'taxi-peninsula' ) : esc_html__( 'Off.', 'taxi-peninsula' ); ?>
					<a href="<?php echo esc_url( tp_settings_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Change the periods', 'taxi-peninsula' ); ?></a>
				</p>
				<table class="widefat striped">
					<tbody>
						<tr><th scope="row"><?php esc_html_e( 'Trips to anonymise', 'taxi-peninsula' ); ?></th><td><?php echo esc_html( $due['trips'] ); ?></td><td><?php echo esc_html( tp_privacy_period_text( 'retention_trip_months' ) ); ?></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Invoice details past 5 years', 'taxi-peninsula' ); ?></th><td><?php echo esc_html( $due['invoices'] ); ?></td><td><?php esc_html_e( '5 years', 'taxi-peninsula' ); ?></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Messages and applications to delete', 'taxi-peninsula' ); ?></th><td><?php echo esc_html( $due['messages'] ); ?></td><td><?php echo esc_html( tp_privacy_period_text( 'retention_messages_months' ) ); ?></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Activity log entries to delete', 'taxi-peninsula' ); ?></th><td><?php echo esc_html( $due['activity'] ); ?></td><td><?php echo esc_html( tp_privacy_period_text( 'retention_activity_months' ) ); ?></td></tr>
					</tbody>
				</table>
				<?php if ( is_array( $last ) ) : ?>
					<p class="description">
						<?php
						/* translators: %s: date and time */
						echo esc_html( sprintf( __( 'Last run: %s', 'taxi-peninsula' ), mysql2date( 'j M Y g:i a', $last['time'] ) ) );
						?>
					</p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tp_privacy_run">
					<?php wp_nonce_field( 'tp_privacy_run' ); ?>
					<?php submit_button( __( 'Run clean-up now', 'taxi-peninsula' ), 'secondary', 'submit', false, array_sum( $due ) ? array() : array( 'disabled' => 'disabled' ) ); ?>
				</form>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Privacy policy', 'taxi-peninsula' ); ?></h2>
				<?php if ( $policy && 'publish' === get_post_status( $policy ) ) : ?>
					<p><?php esc_html_e( 'Published:', 'taxi-peninsula' ); ?> <a href="<?php echo esc_url( get_permalink( $policy ) ); ?>"><?php echo esc_html( get_the_title( $policy ) ); ?></a></p>
				<?php elseif ( $policy && get_post( $policy ) ) : ?>
					<p><?php esc_html_e( 'Draft — review it, then publish:', 'taxi-peninsula' ); ?> <a href="<?php echo esc_url( get_edit_post_link( $policy ) ); ?>"><?php echo esc_html( get_the_title( $policy ) ); ?></a></p>
				<?php endif; ?>
				<p><?php esc_html_e( 'The theme writes a policy that follows the Australian Privacy Principles and updates itself from your settings: the services you have switched on, where data goes overseas, and how long you keep it. Have it checked by your adviser before publishing.', 'taxi-peninsula' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tp_privacy_page">
					<?php wp_nonce_field( 'tp_privacy_page' ); ?>
					<?php submit_button( __( 'Create the privacy policy page (draft)', 'taxi-peninsula' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
	</div>
	<?php
}

function tp_privacy_period_text( $key ) {
	$months = (int) tp_setting( $key );
	/* translators: %d: months */
	return $months ? sprintf( _n( '%d month', '%d months', $months, 'taxi-peninsula' ), $months ) : __( 'Kept', 'taxi-peninsula' );
}

add_action(
	'admin_post_tp_privacy_run',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_privacy_run' );
		tp_privacy_cleanup( false );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-privacy&ran=1' ) );
		exit;
	}
);

add_action(
	'admin_post_tp_privacy_page',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_privacy_page' );
		$id = tp_privacy_create_page();
		wp_safe_redirect( $id ? get_edit_post_link( $id, 'raw' ) : admin_url( 'admin.php?page=tp-privacy' ) );
		exit;
	}
);

/**
 * Create (once) the privacy policy page and make it the site's privacy page.
 *
 * @return int Page ID.
 */
function tp_privacy_create_page() {
	$pages = (array) get_option( 'tp_pages', array() );
	if ( ! empty( $pages['privacy'] ) && get_post( $pages['privacy'] ) && 'trash' !== get_post_status( $pages['privacy'] ) ) {
		$id = (int) $pages['privacy'];
	} else {
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => __( 'Privacy Policy', 'taxi-peninsula' ),
				'post_content' => function_exists( 'tp_pattern_content' ) ? tp_pattern_content( 'page-privacy' ) : "<!-- wp:shortcode -->\n[tp_privacy_policy]\n<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->\n[tp_privacy_request]\n<!-- /wp:shortcode -->",
				'meta_input'   => array( '_wp_page_template' => 'page-templates/policy.php' ),
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		$pages['privacy'] = $id;
		update_option( 'tp_pages', $pages );
	}
	$current = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( ! $current || 'publish' !== get_post_status( $current ) ) {
		update_option( 'wp_page_for_privacy_policy', $id );
	}
	return $id;
}

// Suggested text for Settings → Privacy → Policy Guide.
add_action(
	'admin_init',
	static function () {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'Taxi Peninsula',
				'<p>' . esc_html__( 'This theme stores bookings (names, phone numbers, emails, addresses, mobility and health details, payment and invoice details), customer accounts, messages, ratings and an activity log. Use the [tp_privacy_policy] shortcode (or Bookings → Privacy → Create the privacy policy page) for a full policy that follows the Australian Privacy Principles and lists your retention periods and service providers.', 'taxi-peninsula' ) . '</p>'
			);
		}
	}
);

/* -------------------------------------------------------------------------
 * Requests from the website: [tp_privacy_request].
 * ---------------------------------------------------------------------- */

add_shortcode( 'tp_privacy_request', 'tp_privacy_request_shortcode' );
function tp_privacy_request_shortcode() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['privacy_request'] ) ? sanitize_key( wp_unslash( $_GET['privacy_request'] ) ) : '';
	ob_start();
	?>
	<div class="tp-component privacy-request card" id="privacy-request">
		<h2 class="privacy-request__title"><?php esc_html_e( 'Ask for a copy of your information, or for it to be deleted', 'taxi-peninsula' ); ?></h2>
		<?php if ( 'sent' === $state ) : ?>
			<p class="notice notice--success" role="status" tabindex="-1" data-focus><?php esc_html_e( 'Thank you. Please check your email and click the link to confirm the request — we then reply within 30 days.', 'taxi-peninsula' ); ?></p>
		<?php elseif ( 'error' === $state ) : ?>
			<p class="notice notice--error" role="alert" tabindex="-1" data-focus><?php esc_html_e( 'Sorry, we could not send that. Please check the email address, or call us.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
		<?php if ( tp_is_account_user() ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: link to My Account */
					esc_html__( 'You are signed in, so you can download your data or delete your account straight away under %s.', 'taxi-peninsula' ),
					'<a href="' . esc_url( tp_account_url( array( 'tab' => 'details' ) ) . '#privacy' ) . '">' . esc_html__( 'My Account → My details', 'taxi-peninsula' ) . '</a>'
				);
				?>
			</p>
		<?php endif; ?>
		<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_privacy_request">
			<input type="hidden" name="_tp_return" value="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
			<?php wp_nonce_field( 'tp_privacy_request', '_tp_nonce', false ); ?>
			<fieldset class="field field--wide">
				<legend class="field__label"><?php esc_html_e( 'What would you like?', 'taxi-peninsula' ); ?></legend>
				<label class="choice"><input type="radio" name="type" value="export" checked> <?php esc_html_e( 'A copy of the information you hold about me', 'taxi-peninsula' ); ?></label>
				<label class="choice"><input type="radio" name="type" value="remove"> <?php esc_html_e( 'Delete my information', 'taxi-peninsula' ); ?></label>
			</fieldset>
			<div class="field field--wide">
				<label for="pr-email"><?php esc_html_e( 'Email address you used for bookings', 'taxi-peninsula' ); ?></label>
				<input id="pr-email" name="email" type="email" autocomplete="email" required>
				<p class="field__hint"><?php esc_html_e( 'We email you a link to confirm it is really you.', 'taxi-peninsula' ); ?></p>
			</div>
			<?php tp_spam_fields(); ?>
			<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Send request', 'taxi-peninsula' ); ?></button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_nopriv_tp_privacy_request', 'tp_handle_privacy_request' );
add_action( 'admin_post_tp_privacy_request', 'tp_handle_privacy_request' );
function tp_handle_privacy_request() {
	$return = isset( $_POST['_tp_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ), home_url( '/' ) ) : home_url( '/' );
	$back   = static function ( $state ) use ( $return ) {
		wp_safe_redirect( add_query_arg( 'privacy_request', $state, $return ) . '#privacy-request' );
		exit;
	};
	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_privacy_request' ) ) {
		$back( 'error' );
	}
	if ( tp_is_honeypot_hit() ) {
		$back( 'sent' );
	}
	if ( ! tp_rate_limit( 'privacy', 3, HOUR_IN_SECONDS ) || ! tp_verify_captcha() ) {
		$back( 'error' );
	}
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$type  = isset( $_POST['type'] ) && 'remove' === $_POST['type'] ? 'remove_personal_data' : 'export_personal_data';
	if ( ! is_email( $email ) ) {
		$back( 'error' );
	}
	$request_id = wp_create_user_request( $email, $type );
	if ( ! is_wp_error( $request_id ) ) {
		wp_send_user_request( $request_id );
		tp_in_site_lang(
			static function () use ( $type, $email ) {
				tp_activity(
					'privacy',
					sprintf(
						/* translators: 1: request type, 2: masked email */
						__( 'Privacy request received (%1$s) from %2$s — waiting for email confirmation', 'taxi-peninsula' ),
						'remove_personal_data' === $type ? __( 'delete', 'taxi-peninsula' ) : __( 'copy', 'taxi-peninsula' ),
						tp_privacy_mask_email( $email )
					)
				);
			}
		);
	}
	// Same answer whether or not the email is known, or a request was already open.
	$back( 'sent' );
}

/* -------------------------------------------------------------------------
 * My Account → My details → Your privacy.
 * ---------------------------------------------------------------------- */

function tp_account_privacy_panel( WP_User $user ) {
	$upcoming = array_filter( tp_privacy_booking_ids( $user->user_email, $user->ID ), 'tp_privacy_is_upcoming' );
	$policy   = get_privacy_policy_url();
	?>
	<section class="card account-privacy" id="privacy" aria-labelledby="privacy-title">
		<h3 class="account__title" id="privacy-title"><?php esc_html_e( 'Your privacy', 'taxi-peninsula' ); ?></h3>
		<p>
			<?php esc_html_e( 'You can download everything we hold about you, or close your account.', 'taxi-peninsula' ); ?>
			<?php if ( $policy ) : ?>
				<a href="<?php echo esc_url( $policy ); ?>"><?php esc_html_e( 'Read our privacy policy', 'taxi-peninsula' ); ?></a>
			<?php endif; ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="account-privacy__export">
			<?php tp_account_form_fields( 'tp_account_export' ); ?>
			<button type="submit" class="btn btn--ghost" data-no-loading><?php tp_the_icon( 'download' ); ?> <?php esc_html_e( 'Download my data', 'taxi-peninsula' ); ?></button>
			<p class="field__hint"><?php esc_html_e( 'A file with your details, trips, saved passengers and addresses.', 'taxi-peninsula' ); ?></p>
		</form>

		<details class="account-privacy__delete">
			<summary><?php esc_html_e( 'Delete my account', 'taxi-peninsula' ); ?></summary>
			<?php if ( $upcoming ) : ?>
				<p class="notice notice--error">
					<?php
					/* translators: %s: phone */
					echo esc_html( sprintf( __( 'You have upcoming trips. Please cancel them first, or call us on %s, then come back to delete your account.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) );
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'This signs you out and deletes your account, saved passengers, clients and addresses. Names, contact details and street addresses are removed from your past trips. We keep the trip reference, date, suburbs and fare for our records, and invoice details for 5 years because tax law requires it.', 'taxi-peninsula' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="booking-form">
					<?php tp_account_form_fields( 'tp_account_delete' ); ?>
					<div class="field">
						<label for="ad-pass"><?php esc_html_e( 'Your password', 'taxi-peninsula' ); ?></label>
						<input id="ad-pass" name="password" type="password" autocomplete="current-password" required>
					</div>
					<div class="field field--wide field--check">
						<input id="ad-confirm" name="confirm" type="checkbox" value="1" required>
						<label for="ad-confirm"><?php esc_html_e( 'I understand this cannot be undone.', 'taxi-peninsula' ); ?></label>
					</div>
					<button type="submit" class="btn btn--danger"><?php esc_html_e( 'Delete my account', 'taxi-peninsula' ); ?></button>
				</form>
			<?php endif; ?>
		</details>
	</section>
	<?php
}

add_action( 'admin_post_tp_account_export', 'tp_handle_account_export' );
function tp_handle_account_export() {
	tp_account_verify( 'tp_account_export' );
	$user  = wp_get_current_user();
	$email = $user->user_email;
	$out   = array(
		'exported' => current_time( 'c' ),
		'business' => get_bloginfo( 'name' ),
		'account'  => array(
			__( 'Name', 'taxi-peninsula' )  => trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name,
			__( 'Email', 'taxi-peninsula' ) => $email,
		),
	);
	foreach ( array( 'tp_privacy_export_account', 'tp_privacy_export_bookings', 'tp_privacy_export_messages' ) as $exporter ) {
		$page = 1;
		do {
			$result = call_user_func( $exporter, $email, $page );
			foreach ( $result['data'] as $item ) {
				$row = array();
				foreach ( $item['data'] as $pair ) {
					$row[ $pair['name'] ] = $pair['value'];
				}
				$out[ $item['group_label'] ][] = $row;
			}
			$page++;
		} while ( empty( $result['done'] ) && $page < 100 );
	}
	tp_in_site_lang(
		static function () {
			tp_activity( 'privacy', __( 'Customer downloaded their data from My Account', 'taxi-peninsula' ) );
		}
	);

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=my-data-' . sanitize_title( get_bloginfo( 'name' ) ) . '-' . gmdate( 'Y-m-d' ) . '.json' );
	echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}

add_action( 'admin_post_tp_account_delete', 'tp_handle_account_delete' );
function tp_handle_account_delete() {
	tp_account_verify( 'tp_account_delete' );
	$user = wp_get_current_user();
	$pass = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password.
	if ( empty( $_POST['confirm'] ) || ! wp_check_password( $pass, $user->user_pass, $user->ID ) ) {
		tp_account_back( array( 'tab' => 'details' ), array( __( 'Your password was not right, so your account was not deleted.', 'taxi-peninsula' ) ) );
	}
	if ( tp_is_staff( $user ) ) {
		tp_account_back( array( 'tab' => 'details' ) );
	}
	$ids = tp_privacy_booking_ids( $user->user_email, $user->ID );
	if ( array_filter( $ids, 'tp_privacy_is_upcoming' ) ) {
		tp_account_back( array( 'tab' => 'details' ), array( __( 'You have upcoming trips. Please cancel them first.', 'taxi-peninsula' ) ) );
	}

	// Records and the office email stay in the site language.
	tp_in_site_lang(
		static function () use ( $ids, $user ) {
			foreach ( $ids as $id ) {
				if ( ! get_post_meta( $id, '_tp_anonymised', true ) ) {
					tp_privacy_anonymise_booking( $id, __( 'Customer deleted their account', 'taxi-peninsula' ) );
				}
				tp_activity_quiet( true );
				update_post_meta( $id, '_tp_customer_id', 0 );
				tp_activity_quiet( false );
			}
			tp_privacy_erase_account( $user->user_email );
			tp_privacy_erase_messages( $user->user_email );

			/* translators: %s: masked email */
			tp_activity( 'privacy', sprintf( __( 'Customer account deleted (%s)', 'taxi-peninsula' ), tp_privacy_mask_email( $user->user_email ) ) );
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				__( 'A customer deleted their account', 'taxi-peninsula' ),
				sprintf(
					/* translators: %d: number of trips */
					__( 'A customer account was deleted from My Account. Personal details were removed from %d trips; references, dates, suburbs and fares were kept.', 'taxi-peninsula' ),
					count( $ids )
				),
				tp_mail_headers()
			);
		}
	);

	require_once ABSPATH . 'wp-admin/includes/user.php';
	$admins   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$reassign = $admins ? (int) $admins[0] : null;
	wp_logout();
	wp_delete_user( $user->ID, $reassign );

	wp_safe_redirect( add_query_arg( 'bye', 1, tp_account_url() ) . '#account' );
	exit;
}

/* -------------------------------------------------------------------------
 * The privacy policy: [tp_privacy_policy].
 * ---------------------------------------------------------------------- */

/**
 * Service providers that receive personal information: [name, purpose, where].
 */
function tp_privacy_providers() {
	$list = array();
	$sms  = tp_setting( 'sms_provider' );
	if ( 'clicksend' === $sms ) {
		$list[] = array( 'ClickSend', __( 'sending booking text messages', 'taxi-peninsula' ), __( 'Australia, with some processing overseas', 'taxi-peninsula' ) );
	} elseif ( 'twilio' === $sms ) {
		$list[] = array( 'Twilio', __( 'sending booking text messages', 'taxi-peninsula' ), __( 'United States', 'taxi-peninsula' ) );
	}
	if ( tp_payments_available() ) {
		$list[] = array( 'Stripe', __( 'taking card payments — we never see or store your card number', 'taxi-peninsula' ), __( 'United States and other countries', 'taxi-peninsula' ) );
	}
	if ( function_exists( 'tp_maps_available' ) && tp_maps_available() ) {
		$list[] = array( 'Google Maps', __( 'address suggestions, maps and fare estimates (the addresses you type are sent to Google)', 'taxi-peninsula' ), __( 'United States and other countries', 'taxi-peninsula' ) );
	}
	if ( function_exists( 'tp_addressfinder_on' ) && tp_addressfinder_on() && tp_setting( 'places_enabled' ) ) {
		$list[] = array( 'Addressfinder', __( 'address suggestions (the addresses you type are sent to Addressfinder)', 'taxi-peninsula' ), __( 'Australia and New Zealand', 'taxi-peninsula' ) );
	}
	if ( tp_setting( 'live_location' ) ) {
		$list[] = array( 'OpenStreetMap', __( 'the map showing where your driver is on your booking page (your browser loads the map pictures from OpenStreetMap). The driver’s location is only shared while they are on the way to you and is deleted when the trip ends', 'taxi-peninsula' ), __( 'United Kingdom and other countries', 'taxi-peninsula' ) );
	}
	if ( function_exists( 'tp_ga4_id' ) && tp_ga4_id() ) {
		$list[] = array( 'Google Analytics', __( 'counting website visits, only if you accept analytics cookies', 'taxi-peninsula' ), __( 'United States and other countries', 'taxi-peninsula' ) );
	}
	$captcha = tp_setting( 'captcha_provider' );
	if ( 'turnstile' === $captcha ) {
		$list[] = array( 'Cloudflare Turnstile', __( 'stopping spam on our forms', 'taxi-peninsula' ), __( 'United States and other countries', 'taxi-peninsula' ) );
	} elseif ( 'recaptcha' === $captcha ) {
		$list[] = array( 'Google reCAPTCHA', __( 'stopping spam on our forms', 'taxi-peninsula' ), __( 'United States and other countries', 'taxi-peninsula' ) );
	}
	foreach ( preg_split( '/\r\n|\r|\n/', (string) tp_setting( 'privacy_providers' ) ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$list[] = array( $line, '', '' );
		}
	}
	return $list;
}

add_shortcode( 'tp_privacy_policy', 'tp_privacy_policy_shortcode' );
function tp_privacy_policy_shortcode() {
	$name      = get_bloginfo( 'name' );
	$contact   = tp_privacy_contact();
	$email     = tp_privacy_email();
	$phone     = tp_opt( 'phone_display' );
	$address   = tp_setting( 'business_address' );
	$providers = tp_privacy_providers();
	$hosting   = tp_setting( 'privacy_hosting' );
	$can_edit  = current_user_can( 'edit_pages' );
	$todo      = static function ( $text ) use ( $can_edit ) {
		if ( $can_edit ) {
			echo '<p class="tp-todo">' . esc_html( $text ) . '</p>';
		}
	};
	$trip_m = (int) tp_setting( 'retention_trip_months' );
	$msg_m  = (int) tp_setting( 'retention_messages_months' );
	$log_m  = (int) tp_setting( 'retention_activity_months' );
	$months = static function ( $m ) {
		/* translators: %d: months */
		return sprintf( _n( '%d month', '%d months', $m, 'taxi-peninsula' ), $m );
	};
	ob_start();
	?>
	<div class="tp-privacy-policy">
		<p class="tp-privacy-policy__updated">
			<?php
			/* translators: %s: date */
			echo esc_html( sprintf( __( 'Last updated: %s', 'taxi-peninsula' ), get_the_modified_date( 'j F Y' ) ?: wp_date( 'j F Y' ) ) );
			?>
		</p>

		<h2><?php esc_html_e( 'About this policy', 'taxi-peninsula' ); ?></h2>
		<p>
			<?php
			/* translators: %s: business name */
			echo esc_html( sprintf( __( '%s ("we", "us") provides wheelchair accessible taxi transport. This policy explains what personal information we collect, why, who we share it with and how you can see, correct or delete it. We follow the Privacy Act 1988 (Cth) and the Australian Privacy Principles (APPs).', 'taxi-peninsula' ), $name ) );
			?>
		</p>

		<h2><?php esc_html_e( 'What we collect', 'taxi-peninsula' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Contact details: name, mobile number and email address.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Trip details: pick-up and drop-off addresses, dates and times, number of passengers, and the vehicle you need.', 'taxi-peninsula' ); ?></li>
			<li><strong><?php esc_html_e( 'Health and mobility information', 'taxi-peninsula' ); ?></strong> — <?php esc_html_e( 'your mobility aid, chair size and weight, and any needs you tell us about so the driver can help you safely. This is "sensitive information" under the APPs. We only collect it with your consent, and only what we need for your trip.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Payment and funding details: how you pay, Multi Purpose Taxi Program membership, and for account trips the organisation or plan manager we invoice and an NDIS number or purchase order.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'If you open an account: your saved passengers or clients and saved addresses.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Ratings and comments you give about a trip, and messages you send us.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'If you apply to drive with us: the details in your application, including your accreditation.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Website information: your IP address (to stop spam and misuse) and, only if you accept analytics cookies, how you use the website.', 'taxi-peninsula' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'You can ask about our services without giving your name. To book a trip we need enough detail to collect you and contact you.', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'How we collect it', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'Directly from you when you book, call, text or email us, or from someone booking for you — a family member, carer, support coordinator, plan manager, aged-care home or hospital. If you book for someone else, please make sure they are happy for you to give us their details.', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'Why we use it', 'taxi-peninsula' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'To arrange, run and change your trips, and send the right vehicle.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'To send booking confirmations and updates by SMS and email, such as when your driver is on the way.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'To take payment, issue receipts and invoices, and process MPTP and NDIS claims.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'For safety, to handle complaints and incidents, and to improve our service using your ratings.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'To meet our legal obligations, including commercial passenger vehicle and tax laws.', 'taxi-peninsula' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'We do not sell your information, and we do not send marketing messages without your permission.', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'Who we share it with', 'taxi-peninsula' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'The driver doing your trip sees your name, phone number, addresses and the needs you have told us about. Drivers only see their own trips.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'People you ask us to deal with, such as the coordinator, plan manager or facility that booked or pays for your trip.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Government bodies and police when the law requires it, for example the regulator or police after an incident.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Service providers who help us run the business:', 'taxi-peninsula' ); ?>
				<ul>
					<?php if ( $hosting ) : ?>
						<?php /* translators: %s: hosting provider and location */ ?>
						<li><?php echo esc_html( sprintf( __( 'Website and email hosting: %s', 'taxi-peninsula' ), $hosting ) ); ?></li>
					<?php endif; ?>
					<?php foreach ( $providers as $p ) : ?>
						<li><strong><?php echo esc_html( $p[0] ); ?></strong><?php echo $p[1] ? ' — ' . esc_html( $p[1] ) : ''; ?></li>
					<?php endforeach; ?>
				</ul>
			</li>
		</ul>
		<?php if ( ! $hosting ) : ?>
			<?php $todo( __( '[CONFIRM: add where your website and email are hosted under Bookings → Settings → Privacy. Only staff see this note.]', 'taxi-peninsula' ) ); ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Information sent overseas', 'taxi-peninsula' ); ?></h2>
		<?php
		$overseas = array_filter(
			$providers,
			static function ( $p ) {
				return '' !== $p[2];
			}
		);
		?>
		<?php if ( $overseas ) : ?>
			<p><?php esc_html_e( 'Some of our service providers store or process information outside Australia:', 'taxi-peninsula' ); ?></p>
			<ul>
				<?php foreach ( $overseas as $p ) : ?>
					<li><?php echo esc_html( $p[0] . ' — ' . $p[2] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'We choose providers with strong security and privacy commitments, and only share what they need to do their job.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'We do not knowingly send your information overseas. If that changes we will update this policy.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'How we keep it safe', 'taxi-peninsula' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Our website uses an encrypted (HTTPS) connection, and links to your booking are protected with a secret code.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Every staff member and driver has their own login, and each person can only see what their job needs.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'We keep a log of who changes each booking and when.', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'If a data breach is likely to cause you serious harm, we will tell you and the Office of the Australian Information Commissioner.', 'taxi-peninsula' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'How long we keep it', 'taxi-peninsula' ); ?></h2>
		<ul>
			<?php if ( $trip_m ) : ?>
				<li>
					<?php
					/* translators: %s: period */
					echo esc_html( sprintf( __( 'Trips: names, contact details, street addresses, notes and comments are removed %s after the trip. We keep the reference, date, suburbs and fare for our records.', 'taxi-peninsula' ), $months( $trip_m ) ) );
					?>
				</li>
			<?php else : ?>
				<li><?php esc_html_e( 'Trips: we keep trip records while we need them to run the business, and remove details when you ask.', 'taxi-peninsula' ); ?></li>
			<?php endif; ?>
			<li><?php esc_html_e( 'Invoices and payment records: 5 years, as tax law requires.', 'taxi-peninsula' ); ?></li>
			<?php if ( $msg_m ) : ?>
				<?php /* translators: %s: period */ ?>
				<li><?php echo esc_html( sprintf( __( 'Messages and driver applications: %s.', 'taxi-peninsula' ), $months( $msg_m ) ) ); ?></li>
			<?php endif; ?>
			<?php if ( $log_m ) : ?>
				<?php /* translators: %s: period */ ?>
				<li><?php echo esc_html( sprintf( __( 'Our activity log of changes to bookings: %s.', 'taxi-peninsula' ), $months( $log_m ) ) ); ?></li>
			<?php endif; ?>
			<li><?php esc_html_e( 'Accounts: until you delete your account.', 'taxi-peninsula' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Seeing, correcting or deleting your information', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'You can ask for a copy of the information we hold about you, ask us to correct it, or ask us to delete it. It is free. If you have an account, you can update your details, download your data and delete your account yourself in My Account. Otherwise use the form below or contact us. We will check it is really you and reply within 30 days. If we cannot do what you ask — for example because the law requires us to keep invoices — we will tell you why.', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'Questions and complaints', 'taxi-peninsula' ); ?></h2>
		<p>
			<?php
			/* translators: %s: privacy contact */
			echo esc_html( sprintf( __( 'Please contact %s first. We will look into your concern and reply within 30 days.', 'taxi-peninsula' ), $contact ) );
			?>
		</p>
		<ul class="tp-privacy-policy__contact">
			<?php if ( $email ) : ?>
				<li><?php esc_html_e( 'Email:', 'taxi-peninsula' ); ?> <a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
			<?php endif; ?>
			<?php if ( $phone ) : ?>
				<li><?php esc_html_e( 'Phone:', 'taxi-peninsula' ); ?> <a href="<?php echo esc_url( tp_phone_href() ); ?>"><?php echo esc_html( $phone ); ?></a></li>
			<?php endif; ?>
			<?php if ( $address ) : ?>
				<li><?php esc_html_e( 'Post:', 'taxi-peninsula' ); ?> <?php echo esc_html( preg_replace( '/\s*\n\s*/', ', ', $address ) ); ?></li>
			<?php endif; ?>
			<li><?php esc_html_e( 'Deaf, hard of hearing or speech impaired? Contact us through the National Relay Service: nrschat.nrscall.gov.au or call 133 677 and ask for our number.', 'taxi-peninsula' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'If you are not happy with our answer, you can complain to the Office of the Australian Information Commissioner (OAIC): www.oaic.gov.au or 1300 363 992.', 'taxi-peninsula' ); ?></p>
	</div>
	<?php
	return ob_get_clean();
}
