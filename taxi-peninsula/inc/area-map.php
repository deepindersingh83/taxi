<?php
/**
 * A small, self-contained map of Port Phillip, the Mornington Peninsula and
 * Western Port with your service areas marked. Drawn as inline SVG from
 * approximate coordinates: no map service, no API key, nothing loaded from
 * elsewhere. Suburbs it does not know are simply left off the map.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Known places: lower-case name => [lat, lng].
 */
function tp_map_places() {
	return array(
		'melbourne cbd'     => array( -37.814, 144.963 ),
		'melbourne'         => array( -37.814, 144.963 ),
		'melbourne airport' => array( -37.670, 144.843 ),
		'tullamarine'       => array( -37.670, 144.843 ),
		'avalon airport'    => array( -38.029, 144.472 ),
		'geelong'           => array( -38.149, 144.361 ),
		'werribee'          => array( -37.900, 144.660 ),
		'footscray'         => array( -37.800, 144.900 ),
		'st kilda'          => array( -37.867, 144.980 ),
		'brighton'          => array( -37.906, 144.987 ),
		'box hill'          => array( -37.819, 145.122 ),
		'ringwood'          => array( -37.815, 145.229 ),
		'clayton'           => array( -37.917, 145.120 ),
		'moorabbin'         => array( -37.937, 145.058 ),
		'cheltenham'        => array( -37.966, 145.051 ),
		'springvale'        => array( -37.950, 145.153 ),
		'noble park'        => array( -37.967, 145.176 ),
		'dingley village'   => array( -37.975, 145.124 ),
		'mordialloc'        => array( -38.006, 145.088 ),
		'keysborough'       => array( -38.000, 145.170 ),
		'dandenong'         => array( -37.988, 145.215 ),
		'chelsea'           => array( -38.052, 145.117 ),
		'patterson lakes'   => array( -38.068, 145.140 ),
		'carrum'            => array( -38.075, 145.122 ),
		'seaford'           => array( -38.104, 145.131 ),
		'carrum downs'      => array( -38.098, 145.180 ),
		'skye'              => array( -38.106, 145.217 ),
		'frankston'         => array( -38.144, 145.126 ),
		'langwarrin'        => array( -38.163, 145.181 ),
		'cranbourne'        => array( -38.099, 145.283 ),
		'narre warren'      => array( -38.027, 145.303 ),
		'berwick'           => array( -38.034, 145.350 ),
		'mount eliza'       => array( -38.190, 145.093 ),
		'baxter'            => array( -38.198, 145.159 ),
		'somerville'        => array( -38.225, 145.176 ),
		'pearcedale'        => array( -38.200, 145.235 ),
		'tyabb'             => array( -38.259, 145.186 ),
		'mornington'        => array( -38.218, 145.038 ),
		'mount martha'      => array( -38.266, 145.017 ),
		'hastings'          => array( -38.306, 145.188 ),
		'safety beach'      => array( -38.316, 144.998 ),
		'dromana'           => array( -38.338, 144.966 ),
		'mccrae'            => array( -38.349, 144.931 ),
		'rosebud'           => array( -38.356, 144.906 ),
		'capel sound'       => array( -38.367, 144.876 ),
		'tootgarook'        => array( -38.375, 144.850 ),
		'rye'               => array( -38.371, 144.825 ),
		'blairgowrie'       => array( -38.358, 144.777 ),
		'sorrento'          => array( -38.339, 144.741 ),
		'portsea'           => array( -38.321, 144.711 ),
		'red hill'          => array( -38.375, 145.020 ),
		'main ridge'        => array( -38.400, 144.980 ),
		'crib point'        => array( -38.355, 145.204 ),
		'balnarring'        => array( -38.371, 145.125 ),
		'merricks'          => array( -38.394, 145.087 ),
		'shoreham'          => array( -38.427, 145.050 ),
		'flinders'          => array( -38.474, 145.021 ),
		'cape schanck'      => array( -38.490, 144.890 ),
	);
}

/**
 * Map projection for the drawing: [x, y] in a 600 × 620 box.
 */
function tp_map_xy( $lat, $lng ) {
	$x = ( $lng - 144.32 ) / ( 145.48 - 144.32 ) * 600;
	$y = ( -37.62 - $lat ) / ( 38.56 - 37.62 ) * 620;
	return array( round( $x, 1 ), round( $y, 1 ) );
}

function tp_map_path( array $points, $close = true ) {
	$d = '';
	foreach ( $points as $i => $p ) {
		list( $x, $y ) = tp_map_xy( $p[0], $p[1] );
		$d .= ( 0 === $i ? 'M' : 'L' ) . $x . ' ' . $y;
	}
	return $d . ( $close ? 'Z' : '' );
}

/**
 * The map markup.
 *
 * @param string[] $areas Service area names.
 * @return string
 */
