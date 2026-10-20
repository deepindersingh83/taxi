<?php
/**
 * Partner leaderboard: which hospitals, aged-care homes and NDIS providers
 * sent the most trips each quarter, with a thank-you email and a printable
 * certificate of appreciation for each.
 *
 * - Reports → Partner leaderboard: pick a quarter; partners ranked by
 *   completed trips (from their partner page, QR poster or portal), with the
 *   change on the quarter before.
 * - "Email thank-you" sends the partner contact a short note with their
 *   numbers and a link to their certificate.
 * - At the start of each quarter the office gets the top five by email.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * A quarter from "2026-Q3" (default: the last full quarter).
 *
 * @return array [ key, from, to, label ]
 */
function tp_quarter( $key = '' ) {
	if ( ! preg_match( '/^(\d{4})-Q([1-4])$/', (string) $key, $m ) ) {
		$prev = strtotime( 'first day of -3 months', strtotime( wp_date( 'Y-m-01' ) ) );
		$m    = array( '', wp_date( 'Y', $prev ), (string) ceil( wp_date( 'n', $prev ) / 3 ) );
	}
	$y     = (int) $m[1];
	$q     = (int) $m[2];
	$first = sprintf( '%04d-%02d-01', $y, $q * 3 - 2 );
	$last  = wp_date( 'Y-m-t', strtotime( sprintf( '%04d-%02d-01', $y, $q * 3 ) ) );
	return array(
		'key'   => $y . '-Q' . $q,
		'from'  => $first,
		'to'    => $last,
		/* translators: 1: first month 2: last month 3: year, e.g. Jul–Sep 2026 */
		'label' => sprintf( __( '%1$s–%2$s %3$s', 'taxi-peninsula' ), wp_date( 'M', strtotime( $first ) ), wp_date( 'M', strtotime( $last ) ), $y ),
	);
}

function tp_quarter_before( array $q ) {
	return tp_quarter( wp_date( 'Y', strtotime( $q['from'] . ' -3 months' ) ) . '-Q' . ceil( wp_date( 'n', strtotime( $q['from'] . ' -3 months' ) ) / 3 ) );
}

/**
 * Completed trips per partner in a quarter.
 *
 * @return array venue_id => [ trips, people ] (highest first)
 */
function tp_partner_counts( array $q ) {
	$ids = function_exists( 'tp_trips_ids' ) ? tp_trips_ids( array( 'from' => $q['from'], 'to' => $q['to'], 'status' => 'completed' ) ) : null;
	if ( null === $ids ) {
		$ids = get_posts(
			array(
				'post_type'   => 'tp_booking',
				'post_status' => 'publish',
				'numberposts' => 5000,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_tp_date', 'value' => array( $q['from'], $q['to'] ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
					array( 'key' => '_tp_status', 'value' => 'completed' ),
					array( 'key' => '_tp_venue_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
				),
			)
		);
	}
	$out = array();
	foreach ( $ids as $id ) {
		$venue = (int) get_post_meta( $id, '_tp_venue_id', true );
		if ( ! $venue || 'tp_venue' !== get_post_type( $venue ) ) {
			continue;
		}
		$out[ $venue ]['trips']                    = ( $out[ $venue ]['trips'] ?? 0 ) + 1;
		$out[ $venue ]['people'][ strtolower( trim( (string) get_post_meta( $id, '_tp_name', true ) ) ) ] = 1;
	}
	foreach ( $out as &$row ) {
		$row['people'] = count( $row['people'] );
	}
	unset( $row );
	uasort( $out, static fn( $a, $b ) => $b['trips'] <=> $a['trips'] ?: $b['people'] <=> $a['people'] );
	return $out;
}

function tp_partner_cert_token( $quarter, $venue ) {
	return substr( hash_hmac( 'sha256', 'partner-cert|' . $quarter . '|' . (int) $venue, wp_salt( 'auth' ) ), 0, 20 );
}

function tp_partner_cert_url( $quarter, $venue ) {
	return add_query_arg( array( 'tp_partner_cert' => $quarter, 'v' => (int) $venue, 't' => tp_partner_cert_token( $quarter, $venue ) ), home_url( '/' ) );
}

