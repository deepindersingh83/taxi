<?php
/**
 * More ways to get leads (Settings → Marketing & SEO → Leads & follow-up).
 *
 * - "Text us your booking": an SMS button that opens the visitor's messages
 *   app with a short booking template (and what they already typed). Shown
 *   under the booking form, on the booking page and on the contact page.
 * - Quote by text: two fields (from, to) give an instant price; "Text me
 *   this quote" sends it to the visitor's mobile with a booking link, and the
 *   number is kept as a lead (Reports → Leads & calls). Shortcode
 *   [tp_text_quote], and a home page section.
 * - Phone taps to follow up: someone tapped your number but no booking came
 *   from the same browser within 30 minutes. Listed on Leads & calls with
 *   the time, so staff can match it with the missed calls on the phone.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_FOLLOW_MINUTES = 30;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['leads'] = array( __( 'Leads & follow-up', 'taxi-peninsula' ), __( 'See Reports → Leads & calls for quotes sent by text and phone taps to follow up.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['text_us_on']     = array( 'leads', __( 'Show “Text us your booking” buttons', 'taxi-peninsula' ), 'checkbox', true, __( 'Opens the visitor’s messages app with a short booking template.', 'taxi-peninsula' ) );
		$s['text_us_number'] = array( 'leads', __( 'Mobile number that receives the texts', 'taxi-peninsula' ), 'text', '', __( 'Leave empty to use your main number when it is a mobile.', 'taxi-peninsula' ) );
		$s['text_quote_on']  = array( 'leads', __( 'Quote by text', 'taxi-peninsula' ), 'checkbox', true, __( 'Visitors type two addresses and see a price; “Text me this quote” sends it to their mobile (needs SMS and fare estimates set up).', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_tabs',
	static function ( $tabs ) {
		$tabs['marketing'][1][] = 'leads';
		return $tabs;
	}
);

/* =========================================================================
 * "Text us your booking".
 * ====================================================================== */

/** The number texts go to, as +61…, or ''. */
function tp_text_us_number() {
	if ( ! tp_setting( 'text_us_on' ) ) {
		return '';
	}
	foreach ( array( (string) tp_setting( 'text_us_number' ), (string) tp_opt( 'phone_link' ), (string) tp_opt( 'phone_display' ) ) as $n ) {
		$p = '' !== trim( $n ) ? tp_au_phone( $n ) : null;
		if ( $p && 'mobile' === $p['type'] ) {
			return $p['e164'];
		}
	}
	return '';
}

/** The message the texting app opens with. */
function tp_text_us_template() {
	return implode(
		"\n",
		array(
			__( 'Hi, I’d like to book a taxi.', 'taxi-peninsula' ),
			__( 'From:', 'taxi-peninsula' ) . ' ',
			__( 'To:', 'taxi-peninsula' ) . ' ',
			__( 'Date and time:', 'taxi-peninsula' ) . ' ',
			__( 'Wheelchair (yes/no):', 'taxi-peninsula' ) . ' ',
			__( 'Name:', 'taxi-peninsula' ) . ' ',
		)
	);
}

function tp_text_us_href( $body = null ) {
	$num = tp_text_us_number();
	return $num ? 'sms:' . $num . '?&body=' . rawurlencode( null === $body ? tp_text_us_template() : $body ) : '';
}

/**
 * The button.
 *
 * @param string $class Extra classes.
 * @param string $label Button text.
 */
function tp_text_us_button( $class = 'btn btn--ghost', $label = '' ) {
	$href = tp_text_us_href();
	if ( ! $href ) {
		return '';
	}
	$labels = array(
		'from' => __( 'From:', 'taxi-peninsula' ),
		'to'   => __( 'To:', 'taxi-peninsula' ),
		'when' => __( 'Date and time:', 'taxi-peninsula' ),
		'wc'   => __( 'Wheelchair (yes/no):', 'taxi-peninsula' ),
		'name' => __( 'Name:', 'taxi-peninsula' ),
		'hi'   => __( 'Hi, I’d like to book a taxi.', 'taxi-peninsula' ),
		'yes'  => __( 'Yes', 'taxi-peninsula' ),
		'no'   => __( 'No', 'taxi-peninsula' ),
	);
	return '<a class="' . esc_attr( $class . ' text-us' ) . '" href="' . esc_attr( $href ) . '" data-text-us="' . esc_attr( wp_json_encode( $labels ) ) . '">' . tp_icon( 'mail' ) . '<span>' . esc_html( $label ?: __( 'Text us your booking', 'taxi-peninsula' ) ) . '</span></a>';
}

