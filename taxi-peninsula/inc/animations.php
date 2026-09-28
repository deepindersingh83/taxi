<?php
/**
 * Animated illustrations: a road with moving taxis (hero, page banners, footer)
 * and two scenes — a door-to-door wheelchair pickup and an airport drop-off/pickup.
 *
 * Pure inline SVG + CSS (no video, no libraries). Decorative strips are hidden
 * from screen readers; scenes have a text description. Motion stops for visitors
 * who ask their device to reduce motion, and anyone can pause it with the
 * "Pause animations" button. Settings: Customize → Design → Animations.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Animation settings with defaults.
 */
function tp_anim( $key ) {
	$defaults = array(
		'on'     => true,
		'hero'   => true,
		'banner' => true,
		'footer' => true,
	);
	return (bool) get_theme_mod( 'tp_anim_' . $key, $defaults[ $key ] ?? false );
}

/**
 * Whether the moving road strip shows in a given place.
 */
function tp_anim_road_in( $place ) {
	return tp_anim( 'on' ) && tp_anim( $place );
}

/* --------------------------------------------------------------------------
 * Vehicle and people drawings (SVG fragments, facing right)
 * ----------------------------------------------------------------------- */

function tp_svg_wheel( $x, $y, $r, $class = '' ) {
	$hub = round( $r * 0.42, 1 );
	return sprintf(
		'<g transform="translate(%1$s %2$s)"><g class="tp-wheel %4$s"><circle r="%3$s" fill="#111827"/><circle r="%5$s" fill="#cbd5e1"/><path d="M0 -%5$s V%5$s M-%5$s 0 H%5$s" stroke="#64748b" stroke-width="1.4"/></g></g>',
		$x,
		$y,
		$r,
		esc_attr( $class ),
		$hub
	);
}

/**
 * Standard taxi (sedan), 140 × 58.
 */
function tp_svg_sedan( $wheel_class = '' ) {
	return '<path class="tp-f-accent" d="M4 40Q4 31 14 29L38 26 54 12Q58 8 65 8H97Q103 8 107 13L120 26 130 28Q138 30 138 39V45Q138 49 133 49H9Q4 49 4 45Z"/>'
		. '<path fill="#1e293b" d="M58 13H77V26H45Z"/><path fill="#1e293b" d="M81 13H97Q101 13 104 17L111 26H81Z"/>'
		. '<path d="M79 26V47" stroke="#0f172a" stroke-opacity=".25"/>'
		. '<rect class="tp-f-primary" x="6" y="33" width="130" height="4"/>'
		. '<rect x="69" y="1" width="26" height="8" rx="2" fill="#fff" stroke="#0f172a" stroke-opacity=".25"/>'
		. '<text x="82" y="7.3" text-anchor="middle" font-size="6" font-weight="700" font-family="Arial, sans-serif" class="tp-f-primary">TAXI</text>'
		. '<rect x="132" y="31" width="6" height="4" rx="1" fill="#fff7c2"/><rect x="4" y="31" width="4" height="4" rx="1" fill="#ef4444"/>'
		. tp_svg_wheel( 34, 48, 10, $wheel_class ) . tp_svg_wheel( 110, 48, 10, $wheel_class );
}

/**
 * Wheelchair-accessible maxi taxi (van) with a rear ramp, 200 × 98.
 */
