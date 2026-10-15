<?php
/**
 * Flight-linked airport pick-ups.
 *
 * When a booking has a flight number, the theme checks the flight every
 * 15 minutes on the day of travel (AeroDataBox flight data, through
 * RapidAPI). If the landing time moves, the pick-up moves with it: the gap
 * between landing and pick-up the customer asked for is kept. The office
 * gets an email, the driver an SMS, and the change is written to the
 * booking history.
 *
 * Without an API key the dispatch board and driver screen still show the
 * flight with a link to its live status on FlightAware.
 *
 * Settings: Bookings → Settings → Flight tracking.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['flights'] = array(
			__( 'Flight tracking', 'taxi-peninsula' ),
			__( 'Moves airport pick-ups when flights land early or late. Uses AeroDataBox flight data: subscribe at rapidapi.com/aedbx-aedbx/api/aerodatabox (a free tier is available) and paste your RapidAPI key here.', 'taxi-peninsula' ),
		);
		return $s;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['flight_api_key']   = array( 'flights', __( 'RapidAPI key for AeroDataBox', 'taxi-peninsula' ), 'secret', '', __( 'Leave empty to show a live flight-status link only.', 'taxi-peninsula' ) );
		$s['flight_airports']  = array( 'flights', __( 'Your airports (IATA codes)', 'taxi-peninsula' ), 'text', 'MEL, AVV', __( 'Used to pick the right leg of multi-stop flights.', 'taxi-peninsula' ) );
		$s['flight_buffer']    = array( 'flights', __( 'Default minutes from landing to pick-up', 'taxi-peninsula' ), 'number', 30, __( 'Used when the booked pick-up time is before the flight lands or more than 3 hours after it.', 'taxi-peninsula' ) );
		$s['flight_auto_move'] = array( 'flights', __( 'Move the pick-up time automatically', 'taxi-peninsula' ), 'checkbox', true, __( 'When off, the office is only told about the new landing time.', 'taxi-peninsula' ) );
		$s['flight_threshold'] = array( 'flights', __( 'Only act on changes of at least (minutes)', 'taxi-peninsula' ), 'number', 10, '' );
		return $s;
	}
);

function tp_flights_enabled() {
	return '' !== (string) tp_setting( 'flight_api_key' );
}

/**
 * Public live-status link for a flight.
 */
function tp_flight_status_url( $flight ) {
	return 'https://www.flightaware.com/live/flight/' . rawurlencode( strtoupper( $flight ) );
}

/**
 * Look up a flight's arrival. Returns array or WP_Error.
 *
 * @param string $flight Flight number, e.g. QF432.
 * @param string $date   Local date YYYY-MM-DD.
 * @return array|WP_Error { scheduled: int, latest: int, status: string, airport: string }
 */
function tp_flight_lookup( $flight, $date ) {
	$cache_key = 'tp_flight_' . md5( $flight . $date );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$response = wp_remote_get(
		'https://aerodatabox.p.rapidapi.com/flights/number/' . rawurlencode( $flight ) . '/' . rawurlencode( $date ) . '?withAircraftImage=false&withLocation=false',
		array(
			'timeout' => 12,
			'headers' => array(
				'X-RapidAPI-Key'  => (string) tp_setting( 'flight_api_key' ),
				'X-RapidAPI-Host' => 'aerodatabox.p.rapidapi.com',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( 204 === $code || 404 === $code ) {
		return new WP_Error( 'tp_flight_unknown', __( 'Flight not found for that date.', 'taxi-peninsula' ) );
	}
	if ( 200 !== $code ) {
		/* translators: %d: HTTP status */
		return new WP_Error( 'tp_flight_http', sprintf( __( 'Flight data service error (%d).', 'taxi-peninsula' ), $code ) );
	}
	$legs = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $legs ) || ! $legs ) {
		return new WP_Error( 'tp_flight_unknown', __( 'Flight not found for that date.', 'taxi-peninsula' ) );
	}
	$ours = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) tp_setting( 'flight_airports' ) ) ) ) );
	$leg  = $legs[0];
	foreach ( $legs as $l ) {
		if ( in_array( strtoupper( $l['arrival']['airport']['iata'] ?? '' ), $ours, true ) ) {
			$leg = $l;
			break;
		}
	}
	$arr  = $leg['arrival'] ?? array();
	$time = static function ( $t ) {
		$s = $t['utc'] ?? ( $t['local'] ?? '' );
		$ts = $s ? strtotime( $s ) : false;
		return $ts ?: 0;
	};
	$scheduled = $time( $arr['scheduledTime'] ?? array() );
	$latest    = $time( $arr['revisedTime'] ?? array() ) ?: $time( $arr['predictedTime'] ?? array() ) ?: $scheduled;
	if ( ! $scheduled ) {
		return new WP_Error( 'tp_flight_unknown', __( 'No arrival time for this flight yet.', 'taxi-peninsula' ) );
	}
	$out = array(
		'scheduled' => $scheduled,
		'latest'    => $latest,
		'status'    => (string) ( $leg['status'] ?? '' ),
		'airport'   => (string) ( $arr['airport']['iata'] ?? '' ),
		'terminal'  => (string) ( $arr['terminal'] ?? '' ),
	);
	set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