add_action(
	'tp_booking_form_after_submit',
	static function () {
		$btn = tp_text_us_button( 'text-us--link', __( 'Prefer to text? Text us your booking', 'taxi-peninsula' ) );
		if ( $btn ) {
			echo '<p class="booking-form__alt">' . $btn . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		}
	},
	5
);

add_filter(
	'tp_contact_chooser_items',
	static function ( $items ) {
		$href = tp_text_us_href();
		if ( $href ) {
			$items[] = array( 'mail', __( 'Text us your booking', 'taxi-peninsula' ), __( 'Your messages app opens with a short template to fill in.', 'taxi-peninsula' ), $href );
		}
		return $items;
	}
);

add_shortcode(
	'tp_text_us',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'label' => '' ), $atts, 'tp_text_us' );
		return tp_text_us_button( 'btn btn--ghost', $atts['label'] );
	}
);

/* =========================================================================
 * Quote by text.
 * ====================================================================== */

function tp_text_quote_available() {
	return tp_setting( 'text_quote_on' ) && tp_fare_available();
}

add_shortcode(
	'tp_text_quote',
	static function () {
		ob_start();
		get_template_part( 'template-parts/text-quote' );
		return ob_get_clean();
	}
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'taxi-peninsula/v1',
			'/text-quote',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public, rate limited per connection and per number.
				'callback'            => 'tp_rest_text_quote',
				'args'                => array(
					'from'  => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'to'    => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'phone' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'hp'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}
);

function tp_rest_text_quote( WP_REST_Request $req ) {
	$fail = static function ( $msg, $code = 400 ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => $msg ), $code );
	};
	if ( ! tp_text_quote_available() || ! tp_sms_enabled() ) {
		return $fail( __( 'Quotes by text are not available at the moment. Please call us.', 'taxi-peninsula' ), 404 );
	}
	if ( '' !== (string) $req['hp'] ) {
		return array( 'ok' => true, 'message' => __( 'Sent! Check your messages.', 'taxi-peninsula' ) ); // Spam robot: say yes, send nothing.
	}
	$p = tp_au_phone( (string) $req['phone'] );
	if ( ! $p || 'mobile' !== $p['type'] ) {
		return $fail( __( 'Please enter an Australian mobile number, e.g. 0412 345 678.', 'taxi-peninsula' ) );
	}
	$per_number = 'tp_tq_' . substr( hash_hmac( 'sha256', $p['e164'], wp_salt( 'auth' ) ), 0, 16 );
	if ( (int) get_transient( $per_number ) >= 3 || ! tp_rate_limit( 'text_quote', 4, HOUR_IN_SECONDS ) ) {
		/* translators: %s: phone */
		return $fail( sprintf( __( 'Too many quotes sent. Please call us on %s.', 'taxi-peninsula' ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) ), 429 );
	}
	$from  = mb_substr( (string) $req['from'], 0, 150 );
	$to    = mb_substr( (string) $req['to'], 0, 150 );
	$route = ( mb_strlen( $from ) >= 5 && mb_strlen( $to ) >= 5 ) ? tp_route_distance( $from, $to ) : null;
	$fare  = $route ? tp_fare_from_distance( $route[0], $route[1], '', '', $from, $to ) : null;
	if ( ! $fare ) {
		return $fail( __( 'We couldn’t work out that trip. Please check both addresses, or call us for a price.', 'taxi-peninsula' ) );
	}
	$price = tp_fare_text( $fare[0], $fare[1] );
	$link  = add_query_arg( array( 'pickup' => rawurlencode( $from ), 'dropoff' => rawurlencode( $to ), 'src' => 'textquote' ), tp_booking_page_url() );
	$msg   = sprintf(
		/* translators: 1: site 2: from 3: to 4: price 5: booking link 6: phone */
		__( '%1$s: wheelchair taxi from %2$s to %3$s is about %4$s. Book online: %5$s or call %6$s.', 'taxi-peninsula' ),
		tp_sms_site_name(),
		strtok( $from, ',' ),
		strtok( $to, ',' ),
		$price,
		$link,
		wp_strip_all_tags( tp_opt( 'phone_display' ) )
	);
	$sent = tp_send_sms( $p['display'], $msg );
	if ( is_wp_error( $sent ) ) {
		return $fail( __( 'The text could not be sent just now. Please call us.', 'taxi-peninsula' ), 502 );
	}
	set_transient( $per_number, (int) get_transient( $per_number ) + 1, DAY_IN_SECONDS );
	tp_rate_limit( 'text_quote', 4, HOUR_IN_SECONDS, true );
	$list   = (array) get_option( 'tp_text_quotes', array() );
	$list[] = array(
		'at'    => time(),
		'from'  => $from,
		'to'    => $to,
		'price' => $price,
		'phone' => function_exists( 'tp_encrypt' ) ? tp_encrypt( $p['display'] ) : $p['display'],
	);
	update_option( 'tp_text_quotes', array_slice( $list, -300 ), false );
	tp_track( 'quote_text' );
	return array( 'ok' => true, 'message' => __( 'Sent! Check your messages — the text has a link to book.', 'taxi-peninsula' ) );
}

