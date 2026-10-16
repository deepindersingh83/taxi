<?php
/**
 * Phone call-back requests: [tp_callback] form (starter page "Request a Call
 * Back"), a list for the office under Bookings → Call-backs, and email/SMS
 * alerts.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_callback_statuses() {
	return array(
		'new'       => __( 'New', 'taxi-peninsula' ),
		'no_answer' => __( 'No answer — try again', 'taxi-peninsula' ),
		'called'    => __( 'Called', 'taxi-peninsula' ),
		'done'      => __( 'Done', 'taxi-peninsula' ),
	);
}

function tp_callback_times() {
	return array(
		'asap'      => __( 'As soon as possible', 'taxi-peninsula' ),
		'morning'   => __( 'Morning (9am – 12pm)', 'taxi-peninsula' ),
		'afternoon' => __( 'Afternoon (12pm – 5pm)', 'taxi-peninsula' ),
		'evening'   => __( 'Evening (5pm – 8pm)', 'taxi-peninsula' ),
	);
}

function tp_callback_reasons() {
	return array(
		__( 'Book a trip', 'taxi-peninsula' ),
		__( 'A question about a booking', 'taxi-peninsula' ),
		__( 'Prices and quotes', 'taxi-peninsula' ),
		__( 'NDIS, aged care or business account', 'taxi-peninsula' ),
		__( 'Something else', 'taxi-peninsula' ),
	);
}

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_callback',
			array(
				'labels'          => array(
					'name'          => __( 'Call-back requests', 'taxi-peninsula' ),
					'menu_name'     => __( 'Call-backs', 'taxi-peninsula' ),
					'singular_name' => __( 'Call-back request', 'taxi-peninsula' ),
					'add_new'       => __( 'Add call-back', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Add a call-back request', 'taxi-peninsula' ),
					'edit_item'     => __( 'Call-back request', 'taxi-peninsula' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => tp_admin_parent( 'bookings' ),
				'supports'        => array( 'title' ),
				'capability_type' => 'tp_booking',
				'map_meta_cap'    => true,
			)
		);
	}
);

add_filter(
	'tp_feature_shortcodes',
	static function ( $c ) {
		$c['callback'] = 'tp_callback';
		return $c;
	}
);

add_filter(
	'tp_starter_pages',
	static function ( $p ) {
		$p['callback'] = array(
			'title'   => __( 'Request a Call Back', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Prefer to talk it through? Leave your number and a good time, and one of our team will ring you — no need to wait on hold.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_callback]\n<!-- /wp:shortcode -->",
		);
		return $p;
	}
);

function tp_callback_url() {
	return tp_page_url_by_template( 'callback' );
}

// Entry points: account menu, contact chooser and the booking form.
add_action(
	'tp_account_menu_contact',
	static function () {
		if ( tp_callback_url() ) {
			printf( '<li><a class="acct__link" href="%s">%s<span>%s</span></a></li>', esc_url( tp_callback_url() ), tp_icon( 'phone' ), esc_html__( 'Ask us to call you', 'taxi-peninsula' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
);
add_filter(
	'tp_contact_chooser_items',
	static function ( $items ) {
		if ( tp_callback_url() ) {
			array_splice( $items, 1, 0, array( array( 'phone', __( 'Ask us to call you', 'taxi-peninsula' ), __( 'Leave your number and a good time.', 'taxi-peninsula' ), tp_callback_url() ) ) );
		}
		return $items;
	}
);
add_action(
	'tp_booking_form_after_submit',
	static function () {
		if ( tp_callback_url() ) {
			printf( '<p class="booking-form__alt"><a href="%s">%s</a></p>', esc_url( tp_callback_url() ), esc_html__( 'Rather talk to someone? Ask us to call you back.', 'taxi-peninsula' ) );
		}
	}
);

function tp_callback_meta( $id ) {
	$m = array( 'id' => (int) $id, 'name' => get_the_title( $id ) );
	foreach ( array( 'phone', 'when', 'reason', 'notes', 'status', 'reference', 'attempts', 'last_try' ) as $k ) {
		$m[ $k ] = (string) get_post_meta( $id, '_tp_cb_' . $k, true );
	}
	$m['status'] = $m['status'] ?: 'new';
	return $m;
}

/* -------------------------------------------------------------------------
 * Customer form.
 * ---------------------------------------------------------------------- */

