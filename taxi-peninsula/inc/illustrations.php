<?php
/**
 * Spot illustrations in the same flat style as the animated taxis, coloured
 * with the theme's main, accent and link colours. Used for services, "Why
 * passengers choose us", "Who we help", the contact chooser, destinations,
 * empty states and the booking confirmation.
 *
 * Customize → Design → Fonts & shape → "Icon style" switches back to simple line icons.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Drawings on a 64 × 64 grid (without the background circle).
 */
function tp_illustration_paths() {
	$p = 'class="tp-f-primary"';
	$a = 'class="tp-f-accent"';
	$l = 'class="tp-f-link"';
	$wheel = static function ( $x, $y, $r ) {
		return sprintf( '<circle cx="%1$s" cy="%2$s" r="%3$s" fill="#1e293b"/><circle cx="%1$s" cy="%2$s" r="%4$s" fill="#cbd5e1"/>', $x, $y, $r, round( $r * .45, 1 ) );
	};
	$sedan = static function ( $x, $y ) use ( $a, $p, $wheel ) {
		return sprintf( '<g transform="translate(%1$s %2$s)"><path %3$s d="M1 12Q1 9 4 8.5L10 8 14 3Q15 2 17 2H25Q27 2 28 3L32 8 35 8.5Q38 9 38 12V14Q38 15.5 36.5 15.5H2.5Q1 15.5 1 14Z"/><path fill="#1e293b" d="M15.5 4H21V8H12.5Z M22.5 4H26Q27 4 28 5L30.5 8H22.5Z"/><rect %4$s x="2" y="10.5" width="35" height="1.4"/><rect x="18" y="0" width="7" height="2.4" rx=".8" fill="#fff" stroke="#94a3b8" stroke-width=".4"/>%5$s%6$s</g>', $x, $y, $a, $p, $wheel( 9, 15.5, 3 ), $wheel( 30, 15.5, 3 ) );
	};
	$person = static function ( $x, $y, $class, $skin = '#c9936a' ) {
		return sprintf( '<g transform="translate(%1$s %2$s)"><circle cx="5" cy="3.5" r="3.5" fill="%4$s"/><path %3$s d="M0 13Q0 8 5 8Q10 8 10 13V21H0Z"/></g>', $x, $y, $class, $skin );
	};
	return array(
		// Maxi taxi with the ramp down.
		'wheelchair' => '<rect x="4" y="49" width="56" height="5" rx="2" fill="#64748b"/><path d="M10 48.5L3 51" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>'
			. '<path fill="#fff" stroke="#94a3b8" stroke-width="1.2" d="M10 22Q10 17 15 17H44Q48 17 50 20L56 31Q59 32 59 35V45Q59 47 57 47H12Q10 47 10 45Z"/>'
			. '<path fill="#1e293b" d="M45 20H47.5L53 30H45Z"/><rect x="14" y="21" width="12" height="9" rx="1.5" fill="#1e293b"/><rect x="29" y="21" width="12" height="9" rx="1.5" fill="#1e293b"/>'
			. '<rect ' . $a . ' x="10" y="34" width="49" height="3.5"/><rect x="15" y="39" width="7" height="6.5" rx="1.2" fill="#1d4ed8"/>'
			. '<g transform="translate(15.6 39.6) scale(.24)" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"><circle cx="12" cy="4" r="2"/><path d="M12 7v6h5l2 5h2M12 10h5M8.6 10.9a5.5 5.5 0 1 0 7.6 7.4"/></g>'
			. '<rect ' . $a . ' x="27" y="13" width="9" height="4" rx="1"/>' . $wheel( 20, 47, 4.5 ) . $wheel( 50, 47, 4.5 ),
		// Accredited: badge with a tick.
		'shield'     => '<path ' . $p . ' d="M32 8L50 15V30C50 42 41 50 32 54C23 50 14 42 14 30V15Z"/><path ' . $l . ' d="M32 8L50 15V30C50 42 41 50 32 54Z" opacity=".35"/>'
			. '<path d="M23 31L29.5 37.5L42 24" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>'
			. '<path ' . $a . ' d="M47 7l1.8 3.7 4 .6-2.9 2.8.7 4-3.6-1.9-3.6 1.9.7-4-2.9-2.8 4-.6z"/>',
		// On time: clock and a taxi.
		'clock'      => '<circle cx="30" cy="27" r="19" fill="#fff"/><circle cx="30" cy="27" r="19" fill="none" class="tp-s-primary" stroke-width="4"/>'
			. '<path d="M30 15V27L38 32" fill="none" class="tp-s-primary" stroke-width="3.5" stroke-linecap="round"/><circle ' . $a . ' cx="30" cy="27" r="2.6"/>'
			. $sedan( 22, 40 ),
		// Carers: a carer beside a wheelchair user, heart above.
		'heart'      => '<path ' . $a . ' d="M32 20C28 13 17 15 19 23C20 27 32 33 32 33C32 33 44 27 45 23C47 15 36 13 32 20Z"/>'
			. $person( 38, 30, $p ) . '<circle cx="21" cy="47" r="7" fill="none" stroke="#334155" stroke-width="2.4"/><path d="M17 37V45H28L30 50" fill="none" stroke="#334155" stroke-width="2.4" stroke-linecap="round"/>'
			. '<circle cx="21" cy="31" r="3.4" fill="#c9936a"/><path ' . $l . ' d="M16 37Q16 35 18 35H24Q26 35 26 37V43H16Z"/>',
		// Hospital.
		'medical'    => '<rect x="13" y="22" width="38" height="30" rx="2" fill="#fff" stroke="#94a3b8" stroke-width="1.2"/><rect ' . $p . ' x="11" y="19" width="42" height="5" rx="1.5"/>'
			. '<circle cx="32" cy="14" r="8" fill="#fff" stroke="#ef4444" stroke-width="2"/><path d="M32 9.5V18.5M27.5 14H36.5" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>'
			. '<rect x="18" y="28" width="7" height="6" rx="1" fill="#bfdbfe"/><rect x="39" y="28" width="7" height="6" rx="1" fill="#bfdbfe"/><rect x="18" y="38" width="7" height="6" rx="1" fill="#bfdbfe"/><rect x="39" y="38" width="7" height="6" rx="1" fill="#bfdbfe"/>'
			. '<rect ' . $l . ' x="28" y="38" width="8" height="14" rx="1"/>',
		// Airport: plane taking off.
		'plane'      => '<circle ' . $a . ' cx="46" cy="18" r="7" opacity=".9"/><path d="M6 52H58" stroke="#64748b" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 4"/>'
			. '<g transform="rotate(-18 32 34)"><path ' . $p . ' d="M14 30L10 21H16L23 30Z"/><path fill="#fff" stroke="#94a3b8" stroke-width="1.2" d="M11 34Q11 30 16 30H47Q55 30 58 34Q55 38 47 38H16Q11 38 11 34Z"/>'
			. '<path fill="#cbd5e1" stroke="#94a3b8" stroke-width="1" d="M29 36H38L31 46H26Z"/><circle cx="22" cy="33" r="1" fill="#1e293b"/><circle cx="26" cy="33" r="1" fill="#1e293b"/><circle cx="30" cy="33" r="1" fill="#1e293b"/><circle cx="34" cy="33" r="1" fill="#1e293b"/><circle cx="38" cy="33" r="1" fill="#1e293b"/><circle cx="42" cy="33" r="1" fill="#1e293b"/></g>',
		// Groups and coordinators.
		'users'      => $person( 11, 25, $l ) . $person( 43, 25, $a ) . '<g transform="translate(22 18) scale(1.25)">' . $person( 0, 0, $p ) . '</g><rect x="8" y="48" width="48" height="4" rx="2" fill="#cbd5e1"/>',
		// Bookings and events: calendar.
		'calendar'   => '<rect x="12" y="14" width="40" height="38" rx="4" fill="#fff" stroke="#94a3b8" stroke-width="1.2"/><path ' . $p . ' d="M12 18Q12 14 16 14H48Q52 14 52 18V24H12Z"/>'
			. '<rect x="20" y="9" width="4" height="9" rx="2" fill="#475569"/><rect x="40" y="9" width="4" height="9" rx="2" fill="#475569"/>'
			. '<g fill="#cbd5e1"><rect x="17" y="29" width="6" height="5" rx="1"/><rect x="29" y="29" width="6" height="5" rx="1"/><rect x="17" y="39" width="6" height="5" rx="1"/><rect x="29" y="39" width="6" height="5" rx="1"/></g>'
			. '<rect ' . $a . ' x="40" y="38" width="8" height="7" rx="1.5"/><path d="M41.5 41.5L43.5 43.5L46.5 39.8" fill="none" stroke="#1e293b" stroke-width="1.6" stroke-linecap="round"/>',
		// Events and outings: ticket with a star.
		'star'       => '<path ' . $a . ' d="M10 22Q10 19 13 19H51Q54 19 54 22V27Q50 27 50 32Q50 37 54 37V42Q54 45 51 45H13Q10 45 10 42V37Q14 37 14 32Q14 27 10 27Z"/>'
			. '<path d="M40 19V45" stroke="#fff" stroke-width="1.5" stroke-dasharray="3 2"/><path ' . $p . ' d="M26 24l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.6-4.8 2.6.9-5.4-3.9-3.8 5.4-.8z"/>',
		// Places: pin on a road.
		'pin'        => '<path d="M6 50Q32 40 58 50" fill="none" stroke="#64748b" stroke-width="6" stroke-linecap="round"/><path d="M6 50Q32 40 58 50" fill="none" stroke="#fff" stroke-width="1.2" stroke-dasharray="4 3"/>'
			. '<path ' . $p . ' d="M32 8C24 8 18 14 18 21C18 31 32 43 32 43C32 43 46 31 46 21C46 14 40 8 32 8Z"/><circle ' . $a . ' cx="32" cy="21" r="5.5"/>',
		// Feedback.
		'message'    => '<path ' . $p . ' d="M8 16Q8 11 13 11H39Q44 11 44 16V32Q44 37 39 37H22L13 44V37Q8 37 8 32Z"/><path ' . $a . ' d="M28 26Q28 22 32 22H52Q56 22 56 26V40Q56 44 52 44V50L45 44H32Q28 44 28 40Z"/>'
			. '<g fill="#fff"><circle cx="18" cy="24" r="2.2"/><circle cx="26" cy="24" r="2.2"/><circle cx="34" cy="24" r="2.2"/></g><path d="M35 33H49M35 38H45" stroke="#1e293b" stroke-width="2" stroke-linecap="round"/>',
		// Lost property: suitcase under a magnifying glass.
		'search'     => '<rect ' . $l . ' x="10" y="26" width="28" height="22" rx="3"/><path d="M18 26V21H30V26" fill="none" stroke="#334155" stroke-width="2.5"/><rect x="10" y="34" width="28" height="3" fill="#1e293b" opacity=".25"/>'
			. '<circle cx="40" cy="25" r="11" fill="#fff" fill-opacity=".7" class="tp-s-primary" stroke-width="4"/><path d="M48 33L55 40" class="tp-s-primary" stroke-width="5" stroke-linecap="round"/>',
		// Empty day: a parked taxi under the moon.
		'empty-day'  => '<path ' . $a . ' d="M44 8A10 10 0 1 0 54 22A8 8 0 1 1 44 8Z"/><text x="20" y="22" font-family="Arial, sans-serif" font-size="9" font-weight="700" fill="#94a3b8">z</text><text x="27" y="16" font-family="Arial, sans-serif" font-size="7" font-weight="700" fill="#94a3b8">z</text>'
			. '<rect x="4" y="50" width="56" height="4" rx="2" fill="#64748b"/>' . $sedan( 12, 34 ),
		// Empty list: signpost.
		'empty-list' => '<rect x="30" y="14" width="4" height="40" rx="1.5" fill="#64748b"/><path ' . $p . ' d="M14 17H44L50 22L44 27H14Z"/><path ' . $a . ' d="M50 31H22L16 36L22 41H50Z"/><rect x="18" y="52" width="28" height="4" rx="2" fill="#cbd5e1"/>',
		// Booking received: taxi with a tick.
		'success'    => '<rect x="4" y="50" width="56" height="4" rx="2" fill="#64748b"/>' . $sedan( 8, 34 )
			. '<circle cx="44" cy="20" r="12" fill="#16a34a"/><path d="M38.5 20L42.5 24L50 16.5" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>',
	);
}

