<?php
/**
 * Route pages: "Wheelchair taxi from Frankston to Monash Medical Centre".
 *
 * Every place page gets one route page for each of its nearest suburbs:
 *   /wheelchair-taxi-to/{place}/from-{suburb}/
 *   /wheelchair-taxi-from/{place}/to-{suburb}/   (stations, shopping centres)
 *
 * Each has its own numbers (distance, travel time, a fare range from your
 * rates), the drop-off or pick-up details of the place, a booking card,
 * questions and links to the other routes. Only the nearest suburbs get a
 * page, so search engines see useful pages rather than thousands of copies.
 * They are in the XML sitemap; switch them off in Settings → Marketing & SEO
 * → Local pages.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_ROUTES_RW    = 1;
const TP_ROUTES_EACH  = 8;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['route_pages'] = array( 'local', __( 'Create route pages (suburb → hospital, station…)', 'taxi-peninsula' ), 'checkbox', true, __( 'e.g. “Wheelchair taxi from Frankston to Monash Medical Centre”, for the 8 nearest suburbs of each place page that has a map position.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_action(
	'init',
	static function () {
		add_rewrite_rule( '^wheelchair-taxi-(to|from)/([^/]+)/(from|to)-([^/]+)/?$', 'index.php?tp_place=$matches[2]&tp_place_kind=$matches[1]&tp_route=$matches[4]', 'top' );
		if ( (int) get_option( 'tp_routes_rw' ) !== TP_ROUTES_RW ) {
			flush_rewrite_rules( false );
			update_option( 'tp_routes_rw', TP_ROUTES_RW, false );
		}
	},
	22
);

add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'tp_route';
		return $v;
	}
);

/**
 * The suburbs a place has route pages for: [ name, minutes, straight km ].
 */
function tp_place_routes( $place_id ) {
	return tp_setting( 'route_pages' ) ? tp_place_travel_times( $place_id, TP_ROUTES_EACH ) : array();
}

function tp_route_url( $place_id, $area ) {
	$kind = tp_place_kind( $place_id );
	return home_url( user_trailingslashit( 'wheelchair-taxi-' . $kind . '/' . get_post_field( 'post_name', $place_id ) . '/' . ( 'from' === $kind ? 'to-' : 'from-' ) . sanitize_title( $area ) ) );
}

/**
 * The route for this request: [ place ID, suburb, minutes, road km ] or null.
 */
function tp_route_current() {
	static $cur = false;
	if ( false !== $cur ) {
		return $cur;
	}
	$cur  = null;
	$slug = get_query_var( 'tp_route' );
	if ( ! $slug || ! is_singular( 'tp_place' ) ) {
		return $cur;
	}
	$id = get_queried_object_id();
	foreach ( tp_place_routes( $id ) as $r ) {
		if ( sanitize_title( $r[0] ) === $slug ) {
			$cur = array( $id, $r[0], $r[1], round( $r[2] * 1.3, 1 ) );
		}
	}
	return $cur;
}

/** Fare range text for a route, or ''. */
function tp_route_fare( array $route ) {
	if ( ! function_exists( 'tp_fare_from_distance' ) ) {
		return '';
	}
	$m    = tp_place_meta( $route[0] );
	$fare = tp_fare_from_distance( $route[3], $route[2], '', '', $route[1], get_the_title( $route[0] ) . ' ' . $m['address'] );
	return $fare ? tp_fare_text( $fare[0], $fare[1] ) : '';
}

function tp_route_title( array $route ) {
	$place = get_the_title( $route[0] );
	return 'from' === tp_place_kind( $route[0] )
		/* translators: 1: place, e.g. Frankston Station 2: suburb */
		? sprintf( __( 'Wheelchair taxi from %1$s to %2$s', 'taxi-peninsula' ), $place, $route[1] )
		/* translators: 1: suburb 2: place, e.g. Frankston Hospital */
		: sprintf( __( 'Wheelchair taxi from %1$s to %2$s', 'taxi-peninsula' ), $route[1], $place );
}

// Unknown suburb for this place: send people to the place page.
add_action(
	'template_redirect',
	static function () {
		if ( ! get_query_var( 'tp_route' ) || ! is_singular( 'tp_place' ) ) {
			return;
		}
		if ( ! tp_route_current() ) {
			wp_safe_redirect( get_permalink( get_queried_object_id() ), 301 );
			exit;
		}
	},
	1
);

