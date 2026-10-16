<?php
/**
 * Quote requests for long trips (Geelong, regional, interstate) and events
 * (weddings, groups, school formals).
 *
 * [tp_quote_request] shows the form (starter page "Get a Quote"). Requests
 * are saved under Bookings → Quotes. The office enters a price and sends
 * the quote by email; the customer's "Book this trip" link opens the
 * booking form already filled in, and the booking is linked to the quote.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_quote_kinds() {
	return array(
		'long'     => __( 'Long-distance trip (Geelong, regional Victoria)', 'taxi-peninsula' ),
		'interstate' => __( 'Interstate trip', 'taxi-peninsula' ),
		'wedding'  => __( 'Wedding', 'taxi-peninsula' ),
		'group'    => __( 'Group or event (formal, outing, conference)', 'taxi-peninsula' ),
		'regular'  => __( 'Regular trips for an organisation', 'taxi-peninsula' ),
		'other'    => __( 'Something else', 'taxi-peninsula' ),
	);
}

function tp_quote_statuses() {
	return array(
		'new'      => __( 'New', 'taxi-peninsula' ),
		'sent'     => __( 'Quote sent', 'taxi-peninsula' ),
		'booked'   => __( 'Booked', 'taxi-peninsula' ),
		'declined' => __( 'Declined', 'taxi-peninsula' ),
		'expired'  => __( 'Expired', 'taxi-peninsula' ),
	);
}

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_quote',
			array(
				'labels'          => array(
					'name'          => __( 'Quotes', 'taxi-peninsula' ),
					'singular_name' => __( 'Quote', 'taxi-peninsula' ),
					'edit_item'     => __( 'Quote request', 'taxi-peninsula' ),
					'search_items'  => __( 'Search quotes', 'taxi-peninsula' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=tp_booking',
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
		$c['quote'] = 'tp_quote_request';
		return $c;
	}
);

add_filter(
	'tp_starter_pages',
	static function ( $p ) {
		$p['quote'] = array(
			'title'   => __( 'Get a Quote', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Travelling further afield, or organising transport for a wedding, a group outing or an event? Tell us what you need and we will send you a fixed price — usually within one business day.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_quote_request]\n<!-- /wp:shortcode -->",
		);
		return $p;
	}
);

/**
 * Human reference, e.g. Q-1042.
 */
function tp_quote_ref( $id ) {
	return 'Q-' . (int) $id;
}

function tp_quote_token( $id ) {
	return substr( hash_hmac( 'sha256', 'quote|' . $id, wp_salt( 'auth' ) ), 0, 20 );
}

function tp_quote_fields() {
	return array(
		'name', 'email', 'phone', 'organisation', 'kind', 'pickup', 'dropoff', 'date', 'time', 'return_trip', 'passengers', 'wheelchairs', 'details',
	);
}

function tp_get_quote( $id ) {
	$q = array( 'id' => (int) $id, 'ref' => tp_quote_ref( $id ) );
	foreach ( array_merge( tp_quote_fields(), array( 'status', 'price', 'valid_until', 'office_note', 'booking_id', 'source' ) ) as $k ) {
		$q[ $k ] = get_post_meta( $id, '_tp_q_' . $k, true );
	}
	$q['status'] = $q['status'] ?: 'new';
	return $q;
}

/**
 * "Book this trip" link that opens the filled-in booking form.
 */
function tp_quote_book_url( $id ) {
	return add_query_arg( array( 'quote' => (int) $id, 'qt' => tp_quote_token( $id ) ), strtok( tp_booking_page_url(), '#' ) ) . '#book';
}

/* -------------------------------------------------------------------------
 * Public form.
 * ---------------------------------------------------------------------- */

