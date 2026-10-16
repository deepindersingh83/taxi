<?php
/**
 * Place pages for searches like "wheelchair taxi to Frankston Hospital" and
 * "wheelchair taxi from Frankston Station":
 *
 * - "Going to" places (hospitals, clinics, airports, venues):
 *   /wheelchair-taxi-to/{slug}/
 * - "Picking up from" places (railway stations, shopping centres):
 *   /wheelchair-taxi-from/{slug}/
 *
 * Each page has the accessible entrance, the exact drop-off or pick-up spot,
 * parking and tips, a booking card with the address filled in, travel times
 * from nearby suburbs, questions and answers and matching reviews.
 * Manage them under Website content → Place pages. [tp_places] lists them.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_PLACES_RW = 1;

function tp_place_types() {
	return array(
		'hospital' => array( __( 'Hospital', 'taxi-peninsula' ), 'to' ),
		'clinic'   => array( __( 'Clinic or medical centre', 'taxi-peninsula' ), 'to' ),
		'airport'  => array( __( 'Airport', 'taxi-peninsula' ), 'to' ),
		'venue'    => array( __( 'Venue or attraction', 'taxi-peninsula' ), 'to' ),
		'care'     => array( __( 'Aged care or day program', 'taxi-peninsula' ), 'to' ),
		'station'  => array( __( 'Railway station', 'taxi-peninsula' ), 'from' ),
		'shopping' => array( __( 'Shopping centre', 'taxi-peninsula' ), 'from' ),
	);
}

function tp_place_meta( $id ) {
	$m = array();
	foreach ( array( 'type', 'address', 'lat', 'lng', 'area', 'entrance', 'spot', 'parking', 'tips', 'official' ) as $k ) {
		$m[ $k ] = (string) get_post_meta( $id, '_tp_p_' . $k, true );
	}
	$m['type'] = isset( tp_place_types()[ $m['type'] ] ) ? $m['type'] : 'hospital';
	$m['kind'] = tp_place_types()[ $m['type'] ][1];
	return $m;
}

function tp_place_kind( $id ) {
	return tp_place_meta( $id )['kind'];
}

/**
 * "Wheelchair taxi to Frankston Hospital" / "Wheelchair taxi from Frankston Station".
 */
function tp_place_title( $id ) {
	return 'from' === tp_place_kind( $id )
		/* translators: %s: place */
		? sprintf( __( 'Wheelchair taxi from %s', 'taxi-peninsula' ), get_the_title( $id ) )
		/* translators: %s: place */
		: sprintf( __( 'Wheelchair taxi to %s', 'taxi-peninsula' ), get_the_title( $id ) );
}

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_place',
			array(
				'labels'              => array(
					'name'          => __( 'Place pages', 'taxi-peninsula' ),
					'singular_name' => __( 'Place page', 'taxi-peninsula' ),
					'add_new'       => __( 'Add place', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Add a hospital, station or other place', 'taxi-peninsula' ),
					'edit_item'     => __( 'Edit place page', 'taxi-peninsula' ),
					'view_item'     => __( 'View place page', 'taxi-peninsula' ),
				),
				'public'              => true,
				'show_in_nav_menus'   => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-location',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'rewrite'             => false,
				'query_var'           => 'tp_place',
				'has_archive'         => false,
				'exclude_from_search' => false,
			)
		);
		add_rewrite_rule( '^wheelchair-taxi-(to|from)/([^/]+)/?$', 'index.php?tp_place=$matches[2]&tp_place_kind=$matches[1]', 'top' );
		if ( (int) get_option( 'tp_places_rw' ) !== TP_PLACES_RW ) {
			flush_rewrite_rules( false );
			update_option( 'tp_places_rw', TP_PLACES_RW, false );
		}
	},
	21
);

add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'tp_place_kind';
		return $v;
	}
);

add_filter(
	'post_type_link',
	static function ( $link, $post ) {
		if ( 'tp_place' !== $post->post_type || ! $post->post_name || in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return $link;
		}
		return home_url( user_trailingslashit( 'wheelchair-taxi-' . tp_place_kind( $post->ID ) . '/' . $post->post_name ) );
	},
	10,
	2
);

