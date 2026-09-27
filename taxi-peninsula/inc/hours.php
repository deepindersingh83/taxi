<?php
/**
 * Opening hours per weekday and a live "Open now / Closed" status.
 *
 * Only used when "Open 24 hours, 7 days" is switched off in
 * Customize → Taxi Peninsula → Contact details. The status is recalculated in
 * the browser (in Melbourne time) so cached pages stay correct.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action( 'customize_register', static function ( WP_Customize_Manager $wp_customize ) {
	$wp_customize->add_setting( 'tp_hours_schedule', array( 'default' => "Mon-Fri 06:00-22:00\nSat 07:00-20:00\nSun 08:00-18:00", 'sanitize_callback' => 'sanitize_textarea_field' ) );
	$wp_customize->add_control(
		'tp_hours_schedule',
		array(
			'section'     => 'tp_contact',
			'type'        => 'textarea',
			'label'       => __( 'Opening hours by day (used when not open 24/7)', 'taxi-peninsula' ),
			'description' => __( 'One line per day or range, e.g. "Mon-Fri 06:00-22:00", "Sat 07:00-20:00", "Sun closed".', 'taxi-peninsula' ),
		)
	);
	$wp_customize->add_setting( 'tp_hours_closed_note', array( 'default' => __( 'Book online any time — we will confirm when we open.', 'taxi-peninsula' ), 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'tp_hours_closed_note', array( 'section' => 'tp_contact', 'type' => 'text', 'label' => __( 'Message shown while closed', 'taxi-peninsula' ) ) );
}, 20 );

/**
 * Weekly schedule: [1 (Mon) … 7 (Sun) => ['06:00', '22:00'] | null (closed)].
 */
function tp_hours_schedule() {
	$days  = array( 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7 );
	$out   = array_fill( 1, 7, null );
	$lines = preg_split( '/\R/', (string) get_theme_mod( 'tp_hours_schedule', "Mon-Fri 06:00-22:00\nSat 07:00-20:00\nSun 08:00-18:00" ) );
	foreach ( $lines as $line ) {
		if ( ! preg_match( '/^\s*([a-z]{3})[a-z]*(?:\s*-\s*([a-z]{3})[a-z]*)?\s+(closed|(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2}))\s*$/i', $line, $m ) ) {
			continue;
		}
		$from = $days[ strtolower( $m[1] ) ] ?? 0;
		$to   = ! empty( $m[2] ) ? ( $days[ strtolower( $m[2] ) ] ?? 0 ) : $from;
		if ( ! $from || ! $to ) {
			continue;
		}
		$range = 'closed' === strtolower( $m[3] ) ? null : array( sprintf( '%02d:%s', $m[4], $m[5] ), sprintf( '%02d:%s', $m[6], $m[7] ) );
		for ( $d = $from; ; $d = $d % 7 + 1 ) {
			$out[ $d ] = $range;
			if ( $d === $to ) {
				break;
			}
		}
	}
	return $out;
}

function tp_hours_enabled() {
	return ! tp_opt( 'open_247' ) && array_filter( tp_hours_schedule() );
}

/**
 * "10pm", "6:30am".
 */
function tp_short_time( $hhmm ) {
	list( $h, $m ) = array_map( 'intval', explode( ':', $hhmm ) );
	$h24 = $h % 24;
	$sfx = $h24 < 12 ? 'am' : 'pm';
	$h12 = $h24 % 12 ?: 12;
	return $h12 . ( $m ? ':' . sprintf( '%02d', $m ) : '' ) . $sfx;
}

/**
 * Status text for a moment in site time.
 *
 * @return array [bool open, string text]
 */
