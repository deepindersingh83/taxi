<?php
/**
 * SEO: meta description, Open Graph / Twitter tags, and schema.org
 * structured data (LocalBusiness, Service, FAQPage).
 *
 * Meta and social tags step aside automatically when an SEO plugin
 * (Yoast, Rank Math, All in One SEO, SEOPress) is active. The structured
 * data stays, because those plugins don't describe a taxi business.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/**
 * Description for the current view.
 */
function tp_meta_description() {
	if ( is_front_page() ) {
		$text = tp_opt( 'hero_text' );
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		$text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		if ( '' === trim( $text ) && 'tp_area' === $post->post_type ) {
			/* translators: 1: area, 2: phone */
			$text = sprintf( __( 'Wheelchair accessible taxi in %1$s. Ramp and hoist vehicles, trained drivers, door-to-door. Book online or call %2$s.', 'taxi-peninsula' ), $post->post_title, tp_opt( 'phone_display' ) );
		}
	} elseif ( is_post_type_archive( 'tp_service' ) ) {
		$text = __( 'Wheelchair accessible taxi services in Melbourne: hospital and medical trips, airport transfers, NDIS and aged-care transport, group travel and events.', 'taxi-peninsula' );
	} elseif ( is_post_type_archive( 'tp_area' ) ) {
		$text = __( 'Suburbs we cover with wheelchair accessible taxis across Melbourne and the Mornington Peninsula.', 'taxi-peninsula' );
	} elseif ( is_home() ) {
		$text = __( 'News and travel tips for passengers using wheelchair accessible taxis in Melbourne.', 'taxi-peninsula' );
	} elseif ( is_archive() ) {
		$text = wp_strip_all_tags( get_the_archive_description() );
	} else {
		$text = get_bloginfo( 'description' );
	}
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( '' === $text ) {
		/* translators: 1: site name, 2: phone */
		$text = sprintf( __( '%1$s — wheelchair accessible taxis in Melbourne and the Mornington Peninsula. Book online or call %2$s.', 'taxi-peninsula' ), get_bloginfo( 'name' ), tp_opt( 'phone_display' ) );
	}
	return $text ? wp_html_excerpt( $text, 155, '…' ) : '';
}

function tp_share_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		return get_the_post_thumbnail_url( null, 'large' );
	}
	$hero = (int) tp_opt( 'hero_image' );
	if ( $hero ) {
		return wp_get_attachment_image_url( $hero, 'large' );
	}
	$logo = (int) get_theme_mod( 'custom_logo' );
	if ( $logo ) {
		return wp_get_attachment_image_url( $logo, 'full' );
	}
	return TP_URI . '/screenshot.png';
}

add_action( 'wp_head', 'tp_output_meta_tags', 2 );
function tp_output_meta_tags() {
	if ( tp_seo_plugin_active() || is_404() ) {
		return;
	}
	$desc  = tp_meta_description();
	$title = wp_get_document_title();
	$path  = isset( $GLOBALS['wp']->request ) ? (string) $GLOBALS['wp']->request : '';
	$url   = is_singular() ? get_permalink() : home_url( $path ? '/' . $path . '/' : '/' );

	$tags = array(
		'og:site_name' => get_bloginfo( 'name' ),
		'og:locale'    => 'en_AU',
		'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
		'og:title'     => $title,
		'og:url'       => $url,
		'og:image'     => tp_share_image(),
	);
	if ( $desc ) {
		printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $desc ) );
		$tags['og:description'] = $desc;
	}
	foreach ( $tags as $property => $content ) {
		printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $property ), esc_attr( $content ) );
	}
	echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";

	if ( is_singular() ) {
		printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( get_permalink() ) );
	}
}