// /wheelchair-taxi-to/frankston-station/ → /wheelchair-taxi-from/frankston-station/.
add_action(
	'template_redirect',
	static function () {
		if ( ! is_singular( 'tp_place' ) ) {
			return;
		}
		$want = tp_place_kind( get_queried_object_id() );
		$got  = get_query_var( 'tp_place_kind' );
		if ( $got && $got !== $want ) {
			wp_safe_redirect( get_permalink( get_queried_object_id() ), 301 );
			exit;
		}
	}
);

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_singular( 'tp_place' ) ) {
			$parts['title'] = tp_place_title( get_queried_object_id() );
		}
		return $parts;
	},
	20
);

add_filter(
	'tp_meta_description',
	static function ( $text ) {
		if ( ! is_singular( 'tp_place' ) || has_excerpt() ) {
			return $text;
		}
		$id = get_queried_object_id();
		$m  = tp_place_meta( $id );
		return 'from' === $m['kind']
			/* translators: 1: place 2: phone */
			? sprintf( __( 'Wheelchair accessible taxi pick-ups from %1$s: where to wait, step-free access and how to book. Ramp and hoist vans. Call %2$s.', 'taxi-peninsula' ), get_the_title( $id ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) )
			/* translators: 1: place 2: phone */
			: sprintf( __( 'Wheelchair accessible taxi to %1$s: the accessible entrance, where we drop you off, parking and how to book. Ramp and hoist vans. Call %2$s.', 'taxi-peninsula' ), get_the_title( $id ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) );
	}
);

add_filter(
	'tp_breadcrumb_extra',
	static function ( $extra ) {
		if ( is_singular( 'tp_place' ) ) {
			$list = tp_page_url_by_template( 'places' );
			if ( $list ) {
				$extra[] = array( __( 'Places we go', 'taxi-peninsula' ), $list );
			}
			$extra[] = array( tp_place_title( get_queried_object_id() ), get_permalink() );
		}
		return $extra;
	}
);

// Custom "quick answer" box on the edit screen.
add_action(
	'add_meta_boxes_tp_place',
	static function () {
		add_meta_box( 'tp_place_box', __( 'Place details', 'taxi-peninsula' ), 'tp_render_place_box', 'tp_place', 'normal', 'high' );
		add_meta_box( 'tp_quick_answer', __( 'Quick answer (for Google and AI assistants)', 'taxi-peninsula' ), 'tp_render_quick_answer_box', 'tp_place', 'normal', 'high' );
	}
);

function tp_place_quick_answer( $id ) {
	$custom = trim( (string) get_post_meta( $id, '_tp_quick_answer', true ) );
	if ( '' !== $custom ) {
		return $custom;
	}
	$m    = tp_place_meta( $id );
	$name = get_the_title( $id );
	$site = get_bloginfo( 'name' );
	$a    = 'from' === $m['kind']
		/* translators: 1: business 2: place */
		? sprintf( __( '%1$s picks up wheelchair users from %2$s in ramp and hoist vans, with the driver meeting you at the pick-up point and helping you on board.', 'taxi-peninsula' ), $site, $name )
		/* translators: 1: business 2: place */
		: sprintf( __( '%1$s takes wheelchair users to %2$s in ramp and hoist vans and drops you at the accessible entrance, with help from the driver.', 'taxi-peninsula' ), $site, $name );
	$b = $m['spot'] ? ( 'from' === $m['kind'] ? __( 'Pick-up point:', 'taxi-peninsula' ) : __( 'Drop-off point:', 'taxi-peninsula' ) ) . ' ' . rtrim( $m['spot'], '. ' ) . '.' : '';
	/* translators: %s: phone */
	$c = sprintf( __( 'Book online in about two minutes or call %s; carers travel with you and MPTP, NDIS and aged-care accounts are accepted.', 'taxi-peninsula' ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) );
	return trim( $a . ' ' . $b . ' ' . $c );
}