function tp_hours_status( $now = null ) {
	$now   = $now ?: new DateTimeImmutable( 'now', wp_timezone() );
	$sched = tp_hours_schedule();
	$dow   = (int) $now->format( 'N' );
	$time  = $now->format( 'H:i' );
	$today = $sched[ $dow ];
	if ( $today && $time >= $today[0] && $time < $today[1] ) {
		/* translators: %s: closing time */
		return array( true, sprintf( __( 'Open now · phones answered until %s', 'taxi-peninsula' ), tp_short_time( $today[1] ) ) );
	}
	global $wp_locale;
	for ( $i = 0; $i < 8; $i++ ) {
		$d = ( $dow - 1 + $i ) % 7 + 1;
		$r = $sched[ $d ];
		if ( ! $r || ( 0 === $i && $time >= $r[0] ) ) {
			continue;
		}
		if ( 0 === $i ) {
			/* translators: %s: time */
			$when = sprintf( __( 'we open at %s', 'taxi-peninsula' ), tp_short_time( $r[0] ) );
		} elseif ( 1 === $i ) {
			/* translators: %s: time */
			$when = sprintf( __( 'we open tomorrow at %s', 'taxi-peninsula' ), tp_short_time( $r[0] ) );
		} else {
			/* translators: 1: weekday, 2: time */
			$when = sprintf( __( 'we open %1$s at %2$s', 'taxi-peninsula' ), $wp_locale->get_weekday( $d % 7 ), tp_short_time( $r[0] ) );
		}
		/* translators: %s: when we open */
		return array( false, sprintf( __( 'Closed now · %s', 'taxi-peninsula' ), $when ) );
	}
	return array( false, __( 'Closed now', 'taxi-peninsula' ) );
}

/**
 * Live status element (server text + data for the browser to recalculate).
 */
function tp_hours_badge( $class = '' ) {
	if ( ! tp_hours_enabled() ) {
		return '<span class="' . esc_attr( trim( 'hours-status ' . $class ) ) . '">' . esc_html( tp_opt( 'hours' ) ) . '</span>';
	}
	list( $open, $text ) = tp_hours_status();
	global $wp_locale;
	$days = array();
	for ( $i = 0; $i < 7; $i++ ) {
		$days[] = $wp_locale->get_weekday( $i );
	}
	return sprintf(
		'<span class="%1$s" data-hours="%2$s" data-tz="%3$s" data-days="%4$s"><span class="hours-status__dot" aria-hidden="true"></span><span class="hours-status__text">%5$s</span></span>',
		esc_attr( trim( 'hours-status ' . ( $open ? 'is-open' : 'is-closed' ) . ' ' . $class ) ),
		esc_attr( wp_json_encode( tp_hours_schedule() ) ),
		esc_attr( wp_timezone_string() ),
		esc_attr( wp_json_encode( $days ) ),
		esc_html( $text )
	);
}

function tp_the_hours_badge( $class = '' ) {
	echo tp_hours_badge( $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
}

/**
 * Human-readable weekly table, e.g. for the contact page.
 */
function tp_hours_table() {
	if ( ! tp_hours_enabled() ) {
		return '';
	}
	global $wp_locale;
	$rows = '';
	$today = (int) wp_date( 'N' );
	foreach ( tp_hours_schedule() as $d => $r ) {
		$rows .= sprintf(
			'<tr%1$s><th scope="row">%2$s</th><td>%3$s</td></tr>',
			$d === $today ? ' class="is-today" aria-current="date"' : '',
			esc_html( $wp_locale->get_weekday( $d % 7 ) ),
			esc_html( $r ? tp_short_time( $r[0] ) . ' – ' . tp_short_time( $r[1] ) : __( 'Closed', 'taxi-peninsula' ) )
		);
	}
	return '<table class="hours-table"><caption class="screen-reader-text">' . esc_html__( 'Phone hours', 'taxi-peninsula' ) . '</caption><tbody>' . $rows . '</tbody></table>';
}

/**
 * Structured data: opening hours for Google when not 24/7.
 */
add_filter( 'tp_business_schema', static function ( $node ) {
	if ( ! tp_hours_enabled() ) {
		return $node;
	}
	$names = array( 1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
	$specs = array();
	foreach ( tp_hours_schedule() as $d => $r ) {
		if ( $r ) {
			$specs[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $names[ $d ],
				'opens'     => $r[0],
				'closes'    => $r[1],
			);
		}
	}
	$node['openingHoursSpecification'] = $specs;
	return $node;
} );
