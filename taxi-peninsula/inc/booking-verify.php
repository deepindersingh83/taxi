<?php
/**
 * Getting the customer's phone and email right.
 *
 * - Fix-it link: for 30 minutes after booking, the confirmation screen lets
 *   the customer correct their phone number or email. The confirmation is
 *   sent again to the corrected details and the office is told.
 * - Text code (optional, Settings → SMS): first-time customers get a 4-digit
 *   code by text before their booking is sent, which proves the mobile is
 *   real. Only works when SMS is set up; customers who booked before, or
 *   confirmed their number before, are not asked again.
 *
 * The email typo catcher ("Did you mean …@gmail.com?") is in assets/js/main.js.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_FIX_MINUTES = 30;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['sms_verify_new'] = array( 'sms', __( 'Confirm first-time customers’ mobile numbers with a text code', 'taxi-peninsula' ), 'checkbox', false, __( 'Before a first booking is sent, the customer gets a 4-digit code by text and types it in. It proves the number is real and stops most fake bookings. Each code is one text message. Only works when SMS is set up; customers who booked before are not asked.', 'taxi-peninsula' ) );
		return $s;
	}
);

/* =========================================================================
 * Fix-it link on the confirmation screen.
 * ====================================================================== */

/** Minutes left to correct the details (0 when closed). */
function tp_fix_contact_minutes_left( $post_id ) {
	if ( ! in_array( get_post_meta( $post_id, '_tp_status', true ), array( 'pending', 'confirmed' ), true ) ) {
		return 0;
	}
	$left = TP_FIX_MINUTES * MINUTE_IN_SECONDS - ( time() - (int) get_post_time( 'U', true, $post_id ) );
	return $left > 0 ? (int) ceil( $left / MINUTE_IN_SECONDS ) : 0;
}

