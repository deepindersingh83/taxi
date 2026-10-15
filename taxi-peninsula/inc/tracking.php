<?php
/**
 * Lead tracking: which pages, campaigns and partners bring calls and bookings.
 *
 * - The first page of a visit records where the visitor came from (UTM tags,
 *   Google Ads, a partner page or QR code, a search engine or another site)
 *   in a first-party cookie `tp_src` for 30 days. No personal information.
 * - Taps on phone, WhatsApp, SMS and email links are counted with the page
 *   and that source. Bookings, quotes and sign-ups are tagged with it too.
 * - Optional tracking numbers: show a different phone number to visitors
 *   from a given source (numbers from a call-tracking provider).
 * - Report: Bookings → Leads & calls.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_TRACK_DB = 1;

function tp_track_table() {
	global $wpdb;
	return $wpdb->prefix . 'tp_track';
}

add_action(
	'init',
	static function () {
		if ( (int) get_option( 'tp_track_db' ) === TP_TRACK_DB ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = tp_track_table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created datetime NOT NULL,
				type varchar(20) NOT NULL DEFAULT '',
				ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
				page varchar(255) NOT NULL DEFAULT '',
				source varchar(100) NOT NULL DEFAULT '',
				medium varchar(60) NOT NULL DEFAULT '',
				campaign varchar(100) NOT NULL DEFAULT '',
				device varchar(10) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY created (created),
				KEY type (type),
				KEY ref_id (ref_id)
			) {$wpdb->get_charset_collate()};"
		);
		update_option( 'tp_track_db', TP_TRACK_DB, false );
	},
	5
);

/**
 * Event types shown in the report.
 */
function tp_track_types() {
	return array(
		'call'     => __( 'Phone taps', 'taxi-peninsula' ),
		'whatsapp' => __( 'WhatsApp taps', 'taxi-peninsula' ),
		'sms'      => __( 'SMS taps', 'taxi-peninsula' ),
		'email'    => __( 'Email taps', 'taxi-peninsula' ),
		'booking'  => __( 'Bookings', 'taxi-peninsula' ),
		'quote'    => __( 'Quote requests', 'taxi-peninsula' ),
		'signup'   => __( 'Newsletter sign-ups', 'taxi-peninsula' ),
		'view'     => __( 'Partner page views', 'taxi-peninsula' ),
		'qr'       => __( 'QR code scans', 'taxi-peninsula' ),
	);
}

/**
 * Where this visitor came from: [source, medium, campaign].
 */
function tp_visit_source() {
	static $src = null;
	if ( null !== $src ) {
		return $src;
	}
	$src = array( '', '', '' );
	if ( ! empty( $_COOKIE['tp_src'] ) ) {
		$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['tp_src'] ) ) );
		$src   = array( $parts[0] ?? '', $parts[1] ?? '', $parts[2] ?? '' );
	}
	$now = tp_source_from_request();
	if ( $now ) {
		$src = $now;
	}
	return $src;
}

/**
 * Source from this request's URL or referrer, or null.
 */
function tp_source_from_request() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only tags.
	$get = static function ( $k, $len = 100 ) {
		return isset( $_GET[ $k ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET[ $k ] ) ), 0, $len ) : '';
	};
	if ( $get( 'utm_source' ) ) {
		return array( strtolower( $get( 'utm_source' ) ), strtolower( $get( 'utm_medium', 60 ) ), $get( 'utm_campaign' ) );
	}
	if ( $get( 'gclid' ) || $get( 'gbraid' ) || $get( 'wbraid' ) ) {
		return array( 'google', 'cpc', $get( 'utm_campaign' ) );
	}
	if ( $get( 'fbclid' ) ) {
		return array( 'facebook', 'social', '' );
	}
	if ( $get( 'src' ) ) {
		return array( sanitize_key( $get( 'src' ) ), 'partner', '' );
	}
	// phpcs:enable
	$ref  = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST ) : '';
	$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( $ref && $ref !== $home ) {
		$ref = preg_replace( '/^www\./', '', strtolower( $ref ) );
		foreach ( array( 'google.' => 'google', 'bing.' => 'bing', 'duckduckgo.' => 'duckduckgo', 'yahoo.' => 'yahoo' ) as $needle => $engine ) {
			if ( false !== strpos( $ref, $needle ) ) {
				return array( $engine, 'organic', '' );
			}
		}
		foreach ( array( 'facebook.' => 'facebook', 'instagram.' => 'instagram', 'linkedin.' => 'linkedin', 't.co' => 'twitter' ) as $needle => $net ) {
			if ( false !== strpos( $ref, $needle ) ) {
				return array( $net, 'social', '' );
			}
		}
		return array( $ref, 'referral', '' );
	}
	return null;
}

