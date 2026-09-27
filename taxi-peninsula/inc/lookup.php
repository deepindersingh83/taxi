<?php
/**
 * Customer booking lookup: [tp_booking_lookup]
 *
 * Passengers enter their reference + phone number (or follow the link in their
 * email/SMS) to see the booking status, request a change or cancellation, and
 * pay an outstanding online deposit.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'tp_booking_lookup', 'tp_lookup_shortcode' );
function tp_lookup_shortcode() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- access is controlled by the HMAC token.
	$ref   = isset( $_GET['ref'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ) : '';
	$token = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '';
	$state = array(
		'errors' => array(),
		'old'    => array(),
	);
	if ( isset( $_GET['tp_form'] ) ) {
		$stored = get_transient( 'tp_form_' . sanitize_key( wp_unslash( $_GET['tp_form'] ) ) );
		if ( is_array( $stored ) ) {
			$state = $stored;
		}
	}
	$flags = array(
		'requested' => ! empty( $_GET['requested'] ),
		'paid'      => isset( $_GET['paid'] ) ? sanitize_key( wp_unslash( $_GET['paid'] ) ) : '',
	);
	// phpcs:enable

	ob_start();
	echo '<div class="tp-component" id="lookup">';

	$post_id = $ref ? tp_find_booking( $ref ) : 0;
	if ( $post_id && $token && hash_equals( tp_booking_token( $post_id ), $token ) ) {
		get_template_part(
			'template-parts/booking',
			'status',
			array_merge(
				$flags,
				array(
					'booking' => tp_get_booking( $post_id ),
					'errors'  => $state['errors'],
				)
			)
		);
	} else {
		get_template_part( 'template-parts/booking', 'lookup-form', $state );
	}

	echo '</div>';
	return ob_get_clean();
}

function tp_lookup_return_url() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies nonce.
	$return = wp_validate_redirect( $return, tp_page_url_by_template( 'lookup', home_url( '/' ) ) );
	return remove_query_arg( array( 'tp_form', 'ref', 't', 'requested', 'paid' ), $return );
}

/**
 * Verify the ref + token posted from the status view. Returns booking ID or 0.
 */
function tp_lookup_posted_booking() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- caller verifies nonce.
	$ref     = isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '';
	$token   = isset( $_POST['t'] ) ? sanitize_key( wp_unslash( $_POST['t'] ) ) : '';
	// phpcs:enable
	$post_id = tp_find_booking( $ref );
	return ( $post_id && $token && hash_equals( tp_booking_token( $post_id ), $token ) ) ? $post_id : 0;
}

