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
	$lines = preg_split( '/\r\n|\r|\n/', (string) tp_opt( 'service_areas' ) );
	return array_values( array_filter( array_map( 'trim', $lines ) ) );
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
 */
function tp_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
}
