<?php
/**
 * Theme setup, assets, menus and widget areas.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'tp_setup' );
function tp_setup() {
	load_theme_textdomain( 'taxi-peninsula', TP_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_image_size( 'tp-card', 720, 450, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'taxi-peninsula' ),
			'footer'  => __( 'Footer menu', 'taxi-peninsula' ),
		)
	);

	add_editor_style( array( 'assets/css/editor.css', 'assets/css/patterns.css' ) );
}

add_action( 'init', static function () {
	register_block_pattern_category(
		'taxi-peninsula',
		array(
			'label'       => __( 'Taxi Peninsula', 'taxi-peninsula' ),
			'description' => __( 'Ready-made sections and page starters for your taxi website.', 'taxi-peninsula' ),
		)
	);
} );

add_action( 'widgets_init', 'tp_widgets_init' );
function tp_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Blog sidebar', 'taxi-peninsula' ),
			'id'            => 'sidebar-blog',
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	register_sidebar(
		array(
			'name'          => __( 'Footer', 'taxi-peninsula' ),
			'id'            => 'sidebar-footer',
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}

/**
 * URL of a theme asset, using the minified copy (built by tools/build.js)
 * unless SCRIPT_DEBUG is on or the copy is missing.
 *
 * @param string $path e.g. 'assets/css/main.css'.
 */
function tp_asset( $path ) {
	return TP_URI . '/' . ( tp_asset_min( $path ) ? preg_replace( '/\.(css|js)$/', '.min.$1', $path ) : $path );
}

function tp_asset_min( $path ) {
	return ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && file_exists( TP_DIR . '/' . preg_replace( '/\.(css|js)$/', '.min.$1', $path ) );
}

/**
 * Enqueue a theme stylesheet; the Arabic swap (inc/languages.php) then picks
 * the matching -rtl(.min) file.
 */
function tp_enqueue_style( $handle, $path, $deps = array() ) {
	wp_enqueue_style( $handle, tp_asset( $path ), $deps, TP_VERSION );
	if ( tp_asset_min( $path ) ) {
		wp_style_add_data( $handle, 'suffix', '.min' );
	}
}

/**
 * The front-end stylesheet is the single minified bundle (main + patterns + animations).
 */
function tp_css_bundled() {
	return tp_asset_min( 'assets/css/main.css' );
}

/**
 * Critical CSS: the rules needed for the top of each page, printed inline so
 * the page shows straight away while the full stylesheet loads without
 * blocking. Made by tools/critical.js; skipped automatically when it was
 * made for an older stylesheet (it then can't match the page).
 */
