<?php
/**
 * Popular destinations, built from real bookings.
 *
 * Privacy: a destination only appears when (1) its name looks like a public
 * place (hospital, airport, centre…) and (2) at least three different passengers
 * travelled there in the last 12 months. Staff can hide or rename any of them
 * under Bookings → Destinations. Counts are never shown publicly.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_DEST_MIN_PASSENGERS = 3;

function tp_public_place_pattern() {
	return apply_filters(
		'tp_public_place_pattern',
		'/\b(hospital|airport|medical|clinic|dialysis|health|rehab|renal|oncology|cancer|centre|center|station|shopping|plaza|westfield|university|tafe|college|school|stadium|arena|mcg|marvel|racecourse|library|church|aquatic|pool|day program|nursing|aged care|village|market|terminal|pier|ferry|theatre|cinema)\b/i'
	);
}

/**
 * "12 Smith St, Frankston Hospital, Frankston VIC 3199, Australia" → "frankston hospital, frankston".
 */
function tp_normalise_place( $address ) {
	$a = strtolower( (string) $address );
	$a = preg_replace( '/,?\s*(australia|vic(toria)?)\b/', '', $a );
	$a = preg_replace( '/\b\d{4}\b/', '', $a );
	$a = preg_replace( '/\s+/', ' ', $a );
	return trim( $a, " ,.-" );
}

/**
 * The display label: the first part of the address that names the place.
 */
function tp_place_label( $address ) {
	$parts = array_map( 'trim', explode( ',', (string) $address ) );
	foreach ( $parts as $part ) {
		if ( preg_match( tp_public_place_pattern(), $part ) ) {
			return $part;
		}
	}
	return $parts[0];
}

/**
 * Candidate destinations with passenger counts (admin view; cached 12 hours).
 *
 * @return array key => [label, passengers, trips]
 */