/**
 * Check one booking's flight and move the pick-up if needed.
 *
 * @param int $post_id Booking ID.
 * @return string What happened (for the office), or ''.
 */
function tp_flight_check_booking( $post_id ) {
	$b = tp_get_booking( $post_id );
	if ( '' === (string) $b['flight'] || ! $b['date'] || ! in_array( $b['status'], array( 'pending', 'confirmed', 'assigned', 'accepted' ), true ) ) {
		return '';
	}
	$info = tp_flight_lookup( $b['flight'], $b['date'] );
	update_post_meta( $post_id, '_tp_flight_checked', time() );
	if ( is_wp_error( $info ) ) {
		update_post_meta( $post_id, '_tp_flight_status', $info->get_error_message() );
		return '';
	}
	$tz     = wp_timezone();
	$pickup = $b['time'] ? ( new DateTimeImmutable( $b['date'] . ' ' . $b['time'], $tz ) )->getTimestamp() : 0;
	// First look-up: remember the gap between landing and the requested pick-up.
	$gap = get_post_meta( $post_id, '_tp_flight_gap', true );
	if ( '' === (string) $gap ) {
		$known = (int) get_post_meta( $post_id, '_tp_flight_eta', true ) ?: $info['scheduled'];
		$gap   = $pickup ? (int) round( ( $pickup - $known ) / 60 ) : -1;
		if ( $gap < 0 || $gap > 180 ) {
			$gap = max( 0, (int) tp_setting( 'flight_buffer' ) );
		}
		update_post_meta( $post_id, '_tp_flight_gap', $gap );
		if ( ! get_post_meta( $post_id, '_tp_flight_eta', true ) ) {
			update_post_meta( $post_id, '_tp_flight_eta', $info['scheduled'] );
		}
	}
	$gap      = (int) $gap;
	$prev_eta = (int) get_post_meta( $post_id, '_tp_flight_eta', true );
	update_post_meta( $post_id, '_tp_flight_status', $info['status'] );
	update_post_meta( $post_id, '_tp_flight_latest', $info['latest'] );

	if ( preg_match( '/cancel/i', $info['status'] ) && 'cancelled' !== get_post_meta( $post_id, '_tp_flight_alerted', true ) ) {
		update_post_meta( $post_id, '_tp_flight_alerted', 'cancelled' );
		/* translators: %s: flight */
		$note = sprintf( __( 'Flight %s has been cancelled. Please contact the customer.', 'taxi-peninsula' ), $b['flight'] );
		tp_add_log( $post_id, array( 'note' => $note ) );
		tp_flight_notify( $post_id, $note, false );
		return $note;
	}

	$shift = (int) round( ( $info['latest'] - $prev_eta ) / 60 );
	if ( abs( $shift ) < max( 1, (int) tp_setting( 'flight_threshold' ) ) ) {
		return '';
	}
	update_post_meta( $post_id, '_tp_flight_eta', $info['latest'] );
	$land = wp_date( 'g:i a', $info['latest'] );
	if ( tp_setting( 'flight_auto_move' ) ) {
		$new = $info['latest'] + $gap * MINUTE_IN_SECONDS;
		update_post_meta( $post_id, '_tp_date', wp_date( 'Y-m-d', $new ) );
		update_post_meta( $post_id, '_tp_time', wp_date( 'H:i', $new ) );
		update_post_meta( $post_id, '_tp_pickup_at', wp_date( 'Y-m-d H:i', $new ) );
		/* translators: 1: flight 2: landing time 3: old pick-up 4: new pick-up */
		$note = sprintf( __( 'Flight %1$s now lands at %2$s. Pick-up moved from %3$s to %4$s.', 'taxi-peninsula' ), $b['flight'], $land, $pickup ? wp_date( 'g:i a', $pickup ) : '—', wp_date( 'g:i a', $new ) );
		$moved = true;
	} else {
		/* translators: 1: flight 2: landing time */
		$note  = sprintf( __( 'Flight %1$s now lands at %2$s. Check the pick-up time.', 'taxi-peninsula' ), $b['flight'], $land );
		$moved = false;
	}
	tp_in_site_lang(
		static function () use ( $post_id, $note ) {
			tp_add_log( $post_id, array( 'note' => $note ) );
		}
	);
	tp_flight_notify( $post_id, $note, $moved );
	return $note;
}

/**
 * Tell the office (email), the driver (SMS) and — if it moved — the customer (SMS).
 */
