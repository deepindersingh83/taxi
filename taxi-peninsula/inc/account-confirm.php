<?php
/**
 * Confirm new accounts (Settings → Spam protection).
 *
 * When it is on, a new account gets a 6-digit code by text (when SMS is set up
 * and the number is a mobile) and always a confirm link by email. Either one
 * confirms the account. Customers can book straight away; until they confirm,
 * their bookings carry a mark for the office:
 *
 * - "Unverified customer": the account is not confirmed yet.
 * - "Phone not confirmed": confirmed by email, but not the mobile.
 * - "✓ Phone confirmed": the mobile on the booking was confirmed by text.
 *
 * Changing the mobile in My details asks for it to be confirmed again. Accounts
 * made before this was turned on count as confirmed.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_CONFIRM_LINK_DAYS = 7;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['signup_confirm'] = array( 'captcha', __( 'Confirm new accounts', 'taxi-peninsula' ), 'checkbox', false, __( 'New accounts get a code by text (when SMS is set up) and a confirm link by email; either one confirms the account. Customers can still book before confirming, and those bookings are marked “Unverified customer” or “Phone not confirmed” on the dispatch board.', 'taxi-peninsula' ) );
		return $s;
	}
);

/**
 * Where an account stands.
 *
 * @param int $user_id User.
 * @return array{confirmed:bool,email:bool,phone:bool,pending:bool}
 */
function tp_account_confirm_state( $user_id ) {
	$pending = (bool) get_user_meta( $user_id, 'tp_confirm_pending', true );
	$email   = (bool) get_user_meta( $user_id, 'tp_email_ok', true );
	$phone   = (bool) get_user_meta( $user_id, 'tp_phone_ok', true );
	return array(
		'pending'   => $pending,
		'email'     => $email,
		'phone'     => $phone,
		// Accounts from before (no pending flag) count as confirmed.
		'confirmed' => ! $pending || $email || $phone,
	);
}

/** Can a code go to this account's number by text? */
function tp_account_can_text( $user_id ) {
	$p = tp_au_phone( (string) get_user_meta( $user_id, 'tp_mobile', true ) );
	return tp_sms_enabled() && $p && 'mobile' === $p['type'];
}

function tp_confirm_hash( $value, $user_id ) {
	return hash_hmac( 'sha256', $user_id . '|' . $value, wp_salt( 'nonce' ) );
}

/** Text a fresh 6-digit code. */
function tp_account_send_code( $user_id ) {
	$p = tp_au_phone( (string) get_user_meta( $user_id, 'tp_mobile', true ) );
	if ( ! $p || ! tp_sms_enabled() ) {
		return false;
	}
	$code = (string) random_int( 100000, 999999 );
	update_user_meta( $user_id, 'tp_confirm_code', array( 'hash' => tp_confirm_hash( $code . '|' . $p['e164'], $user_id ), 'sent' => time(), 'tries' => 0 ) );
	$site = tp_sms_site_name();
	/* translators: 1: site 2: code */
	return ! is_wp_error( tp_send_sms( $p['display'], sprintf( __( '%1$s: your code to confirm your account is %2$s. It expires in 30 minutes.', 'taxi-peninsula' ), $site, $code ) ) );
}

/** Email a fresh confirm link. */
function tp_account_send_link( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return false;
	}
	$token = strtolower( wp_generate_password( 32, false ) );
	update_user_meta( $user_id, 'tp_confirm_token', array( 'hash' => tp_confirm_hash( $token, $user_id ), 'until' => time() + TP_CONFIRM_LINK_DAYS * DAY_IN_SECONDS ) );
	$url  = add_query_arg( 'tp_confirm', $user_id . '-' . $token, home_url( '/' ) );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: %s: first name */
	$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $user->first_name ?: $user->display_name ) . "\n\n";
	$body .= __( 'Please confirm your account by opening this link:', 'taxi-peninsula' ) . "\n" . $url . "\n\n";
	/* translators: %d: days */
	$body .= sprintf( __( 'The link works for %d days. You can book in the meantime.', 'taxi-peninsula' ), TP_CONFIRM_LINK_DAYS ) . "\n\n";
	$body .= __( 'Didn’t make an account with us? You can ignore this email.', 'taxi-peninsula' ) . "\n\n" . $site . "\n";
	/* translators: %s: site name */
	return wp_mail( $user->user_email, sprintf( __( 'Confirm your %s account', 'taxi-peninsula' ), $site ), $body, tp_mail_headers( tp_opt( 'email' ) ) );
}

