<?php
/**
 * Working with Rank Math and Yoast SEO.
 *
 * When an SEO plugin is active it writes the titles, descriptions, canonical
 * links and sitemap, and WordPress's own sitemap is switched off. Without
 * help, those plugins don't know the pages this theme builds itself (suburb
 * + service pages, route pages, council pages): they gave them the blog's
 * canonical link and title, and left them out of the sitemap, so search
 * engines ignored them. This file:
 *
 * - gives the plugin the right canonical, title and description for those
 *   pages;
 * - uses the theme's keyword titles and descriptions for the home page,
 *   suburbs, places and services, unless you wrote your own in the plugin;
 * - adds a sitemap of the generated pages (/tp-sitemap.xml) to the plugin's
 *   sitemap index;
 * - refreshes the site's address rules after every plugin has added its own,
 *   and repairs the plugin's sitemap address if it goes missing.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['seo_home_title'] = array( 'search', __( 'Home page title in Google', 'taxi-peninsula' ), 'text', __( 'Taxi & Wheelchair Accessible Taxi — Mornington Peninsula & Melbourne', 'taxi-peninsula' ), __( 'Your business name is added at the end. Put the words people search for first. A title written in Rank Math or Yoast (edit the home page) takes priority.', 'taxi-peninsula' ) );
		$s['seo_area_title'] = array( 'search', __( 'Suburb page title in Google', 'taxi-peninsula' ), 'text', __( '%s Taxi & Wheelchair Accessible Taxi', 'taxi-peninsula' ), __( '%s is the suburb, e.g. “Frankston Taxi & Wheelchair Accessible Taxi”. Many more people search “Frankston taxi” than “wheelchair taxi Frankston”, so naming both brings more visitors.', 'taxi-peninsula' ) );
		return $s;
	}
);

/* =========================================================================
 * Titles and descriptions the theme would use.
 * ====================================================================== */

/** The address of a page this theme builds itself, or ''. */
function tp_generated_view_url() {
	if ( function_exists( 'tp_route_current' ) && tp_route_current() ) {
		$r = tp_route_current();
		return tp_route_url( $r[0], $r[1] );
	}
	if ( function_exists( 'tp_local_current' ) && tp_local_current() ) {
		$c = tp_local_current();
		return tp_local_url( $c[0], $c[1] );
	}
	if ( function_exists( 'tp_council_current' ) && tp_council_current() ) {
		$c = tp_council_current();
		return tp_council_url( '_all' === $c[0] ? '' : $c[0] );
	}
	return '';
}

/** Views where the theme knows a better title and description than the plugin's default. */
function tp_theme_seo_view() {
	return is_front_page() || is_singular( array( 'tp_area', 'tp_place', 'tp_service', 'tp_term', 'tp_event' ) ) || is_post_type_archive( array( 'tp_area', 'tp_service', 'tp_term' ) ) || '' !== tp_generated_view_url();
}

/** Did someone write a title / description for this page in the SEO plugin? */
function tp_seo_custom( $field ) {
	if ( ! is_singular() && ! ( is_front_page() && get_option( 'page_on_front' ) ) ) {
		return false;
	}
	$id   = is_front_page() && get_option( 'page_on_front' ) ? (int) get_option( 'page_on_front' ) : get_queried_object_id();
	$keys = 'title' === $field ? array( 'rank_math_title', '_yoast_wpseo_title' ) : array( 'rank_math_description', '_yoast_wpseo_metadesc' );
	foreach ( $keys as $k ) {
		if ( '' !== trim( (string) get_post_meta( $id, $k, true ) ) ) {
			return true;
		}
	}
	return false;
}

/** "Frankston Taxi & Wheelchair Accessible Taxi | Taxi Peninsula". */
function tp_theme_title() {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	if ( is_front_page() ) {
		$home = trim( (string) tp_setting( 'seo_home_title' ) );
		return $home ? $home . ' | ' . $site : $site;
	}
	// Seeded without wp_get_document_title(): the SEO plugin filters that, which would call back here.
	$parts = array( 'title' => is_singular() ? single_post_title( '', false ) : ( is_post_type_archive() ? post_type_archive_title( '', false ) : '' ) );
	/** This filter is documented in wp-includes/general-template.php */
	$parts = apply_filters( 'document_title_parts', $parts );
	return trim( wp_strip_all_tags( $parts['title'] ) ) . ' | ' . $site;
}

// Suburb pages: the title pattern from the settings (also without an SEO plugin).
add_filter(
	'document_title_parts',
	static function ( $parts ) {
		$pattern = (string) tp_setting( 'seo_area_title' );
		if ( is_singular( 'tp_area' ) && false !== strpos( $pattern, '%s' ) ) {
			$parts['title'] = sprintf( $pattern, single_post_title( '', false ) );
		}
		return $parts;
	},
	15
);