/**
 * Illustration markup, or '' if there is no drawing with that name.
 */
function tp_illustration( $name, $class = '' ) {
	$paths = tp_illustration_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="illo illo--%1$s %2$s" viewBox="0 0 64 64" width="64" height="64" aria-hidden="true" focusable="false"><circle cx="32" cy="32" r="31" class="illo__bg"/>%3$s</svg>',
		esc_attr( $name ),
		esc_attr( $class ),
		$paths[ $name ]
	);
}

function tp_use_illustrations() {
	return 'line' !== tp_design( 'icon_style' );
}

/**
 * Icon spot for cards: an illustration when enabled (and available), else the line icon.
 *
 * @param string $name  Icon name.
 * @param string $class Wrapper class, e.g. "feature__icon".
 */
function tp_the_spot( $name, $class ) {
	$illo = tp_use_illustrations() ? tp_illustration( $name ) : '';
	printf(
		'<span class="%1$s%2$s">%3$s</span>',
		esc_attr( $class ),
		$illo ? ' is-illustrated' : '',
		$illo ? $illo : tp_icon( $name ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	);
}

/**
 * Friendly empty state with an illustration.
 *
 * @param string $illo  Illustration name.
 * @param string $title Main line.
 * @param string $text  Optional second line.
 * @param array  $cta   Optional [label, url].
 */
function tp_empty_state( $illo, $title, $text = '', $cta = array() ) {
	$out  = '<div class="empty-state empty-state--illustrated">' . tp_illustration( $illo, 'empty-state__illo' );
	$out .= '<p class="empty-state__title">' . esc_html( $title ) . '</p>';
	if ( $text ) {
		$out .= '<p class="empty-state__text">' . esc_html( $text ) . '</p>';
	}
	if ( $cta ) {
		$out .= '<p><a class="btn btn--accent" href="' . esc_url( $cta[1] ) . '">' . esc_html( $cta[0] ) . '</a></p>';
	}
	return $out . '</div>';
}

function tp_the_empty_state( $illo, $title, $text = '', $cta = array() ) {
	echo tp_empty_state( $illo, $title, $text, $cta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
}
