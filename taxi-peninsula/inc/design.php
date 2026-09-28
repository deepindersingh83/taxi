<?php
/**
 * Design options: logo, colours, fonts and shapes, header, hero, footer, pages & blog.
 *
 * Everything is under Appearance → Customize (Site Identity for the logo, and the
 * "Design" panel for the rest) and previews before you publish.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defaults for every design option.
 */
function tp_design_defaults() {
	return array(
		// Branding (Site Identity).
		'logo_h'           => 64,
		'logo_h_mobile'    => 48,
		'brand_display'    => 'logo',
		'brand_tagline'    => __( 'Wheelchair Accessible Taxis', 'taxi-peninsula' ),
		'logo_dark'        => 0,
		'logo_contrast'    => 0,
		'logo_footer'      => 0,
		// Colours.
		'color_scheme'     => 'navy',
		'color_primary'    => '',
		'color_accent'     => '',
		'color_link'       => '',
		'color_background' => '',
		'color_footer'     => '',
		// Fonts & shape.
		'font'             => 'atkinson',
		'base_size'        => 100,
		'heading_weight'   => 700,
		'button_shape'     => 'pill',
		'card_radius'      => 'medium',
		'shadow'           => 'soft',
		// Header.
		'header_layout'    => 'split',
		'header_sticky'    => true,
		'header_phone'     => true,
		'header_phone_text'=> '',
		'header_book'      => true,
		'header_book_text' => __( 'Book now', 'taxi-peninsula' ),
		'topbar'           => 'full',
		// Hero.
		'hero_bg'          => 'gradient',
		'hero_side'        => 'auto',
		'hero_align'       => 'left',
		'hero_height'      => 'medium',
		'hero_overlay'     => 60,
		// Footer.
		'footer_cols'      => 'auto',
		'footer_menu'      => true,
		'social_facebook'  => '',
		'social_instagram' => '',
		'social_linkedin'  => '',
		'social_youtube'   => '',
		/* translators: {year} and {site} are replaced automatically. */
		'copyright'        => __( '© {year} {site}. All rights reserved.', 'taxi-peninsula' ),
		// Pages & blog.
		'banner'           => 'color',
		'banner_image'     => 0,
		'blog_layout'      => 'grid',
		'blog_sidebar'     => true,
		'back_to_top'      => true,
	);
}

/**
 * Read a design option.
 */
function tp_design( $key ) {
	$defaults = tp_design_defaults();
	return get_theme_mod( 'tp_d_' . $key, $defaults[ $key ] ?? '' );
}

/**
 * Ready-made colour schemes. Every scheme passes WCAG AA for the pairs the theme uses.
 */
function tp_color_schemes() {
	return array(
		'navy'     => array( 'label' => __( 'Navy & Yellow (original)', 'taxi-peninsula' ), 'primary' => '#0b2a5b', 'accent' => '#ffc72c', 'link' => '#0a57c2', 'background' => '#ffffff', 'footer' => '#071a3a' ),
		'teal'     => array( 'label' => __( 'Teal & Amber', 'taxi-peninsula' ), 'primary' => '#0f4c5c', 'accent' => '#ffb703', 'link' => '#0b6e79', 'background' => '#ffffff', 'footer' => '#082f38' ),
		'green'    => array( 'label' => __( 'Forest Green & Lime', 'taxi-peninsula' ), 'primary' => '#1b4332', 'accent' => '#c5e84a', 'link' => '#1b7a43', 'background' => '#ffffff', 'footer' => '#0f2a1f' ),
		'charcoal' => array( 'label' => __( 'Charcoal & Orange', 'taxi-peninsula' ), 'primary' => '#243040', 'accent' => '#ff9f1c', 'link' => '#1f5fbf', 'background' => '#ffffff', 'footer' => '#151c26' ),
		'purple'   => array( 'label' => __( 'Purple & Gold', 'taxi-peninsula' ), 'primary' => '#3c1a78', 'accent' => '#ffc857', 'link' => '#6a3fc1', 'background' => '#ffffff', 'footer' => '#24104a' ),
	);
}

/**
 * Self-hosted fonts (assets/fonts/<slug>/font.css, SIL Open Font License).
 */
function tp_fonts() {
	return array(
		'atkinson'    => array( 'label' => __( 'Atkinson Hyperlegible — designed for low-vision readers', 'taxi-peninsula' ), 'family' => '"Atkinson Hyperlegible"' ),
		'lexend'      => array( 'label' => __( 'Lexend — wide letters, easy reading', 'taxi-peninsula' ), 'family' => '"Lexend"' ),
		'inter'       => array( 'label' => __( 'Inter — clean and modern', 'taxi-peninsula' ), 'family' => '"Inter"' ),
		'source-sans' => array( 'label' => __( 'Source Sans 3 — friendly and compact', 'taxi-peninsula' ), 'family' => '"Source Sans 3"' ),
	);
}

function tp_font_slug() {
	$slug = tp_design( 'font' );
	return isset( tp_fonts()[ $slug ] ) ? $slug : 'atkinson';
}

function tp_font_stack() {
	return tp_fonts()[ tp_font_slug() ]['family'] . ', system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
}

/* --------------------------------------------------------------------------
 * Colour maths
 * ----------------------------------------------------------------------- */

function tp_hex_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

function tp_rgb_hex( $rgb ) {
	return sprintf( '#%02x%02x%02x', ...array_map( static fn( $v ) => max( 0, min( 255, (int) round( $v ) ) ), $rgb ) );
}

/**
 * Mix $b into $a by $weight (0 → $a, 1 → $b).
 */