/** Quotes sent by text, newest first, with whether that mobile booked afterwards. */
function tp_text_quotes( $days = 30 ) {
	$out = array();
	foreach ( array_reverse( (array) get_option( 'tp_text_quotes', array() ) ) as $q ) {
		if ( $q['at'] < time() - $days * DAY_IN_SECONDS ) {
			break;
		}
		$q['phone']  = function_exists( 'tp_decrypt' ) ? tp_decrypt( $q['phone'] ) : $q['phone'];
		$q['booked'] = function_exists( 'tp_phone_known' ) && tp_phone_known( tp_phone_digits( $q['phone'] ) );
		$out[]       = $q;
	}
	return $out;
}

/* =========================================================================
 * Phone taps to follow up.
 * ====================================================================== */

/**
 * Phone taps from the last few days with no booking from the same visitor within 30 minutes.
 *
 * @return object[] id, created, page, source, medium, device.
 */
function tp_calls_to_follow( $days = 7 ) {
	global $wpdb;
	$table = tp_track_table();
	$since = wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$until = wp_date( 'Y-m-d H:i:s', time() - TP_FOLLOW_MINUTES * MINUTE_IN_SECONDS );
	$done  = array_map( 'intval', (array) get_option( 'tp_calls_followed', array() ) );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.id, c.created, c.page, c.source, c.medium, c.device FROM {$table} c
			WHERE c.type = 'call' AND c.created >= %s AND c.created <= %s
			AND NOT EXISTS (
				SELECT 1 FROM {$table} b WHERE b.type IN ('booking','form_submit') AND b.visitor <> '' AND b.visitor = c.visitor
				AND b.created >= c.created AND b.created <= DATE_ADD(c.created, INTERVAL %d MINUTE)
			)
			ORDER BY c.created DESC LIMIT 100",
			$since,
			$until,
			TP_FOLLOW_MINUTES
		)
	);
	// phpcs:enable
	return array_values( array_filter( (array) $rows, static fn( $r ) => ! in_array( (int) $r->id, $done, true ) ) );
}

