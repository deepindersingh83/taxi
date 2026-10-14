<?php
/**
 * Brand touches outside the page itself:
 * - Branded HTML emails (logo, colours, buttons) with a plain-text copy.
 * - App icons (favicon, Apple touch icon) and a web app manifest, generated in
 *   your colours until you upload a Site Icon.
 * - Share images: a branded 1200 × 630 card with the page title for pages
 *   without a featured image (Facebook, WhatsApp, LinkedIn, Messages).
 * - The app-style bottom bar on phones.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * HTML emails.
 * ---------------------------------------------------------------------- */

/**
 * Turn the theme's plain-text emails into branded HTML. Emails are marked by
 * tp_mail_headers(); anything sent by plugins is left alone.
 */
add_filter( 'wp_mail', 'tp_brand_mail', 20 );
function tp_brand_mail( $args ) {
	$headers = is_array( $args['headers'] ) ? $args['headers'] : explode( "\n", str_replace( "\r\n", "\n", (string) $args['headers'] ) );
	$ours    = false;
	foreach ( $headers as $i => $h ) {
		if ( 0 === stripos( trim( $h ), 'X-TP-Mail:' ) ) {
			$ours = true;
			unset( $headers[ $i ] );
		} elseif ( 0 === stripos( trim( $h ), 'Content-Type:' ) ) {
			unset( $headers[ $i ] );
		}
	}
	if ( ! $ours ) {
		return $args;
	}
	if ( ! apply_filters( 'tp_html_emails', true ) ) {
		$headers[]       = 'Content-Type: text/plain; charset=UTF-8';
		$args['headers'] = array_values( $headers );
		return $args;
	}
	$headers[]           = 'Content-Type: text/html; charset=UTF-8';
	$args['headers']     = array_values( $headers );
	$GLOBALS['tp_alt']   = (string) $args['message'];
	$args['message']     = tp_mail_html( (string) $args['subject'], (string) $args['message'] );
	return $args;
}

add_action( 'phpmailer_init', static function ( $mailer ) {
	if ( ! empty( $GLOBALS['tp_alt'] ) ) {
		$mailer->AltBody   = $GLOBALS['tp_alt']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$GLOBALS['tp_alt'] = '';
	}
} );

/**
 * Render a plain-text email body as branded HTML.
 *
 * - "Label: value" lines become a details table.
 * - "Some text: https://…" lines become buttons.
 * - "- item" lines become a list; other lines are paragraphs with links.
 */
