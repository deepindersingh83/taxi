<?php
/**
 * Marketing → Link partners: ask the hotels, wineries and venues that have a
 * page on your site to link to it as "Getting here by taxi".
 *
 * For each place: a ready-written email (copy, or open in your email app),
 * the link code to paste on their website, where you are up to (not asked,
 * asked, linked, declined) with notes, and a "Check" button that looks for
 * your link on their page. Links from local businesses are one of the
 * strongest signals for local search.
 *
 * Nothing is sent from the website: you send the emails yourself.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/** Place types worth asking. */
function tp_outreach_types() {
	return array( 'hotel', 'winery', 'venue', 'transport' );
}

function tp_outreach_statuses() {
	return array(
		''         => __( 'Not asked yet', 'taxi-peninsula' ),
		'asked'    => __( 'Asked', 'taxi-peninsula' ),
		'followup' => __( 'Follow up', 'taxi-peninsula' ),
		'linked'   => __( 'Linked', 'taxi-peninsula' ),
		'declined' => __( 'Declined', 'taxi-peninsula' ),
	);
}

/** Places to ask, by type then name. */
function tp_outreach_places() {
	$ids = get_posts(
		array(
			'post_type'   => 'tp_place',
			'post_status' => 'publish',
			'numberposts' => 300,
			'fields'      => 'ids',
			'orderby'     => 'title',
			'order'       => 'ASC',
			'meta_query'  => array( array( 'key' => '_tp_p_type', 'value' => tp_outreach_types(), 'compare' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $ids;
}

/** The HTML they paste on their site. */
function tp_outreach_link_code( $id ) {
	/* translators: %s: business name */
	$text = sprintf( __( 'Getting here by taxi — %s', 'taxi-peninsula' ), get_bloginfo( 'name' ) );
	return '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $text ) . '</a>';
}

/** [subject, body] of the request email. */
function tp_outreach_email( $id ) {
	$place = get_the_title( $id );
	$site  = get_bloginfo( 'name' );
	$phone = wp_strip_all_tags( tp_opt( 'phone_display' ) );
	$type  = tp_place_meta( $id )['type'];
	$who   = 'hotel' === $type ? __( 'your guests', 'taxi-peninsula' ) : __( 'your visitors', 'taxi-peninsula' );
	/* translators: %s: place */
	$subject = sprintf( __( 'A free “Getting here by taxi” page for %s', 'taxi-peninsula' ), $place );
	$lines   = array(
		__( 'Hi there,', 'taxi-peninsula' ),
		'',
		/* translators: 1: business 2: place 3: guests/visitors */
		sprintf( __( 'I’m with %1$s, a local taxi and wheelchair accessible taxi service on the Mornington Peninsula, open 24/7. Many of our passengers travel to and from %2$s, so we have made a page just for %3$s:', 'taxi-peninsula' ), $site, $place, $who ),
		'',
		get_permalink( $id ),
		'',
		__( 'It shows where we drop off and pick up, rough travel times from nearby towns, and lets people book a taxi, a wheelchair accessible taxi or a maxi for groups in about two minutes — including a set time to be collected at the end of the day.', 'taxi-peninsula' ),
		'',
		/* translators: 1: place */
		sprintf( __( 'Would you be happy to link to it from the “Getting here”, “Contact” or “Plan your visit” page of %1$s? Something like “Getting here by taxi” is perfect. Here is the code if it helps:', 'taxi-peninsula' ), $place ),
		'',
		tp_outreach_link_code( $id ),
		'',
		__( 'If it suits you, we can also set up a booking page for your front desk, with a printable QR code so guests can book a taxi from their phone. There is no cost for any of this.', 'taxi-peninsula' ),
		'',
		/* translators: %s: phone */
		sprintf( __( 'Thanks very much — happy to chat on %s.', 'taxi-peninsula' ), $phone ),
		'',
		$site,
		home_url( '/' ),
	);
	return array( $subject, implode( "\n", $lines ) );
}

/**
 * Does their page link to us? true, false, or WP_Error.
 */
function tp_outreach_check( $url ) {
	$res = wp_remote_get( $url, array( 'timeout' => 12, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (compatible; LinkCheck; +' . home_url( '/' ) . ')' ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( wp_remote_retrieve_response_code( $res ) >= 400 ) {
		/* translators: %d: HTTP status */
		return new WP_Error( 'tp_outreach', sprintf( __( 'Their page answered with error %d.', 'taxi-peninsula' ), wp_remote_retrieve_response_code( $res ) ) );
	}
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return (bool) preg_match( '#href=["\']https?://(www\.)?' . preg_quote( $host, '#' ) . '#i', (string) wp_remote_retrieve_body( $res ) );
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'marketing' ), __( 'Link partners', 'taxi-peninsula' ), __( 'Link partners', 'taxi-peninsula' ), 'tp_manage_offers', 'tp-outreach', 'tp_render_outreach_page', 30 );
	},
	40
);

add_action(
	'admin_post_tp_outreach_save',
	static function () {
		if ( ! current_user_can( 'tp_manage_offers' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_outreach_save' );
		$rows = isset( $_POST['o'] ) && is_array( $_POST['o'] ) ? wp_unslash( $_POST['o'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$msg  = __( 'Saved.', 'taxi-peninsula' );
		foreach ( $rows as $id => $r ) {
			$id = (int) $id;
			if ( 'tp_place' !== get_post_type( $id ) ) {
				continue;
			}
			$status = sanitize_key( $r['status'] ?? '' );
			$status = isset( tp_outreach_statuses()[ $status ] ) ? $status : '';
			if ( $status !== (string) get_post_meta( $id, '_tp_out_status', true ) ) {
				update_post_meta( $id, '_tp_out_status', $status );
				update_post_meta( $id, '_tp_out_date', time() );
			}
			update_post_meta( $id, '_tp_out_note', sanitize_text_field( $r['note'] ?? '' ) );
			update_post_meta( $id, '_tp_out_url', esc_url_raw( $r['url'] ?? '' ) );
		}
		$check = isset( $_POST['check'] ) ? (int) $_POST['check'] : 0;
		if ( $check ) {
			$url = get_post_meta( $check, '_tp_out_url', true ) ?: tp_place_meta( $check )['official'];
			if ( ! $url ) {
				$msg = __( 'Add the address of their page first.', 'taxi-peninsula' );
			} else {
				$found = tp_outreach_check( $url );
				if ( is_wp_error( $found ) ) {
					$msg = $found->get_error_message();
				} elseif ( $found ) {
					update_post_meta( $check, '_tp_out_status', 'linked' );
					update_post_meta( $check, '_tp_out_date', time() );
					/* translators: %s: place */
					$msg = sprintf( __( 'Found a link to your site on the %s page — marked as linked.', 'taxi-peninsula' ), get_the_title( $check ) );
				} else {
					/* translators: %s: place */
					$msg = sprintf( __( 'No link to your site on that %s page yet. If they put it on another page (e.g. "Getting here"), paste that page’s address and check again.', 'taxi-peninsula' ), get_the_title( $check ) );
				}
			}
		}
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', $msg ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-outreach' ) );
		exit;
	}
);

function tp_render_outreach_page() {
	if ( ! current_user_can( 'tp_manage_offers' ) ) {
		return;
	}
	$ids    = tp_outreach_places();
	$counts = array_fill_keys( array_keys( tp_outreach_statuses() ), 0 );
	foreach ( $ids as $id ) {
		$st = (string) get_post_meta( $id, '_tp_out_status', true );
		$counts[ isset( $counts[ $st ] ) ? $st : '' ]++;
	}
	$types = tp_place_types();
	?>
	<div class="wrap tp-outreach">
		<h1><?php esc_html_e( 'Link partners', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Each hotel, winery and venue below has its own page on your site. Ask them to link to it as “Getting here by taxi” — a link from a local business tells Google you really serve that place, and brings you their visitors. Send the email yourself, then set the status so you know who to follow up.', 'taxi-peninsula' ); ?></p>
		<p>
			<?php
			/* translators: 1: linked 2: asked 3: not asked */
			echo esc_html( sprintf( __( 'Linked: %1$d · Asked: %2$d · Not asked yet: %3$d', 'taxi-peninsula' ), $counts['linked'], $counts['asked'] + $counts['followup'], $counts[''] ) );
			?>
		</p>
		<?php if ( ! $ids ) : ?>
			<p><?php esc_html_e( 'No hotel, winery or venue pages yet. Add them from the Dashboard (“Add the new pages”) or under Place pages.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_outreach_save">
				<?php wp_nonce_field( 'tp_outreach_save' ); ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Place', 'taxi-peninsula' ); ?></th>
						<th><?php esc_html_e( 'Ask them', 'taxi-peninsula' ); ?></th>
						<th><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></th>
						<th><?php esc_html_e( 'Their page with the link', 'taxi-peninsula' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'taxi-peninsula' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $ids as $id ) : ?>
						<?php
						$m       = tp_place_meta( $id );
						$email   = tp_outreach_email( $id );
						$status  = (string) get_post_meta( $id, '_tp_out_status', true );
						$when    = (int) get_post_meta( $id, '_tp_out_date', true );
						$their   = (string) get_post_meta( $id, '_tp_out_url', true );
						$mailto  = 'mailto:?subject=' . rawurlencode( $email[0] ) . '&body=' . rawurlencode( $email[1] );
						?>
						<tr>
							<td>
								<strong><a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $id ) ); ?></a></strong><br>
								<span class="description"><?php echo esc_html( $types[ $m['type'] ][0] ?? '' ); ?><?php echo $m['area'] ? ' · ' . esc_html( $m['area'] ) : ''; ?></span>
								<?php if ( $m['official'] ) : ?>
									<br><a href="<?php echo esc_url( $m['official'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Their website', 'taxi-peninsula' ); ?></a>
								<?php endif; ?>
							</td>
							<td>
								<a class="button button-small" href="<?php echo esc_attr( $mailto ); ?>"><?php esc_html_e( 'Open email', 'taxi-peninsula' ); ?></a>
								<button type="button" class="button button-small" data-tp-copy="<?php echo esc_attr( $email[0] . "\n\n" . $email[1] ); ?>"><?php esc_html_e( 'Copy email', 'taxi-peninsula' ); ?></button>
								<button type="button" class="button button-small" data-tp-copy="<?php echo esc_attr( tp_outreach_link_code( $id ) ); ?>"><?php esc_html_e( 'Copy link code', 'taxi-peninsula' ); ?></button>
							</td>
							<td>
								<select name="o[<?php echo (int) $id; ?>][status]">
									<?php foreach ( tp_outreach_statuses() as $k => $label ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( $when ) : ?>
									<br><span class="description"><?php echo esc_html( wp_date( get_option( 'date_format' ), $when ) ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<input type="url" class="regular-text" name="o[<?php echo (int) $id; ?>][url]" value="<?php echo esc_attr( $their ); ?>" placeholder="<?php echo esc_attr( $m['official'] ?: 'https://' ); ?>">
								<button class="button button-small" name="check" value="<?php echo (int) $id; ?>"><?php esc_html_e( 'Check', 'taxi-peninsula' ); ?></button>
							</td>
							<td><input type="text" name="o[<?php echo (int) $id; ?>][note]" value="<?php echo esc_attr( (string) get_post_meta( $id, '_tp_out_note', true ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. emailed events manager', 'taxi-peninsula' ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button class="button button-primary"><?php esc_html_e( 'Save', 'taxi-peninsula' ); ?></button></p>
			</form>
			<h2><?php esc_html_e( 'Tips', 'taxi-peninsula' ); ?></h2>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Find the right person: the events, functions or guest-services manager rather than a general inbox.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Ask five a week. Follow up once after a fortnight; most links come from the second email or a quick call.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Add a real photo of their entrance to their place page first — it shows you have been there and makes them happier to link.', 'taxi-peninsula' ); ?></li>
				<li><?php esc_html_e( 'Offer a front-desk booking page with a QR code (Bookings → Partner pages) to hotels and wineries that say yes.', 'taxi-peninsula' ); ?></li>
			</ul>
		<?php endif; ?>
	</div>
	<script>
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-tp-copy]' );
		if ( ! b || ! navigator.clipboard ) { return; }
		navigator.clipboard.writeText( b.getAttribute( 'data-tp-copy' ) ).then( function () {
			var t = b.textContent; b.textContent = <?php echo wp_json_encode( __( 'Copied', 'taxi-peninsula' ) ); ?>; setTimeout( function () { b.textContent = t; }, 1500 );
		} );
	} );
	</script>
	<?php
}