/** "1st", "2nd"… in English; just the number elsewhere. */
function tp_ordinal( $n ) {
	if ( 0 !== strpos( determine_locale(), 'en' ) ) {
		return '#' . $n;
	}
	$suffix = in_array( $n % 100, array( 11, 12, 13 ), true ) ? 'th' : ( array( 1 => 'st', 2 => 'nd', 3 => 'rd' )[ $n % 10 ] ?? 'th' );
	return $n . $suffix;
}

/* -------------------------------------------------------------------------
 * Office page.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'reports' ), __( 'Partner leaderboard', 'taxi-peninsula' ), __( 'Partner leaderboard', 'taxi-peninsula' ), 'tp_view_leads', 'tp-partner-board', 'tp_render_partner_board' );
	},
	25
);

function tp_render_partner_board() {
	if ( ! current_user_can( 'tp_view_leads' ) ) {
		return;
	}
	$q      = tp_quarter( isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$prev   = tp_partner_counts( tp_quarter_before( $q ) );
	$rows   = tp_partner_counts( $q );
	$thanks = (array) get_option( 'tp_partner_thanks', array() );
	$types  = tp_venue_types();
	$opts   = array();
	for ( $i = 0, $k = tp_quarter()['key']; $i < 8; $i++ ) {
		$qq          = tp_quarter( $k );
		$opts[ $k ] = $qq['label'];
		$k          = tp_quarter_before( $qq )['key'];
	}
	?>
	<div class="wrap tp-partner-board">
		<h1><?php esc_html_e( 'Partner leaderboard', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Hospitals, aged-care homes and providers ranked by completed trips booked through their partner page, QR poster or partner portal. Say thank you each quarter: a short email and a printed certificate for the reception desk go a long way.', 'taxi-peninsula' ); ?></p>
		<form method="get">
			<input type="hidden" name="page" value="tp-partner-board">
			<label for="tp-pb-q"><?php esc_html_e( 'Quarter', 'taxi-peninsula' ); ?></label>
			<select id="tp-pb-q" name="q"><?php foreach ( $opts as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $q['key'] ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<?php submit_button( __( 'Show', 'taxi-peninsula' ), 'secondary', '', false ); ?>
		</form>
		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'No completed partner trips in this quarter yet. Partner trips are counted when people book from a partner page, its QR poster or the partner portal (Bookings → Partner pages).', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th class="num"><?php esc_html_e( 'Rank', 'taxi-peninsula' ); ?></th>
					<th><?php esc_html_e( 'Partner', 'taxi-peninsula' ); ?></th>
					<th class="num"><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></th>
					<th class="num"><?php esc_html_e( 'Change', 'taxi-peninsula' ); ?></th>
					<th class="num"><?php esc_html_e( 'Passengers', 'taxi-peninsula' ); ?></th>
					<th><?php esc_html_e( 'Say thank you', 'taxi-peninsula' ); ?></th>
				</tr></thead>
				<tbody>
					<?php $rank = 0; ?>
					<?php foreach ( $rows as $vid => $r ) : ?>
						<?php
						++$rank;
						$before = $prev[ $vid ]['trips'] ?? 0;
						$diff   = $r['trips'] - $before;
						$email  = (string) get_post_meta( $vid, '_tp_v_contact_email', true );
						$sent   = $thanks[ $q['key'] . '|' . $vid ] ?? 0;
						?>
						<tr>
							<td class="num"><strong><?php echo esc_html( $rank ); ?></strong><?php echo $rank <= 3 ? ' <span aria-hidden="true">' . esc_html( array( 1 => '🥇', 2 => '🥈', 3 => '🥉' )[ $rank ] ) . '</span>' : ''; ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $vid ) ); ?>"><?php echo esc_html( get_the_title( $vid ) ); ?></a><br><small><?php echo esc_html( $types[ get_post_meta( $vid, '_tp_v_type', true ) ] ?? '' ); ?></small></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['trips'] ) ); ?></td>
							<td class="num"><?php echo esc_html( $before ? ( $diff >= 0 ? '+' : '' ) . number_format_i18n( $diff ) : __( 'new', 'taxi-peninsula' ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['people'] ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( tp_partner_cert_url( $q['key'], $vid ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Certificate', 'taxi-peninsula' ); ?></a>
								<?php if ( is_email( $email ) ) : ?>
									· <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_partner_thank&q=' . $q['key'] . '&v=' . $vid ), 'tp_partner_thank' ) ); ?>"><?php echo esc_html( $sent ? __( 'Email again', 'taxi-peninsula' ) : __( 'Email thank-you', 'taxi-peninsula' ) ); ?></a>
									<?php if ( $sent ) : ?>
										<br><small>
										<?php
										/* translators: %s: date */
										echo esc_html( sprintf( __( 'Thanked %s', 'taxi-peninsula' ), wp_date( 'j M Y', $sent ) ) );
										?>
										</small>
									<?php endif; ?>
								<?php else : ?>
									<br><small><?php esc_html_e( 'Add a contact email on the partner page to email them.', 'taxi-peninsula' ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Tip: print the certificates for your top partners and drop them in with a box of chocolates. Ask if you can put up a fresh QR poster while you are there.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Thank-you email.
 * ---------------------------------------------------------------------- */

function tp_send_partner_thanks( $quarter, $venue ) {
	$q     = tp_quarter( $quarter );
	$rows  = tp_partner_counts( $q );
	$email = (string) get_post_meta( $venue, '_tp_v_contact_email', true );
	if ( ! isset( $rows[ $venue ] ) || ! is_email( $email ) ) {
		return false;
	}
	$rank = array_search( $venue, array_keys( $rows ), true ) + 1;
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$name = (string) get_post_meta( $venue, '_tp_v_contact', true );
	$ok   = false;
	tp_in_site_lang(
		static function () use ( $q, $rows, $venue, $email, $rank, $site, $name, &$ok ) {
			/* translators: %s: name */
			$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $name ?: get_the_title( $venue ) ) . "\n\n";
			/* translators: 1: partner 2: trips 3: quarter */
			$body .= sprintf( _n( 'A quick thank you from all of us: in %3$s, %1$s booked %2$d trip with us.', 'A quick thank you from all of us: in %3$s, %1$s booked %2$d trips with us.', $rows[ $venue ]['trips'], 'taxi-peninsula' ), get_the_title( $venue ), $rows[ $venue ]['trips'], $q['label'] ) . ' ';
			if ( $rank <= 3 ) {
				/* translators: %s: 1st, 2nd or 3rd */
				$body .= sprintf( __( 'That makes you our %s busiest partner this quarter.', 'taxi-peninsula' ), tp_ordinal( $rank ) ) . ' ';
			}
			$body .= __( 'Every one of those is someone who got to an appointment, a visit or home safely, and we are grateful you trust us with them.', 'taxi-peninsula' ) . "\n\n";
			$body .= __( 'Your certificate of appreciation (print it for your reception or staff room):', 'taxi-peninsula' ) . "\n" . tp_partner_cert_url( $q['key'], $venue ) . "\n\n";
			$link = get_permalink( $venue );
			if ( $link ) {
				$body .= __( 'Your booking page and QR poster, for anyone new on the team:', 'taxi-peninsula' ) . "\n" . $link . "\n\n";
			}
			/* translators: %s: phone */
			$body .= sprintf( __( 'If there is anything we can do better, just reply or call %s.', 'taxi-peninsula' ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) ) . "\n\n" . __( 'Thank you,', 'taxi-peninsula' ) . "\n" . $site . "\n";
			$ok = wp_mail(
				$email,
				/* translators: 1: site 2: quarter */
				sprintf( __( 'Thank you from %1$s — %2$s', 'taxi-peninsula' ), $site, $q['label'] ),
				$body,
				tp_mail_headers( tp_opt( 'email' ) )
			);
		}
	);
	if ( $ok ) {
		$all                              = (array) get_option( 'tp_partner_thanks', array() );
		$all[ $q['key'] . '|' . $venue ] = time();
		update_option( 'tp_partner_thanks', $all, false );
	}
	return $ok;
}