function tp_mail_html( $subject, $text ) {
	$c     = tp_design_colors();
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$flogo = (int) tp_design( 'logo_footer' );
	$img   = $flogo ? wp_get_attachment_image_url( $flogo, 'medium' ) : '';
	$main  = (int) get_theme_mod( 'custom_logo' );
	$pill  = ! $img && $main;
	if ( $pill ) {
		$img = wp_get_attachment_image_url( $main, 'medium' );
	}
	$font  = 'Arial, Helvetica, sans-serif';

	$link = static function ( $html ) use ( $c ) {
		return preg_replace( '#(https?://[^\s<]+)#', '<a href="$1" style="color:' . esc_attr( $c['link'] ) . ';word-break:break-all;">$1</a>', $html );
	};

	$blocks = preg_split( "/\n\s*\n/", trim( str_replace( "\r\n", "\n", $text ) ) );
	$body   = '';
	foreach ( $blocks as $block ) {
		$lines = array_values( array_filter( array_map( 'rtrim', explode( "\n", $block ) ), 'strlen' ) );
		if ( ! $lines ) {
			continue;
		}
		$pairs = array();
		foreach ( $lines as $line ) {
			if ( preg_match( '/^([^:]{1,40}):\s+(.+)$/u', $line, $m ) && ! preg_match( '#^https?://#', trim( $m[2] ) ) ) {
				$pairs[] = array( $m[1], $m[2] );
			}
		}
		if ( count( $lines ) >= 3 && count( $pairs ) === count( $lines ) ) {
			$body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:8px 0 20px;background:' . esc_attr( $c['bg_alt'] ) . ';border-radius:8px;">';
			foreach ( $pairs as $p ) {
				$body .= '<tr><td style="padding:8px 14px;border-bottom:1px solid #e3e8f0;color:#4a5670;font:14px/1.4 ' . $font . ';width:38%;vertical-align:top;">' . esc_html( $p[0] ) . '</td><td style="padding:8px 14px;border-bottom:1px solid #e3e8f0;color:#16213a;font:bold 14px/1.4 ' . $font . ';vertical-align:top;">' . $link( esc_html( $p[1] ) ) . '</td></tr>';
			}
			$body .= '</table>';
			continue;
		}
		$list = array();
		foreach ( $lines as $line ) {
			if ( preg_match( '/^(.{3,80}?):\s+(https?:\/\/\S+)$/u', $line, $m ) ) {
				if ( $list ) {
					$body .= '<ul style="margin:0 0 16px;padding-left:20px;font:15px/1.6 ' . $font . ';color:#16213a;">' . implode( '', $list ) . '</ul>';
					$list  = array();
				}
				$body .= '<p style="margin:18px 0;"><a href="' . esc_url( $m[2] ) . '" style="display:inline-block;padding:13px 22px;border-radius:999px;background:' . esc_attr( $c['accent'] ) . ';color:' . esc_attr( $c['on_accent'] ) . ';font:bold 15px/1 ' . $font . ';text-decoration:none;">' . esc_html( rtrim( $m[1], ' :' ) ) . '</a></p>';
			} elseif ( preg_match( '/^-\s+(.+)$/u', $line, $m ) ) {
				$list[] = '<li>' . $link( esc_html( $m[1] ) ) . '</li>';
			} else {
				if ( $list ) {
					$body .= '<ul style="margin:0 0 16px;padding-left:20px;font:15px/1.6 ' . $font . ';color:#16213a;">' . implode( '', $list ) . '</ul>';
					$list  = array();
				}
				$body .= '<p style="margin:0 0 14px;font:15px/1.6 ' . $font . ';color:#16213a;">' . $link( esc_html( $line ) ) . '</p>';
			}
		}
		if ( $list ) {
			$body .= '<ul style="margin:0 0 16px;padding-left:20px;font:15px/1.6 ' . $font . ';color:#16213a;">' . implode( '', $list ) . '</ul>';
		}
	}

	$brand = $img
		? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $site ) . '" height="48" style="display:block;height:48px;width:auto;border:0;' . ( $pill ? 'background:#ffffff;padding:6px 10px;border-radius:8px;' : '' ) . '">'
		: '<span style="font:bold 22px/1.2 ' . $font . ';color:#ffffff;">' . esc_html( $site ) . '</span>';

	ob_start();
	?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $subject ); ?></title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;">
<div style="display:none;max-height:0;overflow:hidden;"><?php echo esc_html( wp_trim_words( $text, 18, '…' ) ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;">
<tr><td align="center" style="padding:24px 12px;">
	<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;">
		<tr><td style="background:<?php echo esc_attr( $c['primary'] ); ?>;padding:22px 28px;"><?php echo $brand; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></td></tr>
		<tr><td style="height:6px;line-height:6px;font-size:0;background:<?php echo esc_attr( $c['accent'] ); ?>;">&nbsp;</td></tr>
		<tr><td style="padding:28px;">
			<h1 style="margin:0 0 18px;font:bold 22px/1.3 <?php echo esc_attr( $font ); ?>;color:<?php echo esc_attr( $c['heading'] ); ?>;"><?php echo esc_html( $subject ); ?></h1>
			<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
		</td></tr>
		<tr><td style="padding:18px 28px;background:<?php echo esc_attr( $c['bg_alt'] ); ?>;font:13px/1.6 <?php echo esc_attr( $font ); ?>;color:#4a5670;">
			<strong style="color:#16213a;"><?php echo esc_html( $site ); ?></strong><br>
			<a href="<?php echo esc_url( tp_phone_href() ); ?>" style="color:<?php echo esc_attr( $c['link'] ); ?>;"><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a> ·
			<a href="mailto:<?php echo esc_attr( tp_opt( 'email' ) ); ?>" style="color:<?php echo esc_attr( $c['link'] ); ?>;"><?php echo esc_html( tp_opt( 'email' ) ); ?></a><br>
			<?php echo esc_html( tp_opt( 'location' ) ); ?><?php echo tp_setting( 'abn' ) ? ' · ABN ' . esc_html( tp_setting( 'abn' ) ) : ''; ?>
		</td></tr>
	</table>
</td></tr>
</table>
</body>
</html>
	<?php
	return trim( ob_get_clean() );
}

/* -------------------------------------------------------------------------
 * App icons and web app manifest.
 * ---------------------------------------------------------------------- */

/**
 * The fallback app icon as SVG (accent tile with the wheelchair symbol).
 */