function tp_area_map( array $areas, $highlight = '' ) {
	$known  = tp_map_places();
	$marked = array();
	$here   = strtolower( trim( (string) $highlight ) );
	if ( $here && isset( $known[ $here ] ) ) {
		$marked[ $here ] = array( $highlight, $known[ $here ] );
	}
	foreach ( $areas as $name ) {
		$key = strtolower( trim( $name ) );
		if ( isset( $known[ $key ] ) && ! isset( $marked[ $key ] ) ) {
			$marked[ $key ] = array( $name, $known[ $key ] );
		}
	}
	if ( count( $marked ) < ( $here ? 1 : 2 ) ) {
		return '';
	}

	// Approximate coastlines (lat, lng).
	$bay     = array(
		array( -37.840, 144.930 ), array( -37.870, 144.975 ), array( -37.910, 144.990 ), array( -37.955, 145.005 ),
		array( -37.985, 145.060 ), array( -38.010, 145.085 ), array( -38.075, 145.120 ), array( -38.105, 145.125 ),
		array( -38.145, 145.115 ), array( -38.190, 145.080 ), array( -38.220, 145.035 ), array( -38.270, 145.015 ),
		array( -38.315, 144.990 ), array( -38.335, 144.960 ), array( -38.360, 144.910 ), array( -38.370, 144.830 ),
		array( -38.345, 144.750 ), array( -38.325, 144.705 ), array( -38.305, 144.660 ), array( -38.290, 144.615 ),
		array( -38.270, 144.665 ), array( -38.170, 144.720 ), array( -38.140, 144.710 ), array( -38.115, 144.650 ),
		array( -38.150, 144.560 ), array( -38.145, 144.400 ), array( -38.080, 144.390 ), array( -38.040, 144.450 ),
		array( -37.970, 144.700 ), array( -37.920, 144.760 ), array( -37.870, 144.830 ), array( -37.865, 144.890 ),
	);
	$sea     = array(
		array( -38.305, 144.660 ), array( -38.330, 144.700 ), array( -38.400, 144.800 ), array( -38.440, 144.850 ),
		array( -38.490, 144.890 ), array( -38.480, 145.020 ), array( -38.470, 145.030 ), array( -38.430, 145.050 ),
		array( -38.420, 145.070 ), array( -38.400, 145.120 ), array( -38.390, 145.160 ), array( -38.370, 145.220 ),
		array( -38.350, 145.200 ), array( -38.305, 145.195 ), array( -38.250, 145.250 ), array( -38.220, 145.310 ),
		array( -38.210, 145.380 ), array( -38.300, 145.480 ), array( -38.560, 145.480 ), array( -38.560, 144.320 ),
		array( -38.330, 144.320 ), array( -38.280, 144.490 ), array( -38.270, 144.530 ), array( -38.290, 144.610 ),
	);
	$phillip = array( array( -38.450, 145.120 ), array( -38.440, 145.250 ), array( -38.470, 145.350 ), array( -38.520, 145.330 ), array( -38.530, 145.150 ), array( -38.500, 145.120 ) );
	$french  = array( array( -38.300, 145.300 ), array( -38.280, 145.400 ), array( -38.360, 145.430 ), array( -38.400, 145.330 ), array( -38.350, 145.290 ) );

	$dots   = '';
	$labels = '';
	$boxes  = array();
	$fits   = static function ( $b ) use ( &$boxes ) {
		if ( $b[0] < 2 || $b[2] > 598 || $b[1] < 2 || $b[3] > 618 ) {
			return false;
		}
		foreach ( $boxes as $o ) {
			if ( $b[0] < $o[2] && $b[2] > $o[0] && $b[1] < $o[3] && $b[3] > $o[1] ) {
				return false;
			}
		}
		return true;
	};
	// Dots first, so labels avoid them.
	$points = array();
	foreach ( $marked as $key => $m ) {
		list( $x, $y ) = tp_map_xy( $m[1][0], $m[1][1] );
		$is_here       = $key === $here;
		$points[]      = array( $m[0], $x, $y, $is_here );
		$boxes[]       = array( $x - 6, $y - 6, $x + 6, $y + 6 );
		$dots         .= sprintf( '<g class="amap__pin%4$s"><circle cx="%1$s" cy="%2$s" r="%5$s" class="amap__halo"/><circle cx="%1$s" cy="%2$s" r="%6$s" class="amap__dot"/><title>%3$s</title></g>', $x, $y, esc_html( $m[0] ), $is_here ? ' amap__pin--here' : '', $is_here ? 16 : 9, $is_here ? 8 : 5.5 );
	}
	foreach ( $points as $p ) {
		list( $name, $x, $y, $is_here ) = $p;
		$w = ( $is_here ? 8.2 : 6.6 ) * mb_strlen( $name ) + 6;
		$h = 15;
		$options = array(
			array( $x + 10, $y + 4.5, 'start', array( $x + 8, $y - 9, $x + 10 + $w, $y + 7 ) ),
			array( $x - 10, $y + 4.5, 'end', array( $x - 10 - $w, $y - 9, $x - 8, $y + 7 ) ),
			array( $x, $y - 12, 'middle', array( $x - $w / 2, $y - 12 - $h, $x + $w / 2, $y - 10 ) ),
			array( $x, $y + 22, 'middle', array( $x - $w / 2, $y + 9, $x + $w / 2, $y + 25 ) ),
		);
		foreach ( $options as $o ) {
			if ( $fits( $o[3] ) ) {
				$boxes[] = $o[3];
				$labels .= sprintf( '<text x="%1$s" y="%2$s" text-anchor="%3$s" class="amap__label%5$s">%4$s</text>', $o[0], $o[1], $o[2], esc_html( $name ), $is_here ? ' amap__label--here' : '' );
				break;
			}
		}
	}

	$text = static function ( $lat, $lng, $label, $class ) {
		list( $x, $y ) = tp_map_xy( $lat, $lng );
		return sprintf( '<text x="%s" y="%s" text-anchor="middle" class="%s">%s</text>', $x, $y, esc_attr( $class ), esc_html( $label ) );
	};

	$id = wp_unique_id( 'amap-' );
	ob_start();
	?>
	<figure class="amap">
		<svg viewBox="0 0 600 620" role="img" aria-labelledby="<?php echo esc_attr( $id ); ?>-t <?php echo esc_attr( $id ); ?>-d" focusable="false">
			<title id="<?php echo esc_attr( $id ); ?>-t"><?php esc_html_e( 'Map of the areas we cover', 'taxi-peninsula' ); ?></title>
			<desc id="<?php echo esc_attr( $id ); ?>-d">
				<?php
				/* translators: %s: list of suburbs */
				echo esc_html( sprintf( __( 'Port Phillip Bay and the Mornington Peninsula, with these places marked: %s.', 'taxi-peninsula' ), implode( ', ', wp_list_pluck( $marked, 0 ) ) ) );
				?>
			</desc>
			<rect width="600" height="620" class="amap__land"/>
			<path d="<?php echo esc_attr( tp_map_path( $bay ) ); ?>" class="amap__water"/>
			<path d="<?php echo esc_attr( tp_map_path( $sea ) ); ?>" class="amap__water"/>
			<path d="<?php echo esc_attr( tp_map_path( $phillip ) ); ?>" class="amap__land amap__island"/>
			<path d="<?php echo esc_attr( tp_map_path( $french ) ); ?>" class="amap__land amap__island"/>
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
			echo $text( -38.06, 144.86, __( 'Port Phillip Bay', 'taxi-peninsula' ), 'amap__sea' );
			echo $text( -38.40, 145.27, __( 'Western Port', 'taxi-peninsula' ), 'amap__sea' );
			echo $text( -38.535, 144.62, __( 'Bass Strait', 'taxi-peninsula' ), 'amap__sea' );
			echo $text( -38.43, 144.99, __( 'Mornington Peninsula', 'taxi-peninsula' ), 'amap__region' );
			echo $dots . $labels;
			// phpcs:enable
			?>
		</svg>
		<figcaption><?php esc_html_e( 'A guide only, not to scale. Not on the map? Ask us — we travel further on request.', 'taxi-peninsula' ); ?></figcaption>
	</figure>
	<?php
	return ob_get_clean();
}