function tp_svg_van( $wheel_class = '', $ramp_class = '' ) {
	$isa = '<g transform="translate(25 65) scale(.67)" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M12 7v6h5l2 5h2"/><path d="M12 10h5"/><path d="M8.6 10.9a5.5 5.5 0 1 0 7.6 7.4"/></g>';
	return '<rect class="tp-ramp ' . esc_attr( $ramp_class ) . '" x="3" y="40" width="5" height="45" rx="1.5" fill="#94a3b8"/>'
		. '<path fill="#fff" stroke="#94a3b8" stroke-width="1.5" d="M8 22Q8 10 20 10H148Q158 10 164 18L182 44Q192 46 192 56V80Q192 86 186 86H14Q8 86 8 80Z"/>'
		. '<path fill="#1e293b" d="M151 16H160Q163 16 165 19L179 43H151Z"/>'
		. '<rect x="22" y="18" width="36" height="24" rx="3" fill="#1e293b"/><rect x="64" y="18" width="36" height="24" rx="3" fill="#1e293b"/><rect x="106" y="18" width="38" height="24" rx="3" fill="#1e293b"/>'
		. '<rect class="tp-f-accent" x="8" y="50" width="184" height="8"/><rect class="tp-f-primary" x="8" y="58" width="184" height="3"/>'
		. '<rect x="24" y="64" width="18" height="18" rx="3" fill="#1d4ed8"/>' . $isa
		. '<text x="112" y="77" text-anchor="middle" font-size="10" font-weight="800" letter-spacing=".5" font-family="Arial, sans-serif" class="tp-f-primary">MAXI TAXI</text>'
		. '<rect class="tp-f-accent" x="84" y="3" width="34" height="8" rx="2"/>'
		. '<rect x="186" y="62" width="6" height="5" rx="1" fill="#fff7c2"/><rect x="8" y="62" width="4" height="8" rx="1" fill="#ef4444"/>'
		. tp_svg_wheel( 44, 86, 12, $wheel_class ) . tp_svg_wheel( 156, 86, 12, $wheel_class );
}

/**
 * Passenger in a wheelchair, 44 × 52.
 */
function tp_svg_rider() {
	return '<circle cx="16" cy="38" r="12" fill="none" stroke="#334155" stroke-width="3"/><circle cx="16" cy="38" r="2" fill="#334155"/>'
		. '<circle cx="36" cy="48" r="3.5" fill="none" stroke="#334155" stroke-width="2.5"/>'
		. '<path d="M11 20V36H32L36 44" fill="none" stroke="#334155" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<path class="tp-f-link" d="M10 15Q10 11 14 11H20Q24 11 24 15V33H10Z"/>'
		. '<path d="M17 31H30L32 43" fill="none" stroke="#1e293b" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<path d="M19 16L25 25H31" fill="none" stroke="#c9936a" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<circle cx="17" cy="5" r="5" fill="#c9936a"/>';
}

/**
 * Traveller walking with a suitcase, 30 × 52.
 */
function tp_svg_walker() {
	return '<g class="tp-leg tp-leg--a"><path d="M11 32L8 51" stroke="#1e293b" stroke-width="4.5" stroke-linecap="round"/></g>'
		. '<g class="tp-leg tp-leg--b"><path d="M14 32L17 51" stroke="#1e293b" stroke-width="4.5" stroke-linecap="round"/></g>'
		. '<path class="tp-f-link" d="M6 15Q6 11 10 11H16Q20 11 20 15V33H6Z"/>'
		. '<path d="M17 16L22 28" stroke="#c9936a" stroke-width="3.5" stroke-linecap="round"/>'
		. '<rect x="19" y="30" width="11" height="17" rx="2" fill="#334155"/><path d="M22 30V27H27V30" fill="none" stroke="#334155" stroke-width="1.6"/>'
		. '<circle cx="23" cy="49" r="1.6" fill="#111827"/><circle cx="13" cy="5" r="5" fill="#c9936a"/>';
}

/**
 * Passenger jet, 120 × 40.
 */
function tp_svg_plane() {
	$windows = '';
	for ( $x = 30; $x <= 100; $x += 7 ) {
		$windows .= '<circle cx="' . $x . '" cy="20" r="1.5" fill="#1e293b"/>';
	}
	return '<path class="tp-f-primary" d="M8 17L2 2H14L28 17Z"/>'
		. '<path fill="#fff" stroke="#94a3b8" d="M6 22Q6 16 14 16H96Q112 16 118 22Q112 28 96 28H14Q6 28 6 22Z"/>'
		. $windows
		. '<path fill="#cbd5e1" stroke="#94a3b8" d="M50 24H70L56 38H46Z"/><path class="tp-f-accent" d="M108 18Q113 19 116 22H106Z"/>';
}