function tp_icon_svg() {
	$c = tp_design_colors();
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . esc_attr( $c['accent'] ) . '"/>'
		. '<g transform="translate(12 12) scale(1.67)" fill="none" stroke="' . esc_attr( $c['on_accent'] ) . '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4" r="2"/><path d="M12 7v6h5l2 5h2"/><path d="M12 10h5"/><path d="M8.6 10.9a5.5 5.5 0 1 0 7.6 7.4"/></g></svg>';
}

function tp_hex_to_gd( $im, $hex, $alpha = 0 ) {
	list( $r, $g, $b ) = tp_hex_rgb( $hex );
	return imagecolorallocatealpha( $im, $r, $g, $b, $alpha );
}

/**
 * A round-capped stroke, drawn by stamping circles (GD's thick lines are blocky).
 */
function tp_gd_stroke( $im, $x1, $y1, $x2, $y2, $d, $color ) {
	$len   = max( 1, hypot( $x2 - $x1, $y2 - $y1 ) );
	$steps = (int) ceil( $len );
	for ( $i = 0; $i <= $steps; $i++ ) {
		$t = $i / $steps;
		imagefilledellipse( $im, (int) round( $x1 + ( $x2 - $x1 ) * $t ), (int) round( $y1 + ( $y2 - $y1 ) * $t ), $d, $d, $color );
	}
}

/**
 * Draw the wheelchair symbol (24-unit grid) onto a GD image. Draw on a large
 * canvas and scale down (tp_gd_supersample) for smooth edges.
 */
function tp_gd_wheelchair( $im, $x, $y, $size, $color, $weight ) {
	$u  = $size / 24;
	$d  = (int) max( 2, round( $weight * $u ) );
	$pt = static fn( $px, $py ) => array( $x + $px * $u, $y + $py * $u );
	$segments = array(
		array( 12, 7, 12, 13 ),
		array( 12, 13, 17, 13 ),
		array( 17, 13, 19, 18 ),
		array( 19, 18, 21, 18 ),
		array( 12, 10, 17, 10 ),
	);
	list( $hx, $hy ) = $pt( 12, 4 );
	imagefilledellipse( $im, (int) round( $hx ), (int) round( $hy ), (int) round( 4.6 * $u ), (int) round( 4.6 * $u ), $color );
	foreach ( $segments as $sg ) {
		list( $ax, $ay ) = $pt( $sg[0], $sg[1] );
		list( $bx, $by ) = $pt( $sg[2], $sg[3] );
		tp_gd_stroke( $im, $ax, $ay, $bx, $by, $d, $color );
	}
	// The wheel: open arc around (11, 15.5), radius 5.5.
	list( $cx, $cy ) = $pt( 11, 15.5 );
	$r = 5.5 * $u;
	for ( $a = 152; $a <= 398; $a += 0.5 ) {
		$rad = deg2rad( $a );
		imagefilledellipse( $im, (int) round( $cx + cos( $rad ) * $r ), (int) round( $cy + sin( $rad ) * $r ), $d, $d, $color );
	}
}

/**
 * New transparent canvas $factor times bigger than the final size.
 */
function tp_gd_canvas( $w, $h ) {
	$im = imagecreatetruecolor( $w, $h );
	imagesavealpha( $im, true );
	imagealphablending( $im, false );
	imagefill( $im, 0, 0, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );
	imagealphablending( $im, true );
	return $im;
}

/**
 * Scale a large canvas down onto $dst (smooth, anti-aliased result).
 */
function tp_gd_supersample( $dst, $src, $dx, $dy, $dw, $dh ) {
	imagealphablending( $dst, true );
	imagecopyresampled( $dst, $src, $dx, $dy, 0, 0, $dw, $dh, imagesx( $src ), imagesy( $src ) );
	imagedestroy( $src );
}

function tp_gd_rounded_rect( $im, $x1, $y1, $x2, $y2, $r, $color ) {
	imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y2, $color );
	imagefilledrectangle( $im, $x1, $y1 + $r, $x2, $y2 - $r, $color );
	foreach ( array( array( $x1 + $r, $y1 + $r ), array( $x2 - $r, $y1 + $r ), array( $x1 + $r, $y2 - $r ), array( $x2 - $r, $y2 - $r ) ) as $pt ) {
		imagefilledellipse( $im, $pt[0], $pt[1], $r * 2, $r * 2, $color );
	}
}

function tp_gd_ready() {
	return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' );
}

