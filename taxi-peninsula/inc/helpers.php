<?php
/**
 * Shared helpers: theme options, lookup lists and inline SVG icons.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default values for every Customizer setting.
 */
function tp_defaults() {
	return array(
		'phone_display'   => '0468 323 211',
		'phone_link'      => '+61468323211',
		'email'           => 'support@taxipeninsula.com.au',
		'location'        => 'Melbourne & the Mornington Peninsula, VIC',
		'hours'           => 'Open 24 hours, 7 days — bookings recommended',
		'hero_eyebrow'    => 'Wheelchair accessible taxis · Melbourne',
		'hero_title'      => 'Accessible rides, booked in minutes.',
		'hero_text'       => 'Ramp and hoist equipped taxis with trained, patient drivers. Door-to-door across Melbourne and the Mornington Peninsula, including hospital, airport and NDIS trips.',
		'hero_image'      => '',
		'service_areas'   => "Melbourne CBD\nFrankston\nMornington\nMount Martha\nMount Eliza\nDromana\nRosebud\nRye\nSorrento\nPortsea\nHastings\nSomerville\nTyabb\nCarrum Downs\nSeaford\nDandenong\nMelbourne Airport\nAvalon Airport",
		'notify_email'    => 'support@taxipeninsula.com.au',
		'show_mptp'       => true,
		'customer_emails' => true,
		'min_notice'      => 60,
		'open_247'        => true,
		'since_year'      => '',
		'trips_offset'    => 0,
		'stats_min'       => 250,
		'hero_moments'    => "04:00-08:59 | Early flight or appointment? Book your pick-up the night before.\n09:00-14:59 | Heading to an appointment? We can have you there on time.\n15:00-18:59 | Home from the hospital? Book a pick-up in two minutes.\n19:00-03:59 | Planning tomorrow's trips? Book tonight so we can confirm early.",
		'sms_number'      => '+61468323211',
		'reply_time'      => '',
		'whatsapp_number' => '',
		'video_title'     => 'How we secure your wheelchair',
		'video_url'       => '',
		'video_file'      => '',
		'video_captions'  => '',
		'video_poster'    => '',
		'video_transcript'=> '',
		'map_query'       => 'Mornington Peninsula VIC',
	);
}

/**
 * Read a theme option with its default.
 *
 * @param string $key Option key.
 * @return mixed
 */
function tp_opt( $key ) {
	$defaults = tp_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	return get_theme_mod( 'tp_' . $key, $default );
}

function tp_phone_href() {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', tp_opt( 'phone_link' ) );
}

function tp_email_href() {
	return 'mailto:' . antispambot( tp_opt( 'email' ) );
}

/**
 * Service areas as an array (one per line in the Customizer).
 */
function tp_service_areas() {
	return array_keys( tp_service_area_aliases() );
}

/**
 * Service areas with their aliases. Each Customizer line can list extra names
 * and postcodes after "|", e.g. "Frankston | Frankston South | Frankston North | 3199".
 *
 * @return array name => string[] aliases (lower-case, including the name)
 */
function tp_service_area_aliases() {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) tp_opt( 'service_areas' ) ) as $line ) {
		$parts = array_values( array_filter( array_map( 'trim', explode( '|', $line ) ) ) );
		if ( ! $parts ) {
			continue;
		}
		$out[ $parts[0] ] = array_map( 'strtolower', $parts );
	}
	return $out;
}

function tp_vehicle_types() {
	return array(
		'wat'      => __( 'Wheelchair Accessible Taxi (WAT)', 'taxi-peninsula' ),
		'maxi_wat' => __( 'Maxi WAT – 2 wheelchairs / groups', 'taxi-peninsula' ),
		'sedan'    => __( 'Standard sedan (no wheelchair)', 'taxi-peninsula' ),
	);
}

function tp_mobility_aids() {
	return array(
		'manual'  => __( 'Manual wheelchair', 'taxi-peninsula' ),
		'power'   => __( 'Power / electric wheelchair', 'taxi-peninsula' ),
		'scooter' => __( 'Mobility scooter', 'taxi-peninsula' ),
		'walker'  => __( 'Walker or frame', 'taxi-peninsula' ),
		'none'    => __( 'None', 'taxi-peninsula' ),
	);
}

function tp_statuses() {
	return array(
		'pending'   => __( 'Pending', 'taxi-peninsula' ),
		'confirmed' => __( 'Confirmed', 'taxi-peninsula' ),
		'assigned'  => __( 'Driver assigned', 'taxi-peninsula' ),
		'completed' => __( 'Completed', 'taxi-peninsula' ),
		'cancelled' => __( 'Cancelled', 'taxi-peninsula' ),
	);
}

/**
 * Inline SVG icon. Icons are decorative (aria-hidden) — pair them with text.
 *
 * @param string $name Icon name.
 * @param string $class Extra class.
 * @return string
 */