function tp_svg_cloud( $x, $y, $s = 1 ) {
	return sprintf(
		'<g transform="translate(%1$s %2$s) scale(%3$s)"><g class="tp-cloud"><path fill="var(--tp-cloud)" d="M10 30Q0 30 0 21Q0 12 10 12Q14 0 28 2Q40 0 44 12Q56 12 56 22Q56 30 46 30Z"/></g></g>',
		$x,
		$y,
		$s
	);
}

/* --------------------------------------------------------------------------
 * Road strip and scenes
 * ----------------------------------------------------------------------- */

/**
 * Decorative road with a maxi taxi and taxis driving by.
 *
 * @param string $variant hero | banner | footer.
 */
function tp_road_strip( $variant = 'hero' ) {
	$svg = static function ( $class, $w, $h, $body ) {
		return sprintf( '<svg class="tp-road__v %1$s" viewBox="0 0 %2$d %3$d" width="%2$d" height="%3$d" focusable="false">%4$s</svg>', esc_attr( $class ), $w, $h, $body );
	};
	return '<div class="tp-road tp-road--' . esc_attr( $variant ) . ' tp-anim" aria-hidden="true">'
		. $svg( 'tp-road__v--far', 140, 58, tp_svg_sedan( 'tp-spin' ) )
		. $svg( 'tp-road__v--van', 200, 98, tp_svg_van( 'tp-spin' ) )
		. $svg( 'tp-road__v--car', 140, 58, tp_svg_sedan( 'tp-spin' ) )
		. '</div>';
}

