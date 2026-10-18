<?php
/**
 * Plugin Name: Taxi Peninsula Core
 * Description: Keeps your bookings, quotes, drivers' records, incidents and website content visible in WordPress even when the Taxi Peninsula theme is not active, and lets you export the booking data at any time (Tools → Taxi data export).
 * Version: 1.0.0
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Taxi Peninsula
 * Text Domain: taxi-peninsula-core
 * License: GPL-2.0-or-later
 *
 * The booking system itself (forms, dispatch, emails, SMS, payments) lives in
 * the theme. If the theme is switched off — on purpose, by an update or by a
 * host — WordPress stops registering the theme's content types, and bookings
 * seem to vanish from the admin (the data is still in the database). This
 * plugin registers the same content types whenever the theme is not active,
 * so everything stays visible, searchable and exportable.
 *
 * @package TaxiPeninsulaCore
 */

defined( 'ABSPATH' ) || exit;

const TPC_VERSION = '1.0.0';

/**
 * Is the Taxi Peninsula theme (or a child of it) active?
 */
function tpc_theme_active() {
	return 'taxi-peninsula' === get_template() || defined( 'TP_VERSION' );
}

/**
 * The theme's content types: key => [plural, singular, public, capability type, supports, rewrite slug or '', archive].
 */
function tpc_types() {
	return array(
		'tp_booking'     => array( 'Bookings', 'Booking', false, 'tp_booking', array( 'title' ), '', false ),
		'tp_quote'       => array( 'Quotes', 'Quote', false, 'tp_booking', array( 'title' ), '', false ),
		'tp_message'     => array( 'Enquiries', 'Enquiry', false, 'tp_booking', array( 'title', 'editor' ), '', false ),
		'tp_lost'        => array( 'Lost property', 'Lost property item', false, 'tp_booking', array( 'title', 'thumbnail' ), '', false ),
		'tp_incident'    => array( 'Incidents & complaints', 'Incident', false, 'tp_booking', array( 'title' ), '', false ),
		'tp_callback'    => array( 'Call-back requests', 'Call-back request', false, 'tp_booking', array( 'title' ), '', false ),
		'tp_promo'       => array( 'Promo codes', 'Promo code', false, 'tp_promo', array( 'title' ), '', false ),
		'tp_venue'       => array( 'Partner pages', 'Partner page', true, 'tp_promo', array( 'title', 'editor', 'thumbnail', 'excerpt' ), 'book-from', false ),
		'tp_event'       => array( 'Events', 'Event', true, 'tp_promo', array( 'title', 'editor', 'thumbnail', 'excerpt' ), 'events', 'events' ),
		'tp_service'     => array( 'Services', 'Service', true, 'page', array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ), 'services', 'services' ),
		'tp_area'        => array( 'Areas', 'Area', true, 'page', array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ), 'wheelchair-taxi', 'wheelchair-taxi' ),
		'tp_place'       => array( 'Place pages', 'Place page', true, 'post', array( 'title', 'editor', 'thumbnail', 'excerpt' ), '', false ),
		'tp_term'        => array( 'Glossary', 'Glossary term', true, 'post', array( 'title', 'editor', 'excerpt' ), 'glossary', 'glossary' ),
		'tp_fleet'       => array( 'Fleet', 'Vehicle', false, 'page', array( 'title', 'editor', 'thumbnail', 'page-attributes' ), '', false ),
		'tp_faq'         => array( 'FAQs', 'FAQ', false, 'page', array( 'title', 'editor', 'page-attributes' ), '', false ),
		'tp_testimonial' => array( 'Testimonials', 'Testimonial', false, 'page', array( 'title', 'editor', 'page-attributes' ), '', false ),
		'tp_partner'     => array( 'Partners', 'Partner', false, 'page', array( 'title', 'thumbnail', 'page-attributes' ), '', false ),
		'tp_notice'      => array( 'Announcements', 'Announcement', false, 'page', array( 'title' ), '', false ),
		'tp_hero'        => array( 'Hero photos', 'Hero photo', false, 'page', array( 'title', 'thumbnail' ), '', false ),
	);
}

