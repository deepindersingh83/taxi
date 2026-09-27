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

add_action( 'wp_enqueue_scripts', 'tp_enqueue' );
function tp_enqueue() {
	// Atkinson Hyperlegible was designed by the Braille Institute for low-vision readers.
	wp_enqueue_style( 'tp-fonts', 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&display=swap', array(), null );
	wp_enqueue_style( 'tp-main', TP_URI . '/assets/css/main.css', array(), TP_VERSION );
	wp_enqueue_style( 'tp-patterns', TP_URI . '/assets/css/patterns.css', array( 'tp-main' ), TP_VERSION );
	wp_enqueue_script( 'tp-main', TP_URI . '/assets/js/main.js', array(), TP_VERSION, true );
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
	<script>try{var d=document.documentElement,s=localStorage.getItem('tp-text-size'),c=localStorage.getItem('tp-contrast'),t=localStorage.getItem('tp-theme');if(s)d.setAttribute('data-text-size',s);if(c==='high')d.setAttribute('data-contrast','high');if(t==='dark'||t==='light')d.setAttribute('data-theme',t);}catch(e){}</script>
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