add_filter(
	'template_include',
	static function ( $template ) {
		if ( tp_route_current() ) {
			$found = locate_template( 'place-route.php' );
			return $found ?: $template;
		}
		return $template;
	}
);

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		$r = tp_route_current();
		if ( $r ) {
			$parts['title'] = tp_route_title( $r );
		}
		return $parts;
	},
	25
);

add_filter(
	'tp_meta_description',
	static function ( $text ) {
		$r = tp_route_current();
		if ( ! $r ) {
			return $text;
		}
		$fare = tp_route_fare( $r );
		/* translators: 1: route title 2: minutes 3: km */
		$out = sprintf( __( '%1$s: about %2$d minutes and %3$s km.', 'taxi-peninsula' ), tp_route_title( $r ), $r[2], number_format_i18n( $r[3], 1 ) );
		if ( $fare ) {
			/* translators: %s: fare range */
			$out .= ' ' . sprintf( __( 'Fare about %s.', 'taxi-peninsula' ), $fare );
		}
		/* translators: %s: phone */
		return $out . ' ' . sprintf( __( 'Ramp and hoist vans, door to door. Book online or call %s.', 'taxi-peninsula' ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) );
	},
	20
);

add_filter(
	'tp_canonical_url',
	static function ( $url ) {
		$r = tp_route_current();
		return $r ? tp_route_url( $r[0], $r[1] ) : $url;
	}
);