function tp_the_road_strip( $variant = 'hero' ) {
	echo tp_road_strip( $variant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG built above.
}

function tp_scene_ground() {
	return '<rect y="196" width="800" height="5" fill="#cbd5e1"/><rect y="200" width="800" height="40" fill="#475569"/>'
		. '<path d="M0 221H800" stroke="#f8fafc" stroke-width="2.5" stroke-dasharray="22 18" opacity=".7"/>';
}

function tp_scene_sky( $id ) {
	return '<defs><linearGradient id="' . esc_attr( $id ) . '-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--tp-sky-1)"/><stop offset="1" stop-color="var(--tp-sky-2)"/></linearGradient></defs>'
		. '<rect width="800" height="200" fill="url(#' . esc_attr( $id ) . '-sky)"/>'
		. '<circle class="tp-f-accent" cx="470" cy="40" r="20" opacity=".85"/>'
		. tp_svg_cloud( 90, 26, 1 ) . tp_svg_cloud( 330, 12, .8 ) . tp_svg_cloud( 560, 40, .7 );
}

/**
 * Animated scene markup.
 *
 * @param string $name pickup | airport.
 */
function tp_scene( $name = 'pickup' ) {
	static $n = 0;
	++$n;
	$id = 'tp-scene-' . $n;

	if ( 'airport' === $name ) {
		$title = __( 'Airport drop-offs and pickups', 'taxi-peninsula' );
		$desc  = __( 'A taxi drops a traveller at Departures while a plane takes off. Then a maxi taxi pulls up at Arrivals, lowers its ramp and a passenger in a wheelchair rolls aboard.', 'taxi-peninsula' );
		$tower = '<rect x="300" y="60" width="18" height="136" fill="#cbd5e1"/><path class="tp-f-primary" d="M290 44H328L322 64H296Z"/><rect x="296" y="48" width="26" height="8" fill="#93c5fd"/>';
		$mull  = '';
		for ( $x = 380; $x < 800; $x += 30 ) {
			$mull .= '<path d="M' . $x . ' 102V150" stroke="#fff" stroke-opacity=".7"/>';
		}
		$building = '<rect class="tp-f-primary" x="350" y="78" width="450" height="10"/>'
			. '<rect x="360" y="88" width="440" height="108" fill="#e2e8f0"/>'
			. '<rect x="360" y="102" width="440" height="48" fill="#93c5fd" opacity=".65"/>' . $mull
			. '<rect x="372" y="152" width="40" height="44" fill="#334155"/><path d="M392 152V196" stroke="#94a3b8"/>'
			. '<rect x="690" y="152" width="40" height="44" fill="#334155"/><path d="M710 152V196" stroke="#94a3b8"/>'
			. '<rect class="tp-f-accent" x="356" y="90" width="72" height="11" rx="2"/><text x="392" y="98.5" text-anchor="middle" font-size="8" font-weight="800" font-family="Arial, sans-serif" class="tp-f-primary">ARRIVALS</text>'
			. '<rect class="tp-f-accent" x="670" y="90" width="80" height="11" rx="2"/><text x="710" y="98.5" text-anchor="middle" font-size="8" font-weight="800" font-family="Arial, sans-serif" class="tp-f-primary">DEPARTURES</text>';
		$body = tp_scene_sky( $id )
			. '<g class="tp-ap-plane"><g transform="translate(-60 -20)">' . tp_svg_plane() . '</g></g>'
			. $tower . $building . tp_scene_ground()
			. '<g class="tp-ap-rider">' . tp_svg_rider() . '</g>'
			. '<g class="tp-ap-car">' . tp_svg_sedan( 'tp-ap-carwheel' ) . '</g>'
			. '<g class="tp-ap-walker">' . tp_svg_walker() . '</g>'
			. '<g class="tp-ap-van">' . tp_svg_van( 'tp-ap-vanwheel', 'tp-ap-ramp' ) . '</g>';
	} else {
		$name  = 'pickup';
		$title = __( 'Door-to-door wheelchair pickup', 'taxi-peninsula' );
		$desc  = __( 'A maxi taxi pulls up outside a house, lowers its rear ramp, and a passenger in a wheelchair rolls aboard before the taxi drives away.', 'taxi-peninsula' );
		$house = '<rect x="70" y="112" width="150" height="84" fill="#f1f5f9" stroke="#cbd5e1"/>'
			. '<path class="tp-f-primary" d="M60 116L145 62L230 116Z"/>'
			. '<rect class="tp-f-accent" x="130" y="146" width="28" height="50" rx="2"/><circle cx="152" cy="172" r="2" fill="#1e293b"/>'
			. '<rect x="84" y="130" width="32" height="26" fill="#bfdbfe" stroke="#94a3b8"/><rect x="174" y="130" width="32" height="26" fill="#bfdbfe" stroke="#94a3b8"/>'
			. '<path d="M126 196H162L166 200H122Z" fill="#94a3b8"/>';
		$tree  = '<rect x="252" y="150" width="8" height="46" fill="#8b5e34"/><circle cx="256" cy="140" r="24" fill="#16a34a"/><circle cx="244" cy="150" r="14" fill="#22c55e"/>'
			. '<rect x="604" y="140" width="8" height="56" fill="#8b5e34"/><circle cx="608" cy="128" r="28" fill="#15803d"/><circle cx="624" cy="140" r="16" fill="#22c55e"/>';
		$body  = tp_scene_sky( $id )
			. '<path d="M0 160Q120 120 260 150T520 140T800 150V196H0Z" fill="var(--tp-hill)"/>'
			. $house . $tree . tp_scene_ground()
			. '<g class="tp-pk-rider">' . tp_svg_rider() . '</g>'
			. '<g class="tp-pk-van">' . tp_svg_van( 'tp-pk-wheel', 'tp-pk-ramp' ) . '</g>';
	}

	return sprintf(
		'<div class="tp-scene tp-scene--%1$s tp-anim">'
		. '<svg class="tp-scene__svg" viewBox="0 0 800 240" role="img" aria-labelledby="%2$s-t %2$s-d" focusable="false"><title id="%2$s-t">%3$s</title><desc id="%2$s-d">%4$s</desc>%5$s</svg>'
		. '%6$s</div>',
		esc_attr( $name ),
		esc_attr( $id ),
		esc_html( $title ),
		esc_html( $desc ),
		$body,
		tp_anim( 'on' ) ? tp_motion_button( 'tp-scene__pause' ) : ''
	);
}

function tp_the_scene( $name = 'pickup' ) {
	echo tp_scene( $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG built above.
}

/**
 * "Pause animations" toggle (all of them share one setting, remembered per visitor).
 */
function tp_motion_button( $class = 'a11y-btn' ) {
	return sprintf(
		'<button type="button" class="%1$s" data-motion-toggle aria-pressed="false" title="%4$s">%2$s%3$s<span>%4$s</span></button>',
		esc_attr( $class ),
		tp_icon( 'pause', 'tp-motion-icon tp-motion-icon--pause' ),
		tp_icon( 'play', 'tp-motion-icon tp-motion-icon--play' ),
		esc_html__( 'Pause animations', 'taxi-peninsula' )
	);
}

/**
 * [tp_scene name="pickup|airport|road"]
 */
add_shortcode(
	'tp_scene',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'name' => 'pickup' ), $atts, 'tp_scene' );
		if ( 'road' === $atts['name'] ) {
			return tp_anim( 'on' ) ? '<div class="tp-component tp-road-wrap">' . tp_road_strip( 'content' ) . '</div>' : '';
		}
		return '<div class="tp-component">' . tp_scene( $atts['name'] ) . '</div>';
	}
);