/**
 * Rough drive times from a suburb to common destinations (straight-line
 * distance × 1.3 at an average 60 km/h, rounded to 5 minutes).
 *
 * @param string $name Suburb.
 * @return array[] Each: [destination, minutes].
 */
function tp_area_travel_times( $name ) {
	$known = tp_map_places();
	$from  = $known[ strtolower( trim( (string) $name ) ) ] ?? null;
	if ( ! $from ) {
		return array();
	}
	$dests = array(
		__( 'Melbourne CBD', 'taxi-peninsula' )          => array( -37.814, 144.963 ),
		__( 'Melbourne Airport', 'taxi-peninsula' )      => array( -37.670, 144.843 ),
		__( 'Frankston Hospital', 'taxi-peninsula' )     => array( -38.151, 145.131 ),
		__( 'Monash Medical Centre', 'taxi-peninsula' )  => array( -37.921, 145.123 ),
		__( 'Rosebud Hospital', 'taxi-peninsula' )       => array( -38.360, 144.905 ),
	);
	$out = array();
	foreach ( $dests as $label => $to ) {
		$dlat = deg2rad( $to[0] - $from[0] );
		$dlng = deg2rad( $to[1] - $from[1] );
		$a    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $from[0] ) ) * cos( deg2rad( $to[0] ) ) * sin( $dlng / 2 ) ** 2;
		$km   = 6371 * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		if ( $km < 2 ) {
			continue;
		}
		$out[] = array( $label, max( 5, (int) ( round( $km * 1.3 / 60 * 60 / 5 ) * 5 ) ) );
	}
	return $out;
}