add_shortcode( 'tp_quote_request', 'tp_quote_shortcode' );
function tp_quote_shortcode() {
	list( $errors, $old ) = tp_form_state();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sent = ! empty( $_GET['quote_sent'] );
	$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$v    = static function ( $k, $d = '' ) use ( $old ) {
		return $old[ $k ] ?? $d;
	};
	$f    = static function ( $name, $label, $o = array() ) use ( $errors, $v ) {
		tp_form_field( 'qr', $name, $label, array_merge( array( 'errors' => $errors, 'value' => $v( $name, $o['default'] ?? '' ) ), $o ) );
	};
	ob_start();
	?>
	<div class="tp-component card quote-form" id="quote">
		<?php if ( $sent ) : ?>
			<?php tp_form_success( __( 'Quote request received', 'taxi-peninsula' ), __( 'Thank you. We will email you a price, usually within one business day. We have sent a copy of your request to your email.', 'taxi-peninsula' ) ); ?>
		<?php else : ?>
			<?php if ( $errors ) : ?>
				<div class="notice notice--error" role="alert" tabindex="-1" data-focus><p><?php echo esc_html( $errors['form'] ?? __( 'Please check the highlighted fields.', 'taxi-peninsula' ) ); ?></p></div>
			<?php endif; ?>
			<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="tp_quote">
				<input type="hidden" name="_tp_return" value="<?php echo esc_url( tp_form_return_url() ); ?>">
				<?php wp_nonce_field( 'tp_quote', '_tp_nonce', false ); ?>
				<fieldset class="booking-form__group">
					<legend><?php esc_html_e( 'Your trip', 'taxi-peninsula' ); ?></legend>
					<?php
					$f( 'kind', __( 'What is it for?', 'taxi-peninsula' ), array( 'type' => 'select', 'choices' => tp_quote_kinds(), 'default' => isset( tp_quote_kinds()[ $kind ] ) ? $kind : 'long', 'wide' => true ) );
					$f( 'pickup', __( 'From', 'taxi-peninsula' ), array( 'required' => true, 'attrs' => 'autocomplete="street-address"' ) );
					$f( 'dropoff', __( 'To', 'taxi-peninsula' ), array( 'required' => true ) );
					$f( 'date', __( 'Date', 'taxi-peninsula' ), array( 'type' => 'date', 'required' => true, 'attrs' => 'min="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"' ) );
					$f( 'time', __( 'Pick-up time (if known)', 'taxi-peninsula' ), array( 'type' => 'time' ) );
					$f( 'passengers', __( 'People travelling', 'taxi-peninsula' ), array( 'type' => 'number', 'default' => 1, 'attrs' => 'min="1" max="60"' ) );
					$f( 'wheelchairs', __( 'Wheelchairs', 'taxi-peninsula' ), array( 'type' => 'number', 'default' => 1, 'attrs' => 'min="0" max="20"' ) );
					$f( 'return_trip', __( 'I need a return trip too', 'taxi-peninsula' ), array( 'type' => 'checkbox' ) );
					$f( 'details', __( 'Anything else we should know', 'taxi-peninsula' ), array( 'type' => 'textarea', 'hint' => __( 'e.g. waiting time, stops on the way, wedding schedule, wheelchair size.', 'taxi-peninsula' ) ) );
					?>
				</fieldset>
				<fieldset class="booking-form__group">
					<legend><?php esc_html_e( 'Your details', 'taxi-peninsula' ); ?></legend>
					<?php
					$f( 'name', __( 'Your name', 'taxi-peninsula' ), array( 'required' => true, 'attrs' => 'autocomplete="name"' ) );
					$f( 'email', __( 'Email', 'taxi-peninsula' ), array( 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete="email"' ) );
					$f( 'phone', __( 'Phone', 'taxi-peninsula' ), array( 'type' => 'tel', 'required' => true, 'attrs' => 'autocomplete="tel"' ) );
					$f( 'organisation', __( 'Organisation (optional)', 'taxi-peninsula' ), array( 'attrs' => 'autocomplete="organization"' ) );
					?>
				</fieldset>
				<?php tp_spam_fields(); ?>
				<div class="booking-form__submit">
					<button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Request a quote', 'taxi-peninsula' ); ?></button>
					<p class="field__hint"><?php printf( /* translators: %s: phone */ esc_html__( 'Prefer to talk? Call %s.', 'taxi-peninsula' ), '<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>' ); ?></p>
				</div>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_nopriv_tp_quote', 'tp_handle_quote' );
add_action( 'admin_post_tp_quote', 'tp_handle_quote' );
function tp_handle_quote() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in tp_form_guard().
	$return = remove_query_arg( array( 'tp_form', 'quote_sent' ), wp_validate_redirect( $return, home_url( '/' ) ) );
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- sanitized per field.
	tp_form_guard( 'tp_quote', $return, $raw, '#quote', 'quote_sent' );

	$q = array(
		'name'         => sanitize_text_field( $raw['name'] ?? '' ),
		'email'        => sanitize_email( $raw['email'] ?? '' ),
		'phone'        => sanitize_text_field( $raw['phone'] ?? '' ),
		'organisation' => sanitize_text_field( $raw['organisation'] ?? '' ),
		'kind'         => sanitize_key( $raw['kind'] ?? '' ),
		'pickup'       => sanitize_text_field( $raw['pickup'] ?? '' ),
		'dropoff'      => sanitize_text_field( $raw['dropoff'] ?? '' ),
		'date'         => sanitize_text_field( $raw['date'] ?? '' ),
		'time'         => sanitize_text_field( $raw['time'] ?? '' ),
		'return_trip'  => empty( $raw['return_trip'] ) ? '' : '1',
		'passengers'   => max( 1, min( 60, absint( $raw['passengers'] ?? 1 ) ) ),
		'wheelchairs'  => min( 20, absint( $raw['wheelchairs'] ?? 0 ) ),
		'details'      => sanitize_textarea_field( $raw['details'] ?? '' ),
	);
	if ( ! isset( tp_quote_kinds()[ $q['kind'] ] ) ) {
		$q['kind'] = 'other';
	}
	$errors = array();
	foreach ( array( 'name' => __( 'Please enter your name.', 'taxi-peninsula' ), 'pickup' => __( 'Where are you starting from?', 'taxi-peninsula' ), 'dropoff' => __( 'Where are you going?', 'taxi-peninsula' ) ) as $k => $msg ) {
		if ( '' === $q[ $k ] ) {
			$errors[ $k ] = $msg;
		}
	}
	if ( ! is_email( $q['email'] ) ) {
		$errors['email'] = __( 'Please enter a valid email so we can send your quote.', 'taxi-peninsula' );
	}
	if ( strlen( preg_replace( '/\D/', '', $q['phone'] ) ) < 8 ) {
		$errors['phone'] = __( 'Please enter a phone number.', 'taxi-peninsula' );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $q['date'] ) || $q['date'] < wp_date( 'Y-m-d' ) ) {
		$errors['date'] = __( 'Please choose a date from today onwards.', 'taxi-peninsula' );
	}
	if ( '' !== $q['time'] && ! preg_match( '/^\d{2}:\d{2}$/', $q['time'] ) ) {
		$q['time'] = '';
	}
	if ( $errors ) {
		$errors['form'] = __( 'Please check the highlighted fields.', 'taxi-peninsula' );
		tp_booking_fail( $return, $errors, $raw, '#quote' );
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'tp_quote',
			'post_status' => 'publish',
			'post_title'  => $q['name'] . ' — ' . tp_short_place( $q['pickup'] ) . ' → ' . tp_short_place( $q['dropoff'] ),
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		/* translators: %s: phone */
		tp_booking_fail( $return, array( 'form' => sprintf( __( 'Sorry, we could not save your request. Please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw, '#quote' );
	}
	foreach ( $q as $k => $val ) {
		update_post_meta( $id, '_tp_q_' . $k, $val );
	}
	update_post_meta( $id, '_tp_q_status', 'new' );
	if ( function_exists( 'tp_visit_source' ) ) {
		update_post_meta( $id, '_tp_q_source', implode( ' / ', array_filter( tp_visit_source() ) ) );
		tp_track( 'quote', $id, (string) wp_parse_url( $return, PHP_URL_PATH ) );
	}
	tp_rate_limit( 'tp_quote', 5, HOUR_IN_SECONDS, true );
	if ( function_exists( 'tp_activity' ) ) {
		/* translators: %s: quote reference */
		tp_activity( 'enquiry', sprintf( __( 'Quote request %s received', 'taxi-peninsula' ), tp_quote_ref( $id ) ) );
	}

	$summary = tp_quote_summary( tp_get_quote( $id ) );
	tp_in_site_lang(
		static function () use ( $id, $summary, $q ) {
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				/* translators: 1: reference 2: name */
				sprintf( __( 'Quote request %1$s — %2$s', 'taxi-peninsula' ), tp_quote_ref( $id ), $q['name'] ),
				$summary . "\n" . __( 'Price this quote', 'taxi-peninsula' ) . ': ' . admin_url( 'post.php?action=edit&post=' . $id ),
				tp_mail_headers( $q['email'] )
			);
		}
	);
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	wp_mail(
		$q['email'],
		/* translators: 1: reference 2: site */
		sprintf( __( 'We received your quote request %1$s — %2$s', 'taxi-peninsula' ), tp_quote_ref( $id ), $site ),
		/* translators: %s: name */
		sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $q['name'] ) . "\n\n" . __( 'Thanks for your quote request. We will email you a price, usually within one business day.', 'taxi-peninsula' ) . "\n\n" . $summary . "\n" . $site,
		tp_mail_headers( tp_opt( 'email' ) )
	);

	wp_safe_redirect( add_query_arg( 'quote_sent', 1, $return ) . '#quote' );
	exit;
}

function tp_quote_summary( array $q ) {
	$kinds = tp_quote_kinds();
	$lines = array(
		__( 'Reference', 'taxi-peninsula' )  => $q['ref'],
		__( 'Type', 'taxi-peninsula' )       => $kinds[ $q['kind'] ] ?? $q['kind'],
		__( 'From', 'taxi-peninsula' )       => $q['pickup'],
		__( 'To', 'taxi-peninsula' )         => $q['dropoff'],
		__( 'Date', 'taxi-peninsula' )       => $q['date'] ? wp_date( 'D j M Y', strtotime( $q['date'] ) ) . ( $q['time'] ? ', ' . $q['time'] : '' ) : '',
		__( 'Return trip', 'taxi-peninsula' ) => $q['return_trip'] ? __( 'Yes', 'taxi-peninsula' ) : __( 'No', 'taxi-peninsula' ),
		__( 'People', 'taxi-peninsula' )     => $q['passengers'],
		__( 'Wheelchairs', 'taxi-peninsula' ) => $q['wheelchairs'],
		__( 'Name', 'taxi-peninsula' )       => $q['name'],
		__( 'Phone', 'taxi-peninsula' )      => $q['phone'],
		__( 'Email', 'taxi-peninsula' )      => $q['email'],
	);
	if ( $q['organisation'] ) {
		$lines[ __( 'Organisation', 'taxi-peninsula' ) ] = $q['organisation'];
	}
	if ( $q['details'] ) {
		$lines[ __( 'Details', 'taxi-peninsula' ) ] = $q['details'];
	}
	$out = '';
	foreach ( $lines as $k => $v ) {
		$out .= $k . ': ' . $v . "\n";
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Admin: price and send.
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tp_quote',
	static function () {
		add_meta_box( 'tp_quote_request', __( 'Request', 'taxi-peninsula' ), 'tp_render_quote_request_box', 'tp_quote', 'normal', 'high' );
		add_meta_box( 'tp_quote_price', __( 'Your quote', 'taxi-peninsula' ), 'tp_render_quote_price_box', 'tp_quote', 'side', 'high' );
	}
);

function tp_render_quote_request_box( $post ) {
	$q = tp_get_quote( $post->ID );
	echo '<pre class="tp-pre">' . esc_html( tp_quote_summary( $q ) ) . '</pre>';
	if ( $q['source'] ) {
		/* translators: %s: source */
		echo '<p class="description">' . esc_html( sprintf( __( 'Came from: %s', 'taxi-peninsula' ), $q['source'] ) ) . '</p>';
	}
	if ( $q['booking_id'] && get_post( $q['booking_id'] ) ) {
		printf( '<p><strong>%1$s</strong> <a href="%2$s">%3$s</a></p>', esc_html__( 'Booked:', 'taxi-peninsula' ), esc_url( get_edit_post_link( $q['booking_id'] ) ), esc_html( get_post_meta( $q['booking_id'], '_tp_reference', true ) ) );
	}
}

function tp_render_quote_price_box( $post ) {
	$q = tp_get_quote( $post->ID );
	wp_nonce_field( 'tp_quote_save', 'tp_quote_nonce' );
	?>
	<p><label for="tp_q_status"><strong><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></strong></label><br>
		<select id="tp_q_status" name="tp_q_status" class="widefat">
			<?php foreach ( tp_quote_statuses() as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $q['status'], $k ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select></p>
	<p><label for="tp_q_price"><strong><?php esc_html_e( 'Price incl. GST ($)', 'taxi-peninsula' ); ?></strong></label>
		<input type="number" step="0.01" min="0" id="tp_q_price" name="tp_q_price" class="widefat" value="<?php echo esc_attr( $q['price'] ); ?>"></p>
	<p><label for="tp_q_valid"><strong><?php esc_html_e( 'Valid until', 'taxi-peninsula' ); ?></strong></label>
		<input type="date" id="tp_q_valid" name="tp_q_valid" class="widefat" value="<?php echo esc_attr( $q['valid_until'] ?: wp_date( 'Y-m-d', strtotime( '+14 days' ) ) ); ?>"></p>
	<p><label for="tp_q_note"><strong><?php esc_html_e( 'Note to the customer', 'taxi-peninsula' ); ?></strong></label>
		<textarea id="tp_q_note" name="tp_q_note" class="widefat" rows="4" placeholder="<?php esc_attr_e( 'e.g. Includes 30 minutes waiting time and tolls.', 'taxi-peninsula' ); ?>"><?php echo esc_textarea( $q['office_note'] ); ?></textarea></p>
	<p><label><input type="checkbox" name="tp_q_send" value="1"> <?php esc_html_e( 'Email this quote to the customer when I save', 'taxi-peninsula' ); ?></label></p>
	<p class="description"><?php esc_html_e( 'The email has a "Book this trip" button that opens the booking form already filled in.', 'taxi-peninsula' ); ?></p>
	<?php
}

add_action(
	'save_post_tp_quote',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_quote_nonce'] ), 'tp_quote_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$status = isset( $_POST['tp_q_status'] ) ? sanitize_key( wp_unslash( $_POST['tp_q_status'] ) ) : 'new';
		$price  = isset( $_POST['tp_q_price'] ) && '' !== $_POST['tp_q_price'] ? round( max( 0, (float) $_POST['tp_q_price'] ), 2 ) : '';
		$valid  = isset( $_POST['tp_q_valid'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_q_valid'] ) ) : '';
		update_post_meta( $post_id, '_tp_q_price', $price );
		update_post_meta( $post_id, '_tp_q_valid_until', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valid ) ? $valid : '' );
		update_post_meta( $post_id, '_tp_q_office_note', isset( $_POST['tp_q_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_q_note'] ) ) : '' );
		if ( ! empty( $_POST['tp_q_send'] ) && '' !== $price ) {
			tp_quote_send( $post_id );
			$status = 'booked' === $status ? $status : 'sent';
		}
		update_post_meta( $post_id, '_tp_q_status', isset( tp_quote_statuses()[ $status ] ) ? $status : 'new' );
	}
);

/**
 * Email the priced quote to the customer.
 */
function tp_quote_send( $id ) {
	$q    = tp_get_quote( $id );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: %s: name */
	$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $q['name'] ) . "\n\n";
	/* translators: 1: price 2: date */
	$body .= sprintf( __( 'Thanks for asking. Our price for this trip is %1$s including GST. This quote is valid until %2$s.', 'taxi-peninsula' ), tp_money( $q['price'] ), $q['valid_until'] ? wp_date( 'j F Y', strtotime( $q['valid_until'] ) ) : '—' ) . "\n\n";
	if ( $q['office_note'] ) {
		$body .= $q['office_note'] . "\n\n";
	}
	$body .= tp_quote_summary( $q ) . "\n";
	$body .= __( 'Book this trip', 'taxi-peninsula' ) . ': ' . tp_quote_book_url( $id ) . "\n\n";
	if ( function_exists( 'tp_pay_options_text' ) && tp_pay_options_available() ) {
		$body .= tp_pay_options_text( 'quote', $id, $q['ref'] ) . "\n";
	}
	$body .= apply_filters( 'tp_customer_email_footer', '' ) . "\n";
	/* translators: %s: phone */
	$body .= sprintf( __( 'Questions? Reply to this email or call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) . "\n\n" . $site;
	wp_mail(
		$q['email'],
		/* translators: 1: reference 2: site */
		sprintf( __( 'Your quote %1$s — %2$s', 'taxi-peninsula' ), $q['ref'], $site ),
		$body,
		tp_mail_headers( tp_opt( 'email' ) )
	);
	update_post_meta( $id, '_tp_q_sent_at', current_time( 'mysql' ) );
}

add_filter(
	'manage_tp_quote_posts_columns',
	static function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => __( 'Request', 'taxi-peninsula' ),
			'tp_kind'   => __( 'Type', 'taxi-peninsula' ),
			'tp_date'   => __( 'Trip date', 'taxi-peninsula' ),
			'tp_price'  => __( 'Price', 'taxi-peninsula' ),
			'tp_status' => __( 'Status', 'taxi-peninsula' ),
			'date'      => __( 'Received', 'taxi-peninsula' ),
		);
	}
);
add_action(
	'manage_tp_quote_posts_custom_column',
	static function ( $col, $id ) {
		$q = tp_get_quote( $id );
		switch ( $col ) {
			case 'tp_kind':
				echo esc_html( tp_quote_kinds()[ $q['kind'] ] ?? $q['kind'] );
				break;
			case 'tp_date':
				echo esc_html( $q['date'] );
				break;
			case 'tp_price':
				echo '' !== (string) $q['price'] ? esc_html( tp_money( $q['price'] ) ) : '—';
				break;
			case 'tp_status':
				echo '<span class="tp-badge tp-badge--' . esc_attr( $q['status'] ) . '">' . esc_html( tp_quote_statuses()[ $q['status'] ] ?? $q['status'] ) . '</span>';
				break;
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Booking from a quote.
 * ---------------------------------------------------------------------- */

/**
 * Quote ID from a valid ?quote=&qt= link (or posted fields), else 0.
 */
function tp_quote_from_request( $src ) {
	$id  = absint( $src['quote'] ?? ( $src['tp_quote'] ?? 0 ) );
	$tok = sanitize_key( (string) ( $src['qt'] ?? ( $src['tp_qt'] ?? '' ) ) );
	return ( $id && 'tp_quote' === get_post_type( $id ) && hash_equals( tp_quote_token( $id ), $tok ) ) ? $id : 0;
}

add_filter(
	'tp_booking_form_link_prefill',
	static function ( $old ) {
		$id = tp_quote_from_request( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- token checked.
		if ( ! $id ) {
			return $old;
		}
		$q = tp_get_quote( $id );
		foreach ( array( 'name', 'email', 'phone', 'pickup', 'dropoff', 'date', 'time', 'passengers', 'wheelchairs', 'return_trip' ) as $k ) {
			if ( '' !== (string) $q[ $k ] ) {
				$old[ $k ] = $q[ $k ];
			}
		}
		if ( (int) $q['wheelchairs'] > 1 ) {
			$old['vehicle'] = 'maxi_wat';
		}
		/* translators: 1: quote reference 2: price */
		$old['notes']    = trim( sprintf( __( 'Quote %1$s: %2$s.', 'taxi-peninsula' ), $q['ref'], '' !== (string) $q['price'] ? tp_money( $q['price'] ) : '' ) . ' ' . $q['details'] );
		$old['tp_quote'] = $id;
		$old['tp_qt']    = tp_quote_token( $id );
		return $old;
	}
);

add_action(
	'tp_booking_form_hidden',
	static function ( $old ) {
		if ( ! empty( $old['tp_quote'] ) && tp_quote_from_request( $old ) ) {
			printf( '<input type="hidden" name="tp_quote" value="%1$d"><input type="hidden" name="tp_qt" value="%2$s">', (int) $old['tp_quote'], esc_attr( $old['tp_qt'] ) );
		}
	}
);

add_action(
	'tp_booking_created',
	static function ( $post_id, $ids ) {
		$qid = tp_quote_from_request( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by the booking handler; token checked.
		if ( ! $qid ) {
			return;
		}
		$q = tp_get_quote( $qid );
		update_post_meta( $qid, '_tp_q_status', 'booked' );
		update_post_meta( $qid, '_tp_q_booking_id', $post_id );
		foreach ( (array) $ids as $id ) {
			update_post_meta( $id, '_tp_quote_id', $qid );
			if ( '' !== (string) $q['price'] ) {
				update_post_meta( $id, '_tp_estimate', tp_money( $q['price'] ) . ' ' . __( '(quoted)', 'taxi-peninsula' ) );
			}
			/* translators: %s: quote reference */
			tp_add_log( $id, array( 'note' => sprintf( __( 'Booked from quote %s', 'taxi-peninsula' ), $q['ref'] ) ) );
		}
	},
	25,
	2
);

// Quotes past their date expire.
add_action(
	'wp_scheduled_delete',
	static function () {
		$ids = get_posts(
			array(
				'post_type'   => 'tp_quote',
				'numberposts' => 200,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_tp_q_status', 'value' => array( 'new', 'sent' ), 'compare' => 'IN' ),
					array( 'key' => '_tp_q_date', 'value' => wp_date( 'Y-m-d' ), 'compare' => '<' ),
				),
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( $id, '_tp_q_status', 'expired' );
		}
	}
);