add_shortcode( 'tp_callback', 'tp_callback_shortcode' );
function tp_callback_shortcode() {
	list( $errors, $old ) = tp_form_state();
	$sent = ! empty( $_GET['callback_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$user = wp_get_current_user();
	$f    = static function ( $name, $label, $o = array() ) use ( $errors, $old ) {
		tp_form_field( 'cb', $name, $label, array_merge( array( 'errors' => $errors, 'value' => $old[ $name ] ?? ( $o['default'] ?? '' ) ), $o ) );
	};
	$open = function_exists( 'tp_hours_enabled' ) && tp_hours_enabled() ? tp_hours_status() : null;
	ob_start();
	?>
	<div class="tp-component card callback-form" id="callback">
		<?php if ( $sent ) : ?>
			<?php tp_form_success( __( 'Thanks — we will call you', 'taxi-peninsula' ), __( 'We have your request and will ring you at the time you chose. The call may come from a private number.', 'taxi-peninsula' ) ); ?>
		<?php else : ?>
			<?php if ( $errors ) : ?>
				<div class="notice notice--error" role="alert" tabindex="-1" data-focus><p><?php echo esc_html( $errors['form'] ?? __( 'Please check the highlighted fields.', 'taxi-peninsula' ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( $open && ! $open[0] ) : ?>
				<p class="notice notice--info"><?php echo esc_html( $open[1] ); ?> — <?php esc_html_e( 'we will call you when the office opens.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
			<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="tp_callback">
				<input type="hidden" name="_tp_return" value="<?php echo esc_url( tp_form_return_url() ); ?>">
				<?php wp_nonce_field( 'tp_callback', '_tp_nonce', false ); ?>
				<?php
				$f( 'name', __( 'Your name', 'taxi-peninsula' ), array( 'required' => true, 'default' => $user->exists() ? $user->display_name : '', 'attrs' => 'autocomplete="name"' ) );
				$f( 'phone', __( 'Phone number', 'taxi-peninsula' ), array( 'type' => 'tel', 'required' => true, 'default' => $user->exists() ? (string) get_user_meta( $user->ID, 'tp_mobile', true ) : '', 'attrs' => 'autocomplete="tel"' ) );
				?>
				<div class="field">
					<label for="cb-when"><?php esc_html_e( 'Best time to call', 'taxi-peninsula' ); ?></label>
					<select id="cb-when" name="when">
						<?php foreach ( tp_callback_times() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $old['when'] ?? 'asap', $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="field">
					<label for="cb-reason"><?php esc_html_e( 'What is it about?', 'taxi-peninsula' ); ?></label>
					<select id="cb-reason" name="reason">
						<?php foreach ( tp_callback_reasons() as $r ) : ?>
							<option <?php selected( $old['reason'] ?? '', $r ); ?>><?php echo esc_html( $r ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php $f( 'notes', __( 'Anything we should know before we call? (optional)', 'taxi-peninsula' ), array( 'type' => 'textarea', 'attrs' => 'rows="3"' ) ); ?>
				<?php tp_spam_fields(); ?>
				<div class="booking-form__submit">
					<button type="submit" class="btn btn--primary btn--lg"><?php tp_the_icon( 'phone' ); ?> <?php esc_html_e( 'Call me back', 'taxi-peninsula' ); ?></button>
					<p class="field__hint"><?php printf( /* translators: %s: phone */ esc_html__( 'Need a taxi in the next hour? Call %s now.', 'taxi-peninsula' ), '<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>' ); ?></p>
				</div>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_nopriv_tp_callback', 'tp_handle_callback' );
add_action( 'admin_post_tp_callback', 'tp_handle_callback' );
function tp_handle_callback() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in tp_form_guard().
	$return = remove_query_arg( array( 'tp_form', 'callback_sent' ), wp_validate_redirect( $return, home_url( '/' ) ) );
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- sanitized per field.
	tp_form_guard( 'tp_callback', $return, $raw, '#callback', 'callback_sent' );

	$d = array(
		'name'   => sanitize_text_field( $raw['name'] ?? '' ),
		'phone'  => sanitize_text_field( $raw['phone'] ?? '' ),
		'when'   => sanitize_key( $raw['when'] ?? 'asap' ),
		'reason' => sanitize_text_field( $raw['reason'] ?? '' ),
		'notes'  => sanitize_textarea_field( $raw['notes'] ?? '' ),
	);
	$d['when']   = isset( tp_callback_times()[ $d['when'] ] ) ? $d['when'] : 'asap';
	$d['reason'] = in_array( $d['reason'], tp_callback_reasons(), true ) ? $d['reason'] : __( 'Something else', 'taxi-peninsula' );
	$errors      = array();
	if ( '' === $d['name'] ) {
		$errors['name'] = __( 'Please enter your name.', 'taxi-peninsula' );
	}
	if ( strlen( preg_replace( '/\D/', '', $d['phone'] ) ) < 8 ) {
		$errors['phone'] = __( 'Please enter a phone number so we can call you.', 'taxi-peninsula' );
	}
	if ( $errors ) {
		$errors['form'] = __( 'Please check the highlighted fields.', 'taxi-peninsula' );
		tp_booking_fail( $return, $errors, $raw, '#callback' );
	}

	$id = wp_insert_post( array( 'post_type' => 'tp_callback', 'post_status' => 'publish', 'post_title' => $d['name'] ) );
	if ( ! $id || is_wp_error( $id ) ) {
		/* translators: %s: phone */
		tp_booking_fail( $return, array( 'form' => sprintf( __( 'Sorry, we could not save your request. Please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw, '#callback' );
	}
	foreach ( array( 'phone', 'when', 'reason', 'notes' ) as $k ) {
		update_post_meta( $id, '_tp_cb_' . $k, $d[ $k ] );
	}
	update_post_meta( $id, '_tp_cb_status', 'new' );
	if ( is_user_logged_in() ) {
		update_post_meta( $id, '_tp_cb_user', get_current_user_id() );
	}
	tp_rate_limit( 'tp_callback', 5, HOUR_IN_SECONDS, true );
	if ( function_exists( 'tp_activity' ) ) {
		/* translators: %s: name */
		tp_activity( 'enquiry', sprintf( __( 'Call-back requested by %s', 'taxi-peninsula' ), $d['name'] ) );
	}

	$when = tp_callback_times()[ $d['when'] ];
	tp_in_site_lang(
		static function () use ( $id, $d, $when ) {
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				/* translators: 1: name, 2: phone */
				sprintf( __( 'Call-back request: %1$s, %2$s', 'taxi-peninsula' ), $d['name'], $d['phone'] ),
				sprintf( "%s\n%s\n%s: %s\n%s: %s\n\n%s\n\n%s: %s", $d['name'], $d['phone'], __( 'Best time', 'taxi-peninsula' ), $when, __( 'About', 'taxi-peninsula' ), $d['reason'], $d['notes'] ?: '—', __( 'Open call-backs', 'taxi-peninsula' ), admin_url( 'edit.php?post_type=tp_callback' ) ),
				tp_mail_headers()
			);
			$office = trim( (string) tp_setting( 'sms_office_number' ) );
			if ( $office && tp_sms_enabled() ) {
				/* translators: 1: name, 2: phone, 3: time */
				tp_send_sms( $office, sprintf( __( 'Call-back: %1$s %2$s (%3$s)', 'taxi-peninsula' ), $d['name'], $d['phone'], $when ) );
			}
		}
	);
	if ( tp_sms_enabled() && preg_match( '/^(\+?61|0)4/', preg_replace( '/[^\d+]/', '', $d['phone'] ) ) ) {
		$site = function_exists( 'tp_sms_site_name' ) ? tp_sms_site_name() : get_bloginfo( 'name' );
		/* translators: 1: site name, 2: time */
		tp_send_sms( $d['phone'], sprintf( __( '%1$s: thanks, we will call you back (%2$s). Need a taxi now? Call %3$s', 'taxi-peninsula' ), $site, $when, wp_strip_all_tags( tp_opt( 'phone_display' ) ) ) );
	}
	wp_safe_redirect( add_query_arg( 'callback_sent', 1, $return ) . '#callback' );
	exit;
}

/* -------------------------------------------------------------------------
 * Office list.
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tp_callback',
	static function () {
		add_meta_box( 'tp-callback', __( 'Call-back details', 'taxi-peninsula' ), 'tp_render_callback_box', 'tp_callback', 'normal', 'high' );
	}
);

function tp_render_callback_box( $post ) {
	$m = tp_callback_meta( $post->ID );
	wp_nonce_field( 'tp_callback_box', '_tp_cb_nonce' );
	?>
	<table class="form-table" role="presentation">
		<tr><th><label for="tp-cb-phone"><?php esc_html_e( 'Phone', 'taxi-peninsula' ); ?></label></th><td><input type="tel" class="regular-text" id="tp-cb-phone" name="tp_cb[phone]" value="<?php echo esc_attr( $m['phone'] ); ?>"> <?php if ( $m['phone'] ) : ?><a class="button" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $m['phone'] ) ); ?>"><?php esc_html_e( 'Call', 'taxi-peninsula' ); ?></a><?php endif; ?></td></tr>
		<tr><th><label for="tp-cb-when"><?php esc_html_e( 'Best time', 'taxi-peninsula' ); ?></label></th><td><select id="tp-cb-when" name="tp_cb[when]"><?php foreach ( tp_callback_times() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m['when'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><label for="tp-cb-reason"><?php esc_html_e( 'About', 'taxi-peninsula' ); ?></label></th><td><input type="text" class="regular-text" id="tp-cb-reason" name="tp_cb[reason]" value="<?php echo esc_attr( $m['reason'] ); ?>"></td></tr>
		<tr><th><label for="tp-cb-notes"><?php esc_html_e( 'Notes', 'taxi-peninsula' ); ?></label></th><td><textarea class="large-text" rows="4" id="tp-cb-notes" name="tp_cb[notes]"><?php echo esc_textarea( $m['notes'] ); ?></textarea></td></tr>
		<tr><th><label for="tp-cb-reference"><?php esc_html_e( 'Booking made (reference)', 'taxi-peninsula' ); ?></label></th><td><input type="text" class="regular-text" id="tp-cb-reference" name="tp_cb[reference]" value="<?php echo esc_attr( $m['reference'] ); ?>" placeholder="TP-XXXXXX"></td></tr>
		<tr><th><label for="tp-cb-status"><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></label></th><td><select id="tp-cb-status" name="tp_cb[status]"><?php foreach ( tp_callback_statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m['status'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<?php if ( $m['attempts'] ) : ?>
				<p class="description"><?php printf( /* translators: 1: number, 2: date */ esc_html__( 'Call attempts: %1$d · last %2$s', 'taxi-peninsula' ), (int) $m['attempts'], esc_html( $m['last_try'] ) ); ?></p>
			<?php endif; ?>
		</td></tr>
	</table>
	<?php
}

add_action(
	'save_post_tp_callback',
	static function ( $post_id ) {
		if ( ! isset( $_POST['_tp_cb_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_cb_nonce'] ) ), 'tp_callback_box' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in  = isset( $_POST['tp_cb'] ) && is_array( $_POST['tp_cb'] ) ? wp_unslash( $_POST['tp_cb'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$old = tp_callback_meta( $post_id );
		foreach ( array( 'phone', 'reason', 'reference' ) as $k ) {
			update_post_meta( $post_id, '_tp_cb_' . $k, sanitize_text_field( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_tp_cb_notes', sanitize_textarea_field( $in['notes'] ?? '' ) );
		$when = sanitize_key( $in['when'] ?? '' );
		update_post_meta( $post_id, '_tp_cb_when', isset( tp_callback_times()[ $when ] ) ? $when : 'asap' );
		$status = sanitize_key( $in['status'] ?? '' );
		$status = isset( tp_callback_statuses()[ $status ] ) ? $status : 'new';
		update_post_meta( $post_id, '_tp_cb_status', $status );
		if ( $status !== $old['status'] && in_array( $status, array( 'no_answer', 'called', 'done' ), true ) ) {
			update_post_meta( $post_id, '_tp_cb_attempts', (int) $old['attempts'] + ( 'done' === $status && 'called' === $old['status'] ? 0 : 1 ) );
			update_post_meta( $post_id, '_tp_cb_last_try', wp_date( 'j M Y g:ia' ) );
			if ( function_exists( 'tp_activity' ) ) {
				/* translators: 1: name, 2: status */
				tp_activity( 'note', sprintf( __( 'Call-back for %1$s: %2$s', 'taxi-peninsula' ), get_the_title( $post_id ), tp_callback_statuses()[ $status ] ) );
			}
		}
	}
);

add_filter(
	'manage_tp_callback_posts_columns',
	static function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => __( 'Name', 'taxi-peninsula' ),
			'tp_phone'  => __( 'Phone', 'taxi-peninsula' ),
			'tp_when'   => __( 'Best time', 'taxi-peninsula' ),
			'tp_reason' => __( 'About', 'taxi-peninsula' ),
			'tp_status' => __( 'Status', 'taxi-peninsula' ),
			'date'      => __( 'Requested', 'taxi-peninsula' ),
		);
	}
);
add_action(
	'manage_tp_callback_posts_custom_column',
	static function ( $col, $id ) {
		$m = tp_callback_meta( $id );
		switch ( $col ) {
			case 'tp_phone':
				printf( '<a href="tel:%s">%s</a>', esc_attr( preg_replace( '/[^\d+]/', '', $m['phone'] ) ), esc_html( $m['phone'] ) );
				break;
			case 'tp_when':
				echo esc_html( tp_callback_times()[ $m['when'] ] ?? $m['when'] );
				break;
			case 'tp_reason':
				echo esc_html( $m['reason'] );
				break;
			case 'tp_status':
				printf( '<span class="tp-pill tp-pill--%s">%s</span>', esc_attr( $m['status'] ), esc_html( tp_callback_statuses()[ $m['status'] ] ?? $m['status'] ) );
				break;
		}
	},
	10,
	2
);

// Waiting call-backs on the menu.
add_action(
	'admin_menu',
	static function () {
		global $submenu;
		$key = tp_admin_parent( 'bookings' );
		if ( empty( $submenu[ $key ] ) ) {
			return;
		}
		$open = count( get_posts( array( 'post_type' => 'tp_callback', 'numberposts' => 99, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_tp_cb_status', 'value' => array( 'new', 'no_answer' ), 'compare' => 'IN' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $open ) {
			return;
		}
		foreach ( $submenu[ $key ] as $i => $item ) {
			if ( 'edit.php?post_type=tp_callback' === $item[2] ) {
				$submenu[ $key ][ $i ][0] .= sprintf( ' <span class="awaiting-mod"><span class="pending-count">%d</span></span>', $open );
			}
		}
	},
	99
);

// Privacy: call-back requests are removed with old messages.
add_action(
	'tp_privacy_cleanup',
	static function () {
		$months = (int) tp_setting( 'retention_messages_months' );
		if ( $months <= 0 ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'tp_callback', 'numberposts' => 200, 'fields' => 'ids', 'date_query' => array( array( 'before' => $months . ' months ago' ) ) ) ) as $id ) {
			wp_delete_post( $id, true );
		}
	}
);
