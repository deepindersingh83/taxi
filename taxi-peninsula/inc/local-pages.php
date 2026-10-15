<?php
/**
 * Generated local pages: one page for every service in every area,
 * e.g. /wheelchair-taxi/mornington/airport-transfers/ — "Airport transfers
 * from Mornington" with travel times, a map, the service's fares and
 * steps, local questions and a booking card with the suburb filled in.
 *
 * Pages are built from your Areas and Services, so they update themselves.
 * They are listed in the XML sitemap. Settings: Bookings → Settings →
 * Local pages (switch off, or keep them out of search engines).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_LOCAL_RW = 1;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['local'] = array( __( 'Local pages', 'taxi-peninsula' ), __( 'A page for each service in each area you cover, e.g. "Airport transfers from Mornington". Built from your Areas and Services.', 'taxi-peninsula' ) );
		return $s;
	}
);
add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['local_pages']       = array( 'local', __( 'Create local service pages', 'taxi-peninsula' ), 'checkbox', true, '' );
		$s['local_pages_index'] = array( 'local', __( 'Let search engines index them', 'taxi-peninsula' ), 'checkbox', true, __( 'Pages are listed in the sitemap. Add your own text to each area (Areas → edit) so the pages are genuinely useful — search engines can ignore pages that look the same.', 'taxi-peninsula' ) );
		return $s;
	}
);

function tp_local_enabled() {
	return (bool) tp_setting( 'local_pages' );
}

function tp_local_url( $area, $service ) {
	$a = is_object( $area ) ? $area->post_name : (string) $area;
	$s = is_object( $service ) ? $service->post_name : (string) $service;
	return home_url( user_trailingslashit( 'wheelchair-taxi/' . $a . '/' . $s ) );
}

add_action(
	'init',
	static function () {
		add_rewrite_rule( '^wheelchair-taxi/([^/]+)/([^/]+)/?$', 'index.php?tp_local_area=$matches[1]&tp_local_service=$matches[2]', 'top' );
		if ( (int) get_option( 'tp_local_rw' ) !== TP_LOCAL_RW ) {
			flush_rewrite_rules( false );
			update_option( 'tp_local_rw', TP_LOCAL_RW, false );
		}
	},
	20
);

add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'tp_local_area';
		$v[] = 'tp_local_service';
		return $v;
	}
);

// Keep the main query cheap for these requests.
add_filter(
	'request',
	static function ( $vars ) {
		if ( ! empty( $vars['tp_local_area'] ) && ! empty( $vars['tp_local_service'] ) ) {
			$vars['posts_per_page'] = 1;
			$vars['no_found_rows']  = true;
		}
		return $vars;
	}
);

/**
 * The area and service for this request: [WP_Post, WP_Post] or null.
 */
function tp_local_current() {
	static $cur = false;
	if ( false !== $cur ) {
		return $cur;
	}
	$cur = null;
	$a   = get_query_var( 'tp_local_area' );
	$s   = get_query_var( 'tp_local_service' );
	if ( ! $a || ! $s || ! tp_local_enabled() ) {
		return $cur;
	}
	$area    = get_page_by_path( sanitize_title( $a ), OBJECT, 'tp_area' );
	$service = get_page_by_path( sanitize_title( $s ), OBJECT, 'tp_service' );
	if ( $area && $service && 'publish' === $area->post_status && 'publish' === $service->post_status ) {
		$cur = array( $area, $service );
	}
	return $cur;
}

function tp_local_title( $area, $service ) {
	/* translators: 1: service, e.g. "Airport transfers" 2: suburb */
	return apply_filters( 'tp_local_title', sprintf( __( '%1$s from %2$s', 'taxi-peninsula' ), get_the_title( $service ), get_the_title( $area ) ), $area, $service );
}

add_action(
	'template_redirect',
	static function () {
		if ( ! get_query_var( 'tp_local_area' ) ) {
			return;
		}
		global $wp_query;
		if ( ! tp_local_current() ) {
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		$wp_query->is_home    = false;
		$wp_query->is_404     = false;
		$wp_query->is_archive = false;
		status_header( 200 );
	},
	0
);

add_filter(
	'template_include',
	static function ( $template ) {
		if ( tp_local_current() ) {
			$found = locate_template( 'local-service.php' );
			return $found ?: $template;
		}
		return $template;
	}
);

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		$cur = tp_local_current();
		if ( $cur ) {
			$parts['title'] = tp_local_title( $cur[0], $cur[1] );
		}
		return $parts;
	},
	20
);

add_filter(
	'tp_meta_description',
	static function ( $text ) {
		$cur = tp_local_current();
		if ( ! $cur ) {
			return $text;
		}
		/* translators: 1: page title 2: suburb 3: phone */
		return sprintf( __( '%1$s by wheelchair accessible taxi. Ramp and hoist vans, trained drivers, door-to-door from anywhere in %2$s. Book online or call %3$s.', 'taxi-peninsula' ), tp_local_title( $cur[0], $cur[1] ), get_the_title( $cur[0] ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) );
	}
);