// Core adds its own canonical for singular views; ours includes it, so avoid duplicates.
add_action(
	'wp',
	static function () {
		if ( ! tp_seo_plugin_active() ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}
);

/**
 * The business as a schema.org LocalBusiness node.
 */
function tp_business_schema() {
	$areas = array_map(
		static function ( $a ) {
			return array( '@type' => 'Place', 'name' => $a );
		},
		tp_service_areas()
	);

	$node = array(
		'@type'     => 'LocalBusiness',
		'@id'       => home_url( '/#business' ),
		'name'      => get_bloginfo( 'name' ),
		'url'       => home_url( '/' ),
		'telephone' => tp_opt( 'phone_link' ),
		'email'     => tp_opt( 'email' ),
		'image'     => tp_share_image(),
		'address'   => array(
			'@type'           => 'PostalAddress',
			'addressLocality' => 'Melbourne',
			'addressRegion'   => 'VIC',
			'addressCountry'  => 'AU',
		),
		'description' => tp_opt( 'hero_text' ),
		'areaServed'  => $areas,
	);

	if ( tp_opt( 'open_247' ) ) {
		$node['openingHoursSpecification'] = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
			'opens'     => '00:00',
			'closes'    => '23:59',
		);
	}

	$logo = (int) get_theme_mod( 'custom_logo' );
	if ( $logo ) {
		$node['logo'] = wp_get_attachment_image_url( $logo, 'full' );
	}
	$node = apply_filters( 'tp_business_schema', $node );

	$same_as = array_values( array_filter( array( tp_setting( 'gbp_url' ), tp_setting( 'google_reviews_url' ) ) ) );
	if ( $same_as ) {
		$node['sameAs'] = array_values( array_unique( $same_as ) );
	}
	return $node;
}

add_action( 'wp_footer', 'tp_output_schema', 99 );
function tp_output_schema() {
	$graph = array( tp_business_schema() );

	if ( is_singular( array( 'tp_service', 'tp_area' ) ) ) {
		$post    = get_queried_object();
		$service = array(
			'@type'       => 'Service',
			'name'        => 'tp_area' === $post->post_type
				/* translators: %s: area */
				? sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), $post->post_title )
				: $post->post_title,
			'url'         => get_permalink( $post ),
			'description' => tp_meta_description(),
			'provider'    => array( '@id' => home_url( '/#business' ) ),
			'serviceType' => __( 'Wheelchair accessible taxi', 'taxi-peninsula' ),
		);
		if ( 'tp_area' === $post->post_type ) {
			$service['areaServed'] = array( '@type' => 'Place', 'name' => $post->post_title . ', VIC' );
		}
		$graph[] = $service;
	}

	$crumbs = tp_breadcrumb_schema();
	if ( $crumbs ) {
		$graph[] = $crumbs;
	}

	$faqs = tp_faq_schema_items();
	if ( $faqs ) {
		$graph[] = array(
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(
				static function ( $f ) {
					return array(
						'@type'          => 'Question',
						'name'           => $f['q'],
						'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
					);
				},
				$faqs
			),
		);
	}

	printf(
		"<script type=\"application/ld+json\">%s</script>\n",
		wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
	);
}

/**
 * "Wheelchair taxi Frankston" reads better in search results than "Frankston".
 */
add_filter( 'document_title_parts', static function ( $parts ) {
	if ( is_singular( 'tp_area' ) ) {
		/* translators: %s: suburb */
		$parts['title'] = sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), single_post_title( '', false ) );
	} elseif ( is_post_type_archive( 'tp_area' ) ) {
		$parts['title'] = __( 'Areas we cover', 'taxi-peninsula' );
	} elseif ( is_post_type_archive( 'tp_service' ) ) {
		$parts['title'] = __( 'Our services', 'taxi-peninsula' );
	}
	return $parts;
} );

/**
 * Keep private utility pages out of search engines.
 */
add_filter( 'wp_robots', static function ( $robots ) {
	$pages = (array) get_option( 'tp_pages', array() );
	foreach ( array( 'lookup', 'driver' ) as $key ) {
		if ( ! empty( $pages[ $key ] ) && is_page( $pages[ $key ] ) ) {
			$robots['noindex'] = true;
		}
	}
	return $robots;
} );