function tp_icon( $name, $class = '' ) {
	$paths = array(
		'wheelchair' => '<circle cx="12" cy="4" r="2"/><path d="M12 7v6h5l2 5h2"/><path d="M12 10h5"/><path d="M8.6 10.9a5.5 5.5 0 1 0 7.6 7.4"/>',
		'phone'      => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
		'mail'       => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
		'clock'      => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'pin'        => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
		'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
		'plane'      => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
		'medical'    => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M12 8v8M8 12h8"/>',
		'users'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
		'heart'      => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
		'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'check'      => '<path d="M20 6 9 17l-5-5"/>',
		'arrow'      => '<path d="M5 12h14M12 5l7 7-7 7"/>',
		'menu'       => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'close'      => '<path d="M18 6 6 18M6 6l12 12"/>',
		'text'       => '<path d="M4 7V4h16v3M9 20h6M12 4v16"/>',
		'contrast'   => '<circle cx="12" cy="12" r="10"/><path d="M12 2v20" /><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor"/>',
		'star'       => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
		'megaphone'  => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
		'message'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'moon'       => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
		'search'     => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'play'       => '<polygon points="6 3 20 12 6 21 6 3" fill="currentColor"/>',
		'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
		'image'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
		'book'       => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
		'chevron-up' => '<path d="m18 15-6-6-6 6"/>',
		'facebook'   => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
		'instagram'  => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.4A4 4 0 1 1 12.6 8a4 4 0 0 1 3.4 3.4z"/><path d="M17.5 6.5h.01"/>',
		'linkedin'   => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
		'youtube'    => '<path d="M2.5 17a24 24 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.6 49.6 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24 24 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.6 49.6 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="icon icon-%1$s %2$s" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		esc_attr( $class ),
		$paths[ $name ]
	);
}

/**
 * Echo an icon (markup is static and trusted).
 */
function tp_the_icon( $name, $class = '' ) {
	echo tp_icon( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Client IP used for simple rate limiting (not trusted for anything else).
 * Uses Cloudflare's CF-Connecting-IP only for requests from Cloudflare's network.
 */
function tp_client_ip() {
	return tp_real_ip();
}

/**
 * Shortcode that identifies each feature page.
 */
function tp_feature_shortcodes() {
	return array(
		'book'    => 'tp_booking_form',
		'lookup'  => 'tp_booking_lookup',
		'contact' => 'tp_contact_form',
		'faq'     => 'tp_faq',
		'fleet'   => 'tp_fleet',
		'driver'  => 'tp_driver_jobs',
		'ndis'    => 'tp_ndis',
	);
}

/**
 * Page template that identifies each feature page.
 */
function tp_feature_templates() {
	return array(
		'book'          => 'page-templates/booking.php',
		'about'         => 'page-templates/about.php',
		'contact'       => 'page-templates/contact.php',
		'services'      => 'page-templates/services.php',
		'blog'          => 'page-templates/blog.php',
		'careers'       => 'page-templates/careers.php',
	);
}

/**
 * URL of the page that provides a feature (booking form, lookup, contact…).
 *
 * Pages created by "Create starter content" are remembered; otherwise the first
 * published page containing the feature's shortcode is used.
 *
 * @param string $key      Feature key from tp_feature_shortcodes().
 * @param string $fallback URL to return when no page exists.
 * @return string
 */
function tp_page_url_by_template( $key, $fallback = '' ) {
	static $cache = array();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ] ?: $fallback;
	}

	$id    = 0;
	$pages = (array) get_option( 'tp_pages', array() );
	if ( ! empty( $pages[ $key ] ) && 'publish' === get_post_status( $pages[ $key ] ) ) {
		$id = (int) $pages[ $key ];
	}

	$templates = tp_feature_templates();
	if ( ! $id && isset( $templates[ $key ] ) ) {
		$found = get_pages(
			array(
				'meta_key'   => '_wp_page_template',
				'meta_value' => $templates[ $key ],
				'number'     => 1,
			)
		);
		$id    = $found ? $found[0]->ID : 0;
	}

	$codes = tp_feature_shortcodes();
	if ( ! $id && isset( $codes[ $key ] ) ) {
		global $wpdb;
		$candidates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 20",
				'%' . $wpdb->esc_like( '[' . $codes[ $key ] ) . '%'
			)
		);
		// Skip pages that belong to another feature (e.g. the NDIS page embeds [tp_faq topic="…"]).
		$taken = array_map( 'intval', array_diff_key( $pages, array( $key => 0 ) ) );
		foreach ( $candidates as $candidate ) {
			if ( ! in_array( (int) $candidate, $taken, true ) ) {
				$id = (int) $candidate;
				break;
			}
		}
	}

	$cache[ $key ] = $id ? get_permalink( $id ) : '';
	return $cache[ $key ] ?: $fallback;
}

/**
 * Normalise an Australian number to E.164 (+614…) for SMS. Returns '' if unusable.
 */
function tp_e164( $phone ) {
	$phone = preg_replace( '/[^\d+]/', '', (string) $phone );
	if ( '' === $phone ) {
		return '';
	}
	if ( '+' === $phone[0] ) {
		return strlen( $phone ) >= 10 ? $phone : '';
	}
	if ( 0 === strpos( $phone, '61' ) && strlen( $phone ) === 11 ) {
		return '+' . $phone;
	}
	if ( '0' === $phone[0] && strlen( $phone ) === 10 ) {
		return '+61' . substr( $phone, 1 );
	}
	return '';
}

/**
 * Terms & cancellation policy page URL (Bookings → Settings, or the page made by Setup).
 * When set, customers must accept it to book.
 */
function tp_terms_url() {
	$id = (int) tp_setting( 'terms_page_id' );
	if ( ! $id ) {
		$pages = (array) get_option( 'tp_pages', array() );
		$id    = (int) ( $pages['terms'] ?? 0 );
	}
	return ( $id && 'publish' === get_post_status( $id ) ) ? get_permalink( $id ) : '';
}