add_action( 'admin_post_nopriv_tp_lookup', 'tp_handle_lookup' );
add_action( 'admin_post_tp_lookup', 'tp_handle_lookup' );
function tp_handle_lookup() {
	$return = tp_lookup_return_url();
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- verified below; values sanitized individually.

	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_lookup' ) ) {
		tp_booking_fail( $return, array( 'form' => __( 'Your session expired. Please try again.', 'taxi-peninsula' ) ), $raw, '#lookup' );
	}
	if ( tp_is_honeypot_hit() ) {
		wp_safe_redirect( $return );
		exit;
	}
	if ( ! tp_rate_limit( 'lookup', 10, 15 * MINUTE_IN_SECONDS, false ) ) {
		/* translators: %s: phone */
		tp_booking_fail( $return, array( 'form' => sprintf( __( 'Too many attempts. Please wait a few minutes or call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw, '#lookup' );
	}
	if ( ! tp_verify_captcha() ) {
		tp_booking_fail( $return, array( 'form' => tp_captcha_error_message() ), $raw, '#lookup' );
	}

	$ref     = strtoupper( sanitize_text_field( $raw['ref'] ?? '' ) );
	$phone   = tp_phone_digits( sanitize_text_field( $raw['phone'] ?? '' ) );
	$post_id = tp_find_booking( $ref );

	$stored = $post_id ? tp_phone_digits( get_post_meta( $post_id, '_tp_phone', true ) ) : '';
	// Compare the last 8 digits so "0412…" and "+61 412…" both match.
	$match = $post_id && strlen( $phone ) >= 8 && hash_equals( substr( $stored, -8 ), substr( $phone, -8 ) );

	if ( ! $match ) {
		tp_rate_limit( 'lookup', 10, 15 * MINUTE_IN_SECONDS, true );
		tp_booking_fail( $return, array( 'form' => __( 'We could not find a booking with that reference and phone number. Please check both and try again.', 'taxi-peninsula' ) ), $raw, '#lookup' );
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'ref' => $ref,
				't'   => tp_booking_token( $post_id ),
			),
			$return
		) . '#lookup'
	);
	exit;
}

add_action( 'admin_post_nopriv_tp_booking_request', 'tp_handle_booking_request' );
add_action( 'admin_post_tp_booking_request', 'tp_handle_booking_request' );
function tp_handle_booking_request() {
	$return  = tp_lookup_return_url();
	$post_id = tp_lookup_posted_booking();
	if ( ! $post_id || ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_request_' . $post_id ) ) {
		wp_safe_redirect( $return );
		exit;
	}

	$args = array(
		'ref' => get_post_meta( $post_id, '_tp_reference', true ),
		't'   => tp_booking_token( $post_id ),
	);

	$type    = isset( $_POST['request_type'] ) ? sanitize_key( wp_unslash( $_POST['request_type'] ) ) : '';
	$message = isset( $_POST['request_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['request_message'] ) ) : '';
	$types   = tp_request_types();

	if ( ! isset( $types[ $type ] ) || ( 'change' === $type && '' === trim( $message ) ) ) {
		$key = strtolower( wp_generate_password( 16, false ) );
		set_transient(
			'tp_form_' . $key,
			array(
				'errors' => array( 'request' => __( 'Please choose an option and tell us what you would like to change.', 'taxi-peninsula' ) ),
				'old'    => array(),
			),
			15 * MINUTE_IN_SECONDS
		);
		$args['tp_form'] = $key;
		wp_safe_redirect( add_query_arg( $args, $return ) . '#lookup' );
		exit;
	}

	if ( ! tp_rate_limit( 'request', 6, HOUR_IN_SECONDS ) ) {
		wp_safe_redirect( add_query_arg( $args, $return ) . '#lookup' );
		exit;
	}

	$request = array(
		'type'    => $type,
		'message' => $message,
		'time'    => current_time( 'mysql' ),
	);
	update_post_meta( $post_id, '_tp_request', $request );
	tp_add_log( $post_id, array( 'note' => sprintf( '%s: %s', $types[ $type ], $message ) ) );

	$b = tp_get_booking( $post_id );
	wp_mail(
		tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
		/* translators: 1: request type, 2: reference */
		sprintf( __( '%1$s — %2$s', 'taxi-peninsula' ), $types[ $type ], $b['reference'] ),
		$types[ $type ] . "\n\n" . ( $message ? $message . "\n\n" : '' ) . tp_booking_summary( $b ) . "\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		tp_mail_headers( $b['email'] )
	);
	if ( tp_setting( 'sms_office_number' ) ) {
		tp_booking_sms(
			$post_id,
			tp_setting( 'sms_office_number' ),
			/* translators: 1: request type, 2: reference, 3: pick-up time */
			sprintf( __( '%1$s for %2$s (%3$s). Check email/admin.', 'taxi-peninsula' ), $types[ $type ], $b['reference'], tp_format_pickup( $b, 'D j M g:ia' ) ),
			__( 'office', 'taxi-peninsula' )
		);
	}

	$args['requested'] = 1;
	wp_safe_redirect( add_query_arg( $args, $return ) . '#lookup' );
	exit;
}

function tp_request_types() {
	return array(
		'cancel'        => __( 'Cancellation request', 'taxi-peninsula' ),
		'cancel_series' => __( 'Cancel all remaining repeat trips', 'taxi-peninsula' ),
		'change'        => __( 'Change request', 'taxi-peninsula' ),
	);
}

add_action( 'admin_post_nopriv_tp_pay', 'tp_handle_pay' );
add_action( 'admin_post_tp_pay', 'tp_handle_pay' );
function tp_handle_pay() {
	$return  = tp_lookup_return_url();
	$post_id = tp_lookup_posted_booking();
	if ( ! $post_id || ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_pay_' . $post_id ) ) {
		wp_safe_redirect( $return );
		exit;
	}
	$url = tp_stripe_checkout_url( $post_id );
	if ( is_wp_error( $url ) ) {
		wp_safe_redirect( add_query_arg( array( 'ref' => get_post_meta( $post_id, '_tp_reference', true ), 't' => tp_booking_token( $post_id ), 'paid' => 'error' ), $return ) . '#lookup' );
		exit;
	}
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Stripe-hosted checkout.
	exit;
}

/**
 * Other trips in the same repeat series (for the status page).
 *
 * @return int[]
 */
function tp_series_ids( $series ) {
	if ( ! $series ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 200,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_tp_series', 'value' => $series ),
			),
		)
	);
}
