<?php
/**
 * Event landing pages: concerts, the footy, Christmas lights, festivals.
 *
 * Events → Add new. Each event has a date and time, the venue, and the
 * accessible drop-off / pick-up points you use there. Every point has
 * "Book a trip here" and "Book a ride home from here" buttons that open the
 * booking form already filled in (with the event's promo code, if any).
 *
 * Pages: /events/ (upcoming events) and /events/{name}/. Past events say so
 * and point to what is coming up. Shortcode: [tp_events limit="3"].
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_event',
			array(
				'labels'          => array(
					'name'          => __( 'Events', 'taxi-peninsula' ),
					'singular_name' => __( 'Event', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Add event', 'taxi-peninsula' ),
					'edit_item'     => __( 'Edit event', 'taxi-peninsula' ),
					'view_item'     => __( 'View event', 'taxi-peninsula' ),
					'all_items'     => __( 'Events', 'taxi-peninsula' ),
				),
				'public'          => true,
				'has_archive'     => 'events',
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-tickets-alt',
				'menu_position'   => 26,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'         => array( 'slug' => 'events', 'with_front' => false ),
				'capability_type' => array( 'tp_promo', 'tp_promos' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

function tp_event_meta( $id ) {
	$m = array();
	foreach ( array( 'start', 'end', 'venue', 'address', 'points', 'return', 'promo', 'arrive' ) as $k ) {
		$m[ $k ] = (string) get_post_meta( $id, '_tp_e_' . $k, true );
	}
	$m['arrive'] = '' === $m['arrive'] ? 45 : (int) $m['arrive'];
	return $m;
}

/**
 * Pick-up / drop-off points: [ [name, address, note], … ].
 */
function tp_event_points( $id ) {
	$out = array();
	foreach ( preg_split( '/\R/', tp_event_meta( $id )['points'] ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( '' !== $p[0] ) {
			$out[] = array( $p[0], $p[1] ?? '', $p[2] ?? '' );
		}
	}
	return $out;
}

function tp_event_ts( $id, $which = 'start' ) {
	$m = tp_event_meta( $id );
	$v = 'end' === $which ? $m['end'] : $m['start'];
	if ( 'end' === $which && preg_match( '/^\d{2}:\d{2}$/', $v ) ) {
		$v = substr( $m['start'], 0, 10 ) . ' ' . $v;
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $v ) ) {
		return 0;
	}
	return ( new DateTimeImmutable( $v, wp_timezone() ) )->getTimestamp();
}

function tp_event_is_past( $id ) {
	$end = tp_event_ts( $id, 'end' ) ?: tp_event_ts( $id ) + 4 * HOUR_IN_SECONDS;
	return $end && $end < time();
}

/**
 * Booking link for an event trip.
 *
 * @param int    $id   Event.
 * @param string $dir  'to' (drop-off at the point) or 'home' (pick-up from the point).
 * @param array  $pt   Point [name, address, note].
 */
function tp_event_book_url( $id, $dir, array $pt ) {
	$m     = tp_event_meta( $id );
	$start = tp_event_ts( $id );
	$end   = tp_event_ts( $id, 'end' );
	$addr  = $pt[1] ?: trim( $pt[0] . ', ' . $m['address'], ', ' );
	$args  = array(
		'date'         => $start ? wp_date( 'Y-m-d', $start ) : '',
		'utm_source'   => get_post_field( 'post_name', $id ),
		'utm_medium'   => 'event',
		'utm_campaign' => get_post_field( 'post_name', $id ),
	);
	if ( 'home' === $dir ) {
		$args['pickup'] = $addr;
		if ( $end ) {
			$args['time'] = wp_date( 'H:i', $end );
		}
	} else {
		$args['dropoff'] = $addr;
	}
	if ( $m['promo'] ) {
		$args['promo'] = $m['promo'];
	}
	return add_query_arg( array_map( 'rawurlencode', array_filter( $args ) ), strtok( tp_booking_page_url(), '#' ) ) . '#book';
}

/**
 * Upcoming events (soonest first).
 */
