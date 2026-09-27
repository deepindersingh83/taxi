<?php
/**
 * SMS notifications through ClickSend or Twilio.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_sms_enabled() {
	$provider = tp_setting( 'sms_provider' );
	if ( 'clicksend' === $provider ) {
		return tp_setting( 'clicksend_username' ) && tp_setting( 'clicksend_api_key' );
	}
	if ( 'twilio' === $provider ) {
		return tp_setting( 'twilio_account_sid' ) && tp_setting( 'twilio_auth_token' ) && tp_setting( 'sms_from' );
	}
	return false;
}

/**
 * Send one SMS. Returns true or WP_Error.
 *
 * @param string $to      Phone number (Australian formats accepted).
 * @param string $message Message text.
 * @return true|WP_Error
 */
function tp_send_sms( $to, $message ) {
	if ( ! tp_sms_enabled() ) {
		return new WP_Error( 'tp_sms_disabled', __( 'SMS is not configured.', 'taxi-peninsula' ) );
	}
	$to = tp_e164( $to );
	if ( ! $to ) {
		return new WP_Error( 'tp_sms_number', __( 'Invalid mobile number.', 'taxi-peninsula' ) );
	}

	$message = wp_strip_all_tags( $message );
	$from    = (string) tp_setting( 'sms_from' );

	if ( 'clicksend' === tp_setting( 'sms_provider' ) ) {
		$msg = array(
			'source' => 'wordpress',
			'body'   => $message,
			'to'     => $to,
		);
		if ( $from ) {
			$msg['from'] = $from;
		}
		$response = wp_remote_post(
			'https://rest.clicksend.com/v3/sms/send',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( tp_setting( 'clicksend_username' ) . ':' . tp_setting( 'clicksend_api_key' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'messages' => array( $msg ) ) ),
			)
		);
	} else {
		$sid      = tp_setting( 'twilio_account_sid' );
		$response = wp_remote_post(
			'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $sid . ':' . tp_setting( 'twilio_auth_token' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				),
				'body'    => array(
					'To'   => $to,
					'From' => $from,
					'Body' => $message,
				),
			)
		);
	}

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$msg  = is_array( $body ) ? ( $body['response_msg'] ?? $body['message'] ?? '' ) : '';
		/* translators: 1: HTTP status, 2: provider message */
		return new WP_Error( 'tp_sms_failed', sprintf( __( 'SMS failed (%1$d) %2$s', 'taxi-peninsula' ), $code, $msg ) );
	}
	return true;
}

/**
 * Send an SMS about a booking and record the outcome in its history.
 */
function tp_booking_sms( $post_id, $to, $message, $who ) {
	if ( ! tp_sms_enabled() || ! $to ) {
		return;
	}
	$result = tp_send_sms( $to, $message );
	tp_add_log(
		$post_id,
		array(
			'note' => is_wp_error( $result )
				/* translators: 1: recipient, 2: error */
				? sprintf( __( 'SMS to %1$s failed: %2$s', 'taxi-peninsula' ), $who, $result->get_error_message() )
				/* translators: %s: recipient */
				: sprintf( __( 'SMS sent to %s', 'taxi-peninsula' ), $who ),
		)
	);
}