/** Every booking made in the same submission (repeat dates). */
function tp_submission_ids( $post_id ) {
	$sub = (int) get_post_meta( $post_id, '_tp_submission', true );
	if ( ! $sub ) {
		return array( (int) $post_id );
	}
	$ids = get_posts( array( 'post_type' => 'tp_booking', 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'meta_key' => '_tp_submission', 'meta_value' => $sub ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return array_map( 'intval', $ids ?: array( $post_id ) );
}

add_action(
	'tp_booking_success_contact',
	static function ( $post_id, $token ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
		$fixed = ! empty( $_GET['fixed'] );
		$error = isset( $_GET['fix_error'] ) ? sanitize_text_field( wp_unslash( $_GET['fix_error'] ) ) : '';
		// phpcs:enable
		$left = tp_fix_contact_minutes_left( $post_id );
		$b    = tp_get_booking( $post_id );
		if ( $fixed ) {
			echo '<p class="notice notice--success" role="status">' . esc_html__( 'Thanks — your details are updated and we’ve sent your confirmation again.', 'taxi-peninsula' ) . '</p>';
		}
		if ( ! $left ) {
			return;
		}
		?>
		<details class="fix-contact"<?php echo $error ? ' open' : ''; ?>>
			<summary><?php esc_html_e( 'Wrong phone number or email? Fix it now', 'taxi-peninsula' ); ?></summary>
			<p>
				<?php
				/* translators: 1: phone 2: email */
				echo esc_html( sprintf( __( 'We’ll contact you on %1$s and %2$s.', 'taxi-peninsula' ), $b['phone'], $b['email'] ?: '—' ) ) . ' ';
				/* translators: %d: minutes */
				echo esc_html( sprintf( _n( 'You can correct them here for the next %d minute.', 'You can correct them here for the next %d minutes.', $left, 'taxi-peninsula' ), $left ) );
				?>
			</p>
			<?php if ( $error ) : ?>
				<p class="notice notice--error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fix-contact__form">
				<input type="hidden" name="action" value="tp_fix_contact">
				<input type="hidden" name="ref" value="<?php echo esc_attr( $b['reference'] ); ?>">
				<input type="hidden" name="t" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="_tp_return" value="<?php echo esc_url( remove_query_arg( array( 'fixed', 'fix_error' ) ) ); ?>">
				<?php wp_nonce_field( 'tp_fix_contact', '_tp_fix_nonce' ); ?>
				<div class="field">
					<label for="fix-phone"><?php esc_html_e( 'Mobile / phone', 'taxi-peninsula' ); ?></label>
					<input id="fix-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required value="<?php echo esc_attr( $b['phone'] ); ?>">
				</div>
				<div class="field">
					<label for="fix-email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?></label>
					<input id="fix-email" name="email" type="email" autocomplete="email" value="<?php echo esc_attr( $b['email'] ); ?>"<?php echo $b['email'] ? ' required' : ''; ?>>
				</div>
				<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Save and send my confirmation again', 'taxi-peninsula' ); ?></button>
			</form>
		</details>
		<?php
	},
	10,
	2
);

add_action( 'admin_post_tp_fix_contact', 'tp_handle_fix_contact' );
add_action( 'admin_post_nopriv_tp_fix_contact', 'tp_handle_fix_contact' );
function tp_handle_fix_contact() {
	$return = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['_tp_return'] ?? '' ) ), home_url( '/' ) );
	$back   = static function ( $msg ) use ( $return ) {
		wp_safe_redirect( add_query_arg( 'fix_error', rawurlencode( $msg ), $return ) . '#book' );
		exit;
	};
	if ( ! isset( $_POST['_tp_fix_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_fix_nonce'] ) ), 'tp_fix_contact' ) ) {
		$back( __( 'Your session expired. Please try again.', 'taxi-peninsula' ) );
	}
	if ( ! tp_rate_limit( 'fix_contact', 6, 10 * MINUTE_IN_SECONDS ) ) {
		/* translators: %s: phone */
		$back( sprintf( __( 'Too many changes. Please call us on %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) );
	}
	$ref     = strtoupper( sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) );
	$post_id = preg_match( '/^TP-[A-Z0-9]{6}$/', $ref ) ? (int) tp_find_booking( $ref ) : 0;
	if ( ! $post_id || ! hash_equals( tp_booking_token( $post_id ), sanitize_key( wp_unslash( $_POST['t'] ?? '' ) ) ) ) {
		$back( __( 'We couldn’t find that booking. Please call us.', 'taxi-peninsula' ) );
	}
	if ( ! tp_fix_contact_minutes_left( $post_id ) ) {
		/* translators: %s: phone */
		$back( sprintf( __( 'The time to change these online has passed. Please call us on %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) );
	}
	$phone = tp_check_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	if ( is_wp_error( $phone ) ) {
		$back( $phone->get_error_message() );
	}
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$b     = tp_get_booking( $post_id );
	if ( ( '' !== $email || '' !== $b['email'] ) && ! is_email( $email ) ) {
		$back( __( 'Please enter a valid email address.', 'taxi-peninsula' ) );
	}
	$phone_changed = tp_phone_digits( $phone ) !== tp_phone_digits( $b['phone'] );
	$email_changed = strtolower( $email ) !== strtolower( $b['email'] );
	if ( ! $phone_changed && ! $email_changed ) {
		wp_safe_redirect( add_query_arg( 'fixed', 1, $return ) . '#book' );
		exit;
	}
	$ids = tp_submission_ids( $post_id );
	foreach ( $ids as $id ) {
		$old = tp_get_booking( $id );
		update_post_meta( $id, '_tp_phone', $phone );
		update_post_meta( $id, '_tp_email', $email );
		$excerpt = str_replace( array( $old['phone'], $old['email'] ), array( $phone, $email ), (string) get_post_field( 'post_excerpt', $id ) );
		wp_update_post( array( 'ID' => $id, 'post_excerpt' => $excerpt ) );
		delete_post_meta( $id, '_tp_suspect' ); // Re-checked below with the new details.
		tp_in_site_lang(
			static function () use ( $id, $old, $phone, $email, $phone_changed, $email_changed ) {
				$parts = array();
				if ( $phone_changed ) {
					/* translators: 1: old 2: new */
					$parts[] = sprintf( __( 'phone %1$s → %2$s', 'taxi-peninsula' ), $old['phone'], $phone );
				}
				if ( $email_changed ) {
					/* translators: 1: old 2: new */
					$parts[] = sprintf( __( 'email %1$s → %2$s', 'taxi-peninsula' ), $old['email'] ?: '—', $email );
				}
				/* translators: %s: what changed */
				tp_add_log( $id, array( 'note' => sprintf( __( 'Customer corrected their details online: %s.', 'taxi-peninsula' ), implode( ', ', $parts ) ) ) );
			}
		);
	}
	if ( function_exists( 'tp_suspect_reasons' ) ) {
		$why = tp_suspect_reasons( tp_get_booking( $ids[0] ), (string) get_post_meta( $ids[0], '_tp_device', true ), $ids );
		foreach ( $why ? $ids : array() as $id ) {
			update_post_meta( $id, '_tp_suspect', $why );
		}
	}
	// Send the confirmation again to the corrected details, and tell the office.
	if ( $email_changed ) {
		tp_send_booking_received_customer( $ids[0], $ids );
	}
	if ( $phone_changed && function_exists( 'tp_sms_booking_received_customer' ) ) {
		tp_sms_booking_received_customer( $ids[0] );
	}
	$nb = tp_get_booking( $ids[0] );
	tp_in_site_lang(
		static function () use ( $nb, $ids ) {
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				/* translators: %s: reference */
				sprintf( __( 'Contact details corrected — %s', 'taxi-peninsula' ), $nb['reference'] ),
				/* translators: 1: reference 2: phone 3: email */
				sprintf( __( 'The customer corrected their details for %1$s just after booking. Phone: %2$s. Email: %3$s.', 'taxi-peninsula' ), $nb['reference'], $nb['phone'], $nb['email'] ?: '—' ) . "\n\n" . admin_url( 'post.php?action=edit&post=' . $ids[0] ),
				tp_mail_headers()
			);
		}
	);
	wp_safe_redirect( add_query_arg( 'fixed', 1, $return ) . '#book' );
	exit;
}

/* =========================================================================
 * Text code for first-time customers.
 * ====================================================================== */

function tp_phone_hash( $digits ) {
	return substr( hash_hmac( 'sha256', 'phone|' . $digits, wp_salt( 'auth' ) ), 0, 16 );
}

/** Has this mobile booked or been confirmed before? */
function tp_phone_known( $digits ) {
	if ( isset( ( (array) get_option( 'tp_verified_phones', array() ) )[ tp_phone_hash( $digits ) ] ) ) {
		return true;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_tp_phone' AND meta_value LIKE %s LIMIT 300", '%' . $wpdb->esc_like( substr( $digits, -3 ) ) ) );
	foreach ( $rows as $r ) {
		if ( tp_phone_digits( $r->meta_value ) === $digits && 'publish' === get_post_status( $r->post_id ) && ! ( function_exists( 'tp_is_spam_booking' ) && tp_is_spam_booking( (int) $r->post_id ) ) ) {
			return true;
		}
	}
	return false;
}

function tp_remember_phone( $digits ) {
	$list                             = (array) get_option( 'tp_verified_phones', array() );
	$list[ tp_phone_hash( $digits ) ] = time();
	update_option( 'tp_verified_phones', array_slice( $list, -3000, null, true ), false );
}

function tp_booking_code_needed( array $data ) {
	if ( ! tp_setting( 'sms_verify_new' ) || ! tp_sms_enabled() || ! empty( $GLOBALS['tp_booking_code_ok'] ) ) {
		return false;
	}
	$p = tp_au_phone( $data['phone'] );
	return $p && 'mobile' === $p['type'] && ! tp_phone_known( tp_phone_digits( $p['display'] ) );
}

function tp_booking_code_hash( $code, $key ) {
	return hash_hmac( 'sha256', $key . '|' . $code, wp_salt( 'nonce' ) );
}

function tp_booking_code_send( $key, array &$state ) {
	$code                = (string) random_int( 1000, 9999 );
	$state['code']       = tp_booking_code_hash( $code, $key );
	$state['sent']       = time();
	$site                = function_exists( 'tp_sms_site_name' ) ? tp_sms_site_name() : get_bloginfo( 'name' );
	/* translators: 1: site 2: code */
	return tp_send_sms( $state['phone'], sprintf( __( '%1$s: your booking code is %2$s. It expires in 15 minutes.', 'taxi-peninsula' ), $site, $code ) );
}

// After every other check: first-time mobile? Text a code and ask for it before saving.
add_action(
	'tp_booking_checks_passed',
	static function ( $raw, $data, $return ) {
		if ( ! tp_booking_code_needed( $data ) ) {
			return;
		}
		if ( ! tp_rate_limit( 'booking_code', 6, HOUR_IN_SECONDS ) ) {
			/* translators: %s: phone */
			tp_booking_fail( $return, array( 'form' => sprintf( __( 'Too many codes sent. Please call us on %s to book.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw );
		}
		unset( $raw['_tp_nonce'], $raw['cf-turnstile-response'], $raw['g-recaptcha-response'] );
		$key   = strtolower( wp_generate_password( 20, false ) );
		$state = array( 'raw' => $raw, 'return' => $return, 'phone' => tp_au_phone( $data['phone'] )['display'], 'tries' => 0, 'resends' => 0 );
		$sent  = tp_booking_code_send( $key, $state );
		if ( is_wp_error( $sent ) ) {
			return; // Texts are not going out: take the booking rather than turn the customer away.
		}
		set_transient( 'tp_bcode_' . $key, $state, 15 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'bcode', $key, $return ) . '#book' );
		exit;
	},
	10,
	3
);

// The code screen, in place of the booking form.
add_filter(
	'tp_booking_form_screen',
	static function ( $done ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
		if ( $done || empty( $_GET['bcode'] ) ) {
			return $done;
		}
		$key   = sanitize_key( wp_unslash( $_GET['bcode'] ) );
		$state = get_transient( 'tp_bcode_' . $key );
		$err   = ! empty( $_GET['bcode_err'] );
		$sent  = ! empty( $_GET['resent'] );
		// phpcs:enable
		$form = static function ( $action, $fields = '' ) use ( $key ) {
			return '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="k" value="' . esc_attr( $key ) . '">' . wp_nonce_field( 'tp_booking_code', '_tp_code_nonce', true, false ) . $fields;
		};
		?>
		<div class="booking-code" role="region" aria-labelledby="bcode-title" tabindex="-1" data-focus>
			<?php if ( ! $state ) : ?>
				<h2 id="bcode-title"><?php esc_html_e( 'This code has expired', 'taxi-peninsula' ); ?></h2>
				<p><?php esc_html_e( 'Codes last 15 minutes. Please fill in the booking form again — it only takes a minute.', 'taxi-peninsula' ); ?></p>
				<p><a class="btn btn--primary" href="<?php echo esc_url( remove_query_arg( array( 'bcode', 'bcode_err', 'resent' ) ) . '#book' ); ?>"><?php esc_html_e( 'Start again', 'taxi-peninsula' ); ?></a></p>
			<?php else : ?>
				<h2 id="bcode-title"><?php esc_html_e( 'Check your phone', 'taxi-peninsula' ); ?></h2>
				<p>
					<?php
					/* translators: %s: mobile number */
					echo wp_kses( sprintf( __( 'We’ve texted a 4-digit code to %s. Type it in to send your booking.', 'taxi-peninsula' ), '<strong>' . esc_html( $state['phone'] ) . '</strong>' ), array( 'strong' => array() ) );
					?>
				</p>
				<?php if ( $err ) : ?>
					<p class="notice notice--error" role="alert"><?php esc_html_e( 'That code isn’t right. Please check the text and try again.', 'taxi-peninsula' ); ?></p>
				<?php elseif ( $sent ) : ?>
					<p class="notice notice--success" role="status"><?php esc_html_e( 'We’ve sent a new code.', 'taxi-peninsula' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="booking-code__form">
					<?php echo $form( 'tp_booking_code' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
					<div class="field">
						<label for="bcode-input"><?php esc_html_e( 'Code from the text message', 'taxi-peninsula' ); ?></label>
						<input id="bcode-input" name="code" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="one-time-code" required class="booking-code__input"<?php echo $err ? ' aria-invalid="true"' : ''; ?>>
					</div>
					<button type="submit" class="btn btn--accent btn--lg"><?php esc_html_e( 'Confirm and send my booking', 'taxi-peninsula' ); ?></button>
				</form>
				<div class="booking-code__more">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php echo $form( 'tp_booking_code_resend' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
						<button type="submit" class="btn-link"><?php esc_html_e( 'Text me a new code', 'taxi-peninsula' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php echo $form( 'tp_booking_code_back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
						<button type="submit" class="btn-link"><?php esc_html_e( 'Wrong number? Change it', 'taxi-peninsula' ); ?></button>
					</form>
				</div>
				<p class="booking-code__help">
					<?php
					/* translators: %s: phone */
					echo esc_html( sprintf( __( 'The text can take a minute to arrive. No luck? Call us on %s and we’ll book it for you.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return true;
	}
);

/** Load the waiting booking for a code action, or send the visitor back. */
function tp_booking_code_state( &$key ) {
	$key = sanitize_key( wp_unslash( $_POST['k'] ?? '' ) );
	if ( ! isset( $_POST['_tp_code_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_code_nonce'] ) ), 'tp_booking_code' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	$state = get_transient( 'tp_bcode_' . $key );
	if ( ! $state ) {
		wp_safe_redirect( add_query_arg( 'bcode', $key, wp_get_referer() ?: home_url( '/' ) ) . '#book' );
		exit;
	}
	return $state;
}

add_action( 'admin_post_tp_booking_code', 'tp_handle_booking_code' );
add_action( 'admin_post_nopriv_tp_booking_code', 'tp_handle_booking_code' );
function tp_handle_booking_code() {
	$key   = '';
	$state = tp_booking_code_state( $key );
	$code  = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['code'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- digits only.
	if ( ! hash_equals( $state['code'], tp_booking_code_hash( $code, $key ) ) ) {
		++$state['tries'];
		if ( $state['tries'] >= 5 ) {
			delete_transient( 'tp_bcode_' . $key );
			/* translators: %s: phone */
			tp_booking_fail( $state['return'], array( 'form' => sprintf( __( 'Too many wrong codes. Please check your mobile number and try again, or call us on %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $state['raw'] );
		}
		set_transient( 'tp_bcode_' . $key, $state, 15 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( array( 'bcode' => $key, 'bcode_err' => 1 ), $state['return'] ) . '#book' );
		exit;
	}
	delete_transient( 'tp_bcode_' . $key );
	tp_remember_phone( tp_phone_digits( $state['phone'] ) );
	// Save the booking exactly as it was submitted.
	$GLOBALS['tp_booking_code_ok'] = true;
	$_POST                         = array_merge( $state['raw'], array( '_tp_nonce' => wp_create_nonce( 'tp_booking' ), '_tp_return' => $state['return'] ) );
	tp_handle_booking();
}

// Note on the booking that the mobile was confirmed.
add_action(
	'tp_booking_created',
	static function ( $first_id, $ids ) {
		if ( empty( $GLOBALS['tp_booking_code_ok'] ) ) {
			return;
		}
		foreach ( $ids ?: array( $first_id ) as $id ) {
			update_post_meta( $id, '_tp_phone_verified', time() );
		}
		tp_in_site_lang(
			static function () use ( $first_id ) {
				tp_add_log( $first_id, array( 'note' => __( 'Mobile number confirmed with a text code.', 'taxi-peninsula' ) ) );
			}
		);
	},
	2,
	2
);

add_action( 'admin_post_tp_booking_code_resend', 'tp_handle_booking_code_resend' );
add_action( 'admin_post_nopriv_tp_booking_code_resend', 'tp_handle_booking_code_resend' );
function tp_handle_booking_code_resend() {
	$key   = '';
	$state = tp_booking_code_state( $key );
	if ( $state['resends'] < 3 && time() - $state['sent'] >= 30 && tp_rate_limit( 'booking_code', 6, HOUR_IN_SECONDS ) ) {
		++$state['resends'];
		tp_booking_code_send( $key, $state );
		set_transient( 'tp_bcode_' . $key, $state, 15 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( add_query_arg( array( 'bcode' => $key, 'resent' => 1 ), $state['return'] ) . '#book' );
	exit;
}

add_action( 'admin_post_tp_booking_code_back', 'tp_handle_booking_code_back' );
add_action( 'admin_post_nopriv_tp_booking_code_back', 'tp_handle_booking_code_back' );
function tp_handle_booking_code_back() {
	$key   = '';
	$state = tp_booking_code_state( $key );
	delete_transient( 'tp_bcode_' . $key );
	tp_booking_fail( $state['return'], array( 'phone' => __( 'Please check your mobile number.', 'taxi-peninsula' ) ), $state['raw'] );
}

// Bookings that were confirmed by code say so on the booking screen.
add_action(
	'tp_status_box_fields',
	static function ( $post ) {
		if ( get_post_meta( $post->ID, '_tp_phone_verified', true ) ) {
			echo '<p class="tp-verified"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Mobile confirmed with a text code', 'taxi-peninsula' ) . '</p>';
		}
	},
	5
);