// Remember the latest known source for 30 days (direct visits keep it).
add_action(
	'init',
	static function () {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || headers_sent() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$now = tp_source_from_request();
		if ( ! $now ) {
			return;
		}
		$value = implode( '|', array_map( static fn( $v ) => str_replace( '|', '', $v ), $now ) );
		setcookie( 'tp_src', $value, array( 'expires' => time() + 30 * DAY_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax' ) );
		$_COOKIE['tp_src'] = $value;
	},
	1
);

/**
 * Record an event.
 *
 * @param string $type   One of tp_track_types().
 * @param int    $ref_id Booking, quote or partner ID.
 * @param string $page   Page path.
 */
function tp_track( $type, $ref_id = 0, $page = '' ) {
	global $wpdb;
	if ( (int) get_option( 'tp_track_db' ) !== TP_TRACK_DB || ! isset( tp_track_types()[ $type ] ) ) {
		return;
	}
	list( $source, $medium, $campaign ) = tp_visit_source();
	if ( '' === $page ) {
		$page = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		tp_track_table(),
		array(
			'created'  => current_time( 'mysql' ),
			'type'     => $type,
			'ref_id'   => (int) $ref_id,
			'page'     => mb_substr( $page, 0, 255 ),
			'source'   => $source ?: '(direct)',
			'medium'   => $medium,
			'campaign' => $campaign,
			'device'   => preg_match( '/Mobi|Android|iPhone/i', $ua ) ? 'mobile' : 'desktop',
		),
		array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
	);
}

// Bookings remember where the customer came from.
add_action(
	'tp_booking_created',
	static function ( $post_id, $ids ) {
		list( $source, $medium, $campaign ) = tp_visit_source();
		foreach ( (array) $ids as $id ) {
			update_post_meta( $id, '_tp_source', trim( ( $source ?: '(direct)' ) . ( $medium ? ' / ' . $medium : '' ) . ( $campaign ? ' / ' . $campaign : '' ) ) );
		}
		$page = isset( $_POST['_tp_return'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the booking handler.
		tp_track( 'booking', $post_id, $page );
	},
	30,
	2
);

// Taps on phone / WhatsApp / SMS / email links.
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'taxi-peninsula/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function ( WP_REST_Request $req ) {
					$type = sanitize_key( (string) $req->get_param( 'type' ) );
					if ( ! in_array( $type, array( 'call', 'whatsapp', 'sms', 'email' ), true ) || ! tp_rate_limit( 'track', 60, 10 * MINUTE_IN_SECONDS ) ) {
						return new WP_REST_Response( null, 204 );
					}
					$page = (string) wp_parse_url( esc_url_raw( (string) $req->get_param( 'page' ) ), PHP_URL_PATH );
					tp_track( $type, absint( $req->get_param( 'ref' ) ), $page );
					return new WP_REST_Response( null, 204 );
				},
			)
		);
	}
);

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['tracking'] = array( __( 'Call tracking', 'taxi-peninsula' ), __( 'See Bookings → Leads & calls for the report.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['tracking_numbers'] = array( 'tracking', __( 'Tracking phone numbers', 'taxi-peninsula' ), 'textarea', '', __( 'Optional. One per line: source | medium | number shown | number dialled. Visitors who arrived from that source see that number instead of your main number, so your call-tracking provider can count calls from it. Example: google | cpc | 03 5900 1111 | +61359001111 (leave medium empty to match any medium).', 'taxi-peninsula' ) );
		return $s;
	}
);

/**
 * Tracking numbers: [source, medium, display, dial] from the setting.
 */
function tp_tracking_numbers() {
	$out = array();
	foreach ( preg_split( '/\R/', (string) tp_setting( 'tracking_numbers' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 3 && '' !== $p[0] && '' !== $p[2] ) {
			$out[] = array( strtolower( $p[0] ), strtolower( $p[1] ), $p[2], preg_replace( '/[^\d+]/', '', $p[3] ?? $p[2] ) );
		}
	}
	return $out;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_add_inline_script(
			'tp-main',
			'window.tpTrack = ' . wp_json_encode(
				array(
					'url'     => esc_url_raw( rest_url( 'taxi-peninsula/v1/track' ) ),
					'numbers' => tp_tracking_numbers(),
					'phone'   => preg_replace( '/[^\d+]/', '', tp_opt( 'phone_link' ) ),
				)
			) . ';',
			'before'
		);
	},
	30
);