// Home page title without an SEO plugin.
add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_front_page() && ! tp_seo_plugin_active() && trim( (string) tp_setting( 'seo_home_title' ) ) ) {
			$parts = array( 'title' => trim( (string) tp_setting( 'seo_home_title' ) ), 'site' => get_bloginfo( 'name' ) );
		}
		return $parts;
	},
	30
);

/* =========================================================================
 * Rank Math and Yoast.
 * ====================================================================== */

$tp_seo_title = static function ( $title ) {
	if ( ! tp_theme_seo_view() || ( '' === tp_generated_view_url() && tp_seo_custom( 'title' ) ) ) {
		return $title;
	}
	return tp_theme_title();
};
$tp_seo_desc  = static function ( $desc ) {
	if ( ! tp_theme_seo_view() || ( '' === tp_generated_view_url() && tp_seo_custom( 'description' ) ) ) {
		return $desc;
	}
	$mine = function_exists( 'tp_meta_description' ) ? trim( (string) tp_meta_description() ) : '';
	return '' !== $mine ? $mine : $desc;
};
$tp_seo_url   = static function ( $url ) {
	$mine = tp_generated_view_url();
	return '' !== $mine ? $mine : $url;
};

foreach ( array( 'rank_math/frontend/title', 'rank_math/opengraph/facebook/og_title', 'wpseo_title', 'wpseo_opengraph_title' ) as $tp_hook ) {
	add_filter( $tp_hook, $tp_seo_title, 20 );
}
foreach ( array( 'rank_math/frontend/description', 'rank_math/opengraph/facebook/og_description', 'wpseo_metadesc', 'wpseo_opengraph_desc' ) as $tp_hook ) {
	add_filter( $tp_hook, $tp_seo_desc, 20 );
}
foreach ( array( 'rank_math/frontend/canonical', 'rank_math/opengraph/url', 'wpseo_canonical', 'wpseo_opengraph_url' ) as $tp_hook ) {
	add_filter( $tp_hook, $tp_seo_url, 20 );
}

// Generated pages are never "the blog": no "noindex" the plugin may have set for that view.
add_filter(
	'rank_math/frontend/robots',
	static function ( $robots ) {
		if ( '' !== tp_generated_view_url() ) {
			$robots['index'] = 'index';
			unset( $robots['noindex'] );
		}
		return $robots;
	},
	20
);

/* =========================================================================
 * Sitemap of the generated pages, for SEO plugins.
 * ====================================================================== */

/** Every generated page address: [ url, … ]. */
function tp_generated_urls() {
	$out = array();
	foreach ( array( 'TP_Local_Sitemap', 'TP_Route_Sitemap', 'TP_Council_Sitemap' ) as $class ) {
		if ( class_exists( $class ) ) {
			foreach ( ( new $class() )->get_url_list( 1 ) as $row ) {
				$out[] = $row['loc'];
			}
		}
	}
	return array_values( array_unique( $out ) );
}

function tp_generated_sitemap_url() {
	return home_url( '/tp-sitemap.xml' );
}

add_action(
	'parse_request',
	static function ( $wp ) {
		if ( 'tp-sitemap.xml' !== $wp->request ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( tp_generated_urls() as $u ) {
			echo "\t<url><loc>" . esc_url( $u ) . "</loc></url>\n";
		}
		echo '</urlset>' . "\n";
		exit;
	},
	0
);

$tp_sitemap_entry = static function ( $xml ) {
	return $xml . "\t<sitemap>\n\t\t<loc>" . esc_url( tp_generated_sitemap_url() ) . "</loc>\n\t\t<lastmod>" . esc_html( gmdate( 'c', (int) get_option( 'tp_generated_modified', time() ) ) ) . "</lastmod>\n\t</sitemap>\n";
};
add_filter( 'rank_math/sitemap/index', $tp_sitemap_entry );
add_filter( 'wpseo_sitemap_index', $tp_sitemap_entry );

// The "lastmod" date moves when the pages behind the generated ones change.
foreach ( array( 'save_post_tp_place', 'save_post_tp_area', 'save_post_tp_service' ) as $tp_hook ) {
	add_action(
		$tp_hook,
		static function () {
			update_option( 'tp_generated_modified', time(), false );
		}
	);
}

/** The sitemap address search engines are given (robots.txt). */
function tp_sitemap_index_url() {
	if ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ) {
		return home_url( '/sitemap_index.xml' );
	}
	return home_url( '/wp-sitemap.xml' );
}

