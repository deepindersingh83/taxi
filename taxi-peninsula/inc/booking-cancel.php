<?php
/**
 * Cancelling with a reason, the blocked list, and "Looks suspicious".
 *
 * - Cancel with a reason (booking screen and dispatch card): customer asked,
 *   wrong contact details, fake or spam, duplicate, no vehicle, other (with a
 *   note). The badge then reads e.g. "Cancelled · Wrong details".
 * - Fake or spam: the customer gets no message, the trip is left out of the
 *   owner dashboard and partner numbers, and staff can tick "block" to stop
 *   that phone number, email and device booking online again
 *   (Bookings → Blocked contacts).
 * - Online bookings are checked for signs of a fake booking and marked
 *   "Looks suspicious" on the dispatch board and booking list. Staff decide.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cancellation reasons: key => [full label, short label for the badge].
 */
function tp_cancel_reasons() {
	return array(
		'customer'      => array( __( 'Customer asked', 'taxi-peninsula' ), __( 'Customer asked', 'taxi-peninsula' ) ),
		'wrong_details' => array( __( 'Wrong contact details — couldn’t reach the customer', 'taxi-peninsula' ), __( 'Wrong details', 'taxi-peninsula' ) ),
		'fake'          => array( __( 'Fake or spam booking', 'taxi-peninsula' ), __( 'Fake / spam', 'taxi-peninsula' ) ),
		'duplicate'     => array( __( 'Duplicate booking', 'taxi-peninsula' ), __( 'Duplicate', 'taxi-peninsula' ) ),
		'no_vehicle'    => array( __( 'No vehicle available', 'taxi-peninsula' ), __( 'No vehicle', 'taxi-peninsula' ) ),
		'other'         => array( __( 'Other', 'taxi-peninsula' ), __( 'Other', 'taxi-peninsula' ) ),
	);
}

function tp_cancel_reason( $post_id ) {
	$r = (string) get_post_meta( $post_id, '_tp_cancel_reason', true );
	return isset( tp_cancel_reasons()[ $r ] ) ? $r : '';
}

/** Cancelled as fake or spam: left out of reports and partner numbers. */
function tp_is_spam_booking( $post_id ) {
	return 'fake' === tp_cancel_reason( $post_id ) && 'cancelled' === get_post_meta( $post_id, '_tp_status', true );
}