// New account: mark it as waiting and send the code and the link.
add_action(
	'tp_account_registered',
	static function ( $user_id ) {
		if ( ! tp_setting( 'signup_confirm' ) ) {
			return;
		}
		update_user_meta( $user_id, 'tp_confirm_pending', time() );
		if ( tp_account_can_text( $user_id ) && tp_rate_limit( 'confirm_sms', 6, HOUR_IN_SECONDS ) ) {
			tp_account_send_code( $user_id );
		}
		tp_account_send_link( $user_id );
	}
);

// New mobile in My details: ask for it to be confirmed again.
add_action(
	'tp_account_phone_changed',
	static function ( $user_id, $was, $phone ) {
		delete_user_meta( $user_id, 'tp_confirm_code' );
		if ( ! tp_setting( 'signup_confirm' ) ) {
			delete_user_meta( $user_id, 'tp_phone_ok' );
			return;
		}
		$state = tp_account_confirm_state( $user_id );
		if ( ! $state['pending'] ) {
			// An older account: it stays confirmed (by email), only the new mobile is not.
			update_user_meta( $user_id, 'tp_confirm_pending', time() );
			update_user_meta( $user_id, 'tp_email_ok', time() );
		}
		delete_user_meta( $user_id, 'tp_phone_ok' );
		if ( '' !== $phone && tp_account_can_text( $user_id ) && tp_rate_limit( 'confirm_sms', 6, HOUR_IN_SECONDS ) ) {
			tp_account_send_code( $user_id );
		}
		tp_account_refresh_marks( $user_id );
	},
	10,
	3
);

/** Record a confirmation and update the marks on upcoming bookings. */
function tp_account_confirmed( $user_id, $by ) {
	if ( 'phone' === $by ) {
		update_user_meta( $user_id, 'tp_phone_ok', time() );
		delete_user_meta( $user_id, 'tp_confirm_code' );
		if ( function_exists( 'tp_remember_phone' ) ) {
			tp_remember_phone( tp_phone_digits( (string) get_user_meta( $user_id, 'tp_mobile', true ) ) );
		}
	} else {
		update_user_meta( $user_id, 'tp_email_ok', time() );
		delete_user_meta( $user_id, 'tp_confirm_token' );
	}
	tp_account_refresh_marks( $user_id );
}

/* =========================================================================
 * Marks on bookings.
 * ====================================================================== */

/** Set the mark on one booking from its account's state. */
function tp_account_mark_booking( $id, $user_id ) {
	$state = tp_account_confirm_state( $user_id );
	$b     = tp_get_booking( $id );
	$same  = tp_phone_digits( (string) $b['phone'] ) === tp_phone_digits( (string) get_user_meta( $user_id, 'tp_mobile', true ) );
	if ( $state['phone'] && $same && ! get_post_meta( $id, '_tp_phone_verified', true ) ) {
		update_post_meta( $id, '_tp_phone_verified', time() );
	}
	if ( ! $state['pending'] || get_post_meta( $id, '_tp_phone_verified', true ) ) {
		delete_post_meta( $id, '_tp_unverified' );
	} else {
		update_post_meta( $id, '_tp_unverified', $state['confirmed'] ? 'phone' : 'account' );
	}
}

/** Re-mark the account's trips that have not happened yet. */
function tp_account_refresh_marks( $user_id ) {
	$ids = get_posts( array( 'post_type' => 'tp_booking', 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'meta_key' => '_tp_customer_id', 'meta_value' => (int) $user_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $ids as $id ) {
		if ( ! in_array( get_post_meta( $id, '_tp_status', true ), array( 'completed', 'cancelled' ), true ) ) {
			tp_account_mark_booking( $id, $user_id );
		}
	}
}

// After the booking is linked to the account (priority 5).
add_action(
	'tp_booking_created',
	static function ( $first_id, $ids ) {
		foreach ( $ids ?: array( $first_id ) as $id ) {
			$uid = (int) get_post_meta( $id, '_tp_customer_id', true );
			if ( $uid ) {
				tp_account_mark_booking( $id, $uid );
			}
		}
	},
	6,
	2
);

/** Text for a booking's mark, or ''. */
function tp_unverified_label( $post_id ) {
	$mark = get_post_meta( $post_id, '_tp_unverified', true );
	if ( 'account' === $mark ) {
		return __( 'Unverified customer', 'taxi-peninsula' );
	}
	return 'phone' === $mark ? __( 'Phone not confirmed', 'taxi-peninsula' ) : '';
}

function tp_unverified_help( $post_id ) {
	return 'account' === get_post_meta( $post_id, '_tp_unverified', true )
		? __( 'New account that has not confirmed its email or mobile yet. Call to check before sending a car.', 'taxi-peninsula' )
		: __( 'The account confirmed its email, but not this mobile number.', 'taxi-peninsula' );
}

add_action(
	'tp_dispatch_card_flags',
	static function ( $b ) {
		if ( 'cancelled' === $b['status'] ) {
			return;
		}
		$label = tp_unverified_label( $b['id'] );
		if ( $label ) {
			echo '<p class="tp-dcard__flag tp-dcard__flag--unverified" title="' . esc_attr( tp_unverified_help( $b['id'] ) ) . '"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <strong>' . esc_html( $label ) . '</strong></p>';
		} elseif ( get_post_meta( $b['id'], '_tp_phone_verified', true ) ) {
			echo '<p class="tp-dcard__flag tp-dcard__flag--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Phone confirmed', 'taxi-peninsula' ) . '</p>';
		}
	},
	6
);