add_action(
	'admin_post_tp_partner_thank',
	static function () {
		check_admin_referer( 'tp_partner_thank' );
		if ( ! current_user_can( 'tp_view_leads' ) ) {
			wp_die( '', 403 );
		}
		$q  = tp_quarter( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) );
		$v  = absint( $_GET['v'] ?? 0 );
		$ok = tp_send_partner_thanks( $q['key'], $v );
		/* translators: %s: partner */
		set_transient( 'tp_admin_notice_' . get_current_user_id(), $ok ? array( 'success', sprintf( __( 'Thank-you emailed to %s.', 'taxi-peninsula' ), get_the_title( $v ) ) ) : array( 'error', __( 'The email could not be sent. Check the partner’s contact email and Taxi settings → Email check.', 'taxi-peninsula' ) ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-partner-board&q=' . $q['key'] ) );
		exit;
	}
);

// Start of each quarter: email the office last quarter's top five.
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'tp_partner_quarterly' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 08:40' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'tp_partner_quarterly' );
		}
	}
);
add_action(
	'tp_partner_quarterly',
	static function () {
		if ( '01' !== wp_date( 'd' ) || ! in_array( (int) wp_date( 'n' ), array( 1, 4, 7, 10 ), true ) ) {
			return;
		}
		$q    = tp_quarter();
		$rows = array_slice( tp_partner_counts( $q ), 0, 5, true );
		if ( ! $rows || get_option( 'tp_partner_quarterly_sent' ) === $q['key'] ) {
			return;
		}
		update_option( 'tp_partner_quarterly_sent', $q['key'], false );
		tp_in_site_lang(
			static function () use ( $q, $rows ) {
				$lines = array();
				$i     = 0;
				foreach ( $rows as $vid => $r ) {
					/* translators: 1: rank 2: partner 3: trips */
					$lines[] = sprintf( __( '%1$d. %2$s — %3$d trips', 'taxi-peninsula' ), ++$i, get_the_title( $vid ), $r['trips'] );
				}
				wp_mail(
					tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
					/* translators: %s: quarter */
					sprintf( __( 'Your top partners for %s — time to say thank you', 'taxi-peninsula' ), $q['label'] ),
					/* translators: %s: quarter */
					sprintf( __( 'Your busiest partners for %s:', 'taxi-peninsula' ), $q['label'] ) . "\n\n" . implode( "\n", $lines ) . "\n\n" . __( 'Send each a thank-you email and print their certificates here:', 'taxi-peninsula' ) . "\n" . admin_url( 'admin.php?page=tp-partner-board&q=' . $q['key'] ) . "\n",
					tp_mail_headers()
				);
			}
		);
	}
);