/**
 * Service pages get a matching scene: the airport one for airport services,
 * the doorstep pickup for everything else.
 */
function tp_service_scene_name( $post = null ) {
	$post = get_post( $post );
	return $post && preg_match( '/airport|flight|terminal/i', $post->post_title . ' ' . $post->post_name ) ? 'airport' : 'pickup';
}

/* --------------------------------------------------------------------------
 * Assets, body class, Customizer
 * ----------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', static function () {
	wp_enqueue_style( 'tp-animations', TP_URI . '/assets/css/animations.css', array( 'tp-main' ), TP_VERSION );
}, 15 );

add_filter( 'body_class', static function ( $classes ) {
	if ( ! tp_anim( 'on' ) ) {
		$classes[] = 'tp-anim-off';
	}
	return $classes;
} );

add_action( 'customize_register', static function ( WP_Customize_Manager $wp_customize ) {
	$wp_customize->add_section(
		'tp_design_anim',
		array(
			'title'       => __( 'Animations', 'taxi-peninsula' ),
			'panel'       => 'tp_design',
			'description' => __( 'Moving taxis and the pickup/airport scenes. Visitors can pause them, and they stay still for anyone whose device asks for reduced motion. The scenes are also on the home page (Home page layout → “How a trip works”), on service pages, and anywhere you add the shortcode [tp_scene name="pickup"], [tp_scene name="airport"] or [tp_scene name="road"].', 'taxi-peninsula' ),
		)
	);
	foreach ( array(
		'on'     => array( __( 'Turn animations on', 'taxi-peninsula' ), __( 'When off, the scenes show as still pictures and the moving road is hidden.', 'taxi-peninsula' ) ),
		'hero'   => array( __( 'Taxis driving along the bottom of the home page hero', 'taxi-peninsula' ), '' ),
		'banner' => array( __( 'Taxis driving under page title banners', 'taxi-peninsula' ), '' ),
		'footer' => array( __( 'Taxis driving along the top of the footer', 'taxi-peninsula' ), '' ),
	) as $key => $labels ) {
		$wp_customize->add_setting( 'tp_anim_' . $key, array( 'default' => true, 'sanitize_callback' => 'tp_sanitize_checkbox' ) );
		$wp_customize->add_control(
			'tp_anim_' . $key,
			array(
				'section'         => 'tp_design_anim',
				'type'            => 'checkbox',
				'label'           => $labels[0],
				'description'     => $labels[1],
				'active_callback' => 'on' === $key ? null : static fn() => tp_anim( 'on' ),
			)
		);
	}
}, 40 );
