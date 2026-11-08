<?php
/**
 * Page addresses moved in v2.14 — one folder per kind of page:
 *
 *   /wheelchair-taxi/sorrento/                       → /locations/sorrento/
 *   /wheelchair-taxi/sorrento/airport-transfers/     → /locations/sorrento/airport-transfers/
 *   /council/frankston-city/                         → /locations/council/frankston-city/
 *   /wheelchair-taxi-to/frankston-hospital/          → /places/frankston-hospital/
 *   /wheelchair-taxi-to/frankston-hospital/from-rye/ → /routes/rye-to-frankston-hospital/
 *   /wheelchair-taxi-from/frankston-station/to-rye/  → /routes/frankston-station-to-rye/
 *   /our-services/, /places-we-go/                   → /services/, /places/
 *
 * Old addresses answer with a permanent (301) redirect straight to the new
 * one, so search rankings, shared links and printed QR codes keep working.
 * Old links inside posts, pages and menus are rewritten when shown (nothing
 * in the database is changed).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_URL_MOVES_VERSION = 1;

/**
 * New path for an old one ("wheelchair-taxi/rye/" → "locations/rye/"), or ''.
 *
 * @param string $path Path without the home path, with or without slashes.
 */
function tp_moved_path( $path ) {
	$path = trim( (string) $path, '/' );
	if ( '' === $path ) {
		return '';
	}
	if ( preg_match( '#^wheelchair-taxi-(to|from)/([^/]+)/(from|to)-([^/]+)$#', $path, $m ) ) {
		return 'routes/' . ( 'to' === $m[1] ? $m[4] . '-to-' . $m[2] : $m[2] . '-to-' . $m[4] ) . '/';
	}
	if ( preg_match( '#^wheelchair-taxi-(?:to|from)/([^/]+)$#', $path, $m ) ) {
		return 'places/' . $m[1] . '/';
	}
	if ( preg_match( '#^wheelchair-taxi(/.*)?$#', $path, $m ) ) {
		return 'locations' . ( $m[1] ?? '' ) . '/';
	}
	if ( preg_match( '#^council(/.*)?$#', $path, $m ) ) {
		return 'locations/council' . ( $m[1] ?? '' ) . '/';
	}
	$pages = (array) get_option( 'tp_url_moves', array() );
	return isset( $pages[ $path ] ) ? trim( $pages[ $path ], '/' ) . '/' : '';
}

/** The request path relative to the site's home path. */
function tp_request_path() {
	$uri  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared only.
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $home && 0 === strpos( $uri, $home ) ) {
		$uri = substr( $uri, strlen( $home ) );
	}
	return rawurldecode( trim( $uri, '/' ) );
}

// Old address → new address, before WordPress guesses a page for the 404.
add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() ) {
			return;
		}
		$new = tp_moved_path( tp_request_path() );
		if ( '' === $new ) {
			return;
		}
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed through.
		$url   = home_url( user_trailingslashit( untrailingslashit( $new ) ) ) . ( '' !== $query ? '?' . $query : '' );
		wp_safe_redirect( $url, 301, 'Taxi Peninsula' );
		exit;
	},
	1
);

/**
 * The new URL for an old internal link, or the link unchanged.
 */
function tp_moved_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! $parts || empty( $parts['path'] ) ) {
		return $url;
	}
	$home = wp_parse_url( home_url( '/' ) );
	if ( ! empty( $parts['host'] ) && preg_replace( '/^www\./', '', strtolower( $parts['host'] ) ) !== preg_replace( '/^www\./', '', strtolower( $home['host'] ?? '' ) ) ) {
		return $url;
	}
	$path = $parts['path'];
	$base = $home['path'] ?? '/';
	if ( '/' !== $base && 0 === strpos( $path, untrailingslashit( $base ) ) ) {
		$path = substr( $path, strlen( untrailingslashit( $base ) ) );
	}
	$new = tp_moved_path( $path );
	if ( '' === $new ) {
		return $url;
	}
	return home_url( user_trailingslashit( untrailingslashit( $new ) ) ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
}

/** Old path fragments worth looking for in content. */
function tp_moved_markers() {
	return array_merge( array( '/wheelchair-taxi', '/council' ), array_map( static fn( $p ) => '/' . trim( $p, '/' ), array_keys( (array) get_option( 'tp_url_moves', array() ) ) ) );
}

