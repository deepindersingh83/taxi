<?php
/**
 * Refer a friend: "Give $10, get $10" (Settings → Marketing & SEO).
 *
 * Each customer account gets its own code (a promo code, so the booking form
 * checks it like any other). A friend's code takes the "give" amount off their
 * first trip; new customers only. When the friend's first trip is completed,
 * the customer who referred them gets a single-use thank-you code for the
 * "get" amount by email (and text when SMS is set up).
 *
 * Referral and thank-you codes are kept out of the Promo codes list; see
 * Reports → Referrals.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['referrals'] = array( __( 'Refer a friend', 'taxi-peninsula' ), __( 'Customers share their own code from My Account. Friends save on their first trip; the customer gets a thank-you code once that trip is completed.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['referral_on']     = array( 'referrals', __( 'Turn on Refer a friend', 'taxi-peninsula' ), 'checkbox', false, '' );
		$s['referral_give']   = array( 'referrals', __( 'Friend’s discount on their first trip (AUD)', 'taxi-peninsula' ), 'number', 10, '' );
		$s['referral_get']    = array( 'referrals', __( 'Thank-you code for the customer who referred them (AUD)', 'taxi-peninsula' ), 'number', 10, '' );
		$s['referral_months'] = array( 'referrals', __( 'Thank-you codes can be used for (months)', 'taxi-peninsula' ), 'number', 12, '' );
		return $s;
	}
);

add_filter(
	'tp_settings_tabs',
	static function ( $tabs ) {
		$tabs['marketing'][1][] = 'referrals';
		return $tabs;
	}
);

function tp_referrals_on() {
	return (bool) tp_setting( 'referral_on' );
}

function tp_referral_amount( $which ) {
	return max( 0, (float) tp_setting( 'give' === $which ? 'referral_give' : 'referral_get' ) );
}

/** "$10" */
function tp_referral_money( $amount ) {
	return '$' . ( floor( $amount ) == $amount ? number_format_i18n( $amount ) : number_format_i18n( $amount, 2 ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- float vs whole number.
}

/** A code nobody has: letters from the name, then 4 random characters. */
function tp_referral_new_code( $prefix ) {
	$prefix = substr( preg_replace( '/[^A-Z]/', '', strtoupper( remove_accents( (string) $prefix ) ) ), 0, 6 ) ?: 'FRIEND';
	do {
		$code = $prefix;
		for ( $i = 0; $i < 4; $i++ ) {
			$code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[ random_int( 0, 31 ) ];
		}
	} while ( tp_promo_find( $code ) );
	return $code;
}

/**
 * The account's referral code (made the first time it is needed).
 *
 * @param int $user_id Customer.
 * @return string
 */
function tp_referral_code( $user_id ) {
	$id = (int) get_user_meta( $user_id, 'tp_ref_promo', true );
	if ( $id && 'tp_promo' === get_post_type( $id ) ) {
		return get_the_title( $id );
	}
	$user = get_userdata( $user_id );
	$code = tp_referral_new_code( $user ? ( $user->first_name ?: $user->display_name ) : '' );
	$id   = wp_insert_post( array( 'post_type' => 'tp_promo', 'post_status' => 'publish', 'post_title' => $code ), true );
	if ( is_wp_error( $id ) ) {
		return '';
	}
	update_post_meta( $id, '_tp_promo_kind', 'fixed' );
	update_post_meta( $id, '_tp_promo_first', 1 );
	update_post_meta( $id, '_tp_ref_owner', (int) $user_id );
	update_user_meta( $user_id, 'tp_ref_promo', $id );
	return $code;
}

function tp_referral_link( $code ) {
	return add_query_arg( 'ref', $code, home_url( '/' ) );
}

/** Is this booking (or visitor) the account holder? */
function tp_referral_is_owner( $user_id, array $d ) {
	if ( is_user_logged_in() && get_current_user_id() === (int) $user_id ) {
		return true;
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	if ( ! empty( $d['email'] ) && strtolower( $d['email'] ) === strtolower( $user->user_email ) ) {
		return true;
	}
	$mobile = tp_phone_digits( (string) get_user_meta( $user_id, 'tp_mobile', true ) );
	return '' !== $mobile && ! empty( $d['phone'] ) && tp_phone_digits( $d['phone'] ) === $mobile;
}

// Referral codes always use the current amount and are for first trips only.
add_filter(
	'tp_promo_meta',
	static function ( $m, $id ) {
		if ( get_post_meta( $id, '_tp_ref_owner', true ) ) {
			$m['kind']   = 'fixed';
			$m['amount'] = tp_referral_amount( 'give' );
			$m['first']  = true;
		}
		return $m;
	},
	10,
	2
);

add_filter(
	'tp_promo_check',
	static function ( $error, $id, $d ) {
		if ( $error ) {
			return $error;
		}
		$owner = (int) get_post_meta( $id, '_tp_ref_owner', true );
		if ( $owner ) {
			if ( ! tp_referrals_on() ) {
				return __( 'Referral codes are not available at the moment.', 'taxi-peninsula' );
			}
			if ( tp_referral_is_owner( $owner, $d ) ) {
				return __( 'That’s your own referral code — share it with friends instead. Thank you!', 'taxi-peninsula' );
			}
		}
		$for = (int) get_post_meta( $id, '_tp_ref_reward_for', true );
		if ( $for && ! tp_referral_is_owner( $for, $d ) ) {
			return __( 'That thank-you code belongs to another account. Please sign in to use it.', 'taxi-peninsula' );
		}
		return '';
	},
	10,
	3
);

// Keep referral and thank-you codes out of the Promo codes list.
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() && $q->is_main_query() && 'tp_promo' === $q->get( 'post_type' ) ) {
			$q->set( 'meta_query', array( array( 'key' => '_tp_ref_owner', 'compare' => 'NOT EXISTS' ), array( 'key' => '_tp_ref_reward_for', 'compare' => 'NOT EXISTS' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
	}
);

/* =========================================================================
 * Landing link: /?ref=CODE fills in the promo code on the booking form.
 * ====================================================================== */

add_action(
	'init',
	static function () {
		if ( empty( $_GET['ref'] ) || is_admin() || headers_sent() || ! tp_referrals_on() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id   = tp_promo_find( $code );
		if ( $id && get_post_meta( $id, '_tp_ref_owner', true ) ) {
			setcookie( 'tp_ref', $code, array( 'expires' => time() + 30 * DAY_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN ?: '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			$_COOKIE['tp_ref'] = $code;
		}
	},
	2
);

add_filter(
	'tp_booking_form_link_prefill',
	static function ( $old ) {
		if ( empty( $old['promo'] ) && ! empty( $_COOKIE['tp_ref'] ) && tp_referrals_on() ) {
			$old['promo'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_COOKIE['tp_ref'] ) ) ) );
		}
		return $old;
	}
);

/* =========================================================================
 * Thank-you code when the friend's first trip is completed.
 * ====================================================================== */

add_action(
	'tp_booking_status_changed',
	static function ( $id, $old, $status ) {
		if ( 'completed' !== $status || ! tp_referrals_on() || get_post_meta( $id, '_tp_ref_rewarded', true ) ) {
			return;
		}
		$code  = (string) get_post_meta( $id, '_tp_promo', true );
		$promo = $code ? tp_promo_find( $code ) : 0;
		$owner = $promo ? (int) get_post_meta( $promo, '_tp_ref_owner', true ) : 0;
		if ( ! $owner || ( function_exists( 'tp_is_spam_booking' ) && tp_is_spam_booking( $id ) ) ) {
			return;
		}
		$b = tp_get_booking( $id );
		if ( tp_referral_is_owner( $owner, array( 'email' => $b['email'], 'phone' => $b['phone'] ) ) ) {
			return;
		}
		// One thank-you per friend, even for repeat bookings with the same code.
		$friend  = substr( hash_hmac( 'sha256', tp_phone_digits( (string) $b['phone'] ) ?: strtolower( (string) $b['email'] ), wp_salt( 'auth' ) ), 0, 16 );
		$friends = (array) get_post_meta( $promo, '_tp_ref_friends', true );
		update_post_meta( $id, '_tp_ref_rewarded', time() );
		if ( isset( $friends[ $friend ] ) ) {
			return;
		}
		$reward = tp_referral_reward( $owner, $id );
		if ( $reward ) {
			$friends[ $friend ] = $reward;
			update_post_meta( $promo, '_tp_ref_friends', $friends );
		}
	},
	30,
	3
);

/**
 * Make a single-use thank-you code and tell the customer.
 *
 * @param int $owner      Customer who referred.
 * @param int $booking_id Friend's completed trip.
 * @return string Code, or ''.
 */
function tp_referral_reward( $owner, $booking_id ) {
	$amount = tp_referral_amount( 'get' );
	if ( $amount <= 0 ) {
		return '';
	}
	$code = tp_referral_new_code( 'THANKS' );
	$id   = wp_insert_post( array( 'post_type' => 'tp_promo', 'post_status' => 'publish', 'post_title' => $code ), true );
	if ( is_wp_error( $id ) ) {
		return '';
	}
	$end = wp_date( 'Y-m-d', strtotime( '+' . max( 1, (int) tp_setting( 'referral_months' ) ) . ' months' ) );
	update_post_meta( $id, '_tp_promo_kind', 'fixed' );
	update_post_meta( $id, '_tp_promo_amount', $amount );
	update_post_meta( $id, '_tp_promo_max', 1 );
	update_post_meta( $id, '_tp_promo_end', $end );
	update_post_meta( $id, '_tp_ref_reward_for', (int) $owner );
	update_post_meta( $id, '_tp_ref_from_booking', (int) $booking_id );

	$user = get_userdata( $owner );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$date = wp_date( get_option( 'date_format' ), strtotime( $end ) );
	tp_in_site_lang(
		static function () use ( $user, $site, $code, $amount, $date, $booking_id ) {
			tp_add_log( $booking_id, array( 'note' => __( 'Referral: thank-you code sent to the customer who referred this passenger.', 'taxi-peninsula' ) ) );
			if ( ! $user ) {
				return;
			}
			/* translators: %s: first name */
			$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $user->first_name ?: $user->display_name ) . "\n\n";
			/* translators: 1: amount 2: code */
			$body .= sprintf( __( 'A friend you referred has just taken their first trip with us. Thank you! Here is %1$s off your next trip: %2$s', 'taxi-peninsula' ), tp_referral_money( $amount ), $code ) . "\n\n";
			/* translators: %s: date */
			$body .= sprintf( __( 'Type the code in the “Promo code” box when you book. It can be used once, until %s.', 'taxi-peninsula' ), $date ) . "\n";
			$body .= tp_booking_page_url() . "\n\n" . $site . "\n";
			/* translators: 1: amount 2: site */
			wp_mail( $user->user_email, sprintf( __( '%1$s off your next trip with %2$s', 'taxi-peninsula' ), tp_referral_money( $amount ), $site ), $body, tp_mail_headers( tp_opt( 'email' ) ) );
			$p = tp_au_phone( (string) get_user_meta( $user->ID, 'tp_mobile', true ) );
			if ( $p && 'mobile' === $p['type'] && tp_sms_enabled() ) {
				/* translators: 1: site 2: amount 3: code */
				tp_send_sms( $p['display'], sprintf( __( '%1$s: thanks for referring a friend! Here is %2$s off your next trip: %3$s', 'taxi-peninsula' ), tp_sms_site_name(), tp_referral_money( $amount ), $code ) );
			}
		}
	);
	return $code;
}

/** Thank-you codes of an account: [code, amount, used, end]. */
function tp_referral_rewards( $user_id ) {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'tp_promo', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => '_tp_ref_reward_for', 'meta_value' => (int) $user_id ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$code  = get_the_title( $id );
		$out[] = array( $code, (float) get_post_meta( $id, '_tp_promo_amount', true ), tp_promo_uses( $code ) > 0, (string) get_post_meta( $id, '_tp_promo_end', true ) );
	}
	return $out;
}

/* =========================================================================
 * My Account → Refer a friend.
 * ====================================================================== */

add_filter(
	'tp_account_tabs',
	static function ( $tabs, $coord ) {
		if ( tp_referrals_on() && ! $coord ) {
			$tabs['refer'] = __( 'Refer a friend', 'taxi-peninsula' );
		}
		return $tabs;
	},
	20,
	2
);

add_action(
	'tp_account_tab_refer',
	static function ( WP_User $user ) {
		$code = tp_referral_code( $user->ID );
		if ( ! $code ) {
			return;
		}
		$give  = tp_referral_money( tp_referral_amount( 'give' ) );
		$get   = tp_referral_money( tp_referral_amount( 'get' ) );
		$link  = tp_referral_link( $code );
		$promo = (int) get_user_meta( $user->ID, 'tp_ref_promo', true );
		$count = count( (array) get_post_meta( $promo, '_tp_ref_friends', true ) ?: array() );
		/* translators: 1: amount 2: site 3: code 4: link */
		$msg     = sprintf( __( 'I use %2$s for my trips. Get %1$s off your first one with my code %3$s: %4$s', 'taxi-peninsula' ), $give, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $code, $link );
		$rewards = tp_referral_rewards( $user->ID );
		?>
		<section class="refer" aria-labelledby="refer-title">
			<div class="refer__hero card">
				<h3 id="refer-title" class="refer__title">
					<?php
					/* translators: 1: friend's amount 2: your amount */
					echo esc_html( sprintf( __( 'Give %1$s, get %2$s', 'taxi-peninsula' ), $give, $get ) );
					?>
				</h3>
				<p>
					<?php
					/* translators: 1: friend's amount 2: your amount */
					echo esc_html( sprintf( __( 'Friends get %1$s off their first trip with your code. When they’ve travelled, we send you a %2$s thank-you code for your next trip.', 'taxi-peninsula' ), $give, $get ) );
					?>
				</p>
				<div class="refer__code">
					<span class="refer__label" id="refer-code-label"><?php esc_html_e( 'Your code', 'taxi-peninsula' ); ?></span>
					<strong class="refer__value" aria-labelledby="refer-code-label"><?php echo esc_html( $code ); ?></strong>
					<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( $code ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'taxi-peninsula' ); ?>"><?php esc_html_e( 'Copy code', 'taxi-peninsula' ); ?></button>
				</div>
				<p class="refer__share">
					<a class="btn btn--accent" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $msg ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Share on WhatsApp', 'taxi-peninsula' ); ?></a>
					<a class="btn btn--ghost" href="<?php echo esc_url( 'sms:?&body=' . rawurlencode( $msg ), array( 'sms' ) ); ?>"><?php esc_html_e( 'Text a friend', 'taxi-peninsula' ); ?></a>
					<a class="btn btn--ghost" href="<?php echo esc_url( 'mailto:?subject=' . rawurlencode( sprintf( /* translators: %s: amount */ __( '%s off your first taxi trip', 'taxi-peninsula' ), $give ) ) . '&body=' . rawurlencode( $msg ) ); ?>"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?></a>
					<button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( $link ); ?>" data-copied="<?php esc_attr_e( 'Link copied', 'taxi-peninsula' ); ?>"><?php esc_html_e( 'Copy link', 'taxi-peninsula' ); ?></button>
				</p>
				<p class="refer__status" role="status" aria-live="polite"></p>
			</div>
			<h3><?php esc_html_e( 'Your thank-you codes', 'taxi-peninsula' ); ?></h3>
			<p>
				<?php
				/* translators: %d: number of friends */
				echo esc_html( sprintf( _n( '%d friend has travelled with your code.', '%d friends have travelled with your code.', $count, 'taxi-peninsula' ), $count ) );
				?>
			</p>
			<?php if ( $rewards ) : ?>
				<ul class="refer__rewards">
					<?php foreach ( $rewards as $r ) : ?>
						<?php $expired = $r[3] && $r[3] < wp_date( 'Y-m-d' ); ?>
						<li>
							<strong><?php echo esc_html( $r[0] ); ?></strong> — <?php echo esc_html( tp_referral_money( $r[1] ) ); ?>
							<span class="refer__tag">
								<?php
								if ( $r[2] ) {
									esc_html_e( 'Used', 'taxi-peninsula' );
								} elseif ( $expired ) {
									esc_html_e( 'Expired', 'taxi-peninsula' );
								} else {
									/* translators: %s: date */
									echo esc_html( sprintf( __( 'Use by %s', 'taxi-peninsula' ), wp_date( get_option( 'date_format' ), strtotime( $r[3] ) ) ) );
								}
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="refer__empty"><?php esc_html_e( 'Your thank-you codes will appear here.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
			<p class="refer__small"><?php esc_html_e( 'Friends must be new customers. One thank-you code per friend, sent after their first trip is completed. Codes can’t be swapped for cash.', 'taxi-peninsula' ); ?></p>
		</section>
		<?php
	}
);

/* =========================================================================
 * Reports → Referrals.
 * ====================================================================== */

add_action(
	'admin_menu',
	static function () {
		if ( tp_referrals_on() ) {
			add_submenu_page( tp_admin_parent( 'reports' ), __( 'Referrals', 'taxi-peninsula' ), __( 'Referrals', 'taxi-peninsula' ), 'tp_view_leads', 'tp-referrals', 'tp_render_referrals_page' );
		}
	},
	44
);

function tp_render_referrals_page() {
	if ( ! current_user_can( 'tp_view_leads' ) ) {
		return;
	}
	$rows = array();
	foreach ( get_posts( array( 'post_type' => 'tp_promo', 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'meta_key' => '_tp_ref_owner', 'meta_compare' => 'EXISTS' ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$code  = get_the_title( $id );
		$owner = (int) get_post_meta( $id, '_tp_ref_owner', true );
		$given = tp_referral_rewards( $owner );
		$rows[] = array(
			'user'    => get_userdata( $owner ),
			'code'    => $code,
			'booked'  => tp_promo_uses( $code ),
			'friends' => count( (array) get_post_meta( $id, '_tp_ref_friends', true ) ?: array() ),
			'rewards' => count( $given ),
			'used'    => count( array_filter( wp_list_pluck( $given, 2 ) ) ),
		);
	}
	usort( $rows, static fn( $a, $b ) => $b['friends'] <=> $a['friends'] ?: $b['booked'] <=> $a['booked'] );
	$sum = static fn( $k ) => array_sum( wp_list_pluck( $rows, $k ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Referrals', 'taxi-peninsula' ); ?></h1>
		<p>
			<?php
			/* translators: 1: friend's amount 2: thank-you amount */
			echo esc_html( sprintf( __( 'Friends get %1$s off their first trip; the customer who referred them gets a %2$s thank-you code when that trip is completed. Change the amounts in Settings → Marketing & SEO.', 'taxi-peninsula' ), tp_referral_money( tp_referral_amount( 'give' ) ), tp_referral_money( tp_referral_amount( 'get' ) ) ) );
			?>
		</p>
		<p>
			<?php
			/* translators: 1: bookings 2: friends 3: codes sent 4: codes used */
			echo esc_html( sprintf( __( 'Bookings with a referral code: %1$d · Friends who travelled: %2$d · Thank-you codes sent: %3$d · used: %4$d', 'taxi-peninsula' ), $sum( 'booked' ), $sum( 'friends' ), $sum( 'rewards' ), $sum( 'used' ) ) );
			?>
		</p>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Customer', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Code', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Bookings with the code', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Friends who travelled', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Thank-you codes (used)', 'taxi-peninsula' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No referral codes yet. Customers get theirs when they open “Refer a friend” in My Account.', 'taxi-peninsula' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo $r['user'] ? '<a href="' . esc_url( get_edit_user_link( $r['user']->ID ) ) . '">' . esc_html( $r['user']->display_name ) . '</a>' : '—'; ?></td>
						<td><code><?php echo esc_html( $r['code'] ); ?></code></td>
						<td><?php echo esc_html( $r['booked'] ); ?></td>
						<td><?php echo esc_html( $r['friends'] ); ?></td>
						<td><?php echo esc_html( $r['rewards'] . ' (' . $r['used'] . ')' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