add_action(
	'manage_tp_booking_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'tp_status' !== $col || 'cancelled' === get_post_meta( $post_id, '_tp_status', true ) ) {
			return;
		}
		$label = tp_unverified_label( $post_id );
		if ( $label ) {
			echo '<br><span class="tp-badge tp-badge--unverified" title="' . esc_attr( tp_unverified_help( $post_id ) ) . '">' . esc_html( $label ) . '</span>';
		}
	},
	21,
	2
);

add_action(
	'tp_status_box_fields',
	static function ( $post ) {
		$label = tp_unverified_label( $post->ID );
		if ( $label ) {
			echo '<p class="tp-unverified"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <strong>' . esc_html( $label ) . '</strong><br><span class="description">' . esc_html( tp_unverified_help( $post->ID ) ) . '</span></p>';
		}
	},
	4
);

/* =========================================================================
 * My Account: the banner, the code form and the email link.
 * ====================================================================== */

add_filter(
	'tp_account_messages',
	static function ( $m ) {
		$m['confirmed']   = __( 'Thank you — your account is confirmed.', 'taxi-peninsula' );
		$m['phone_ok']    = __( 'Thank you — your mobile number is confirmed.', 'taxi-peninsula' );
		$m['code_sent']   = __( 'We’ve sent a new code.', 'taxi-peninsula' );
		$m['link_sent']   = __( 'We’ve emailed you a new link.', 'taxi-peninsula' );
		$m['link_old']    = __( 'That link has expired or was already used. Sign in to send yourself a new one.', 'taxi-peninsula' );
		return $m;
	}
);