function tp_mix( $a, $b, $weight ) {
	$x = tp_hex_rgb( $a );
	$y = tp_hex_rgb( $b );
	return tp_rgb_hex( array_map( static fn( $i ) => $x[ $i ] + ( $y[ $i ] - $x[ $i ] ) * $weight, array( 0, 1, 2 ) ) );
}

function tp_luminance( $hex ) {
	$c = array_map(
		static function ( $v ) {
			$v /= 255;
			return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
		},
		tp_hex_rgb( $hex )
	);
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function tp_contrast( $a, $b ) {
	$x = tp_luminance( $a );
	$y = tp_luminance( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
}

/**
 * Lighten (positive) or darken (negative) in HSL lightness.
 */
function tp_shade( $hex, $amount ) {
	list( $r, $g, $b ) = array_map( static fn( $v ) => $v / 255, tp_hex_rgb( $hex ) );
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$l   = ( $max + $min ) / 2;
	$d   = $max - $min;
	$h   = 0;
	$s   = 0;
	if ( $d > 0 ) {
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
		if ( $max === $r ) {
			$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}
		$h /= 6;
	}
	$l   = max( 0, min( 1, $l + $amount ) );
	$hue = static function ( $p, $q, $t ) {
		$t += $t < 0 ? 1 : ( $t > 1 ? -1 : 0 );
		if ( $t < 1 / 6 ) {
			return $p + ( $q - $p ) * 6 * $t;
		}
		if ( $t < 1 / 2 ) {
			return $q;
		}
		if ( $t < 2 / 3 ) {
			return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		}
		return $p;
	};
	if ( 0 == $s ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		return tp_rgb_hex( array( $l * 255, $l * 255, $l * 255 ) );
	}
	$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
	$p = 2 * $l - $q;
	return tp_rgb_hex( array( $hue( $p, $q, $h + 1 / 3 ) * 255, $hue( $p, $q, $h ) * 255, $hue( $p, $q, $h - 1 / 3 ) * 255 ) );
}

/**
 * Pick the most readable of the candidate text colours for a background.
 */
function tp_best_text( $bg, $candidates ) {
	usort( $candidates, static fn( $a, $b ) => tp_contrast( $b, $bg ) <=> tp_contrast( $a, $bg ) );
	return $candidates[0];
}

/**
 * The colours in use: the chosen scheme, with any colour picked by hand on top,
 * plus the shades worked out from them.
 */
function tp_design_colors() {
	static $cache = null;
	if ( null !== $cache && ! is_customize_preview() ) {
		return $cache;
	}
	$schemes = tp_color_schemes();
	$scheme  = $schemes[ tp_design( 'color_scheme' ) ] ?? $schemes['navy'];
	$c       = array();
	foreach ( array( 'primary', 'accent', 'link', 'background', 'footer' ) as $key ) {
		$value     = sanitize_hex_color( (string) tp_design( 'color_' . $key ) );
		$c[ $key ] = $value ? strtolower( $value ) : $scheme[ $key ];
	}
	$text = '#16213a';

	$c['text']         = $text;
	$c['primary_2']    = tp_shade( $c['primary'], 0.12 );
	$c['accent_hover'] = tp_luminance( $c['accent'] ) > 0.25 ? tp_shade( $c['accent'], -0.07 ) : tp_shade( $c['accent'], 0.08 );
	$c['on_accent']    = tp_contrast( $c['primary'], $c['accent'] ) >= 4.5 ? $c['primary'] : tp_best_text( $c['accent'], array( '#000000', '#ffffff' ) );
	$c['heading']      = tp_contrast( $c['primary'], $c['background'] ) >= 4.5 ? $c['primary'] : $text;
	$c['bg_alt']       = tp_mix( $c['background'], $c['link'], 0.05 );
	$c['border']       = tp_mix( $c['background'], $c['primary'], 0.17 );
	$c['warm_bg']      = tp_mix( $c['background'], $c['accent'], 0.12 );
	$c['footer_text']  = tp_best_text( $c['footer'], array( '#dfe6f3', $text ) );
	$c['footer_link']  = tp_best_text( $c['footer'], array( '#ffffff', $c['primary'], $text ) );

	// Link colour for dark mode: lighten until it reads on the dark cards.
	$dark = $c['link'];
	for ( $i = 0; $i < 20 && tp_contrast( $dark, '#16223a' ) < 7; $i++ ) {
		$dark = tp_mix( $dark, '#ffffff', 0.15 );
	}
	$c['link_dark'] = $dark;

	$cache = $c;
	return $c;
}

/**
 * Contrast problems with the current colours (also checked live in the Customizer).
 *
 * @return string[]
 */
function tp_design_contrast_issues( $c = null ) {
	$c      = $c ?: tp_design_colors();
	$issues = array();
	if ( tp_contrast( $c['text'], $c['background'] ) < 4.5 ) {
		$issues[] = __( 'Body text is hard to read on the background colour.', 'taxi-peninsula' );
	}
	if ( tp_contrast( $c['link'], $c['background'] ) < 4.5 ) {
		$issues[] = __( 'Links are hard to read on the background colour.', 'taxi-peninsula' );
	}
	if ( tp_contrast( '#ffffff', $c['primary'] ) < 4.5 ) {
		$issues[] = __( 'White text on the main colour (top bar, page banners, buttons) is hard to read.', 'taxi-peninsula' );
	}
	if ( tp_contrast( $c['accent'], $c['primary'] ) < 3 ) {
		$issues[] = __( 'Accent icons and buttons don’t stand out on the main colour.', 'taxi-peninsula' );
	}
	if ( tp_contrast( $c['footer_text'], $c['footer'] ) < 4.5 ) {
		$issues[] = __( 'Footer text is hard to read on the footer colour.', 'taxi-peninsula' );
	}
	return $issues;
}

/* --------------------------------------------------------------------------
 * Output: CSS variables, fonts, body classes
 * ----------------------------------------------------------------------- */

function tp_rgb_list( $hex ) {
	return implode( ' ', tp_hex_rgb( $hex ) );
}

/**
 * CSS for the chosen colours, fonts and shapes.
 */
function tp_design_css() {
	$c      = tp_design_colors();
	$radius = array(
		'square' => array( '.25rem', '.25rem' ),
		'small'  => array( '.75rem', '.5rem' ),
		'medium' => array( '1.25rem', '.75rem' ),
		'large'  => array( '2rem', '1rem' ),
	);
	$radius = $radius[ tp_design( 'card_radius' ) ] ?? $radius['medium'];
	$btn    = array(
		'pill'    => '999px',
		'rounded' => '.75rem',
		'square'  => '.25rem',
	);
	$btn    = $btn[ tp_design( 'button_shape' ) ] ?? '999px';
	$p      = tp_rgb_list( $c['primary'] );
	$shadow = array(
		'none'   => 'none',
		'soft'   => "0 1px 2px rgb($p / .06), 0 8px 24px rgb($p / .08)",
		'strong' => "0 2px 4px rgb($p / .1), 0 14px 36px rgb($p / .18)",
	);
	$shadow_key = tp_design( 'shadow' );
	$shadow     = $shadow[ $shadow_key ] ?? $shadow['soft'];
	$overlay    = max( 0, min( 95, (int) tp_design( 'hero_overlay' ) ) ) / 100;
	$overlay_c  = tp_rgb_list( tp_mix( $c['primary'], '#000000', 0.45 ) );
	$base       = max( 80, min( 130, (int) tp_design( 'base_size' ) ) );
	$weight     = in_array( (int) tp_design( 'heading_weight' ), array( 600, 700, 800 ), true ) ? (int) tp_design( 'heading_weight' ) : 700;

	$vars = array(
		'--font'             => tp_font_stack(),
		'--tp-base'          => $base . '%',
		'--heading-weight'   => $weight,
		'--btn-radius'       => $btn,
		'--radius-lg'        => $radius[0],
		'--radius'           => $radius[1],
		'--shadow'           => $shadow,
		'--logo-h'           => max( 24, min( 200, (int) tp_design( 'logo_h' ) ) ) / 16 . 'rem',
		'--logo-h-mobile'    => max( 20, min( 120, (int) tp_design( 'logo_h_mobile' ) ) ) / 16 . 'rem',
		'--hero-overlay'     => "rgb($overlay_c / $overlay)",
		'--c-primary'        => $c['primary'],
		'--c-primary-2'      => $c['primary_2'],
		'--c-accent'         => $c['accent'],
		'--c-accent-hover'   => $c['accent_hover'],
		'--c-accent-glow'    => 'rgb(' . tp_rgb_list( $c['accent'] ) . ' / .25)',
		'--c-on-accent'      => $c['on_accent'],
		'--c-link'           => $c['link'],
		'--c-bg'             => $c['background'],
		'--c-bg-alt'         => $c['bg_alt'],
		'--c-border'         => $c['border'],
		'--c-heading'        => $c['heading'],
		'--c-outline'        => $c['heading'],
		'--c-warm-bg'        => $c['warm_bg'],
		'--c-footer'         => $c['footer'],
		'--c-footer-text'    => $c['footer_text'],
		'--c-footer-link'    => $c['footer_link'],
	);
	$css = ':root{';
	foreach ( $vars as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}
	$css .= '}';
	$dark = '--c-link:' . $c['link_dark'] . ';' . ( 'none' === $shadow_key ? '--shadow:none;' : '' );
	$css .= '@media (prefers-color-scheme: dark){:root:not([data-theme="light"]):not([data-contrast="high"]){' . $dark . '}}';
	$css .= ':root[data-theme="dark"]:not([data-contrast="high"]){' . $dark . '}';
	return $css;
}

add_action( 'wp_enqueue_scripts', static function () {
	wp_enqueue_style( 'tp-fonts', TP_URI . '/assets/fonts/' . tp_font_slug() . '/font.css', array(), TP_VERSION );
	wp_add_inline_style( 'tp-main', tp_design_css() );
}, 20 );

/**
 * Preload the main (Latin, upright) font file so text doesn't flash.
 */
add_action( 'wp_head', static function () {
	$css = @file_get_contents( TP_DIR . '/assets/fonts/' . tp_font_slug() . '/font.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( $css && preg_match( '#/\* latin \*/\s*@font-face\s*\{[^}]*font-style:\s*normal;[^}]*url\(([^)]+\.woff2)\)#', $css, $m ) ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( TP_URI . '/assets/fonts/' . tp_font_slug() . '/' . $m[1] ) );
	}
}, 2 );

add_filter( 'body_class', static function ( $classes ) {
	$classes[] = 'tp-header--' . sanitize_html_class( tp_design( 'header_layout' ) );
	if ( ! tp_design( 'header_sticky' ) ) {
		$classes[] = 'tp-header--static';
	}
	$classes[] = 'tp-topbar--' . sanitize_html_class( tp_design( 'topbar' ) );
	return $classes;
} );

/**
 * Editor: same colours and font as the site.
 */
add_filter( 'block_editor_settings_all', static function ( $settings ) {
	$c = tp_design_colors();
	$settings['styles'][] = array(
		'css'     => file_get_contents( TP_DIR . '/assets/fonts/' . tp_font_slug() . '/font.css' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			. tp_design_css()
			. 'body{font-family:' . tp_font_stack() . ';}h1,h2,h3,h4{color:' . $c['heading'] . ';font-weight:var(--heading-weight);}',
		'baseURL' => TP_URI . '/assets/fonts/' . tp_font_slug() . '/',
	);
	return $settings;
} );

/**
 * Editor colour palette and font follow the Customizer choices.
 */
add_filter( 'wp_theme_json_data_theme', static function ( $theme_json ) {
	$c = tp_design_colors();
	return $theme_json->update_with(
		array(
			'version'  => 3,
			'settings' => array(
				'color'      => array(
					'palette' => array(
						array( 'slug' => 'primary', 'name' => __( 'Main colour', 'taxi-peninsula' ), 'color' => $c['primary'] ),
						array( 'slug' => 'accent', 'name' => __( 'Accent', 'taxi-peninsula' ), 'color' => $c['accent'] ),
						array( 'slug' => 'link-blue', 'name' => __( 'Link colour', 'taxi-peninsula' ), 'color' => $c['link'] ),
						array( 'slug' => 'ink', 'name' => __( 'Ink (body text)', 'taxi-peninsula' ), 'color' => $c['text'] ),
						array( 'slug' => 'muted', 'name' => __( 'Muted', 'taxi-peninsula' ), 'color' => '#4a5670' ),
						array( 'slug' => 'surface-alt', 'name' => __( 'Light tint', 'taxi-peninsula' ), 'color' => $c['bg_alt'] ),
						array( 'slug' => 'white', 'name' => __( 'White', 'taxi-peninsula' ), 'color' => '#ffffff' ),
					),
				),
				'typography' => array(
					'fontFamilies' => array(
						array( 'slug' => 'hyperlegible', 'name' => wp_strip_all_tags( strtok( tp_fonts()[ tp_font_slug() ]['label'], '—' ) ), 'fontFamily' => tp_font_stack() ),
					),
				),
			),
		)
	);
} );

/* --------------------------------------------------------------------------
 * Template helpers
 * ----------------------------------------------------------------------- */

/**
 * The logo / site name block for the header.
 */
function tp_the_brand() {
	$logo    = (int) get_theme_mod( 'custom_logo' );
	$logo    = $logo && wp_attachment_is_image( $logo ) ? $logo : 0;
	$tagline = trim( (string) tp_design( 'brand_tagline' ) );
	$name    = get_bloginfo( 'name' );

	$text = '<span class="brand__text"><span class="brand__name">' . esc_html( $name ) . '</span>'
		. ( '' !== $tagline ? '<span class="brand__tag">' . esc_html( $tagline ) . '</span>' : '' ) . '</span>';

	if ( ! $logo ) {
		printf(
			'<a class="brand__link" href="%1$s" rel="home"><span class="brand__mark">%2$s</span>%3$s</a>',
			esc_url( home_url( '/' ) ),
			tp_icon( 'wheelchair' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			$text // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		);
		return;
	}

	$with_text = 'logo-text' === tp_design( 'brand_display' );
	// With the name beside it the image is decorative; on its own it names the link.
	$alt     = $with_text ? '' : $name;
	$classes = array( 'brand__link', 'custom-logo-link' );
	$images  = wp_get_attachment_image( $logo, 'full', false, array( 'class' => 'custom-logo brand__logo brand__logo--light', 'alt' => $alt, 'loading' => false, 'decoding' => 'async' ) );
	foreach ( array( 'dark', 'contrast' ) as $variant ) {
		$id = (int) tp_design( 'logo_' . $variant );
		if ( $id && wp_attachment_is_image( $id ) ) {
			$classes[] = 'has-' . $variant . '-logo';
			$images   .= wp_get_attachment_image( $id, 'full', false, array( 'class' => 'brand__logo brand__logo--' . $variant, 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async' ) );
		}
	}
	printf(
		'<a class="%1$s" href="%2$s" rel="home">%3$s%4$s</a>',
		esc_attr( implode( ' ', $classes ) ),
		esc_url( home_url( '/' ) ),
		$images, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		$with_text ? $text : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	);
}

/**
 * Text size / contrast / dark mode buttons (top bar or footer).
 */
function tp_the_display_options( $class = '' ) {
	?>
	<div class="<?php echo esc_attr( trim( 'a11y-tools ' . $class ) ); ?>" role="group" aria-label="<?php esc_attr_e( 'Display options', 'taxi-peninsula' ); ?>">
		<span class="a11y-tools__label"><?php tp_the_icon( 'text' ); ?><span><?php esc_html_e( 'Text size', 'taxi-peninsula' ); ?></span></span>
		<button type="button" class="a11y-btn" data-text-size="sm" aria-pressed="false" aria-label="<?php esc_attr_e( 'Smaller text', 'taxi-peninsula' ); ?>">A<sup>−</sup></button>
		<button type="button" class="a11y-btn" data-text-size="md" aria-pressed="true" aria-label="<?php esc_attr_e( 'Default text size', 'taxi-peninsula' ); ?>">A</button>
		<button type="button" class="a11y-btn" data-text-size="lg" aria-pressed="false" aria-label="<?php esc_attr_e( 'Larger text', 'taxi-peninsula' ); ?>">A<sup>+</sup></button>
		<button type="button" class="a11y-btn" data-text-size="xl" aria-pressed="false" aria-label="<?php esc_attr_e( 'Largest text', 'taxi-peninsula' ); ?>">A<sup>++</sup></button>
		<button type="button" class="a11y-btn a11y-btn--contrast" data-contrast-toggle aria-pressed="false"><?php tp_the_icon( 'contrast' ); ?><span><?php esc_html_e( 'High contrast', 'taxi-peninsula' ); ?></span></button>
		<button type="button" class="a11y-btn a11y-btn--theme" data-theme-toggle aria-pressed="false"><?php tp_the_icon( 'moon' ); ?><span><?php esc_html_e( 'Dark mode', 'taxi-peninsula' ); ?></span></button>
	</div>
	<?php
}

/**
 * Social profile links that have been filled in.
 *
 * @return array [network => [label, url]]
 */
function tp_social_links() {
	$out = array();
	foreach ( array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
	) as $key => $label ) {
		$url = trim( (string) tp_design( 'social_' . $key ) );
		if ( $url ) {
			$out[ $key ] = array( $label, $url );
		}
	}
	return $out;
}

function tp_the_social_links() {
	$links = tp_social_links();
	if ( ! $links ) {
		return;
	}
	echo '<ul class="social-links">';
	foreach ( $links as $key => $link ) {
		printf(
			'<li><a href="%1$s" rel="me noopener">%2$s<span class="screen-reader-text">%3$s</span></a></li>',
			esc_url( $link[1] ),
			tp_icon( $key ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html( sprintf( /* translators: %s: social network */ __( '%s (our page)', 'taxi-peninsula' ), $link[0] ) )
		);
	}
	echo '</ul>';
}

function tp_copyright_text() {
	$text = (string) tp_design( 'copyright' );
	$text = str_replace( array( '{year}', '{site}' ), array( wp_date( 'Y' ), get_bloginfo( 'name' ) ), $text );
	return wp_kses( $text, array( 'a' => array( 'href' => true ), 'strong' => array(), 'em' => array() ) );
}

/**
 * Image for the page title banner (0 = use the colour banner).
 */
function tp_banner_image_id() {
	if ( 'image' !== tp_design( 'banner' ) ) {
		return 0;
	}
	if ( tp_banner_uses_featured() ) {
		return (int) get_post_thumbnail_id( get_queried_object_id() );
	}
	$id = (int) tp_design( 'banner_image' );
	return $id && wp_attachment_is_image( $id ) ? $id : 0;
}

/**
 * True when the banner shows the page's own featured image (so the page doesn't repeat it).
 */
function tp_banner_uses_featured() {
	return 'image' === tp_design( 'banner' ) && is_singular() && has_post_thumbnail( get_queried_object_id() );
}

/**
 * Whether a template should print the featured image inside the content.
 */
function tp_show_featured_image() {
	return has_post_thumbnail() && ! tp_banner_uses_featured();
}

function tp_blog_has_sidebar() {
	return (bool) tp_design( 'blog_sidebar' );
}

/**
 * Wrapper class for blog listings and single posts.
 */
function tp_blog_layout_class( $single = false ) {
	if ( tp_blog_has_sidebar() ) {
		return 'layout-sidebar';
	}
	return $single ? 'layout-single' : 'layout-full';
}

/**
 * Class for the list of post cards.
 */
function tp_post_list_class() {
	if ( 'list' === tp_design( 'blog_layout' ) ) {
		return 'post-list';
	}
	return tp_blog_has_sidebar() ? 'post-grid post-grid--2' : 'post-grid post-grid--3';
}

/* --------------------------------------------------------------------------
 * Customizer
 * ----------------------------------------------------------------------- */

add_action( 'customize_register', 'tp_design_customize_register', 30 );
function tp_design_customize_register( WP_Customize_Manager $wp_customize ) {
	$d = tp_design_defaults();

	$wp_customize->add_panel(
		'tp_design',
		array(
			'title'       => __( 'Design', 'taxi-peninsula' ),
			'description' => __( 'Colours, fonts, header, hero, footer and blog layout. Your logo is under Site Identity.', 'taxi-peninsula' ),
			'priority'    => 25,
		)
	);
	foreach ( array(
		'tp_design_colors' => __( 'Colours', 'taxi-peninsula' ),
		'tp_design_type'   => __( 'Fonts & shape', 'taxi-peninsula' ),
		'tp_design_header' => __( 'Header', 'taxi-peninsula' ),
		'tp_design_hero'   => __( 'Home page hero', 'taxi-peninsula' ),
		'tp_design_footer' => __( 'Footer', 'taxi-peninsula' ),
		'tp_design_pages'  => __( 'Pages & blog', 'taxi-peninsula' ),
	) as $id => $title ) {
		$wp_customize->add_section( $id, array( 'title' => $title, 'panel' => 'tp_design' ) );
	}

	$choice = static function ( $choices ) {
		return static function ( $value, $setting ) use ( $choices ) {
			return array_key_exists( $value, $choices ) ? $value : $setting->default;
		};
	};
	$range = static function ( $min, $max ) {
		return static function ( $value, $setting ) use ( $min, $max ) {
			return is_numeric( $value ) ? max( $min, min( $max, (int) $value ) ) : $setting->default;
		};
	};

	$add = static function ( $key, $section, $label, $type, $args = array() ) use ( $wp_customize, $d ) {
		$wp_customize->add_setting(
			'tp_d_' . $key,
			array(
				'default'           => $d[ $key ],
				'sanitize_callback' => $args['sanitize'] ?? 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$control = array_merge(
			array(
				'section'  => $section,
				'label'    => $label,
				'type'     => $type,
				'priority' => $args['priority'] ?? 10,
			),
			array_intersect_key( $args, array_flip( array( 'choices', 'description', 'input_attrs', 'active_callback' ) ) )
		);
		if ( 'image' === $type ) {
			unset( $control['type'] );
			$control['mime_type'] = 'image';
			$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'tp_d_' . $key, $control ) );
		} elseif ( 'color' === $type ) {
			unset( $control['type'] );
			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'tp_d_' . $key, $control ) );
		} else {
			$wp_customize->add_control( 'tp_d_' . $key, $control );
		}
	};

	$has_logo = static function () {
		return (bool) get_theme_mod( 'custom_logo' );
	};

	// ---- Branding (Site Identity, next to the core logo control).
	$add( 'logo_h', 'title_tagline', __( 'Logo height on computers (pixels)', 'taxi-peninsula' ), 'range', array( 'sanitize' => $range( 24, 200 ), 'input_attrs' => array( 'min' => 24, 'max' => 160, 'step' => 2 ), 'priority' => 9 ) );
	$add( 'logo_h_mobile', 'title_tagline', __( 'Logo height on phones (pixels)', 'taxi-peninsula' ), 'range', array( 'sanitize' => $range( 20, 120 ), 'input_attrs' => array( 'min' => 20, 'max' => 100, 'step' => 2 ), 'priority' => 9 ) );
	$brand_choices = array(
		'logo'      => __( 'Logo only', 'taxi-peninsula' ),
		'logo-text' => __( 'Logo with site name and tagline', 'taxi-peninsula' ),
	);
	$add( 'brand_display', 'title_tagline', __( 'Show in the header', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $brand_choices ), 'choices' => $brand_choices, 'priority' => 9, 'description' => __( 'Without a logo, the header shows a wheelchair icon with the site name and tagline.', 'taxi-peninsula' ) ) );
	$add( 'brand_tagline', 'title_tagline', __( 'Header tagline (under the name)', 'taxi-peninsula' ), 'text', array( 'priority' => 9, 'description' => __( 'Leave empty to hide it.', 'taxi-peninsula' ) ) );
	$add( 'logo_dark', 'title_tagline', __( 'Logo for dark mode (optional)', 'taxi-peninsula' ), 'image', array( 'sanitize' => 'absint', 'priority' => 9, 'description' => __( 'Usually a light or white version. Shown when a visitor uses dark mode.', 'taxi-peninsula' ), 'active_callback' => $has_logo ) );
	$add( 'logo_contrast', 'title_tagline', __( 'Logo for high contrast mode (optional)', 'taxi-peninsula' ), 'image', array( 'sanitize' => 'absint', 'priority' => 9, 'description' => __( 'A plain black version works best.', 'taxi-peninsula' ), 'active_callback' => $has_logo ) );
	$add( 'logo_footer', 'title_tagline', __( 'Footer logo (optional)', 'taxi-peninsula' ), 'image', array( 'sanitize' => 'absint', 'priority' => 9, 'description' => __( 'The footer has a dark background, so a white version usually looks best.', 'taxi-peninsula' ) ) );

	// ---- Colours.
	$schemes = array();
	foreach ( tp_color_schemes() as $key => $scheme ) {
		$schemes[ $key ] = $scheme['label'];
	}
	$add( 'color_scheme', 'tp_design_colors', __( 'Colour scheme', 'taxi-peninsula' ), 'select', array( 'sanitize' => $choice( $schemes ), 'choices' => $schemes, 'description' => __( 'Picking a scheme fills in the colours below. You can then change any of them.', 'taxi-peninsula' ) ) );
	foreach ( array(
		'primary'    => array( __( 'Main colour', 'taxi-peninsula' ), __( 'Top bar, page banners, hero and dark buttons. White text goes on it, so keep it dark.', 'taxi-peninsula' ) ),
		'accent'     => array( __( 'Accent colour', 'taxi-peninsula' ), __( '“Book” buttons, icons and highlights.', 'taxi-peninsula' ) ),
		'link'       => array( __( 'Link colour', 'taxi-peninsula' ), '' ),
		'background' => array( __( 'Page background', 'taxi-peninsula' ), __( 'Keep it light — visitors can switch to dark mode themselves.', 'taxi-peninsula' ) ),
		'footer'     => array( __( 'Footer background', 'taxi-peninsula' ), '' ),
	) as $key => $labels ) {
		$add( 'color_' . $key, 'tp_design_colors', $labels[0], 'color', array( 'sanitize' => 'sanitize_hex_color', 'description' => $labels[1] ) );
	}

	// ---- Fonts & shape.
	$fonts = array();
	foreach ( tp_fonts() as $key => $font ) {
		$fonts[ $key ] = $font['label'];
	}
	$add( 'font', 'tp_design_type', __( 'Font', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $fonts ), 'choices' => $fonts, 'description' => __( 'All fonts are stored on your own website — nothing is loaded from Google.', 'taxi-peninsula' ) ) );
	$add( 'base_size', 'tp_design_type', __( 'Base text size (%)', 'taxi-peninsula' ), 'range', array( 'sanitize' => $range( 80, 130 ), 'input_attrs' => array( 'min' => 90, 'max' => 125, 'step' => 5 ), 'description' => __( '100% is the default. Visitors can still make text bigger with the text size buttons.', 'taxi-peninsula' ) ) );
	$weights = array(
		'600' => __( 'Semi-bold', 'taxi-peninsula' ),
		'700' => __( 'Bold', 'taxi-peninsula' ),
		'800' => __( 'Extra bold', 'taxi-peninsula' ),
	);
	$add( 'heading_weight', 'tp_design_type', __( 'Heading weight', 'taxi-peninsula' ), 'select', array( 'sanitize' => $choice( $weights ), 'choices' => $weights, 'description' => __( 'Atkinson Hyperlegible only has bold, so all three look the same with it.', 'taxi-peninsula' ) ) );
	$shapes = array(
		'pill'    => __( 'Pill (fully rounded)', 'taxi-peninsula' ),
		'rounded' => __( 'Rounded corners', 'taxi-peninsula' ),
		'square'  => __( 'Square corners', 'taxi-peninsula' ),
	);
	$add( 'button_shape', 'tp_design_type', __( 'Button shape', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $shapes ), 'choices' => $shapes ) );
	$radii = array(
		'square' => __( 'Square', 'taxi-peninsula' ),
		'small'  => __( 'Small', 'taxi-peninsula' ),
		'medium' => __( 'Medium', 'taxi-peninsula' ),
		'large'  => __( 'Large', 'taxi-peninsula' ),
	);
	$add( 'card_radius', 'tp_design_type', __( 'Card corner size', 'taxi-peninsula' ), 'select', array( 'sanitize' => $choice( $radii ), 'choices' => $radii ) );
	$shadows = array(
		'none'   => __( 'None (flat)', 'taxi-peninsula' ),
		'soft'   => __( 'Soft', 'taxi-peninsula' ),
		'strong' => __( 'Strong', 'taxi-peninsula' ),
	);
	$add( 'shadow', 'tp_design_type', __( 'Shadows', 'taxi-peninsula' ), 'select', array( 'sanitize' => $choice( $shadows ), 'choices' => $shadows ) );

	// ---- Header.
	$layouts = array(
		'split'    => __( 'Logo left, menu right', 'taxi-peninsula' ),
		'centered' => __( 'Everything centred', 'taxi-peninsula' ),
		'stacked'  => __( 'Logo row above the menu', 'taxi-peninsula' ),
	);
	$add( 'header_layout', 'tp_design_header', __( 'Layout on computers', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $layouts ), 'choices' => $layouts, 'description' => __( 'Phones and tablets always use the Menu button.', 'taxi-peninsula' ) ) );
	$add( 'header_sticky', 'tp_design_header', __( 'Keep the header at the top while scrolling', 'taxi-peninsula' ), 'checkbox', array( 'sanitize' => 'tp_sanitize_checkbox' ) );
	$add( 'header_book', 'tp_design_header', __( 'Show the “Book” button', 'taxi-peninsula' ), 'checkbox', array( 'sanitize' => 'tp_sanitize_checkbox' ) );
	$add( 'header_book_text', 'tp_design_header', __( '“Book” button wording', 'taxi-peninsula' ), 'text' );
	$add( 'header_phone', 'tp_design_header', __( 'Show the phone button', 'taxi-peninsula' ), 'checkbox', array( 'sanitize' => 'tp_sanitize_checkbox', 'description' => __( 'With the “Logo left, menu right” layout and the top bar showing, it is hidden on wide screens to leave room for the menu — the number is in the top bar.', 'taxi-peninsula' ) ) );
	$add( 'header_phone_text', 'tp_design_header', __( 'Phone button wording', 'taxi-peninsula' ), 'text', array( 'description' => __( 'Leave empty to show the phone number.', 'taxi-peninsula' ) ) );
	$topbar = array(
		'full'    => __( 'Top bar with contact details and display options', 'taxi-peninsula' ),
		'contact' => __( 'Top bar with contact details — display options in the footer', 'taxi-peninsula' ),
		'none'    => __( 'No top bar — display options in the footer', 'taxi-peninsula' ),
	);
	$add( 'topbar', 'tp_design_header', __( 'Top bar', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $topbar ), 'choices' => $topbar, 'description' => __( 'Display options are the text size, high contrast and dark mode buttons. They are never removed completely.', 'taxi-peninsula' ) ) );

	// ---- Hero.
	$bgs = array(
		'gradient' => __( 'Gradient', 'taxi-peninsula' ),
		'solid'    => __( 'Solid colour', 'taxi-peninsula' ),
		'photo'    => __( 'Photo with a darkening overlay', 'taxi-peninsula' ),
	);
	$add( 'hero_bg', 'tp_design_hero', __( 'Background', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $bgs ), 'choices' => $bgs, 'description' => __( 'The photo is the hero photo (Taxi Peninsula → Home page hero) or the current seasonal photo.', 'taxi-peninsula' ) ) );
	$add( 'hero_overlay', 'tp_design_hero', __( 'Overlay darkness (%)', 'taxi-peninsula' ), 'range', array( 'sanitize' => $range( 20, 90 ), 'input_attrs' => array( 'min' => 30, 'max' => 85, 'step' => 5 ), 'description' => __( 'Keep it at 55% or more so white text stays readable on busy photos.', 'taxi-peninsula' ), 'active_callback' => static fn() => 'photo' === tp_design( 'hero_bg' ) ) );
	$sides = array(
		'auto' => __( 'The hero photo (or the badge card if there is no photo)', 'taxi-peninsula' ),
		'card' => __( 'The badge card', 'taxi-peninsula' ),
		'none' => __( 'Nothing — text only', 'taxi-peninsula' ),
	);
	$add( 'hero_side', 'tp_design_hero', __( 'Beside the text', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $sides ), 'choices' => $sides, 'active_callback' => static fn() => 'photo' !== tp_design( 'hero_bg' ) && 'center' !== tp_design( 'hero_align' ) ) );
	$aligns = array(
		'left'   => __( 'Left', 'taxi-peninsula' ),
		'center' => __( 'Centred', 'taxi-peninsula' ),
	);
	$add( 'hero_align', 'tp_design_hero', __( 'Text position', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $aligns ), 'choices' => $aligns ) );
	$heights = array(
		'short'  => __( 'Short', 'taxi-peninsula' ),
		'medium' => __( 'Medium', 'taxi-peninsula' ),
		'full'   => __( 'Full screen', 'taxi-peninsula' ),
	);
	$add( 'hero_height', 'tp_design_hero', __( 'Height', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $heights ), 'choices' => $heights ) );

	// ---- Footer.
	$cols = array(
		'auto' => __( 'Automatic', 'taxi-peninsula' ),
		'2'    => '2',
		'3'    => '3',
		'4'    => '4',
	);
	$add( 'footer_cols', 'tp_design_footer', __( 'Columns on computers', 'taxi-peninsula' ), 'select', array( 'sanitize' => $choice( $cols ), 'choices' => $cols ) );
	$add( 'footer_menu', 'tp_design_footer', __( 'Show the footer menu', 'taxi-peninsula' ), 'checkbox', array( 'sanitize' => 'tp_sanitize_checkbox', 'description' => __( 'Edit its links under Appearance → Menus.', 'taxi-peninsula' ) ) );
	foreach ( array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
	) as $key => $label ) {
		/* translators: %s: social network */
		$add( 'social_' . $key, 'tp_design_footer', sprintf( __( '%s page link', 'taxi-peninsula' ), $label ), 'url', array( 'sanitize' => 'esc_url_raw', 'input_attrs' => array( 'placeholder' => 'https://' ) ) );
	}
	$add(
		'copyright',
		'tp_design_footer',
		__( 'Copyright line', 'taxi-peninsula' ),
		'text',
		array(
			'sanitize'    => static fn( $v ) => wp_kses( $v, array( 'a' => array( 'href' => true ), 'strong' => array(), 'em' => array() ) ),
			'description' => __( '{year} and {site} are filled in for you.', 'taxi-peninsula' ),
		)
	);

	// ---- Pages & blog.
	$banners = array(
		'color' => __( 'Main colour', 'taxi-peninsula' ),
		'image' => __( 'Image', 'taxi-peninsula' ),
		'plain' => __( 'Plain (light background)', 'taxi-peninsula' ),
	);
	$add( 'banner', 'tp_design_pages', __( 'Page title banner', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $banners ), 'choices' => $banners, 'description' => __( 'With “Image”, a page’s featured image is used when it has one.', 'taxi-peninsula' ) ) );
	$add( 'banner_image', 'tp_design_pages', __( 'Default banner image', 'taxi-peninsula' ), 'image', array( 'sanitize' => 'absint', 'active_callback' => static fn() => 'image' === tp_design( 'banner' ) ) );
	$blog = array(
		'grid' => __( 'Grid of cards', 'taxi-peninsula' ),
		'list' => __( 'List (image beside text)', 'taxi-peninsula' ),
	);
	$add( 'blog_layout', 'tp_design_pages', __( 'Blog layout', 'taxi-peninsula' ), 'radio', array( 'sanitize' => $choice( $blog ), 'choices' => $blog ) );
	$add( 'blog_sidebar', 'tp_design_pages', __( 'Show the blog sidebar', 'taxi-peninsula' ), 'checkbox', array( 'sanitize' => 'tp_sanitize_checkbox', 'description' => __( 'On the blog and on each post.', 'taxi-peninsula' ) ) );
	$wp_customize->add_setting(
		'posts_per_page',
		array(
			'type'              => 'option',
			'capability'        => 'manage_options',
			'default'           => 10,
			'sanitize_callback' => static fn( $v ) => max( 1, min( 50, absint( $v ) ) ),
		)
	);
	$wp_customize->add_control(
		'posts_per_page',
		array(
			'section'     => 'tp_design_pages',
			'label'       => __( 'Posts per page', 'taxi-peninsula' ),
			'type'        => 'number',
			'input_attrs' => array( 'min' => 1, 'max' => 50 ),
		)
	);
	$add( 'back_to_top', 'tp_design_pages', __( 'Show a “Back to top” button', 'taxi-peninsula' ), 'checkbox', array( 'sanitize' => 'tp_sanitize_checkbox' ) );
}