/* -------------------------------------------------------------------------
 * Certificate (?tp_partner_cert=2026-Q3&v=ID&t=token): A4 landscape, print-ready.
 * ---------------------------------------------------------------------- */

add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the check.
		if ( empty( $_GET['tp_partner_cert'] ) ) {
			return;
		}
		$q   = tp_quarter( sanitize_text_field( wp_unslash( $_GET['tp_partner_cert'] ) ) );
		$v   = absint( $_GET['v'] ?? 0 );
		$tok = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );
		// phpcs:enable
		if ( ! hash_equals( tp_partner_cert_token( $q['key'], $v ), $tok ) || 'tp_venue' !== get_post_type( $v ) ) {
			wp_die( esc_html__( 'This certificate link is not valid.', 'taxi-peninsula' ), '', array( 'response' => 403 ) );
		}
		$rows = tp_partner_counts( $q );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		tp_render_partner_cert( $q, $v, $rows[ $v ]['trips'] ?? 0, isset( $rows[ $v ] ) ? array_search( $v, array_keys( $rows ), true ) + 1 : 0 );
		exit;
	},
	1
);

function tp_render_partner_cert( array $q, $venue, $trips, $rank ) {
	$c    = tp_design_colors();
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '';
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( __( 'Certificate of appreciation', 'taxi-peninsula' ) . ' — ' . get_the_title( $venue ) ); ?></title>
	<style>
		@page { size: A4 landscape; margin: 0; }
		* { box-sizing: border-box; }
		body { margin: 0; background: #e9edf3; font-family: Georgia, "Times New Roman", serif; color: #16213a; }
		.cert { width: 297mm; height: 210mm; margin: 16px auto; background: #fff; padding: 14mm; box-shadow: 0 6px 30px rgb(0 0 0 / .15); }
		.frame { height: 100%; border: 3px solid <?php echo esc_attr( $c['primary'] ); ?>; outline: 10px solid <?php echo esc_attr( $c['accent'] ); ?>; outline-offset: -16px; padding: 16mm 22mm; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 4mm; }
		.logo { max-height: 22mm; max-width: 70mm; }
		.kicker { font: 700 13pt/1 system-ui, Arial, sans-serif; letter-spacing: .3em; text-transform: uppercase; color: <?php echo esc_attr( $c['primary'] ); ?>; margin: 0; }
		h1 { font-size: 34pt; margin: 0; color: <?php echo esc_attr( $c['primary'] ); ?>; font-weight: 700; }
		.to { font-size: 13pt; margin: 2mm 0 0; font-style: italic; }
		.name { font-size: 30pt; margin: 0; padding: 0 10mm 2mm; border-bottom: 2px solid <?php echo esc_attr( $c['accent'] ); ?>; }
		.why { font-size: 14pt; max-width: 200mm; line-height: 1.5; margin: 3mm 0 0; }
		.rank { font: 700 12pt/1 system-ui, Arial, sans-serif; background: <?php echo esc_attr( $c['accent'] ); ?>; color: #16213a; padding: 2.5mm 6mm; border-radius: 999px; margin: 1mm 0 0; }
		.sign { display: flex; gap: 40mm; margin-top: 8mm; font: 11pt/1.4 system-ui, Arial, sans-serif; }
		.sign div { border-top: 1.5px solid #16213a; padding-top: 2mm; min-width: 60mm; }
		.actions { text-align: center; margin: 0 0 16px; font-family: system-ui, Arial, sans-serif; }
		.actions button { font: inherit; font-weight: 700; padding: 10px 18px; border-radius: 8px; border: 0; background: <?php echo esc_attr( $c['primary'] ); ?>; color: #fff; cursor: pointer; }
		@media print { body { background: #fff; } .cert { margin: 0; box-shadow: none; } .actions { display: none; } }
		@media (max-width: 1150px) { .cert { transform: scale(.5); transform-origin: top center; margin-bottom: -100mm; } }
	</style>
</head>
<body>
	<main class="cert">
		<div class="frame">
			<?php if ( $logo ) : ?><img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $site ); ?>"><?php endif; ?>
			<p class="kicker"><?php echo esc_html( $site ); ?></p>
			<h1><?php esc_html_e( 'Certificate of Appreciation', 'taxi-peninsula' ); ?></h1>
			<p class="to"><?php esc_html_e( 'presented to', 'taxi-peninsula' ); ?></p>
			<p class="name"><?php echo esc_html( get_the_title( $venue ) ); ?></p>
			<p class="why">
				<?php
				echo esc_html(
					$trips
						/* translators: 1: site 2: trips 3: quarter */
						? sprintf( _n( 'With thanks for trusting %1$s with %2$d trip during %3$s, and for helping the people in your care travel safely and on time.', 'With thanks for trusting %1$s with %2$d trips during %3$s, and for helping the people in your care travel safely and on time.', $trips, 'taxi-peninsula' ), $site, $trips, $q['label'] )
						/* translators: 1: site 2: quarter */
						: sprintf( __( 'With thanks for your partnership with %1$s during %2$s, and for helping the people in your care travel safely and on time.', 'taxi-peninsula' ), $site, $q['label'] )
				);
				?>
			</p>
			<?php if ( $rank && $rank <= 3 ) : ?>
				<p class="rank">
					<?php
					/* translators: 1: 1st/2nd/3rd 2: quarter */
					echo esc_html( 1 === $rank ? sprintf( __( 'Our top partner for %s', 'taxi-peninsula' ), $q['label'] ) : sprintf( __( 'Our %1$s busiest partner for %2$s', 'taxi-peninsula' ), tp_ordinal( $rank ), $q['label'] ) );
					?>
				</p>
			<?php endif; ?>
			<div class="sign">
				<div><?php echo esc_html( $site ); ?></div>
				<div><?php echo esc_html( wp_date( 'j F Y' ) ); ?></div>
			</div>
		</div>
	</main>
	<p class="actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print certificate', 'taxi-peninsula' ); ?></button></p>
</body>
</html>
	<?php
}