function tp_render_place_box( $post ) {
	$m = tp_place_meta( $post->ID );
	wp_nonce_field( 'tp_place_save', 'tp_place_nonce' );
	$row = static function ( $key, $label, $type = 'text', $help = '' ) use ( $m ) {
		printf( '<tr><th><label for="tp_p_%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
		if ( 'textarea' === $type ) {
			printf( '<textarea class="large-text" rows="3" id="tp_p_%1$s" name="tp_p[%1$s]">%2$s</textarea>', esc_attr( $key ), esc_textarea( $m[ $key ] ) );
		} else {
			printf( '<input type="%3$s" class="regular-text" id="tp_p_%1$s" name="tp_p[%1$s]" value="%2$s"%4$s>', esc_attr( $key ), esc_attr( $m[ $key ] ), esc_attr( $type ), 'number' === $type ? ' step="any"' : '' );
		}
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	?>
	<p class="description"><?php esc_html_e( 'The title is the place name, e.g. "Frankston Hospital". The page heading becomes "Wheelchair taxi to Frankston Hospital" (or "from" for stations and shopping centres). Use the editor for anything else worth knowing, and the featured image for a real photo of the entrance.', 'taxi-peninsula' ); ?></p>
	<?php if ( false !== strpos( $m['tips'] . $m['spot'] . $m['entrance'] . $m['address'], '[CONFIRM' ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'This suggestion includes [CONFIRM] notes. Check each detail on site (or with the venue) and remove the notes before publishing.', 'taxi-peninsula' ); ?></p></div>
	<?php endif; ?>
	<table class="form-table" role="presentation">
		<tr><th><label for="tp_p_type"><?php esc_html_e( 'Type', 'taxi-peninsula' ); ?></label></th><td>
			<select id="tp_p_type" name="tp_p[type]">
				<?php foreach ( tp_place_types() as $k => $t ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m['type'], $k ); ?>><?php echo esc_html( $t[0] . ' — ' . ( 'from' === $t[1] ? __( 'pick-ups from', 'taxi-peninsula' ) : __( 'trips to', 'taxi-peninsula' ) ) ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
		<?php
		$row( 'address', __( 'Street address', 'taxi-peninsula' ), 'text', __( 'Filled in on the booking form.', 'taxi-peninsula' ) );
		$row( 'area', __( 'Suburb', 'taxi-peninsula' ), 'text', __( 'Links the page to that suburb’s page.', 'taxi-peninsula' ) );
		$row( 'lat', __( 'Latitude', 'taxi-peninsula' ), 'number', __( 'Optional, e.g. -38.151. Used for travel times from nearby suburbs.', 'taxi-peninsula' ) );
		$row( 'lng', __( 'Longitude', 'taxi-peninsula' ), 'number' );
		$row( 'entrance', __( 'Accessible entrance', 'taxi-peninsula' ), 'textarea', __( 'e.g. "Main entrance on Hastings Rd — level access, automatic doors, wheelchairs to borrow at reception."', 'taxi-peninsula' ) );
		$row( 'spot', __( 'Where we drop off / pick up', 'taxi-peninsula' ), 'textarea', __( 'The exact spot drivers use, e.g. "Set-down bay outside the main entrance (2 minutes)".', 'taxi-peninsula' ) );
		$row( 'parking', __( 'Parking & waiting', 'taxi-peninsula' ), 'textarea' );
		$row( 'tips', __( 'Tips for passengers', 'taxi-peninsula' ), 'textarea', __( 'One per line.', 'taxi-peninsula' ) );
		$row( 'official', __( 'Official website', 'taxi-peninsula' ), 'url', __( 'For visiting hours and maps.', 'taxi-peninsula' ) );
		?>
	</table>
	<?php
}

add_action(
	'save_post_tp_place',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_place_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_place_nonce'] ), 'tp_place_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['tp_p'] ) && is_array( $_POST['tp_p'] ) ? wp_unslash( $_POST['tp_p'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		update_post_meta( $post_id, '_tp_p_type', isset( tp_place_types()[ $in['type'] ?? '' ] ) ? $in['type'] : 'hospital' );
		foreach ( array( 'address', 'area' ) as $k ) {
			update_post_meta( $post_id, '_tp_p_' . $k, sanitize_text_field( $in[ $k ] ?? '' ) );
		}
		foreach ( array( 'lat', 'lng' ) as $k ) {
			$v = trim( (string) ( $in[ $k ] ?? '' ) );
			update_post_meta( $post_id, '_tp_p_' . $k, is_numeric( $v ) ? (string) round( (float) $v, 6 ) : '' );
		}
		foreach ( array( 'entrance', 'spot', 'parking', 'tips' ) as $k ) {
			update_post_meta( $post_id, '_tp_p_' . $k, sanitize_textarea_field( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_tp_p_official', esc_url_raw( $in['official'] ?? '' ) );
	}
);

// Never publish a page that still has [CONFIRM] notes in its details.
add_filter(
	'wp_insert_post_data',
	static function ( $data, $postarr ) {
		if ( 'tp_place' !== $data['post_type'] || 'publish' !== $data['post_status'] || empty( $postarr['tp_p'] ) ) {
			return $data;
		}
		if ( false !== strpos( wp_json_encode( $postarr['tp_p'] ) . $data['post_content'], '[CONFIRM' ) ) {
			$data['post_status'] = 'draft';
			add_filter( 'redirect_post_location', static fn( $loc ) => add_query_arg( 'tp_confirm_left', 1, $loc ) );
		}
		return $data;
	},
	10,
	2
);
add_action(
	'admin_notices',
	static function () {
		if ( ! empty( $_GET['tp_confirm_left'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Saved as a draft: remove every [CONFIRM] note once you have checked the details, then publish.', 'taxi-peninsula' ) . '</p></div>';
		}
	}
);

/* -------------------------------------------------------------------------
 * List columns, starter suggestions and import from Popular destinations.
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_tp_place_posts_columns',
	static function ( $cols ) {
		return array_slice( $cols, 0, 2, true ) + array( 'tp_type' => __( 'Type', 'taxi-peninsula' ), 'tp_url' => __( 'Page address', 'taxi-peninsula' ) ) + array_slice( $cols, 2, null, true );
	}
);
add_action(
	'manage_tp_place_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'tp_type' === $col ) {
			echo esc_html( tp_place_types()[ tp_place_meta( $id )['type'] ][0] );
		} elseif ( 'tp_url' === $col ) {
			echo '<code>/wheelchair-taxi-' . esc_html( tp_place_kind( $id ) . '/' . get_post_field( 'post_name', $id ) ) . '/</code>';
		}
	},
	10,
	2
);

/**
 * Starter suggestions — saved as drafts with [CONFIRM] notes.
 */
function tp_place_suggestions() {
	$c = ' ' . __( '[CONFIRM on site]', 'taxi-peninsula' );
	return array(
		array( 'Frankston Hospital', 'hospital', '2 Hastings Road, Frankston VIC 3199', 'Frankston', -38.151, 145.131, __( 'Main entrance on Hastings Road, with level access and automatic doors.', 'taxi-peninsula' ) . $c, __( 'Set-down bay in front of the main entrance.', 'taxi-peninsula' ) . $c, 'https://www.peninsulahealth.org.au/' ),
		array( 'Rosebud Hospital', 'hospital', '1527 Point Nepean Road, Rosebud VIC 3939', 'Rosebud', -38.360, 144.905, __( 'Main entrance off Point Nepean Road.', 'taxi-peninsula' ) . $c, __( 'Drop-off zone at the main entrance.', 'taxi-peninsula' ) . $c, 'https://www.peninsulahealth.org.au/' ),
		array( 'Monash Medical Centre Clayton', 'hospital', '246 Clayton Road, Clayton VIC 3168', 'Clayton', -37.921, 145.123, __( 'Main entrance on Clayton Road.', 'taxi-peninsula' ) . $c, __( 'Taxi set-down at the main entrance.', 'taxi-peninsula' ) . $c, 'https://monashhealth.org/' ),
		array( 'The Alfred', 'hospital', '55 Commercial Road, Melbourne VIC 3004', 'Melbourne', -37.846, 144.982, __( 'Main entrance on Commercial Road.', 'taxi-peninsula' ) . $c, __( 'Set-down area outside the main entrance.', 'taxi-peninsula' ) . $c, 'https://www.alfredhealth.org.au/' ),
		array( 'Melbourne Airport', 'airport', 'Departure Drive, Melbourne Airport VIC 3045', 'Tullamarine', -37.670, 144.843, __( 'Every terminal has level access from the forecourt.', 'taxi-peninsula' ) . $c, __( 'Departures: the kerb outside your airline. Arrivals: we meet you at the arrivals hall and walk you to the van.', 'taxi-peninsula' ) . $c, 'https://www.melbourneairport.com.au/' ),
		array( 'Frankston Station', 'station', 'Young Street, Frankston VIC 3199', 'Frankston', -38.142, 145.126, __( 'Step-free access from the platforms to Young Street.', 'taxi-peninsula' ) . $c, __( 'The taxi rank and pick-up zone on Young Street, outside the station entrance.', 'taxi-peninsula' ) . $c, 'https://www.ptv.vic.gov.au/' ),
		array( 'Bayside Shopping Centre', 'shopping', '28 Beach Street, Frankston VIC 3199', 'Frankston', -38.143, 145.124, __( 'Ground-floor entrances with automatic doors.', 'taxi-peninsula' ) . $c, __( 'The taxi rank closest to the main entrance — tell us which entrance when you book.', 'taxi-peninsula' ) . $c, '' ),
		array( 'Karingal Hub', 'shopping', '330 Cranbourne Road, Frankston VIC 3199', 'Frankston', -38.150, 145.160, __( 'Level entrances from the car park.', 'taxi-peninsula' ) . $c, __( 'The taxi rank near the main entrance.', 'taxi-peninsula' ) . $c, '' ),
		array( 'Westfield Southland', 'shopping', '1239 Nepean Highway, Cheltenham VIC 3192', 'Cheltenham', -37.958, 145.054, __( 'Accessible entrances on every level from the car park.', 'taxi-peninsula' ) . $c, __( 'The taxi rank on the ground floor.', 'taxi-peninsula' ) . $c, '' ),
	);
}

function tp_place_create_draft( $name, $type, $meta = array() ) {
	$existing = get_posts( array( 'post_type' => 'tp_place', 'post_status' => 'any', 'title' => $name, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return 0;
	}
	$id = wp_insert_post( array( 'post_type' => 'tp_place', 'post_status' => 'draft', 'post_title' => $name ) );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_tp_p_type', $type );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, '_tp_p_' . $k, $v );
	}
	return $id;
}

add_action(
	'admin_post_tp_place_starters',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_place_starters' );
		$n = 0;
		if ( isset( $_POST['from_destinations'] ) && function_exists( 'tp_popular_destinations' ) ) {
			foreach ( tp_popular_destinations( 20 ) as $d ) {
				$type = preg_match( '/airport/i', $d['label'] ) ? 'airport' : ( preg_match( '/station/i', $d['label'] ) ? 'station' : ( preg_match( '/shopping|plaza|westfield|hub/i', $d['label'] ) ? 'shopping' : ( preg_match( '/clinic|medical|dialysis/i', $d['label'] ) ? 'clinic' : 'hospital' ) ) );
				$n   += tp_place_create_draft( $d['label'], $type, array( 'address' => $d['label'], 'spot' => __( '[CONFIRM] Where exactly do drivers drop off?', 'taxi-peninsula' ) ) ) ? 1 : 0;
			}
		} else {
			foreach ( tp_place_suggestions() as $s ) {
				$n += tp_place_create_draft( $s[0], $s[1], array( 'address' => $s[2], 'area' => $s[3], 'lat' => (string) $s[4], 'lng' => (string) $s[5], 'entrance' => $s[6], 'spot' => $s[7], 'official' => $s[8], 'tips' => __( '[CONFIRM] Add one or two tips from your drivers.', 'taxi-peninsula' ) ) ) ? 1 : 0;
			}
		}
		/* translators: %d: number */
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( 'success', sprintf( _n( '%d draft place page added.', '%d draft place pages added.', $n, 'taxi-peninsula' ), $n ) ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=tp_place&post_status=draft' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	static function () {
		$s = get_current_screen();
		if ( ! $s || 'edit-tp_place' !== $s->id ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'Place pages answer searches such as "wheelchair taxi to Frankston Hospital" or "accessible taxi from Frankston Station". Add the places your passengers go most — a real photo of the entrance and the exact drop-off spot make them far more useful than a generic page.', 'taxi-peninsula' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px">
				<input type="hidden" name="action" value="tp_place_starters">
				<?php wp_nonce_field( 'tp_place_starters' ); ?>
				<button class="button"><?php esc_html_e( 'Add suggested hospitals, stations and shopping centres (drafts)', 'taxi-peninsula' ); ?></button>
				<button class="button" name="from_destinations" value="1"><?php esc_html_e( 'Add drafts from Popular destinations', 'taxi-peninsula' ); ?></button>
			</form>
		</div>
		<?php
	}
);

/* -------------------------------------------------------------------------
 * Front end helpers.
 * ---------------------------------------------------------------------- */

/**
 * Nearby service suburbs with rough drive times: [ [name, minutes], ... ].
 */
function tp_place_travel_times( $id, $limit = 6 ) {
	$m = tp_place_meta( $id );
	if ( ! is_numeric( $m['lat'] ) || ! is_numeric( $m['lng'] ) ) {
		return array();
	}
	$known = tp_map_places();
	$out   = array();
	foreach ( tp_service_areas() as $area ) {
		$c = $known[ strtolower( $area ) ] ?? null;
		if ( ! $c ) {
			continue;
		}
		$dlat = deg2rad( $c[0] - (float) $m['lat'] );
		$dlng = deg2rad( $c[1] - (float) $m['lng'] );
		$a    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( (float) $m['lat'] ) ) * cos( deg2rad( $c[0] ) ) * sin( $dlng / 2 ) ** 2;
		$km   = 6371 * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		$out[] = array( $area, max( 5, (int) ( round( $km * 1.3 / 5 ) * 5 ) ), $km );
	}
	usort( $out, static fn( $x, $y ) => $x[2] <=> $y[2] );
	return array_slice( $out, 0, $limit );
}

function tp_place_faq( $id ) {
	$m    = tp_place_meta( $id );
	$name = get_the_title( $id );
	$faq  = array();
	if ( 'from' === $m['kind'] ) {
		/* translators: %s: place */
		$faq[] = array( sprintf( __( 'Where do I wait for my taxi at %s?', 'taxi-peninsula' ), $name ), $m['spot'] ?: __( 'Tell us where you will be when you book and the driver will meet you there. We text you when the driver is on the way and when they arrive.', 'taxi-peninsula' ) );
		/* translators: %s: place */
		$faq[] = array( sprintf( __( 'Can you pick me up from %s straight after my train or shopping?', 'taxi-peninsula' ), $name ), __( 'Yes. Book a time, or call us when you are ready. If your train is running late, just let us know and we will adjust the pick-up.', 'taxi-peninsula' ) );
	} else {
		/* translators: %s: place */
		$faq[] = array( sprintf( __( 'Where will you drop me off at %s?', 'taxi-peninsula' ), $name ), $m['spot'] ?: __( 'At the most accessible entrance, as close to the door as the driver can safely stop. Tell us your department or appointment location when you book.', 'taxi-peninsula' ) );
		/* translators: %s: place */
		$faq[] = array( sprintf( __( 'Can you wait or pick me up after my appointment at %s?', 'taxi-peninsula' ), $name ), __( 'Yes. Book a return trip with an approximate time, then call or text us when you are ready. Appointments often run late, so we keep return pick-ups flexible.', 'taxi-peninsula' ) );
	}
	if ( $m['entrance'] ) {
		/* translators: %s: place */
		$faq[] = array( sprintf( __( 'Is there step-free access at %s?', 'taxi-peninsula' ), $name ), $m['entrance'] );
	}
	return array_merge( $faq, tp_access_faq() );
}

add_filter(
	'tp_schema_graph',
	static function ( $graph ) {
		if ( ! is_singular( 'tp_place' ) ) {
			return $graph;
		}
		$id    = get_queried_object_id();
		$m     = tp_place_meta( $id );
		$place = array( '@type' => 'airport' === $m['type'] ? 'Airport' : ( 'hospital' === $m['type'] ? 'Hospital' : ( 'station' === $m['type'] ? 'TrainStation' : ( 'shopping' === $m['type'] ? 'ShoppingCenter' : 'Place' ) ) ), 'name' => get_the_title( $id ) );
		if ( $m['address'] ) {
			$place['address'] = $m['address'];
		}
		if ( is_numeric( $m['lat'] ) && is_numeric( $m['lng'] ) ) {
			$place['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $m['lat'], 'longitude' => (float) $m['lng'] );
		}
		if ( $m['official'] ) {
			$place['sameAs'] = $m['official'];
		}
		$graph[] = array(
			'@type'       => 'Service',
			'name'        => tp_place_title( $id ),
			'url'         => get_permalink( $id ),
			'serviceType' => __( 'Wheelchair accessible taxi', 'taxi-peninsula' ),
			'provider'    => array( '@id' => home_url( '/#business' ) ),
			'areaServed'  => $place,
		);
		return $graph;
	}
);

// Suburb pages list their place pages.
add_action(
	'tp_area_after_content',
	static function ( $area ) {
		$ids = get_posts( array( 'post_type' => 'tp_place', 'numberposts' => 12, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_tp_p_area', 'value' => $area->post_title ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $ids ) {
			return;
		}
		/* translators: %s: suburb */
		echo '<section class="place-links"><h2>' . esc_html( sprintf( __( 'Hospitals, stations and places in %s', 'taxi-peninsula' ), $area->post_title ) ) . '</h2><ul class="chips">';
		foreach ( $ids as $id ) {
			printf( '<li><a class="chip" href="%s">%s</a></li>', esc_url( get_permalink( $id ) ), esc_html( tp_place_title( $id ) ) );
		}
		echo '</ul></section>';
	},
	12
);

/* -------------------------------------------------------------------------
 * [tp_places] list and starter page.
 * ---------------------------------------------------------------------- */

add_shortcode(
	'tp_places',
	static function ( $atts ) {
		$atts   = shortcode_atts( array( 'kind' => '' ), $atts );
		$groups = array(
			'to'   => __( 'Hospitals, clinics, airports and venues', 'taxi-peninsula' ),
			'from' => __( 'Railway stations and shopping centres', 'taxi-peninsula' ),
		);
		$ids    = get_posts( array( 'post_type' => 'tp_place', 'numberposts' => 200, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
		ob_start();
		echo '<div class="tp-component places-list">';
		foreach ( $groups as $kind => $label ) {
			if ( $atts['kind'] && $atts['kind'] !== $kind ) {
				continue;
			}
			$mine = array_filter( $ids, static fn( $id ) => tp_place_kind( $id ) === $kind );
			if ( ! $mine ) {
				continue;
			}
			echo '<h2>' . esc_html( $label ) . '</h2><ul class="places-list__grid">';
			foreach ( $mine as $id ) {
				$m = tp_place_meta( $id );
				printf(
					'<li class="card places-list__item">%s<h3><a href="%s">%s</a></h3><p>%s</p></li>',
					has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'medium', array( 'loading' => 'lazy', 'alt' => '' ) ) : '',
					esc_url( get_permalink( $id ) ),
					esc_html( get_the_title( $id ) ),
					esc_html( $m['area'] ?: $m['address'] )
				);
			}
			echo '</ul>';
		}
		echo '</div>';
		return ob_get_clean();
	}
);

add_filter(
	'tp_feature_shortcodes',
	static function ( $c ) {
		$c['places'] = 'tp_places';
		return $c;
	}
);

add_filter(
	'tp_starter_pages',
	static function ( $p ) {
		$p['places'] = array(
			'title'   => __( 'Places We Go', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Accessible entrances, drop-off points and tips for the hospitals, stations and shopping centres our passengers travel to most.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_places]\n<!-- /wp:shortcode -->",
		);
		return $p;
	}
);

add_action(
	'tp_footer_area_links',
	static function () {
		$url = tp_page_url_by_template( 'places' );
		if ( $url ) {
			printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html__( 'Hospitals & stations', 'taxi-peninsula' ) );
		}
	}
);