/**
 * PNG app icon of a given size.
 */
function tp_icon_png( $size ) {
	$c        = tp_design_colors();
	$k        = 4;
	$big      = $size * $k;
	$src      = tp_gd_canvas( $big, $big );
	$maskable = $size >= 192;
	if ( $maskable ) {
		imagefilledrectangle( $src, 0, 0, $big, $big, tp_hex_to_gd( $src, $c['accent'] ) );
	} else {
		tp_gd_rounded_rect( $src, 0, 0, $big - 1, $big - 1, (int) round( $big * .22 ), tp_hex_to_gd( $src, $c['accent'] ) );
	}
	$pad = (int) round( $big * ( $maskable ? .2 : .14 ) );
	tp_gd_wheelchair( $src, $pad, $pad, $big - 2 * $pad, tp_hex_to_gd( $src, $c['on_accent'] ), 2.4 );
	$im = tp_gd_canvas( $size, $size );
	imagealphablending( $im, false );
	imagecopyresampled( $im, $src, 0, 0, 0, 0, $size, $size, $big, $big );
	imagedestroy( $src );
	imagesavealpha( $im, true );
	ob_start();
	imagepng( $im );
	imagedestroy( $im );
	return ob_get_clean();
}

function tp_brand_url( $what, $arg = '' ) {
	return add_query_arg( array( 'tp_brand' => $what, 'v' => substr( md5( wp_json_encode( tp_design_colors() ) . TP_VERSION ), 0, 8 ), 'a' => $arg ), home_url( '/' ) );
}

add_action( 'wp_head', static function () {
	if ( ! has_site_icon() ) {
		printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( tp_brand_url( 'icon', 'svg' ) ) );
		if ( tp_gd_ready() ) {
			printf( '<link rel="icon" href="%s" sizes="32x32" type="image/png">' . "\n", esc_url( tp_brand_url( 'icon', '32' ) ) );
			printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( tp_brand_url( 'icon', '180' ) ) );
		}
	}
	printf( '<link rel="manifest" href="%s">' . "\n", esc_url( tp_brand_url( 'manifest' ) ) );
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	printf( '<meta name="apple-mobile-web-app-title" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
}, 3 );