add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( tp_route_current() && ! tp_setting( 'local_pages_index' ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

add_filter(
	'tp_breadcrumb_extra',
	static function ( $extra ) {
		$r = tp_route_current();
		if ( $r ) {
			$extra[] = array( tp_route_title( $r ), tp_route_url( $r[0], $r[1] ) );
		}
		return $extra;
	},
	20
);

/**
 * Questions for a route page.
 *
 * @return array [ [question, answer], … ]
 */
function tp_route_faq( array $route ) {
	$place = get_the_title( $route[0] );
	$area  = $route[1];
	$m     = tp_place_meta( $route[0] );
	$fare  = tp_route_fare( $route );
	$phone = wp_strip_all_tags( tp_opt( 'phone_display' ) );
	$from  = 'from' === $m['kind'];
	$a     = $from ? $place : $area;
	$b     = $from ? $area : $place;
	$out   = array();
	$out[] = array(
		/* translators: 1: from 2: to */
		sprintf( __( 'How much is a wheelchair taxi from %1$s to %2$s?', 'taxi-peninsula' ), $a, $b ),
		$fare
			/* translators: 1: fare range 2: phone */
			? sprintf( __( 'Usually about %1$s for a wheelchair accessible taxi, depending on the exact address, traffic and time of day. MPTP members pay half (up to the subsidy limit), and NDIS and aged-care accounts can be invoiced. Call %2$s for a firm price.', 'taxi-peninsula' ), $fare, $phone )
			/* translators: %s: phone */
			: sprintf( __( 'It depends on the exact address and time of day — call %s for a price. MPTP members pay half (up to the subsidy limit), and NDIS and aged-care accounts can be invoiced.', 'taxi-peninsula' ), $phone ),
	);
	$out[] = array(
		/* translators: 1: from 2: to */
		sprintf( __( 'How long does the trip from %1$s to %2$s take?', 'taxi-peninsula' ), $a, $b ),
		/* translators: 1: minutes 2: km */
		sprintf( __( 'About %1$d minutes for the %2$s km without traffic. We allow a few extra minutes to load and secure your wheelchair.', 'taxi-peninsula' ), $route[2], number_format_i18n( $route[3], 1 ) ),
	);
	if ( $m['spot'] ) {
		$out[] = array(
			/* translators: %s: place */
			$from ? sprintf( __( 'Where do you pick up at %s?', 'taxi-peninsula' ), $place ) : sprintf( __( 'Where do you drop off at %s?', 'taxi-peninsula' ), $place ),
			$m['spot'],
		);
	}
	if ( ! $from && tp_place_is_medical( $m ) ) {
		$out[] = array(
			/* translators: %s: area */
			sprintf( __( 'Can you take me home to %s when I’m discharged?', 'taxi-peninsula' ), $area ),
			__( 'Yes. Book a “pick up when ready” trip: choose the day, then tap “I’m ready” on your booking page or call us when the ward lets you go, and we send the driver.', 'taxi-peninsula' ),
		);
	}
	$out[] = array(
		__( 'Can a carer or family member come too?', 'taxi-peninsula' ),
		__( 'Yes. Tell us how many people are travelling when you book; carers travel at no extra charge.', 'taxi-peninsula' ),
	);
	return $out;
}

/** The area page for a suburb name, or ''. */
function tp_route_area_url( $name ) {
	$ids = get_posts( array( 'post_type' => 'tp_area', 'post_status' => 'publish', 'name' => sanitize_title( $name ), 'numberposts' => 1, 'fields' => 'ids' ) );
	return $ids ? get_permalink( $ids[0] ) : '';
}

/**
 * Routes from a suburb to other places (for the area page and route pages).
 *
 * @return array [ [url, title, minutes], … ]
 */
function tp_routes_for_area( $name, $except = 0, $limit = 8 ) {
	if ( ! tp_setting( 'route_pages' ) ) {
		return array();
	}
	$key = 'tp_routes_area_' . md5( strtolower( $name ) . '|' . $limit );
	$out = get_transient( $key );
	if ( ! is_array( $out ) ) {
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'tp_place', 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids' ) ) as $pid ) {
			foreach ( tp_place_routes( $pid ) as $r ) {
				if ( 0 === strcasecmp( $r[0], $name ) ) {
					$out[] = array( $pid, $r[1] );
				}
			}
		}
		usort( $out, static fn( $x, $y ) => $x[1] <=> $y[1] );
		$out = array_slice( $out, 0, $limit + 1 );
		set_transient( $key, $out, DAY_IN_SECONDS );
	}
	$list = array();
	foreach ( $out as $o ) {
		if ( (int) $o[0] !== (int) $except && 'publish' === get_post_status( $o[0] ) ) {
			$list[] = array( tp_route_url( $o[0], $name ), tp_route_title( array( $o[0], $name, $o[1], 0 ) ), $o[1] );
		}
	}
	return array_slice( $list, 0, $limit );
}

// Lists change when place pages change.
add_action(
	'save_post_tp_place',
	static function () {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_tp\_routes\_area\_%' OR option_name LIKE '\_transient\_timeout\_tp\_routes\_area\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
);

// Area pages: "Popular trips from Frankston".
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular( 'tp_area' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		static $done = false;
		if ( $done ) {
			return $content;
		}
		$routes = tp_routes_for_area( get_the_title() );
		if ( ! $routes ) {
			return $content;
		}
		$done = true;
		/* translators: %s: suburb */
		$html = '<section class="route-links"><h2>' . esc_html( sprintf( __( 'Popular trips from %s', 'taxi-peninsula' ), get_the_title() ) ) . '</h2><ul class="route-links__list">';
		foreach ( $routes as $r ) {
			/* translators: %d: minutes */
			$html .= '<li><a href="' . esc_url( $r[0] ) . '">' . esc_html( $r[1] ) . '</a> <span>' . esc_html( sprintf( __( 'about %d min', 'taxi-peninsula' ), $r[2] ) ) . '</span></li>';
		}
		return $content . $html . '</ul></section>';
	},
	30
);

// Sitemap.
add_action(
	'init',
	static function () {
		if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
			return;
		}
		if ( ! class_exists( 'TP_Route_Sitemap' ) ) {
			/**
			 * Sitemap of route pages.
			 */
			class TP_Route_Sitemap extends WP_Sitemaps_Provider { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
				public function __construct() {
					$this->name        = 'tproutes';
					$this->object_type = 'tproutes';
				}
				public function get_url_list( $page_num, $object_subtype = '' ) {
					if ( ! tp_setting( 'route_pages' ) || ! tp_setting( 'local_pages_index' ) ) {
						return array();
					}
					$out = array();
					foreach ( get_posts( array( 'post_type' => 'tp_place', 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids' ) ) as $pid ) {
						foreach ( tp_place_routes( $pid ) as $r ) {
							$out[] = array( 'loc' => tp_route_url( $pid, $r[0] ) );
						}
					}
					return array_slice( $out, ( $page_num - 1 ) * 2000, 2000 );
				}
				public function get_max_num_pages( $object_subtype = '' ) {
					return 1;
				}
			}
		}
		wp_register_sitemap_provider( 'tproutes', new TP_Route_Sitemap() );
	},
	11
);
