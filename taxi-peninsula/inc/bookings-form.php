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
	tp_render_booking_form();
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
			array( 'reference' => preg_match( '/^TP-[A-Z0-9]{6}$/', $ref ) ? $ref : '' )
		);
		return;
	}

	if ( isset( $_GET['tp_form'] ) ) {
		$key    = sanitize_key( wp_unslash( $_GET['tp_form'] ) );
		$stored = get_transient( 'tp_form_' . $key );
		if ( is_array( $stored ) ) {
			$state = $stored;
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
	$return = remove_query_arg( array( 'tp_form', 'booking', 'ref' ), $return );

	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_booking' ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Your session expired. Please check your details and submit again.', 'taxi-peninsula' ) ), $_POST );
	}

	// Honeypot: real visitors never fill this in.
	if ( ! empty( $_POST['tp_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'booking', 'received', $return ) . '#book' );
		exit;
	}

	$ip_key = 'tp_rl_' . md5( tp_client_ip() );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		tp_booking_fail(
			$return,
			array(
				/* translators: %s: phone number */
				'form' => sprintf( __( 'Too many bookings from your connection. Please call us on %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ),
			),
			$_POST
		);
	}

	$raw = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in tp_sanitize_booking().
	list( $data, $errors ) = tp_sanitize_booking( $raw, true );

	if ( empty( $raw['consent'] ) ) {
		$errors['consent'] = __( 'Please agree so we can contact you about this booking.', 'taxi-peninsula' );
	}

	if ( $errors ) {
		tp_booking_fail( $return, $errors, $raw );
	}

	$ref     = tp_new_reference();
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'tp_booking',
			'post_status'  => 'publish',
			'post_title'   => $ref . ' — ' . $data['name'],
			'post_excerpt' => implode( ' | ', array( $data['phone'], $data['email'], $data['pickup'], $data['dropoff'] ) ),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		tp_booking_fail(
			$return,
			/* translators: %s: phone number */
			array( 'form' => sprintf( __( 'Sorry, we could not save your booking. Please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ),
			$raw
		);
	}

	update_post_meta( $post_id, '_tp_reference', $ref );
	update_post_meta( $post_id, '_tp_status', 'pending' );
	tp_save_booking_meta( $post_id, $data );

	set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );

	tp_send_new_booking_emails( $post_id );

	wp_safe_redirect( add_query_arg( array( 'booking' => 'received', 'ref' => $ref ), $return ) . '#book' );
	exit;
}

/**
 * Store errors + input briefly and send the visitor back to the form.
 */
function tp_booking_fail( $return, array $errors, $old ) {
	$old = is_array( $old ) ? wp_unslash( $old ) : array();
	unset( $old['_tp_nonce'], $old['_tp_return'], $old['action'], $old['tp_website'] );
	$old = array_map(
		static function ( $v ) {
			return is_scalar( $v ) ? sanitize_textarea_field( (string) $v ) : '';
		},
		$old
	);

	$key = strtolower( wp_generate_password( 16, false ) );
	set_transient(
		'tp_form_' . $key,
		array(
			'errors' => $errors,
			'old'    => $old,
		),
		15 * MINUTE_IN_SECONDS
	);
	wp_safe_redirect( add_query_arg( 'tp_form', $key, $return ) . '#book' );
	exit;
}

function tp_mail_headers( $reply_to = '' ) {
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}
	return $headers;
}

function tp_send_new_booking_emails( $post_id ) {
	$b        = tp_get_booking( $post_id );
	$site     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$summary  = tp_booking_summary( $b );
	$admin_to = tp_opt( 'notify_email' ) ?: get_option( 'admin_email' );

	wp_mail(
		$admin_to,
		/* translators: 1: reference, 2: pick-up time */
		sprintf( __( 'New booking %1$s — %2$s', 'taxi-peninsula' ), $b['reference'], tp_format_pickup( $b ) ),
		$summary . "\n" . __( 'Manage this booking:', 'taxi-peninsula' ) . ' ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		tp_mail_headers( $b['email'] )
	);

	if ( tp_opt( 'customer_emails' ) && is_email( $b['email'] ) ) {
		$body  = sprintf(
			/* translators: %s: passenger name */
			__( 'Hi %s,', 'taxi-peninsula' ),
			$b['name']
		) . "\n\n";
		$body .= __( 'Thanks for booking with us. We have received your request and will confirm it shortly.', 'taxi-peninsula' ) . "\n\n";
		$body .= $summary . "\n";
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
	$body .= tp_booking_summary( $b ) . "\n";
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