function tp_critical_css() {
	static $css = null;
	if ( null !== $css ) {
		return $css;
	}
	$css = '';
	if ( is_admin() || is_customize_preview() || ! tp_css_bundled() || ! apply_filters( 'tp_critical_css_enabled', true ) ) {
		return $css;
	}
	$rtl    = is_rtl();
	$crit   = TP_DIR . '/assets/css/' . ( $rtl ? 'critical-rtl.min.css' : 'critical.min.css' );
	$bundle = TP_DIR . '/assets/css/' . ( $rtl ? 'main-rtl.min.css' : 'main.min.css' );
	if ( ! is_readable( $crit ) || ! is_readable( $bundle ) ) {
		return $css;
	}
	// Check the critical file was made from this exact stylesheet (cached until either file changes).
	$stamp = filemtime( $crit ) . '|' . filemtime( $bundle ) . '|' . filesize( $bundle );
	$seen  = (array) get_option( 'tp_critical_ok', array() );
	$key   = $rtl ? 'rtl' : 'ltr';
	if ( ( $seen[ $key ]['stamp'] ?? '' ) !== $stamp ) {
		$head          = (string) file_get_contents( $crit, false, null, 0, 40 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$ok            = preg_match( '#^/\*bundle:([0-9a-f]{12})\*/#', $head, $m ) && substr( sha1_file( $bundle ), 0, 12 ) === $m[1];
		$seen[ $key ]  = array( 'stamp' => $stamp, 'ok' => $ok );
		update_option( 'tp_critical_ok', $seen, true );
	}
	if ( ! empty( $seen[ $key ]['ok'] ) ) {
		$css = (string) file_get_contents( $crit ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	return $css;
}

add_action(
	'wp_head',
	static function () {
		$css = tp_critical_css();
		if ( '' !== $css ) {
			echo '<style id="tp-critical">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the theme's own built CSS file.
		}
	},
	1
);

// With critical CSS inline, the full stylesheet loads early but without holding up the page.
add_filter(
	'style_loader_tag',
	static function ( $tag, $handle, $href ) {
		if ( 'tp-main' !== $handle || '' === tp_critical_css() ) {
			return $tag;
		}
		$async = preg_replace( "/media=(['\"])all\\1/", 'media="print" onload="this.media=\'all\'"', $tag, 1 );
		return '<link rel="preload" as="style" href="' . esc_url( $href ) . '">' . "\n" . $async . '<noscript>' . $tag . '</noscript>' . "\n";
	},
	10,
	3
);

// No emoji script: the theme uses its own icons and every browser draws emoji.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
add_filter( 'emoji_svg_url', '__return_false' );

add_action( 'wp_enqueue_scripts', 'tp_enqueue' );
function tp_enqueue() {
	// Fonts are self-hosted; the chosen one is inlined in inc/design.php.
	tp_enqueue_style( 'tp-main', 'assets/css/main.css' );
	if ( tp_css_bundled() ) {
		// main.min.css already holds the patterns and animations (tools/build.js); keep the handle for anything that depends on it.
		wp_register_style( 'tp-patterns', false, array( 'tp-main' ), TP_VERSION );
		wp_enqueue_style( 'tp-patterns' );
	} else {
		tp_enqueue_style( 'tp-patterns', 'assets/css/patterns.css', array( 'tp-main' ) );
	}
	wp_enqueue_script( 'tp-main', tp_asset( 'assets/js/main.js' ), array(), TP_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_add_inline_script(
		'tp-main',
		'window.tpI18n = ' . wp_json_encode(
			array(
				/* translators: %s: suburb */
				'yes'      => __( 'Yes — we cover %s.', 'taxi-peninsula' ),
				/* translators: %s: suburb */
				'book'     => __( 'Book a pick-up in %s', 'taxi-peninsula' ),
				/* translators: %s: suburb */
				'about'    => __( 'Wheelchair taxis in %s', 'taxi-peninsula' ),
				/* translators: %s: what the visitor typed */
				'no'       => __( '"%s" is not on our regular list, but we often travel further on request.', 'taxi-peninsula' ),
				/* translators: %s: phone number */
				'call'     => __( 'Call %s to check', 'taxi-peninsula' ),
				/* translators: %d: number of questions */
				'faqCount' => __( '%d matching questions', 'taxi-peninsula' ),
				'close'    => __( 'Close', 'taxi-peninsula' ),
				'more'     => __( 'Read more', 'taxi-peninsula' ),
				/* translators: %s: closing time */
				'openUntil'    => __( 'Open now · phones answered until %s', 'taxi-peninsula' ),
				'closed'       => __( 'Closed now', 'taxi-peninsula' ),
				/* translators: %s: when we open */
				'closedWhen'   => __( 'Closed now · %s', 'taxi-peninsula' ),
				/* translators: %s: time */
				'openAt'       => __( 'we open at %s', 'taxi-peninsula' ),
				/* translators: %s: time */
				'openTomorrow' => __( 'we open tomorrow at %s', 'taxi-peninsula' ),
				/* translators: 1: weekday, 2: time */
				'openDay'      => __( 'we open %1$s at %2$s', 'taxi-peninsula' ),
				'less'     => __( 'Show less', 'taxi-peninsula' ),
				'passenger'   => __( 'passenger', 'taxi-peninsula' ),
				'passengers'  => __( 'passengers', 'taxi-peninsula' ),
				'wheelchair'  => __( 'wheelchair', 'taxi-peninsula' ),
				'wheelchairs' => __( 'wheelchairs', 'taxi-peninsula' ),
				'pickup'      => __( 'Pick-up', 'taxi-peninsula' ),
				'dropoff'     => __( 'Drop-off', 'taxi-peninsula' ),
				'yesShort'    => __( 'Yes', 'taxi-peninsula' ),
				'sending'     => __( 'Sending…', 'taxi-peninsula' ),
				'now'         => __( 'Now', 'taxi-peninsula' ),
					/* translators: %s: menu item */
					'showSubmenu' => __( 'Show more: %s', 'taxi-peninsula' ),
				/* translators: %s: suggested email address */
				'emailDidYouMean' => __( 'Did you mean %s?', 'taxi-peninsula' ),
				'emailUseIt'      => __( 'Yes, use this', 'taxi-peninsula' ),
				'phoneBad'      => __( 'Please enter an Australian phone number, e.g. 0412 345 678 or (03) 9123 4567.', 'taxi-peninsula' ),
				'phoneMobile'   => __( 'Please enter a mobile number starting with 04, so we can send texts.', 'taxi-peninsula' ),
				'phoneLandline' => __( 'That looks like a landline — we will call you instead of sending texts.', 'taxi-peninsula' ),
				'prev'     => __( 'Previous photo', 'taxi-peninsula' ),
				'next'     => __( 'Next photo', 'taxi-peninsula' ),
				/* translators: 1: photo number, 2: total */
				'of'       => __( 'Photo %1$d of %2$d', 'taxi-peninsula' ),
			)
		) . ';',
		'before'
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

/**
 * Apply the visitor's saved text size / contrast before first paint so the page doesn't jump.
 */
add_action( 'wp_head', 'tp_prepaint_prefs', 1 );
function tp_prepaint_prefs() {
	?>
	<script>try{var d=document.documentElement;['tp-text-size','tp-contrast','tp-theme'].forEach(function(k){localStorage.removeItem(k);});if(localStorage.getItem('tp-motion')==='off')d.setAttribute('data-motion','off');}catch(e){}</script>
	<?php
}

/**
 * Fallback primary menu when none is assigned yet.
 */
function tp_menu_fallback() {
	$items = array(
		home_url( '/' ) => __( 'Home', 'taxi-peninsula' ),
	);
	$posts_page = (int) get_option( 'page_for_posts' );
	if ( $posts_page ) {
		$items[ get_permalink( $posts_page ) ] = __( 'Blog', 'taxi-peninsula' );
	}
	$items[ home_url( '/#services' ) ] = __( 'Services', 'taxi-peninsula' );
	$items[ home_url( '/#areas' ) ]    = __( 'Areas', 'taxi-peninsula' );

	echo '<ul class="menu">';
	foreach ( $items as $url => $label ) {
		printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * URL of the booking page, or the front-page booking anchor.
 */
function tp_booking_page_url() {
	return tp_page_url_by_template( 'book', home_url( '/#book' ) );
}

add_filter( 'excerpt_length', static function () {
	return 28;
} );

add_filter( 'excerpt_more', static function () {
	return '…';
} );

/**
 * Hide menu links to pages/posts that aren't published yet (e.g. draft policy
 * pages created by Setup), so visitors never hit a "not found" page.
 */
add_filter( 'wp_nav_menu_objects', static function ( $items ) {
	return array_values(
		array_filter(
			$items,
			static function ( $item ) {
				if ( 'post_type' !== $item->type || ! $item->object_id ) {
					return true;
				}
				return 'publish' === get_post_status( (int) $item->object_id ) || current_user_can( 'edit_post', (int) $item->object_id );
			}
		)
	);
} );