add_action( 'customize_controls_enqueue_scripts', static function () {
	wp_enqueue_script( 'tp-customizer-design', TP_URI . '/assets/js/customizer-design.js', array( 'customize-controls' ), TP_VERSION, true );
	wp_localize_script(
		'tp-customizer-design',
		'tpDesign',
		array(
			'schemes' => tp_color_schemes(),
			'i18n'    => array(
				'text'    => __( 'Body text is hard to read on this background (contrast %s:1, needs 4.5:1).', 'taxi-peninsula' ),
				'link'    => __( 'Links are hard to read on the page background (contrast %s:1, needs 4.5:1).', 'taxi-peninsula' ),
				'primary' => __( 'White text on the main colour is hard to read (contrast %s:1, needs 4.5:1). Pick a darker main colour.', 'taxi-peninsula' ),
				'accent'  => __( 'The accent colour doesn’t stand out on the main colour (contrast %s:1, needs 3:1).', 'taxi-peninsula' ),
				'footer'  => __( 'Footer text is hard to read on this colour (contrast %s:1, needs 4.5:1).', 'taxi-peninsula' ),
				'onAccent'=> __( 'Text on accent buttons is hard to read (contrast %s:1, needs 4.5:1).', 'taxi-peninsula' ),
				'px'      => __( '%s px', 'taxi-peninsula' ),
				'pct'     => '%s%',
			),
		)
	);
} );
