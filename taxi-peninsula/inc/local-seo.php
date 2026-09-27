<?php
/**
 * Local SEO helpers: breadcrumbs (+ BreadcrumbList data), sitemap clean-up,
 * Google review requests after completed trips, and image alt-text safeguards.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Breadcrumbs.
 * ---------------------------------------------------------------------- */

/**
 * Trail for the current view as [ [name, url], … ]. The last item is the current page.
 *
 * @return array[]
 */
function tp_breadcrumb_trail() {
	if ( is_front_page() ) {
		return array();
	}
	$trail = array( array( __( 'Home', 'taxi-peninsula' ), home_url( '/' ) ) );

	$blog_id  = (int) get_option( 'page_for_posts' );
	$blog_url = $blog_id ? get_permalink( $blog_id ) : tp_page_url_by_template( 'blog' );
	$blog     = $blog_url ? array( $blog_id ? get_the_title( $blog_id ) : __( 'Blog', 'taxi-peninsula' ), $blog_url ) : null;

	if ( is_singular( 'tp_service' ) ) {
		$trail[] = array( __( 'Services', 'taxi-peninsula' ), tp_page_url_by_template( 'services', get_post_type_archive_link( 'tp_service' ) ) );
		$trail[] = array( single_post_title( '', false ), get_permalink() );
	} elseif ( is_singular( 'tp_area' ) ) {
		$trail[] = array( __( 'Areas we cover', 'taxi-peninsula' ), get_post_type_archive_link( 'tp_area' ) );
		/* translators: %s: suburb */
		$trail[] = array( sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), single_post_title( '', false ) ), get_permalink() );
	} elseif ( is_singular( 'post' ) ) {
		if ( $blog ) {
			$trail[] = $blog;
		}
		$cats = get_the_category();
		if ( $cats ) {
			$trail[] = array( $cats[0]->name, get_category_link( $cats[0] ) );
		}
		$trail[] = array( single_post_title( '', false ), get_permalink() );
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_queried_object_id() ) ) as $ancestor ) {
			$trail[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
		}
		$trail[] = array( single_post_title( '', false ), get_permalink() );
	} elseif ( is_home() ) {
		$trail[] = array( $blog_id ? get_the_title( $blog_id ) : __( 'Blog', 'taxi-peninsula' ), $blog_url ?: home_url( '/' ) );
	} elseif ( is_post_type_archive( 'tp_service' ) ) {
		$trail[] = array( __( 'Services', 'taxi-peninsula' ), get_post_type_archive_link( 'tp_service' ) );
	} elseif ( is_post_type_archive( 'tp_area' ) ) {
		$trail[] = array( __( 'Areas we cover', 'taxi-peninsula' ), get_post_type_archive_link( 'tp_area' ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		if ( $blog ) {
			$trail[] = $blog;
		}
		$term    = get_queried_object();
		$trail[] = array( $term->name, get_term_link( $term ) );
	} elseif ( is_search() ) {
		/* translators: %s: search query */
		$trail[] = array( sprintf( __( 'Search: %s', 'taxi-peninsula' ), get_search_query() ), get_search_link() );
	} elseif ( is_archive() ) {
		if ( $blog ) {
			$trail[] = $blog;
		}
		$trail[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	} else {
		return array();
	}
	return $trail;
}

function tp_breadcrumbs() {
	$trail = tp_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return;
	}
	echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'taxi-peninsula' ) . '"><ol>';
	$last = count( $trail ) - 1;
	foreach ( $trail as $i => $crumb ) {
		if ( $i === $last || ! $crumb[1] ) {
			printf( '<li><span aria-current="page">%s</span></li>', esc_html( $crumb[0] ) );
		} else {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $crumb[1] ), esc_html( $crumb[0] ) );
		}
	}
	echo '</ol></nav>';
}

/**
 * BreadcrumbList node for the structured data graph.
 */
function tp_breadcrumb_schema() {
	$trail = tp_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return null;
	}
	$items = array();
	foreach ( $trail as $i => $crumb ) {
		$item = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $crumb[0],
		);
		if ( $crumb[1] ) {
			$item['item'] = $crumb[1];
		}
		$items[] = $item;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}

/* -------------------------------------------------------------------------
 * Sitemap: keep private pages and user archives out.
 * ---------------------------------------------------------------------- */