function tp_destination_candidates() {
	$cached = get_transient( 'tp_destination_candidates' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$since = wp_date( 'Y-m-d', strtotime( '-12 months' ) );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT dro.meta_value AS dropoff, ph.meta_value AS phone
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} dro ON dro.post_id = p.ID AND dro.meta_key = '_tp_dropoff'
			 INNER JOIN {$wpdb->postmeta} ph ON ph.post_id = p.ID AND ph.meta_key = '_tp_phone'
			 INNER JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_tp_status'
			 INNER JOIN {$wpdb->postmeta} dt ON dt.post_id = p.ID AND dt.meta_key = '_tp_date'
			 WHERE p.post_type = 'tp_booking' AND p.post_status = 'publish'
			   AND st.meta_value IN ('completed', 'confirmed', 'assigned')
			   AND dt.meta_value >= %s",
			$since
		)
	);

	$groups = array();
	foreach ( $rows as $row ) {
		$label = tp_place_label( $row->dropoff );
		if ( ! preg_match( tp_public_place_pattern(), $label ) ) {
			continue; // Probably a home or business address — never shown.
		}
		$key = tp_normalise_place( $label );
		if ( '' === $key ) {
			continue;
		}
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array( 'labels' => array(), 'phones' => array(), 'trips' => 0 );
		}
		$groups[ $key ]['labels'][ $label ] = ( $groups[ $key ]['labels'][ $label ] ?? 0 ) + 1;
		$groups[ $key ]['phones'][ tp_phone_digits( $row->phone ) ] = true;
		$groups[ $key ]['trips']++;
	}

	$out = array();
	foreach ( $groups as $key => $g ) {
		arsort( $g['labels'] );
		$out[ $key ] = array(
			'label'      => (string) array_key_first( $g['labels'] ),
			'passengers' => count( $g['phones'] ),
			'trips'      => $g['trips'],
		);
	}
	uasort(
		$out,
		static function ( $a, $b ) {
			return array( $b['passengers'], $b['trips'] ) <=> array( $a['passengers'], $a['trips'] );
		}
	);
	set_transient( 'tp_destination_candidates', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/**
 * Public list: eligible, not hidden, with overrides applied.
 *
 * @return array[] Each: label, book_url, guide_url.
 */
function tp_popular_destinations( $limit = 6 ) {
	$overrides = (array) get_option( 'tp_destinations', array() );
	$book      = tp_booking_page_url();
	$out       = array();
	foreach ( tp_destination_candidates() as $key => $d ) {
		$o = $overrides[ $key ] ?? array();
		if ( ! empty( $o['hidden'] ) || $d['passengers'] < TP_DEST_MIN_PASSENGERS ) {
			continue;
		}
		$label = ! empty( $o['label'] ) ? $o['label'] : $d['label'];
		$guide = ! empty( $o['url'] ) ? $o['url'] : tp_find_guide_for( $label );
		$out[] = array(
			'label'     => $label,
			'book_url'  => add_query_arg( 'dropoff', rawurlencode( $label ), $book ),
			'guide_url' => $guide,
		);
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * A published blog post whose title mentions the place, if any.
 */
function tp_find_guide_for( $label ) {
	static $cache = array();
	if ( isset( $cache[ $label ] ) ) {
		return $cache[ $label ];
	}
	$found           = get_posts(
		array(
			'post_type'   => 'post',
			'numberposts' => 1,
			's'           => $label,
			'search_columns' => array( 'post_title' ),
		)
	);
	$cache[ $label ] = ( $found && false !== stripos( $found[0]->post_title, $label ) ) ? get_permalink( $found[0] ) : '';
	return $cache[ $label ];
}

add_action( 'tp_booking_status_changed', static function () {
	delete_transient( 'tp_destination_candidates' );
} );

add_shortcode( 'tp_destinations', static function () {
	ob_start();
	get_template_part( 'template-parts/destinations' );
	return ob_get_clean();
} );

/* ---- Admin: Bookings → Destinations ---- */

add_action( 'admin_menu', static function () {
	add_submenu_page( 'edit.php?post_type=tp_booking', __( 'Popular destinations', 'taxi-peninsula' ), __( 'Destinations', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-destinations', 'tp_render_destinations_page' );
}, 16 );

function tp_render_destinations_page() {
	$candidates = tp_destination_candidates();
	$overrides  = (array) get_option( 'tp_destinations', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Popular destinations', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Built automatically from bookings in the last 12 months. To protect privacy, only places that look public (hospitals, airports, centres…) and that at least three different passengers travelled to can appear on the website. Visitor counts are never shown.', 'taxi-peninsula' ); ?></p>
		<?php if ( ! $candidates ) : ?>
			<p><em><?php esc_html_e( 'Nothing yet — destinations appear here as bookings build up.', 'taxi-peninsula' ); ?></em></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_save_destinations">
				<?php wp_nonce_field( 'tp_save_destinations' ); ?>
				<table class="widefat striped">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Destination', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Passengers / trips (12 months)', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Show as', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Guide link (optional)', 'taxi-peninsula' ); ?></th>
						<th scope="col"><?php esc_html_e( 'On website', 'taxi-peninsula' ); ?></th>
					</tr></thead>
					<tbody>
						<?php
						foreach ( $candidates as $key => $d ) :
							$o        = $overrides[ $key ] ?? array();
							$eligible = $d['passengers'] >= TP_DEST_MIN_PASSENGERS;
							$field    = 'dest[' . md5( $key ) . ']';
							?>
							<tr>
								<td><?php echo esc_html( $d['label'] ); ?><input type="hidden" name="<?php echo esc_attr( $field ); ?>[key]" value="<?php echo esc_attr( $key ); ?>"></td>
								<td><?php echo esc_html( $d['passengers'] . ' / ' . $d['trips'] ); ?></td>
								<td><input type="text" name="<?php echo esc_attr( $field ); ?>[label]" value="<?php echo esc_attr( $o['label'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $d['label'] ); ?>"></td>
								<td><input type="url" name="<?php echo esc_attr( $field ); ?>[url]" value="<?php echo esc_attr( $o['url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( tp_find_guide_for( $d['label'] ) ?: 'https://' ); ?>"></td>
								<td>
									<?php if ( $eligible ) : ?>
										<label><input type="checkbox" name="<?php echo esc_attr( $field ); ?>[show]" value="1" <?php checked( empty( $o['hidden'] ) ); ?>> <?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></label>
									<?php else : ?>
										<span class="description"><?php esc_html_e( 'Needs 3+ passengers', 'taxi-peninsula' ); ?></span>
										<input type="hidden" name="<?php echo esc_attr( $field ); ?>[show]" value="<?php echo empty( $o['hidden'] ) ? '1' : ''; ?>">
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_tp_save_destinations', static function () {
	if ( ! current_user_can( 'edit_tp_bookings' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'tp_save_destinations' );
	$in  = isset( $_POST['dest'] ) && is_array( $_POST['dest'] ) ? wp_unslash( $_POST['dest'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
	$out = array();
	foreach ( $in as $row ) {
		$key = sanitize_text_field( $row['key'] ?? '' );
		if ( '' === $key ) {
			continue;
		}
		$out[ $key ] = array(
			'hidden' => empty( $row['show'] ),
			'label'  => sanitize_text_field( $row['label'] ?? '' ),
			'url'    => esc_url_raw( $row['url'] ?? '' ),
		);
	}
	update_option( 'tp_destinations', $out, false );
	set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', __( 'Destinations saved.', 'taxi-peninsula' ) ), 60 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=tp_booking&page=tp-destinations' ) );
	exit;
} );