add_action( 'template_redirect', 'tp_brand_endpoint', 0 );
function tp_brand_endpoint() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only assets.
	if ( empty( $_GET['tp_brand'] ) ) {
		return;
	}
	$what = sanitize_key( wp_unslash( $_GET['tp_brand'] ) );
	$arg  = isset( $_GET['a'] ) ? sanitize_key( wp_unslash( $_GET['a'] ) ) : '';
	// phpcs:enable
	header( 'Cache-Control: public, max-age=604800' );
	header( 'X-Robots-Tag: noindex' );

	if ( 'icon' === $what ) {
		if ( 'svg' === $arg ) {
			header( 'Content-Type: image/svg+xml' );
			echo tp_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped colours.
			exit;
		}
		$size = in_array( (int) $arg, array( 32, 180, 192, 512 ), true ) ? (int) $arg : 192;
		if ( ! tp_gd_ready() ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: image/png' );
		echo tp_icon_png( $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary image.
		exit;
	}

	if ( 'manifest' === $what ) {
		$c     = tp_design_colors();
		$icons = array();
		if ( has_site_icon() ) {
			foreach ( array( 192, 512 ) as $s ) {
				$icons[] = array( 'src' => get_site_icon_url( $s ), 'sizes' => "{$s}x{$s}", 'type' => 'image/png' );
			}
		} elseif ( tp_gd_ready() ) {
			foreach ( array( 192, 512 ) as $s ) {
				$icons[] = array( 'src' => tp_brand_url( 'icon', (string) $s ), 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any maskable' );
			}
		}
		$icons[] = array( 'src' => tp_brand_url( 'icon', 'svg' ), 'sizes' => 'any', 'type' => 'image/svg+xml' );
		$book    = tp_booking_page_url();
		$account = tp_page_url_by_template( 'account' );
		$short   = array( array( 'name' => __( 'Book a taxi', 'taxi-peninsula' ), 'url' => $book ) );
		if ( $account ) {
			$short[] = array( 'name' => __( 'My trips', 'taxi-peninsula' ), 'url' => $account );
		}
		$driver = tp_page_url_by_template( 'driver' );
		if ( $driver ) {
			$short[] = array( 'name' => __( 'Driver jobs', 'taxi-peninsula' ), 'url' => $driver );
		}
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'name'             => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'short_name'       => wp_specialchars_decode( mb_substr( get_bloginfo( 'name' ), 0, 12 ), ENT_QUOTES ),
				'description'      => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
				'start_url'        => home_url( '/' ),
				'scope'            => home_url( '/' ),
				'display'          => 'standalone',
				'background_color' => $c['background'],
				'theme_color'      => $c['primary'],
				'icons'            => $icons,
				'shortcuts'        => $short,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	if ( 'og' === $what ) {
		$post_id = absint( $arg );
		$title   = $post_id && 'publish' === get_post_status( $post_id ) ? get_the_title( $post_id ) : get_bloginfo( 'description' );
		$file    = tp_og_image_file( $title ?: get_bloginfo( 'name' ) );
		if ( ! $file ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: image/png' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}

/* -------------------------------------------------------------------------
 * Share images.
 * ---------------------------------------------------------------------- */

/**
 * Wrap text to fit a width with a TrueType font.
 *
 * @return string[] Lines.
 */
function tp_gd_wrap( $text, $font, $size, $width, $max_lines ) {
	$words = preg_split( '/\s+/u', trim( wp_specialchars_decode( wp_strip_all_tags( $text ), ENT_QUOTES ) ) );
	$lines = array();
	$line  = '';
	foreach ( $words as $w ) {
		$try = trim( $line . ' ' . $w );
		$box = imagettfbbox( $size, 0, $font, $try );
		if ( $box[2] - $box[0] > $width && '' !== $line ) {
			$lines[] = $line;
			$line    = $w;
		} else {
			$line = $try;
		}
	}
	if ( '' !== $line ) {
		$lines[] = $line;
	}
	if ( count( $lines ) > $max_lines ) {
		$lines                    = array_slice( $lines, 0, $max_lines );
		$lines[ $max_lines - 1 ] .= '…';
	}
	return $lines;
}

/**
 * Generate (and cache in uploads) a 1200 × 630 branded share card.
 *
 * @return string|false Path to the PNG.
 */
function tp_og_image_file( $title ) {
	if ( ! tp_gd_ready() ) {
		return false;
	}
	$c     = tp_design_colors();
	$uploads = wp_upload_dir();
	$dir   = trailingslashit( $uploads['basedir'] ) . 'tp-share';
	$key   = md5( $title . '|' . wp_json_encode( $c ) . '|' . get_bloginfo( 'name' ) . '|' . tp_opt( 'phone_display' ) . '|' . TP_VERSION . '|2' );
	$file  = $dir . '/' . $key . '.png';
	if ( file_exists( $file ) ) {
		return $file;
	}
	wp_mkdir_p( $dir );
	$bold = TP_DIR . '/assets/fonts/atkinson/AtkinsonHyperlegible-Bold.ttf';
	$reg  = TP_DIR . '/assets/fonts/atkinson/AtkinsonHyperlegible-Regular.ttf';

	$w  = 1200;
	$h  = 630;
	$im = imagecreatetruecolor( $w, $h );
	imageantialias( $im, true );
	// Background: main colour with a soft diagonal to the lighter shade.
	list( $r1, $g1, $b1 ) = tp_hex_rgb( $c['primary'] );
	list( $r2, $g2, $b2 ) = tp_hex_rgb( $c['primary_2'] );
	for ( $x = 0; $x < $w; $x += 2 ) {
		$t   = $x / $w;
		$col = imagecolorallocate( $im, (int) ( $r1 + ( $r2 - $r1 ) * $t ), (int) ( $g1 + ( $g2 - $g1 ) * $t ), (int) ( $b1 + ( $b2 - $b1 ) * $t ) );
		imagefilledrectangle( $im, $x, 0, $x + 1, $h, $col );
	}
	$white  = imagecolorallocate( $im, 255, 255, 255 );
	$soft   = imagecolorallocatealpha( $im, 255, 255, 255, 30 );
	$accent = tp_hex_to_gd( $im, $c['accent'] );
	$on_acc = tp_hex_to_gd( $im, $c['on_accent'] );
	$road   = imagecolorallocate( $im, 51, 65, 85 );

	// Accent badge with the wheelchair symbol, drawn 4× and scaled down for smooth edges.
	$k     = 4;
	$badge = tp_gd_canvas( 440 * $k, 440 * $k );
	imagefilledellipse( $badge, 220 * $k, 220 * $k, 430 * $k, 430 * $k, imagecolorallocatealpha( $badge, 255, 255, 255, 112 ) );
	imagefilledellipse( $badge, 220 * $k, 220 * $k, 310 * $k, 310 * $k, tp_hex_to_gd( $badge, $c['accent'] ) );
	tp_gd_wheelchair( $badge, 115 * $k, 115 * $k, 210 * $k, tp_hex_to_gd( $badge, $c['on_accent'] ), 2.4 );
	tp_gd_supersample( $im, $badge, 740, 30, 440, 440 );

	// Road along the bottom with a dashed centre line.
	imagefilledrectangle( $im, 0, $h - 70, $w, $h, $road );
	for ( $x = 20; $x < $w; $x += 70 ) {
		imagefilledrectangle( $im, $x, $h - 37, $x + 38, $h - 33, $soft );
	}

	// Site name pill.
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$box  = imagettfbbox( 26, 0, $bold, $site );
	$pw   = ( $box[2] - $box[0] ) + 44;
	$pill = tp_gd_canvas( $pw * 4, 54 * 4 );
	tp_gd_rounded_rect( $pill, 0, 0, $pw * 4 - 1, 54 * 4 - 1, 27 * 4 - 1, tp_hex_to_gd( $pill, $c['accent'] ) );
	tp_gd_supersample( $im, $pill, 70, 64, $pw, 54 );
	imagettftext( $im, 26, 0, 92, 102, $on_acc, $bold, $site );

	// Title.
	$size  = 58;
	$lines = tp_gd_wrap( $title, $bold, $size, 680, 3 );
	if ( count( $lines ) >= 3 ) {
		$size  = 48;
		$lines = tp_gd_wrap( $title, $bold, $size, 680, 3 );
	}
	$y = 210;
	foreach ( $lines as $line ) {
		imagettftext( $im, $size, 0, 70, $y, $white, $bold, $line );
		$y += (int) ( $size * 1.3 );
	}
	// Phone.
	imagettftext( $im, 28, 0, 70, $h - 105, $white, $reg, tp_opt( 'phone_display' ) . '  ·  ' . wp_parse_url( home_url(), PHP_URL_HOST ) );

	imagepng( $im, $file, 6 );
	imagedestroy( $im );
	return file_exists( $file ) ? $file : false;
}

/**
 * URL of the branded share card for the current page.
 */
function tp_generated_share_image() {
	if ( ! tp_gd_ready() ) {
		return '';
	}
	return tp_brand_url( 'og', is_singular() ? (string) get_queried_object_id() : '0' );
}

/* -------------------------------------------------------------------------
 * App-style bottom bar on phones.
 * ---------------------------------------------------------------------- */

function tp_the_bottom_nav() {
	if ( ! tp_design( 'bottom_nav' ) ) {
		return;
	}
	$account = tp_page_url_by_template( 'account' );
	$trips   = $account ?: tp_page_url_by_template( 'lookup' );
	$book    = tp_booking_page_url();
	$here    = home_url( add_query_arg( array() ) );
	$is      = static function ( $url ) use ( $here ) {
		return $url && 0 === strpos( strtok( $here, '?' ), strtok( $url, '#' ) ) && strtok( $url, '#' ) !== home_url( '/' );
	};
	?>
	<nav class="bottom-nav" aria-label="<?php esc_attr_e( 'Quick links', 'taxi-peninsula' ); ?>">
		<a href="<?php echo esc_url( $book ); ?>"<?php echo $is( $book ) ? ' aria-current="page"' : ''; ?>><?php tp_the_icon( 'calendar' ); ?><span><?php esc_html_e( 'Book', 'taxi-peninsula' ); ?></span></a>
		<?php if ( $trips ) : ?>
			<a href="<?php echo esc_url( $trips ); ?>"<?php echo $is( $trips ) ? ' aria-current="page"' : ''; ?>><?php tp_the_icon( 'clock' ); ?><span><?php esc_html_e( 'My trips', 'taxi-peninsula' ); ?></span></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( tp_phone_href() ); ?>" class="bottom-nav__call"><?php tp_the_icon( 'phone' ); ?><span><?php esc_html_e( 'Call', 'taxi-peninsula' ); ?></span></a>
		<button type="button" data-open-menu aria-controls="primary-nav"><?php tp_the_icon( 'menu' ); ?><span><?php esc_html_e( 'Menu', 'taxi-peninsula' ); ?></span></button>
	</nav>
	<?php
}