/** Rewrite old links in a block of HTML. */
function tp_rewrite_moved_links( $html ) {
	$hit = false;
	foreach ( tp_moved_markers() as $mark ) {
		if ( false !== strpos( (string) $html, $mark ) ) {
			$hit = true;
			break;
		}
	}
	if ( ! $hit ) {
		return $html;
	}
	return preg_replace_callback(
		'#(href=["\'])([^"\']+)#i',
		static fn( $m ) => $m[1] . esc_url( tp_moved_url( html_entity_decode( $m[2] ) ) ),
		$html
	);
}
add_filter( 'the_content', 'tp_rewrite_moved_links', 99 );
add_filter( 'widget_text_content', 'tp_rewrite_moved_links', 99 );
add_filter( 'widget_block_content', 'tp_rewrite_moved_links', 99 );
add_filter(
	'wp_nav_menu_objects',
	static function ( $items ) {
		foreach ( $items as $item ) {
			if ( ! empty( $item->url ) ) {
				$item->url = tp_moved_url( $item->url );
			}
		}
		return $items;
	}
);

/**
 * The services and places hub pages take the short addresses /services/ and
 * /places/ (once, on upgrade). Their old addresses are remembered for redirects.
 */
add_action(
	'init',
	static function () {
		if ( (int) get_option( 'tp_url_moves_v' ) >= TP_URL_MOVES_VERSION ) {
			return;
		}
		update_option( 'tp_url_moves_v', TP_URL_MOVES_VERSION );
		$moves = (array) get_option( 'tp_url_moves', array() );
		foreach ( array( 'services' => 'services', 'places' => 'places' ) as $key => $slug ) {
			$url = tp_page_url_by_template( $key );
			$id  = $url ? url_to_postid( $url ) : 0;
			if ( ! $id || 'page' !== get_post_type( $id ) || wp_get_post_parent_id( $id ) ) {
				continue;
			}
			$old = get_post_field( 'post_name', $id );
			if ( $old === $slug || get_page_by_path( $slug ) ) {
				continue;
			}
			wp_update_post( array( 'ID' => $id, 'post_name' => $slug ) );
			if ( get_post_field( 'post_name', $id ) === $slug ) {
				$moves[ $old ] = $slug;
			}
		}
		update_option( 'tp_url_moves', $moves, true );
		tp_flush_rewrites_soon();
	},
	1 // Before the content types are registered, so /services/ goes to the page.
);

/** Whether the services page owns /services/ (then the plain list is not needed). */
function tp_services_page_at_root() {
	$page = get_page_by_path( 'services' );
	return $page && 'publish' === $page->post_status && '' !== (string) get_page_template_slug( $page ) && false !== strpos( get_page_template_slug( $page ), 'services' );
}

/* -------------------------------------------------------------------------
 * Check: old links left in posts and menus.
 * ---------------------------------------------------------------------- */

add_filter(
	'tp_settings_checks',
	static function ( $c, $tab ) {
		if ( 'marketing' !== $tab ) {
			return $c;
		}
		$n = get_transient( 'tp_old_links_count' );
		if ( false === $n ) {
			global $wpdb;
			$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision','tp_booking') AND ( post_content LIKE '%/wheelchair-taxi/%' OR post_content LIKE '%/wheelchair-taxi-to/%' OR post_content LIKE '%/wheelchair-taxi-from/%' OR post_content LIKE '%/council/%' ) " ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$n += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = 'nav_menu_item' AND m.meta_key = '_menu_item_url' AND ( m.meta_value LIKE '%/wheelchair-taxi%' OR m.meta_value LIKE '%/council/%' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			set_transient( 'tp_old_links_count', $n, HOUR_IN_SECONDS );
		}
		$c[] = $n
			/* translators: %d: number */
			? array( 'notice', __( 'Old page addresses', 'taxi-peninsula' ), sprintf( _n( '%d post or menu link still uses an old address (e.g. /wheelchair-taxi/…). It still works — visitors are sent to the new page and links are updated when shown — but update it when convenient.', '%d posts or menu links still use an old address (e.g. /wheelchair-taxi/…). They still work — visitors are sent to the new pages and links are updated when shown — but update them when convenient.', $n, 'taxi-peninsula' ), $n ) )
			: array( 'ok', __( 'Old page addresses', 'taxi-peninsula' ), __( 'Suburb, place and route pages use the new addresses (/locations/, /places/, /routes/). Old addresses redirect permanently.', 'taxi-peninsula' ) );
		return $c;
	},
	11,
	2
);
