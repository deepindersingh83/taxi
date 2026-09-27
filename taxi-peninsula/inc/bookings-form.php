<?php
/**
 * Front-end booking form: shortcode, submission handler and emails.
 *
 * Use [tp_booking_form] on any page, or the "Book a Taxi" page template.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'tp_booking_form', 'tp_booking_form_shortcode' );
function tp_booking_form_shortcode() {
	ob_start();
	echo '<div class="tp-component">';
	tp_render_booking_form();
	echo '</div>';
	return ob_get_clean();
}

/**
 * Render the form, a success panel, or the form with validation errors.
 */
function tp_render_booking_form() {
	$state = array(
		'errors' => array(),
		'old'    => array(),
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display state.
	if ( isset( $_GET['booking'] ) && 'received' === $_GET['booking'] ) {
		$ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
		get_template_part(
			'template-parts/booking',
			'success',
			array(
				'reference' => preg_match( '/^TP-[A-Z0-9]{6}$/', $ref ) ? $ref : '',
				'count'     => isset( $_GET['trips'] ) ? absint( $_GET['trips'] ) : 1,
				'pay_error' => ! empty( $_GET['pay_error'] ),
			)
		);
		return;
	}

	if ( isset( $_GET['tp_form'] ) ) {
		$key    = sanitize_key( wp_unslash( $_GET['tp_form'] ) );
		$stored = get_transient( 'tp_form_' . $key );
		if ( is_array( $stored ) ) {
			$state = $stored;
		}
	} else {
		// Prefill from links and the hero quick-book bar: ?pickup=…&dropoff=…&date=YYYY-MM-DD.
		foreach ( array( 'pickup', 'dropoff' ) as $prefill ) {
			if ( ! empty( $_GET[ $prefill ] ) ) {
				$state['old'][ $prefill ] = sanitize_text_field( wp_unslash( $_GET[ $prefill ] ) );
			}
		}
		foreach ( array( 'date' => '/^\d{4}-\d{2}-\d{2}$/', 'time' => '/^\d{2}:\d{2}$/' ) as $prefill => $pattern ) {
			$v = isset( $_GET[ $prefill ] ) ? sanitize_text_field( wp_unslash( $_GET[ $prefill ] ) ) : '';
			if ( preg_match( $pattern, $v ) ) {
				$state['old'][ $prefill ] = $v;
			}
		}
		$state['quickbook'] = ! empty( $_GET['pickup'] ) || ! empty( $_GET['dropoff'] );
		if ( isset( $_GET['payment'] ) ) {
			$state['old']['payment'] = sanitize_key( wp_unslash( $_GET['payment'] ) );
		}
	}
	// phpcs:enable

	get_template_part( 'template-parts/booking', 'form', $state );
}

add_action( 'admin_post_nopriv_tp_booking', 'tp_handle_booking' );
add_action( 'admin_post_tp_booking', 'tp_handle_booking' );
function tp_handle_booking() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : '';
	$return = wp_validate_redirect( $return, home_url( '/' ) );
	$return = remove_query_arg( array( 'tp_form', 'booking', 'ref', 'trips', 'pay_error', 'pickup', 'dropoff', 'payment' ), $return );

	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_booking' ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Your session expired. Please check your details and submit again.', 'taxi-peninsula' ) ), $_POST );
	}

	if ( tp_is_honeypot_hit() ) {
		wp_safe_redirect( add_query_arg( 'booking', 'received', $return ) . '#book' );
		exit;
	}

	$raw = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in tp_sanitize_booking().

	if ( ! tp_rate_limit( 'booking', 5, 10 * MINUTE_IN_SECONDS, false ) ) {
		/* translators: %s: phone number */
		tp_booking_fail( $return, array( 'form' => sprintf( __( 'Too many bookings from your connection. Please call us on %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw );
	}

	if ( ! tp_verify_captcha() ) {
		tp_booking_fail( $return, array( 'form' => tp_captcha_error_message() ), $raw );
	}

	list( $data, $errors ) = tp_sanitize_booking( $raw, true );

	list( $dates, $repeat_error ) = tp_recurring_dates( $raw, $data['date'] );
	if ( $repeat_error ) {
		$errors['repeat'] = $repeat_error;
	}

	if ( empty( $raw['consent'] ) ) {
		$errors['consent'] = __( 'Please agree so we can contact you about this booking.', 'taxi-peninsula' );
	}
	if ( tp_terms_url() && empty( $raw['terms'] ) ) {
		$errors['terms'] = __( 'Please accept the terms and cancellation policy.', 'taxi-peninsula' );
	}

	if ( $errors ) {
		tp_booking_fail( $return, $errors, $raw );
	}

	$series = count( $dates ) > 1 ? tp_new_reference() : '';
	$ids    = array();
	foreach ( $dates as $date ) {
		$trip         = $data;
		$trip['date'] = $date;
		$ref          = tp_new_reference();
		$post_id      = wp_insert_post(
			array_merge(
				array(
					'post_type'   => 'tp_booking',
					'post_status' => 'publish',
				),
				tp_booking_post_fields( $ref, $trip )
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			break;
		}
		update_post_meta( $post_id, '_tp_reference', $ref );
		update_post_meta( $post_id, '_tp_status', 'pending' );
		if ( $series ) {
			update_post_meta( $post_id, '_tp_series', $series );
		}
		tp_save_booking_meta( $post_id, $trip );
		if ( tp_terms_url() ) {
			update_post_meta( $post_id, '_tp_terms_accepted', current_time( 'mysql' ) );
		}
		$ids[] = $post_id;
	}

	if ( ! $ids ) {
		tp_booking_fail(
			$return,
			/* translators: %s: phone number */
			array( 'form' => sprintf( __( 'Sorry, we could not save your booking. Please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ),
			$raw
		);
	}

	tp_rate_limit( 'booking', 5, 10 * MINUTE_IN_SECONDS, true );

	/**
	 * Fires once per submission (not per repeat trip).
	 *
	 * @param int   $first_id First booking ID.
	 * @param int[] $ids      All booking IDs created (more than one for repeat trips).
	 */
	do_action( 'tp_booking_created', $ids[0], $ids );

	$first_ref = get_post_meta( $ids[0], '_tp_reference', true );
	$args      = array(
		'booking' => 'received',
		'ref'     => $first_ref,
	);
	if ( count( $ids ) > 1 ) {
		$args['trips'] = count( $ids );
	}

	if ( 'online' === $data['payment'] ) {
		$url = tp_stripe_checkout_url( $ids[0] );
		if ( ! is_wp_error( $url ) ) {
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Stripe-hosted checkout.
			exit;
		}
		$args['pay_error'] = 1;
	}

	wp_safe_redirect( add_query_arg( $args, $return ) . '#book' );
	exit;
}

/**
 * Store errors + input briefly and send the visitor back to the form.
 */
function tp_booking_fail( $return, array $errors, $old, $anchor = '#book' ) {
	$old = is_array( $old ) ? $old : array();
	unset( $old['_tp_nonce'], $old['_tp_return'], $old['action'], $old['tp_website'], $old['cf-turnstile-response'], $old['g-recaptcha-response'] );
	$clean = array();
	foreach ( $old as $k => $v ) {
		if ( is_array( $v ) ) {
			$clean[ $k ] = array_map( 'sanitize_text_field', array_filter( $v, 'is_scalar' ) );
		} elseif ( is_scalar( $v ) ) {
			$clean[ $k ] = sanitize_textarea_field( (string) $v );
		}
	}

	$key = strtolower( wp_generate_password( 16, false ) );
	set_transient(
		'tp_form_' . $key,
		array(
			'errors' => $errors,
			'old'    => $clean,
		),
		15 * MINUTE_IN_SECONDS
	);
	wp_safe_redirect( add_query_arg( 'tp_form', $key, $return ) . $anchor );
	exit;
}

function tp_mail_headers( $reply_to = '' ) {
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}
	return $headers;
}

/**
 * List of dates for a repeat booking, for emails.
 */
function tp_series_dates_text( array $ids ) {
	$out = '';
	foreach ( $ids as $id ) {
		$b    = tp_get_booking( $id );
		$out .= '- ' . tp_format_pickup( $b ) . ' (' . $b['reference'] . ")\n";
	}
	return $out;
}

add_action( 'tp_booking_created', 'tp_send_new_booking_emails', 10, 2 );
function tp_send_new_booking_emails( $post_id, $ids = array() ) {
	$ids      = $ids ?: array( $post_id );
	$b        = tp_get_booking( $post_id );
	$site     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$summary  = tp_booking_summary( $b );
	$admin_to = tp_opt( 'notify_email' ) ?: get_option( 'admin_email' );
	$series   = count( $ids ) > 1
		/* translators: %d: number of trips */
		? "\n" . sprintf( __( 'Repeat booking — %d trips:', 'taxi-peninsula' ), count( $ids ) ) . "\n" . tp_series_dates_text( $ids )
		: '';

	wp_mail(
		$admin_to,
		/* translators: 1: reference, 2: pick-up time */
		sprintf( __( 'New booking %1$s — %2$s', 'taxi-peninsula' ), $b['reference'], tp_format_pickup( $b ) ) . ( $series ? ' ' . __( '(repeat)', 'taxi-peninsula' ) : '' ),
		$summary . $series . "\n" . __( 'Manage this booking:', 'taxi-peninsula' ) . ' ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		tp_mail_headers( $b['email'] )
	);

	if ( tp_opt( 'customer_emails' ) && is_email( $b['email'] ) ) {
		/* translators: %s: passenger name */
		$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $b['name'] ) . "\n\n";
		$body .= __( 'Thanks for booking with us. We have received your request and will confirm it shortly.', 'taxi-peninsula' ) . "\n\n";
		$body .= $summary . $series . "\n";
		$body .= __( 'View or manage your booking:', 'taxi-peninsula' ) . ' ' . tp_booking_manage_url( $post_id ) . "\n\n";
		$body .= sprintf(
			/* translators: 1: phone, 2: email */
			__( 'Need to change something? Call %1$s or email %2$s and quote your reference.', 'taxi-peninsula' ),
			tp_opt( 'phone_display' ),
			tp_opt( 'email' )
		) . "\n\n" . $site . "\n";

		wp_mail(
			$b['email'],
			/* translators: 1: site name, 2: reference */
			sprintf( __( '%1$s booking received — %2$s', 'taxi-peninsula' ), $site, $b['reference'] ),
			$body,
			tp_mail_headers( tp_opt( 'email' ) )
		);
	}
}

add_action( 'tp_booking_status_changed', 'tp_email_on_status', 10, 4 );
function tp_email_on_status( $post_id, $old, $status, $notify ) {
	if ( $notify ) {
		tp_send_status_email( $post_id );
	}
}

/**
 * Let the customer know when a booking changes status.
 */
function tp_send_status_email( $post_id ) {
	$b = tp_get_booking( $post_id );
	if ( ! is_email( $b['email'] ) ) {
		return false;
	}
	$site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$status = tp_statuses()[ $b['status'] ] ?? $b['status'];

	$messages = array(
		'confirmed' => __( 'Good news — your booking is confirmed.', 'taxi-peninsula' ),
		'assigned'  => __( 'A driver has been assigned to your booking.', 'taxi-peninsula' ),
		'completed' => __( 'Thanks for travelling with us. We hope to see you again soon.', 'taxi-peninsula' ),
		'cancelled' => __( 'Your booking has been cancelled. If this is unexpected, please call us.', 'taxi-peninsula' ),
		'pending'   => __( 'Your booking is being reviewed.', 'taxi-peninsula' ),
	);

	/* translators: %s: passenger name */
	$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $b['name'] ) . "\n\n";
	$body .= ( $messages[ $b['status'] ] ?? '' ) . "\n\n";
	if ( 'assigned' === $b['status'] && $b['driver_id'] ) {
		$driver = get_userdata( $b['driver_id'] );
		if ( $driver ) {
			/* translators: %s: driver first name */
			$body .= sprintf( __( 'Your driver: %s', 'taxi-peninsula' ), $driver->first_name ?: $driver->display_name ) . "\n";
		}
		if ( $b['fleet_id'] ) {
			/* translators: %s: vehicle */
			$body .= sprintf( __( 'Vehicle: %s', 'taxi-peninsula' ), tp_fleet_label( $b['fleet_id'] ) ) . "\n";
		}
		$body .= "\n";
	}
	$body .= tp_booking_summary( $b ) . "\n";
	$body .= __( 'View or manage your booking:', 'taxi-peninsula' ) . ' ' . tp_booking_manage_url( $post_id ) . "\n\n";
	/* translators: %s: phone number */
	$body .= sprintf( __( 'Questions? Call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) . "\n\n" . $site . "\n";

	return wp_mail(
		$b['email'],
		/* translators: 1: reference, 2: status */
		sprintf( __( 'Booking %1$s: %2$s', 'taxi-peninsula' ), $b['reference'], $status ),
		$body,
		tp_mail_headers( tp_opt( 'email' ) )
	);
}