/* -------------------------------------------------------------------------
 * Fallback: register everything while the theme is not active.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		if ( tpc_theme_active() ) {
			return;
		}
		foreach ( tpc_types() as $type => $t ) {
			register_post_type(
				$type,
				array(
					'labels'          => array(
						'name'          => $t[0],
						'singular_name' => $t[1],
						'menu_name'     => $t[0],
					),
					'public'          => $t[2],
					'show_ui'         => true,
					'show_in_menu'    => 'tpc-data',
					'show_in_rest'    => false,
					'supports'        => $t[4],
					'capability_type' => 'post' === $t[3] || 'page' === $t[3] ? $t[3] : array( $t[3], $t[3] . 's' ),
					'map_meta_cap'    => true,
					'rewrite'         => $t[5] ? array( 'slug' => $t[5], 'with_front' => false ) : false,
					'has_archive'     => $t[6],
				)
			);
		}
		register_taxonomy( 'tp_faq_topic', 'tp_faq', array( 'label' => 'FAQ topics', 'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true ) );
	},
	5
);

add_action(
	'admin_menu',
	static function () {
		if ( tpc_theme_active() ) {
			return;
		}
		add_menu_page( 'Taxi Peninsula data', 'Taxi data', 'read', 'tpc-data', 'tpc_render_home', 'dashicons-car', 3 );
	}
);

function tpc_render_home() {
	echo '<div class="wrap"><h1>Taxi Peninsula data</h1>';
	echo '<p>The Taxi Peninsula theme is not active, so the booking form, dispatch board, emails, SMS and payments are paused. All your records are safe and listed here. Reactivate the theme under Appearance → Themes to switch everything back on.</p><ul class="ul-disc">';
	foreach ( tpc_types() as $type => $t ) {
		if ( ! post_type_exists( $type ) || ! current_user_can( get_post_type_object( $type )->cap->edit_posts ) ) {
			continue;
		}
		$n = (int) array_sum( (array) wp_count_posts( $type ) );
		printf( '<li><a href="%s">%s</a> — %d</li>', esc_url( admin_url( 'edit.php?post_type=' . $type ) ), esc_html( $t[0] ), (int) $n );
	}
	echo '</ul><p><a class="button button-primary" href="' . esc_url( admin_url( 'tools.php?page=tpc-export' ) ) . '">Export booking data</a></p></div>';
}

add_action(
	'admin_notices',
	static function () {
		if ( tpc_theme_active() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Taxi Peninsula theme is not active.</strong> Online bookings, the dispatch board, emails and SMS are paused. Your bookings and records are safe under <a href="' . esc_url( admin_url( 'admin.php?page=tpc-data' ) ) . '">Taxi data</a>. Reactivate the theme in <a href="' . esc_url( admin_url( 'themes.php' ) ) . '">Appearance → Themes</a>.</p></div>';
	}
);

// Saved details (read-only) on each record while the theme is off.
add_action(
	'add_meta_boxes',
	static function ( $type ) {
		if ( ! tpc_theme_active() && isset( tpc_types()[ $type ] ) ) {
			add_meta_box( 'tpc-details', 'Saved details', 'tpc_render_details', $type, 'normal', 'high' );
		}
	}
);

function tpc_render_details( $post ) {
	$rows = array();
	foreach ( get_post_meta( $post->ID ) as $key => $values ) {
		if ( 0 !== strpos( $key, '_tp_' ) ) {
			continue;
		}
		$v = maybe_unserialize( $values[0] );
		if ( is_array( $v ) ) {
			$v = wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		$rows[ ucfirst( str_replace( '_', ' ', substr( $key, 4 ) ) ) ] = (string) $v;
	}
	if ( ! $rows ) {
		echo '<p>No saved details.</p>';
		return;
	}
	ksort( $rows );
	echo '<table class="widefat striped"><tbody>';
	foreach ( $rows as $k => $v ) {
		printf( '<tr><th style="width:14em">%s</th><td>%s</td></tr>', esc_html( $k ), esc_html( mb_strimwidth( $v, 0, 600, '…' ) ) );
	}
	echo '</tbody></table><p class="description">Read-only while the theme is off. Reactivate the theme to edit bookings.</p>';
}

/* -------------------------------------------------------------------------
 * Export (always available): Tools → Taxi data export.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_management_page( 'Taxi data export', 'Taxi data export', 'manage_options', 'tpc-export', 'tpc_render_export' );
	}
);

function tpc_render_export() {
	echo '<div class="wrap"><h1>Taxi data export</h1><p>Download your records as spreadsheets (CSV) — for your own backup, your accountant, or moving to another system. Files include personal information: store them securely and delete them when you no longer need them.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tpc_export">';
	wp_nonce_field( 'tpc_export' );
	echo '<p><label for="tpc-type">Records</label><br><select id="tpc-type" name="type">';
	foreach ( tpc_types() as $type => $t ) {
		printf( '<option value="%s">%s</option>', esc_attr( $type ), esc_html( $t[0] ) );
	}
	echo '</select></p>';
	echo '<p><label for="tpc-from">From (optional)</label><br><input type="date" id="tpc-from" name="from"> <label for="tpc-to">to</label> <input type="date" id="tpc-to" name="to"></p>';
	submit_button( 'Download CSV', 'primary', 'submit', false );
	echo '</form></div>';
}

add_action(
	'admin_post_tpc_export',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tpc_export' );
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		if ( ! isset( tpc_types()[ $type ] ) ) {
			wp_die( 'Unknown record type.', 400 );
		}
		$args = array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' );
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$args['date_query'] = array( array_filter( array( 'after' => $from ?: null, 'before' => $to ?: null, 'inclusive' => true ) ) );
		}
		$posts = get_posts( $args );
		$keys  = array();
		$rows  = array();
		foreach ( $posts as $p ) {
			$row = array( 'id' => $p->ID, 'title' => $p->post_title, 'status' => $p->post_status, 'created' => $p->post_date, 'content' => wp_strip_all_tags( $p->post_content ) );
			foreach ( get_post_meta( $p->ID ) as $k => $v ) {
				if ( 0 !== strpos( $k, '_tp_' ) || in_array( $k, array( '_tp_log', '_tp_token' ), true ) ) {
					continue;
				}
				$val            = maybe_unserialize( $v[0] );
				$row[ substr( $k, 4 ) ] = is_array( $val ) ? wp_json_encode( $val, JSON_UNESCAPED_UNICODE ) : (string) $val;
			}
			$keys   = array_unique( array_merge( $keys, array_keys( $row ) ) );
			$rows[] = $row;
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( str_replace( 'tp_', '', $type ) . '-' . gmdate( 'Y-m-d' ) ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // Excel-friendly UTF-8.
		fputcsv( $out, $keys, ',', '"', '' );
		foreach ( $rows as $r ) {
			$line = array();
			foreach ( $keys as $k ) {
				$v      = (string) ( $r[ $k ] ?? '' );
				$line[] = preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v; // No spreadsheet formulas.
			}
			fputcsv( $out, $line, ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);