add_action(
	'tp_account_notices',
	static function ( WP_User $user ) {
		$state = tp_account_confirm_state( $user->ID );
		if ( ! $state['pending'] || $state['phone'] ) {
			return;
		}
		$text   = tp_account_can_text( $user->ID );
		$mobile = (string) get_user_meta( $user->ID, 'tp_mobile', true );
		if ( $state['confirmed'] && ! $text ) {
			return; // Email done, and no way to text a code.
		}
		$code = get_user_meta( $user->ID, 'tp_confirm_code', true );
		$err  = ! empty( $_GET['code_err'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = static function ( $action, $inner ) {
			return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '">' . wp_nonce_field( 'tp_confirm', '_tp_nonce', true, false ) . $inner . '</form>';
		};
		?>
		<section class="account-confirm card<?php echo $state['confirmed'] ? ' account-confirm--soft' : ''; ?>" aria-labelledby="account-confirm-title">
			<h3 id="account-confirm-title"><?php echo $state['confirmed'] ? esc_html__( 'Confirm your mobile number', 'taxi-peninsula' ) : esc_html__( 'Please confirm your account', 'taxi-peninsula' ); ?></h3>
			<p>
				<?php
				if ( $state['confirmed'] ) {
					esc_html_e( 'Your email is confirmed. Confirming your mobile too means drivers can reach you and your bookings go through without a check call.', 'taxi-peninsula' );
				} elseif ( $text && $code ) {
					/* translators: %s: mobile number */
					echo wp_kses( sprintf( __( 'We’ve texted a 6-digit code to %s and emailed you a link. Use either one. You can book in the meantime — we may call to check your first trip.', 'taxi-peninsula' ), '<strong>' . esc_html( tp_au_phone( $mobile )['display'] ?? $mobile ) . '</strong>' ), array( 'strong' => array() ) );
				} else {
					/* translators: %s: email */
					echo wp_kses( sprintf( __( 'We’ve emailed a confirm link to %s. You can book in the meantime — we may call to check your first trip.', 'taxi-peninsula' ), '<strong>' . esc_html( $user->user_email ) . '</strong>' ), array( 'strong' => array() ) );
				}
				?>
			</p>
			<?php if ( $err ) : ?>
				<p class="notice notice--error" role="alert"><?php esc_html_e( 'That code isn’t right, or it has expired. Please check the text or send a new code.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
			<?php if ( $text && $code ) : ?>
				<?php
				echo $post( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
					'tp_confirm_code',
					'<div class="account-confirm__row"><div class="field"><label for="confirm-code">' . esc_html__( 'Code from the text message', 'taxi-peninsula' ) . '</label><input id="confirm-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required' . ( $err ? ' aria-invalid="true"' : '' ) . '></div><button type="submit" class="btn btn--primary">' . esc_html__( 'Confirm', 'taxi-peninsula' ) . '</button></div>'
				);
				?>
			<?php endif; ?>
			<div class="account-confirm__more">
				<?php
				if ( $text ) {
					echo $post( 'tp_confirm_resend', '<input type="hidden" name="by" value="phone"><button type="submit" class="btn-link">' . ( $code ? esc_html__( 'Text me a new code', 'taxi-peninsula' ) : esc_html__( 'Text me a code', 'taxi-peninsula' ) ) . '</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				if ( ! $state['confirmed'] ) {
					echo $post( 'tp_confirm_resend', '<input type="hidden" name="by" value="email"><button type="submit" class="btn-link">' . esc_html__( 'Email me a new link', 'taxi-peninsula' ) . '</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
				<a class="btn-link" href="<?php echo esc_url( tp_account_url( array( 'tab' => 'details' ) ) . '#account' ); ?>"><?php esc_html_e( 'Wrong number? Change it', 'taxi-peninsula' ); ?></a>
			</div>
		</section>
		<?php
	}
);

function tp_confirm_verify() {
	if ( ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_confirm' ) || ! is_user_logged_in() ) {
		wp_safe_redirect( tp_account_url() );
		exit;
	}
	return get_current_user_id();
}

add_action( 'admin_post_tp_confirm_code', 'tp_handle_confirm_code' );
function tp_handle_confirm_code() {
	$uid   = tp_confirm_verify();
	$code  = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['code'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- digits only.
	$saved = get_user_meta( $uid, 'tp_confirm_code', true );
	$p     = tp_au_phone( (string) get_user_meta( $uid, 'tp_mobile', true ) );
	$ok    = is_array( $saved ) && $p && time() - $saved['sent'] < 30 * MINUTE_IN_SECONDS && $saved['tries'] < 5
		&& hash_equals( $saved['hash'], tp_confirm_hash( $code . '|' . $p['e164'], $uid ) );
	if ( ! $ok ) {
		if ( is_array( $saved ) ) {
			++$saved['tries'];
			update_user_meta( $uid, 'tp_confirm_code', $saved );
		}
		tp_account_back( array( 'code_err' => 1 ) );
	}
	$first = ! tp_account_confirm_state( $uid )['confirmed'];
	tp_account_confirmed( $uid, 'phone' );
	tp_account_back( array( 'saved' => $first ? 'confirmed' : 'phone_ok' ) );
}

add_action( 'admin_post_tp_confirm_resend', 'tp_handle_confirm_resend' );
function tp_handle_confirm_resend() {
	$uid   = tp_confirm_verify();
	$by    = 'phone' === ( $_POST['by'] ?? '' ) ? 'phone' : 'email'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
	$saved = get_user_meta( $uid, 'phone' === $by ? 'tp_confirm_code' : 'tp_confirm_token', true );
	$sent  = is_array( $saved ) && isset( $saved['sent'] ) ? $saved['sent'] : 0;
	// One send every 30 seconds per account, and a few an hour per connection.
	if ( time() - $sent >= 30 && tp_rate_limit( 'phone' === $by ? 'confirm_sms' : 'confirm_mail', 6, HOUR_IN_SECONDS ) ) {
		if ( 'phone' === $by && tp_account_can_text( $uid ) ) {
			tp_account_send_code( $uid );
		} elseif ( 'email' === $by ) {
			tp_account_send_link( $uid );
		}
	}
	tp_account_back( array( 'saved' => 'phone' === $by ? 'code_sent' : 'link_sent' ) );
}

// The email link works signed in or out.
add_action(
	'template_redirect',
	static function () {
		if ( empty( $_GET['tp_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$raw   = sanitize_text_field( wp_unslash( $_GET['tp_confirm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the check.
		$parts = explode( '-', $raw, 2 );
		$uid   = (int) $parts[0];
		$saved = $uid ? get_user_meta( $uid, 'tp_confirm_token', true ) : '';
		$ok    = is_array( $saved ) && isset( $parts[1] ) && $saved['until'] > time() && hash_equals( $saved['hash'], tp_confirm_hash( $parts[1], $uid ) );
		nocache_headers();
		if ( $ok ) {
			$first = ! tp_account_confirm_state( $uid )['confirmed'];
			tp_account_confirmed( $uid, 'email' );
			wp_safe_redirect( tp_account_url( array( 'saved' => $first ? 'confirmed' : 'details' ) ) . '#account' );
		} else {
			wp_safe_redirect( tp_account_url( array( 'saved' => 'link_old' ) ) . '#account' );
		}
		exit;
	},
	0
);