add_filter(
	'tp_status_badge_label',
	static function ( $label, $status, $post_id ) {
		if ( 'cancelled' === $status && $post_id && ( $r = tp_cancel_reason( $post_id ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
			$label .= ' · ' . tp_cancel_reasons()[ $r ][1];
		}
		return $label;
	},
	10,
	3
);

/**
 * Cancel a booking with a reason.
 *
 * @param int    $post_id Booking.
 * @param string $reason  Key from tp_cancel_reasons().
 * @param string $note    Optional note (needed for "other").
 * @param bool   $notify  Tell the customer (never for fake bookings).
 * @param bool   $block   Fake bookings only: block the phone, email and device.
 */
function tp_cancel_booking( $post_id, $reason, $note = '', $notify = false, $block = false ) {
	$reason = isset( tp_cancel_reasons()[ $reason ] ) ? $reason : 'other';
	update_post_meta( $post_id, '_tp_cancel_reason', $reason );
	update_post_meta( $post_id, '_tp_cancel_note', sanitize_text_field( $note ) );
	tp_set_status( $post_id, 'cancelled', 'fake' === $reason ? false : $notify );
	tp_in_site_lang(
		static function () use ( $post_id, $reason, $note ) {
			/* translators: 1: reason 2: note */
			tp_add_log( $post_id, array( 'note' => trim( sprintf( __( 'Cancelled: %1$s. %2$s', 'taxi-peninsula' ), tp_cancel_reasons()[ $reason ][0], $note ) ) ) );
		}
	);
	if ( 'fake' === $reason && $block ) {
		tp_block_booking_contacts( $post_id );
	}
	delete_transient( 'tp_pp_trips' );
}

// A booking cancelled from the customer's own booking page: "Customer asked".
add_action(
	'tp_booking_status_changed',
	static function ( $post_id, $old, $status ) {
		if ( 'cancelled' === $status && ! tp_cancel_reason( $post_id ) && ! current_user_can( 'edit_tp_bookings' ) ) {
			update_post_meta( $post_id, '_tp_cancel_reason', 'customer' );
		}
		if ( 'cancelled' !== $status && $old === 'cancelled' ) {
			delete_post_meta( $post_id, '_tp_cancel_reason' ); // Reinstated.
			delete_post_meta( $post_id, '_tp_cancel_note' );
		}
	},
	5,
	3
);

/* -------------------------------------------------------------------------
 * Booking screen: reason fields under the status.
 * ---------------------------------------------------------------------- */

function tp_cancel_fields( $prefix, $current = '', $note = '' ) {
	ob_start();
	?>
	<div class="tp-cancel-fields" data-cancel-fields>
		<p><label for="<?php echo esc_attr( $prefix ); ?>-reason"><strong><?php esc_html_e( 'Reason for cancelling', 'taxi-peninsula' ); ?></strong></label><br>
			<select id="<?php echo esc_attr( $prefix ); ?>-reason" name="tp_cancel_reason" data-cancel-reason>
				<option value=""><?php esc_html_e( '— Choose a reason —', 'taxi-peninsula' ); ?></option>
				<?php foreach ( tp_cancel_reasons() as $k => $r ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $current, $k ); ?>><?php echo esc_html( $r[0] ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p><label for="<?php echo esc_attr( $prefix ); ?>-note"><?php esc_html_e( 'Note (optional; needed for “Other”)', 'taxi-peninsula' ); ?></label><br>
			<input type="text" class="widefat" id="<?php echo esc_attr( $prefix ); ?>-note" name="tp_cancel_note" value="<?php echo esc_attr( $note ); ?>" maxlength="200"></p>
		<div class="tp-cancel-fake" data-cancel-fake hidden>
			<p class="description"><?php esc_html_e( 'Fake or spam: the customer is not told, and the trip is left out of reports and partner numbers.', 'taxi-peninsula' ); ?></p>
			<p><label><input type="checkbox" name="tp_cancel_block" value="1"> <?php esc_html_e( 'Block this phone number, email and device from booking online again', 'taxi-peninsula' ); ?></label></p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_action(
	'tp_status_box_fields',
	static function ( $post, $b ) {
		echo tp_cancel_fields( 'tp-cancel', tp_cancel_reason( $post->ID ), (string) get_post_meta( $post->ID, '_tp_cancel_note', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		$suspect = (array) get_post_meta( $post->ID, '_tp_suspect', true );
		if ( array_filter( $suspect ) ) {
			echo '<div class="tp-suspect-box"><p><strong><span class="dashicons dashicons-flag" aria-hidden="true"></span> ' . esc_html__( 'Looks suspicious', 'taxi-peninsula' ) . '</strong></p><ul>';
			foreach ( $suspect as $why ) {
				echo '<li>' . esc_html( $why ) . '</li>';
			}
			echo '</ul><p><label><input type="checkbox" name="tp_suspect_clear" value="1"> ' . esc_html__( 'Checked — it’s genuine (remove the flag)', 'taxi-peninsula' ) . '</label></p></div>';
		}
	},
	10,
	2
);

// Before the booking is saved: remember the reason, and never message the customer about a fake booking.
add_action(
	'save_post_tp_booking',
	static function ( $post_id ) {
		if ( ! isset( $_POST['_tp_admin_nonce'], $_POST['tp_status'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_admin_nonce'] ) ), 'tp_save_booking' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['tp_suspect_clear'] ) ) {
			delete_post_meta( $post_id, '_tp_suspect' );
			tp_add_log( $post_id, array( 'note' => __( 'Checked by staff: not suspicious.', 'taxi-peninsula' ) ) );
		}
		if ( 'cancelled' !== sanitize_key( wp_unslash( $_POST['tp_status'] ) ) ) {
			return;
		}
		$reason = sanitize_key( wp_unslash( $_POST['tp_cancel_reason'] ?? '' ) );
		if ( ! isset( tp_cancel_reasons()[ $reason ] ) ) {
			return;
		}
		$was = get_post_meta( $post_id, '_tp_status', true );
		update_post_meta( $post_id, '_tp_cancel_reason', $reason );
		update_post_meta( $post_id, '_tp_cancel_note', sanitize_text_field( wp_unslash( $_POST['tp_cancel_note'] ?? '' ) ) );
		if ( 'fake' === $reason ) {
			unset( $_POST['tp_notify'] );
			if ( ! empty( $_POST['tp_cancel_block'] ) ) {
				tp_block_booking_contacts( $post_id );
			}
		}
		if ( 'cancelled' !== $was ) {
			tp_in_site_lang(
				static function () use ( $post_id, $reason ) {
					/* translators: 1: reason 2: note */
					tp_add_log( $post_id, array( 'note' => trim( sprintf( __( 'Cancelled: %1$s. %2$s', 'taxi-peninsula' ), tp_cancel_reasons()[ $reason ][0], get_post_meta( $post_id, '_tp_cancel_note', true ) ) ) ) );
				}
			);
		}
	},
	5
);

/* -------------------------------------------------------------------------
 * Dispatch card: "Cancel…" and the suspicious flag.
 * ---------------------------------------------------------------------- */

add_action(
	'tp_dispatch_card_after',
	static function ( $b, $day ) {
		if ( ! in_array( $b['status'], array( 'pending', 'confirmed', 'assigned' ), true ) ) {
			return;
		}
		$id = 'tp-cx-' . $b['id'];
		?>
		<details class="tp-dcard__cancel">
			<summary><?php esc_html_e( 'Cancel…', 'taxi-peninsula' ); ?></summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_cancel_booking">
				<input type="hidden" name="booking" value="<?php echo esc_attr( $b['id'] ); ?>">
				<input type="hidden" name="day" value="<?php echo esc_attr( $day ); ?>">
				<?php wp_nonce_field( 'tp_cancel_' . $b['id'] ); ?>
				<?php echo tp_cancel_fields( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<p class="tp-cancel-notify"><label><input type="checkbox" name="tp_notify" value="1" <?php checked( (bool) tp_opt( 'customer_emails' ) || tp_sms_enabled() ); ?>> <?php esc_html_e( 'Tell the customer', 'taxi-peninsula' ); ?></label></p>
				<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Cancel this booking', 'taxi-peninsula' ); ?></button>
			</form>
		</details>
		<?php
	},
	10,
	2
);

add_action(
	'admin_post_tp_cancel_booking',
	static function () {
		$post_id = absint( $_POST['booking'] ?? 0 );
		check_admin_referer( 'tp_cancel_' . $post_id );
		if ( 'tp_booking' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You cannot change this booking.', 'taxi-peninsula' ), 403 );
		}
		$reason = sanitize_key( wp_unslash( $_POST['tp_cancel_reason'] ?? '' ) );
		$note   = sanitize_text_field( wp_unslash( $_POST['tp_cancel_note'] ?? '' ) );
		$day    = sanitize_text_field( wp_unslash( $_POST['day'] ?? '' ) );
		$back   = admin_url( 'admin.php?page=tp-dispatch&day=' . rawurlencode( $day ) . '#tp-card-' . $post_id );
		$b      = tp_get_booking( $post_id );
		if ( ! isset( tp_cancel_reasons()[ $reason ] ) || ( 'other' === $reason && '' === $note ) ) {
			/* translators: %s: reference */
			set_transient( 'tp_dispatch_notice_' . get_current_user_id(), array( 'message' => sprintf( __( '%s was not cancelled: choose a reason (and add a note for “Other”).', 'taxi-peninsula' ), $b['reference'] ), 'warnings' => array() ), MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		tp_cancel_booking( $post_id, $reason, $note, ! empty( $_POST['tp_notify'] ), ! empty( $_POST['tp_cancel_block'] ) );
		/* translators: 1: reference 2: reason */
		set_transient( 'tp_dispatch_notice_' . get_current_user_id(), array( 'message' => sprintf( __( '%1$s cancelled (%2$s).', 'taxi-peninsula' ), $b['reference'], tp_cancel_reasons()[ $reason ][1] ), 'warnings' => array() ), MINUTE_IN_SECONDS );
		wp_safe_redirect( $back );
		exit;
	}
);

add_action(
	'tp_dispatch_card_flags',
	static function ( $b ) {
		$suspect = array_filter( (array) get_post_meta( $b['id'], '_tp_suspect', true ) );
		if ( $suspect && 'cancelled' !== $b['status'] ) {
			echo '<p class="tp-dcard__flag tp-dcard__flag--suspect"><span class="dashicons dashicons-flag" aria-hidden="true"></span> <strong>' . esc_html__( 'Looks suspicious', 'taxi-peninsula' ) . ':</strong> ' . esc_html( implode( '; ', $suspect ) ) . '</p>';
		}
	},
	5
);

// Booking list: the same flag under the status.
add_action(
	'manage_tp_booking_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'tp_status' === $col && array_filter( (array) get_post_meta( $post_id, '_tp_suspect', true ) ) && 'cancelled' !== get_post_meta( $post_id, '_tp_status', true ) ) {
			echo '<br><span class="tp-badge tp-badge--suspect" title="' . esc_attr( implode( '; ', (array) get_post_meta( $post_id, '_tp_suspect', true ) ) ) . '">' . esc_html__( 'Looks suspicious', 'taxi-peninsula' ) . '</span>';
		}
	},
	20,
	2
);

// Fake bookings are left out of the owner dashboard.
add_filter(
	'tp_owner_rows',
	static function ( $rows ) {
		return array_values( array_filter( $rows, static fn( $r ) => ! ( 'cancelled' === $r->status && tp_is_spam_booking( (int) $r->id ) ) ) );
	}
);

/* -------------------------------------------------------------------------
 * Blocked list.
 * ---------------------------------------------------------------------- */

function tp_device_hash() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return substr( hash_hmac( 'sha256', tp_client_ip() . '|' . $ua, wp_salt( 'auth' ) ), 0, 20 );
}

function tp_blocklist() {
	return (array) get_option( 'tp_blocklist', array() );
}

function tp_block_add( $type, $value, $label, $ref = '' ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return;
	}
	$list = tp_blocklist();
	foreach ( $list as $e ) {
		if ( $e['type'] === $type && $e['value'] === $value ) {
			return;
		}
	}
	$list[] = array( 'type' => $type, 'value' => $value, 'label' => $label, 'ref' => $ref, 'at' => time(), 'by' => get_current_user_id() );
	update_option( 'tp_blocklist', $list, false );
}

function tp_block_booking_contacts( $post_id ) {
	$b = tp_get_booking( $post_id );
	tp_block_add( 'phone', tp_phone_digits( $b['phone'] ), $b['phone'], $b['reference'] );
	tp_block_add( 'email', strtolower( trim( $b['email'] ) ), $b['email'], $b['reference'] );
	/* translators: %s: reference */
	tp_block_add( 'device', (string) get_post_meta( $post_id, '_tp_device', true ), sprintf( __( 'Device used for %s', 'taxi-peninsula' ), $b['reference'] ), $b['reference'] );
	tp_in_site_lang(
		static function () use ( $post_id ) {
			tp_add_log( $post_id, array( 'note' => __( 'Phone, email and device blocked from booking online.', 'taxi-peninsula' ) ) );
		}
	);
}

/**
 * Which blocked entries a booking matches.
 *
 * @return string[] Types matched (phone, email, device).
 */
function tp_blocked_matches( $phone, $email, $device = '' ) {
	$want = array( 'phone' => tp_phone_digits( $phone ), 'email' => strtolower( trim( (string) $email ) ), 'device' => (string) $device );
	$hits = array();
	foreach ( tp_blocklist() as $e ) {
		if ( 'device' === $e['type'] && $e['at'] < time() - 30 * DAY_IN_SECONDS ) {
			continue; // Device blocks last 30 days (connections get passed on to other people).
		}
		if ( '' !== $want[ $e['type'] ] && $want[ $e['type'] ] === $e['value'] ) {
			$hits[] = $e['type'];
		}
	}
	return array_unique( $hits );
}

// Online bookings from a blocked phone or email are turned away politely. A blocked device only
// flags the booking ("Looks suspicious"), because several people can share one internet connection.
add_action(
	'tp_booking_checks_passed',
	static function ( $raw, $data, $return ) {
		if ( tp_blocked_matches( $data['phone'], $data['email'] ) ) {
			/* translators: %s: phone */
			tp_booking_fail( $return, array( 'form' => sprintf( __( 'Sorry, we can’t take this booking online. Please call us on %s and we’ll help.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw );
		}
	},
	5,
	3
);

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'edit.php?post_type=tp_booking', __( 'Blocked contacts', 'taxi-peninsula' ), __( 'Blocked contacts', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-blocked', 'tp_render_blocked_page', 40 );
	}
);

function tp_render_blocked_page() {
	$list  = tp_blocklist();
	$types = array( 'phone' => __( 'Phone', 'taxi-peninsula' ), 'email' => __( 'Email', 'taxi-peninsula' ), 'device' => __( 'Device', 'taxi-peninsula' ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Blocked contacts', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Online bookings from these phone numbers and emails are turned away with a polite message asking them to call. Bookings from a blocked device are let through but marked “Looks suspicious”, because several people can share one internet connection; device blocks end after 30 days. Add entries by cancelling a booking as “Fake or spam” and ticking “Block”, or below.', 'taxi-peninsula' ); ?></p>
		<?php if ( $list ) : ?>
			<table class="widefat striped" style="max-width:60rem">
				<thead><tr><th><?php esc_html_e( 'Type', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Blocked', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'From booking', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'When', 'taxi-peninsula' ); ?></th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $list as $i => $e ) : ?>
						<tr>
							<td><?php echo esc_html( $types[ $e['type'] ] ?? $e['type'] ); ?></td>
							<td><?php echo esc_html( $e['label'] ); ?></td>
							<td><?php echo esc_html( $e['ref'] ?: '—' ); ?></td>
							<td><?php echo esc_html( wp_date( 'j M Y', $e['at'] ) ); ?><?php echo ( $u = get_userdata( (int) $e['by'] ) ) ? ' · ' . esc_html( $u->display_name ) : ''; // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInTernaryCondition ?></td>
							<td><a class="button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_unblock&i=' . $i ), 'tp_unblock' ) ); ?>"><?php esc_html_e( 'Remove', 'taxi-peninsula' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Nothing is blocked.', 'taxi-peninsula' ); ?></em></p>
		<?php endif; ?>
		<h2><?php esc_html_e( 'Block a phone number or email', 'taxi-peninsula' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_block_add"><?php wp_nonce_field( 'tp_block_add' ); ?>
			<label for="tp-block-value" class="screen-reader-text"><?php esc_html_e( 'Phone number or email', 'taxi-peninsula' ); ?></label>
			<input id="tp-block-value" type="text" name="value" class="regular-text" placeholder="<?php esc_attr_e( 'Phone number or email', 'taxi-peninsula' ); ?>" required>
			<?php submit_button( __( 'Block', 'taxi-peninsula' ), 'secondary', '', false ); ?>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_tp_unblock',
	static function () {
		check_admin_referer( 'tp_unblock' );
		if ( ! current_user_can( 'edit_tp_bookings' ) ) {
			wp_die( '', 403 );
		}
		$list = tp_blocklist();
		unset( $list[ absint( $_GET['i'] ?? -1 ) ] );
		update_option( 'tp_blocklist', array_values( $list ), false );
		wp_safe_redirect( admin_url( 'edit.php?post_type=tp_booking&page=tp-blocked' ) );
		exit;
	}
);

add_action(
	'admin_post_tp_block_add',
	static function () {
		check_admin_referer( 'tp_block_add' );
		if ( ! current_user_can( 'edit_tp_bookings' ) ) {
			wp_die( '', 403 );
		}
		$v = trim( sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ) );
		if ( is_email( $v ) ) {
			tp_block_add( 'email', strtolower( $v ), $v );
		} elseif ( $p = tp_au_phone( $v ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
			tp_block_add( 'phone', tp_phone_digits( $p['display'] ), $p['display'] );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=tp_booking&page=tp-blocked' ) );
		exit;
	}
);

/* -------------------------------------------------------------------------
 * "Looks suspicious" checks on new online bookings.
 * ---------------------------------------------------------------------- */

function tp_disposable_email_domains() {
	return apply_filters(
		'tp_disposable_email_domains',
		array( 'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', 'grr.la', '10minutemail.com', 'temp-mail.org', 'tempmail.com', 'tempmail.net', 'tempmailo.com', 'yopmail.com', 'trashmail.com', 'getnada.com', 'nada.email', 'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com', 'tempail.com', 'tempr.email', 'burnermail.io', 'spamgourmet.com', 'mailnesia.com', 'mytemp.email', 'moakt.com', 'tmpmail.org', 'tmpmail.net', 'inboxkitten.com', 'mail.tm', 'emailfake.com', 'fakemail.net', 'example.com', 'example.org', 'test.com' )
	);
}

/**
 * Reasons a booking looks fake (empty when it looks fine).
 */
function tp_suspect_reasons( array $b, $device, array $exclude_ids = array() ) {
	$why    = array();
	$domain = strtolower( substr( strrchr( (string) $b['email'], '@' ) ?: '', 1 ) );
	if ( $domain && in_array( $domain, tp_disposable_email_domains(), true ) ) {
		/* translators: %s: email domain */
		$why[] = sprintf( __( 'throwaway email address (%s)', 'taxi-peninsula' ), $domain );
	}
	$d = tp_phone_digits( $b['phone'] );
	if ( $d ) {
		$rest = substr( $d, 2 );
		$seq  = '0123456789012';
		$fake = 1 === count( array_unique( str_split( $rest ) ) ) // 0400 000 000
			|| false !== strpos( $seq, $rest ) || false !== strpos( strrev( $seq ), $rest ) // 0412 345 678
			|| preg_match( '/^(\d\d)\1{3}$/', $rest ) // 0412 121 212
			|| preg_match( '/^(049157|035550|037010)/', $d ) // Numbers set aside for TV and films.
			|| in_array( $d, array( '0412345678', '0400000000', '0411111111', '0400123456' ), true );
		if ( $fake ) {
			/* translators: %s: phone */
			$why[] = sprintf( __( 'phone number looks made up (%s)', 'taxi-peninsula' ), $b['phone'] );
		}
	}
	$name = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $b['name'] ) ) );
	if ( '' !== $name && ( preg_match( '/\b(test|testing|asdf|qwerty|fake|dummy|sample|none|nobody)\b/', $name ) || preg_match( '/^(.)\1*$/u', str_replace( ' ', '', $name ) ) || mb_strlen( str_replace( ' ', '', $name ) ) < 2 || preg_match( '/^(asd|qwe|zxc|abc|xyz)/', $name ) ) ) {
		/* translators: %s: name */
		$why[] = sprintf( __( 'name looks made up (“%s”)', 'taxi-peninsula' ), $b['name'] );
	}
	if ( $device && ! is_user_logged_in() ) { // Coordinators often book several clients in a row.
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_tp_device' AND m.meta_value = %s WHERE p.post_type = 'tp_booking' AND p.post_date_gmt >= %s", $device, $since ) );
		$groups = array();
		foreach ( array_diff( array_map( 'intval', $ids ), $exclude_ids ) as $id ) {
			$groups[ (int) get_post_meta( $id, '_tp_submission', true ) ?: $id ] = 1;
		}
		if ( count( $groups ) >= 3 ) {
			/* translators: %d: bookings */
			$why[] = sprintf( __( '%d other bookings from the same device in the last 10 minutes', 'taxi-peninsula' ), count( $groups ) );
		}
	}
	$hits = tp_blocked_matches( $b['phone'], $b['email'], $device );
	if ( $hits ) {
		/* translators: %s: phone / email / device */
		$why[] = sprintf( __( 'matches the blocked list (%s)', 'taxi-peninsula' ), implode( ', ', $hits ) );
	}
	return $why;
}

// Every new booking: remember the device and the submission, then check it.
add_action(
	'tp_booking_created',
	static function ( $first_id, $ids ) {
		$ids    = array_map( 'intval', $ids ?: array( $first_id ) );
		$online = doing_action( 'admin_post_tp_booking' ) || doing_action( 'admin_post_nopriv_tp_booking' ) || doing_action( 'admin_post_tp_booking_code' ) || doing_action( 'admin_post_nopriv_tp_booking_code' );
		$device = $online ? tp_device_hash() : '';
		foreach ( $ids as $id ) {
			update_post_meta( $id, '_tp_submission', $ids[0] );
			if ( $device ) {
				update_post_meta( $id, '_tp_device', $device );
			}
		}
		$why = tp_suspect_reasons( tp_get_booking( $ids[0] ), $device, $ids );
		if ( $why ) {
			foreach ( $ids as $id ) {
				update_post_meta( $id, '_tp_suspect', $why );
			}
			tp_in_site_lang(
				static function () use ( $ids, $why ) {
					/* translators: %s: reasons */
					tp_add_log( $ids[0], array( 'note' => sprintf( __( 'Looks suspicious: %s', 'taxi-peninsula' ), implode( '; ', $why ) ) ) );
				}
			);
		}
	},
	1,
	2
);

// The office alert says so at the top.
add_filter(
	'tp_new_booking_office_note',
	static function ( $note, $post_id ) {
		$why = array_filter( (array) get_post_meta( $post_id, '_tp_suspect', true ) );
		/* translators: %s: reasons */
		return $why ? sprintf( __( '⚠ Looks suspicious: %s. Check before confirming.', 'taxi-peninsula' ), implode( '; ', $why ) ) . "\n\n" . $note : $note;
	},
	10,
	2
);

// Booking screen and dispatch board: show the reason only when cancelling; "fake" hides the customer message.
add_action(
	'admin_footer',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || ( 'tp_booking' !== $screen->post_type && false === strpos( $screen->id, 'tp-dispatch' ) ) ) {
			return;
		}
		?>
		<script>
		document.querySelectorAll('[data-cancel-fields]').forEach(function (box) {
			var form = box.closest('form') || document, status = form.querySelector('select[name="tp_status"]');
			var reason = box.querySelector('[data-cancel-reason]'), fake = box.querySelector('[data-cancel-fake]');
			var notify = form.querySelector('input[name="tp_notify"]'), notifyWas = notify ? notify.checked : false;
			var sync = function () {
				var cancelling = !status || status.value === 'cancelled';
				box.hidden = !cancelling;
				reason.required = cancelling && !!status && status.value !== status.getAttribute('data-was');
				var isFake = cancelling && reason.value === 'fake';
				fake.hidden = !isFake;
				if (notify) { notify.disabled = isFake; notify.checked = isFake ? false : notifyWas; }
			};
			if (status) { status.setAttribute('data-was', status.value); status.addEventListener('change', sync); }
			if (notify) { notify.addEventListener('change', function () { if (!notify.disabled) { notifyWas = notify.checked; } }); }
			reason.addEventListener('change', sync);
			if (!status) { reason.required = true; }
			sync();
		});
		</script>
		<?php
	}
);