add_action(
	'admin_post_tp_call_followed',
	static function () {
		check_admin_referer( 'tp_call_followed' );
		if ( ! current_user_can( 'tp_view_leads' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'taxi-peninsula' ) );
		}
		$done = (array) get_option( 'tp_calls_followed', array() );
		$ids  = isset( $_POST['all'] ) ? wp_list_pluck( tp_calls_to_follow(), 'id' ) : array( absint( $_POST['id'] ?? 0 ) );
		update_option( 'tp_calls_followed', array_slice( array_unique( array_merge( $done, array_map( 'intval', $ids ) ) ), -2000 ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-leads#follow-up' ) );
		exit;
	}
);

// A count on the menu, so the office notices.
add_action(
	'admin_menu',
	static function () {
		global $submenu;
		if ( ! current_user_can( 'tp_view_leads' ) ) {
			return;
		}
		$n = count( array_filter( tp_calls_to_follow( 1 ) ) );
		if ( ! $n ) {
			return;
		}
		foreach ( $submenu as $parent => &$items ) {
			foreach ( $items as &$item ) {
				if ( 'tp-leads' === $item[2] ) {
					/* translators: %d: number of phone taps */
					$item[0] .= ' <span class="awaiting-mod" title="' . esc_attr( sprintf( _n( '%d phone tap to follow up', '%d phone taps to follow up', $n, 'taxi-peninsula' ), $n ) ) . '">' . (int) $n . '</span>';
				}
			}
		}
	},
	99
);

// Two extra blocks at the top of Leads & calls.
add_action(
	'tp_leads_page_top',
	static function () {
		$calls  = tp_calls_to_follow();
		$quotes = tp_text_quotes();
		?>
		<h2 id="follow-up"><?php esc_html_e( 'Phone taps to follow up', 'taxi-peninsula' ); ?></h2>
		<p class="description">
			<?php
			/* translators: %d: minutes */
			echo esc_html( sprintf( __( 'Someone tapped your number on the website, but no booking came from the same browser within %d minutes. Match the time with missed or short calls on your phone and call them back.', 'taxi-peninsula' ), TP_FOLLOW_MINUTES ) );
			?>
		</p>
		<?php if ( ! $calls ) : ?>
			<p><?php esc_html_e( 'Nothing to follow up in the last 7 days.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped tp-follow">
				<thead><tr><th><?php esc_html_e( 'Tapped at', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Page', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Came from', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Device', 'taxi-peninsula' ); ?></th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $calls as $c ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $c->created ) ); ?></td>
							<td><code><?php echo esc_html( $c->page ?: '/' ); ?></code></td>
							<td><?php echo esc_html( trim( $c->source . ( $c->medium ? ' / ' . $c->medium : '' ) ) ); ?></td>
							<td><?php echo esc_html( 'mobile' === $c->device ? __( 'Phone', 'taxi-peninsula' ) : __( 'Computer', 'taxi-peninsula' ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="tp_call_followed"><input type="hidden" name="id" value="<?php echo esc_attr( $c->id ); ?>">
									<?php wp_nonce_field( 'tp_call_followed' ); ?>
									<button class="button button-small"><?php esc_html_e( 'Done', 'taxi-peninsula' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-follow__all">
				<input type="hidden" name="action" value="tp_call_followed"><input type="hidden" name="all" value="1">
				<?php wp_nonce_field( 'tp_call_followed' ); ?>
				<button class="button"><?php esc_html_e( 'Mark all as done', 'taxi-peninsula' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( tp_setting( 'text_quote_on' ) ) : ?>
			<h2><?php esc_html_e( 'Quotes sent by text', 'taxi-peninsula' ); ?></h2>
			<?php if ( ! $quotes ) : ?>
				<p><?php esc_html_e( 'None in the last 30 days.', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Sent', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Trip', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Price', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Mobile', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Booked with us?', 'taxi-peninsula' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $quotes as $q ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $q['at'] ) ); ?></td>
								<td><?php echo esc_html( $q['from'] . ' → ' . $q['to'] ); ?></td>
								<td><?php echo esc_html( $q['price'] ); ?></td>
								<td><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $q['phone'] ) ); ?>"><?php echo esc_html( $q['phone'] ); ?></a></td>
								<td><?php echo $q['booked'] ? esc_html__( 'Yes', 'taxi-peninsula' ) : '<strong>' . esc_html__( 'Not yet — worth a call', 'taxi-peninsula' ) . '</strong>'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}
);