function tp_upcoming_events( $limit = 12 ) {
	return get_posts(
		array(
			'post_type'   => 'tp_event',
			'numberposts' => $limit,
			'meta_key'    => '_tp_e_start', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( array( 'key' => '_tp_e_start', 'value' => wp_date( 'Y-m-d H:i', time() - 6 * HOUR_IN_SECONDS ), 'compare' => '>=' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

// The /events/ archive lists upcoming events only, soonest first.
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_post_type_archive( 'tp_event' ) ) {
			$q->set( 'meta_key', '_tp_e_start' );
			$q->set( 'orderby', 'meta_value' );
			$q->set( 'order', 'ASC' );
			$q->set( 'meta_query', array( array( 'key' => '_tp_e_start', 'value' => wp_date( 'Y-m-d H:i', time() - 6 * HOUR_IN_SECONDS ), 'compare' => '>=' ) ) );
			$q->set( 'posts_per_page', 24 );
		}
	}
);

/**
 * Card for an event (used on /events/ and by the shortcode).
 */
function tp_event_card( $post ) {
	$start = tp_event_ts( $post->ID );
	$m     = tp_event_meta( $post->ID );
	ob_start();
	?>
	<article class="event-card card">
		<?php if ( has_post_thumbnail( $post ) ) : ?>
			<a class="event-card__img" href="<?php echo esc_url( get_permalink( $post ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo get_the_post_thumbnail( $post, 'medium_large', array( 'alt' => '', 'loading' => 'lazy' ) ); ?></a>
		<?php endif; ?>
		<?php if ( $start ) : ?>
			<p class="event-card__date"><time datetime="<?php echo esc_attr( wp_date( 'c', $start ) ); ?>"><span class="event-card__day"><?php echo esc_html( wp_date( 'j', $start ) ); ?></span> <span><?php echo esc_html( wp_date( 'M', $start ) ); ?></span></time></p>
		<?php endif; ?>
		<h3 class="event-card__title"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
		<p class="event-card__meta"><?php echo esc_html( trim( ( $start ? wp_date( 'l, g:i a', $start ) : '' ) . ( $m['venue'] ? ' · ' . $m['venue'] : '' ), ' ·' ) ); ?></p>
		<?php if ( has_excerpt( $post ) ) : ?>
			<p><?php echo esc_html( get_the_excerpt( $post ) ); ?></p>
		<?php endif; ?>
	</article>
	<?php
	return ob_get_clean();
}

add_shortcode(
	'tp_events',
	static function ( $atts ) {
		$atts   = shortcode_atts( array( 'limit' => 3 ), $atts, 'tp_events' );
		$events = tp_upcoming_events( max( 1, min( 12, (int) $atts['limit'] ) ) );
		if ( ! $events ) {
			return '';
		}
		return '<div class="tp-component event-grid">' . implode( '', array_map( 'tp_event_card', $events ) ) . '</div>';
	}
);

/* -------------------------------------------------------------------------
 * Admin.
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tp_event',
	static function () {
		add_meta_box( 'tp_event_box', __( 'Event details', 'taxi-peninsula' ), 'tp_render_event_box', 'tp_event', 'normal', 'high' );
	}
);

function tp_render_event_box( $post ) {
	$m = tp_event_meta( $post->ID );
	wp_nonce_field( 'tp_event_save', 'tp_event_nonce' );
	?>
	<table class="form-table" role="presentation">
		<tr><th><label for="tp_e_start"><?php esc_html_e( 'Starts', 'taxi-peninsula' ); ?></label></th><td><input type="datetime-local" id="tp_e_start" name="tp_e[start]" value="<?php echo esc_attr( str_replace( ' ', 'T', $m['start'] ) ); ?>"> <label for="tp_e_end"><?php esc_html_e( 'Finishes at', 'taxi-peninsula' ); ?></label> <input type="time" id="tp_e_end" name="tp_e[end]" value="<?php echo esc_attr( $m['end'] ); ?>"></td></tr>
		<tr><th><label for="tp_e_venue"><?php esc_html_e( 'Venue', 'taxi-peninsula' ); ?></label></th><td><input type="text" class="regular-text" id="tp_e_venue" name="tp_e[venue]" value="<?php echo esc_attr( $m['venue'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Melbourne Cricket Ground', 'taxi-peninsula' ); ?>"></td></tr>
		<tr><th><label for="tp_e_address"><?php esc_html_e( 'Venue address', 'taxi-peninsula' ); ?></label></th><td><input type="text" class="large-text" id="tp_e_address" name="tp_e[address]" value="<?php echo esc_attr( $m['address'] ); ?>"></td></tr>
		<tr><th><label for="tp_e_points"><?php esc_html_e( 'Drop-off and pick-up points', 'taxi-peninsula' ); ?></label></th><td><textarea class="large-text" rows="5" id="tp_e_points" name="tp_e[points]" placeholder="<?php esc_attr_e( 'Gate 3 accessible zone | Brunton Ave, Richmond VIC 3002 | Lift to level 1. Staff in yellow vests.', 'taxi-peninsula' ); ?>"><?php echo esc_textarea( $m['points'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One per line: name | address | note for passengers. Leave the address empty to use the venue address.', 'taxi-peninsula' ); ?></p></td></tr>
		<tr><th><label for="tp_e_return"><?php esc_html_e( 'Going home after', 'taxi-peninsula' ); ?></label></th><td><textarea class="large-text" rows="2" id="tp_e_return" name="tp_e[return]" placeholder="<?php esc_attr_e( 'e.g. Meet your driver at Gate 3 from 10:15 pm. Expect a short wait after the final siren.', 'taxi-peninsula' ); ?>"><?php echo esc_textarea( $m['return'] ); ?></textarea></td></tr>
		<tr><th><label for="tp_e_arrive"><?php esc_html_e( 'Arrive this many minutes early', 'taxi-peninsula' ); ?></label></th><td><input type="number" min="0" max="240" class="small-text" id="tp_e_arrive" name="tp_e[arrive]" value="<?php echo esc_attr( $m['arrive'] ); ?>"></td></tr>
		<tr><th><label for="tp_e_promo"><?php esc_html_e( 'Promo code (optional)', 'taxi-peninsula' ); ?></label></th><td><input type="text" id="tp_e_promo" name="tp_e[promo]" value="<?php echo esc_attr( $m['promo'] ); ?>"></td></tr>
	</table>
	<?php
}

add_action(
	'save_post_tp_event',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_event_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_event_nonce'] ), 'tp_event_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in    = isset( $_POST['tp_e'] ) && is_array( $_POST['tp_e'] ) ? wp_unslash( $_POST['tp_e'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$start = str_replace( 'T', ' ', sanitize_text_field( $in['start'] ?? '' ) );
		update_post_meta( $post_id, '_tp_e_start', preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $start ) ? $start : '' );
		$end = sanitize_text_field( $in['end'] ?? '' );
		update_post_meta( $post_id, '_tp_e_end', preg_match( '/^\d{2}:\d{2}$/', $end ) ? $end : '' );
		foreach ( array( 'venue', 'address' ) as $k ) {
			update_post_meta( $post_id, '_tp_e_' . $k, sanitize_text_field( $in[ $k ] ?? '' ) );
		}
		foreach ( array( 'points', 'return' ) as $k ) {
			update_post_meta( $post_id, '_tp_e_' . $k, sanitize_textarea_field( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_tp_e_arrive', min( 240, absint( $in['arrive'] ?? 45 ) ) );
		update_post_meta( $post_id, '_tp_e_promo', strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['promo'] ?? '' ) ) ) );
	}
);

add_filter(
	'manage_tp_event_posts_columns',
	static function ( $c ) {
		return array( 'cb' => $c['cb'], 'title' => $c['title'], 'tp_when' => __( 'When', 'taxi-peninsula' ), 'tp_venue' => __( 'Venue', 'taxi-peninsula' ), 'tp_bookings' => __( 'Bookings', 'taxi-peninsula' ) );
	}
);
add_action(
	'manage_tp_event_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'tp_when' === $col ) {
			$ts = tp_event_ts( $id );
			echo esc_html( $ts ? wp_date( 'D j M Y, g:i a', $ts ) : '—' ) . ( tp_event_is_past( $id ) ? ' <em>(' . esc_html__( 'past', 'taxi-peninsula' ) . ')</em>' : '' );
		} elseif ( 'tp_venue' === $col ) {
			echo esc_html( tp_event_meta( $id )['venue'] );
		} elseif ( 'tp_bookings' === $col ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			echo esc_html( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tp_source' AND meta_value LIKE %s", $wpdb->esc_like( get_post_field( 'post_name', $id ) . ' / event' ) . '%' ) ) );
		}
	},
	10,
	2
);

// schema.org Event.
add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( 'tp_event' ) ) {
			return;
		}
		$id    = get_queried_object_id();
		$m     = tp_event_meta( $id );
		$start = tp_event_ts( $id );
		if ( ! $start ) {
			return;
		}
		$data = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => get_the_title( $id ),
			'startDate'           => wp_date( 'c', $start ),
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'location'            => array( '@type' => 'Place', 'name' => $m['venue'] ?: get_the_title( $id ), 'address' => $m['address'] ),
			'description'         => has_excerpt( $id ) ? get_the_excerpt( $id ) : '',
			'organizer'           => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
		);
		if ( tp_event_ts( $id, 'end' ) ) {
			$data['endDate'] = wp_date( 'c', tp_event_ts( $id, 'end' ) );
		}
		if ( has_post_thumbnail( $id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $id, 'large' );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
);

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_post_type_archive( 'tp_event' ) ) {
			$parts['title'] = __( 'Wheelchair taxis to events', 'taxi-peninsula' );
		}
		return $parts;
	}
);