/* =========================================================================
 * Address rules: refreshed once every plugin has added its own.
 * ====================================================================== */

/** Ask for the address rules to be rebuilt at the end of start-up. */
function tp_flush_rewrites_soon() {
	$GLOBALS['tp_flush_rewrites'] = true;
}

add_action(
	'wp_loaded',
	static function () {
		if ( ! empty( $GLOBALS['tp_flush_rewrites'] ) ) {
			flush_rewrite_rules( false );
			return;
		}
		// Self-repair: the SEO plugin's sitemap address is missing (it then shows "Page not found").
		if ( ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ) && ! get_transient( 'tp_sitemap_rule_check' ) ) {
			set_transient( 'tp_sitemap_rule_check', 1, HOUR_IN_SECONDS );
			$rules = (array) get_option( 'rewrite_rules', array() );
			$found = false;
			foreach ( array_keys( $rules ) as $k ) {
				if ( false !== strpos( $k, 'sitemap_index' ) || false !== strpos( $k, 'sitemap(-([0-9]+))?' ) || false !== strpos( $k, '_sitemap' ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found && $rules ) {
				flush_rewrite_rules( false );
			}
		}
	},
	99
);

/* =========================================================================
 * Checks (Settings → Marketing & SEO → Test & troubleshoot).
 * ====================================================================== */

/**
 * Fetch the sitemap index and the generated sitemap: [ status, message ].
 */
function tp_sitemap_health() {
	$cached = get_transient( 'tp_sitemap_health' );
	if ( is_array( $cached ) && ! isset( $_GET['recheck'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		return $cached;
	}
	$out = tp_sitemap_health_now();
	set_transient( 'tp_sitemap_health', $out, 10 * MINUTE_IN_SECONDS );
	return $out;
}

function tp_sitemap_health_now() {
	$url = tp_sitemap_index_url();
	$res = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'sslverify' => apply_filters( 'https_local_ssl_verify', true ) ) );
	if ( is_wp_error( $res ) ) {
		/* translators: 1: url 2: error */
		return array( 'warn', sprintf( __( 'Could not check %1$s from the server (%2$s). Open it in your browser: it should list your pages.', 'taxi-peninsula' ), $url, $res->get_error_message() ) );
	}
	$code = wp_remote_retrieve_response_code( $res );
	$body = wp_remote_retrieve_body( $res );
	if ( 200 !== $code || false === strpos( $body, '<sitemap' ) && false === strpos( $body, '<urlset' ) ) {
		/* translators: 1: url 2: HTTP status */
		return array( 'fail', sprintf( __( '%1$s does not work (status %2$s), so Google can’t find your pages. Go to Settings → Permalinks and press Save, then check again. With Rank Math also open Rank Math → Sitemap Settings and press Save.', 'taxi-peninsula' ), $url, $code ) );
	}
	if ( tp_seo_plugin_active() && false === strpos( $body, 'tp-sitemap.xml' ) ) {
		return array( 'warn', __( 'The sitemap works, but the theme’s suburb, route and council pages are not listed in it yet. Clear your caching plugin’s cache and check again.', 'taxi-peninsula' ) );
	}
	/* translators: 1: url 2: number of pages */
	return array( 'ok', sprintf( __( '%1$s works, including %2$d suburb, route and council pages. Submit it once in Google Search Console → Sitemaps.', 'taxi-peninsula' ), $url, count( tp_generated_urls() ) ) );
}

add_filter(
	'tp_settings_checks',
	static function ( $c, $tab ) {
		if ( 'marketing' !== $tab ) {
			return $c;
		}
		$plugin = defined( 'RANK_MATH_VERSION' ) ? 'Rank Math' : ( defined( 'WPSEO_VERSION' ) ? 'Yoast SEO' : ( tp_seo_plugin_active() ? __( 'An SEO plugin', 'taxi-peninsula' ) : '' ) );
		$c[]    = $plugin
			/* translators: %s: plugin */
			? array( 'ok', __( 'SEO plugin', 'taxi-peninsula' ), sprintf( __( '%s writes titles and the sitemap; the theme gives it the right details for its own pages (suburb, route and council pages) and your home and suburb titles unless you wrote your own there.', 'taxi-peninsula' ), $plugin ) )
			: array( 'ok', __( 'SEO plugin', 'taxi-peninsula' ), __( 'None — the theme writes titles, descriptions and the sitemap itself. You don’t need an SEO plugin.', 'taxi-peninsula' ) );
		list( $status, $msg ) = tp_sitemap_health();
		$c[] = array( $status, __( 'Sitemap', 'taxi-peninsula' ), $msg );
		return $c;
	},
	10,
	2
);
