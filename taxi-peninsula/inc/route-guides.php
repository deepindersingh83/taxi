<?php
/**
 * Fare and route guides (blog posts) and the [tp_trip_facts] box they use.
 *
 * [tp_trip_facts from="Rye" to="Melbourne Airport"] shows the rough distance,
 * travel time and a fare estimate from your own rates (Bookings → Settings →
 * Fares), plus a "Book this trip" button with both addresses filled in. The
 * numbers are worked out when the page is shown, so they follow your rates.
 * "from" and "to" can be a suburb you cover or the name of a place page.
 *
 * The content pack adds 24 written guides in a "Fare & route guides"
 * category: four are published at once and the rest one a week, so the blog
 * keeps getting fresh posts. Change any dates under Posts.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * A suburb or place: [name, lat, lng, url, address] or null.
 */
function tp_trip_point( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return null;
	}
	$place = get_posts( array( 'post_type' => 'tp_place', 'post_status' => 'publish', 'title' => $name, 'numberposts' => 1 ) );
	if ( ! $place ) {
		$place = get_posts( array( 'post_type' => 'tp_place', 'post_status' => 'publish', 'name' => sanitize_title( $name ), 'numberposts' => 1 ) );
	}
	if ( $place ) {
		$m = tp_place_meta( $place[0]->ID );
		if ( is_numeric( $m['lat'] ) && is_numeric( $m['lng'] ) ) {
			return array( get_the_title( $place[0] ), (float) $m['lat'], (float) $m['lng'], get_permalink( $place[0] ), $m['address'] ?: get_the_title( $place[0] ) );
		}
	}
	$known = tp_map_places();
	$k     = strtolower( $name );
	if ( isset( $known[ $k ] ) ) {
		$area = function_exists( 'tp_route_area_url' ) ? tp_route_area_url( $name ) : '';
		return array( $name, $known[ $k ][0], $known[ $k ][1], $area, $name . ' VIC' );
	}
	return null;
}

/**
 * Trip numbers: [ road km, minutes, fare text or '' ] or null.
 */
function tp_trip_numbers( $a, $b ) {
	$dlat = deg2rad( $b[1] - $a[1] );
	$dlng = deg2rad( $b[2] - $a[2] );
	$h    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $a[1] ) ) * cos( deg2rad( $b[1] ) ) * sin( $dlng / 2 ) ** 2;
	$km   = 6371 * 2 * atan2( sqrt( $h ), sqrt( 1 - $h ) ) * 1.3;
	if ( $km < 0.5 ) {
		return null;
	}
	$minutes = max( 5, (int) ( round( $km * ( $km > 30 ? 0.85 : 1.1 ) / 5 ) * 5 ) );
	$fare    = function_exists( 'tp_fare_from_distance' ) ? tp_fare_from_distance( $km, $minutes, '', '', $a[0], $b[0] ) : null;
	return array( round( $km ), $minutes, $fare ? tp_fare_text( $fare[0], $fare[1] ) : '' );
}

add_shortcode(
	'tp_trip_facts',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'from' => '', 'to' => '' ), $atts, 'tp_trip_facts' );
		$a    = tp_trip_point( $atts['from'] );
		$b    = tp_trip_point( $atts['to'] );
		if ( ! $a || ! $b ) {
			return '';
		}
		$n = tp_trip_numbers( $a, $b );
		if ( ! $n ) {
			return '';
		}
		$book = add_query_arg( array( 'pickup' => rawurlencode( $a[4] ), 'dropoff' => rawurlencode( $b[4] ) ), tp_booking_page_url() ) . '#book';
		ob_start();
		?>
		<div class="trip-facts card">
			<p class="trip-facts__title">
				<?php
				/* translators: 1: from 2: to */
				echo esc_html( sprintf( __( '%1$s to %2$s at a glance', 'taxi-peninsula' ), $a[0], $b[0] ) );
				?>
			</p>
			<ul class="route-facts">
				<li><span><?php esc_html_e( 'Distance', 'taxi-peninsula' ); ?></span><strong>
					<?php
					/* translators: %d: km */
					echo esc_html( sprintf( __( 'about %d km', 'taxi-peninsula' ), $n[0] ) );
					?>
				</strong></li>
				<li><span><?php esc_html_e( 'Drive', 'taxi-peninsula' ); ?></span><strong>
					<?php
					/* translators: %d: minutes */
					echo esc_html( sprintf( __( 'about %d min', 'taxi-peninsula' ), $n[1] ) );
					?>
				</strong></li>
				<?php if ( $n[2] ) : ?>
					<li><span><?php esc_html_e( 'Fare estimate', 'taxi-peninsula' ); ?></span><strong><?php echo esc_html( $n[2] ); ?></strong></li>
				<?php endif; ?>
			</ul>
			<p class="route-facts__note"><?php esc_html_e( 'A guide without traffic, from our current rates. The fare depends on the exact addresses, time of day, waiting and tolls — ask for a fixed quote when you book.', 'taxi-peninsula' ); ?></p>
			<p class="trip-facts__actions">
				<a class="btn btn--accent" href="<?php echo esc_url( $book ); ?>"><?php esc_html_e( 'Book this trip', 'taxi-peninsula' ); ?></a>
				<?php if ( $b[3] ) : ?>
					<?php /* translators: %s: place */ ?>
					<a class="btn btn--ghost" href="<?php echo esc_url( $b[3] ); ?>"><?php echo esc_html( sprintf( __( 'About %s', 'taxi-peninsula' ), $b[0] ) ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}
);