add_action(
	'wp_head',
	static function () {
		$cur = tp_local_current();
		if ( ! $cur || ( function_exists( 'tp_seo_plugin_active' ) && tp_seo_plugin_active() ) ) {
			return;
		}
		printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( tp_local_url( $cur[0], $cur[1] ) ) );
	},
	3
);

add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( tp_local_current() && ! tp_setting( 'local_pages_index' ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

add_filter(
	'body_class',
	static function ( $c ) {
		if ( tp_local_current() ) {
			$c[] = 'local-service-page';
		}
		return $c;
	}
);

/**
 * Questions generated for an area + service, plus the area's own.
 *
 * @return array [ [question, answer], … ]
 */
function tp_local_faq( $area, $service ) {
	$a     = get_the_title( $area );
	$sname = get_the_title( $service );
	$times = tp_area_travel_times( $a );
	$faq   = array();
	$is_airport = false !== stripos( $service->post_name . ' ' . $sname, 'airport' );
	foreach ( $times as $t ) {
		if ( ( $is_airport && false !== stripos( $t[0], 'airport' ) ) || ( ! $is_airport && ! $faq ) ) {
			$faq[] = array(
				/* translators: 1: suburb 2: destination */
				sprintf( __( 'How long does it take from %1$s to %2$s?', 'taxi-peninsula' ), $a, $t[0] ),
				/* translators: %d: minutes */
				sprintf( __( 'About %d minutes without traffic. We add time for loading and securing your wheelchair, and for peak-hour traffic, when we plan your pick-up.', 'taxi-peninsula' ), $t[1] ),
			);
			break;
		}
	}
	$price = (float) get_post_meta( $service->ID, '_tp_from_price', true );
	$faq[] = array(
		/* translators: 1: service 2: suburb */
		sprintf( __( 'How much does it cost for %1$s from %2$s?', 'taxi-peninsula' ), strtolower( $sname ), $a ),
		$price
			/* translators: %s: price */
			? sprintf( __( 'Fares start from %s and depend on distance, time of day and waiting time. The booking form shows an estimate, or ask us for a fixed quote.', 'taxi-peninsula' ), '$' . number_format_i18n( $price, floor( $price ) == $price ? 0 : 2 ) )
			: __( 'Fares depend on distance, time of day and waiting time. The booking form shows an estimate, or ask us for a fixed quote.', 'taxi-peninsula' ),
	);
	$faq[] = array(
		/* translators: %s: suburb */
		sprintf( __( 'Can you pick me up from my home, a hospital or an aged-care home in %s?', 'taxi-peninsula' ), $a ),
		__( 'Yes. We pick up from any address — homes, hospitals, clinics, aged-care homes and day programs — and help you from the door to the vehicle.', 'taxi-peninsula' ),
	);
	if ( $is_airport ) {
		$faq[] = array(
			__( 'What if my flight is late?', 'taxi-peninsula' ),
			__( 'Add your flight number when you book. We watch the flight and move your pick-up if it lands early or late.', 'taxi-peninsula' ),
		);
	}
	return array_merge( $faq, tp_parse_local_faq( get_post_meta( $area->ID, '_tp_local_faq', true ) ) );
}

/**
 * Drop-off to suggest on the booking card for a service.
 */
function tp_local_suggested_dropoff( $service ) {
	$name = strtolower( $service->post_name . ' ' . $service->post_title );
	if ( false !== strpos( $name, 'airport' ) ) {
		return __( 'Melbourne Airport', 'taxi-peninsula' );
	}
	return '';
}

// Area pages link to their local service pages.
add_filter(
	'tp_area_service_url',
	static function ( $url, $area_id, $service_url ) {
		if ( ! tp_local_enabled() ) {
			return $url;
		}
		$service = url_to_postid( $service_url );
		return $service && 'tp_service' === get_post_type( $service ) ? tp_local_url( get_post( $area_id ), get_post( $service ) ) : $url;
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Sitemap.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
			return;
		}
		if ( ! class_exists( 'TP_Local_Sitemap' ) ) {
			/**
			 * Sitemap of generated local pages.
			 */
			class TP_Local_Sitemap extends WP_Sitemaps_Provider { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
				public function __construct() {
					$this->name        = 'tplocal';
					$this->object_type = 'tplocal';
				}
				public function get_url_list( $page_num, $object_subtype = '' ) {
					if ( ! tp_local_enabled() || ! tp_setting( 'local_pages_index' ) ) {
						return array();
					}
					$out      = array();
					$services = get_posts( array( 'post_type' => 'tp_service', 'numberposts' => 30 ) );
					foreach ( get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 200 ) ) as $area ) {
						foreach ( $services as $s ) {
							$out[] = array( 'loc' => tp_local_url( $area, $s ) );
						}
					}
					return array_slice( $out, ( $page_num - 1 ) * 2000, 2000 );
				}
				public function get_max_num_pages( $object_subtype = '' ) {
					return 1;
				}
			}
		}
		wp_register_sitemap_provider( 'tplocal', new TP_Local_Sitemap() );
	},
	11
);