/* -------------------------------------------------------------------------
 * Bookings → Leads & calls.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'edit.php?post_type=tp_booking', __( 'Leads & calls', 'taxi-peninsula' ), __( 'Leads & calls', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-leads', 'tp_render_leads_page' );
	},
	42
);

function tp_render_leads_page() {
	global $wpdb;
	if ( ! current_user_can( 'edit_tp_bookings' ) ) {
		return;
	}
	$days  = isset( $_GET['days'] ) ? max( 1, min( 365, absint( $_GET['days'] ) ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$since = wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$table = tp_track_table();
	$types = tp_track_types();
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$by_source = $wpdb->get_results( $wpdb->prepare( "SELECT source, medium, campaign, type, COUNT(*) n FROM {$table} WHERE created >= %s GROUP BY source, medium, campaign, type", $since ) );
	$by_page   = $wpdb->get_results( $wpdb->prepare( "SELECT page, type, COUNT(*) n FROM {$table} WHERE created >= %s AND type IN ('call','whatsapp','sms','email','booking') GROUP BY page, type", $since ) );
	$totals    = $wpdb->get_results( $wpdb->prepare( "SELECT type, COUNT(*) n FROM {$table} WHERE created >= %s GROUP BY type", $since ), OBJECT_K );
	// phpcs:enable
	$pivot = static function ( $rows, $key_fn ) {
		$out = array();
		foreach ( $rows as $r ) {
			$k = $key_fn( $r );
			$out[ $k ][ $r->type ] = ( $out[ $k ][ $r->type ] ?? 0 ) + (int) $r->n;
		}
		uasort( $out, static fn( $a, $b ) => array_sum( $b ) <=> array_sum( $a ) );
		return $out;
	};
	$sources = $pivot( $by_source, static fn( $r ) => trim( $r->source . ( $r->medium ? ' / ' . $r->medium : '' ) . ( $r->campaign ? ' / ' . $r->campaign : '' ) ) );
	$pages   = $pivot( $by_page, static fn( $r ) => $r->page ?: '/' );
	$cols    = array( 'call', 'whatsapp', 'sms', 'booking', 'quote', 'signup' );
	?>
	<div class="wrap tp-leads">
		<h1><?php esc_html_e( 'Leads & calls', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Which campaigns, websites, partners and pages bring phone calls, messages and bookings. Phone taps are counted when someone taps your number on the website; calls dialled from memory or Google Maps are not seen here — use tracking numbers (Bookings → Settings → Call tracking) to measure those.', 'taxi-peninsula' ); ?></p>
		<p class="tp-leads__range">
			<?php foreach ( array( 7, 30, 90, 365 ) as $d ) : ?>
				<a class="button<?php echo $d === $days ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $d ) ); ?>">
					<?php
					/* translators: %d: days */
					echo esc_html( sprintf( _n( 'Last %d day', 'Last %d days', $d, 'taxi-peninsula' ), $d ) );
					?>
				</a>
			<?php endforeach; ?>
		</p>
		<ul class="tp-leads__totals">
			<?php foreach ( $types as $k => $label ) : ?>
				<li><strong><?php echo esc_html( number_format_i18n( isset( $totals[ $k ] ) ? (int) $totals[ $k ]->n : 0 ) ); ?></strong><span><?php echo esc_html( $label ); ?></span></li>
			<?php endforeach; ?>
		</ul>

		<?php
		foreach ( array(
			array( __( 'By source and campaign', 'taxi-peninsula' ), __( 'Source / medium / campaign', 'taxi-peninsula' ), $sources ),
			array( __( 'By page', 'taxi-peninsula' ), __( 'Page', 'taxi-peninsula' ), $pages ),
		) as $block ) :
			?>
			<h2><?php echo esc_html( $block[0] ); ?></h2>
			<?php if ( ! $block[2] ) : ?>
				<p><?php esc_html_e( 'Nothing recorded in this period yet.', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html( $block[1] ); ?></th>
						<?php foreach ( $cols as $c ) : ?>
							<th class="num"><?php echo esc_html( $types[ $c ] ); ?></th>
						<?php endforeach; ?>
					</tr></thead>
					<tbody>
						<?php foreach ( array_slice( $block[2], 0, 50, true ) as $label => $counts ) : ?>
							<tr><td><?php echo esc_html( $label ); ?></td>
								<?php foreach ( $cols as $c ) : ?>
									<td class="num"><?php echo esc_html( $counts[ $c ] ?? 0 ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php do_action( 'tp_leads_report_after', $since ); ?>
		<h2><?php esc_html_e( 'Tracking links', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'Add these tags to links in ads, emails and social posts to see them above:', 'taxi-peninsula' ); ?></p>
		<p><code><?php echo esc_html( add_query_arg( array( 'utm_source' => 'facebook', 'utm_medium' => 'social', 'utm_campaign' => 'winter-offer' ), home_url( '/' ) ) ); ?></code></p>
	</div>
	<?php
}