function tp_flight_notify( $post_id, $note, $moved ) {
	$b = tp_get_booking( $post_id );
	tp_in_site_lang(
		static function () use ( $b, $note, $post_id ) {
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				/* translators: 1: reference 2: flight */
				sprintf( __( 'Flight change for %1$s (%2$s)', 'taxi-peninsula' ), $b['reference'], $b['flight'] ),
				$note . "\n\n" . tp_booking_summary( $b ) . "\n" . __( 'Open booking', 'taxi-peninsula' ) . ': ' . admin_url( 'post.php?action=edit&post=' . $post_id ),
				tp_mail_headers()
			);
		}
	);
	if ( ! function_exists( 'tp_booking_sms' ) ) {
		return;
	}
	if ( $b['driver_id'] ) {
		tp_booking_sms( $post_id, get_user_meta( $b['driver_id'], 'tp_mobile', true ), $b['reference'] . ': ' . $note, 'driver' );
	}
	if ( $moved && $b['phone'] ) {
		$msg = sprintf(
			/* translators: 1: flight 2: new pick-up 3: site name */
			__( 'Your flight %1$s is running to a new time, so we have moved your wheelchair taxi pick-up to %2$s. No need to call. %3$s', 'taxi-peninsula' ),
			$b['flight'],
			tp_format_pickup( $b, 'g:ia' ),
			function_exists( 'tp_sms_site_name' ) ? tp_sms_site_name() : get_bloginfo( 'name' )
		);
		tp_booking_sms( $post_id, $b['phone'], $msg, 'customer' );
	}
}

// Every 15 minutes: flights landing in the next 30 hours.
add_filter(
	'cron_schedules',
	static function ( $s ) {
		$s['tp_quarter_hour'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => __( 'Every 15 minutes', 'taxi-peninsula' ) );
		return $s;
	}
);

add_action(
	'init',
	static function () {
		$next = wp_next_scheduled( 'tp_flight_watch' );
		if ( tp_flights_enabled() && ! $next ) {
			wp_schedule_event( time() + 60, 'tp_quarter_hour', 'tp_flight_watch' );
		} elseif ( ! tp_flights_enabled() && $next ) {
			wp_clear_scheduled_hook( 'tp_flight_watch' );
		}
	}
);

add_action( 'tp_flight_watch', 'tp_flight_watch_run' );
function tp_flight_watch_run() {
	if ( ! tp_flights_enabled() ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'post_status' => 'publish',
			'numberposts' => 100,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => '_tp_flight', 'value' => '', 'compare' => '!=' ),
				array( 'key' => '_tp_status', 'value' => array( 'pending', 'confirmed', 'assigned', 'accepted' ), 'compare' => 'IN' ),
				array( 'key' => '_tp_pickup_at', 'value' => array( wp_date( 'Y-m-d H:i', time() - 3 * HOUR_IN_SECONDS ), wp_date( 'Y-m-d H:i', time() + 30 * HOUR_IN_SECONDS ) ), 'compare' => 'BETWEEN' ),
			),
		)
	);
	foreach ( $ids as $id ) {
		tp_flight_check_booking( $id );
	}
	return count( $ids );
}

/**
 * Flight line for the dispatch board and driver screen.
 */
function tp_flight_badge( array $b ) {
	if ( '' === (string) $b['flight'] ) {
		return '';
	}
	$latest = (int) get_post_meta( $b['id'], '_tp_flight_latest', true );
	$status = (string) get_post_meta( $b['id'], '_tp_flight_status', true );
	$text   = sprintf(
		/* translators: %s: flight number */
		__( 'Flight %s', 'taxi-peninsula' ),
		$b['flight']
	);
	if ( $latest ) {
		/* translators: %s: time */
		$text .= ' · ' . sprintf( __( 'lands %s', 'taxi-peninsula' ), wp_date( 'g:i a', $latest ) );
	}
	if ( $status ) {
		$text .= ' · ' . $status;
	}
	return sprintf(
		'<p class="flight-badge">' . tp_icon( 'plane' ) . ' %1$s — <a href="%2$s" target="_blank" rel="noopener">%3$s<span class="screen-reader-text"> %4$s</span></a></p>',
		esc_html( $text ),
		esc_url( tp_flight_status_url( $b['flight'] ) ),
		esc_html__( 'live status', 'taxi-peninsula' ),
		esc_html__( '(opens in a new tab)', 'taxi-peninsula' )
	);
}

// Check straight away when a booking is made for today or tomorrow.
add_action(
	'tp_booking_created',
	static function ( $post_id ) {
		if ( tp_flights_enabled() && get_post_meta( $post_id, '_tp_flight', true ) ) {
			$date = get_post_meta( $post_id, '_tp_date', true );
			if ( $date && $date <= wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) ) {
				wp_schedule_single_event( time() + 30, 'tp_flight_check_one', array( (int) $post_id ) );
			}
		}
	},
	40
);
add_action( 'tp_flight_check_one', 'tp_flight_check_booking' );

// When the office edits the booking, measure the pick-up gap again from the new time.
add_action(
	'save_post_tp_booking',
	static function ( $post_id ) {
		if ( is_admin() && ! wp_doing_cron() && isset( $_POST['tp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the booking save handler.
			delete_post_meta( $post_id, '_tp_flight_gap' );
		}
	},
	30
);