function tp_sms_site_name() {
	return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

add_action( 'tp_booking_created', 'tp_sms_on_created', 20, 2 );
function tp_sms_on_created( $post_id, $all_ids ) {
	$b     = tp_get_booking( $post_id );
	$count = count( $all_ids );

	if ( tp_setting( 'sms_office_number' ) ) {
		$msg = sprintf(
			/* translators: 1: reference, 2: pick-up time, 3: name, 4: phone, 5: from, 6: to */
			__( 'New booking %1$s: %2$s, %3$s (%4$s). %5$s → %6$s', 'taxi-peninsula' ),
			$b['reference'],
			tp_format_pickup( $b, 'D j M g:ia' ),
			$b['name'],
			$b['phone'],
			$b['pickup'],
			$b['dropoff']
		);
		if ( $count > 1 ) {
			/* translators: %d: number of trips */
			$msg .= ' ' . sprintf( __( '(repeat: %d trips)', 'taxi-peninsula' ), $count );
		}
		tp_booking_sms( $post_id, tp_setting( 'sms_office_number' ), $msg, __( 'office', 'taxi-peninsula' ) );
	}

	if ( tp_setting( 'sms_customer_received' ) ) {
		$msg = sprintf(
			/* translators: 1: site, 2: reference, 3: pick-up time, 4: link */
			__( '%1$s: we received your booking %2$s for %3$s. We will confirm shortly. Manage it: %4$s', 'taxi-peninsula' ),
			tp_sms_site_name(),
			$b['reference'],
			tp_format_pickup( $b, 'D j M g:ia' ),
			tp_booking_manage_url( $post_id )
		);
		tp_booking_sms( $post_id, $b['phone'], $msg, __( 'customer', 'taxi-peninsula' ) );
	}
}

add_action( 'tp_booking_status_changed', 'tp_sms_on_status', 20, 4 );
function tp_sms_on_status( $post_id, $old, $status, $notify ) {
	if ( ! $notify || ! tp_setting( 'sms_customer_status' ) ) {
		return;
	}
	$b    = tp_get_booking( $post_id );
	$when = tp_format_pickup( $b, 'D j M g:ia' );
	$site = tp_sms_site_name();

	switch ( $status ) {
		case 'confirmed':
			/* translators: 1: site, 2: reference, 3: pick-up time */
			$msg = sprintf( __( '%1$s: booking %2$s is confirmed for %3$s.', 'taxi-peninsula' ), $site, $b['reference'], $when );
			break;
		case 'assigned':
			$driver = $b['driver_id'] ? get_userdata( $b['driver_id'] ) : null;
			$fleet  = $b['fleet_id'] ? tp_fleet_label( $b['fleet_id'] ) : '';
			/* translators: 1: site, 2: reference, 3: pick-up time */
			$msg = sprintf( __( '%1$s: a driver is assigned to booking %2$s for %3$s.', 'taxi-peninsula' ), $site, $b['reference'], $when );
			if ( $driver ) {
				/* translators: %s: driver first name */
				$msg .= ' ' . sprintf( __( 'Driver: %s.', 'taxi-peninsula' ), $driver->first_name ?: $driver->display_name );
			}
			if ( $fleet ) {
				/* translators: %s: vehicle */
				$msg .= ' ' . sprintf( __( 'Vehicle: %s.', 'taxi-peninsula' ), $fleet );
			}
			break;
		case 'cancelled':
			/* translators: 1: site, 2: reference, 3: phone */
			$msg = sprintf( __( '%1$s: booking %2$s has been cancelled. Questions? Call %3$s.', 'taxi-peninsula' ), $site, $b['reference'], tp_opt( 'phone_display' ) );
			break;
		default:
			return;
	}
	tp_booking_sms( $post_id, $b['phone'], $msg, __( 'customer', 'taxi-peninsula' ) );
}

add_action( 'tp_driver_assigned', 'tp_sms_driver_assigned', 20, 3 );
function tp_sms_driver_assigned( $post_id, $driver_id, $notify ) {
	if ( ! tp_setting( 'sms_driver_assigned' ) ) {
		return;
	}
	$phone = get_user_meta( $driver_id, 'tp_mobile', true );
	if ( ! $phone ) {
		return;
	}
	$b   = tp_get_booking( $post_id );
	$msg = sprintf(
		/* translators: 1: reference, 2: pick-up time, 3: from, 4: to, 5: name, 6: phone */
		__( 'New job %1$s: %2$s. %3$s → %4$s. %5$s %6$s', 'taxi-peninsula' ),
		$b['reference'],
		tp_format_pickup( $b, 'D j M g:ia' ),
		$b['pickup'],
		$b['dropoff'],
		$b['name'],
		$b['phone']
	);
	$jobs = tp_page_url_by_template( 'driver' );
	if ( $jobs ) {
		$msg .= ' ' . $jobs;
	}
	tp_booking_sms( $post_id, $phone, $msg, __( 'driver', 'taxi-peninsula' ) );
}

add_action( 'admin_post_tp_test_sms', 'tp_handle_test_sms' );
function tp_handle_test_sms() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'tp_test_sms' );
	$to     = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
	$result = tp_send_sms( $to, sprintf( /* translators: %s: site name */ __( 'Test message from %s. SMS is working.', 'taxi-peninsula' ), tp_sms_site_name() ) );
	set_transient(
		'tp_admin_notice_' . get_current_user_id(),
		is_wp_error( $result ) ? array( 'error', $result->get_error_message() ) : array( 'success', __( 'Test SMS sent.', 'taxi-peninsula' ) ),
		60
	);
	wp_safe_redirect( admin_url( 'edit.php?post_type=tp_booking&page=tp-settings' ) );
	exit;
}

add_action(
	'admin_notices',
	static function () {
		$key    = 'tp_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( is_array( $notice ) ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice[0] ), esc_html( $notice[1] ) );
		}
	}
);
