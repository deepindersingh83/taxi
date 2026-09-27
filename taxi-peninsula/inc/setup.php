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

	add_editor_style( 'assets/css/editor.css' );
}

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

add_action( 'wp_enqueue_scripts', 'tp_enqueue' );
function tp_enqueue() {
	// Atkinson Hyperlegible was designed by the Braille Institute for low-vision readers.
	wp_enqueue_style( 'tp-fonts', 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&display=swap', array(), null );
	wp_enqueue_style( 'tp-main', TP_URI . '/assets/css/main.css', array(), TP_VERSION );
	wp_enqueue_script( 'tp-main', TP_URI . '/assets/js/main.js', array(), TP_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

add_filter( 'wp_resource_hints', 'tp_resource_hints', 10, 2 );
function tp_resource_hints( $urls, $relation ) {
	if ( 'preconnect' === $relation ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}

/**
 * Apply the visitor's saved text size / contrast before first paint so the page doesn't jump.
 */
add_action( 'wp_head', 'tp_prepaint_prefs', 1 );
function tp_prepaint_prefs() {
	?>
	<script>try{var d=document.documentElement,s=localStorage.getItem('tp-text-size'),c=localStorage.getItem('tp-contrast');if(s)d.setAttribute('data-text-size',s);if(c==='high')d.setAttribute('data-contrast','high');}catch(e){}</script>
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