/** The guides category (created when needed). */
function tp_guides_category() {
	$term = get_term_by( 'slug', 'fare-route-guides', 'category' );
	if ( $term ) {
		return (int) $term->term_id;
	}
	$made = wp_insert_term( __( 'Fare & route guides', 'taxi-peninsula' ), 'category', array( 'slug' => 'fare-route-guides', 'description' => __( 'Taxi fares, travel times and tips for trips to and from the Mornington Peninsula.', 'taxi-peninsula' ) ) );
	return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
}

/** Post body for a written guide. */
function tp_guide_content( array $g ) {
	$out = array();
	foreach ( (array) $g['content'] as $item ) {
		$out[] = isset( $item['facts'] )
			? "<!-- wp:shortcode -->\n[tp_trip_facts from=\"" . esc_attr( $item['facts']['from'] ?? '' ) . '" to="' . esc_attr( $item['facts']['to'] ?? '' ) . "\"]\n<!-- /wp:shortcode -->"
			: tp_pack_blocks( array( $item ) );
	}
	if ( ! empty( $g['faq'] ) ) {
		$out[] = tp_pack_blocks( array( array( 'h2' => __( 'Questions people ask', 'taxi-peninsula' ) ) ) );
		foreach ( $g['faq'] as $qa ) {
			$out[] = "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . esc_html( $qa['q'] ) . "</h3>\n<!-- /wp:heading -->";
			$out[] = tp_pack_blocks( array( array( 'p' => $qa['a'] ) ) );
		}
	}
	return implode( "\n\n", $out );
}

/** Guides not yet added. */
function tp_guides_pending() {
	$n = 0;
	foreach ( tp_pack_data( 'posts' ) as $g ) {
		$n += get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'name' => $g['slug'], 'numberposts' => 1, 'fields' => 'ids' ) ) ? 0 : 1;
	}
	return $n;
}

/**
 * Add the written guides: the first four now, then one a week at 9am.
 */
function tp_guides_import() {
	$cat   = tp_guides_category();
	$added = 0;
	$i     = 0;
	$start = (int) strtotime( 'tomorrow 09:00', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local time for post_date.
	foreach ( tp_pack_data( 'posts' ) as $g ) {
		if ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'name' => $g['slug'], 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
			continue;
		}
		$now  = $i < 4;
		$when = $now ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s', $start + ( $i - 4 ) * WEEK_IN_SECONDS ); // Site-local time, as WordPress expects.
		$id   = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => $now ? 'publish' : 'future',
				'post_title'    => $g['title'],
				'post_name'     => $g['slug'],
				'post_excerpt'  => $g['excerpt'] ?? '',
				'post_content'  => tp_guide_content( $g ),
				'post_date'     => $when,
				'post_category' => $cat ? array( $cat ) : array(),
			)
		);
		++$i;
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_tp_meta_desc', $g['description'] ?? '' );
			update_post_meta( $id, '_tp_pack', TP_PACK_VERSION );
			++$added;
		}
	}
	return $added;
}