add_filter( 'wp_sitemaps_posts_query_args', static function ( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$pages   = (array) get_option( 'tp_pages', array() );
		$exclude = array_filter( array( (int) ( $pages['lookup'] ?? 0 ), (int) ( $pages['driver'] ?? 0 ) ) );
		if ( $exclude ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $exclude );
		}
	}
	return $args;
}, 10, 2 );

add_filter( 'wp_sitemaps_add_provider', static function ( $provider, $name ) {
	return ( 'users' === $name && tp_setting( 'sec_block_user_enum' ) ) ? false : $provider;
}, 10, 2 );

/* -------------------------------------------------------------------------
 * Google review requests after a completed trip.
 * ---------------------------------------------------------------------- */

add_action( 'tp_booking_status_changed', 'tp_maybe_request_review', 30, 3 );
function tp_maybe_request_review( $post_id, $old, $status ) {
	$url = tp_setting( 'google_reviews_url' );
	if ( 'completed' !== $status || ! $url || ! tp_setting( 'review_request' ) ) {
		return;
	}
	$b = tp_get_booking( $post_id );

	// Only for recent trips, so bulk-completing old bookings doesn't send a flood.
	$pickup = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $b['date'] . ' ' . ( $b['time'] ?: '00:00' ), wp_timezone() );
	if ( ! $pickup || $pickup->getTimestamp() < time() - 2 * DAY_IN_SECONDS ) {
		return;
	}

	// Once per customer (matched by phone number).
	$asked = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_tp_phone', 'value' => $b['phone'] ),
				array( 'key' => '_tp_review_requested', 'compare' => 'EXISTS' ),
			),
		)
	);
	if ( $asked ) {
		return;
	}
	update_post_meta( $post_id, '_tp_review_requested', current_time( 'mysql' ) );

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	if ( is_email( $b['email'] ) ) {
		/* translators: %s: passenger name */
		$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $b['name'] ) . "\n\n";
		$body .= __( 'Thank you for travelling with us. If you have a minute, a short Google review helps other passengers and carers find an accessible taxi they can trust:', 'taxi-peninsula' ) . "\n\n" . $url . "\n\n";
		/* translators: %s: phone */
		$body .= sprintf( __( 'Something we could do better? Reply to this email or call %s — we read every message.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) . "\n\n" . $site . "\n";
		wp_mail(
			$b['email'],
			/* translators: %s: site name */
			sprintf( __( 'How was your trip with %s?', 'taxi-peninsula' ), $site ),
			$body,
			tp_mail_headers( tp_opt( 'email' ) )
		);
	}
	tp_booking_sms(
		$post_id,
		$b['phone'],
		/* translators: 1: site, 2: review link */
		sprintf( __( 'Thanks for travelling with %1$s! A quick Google review would mean a lot: %2$s', 'taxi-peninsula' ), $site, $url ),
		__( 'customer (review request)', 'taxi-peninsula' )
	);
	tp_add_log( $post_id, array( 'note' => __( 'Google review requested', 'taxi-peninsula' ) ) );
}

/* -------------------------------------------------------------------------
 * Image alt text.
 * ---------------------------------------------------------------------- */

/**
 * Alt text for a post's featured image, falling back to a descriptive default.
 */
function tp_thumbnail_alt( $post, $fallback = '' ) {
	$alt = trim( (string) get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true ) );
	return '' !== $alt ? $alt : ( $fallback ?: get_the_title( $post ) );
}

/**
 * Remind editors when a featured image has no alt text.
 */
add_action( 'admin_notices', static function () {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, array( 'post', 'page', 'tp_service', 'tp_area', 'tp_fleet' ), true ) ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$thumb   = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
	if ( $thumb && '' === trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ) ) {
		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'The featured image has no alt text.', 'taxi-peninsula' ),
			esc_html__( 'Describe what it shows (e.g. "Driver securing a power wheelchair on the rear ramp") — it helps screen-reader users and Google Images. Descriptive file names help too, e.g. wheelchair-taxi-ramp-frankston.jpg.', 'taxi-peninsula' ),
			esc_url( get_edit_post_link( $thumb ) ),
			esc_html__( 'Add alt text', 'taxi-peninsula' )
		);
	}
} );

/**
 * Tidy uploaded file names: lower-case, hyphens, no camera junk like "IMG_1234 (1)".
 */
add_filter( 'sanitize_file_name', static function ( $name ) {
	$info = pathinfo( $name );
	$ext  = isset( $info['extension'] ) ? '.' . strtolower( $info['extension'] ) : '';
	$base = sanitize_title( $info['filename'] );
	return ( $base ?: 'file' ) . $ext;
}, 20 );
