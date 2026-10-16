<?php
/**
 * Search engines and AI assistants.
 *
 * - Google Search Console and Bing Webmaster Tools verification tags.
 * - Richer business data for Google: street address, map position,
 *   opening hours, price range, services offered, languages, credentials,
 *   plus a TaxiService and a WebSite (with site search) node.
 * - IndexNow: new and changed pages are sent to Bing (and other IndexNow
 *   search engines) straight away. Bing results also feed ChatGPT search
 *   and Microsoft Copilot.
 * - /llms.txt: a plain summary of the business for AI assistants.
 * - robots.txt rules for AI crawlers (allow AI search, choose about AI
 *   training).
 * - Thin pages (search results, tag/date/author archives, attachments)
 *   are kept out of search results.
 * - Speed: the home page photo is loaded first; new uploads are saved as
 *   WebP when the server supports it.
 * - Footer: full business name, address and phone (matching your Google
 *   profile), suburbs served and accreditation numbers.
 * - "Helpful official information" links on service pages.
 *
 * Settings: Taxi settings → Settings → Search engines & AI, and
 * Marketing → AI & Bing search.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['search'] = array( __( 'Search engines & AI', 'taxi-peninsula' ), __( 'Business details used by Google, Bing and AI assistants. Use exactly the same name, address and phone as your Google Business Profile.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['google_site_verification'] = array( 'search', __( 'Google Search Console verification code', 'taxi-peninsula' ), 'text', '', __( 'In Search Console choose "URL prefix" → "HTML tag" and paste just the content="…" value.', 'taxi-peninsula' ) );
		$s['bing_site_verification']   = array( 'search', __( 'Bing Webmaster Tools verification code', 'taxi-peninsula' ), 'text', '', __( 'In Bing Webmaster Tools choose "HTML Meta Tag" and paste just the content="…" value. (Or import your site from Search Console.)', 'taxi-peninsula' ) );
		$s['biz_street']               = array( 'search', __( 'Street address', 'taxi-peninsula' ), 'text', '', __( 'Leave empty if you do not want a street address shown (service-area business).', 'taxi-peninsula' ) );
		$s['biz_suburb']               = array( 'search', __( 'Suburb', 'taxi-peninsula' ), 'text', '', '' );
		$s['biz_postcode']             = array( 'search', __( 'Postcode', 'taxi-peninsula' ), 'text', '', '' );
		$s['biz_lat']                  = array( 'search', __( 'Latitude', 'taxi-peninsula' ), 'text', '', __( 'From Google Maps: right-click your base → the first number.', 'taxi-peninsula' ) );
		$s['biz_lng']                  = array( 'search', __( 'Longitude', 'taxi-peninsula' ), 'text', '', __( 'The second number.', 'taxi-peninsula' ) );
		$s['price_range']              = array( 'search', __( 'Price range', 'taxi-peninsula' ), 'text', '$$', __( 'e.g. $$ — shown by some search engines.', 'taxi-peninsula' ) );
		$s['cpv_number']               = array( 'search', __( 'Commercial passenger vehicle accreditation / registration', 'taxi-peninsula' ), 'text', '', __( 'Shown in the footer to build trust, e.g. your CPVV booking service provider registration.', 'taxi-peninsula' ) );
		$s['footer_areas']             = array( 'search', __( 'Show "Suburbs we cover" in the footer', 'taxi-peninsula' ), 'checkbox', true, '' );
		$s['official_links']           = array( 'search', __( 'Helpful official links on service pages', 'taxi-peninsula' ), 'textarea', "NDIS — National Disability Insurance Scheme | https://www.ndis.gov.au/\nCommercial Passenger Vehicles Victoria | https://cpv.vic.gov.au/", __( 'One per line: label | address. Linking to trusted official sources helps visitors and shows search engines your pages are reliable. Leave empty to hide.', 'taxi-peninsula' ) );
		$s['noindex_thin']             = array( 'search', __( 'Keep thin pages out of search results', 'taxi-peninsula' ), 'checkbox', true, __( 'Search results, tag, date and author archives and attachment pages.', 'taxi-peninsula' ) );
		$s['webp_uploads']             = array( 'search', __( 'Save new photo uploads as WebP (smaller, faster)', 'taxi-peninsula' ), 'checkbox', true, __( 'Only when your server supports it.', 'taxi-peninsula' ) );
		$s['indexnow']                 = array( 'search', __( 'Tell Bing about new and changed pages instantly (IndexNow)', 'taxi-peninsula' ), 'checkbox', true, '' );
		$s['llms_txt']                 = array( 'search', __( 'Publish /llms.txt for AI assistants', 'taxi-peninsula' ), 'checkbox', true, '' );
		$s['ai_crawlers']              = array( 'search', __( 'AI crawlers', 'taxi-peninsula' ), 'select', 'search', __( 'AI search bots (ChatGPT search, Perplexity, Copilot) are what recommend you to customers — keep them allowed.', 'taxi-peninsula' ), array( 'search' => __( 'Allow AI search, block AI training (recommended)', 'taxi-peninsula' ), 'all' => __( 'Allow all AI bots', 'taxi-peninsula' ), 'none' => __( 'Block all AI bots (not recommended — you will not be recommended by AI assistants)', 'taxi-peninsula' ) ) );
		return $s;
	}
);

/* -------------------------------------------------------------------------
 * Verification tags.
 * ---------------------------------------------------------------------- */

add_action(
	'wp_head',
	static function () {
		$g = trim( (string) tp_setting( 'google_site_verification' ) );
		$b = trim( (string) tp_setting( 'bing_site_verification' ) );
		if ( $g ) {
			printf( "<meta name=\"google-site-verification\" content=\"%s\">\n", esc_attr( tp_verification_value( $g ) ) );
		}
		if ( $b ) {
			printf( "<meta name=\"msvalidate.01\" content=\"%s\">\n", esc_attr( tp_verification_value( $b ) ) );
		}
	},
	1
);

// People often paste the whole <meta> tag: keep just the code before the settings are cleaned.
add_filter(
	'sanitize_option_tp_settings',
	static function ( $value ) {
		if ( is_array( $value ) ) {
			foreach ( array( 'google_site_verification', 'bing_site_verification' ) as $k ) {
				if ( isset( $value[ $k ] ) && is_string( $value[ $k ] ) ) {
					$value[ $k ] = tp_verification_value( wp_unslash( $value[ $k ] ) );
				}
			}
		}
		return $value;
	},
	5
);

/**
 * Accept either the code or the whole <meta …> tag.
 */
function tp_verification_value( $v ) {
	return preg_match( '/content=["\']([^"\']+)["\']/', $v, $m ) ? $m[1] : $v;
}

/* -------------------------------------------------------------------------
 * Structured data.
 * ---------------------------------------------------------------------- */

add_filter(
	'tp_business_schema',
	static function ( $node ) {
		$street = trim( (string) tp_setting( 'biz_street' ) );
		$suburb = trim( (string) tp_setting( 'biz_suburb' ) );
		if ( $street || $suburb ) {
			$node['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $street,
					'addressLocality' => $suburb ?: 'Melbourne',
					'addressRegion'   => 'VIC',
					'postalCode'      => trim( (string) tp_setting( 'biz_postcode' ) ),
					'addressCountry'  => 'AU',
				)
			);
		}
		$lat = (float) tp_setting( 'biz_lat' );
		$lng = (float) tp_setting( 'biz_lng' );
		if ( $lat && $lng ) {
			$node['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng );
		}
		if ( tp_setting( 'price_range' ) ) {
			$node['priceRange'] = (string) tp_setting( 'price_range' );
		}
		if ( empty( $node['openingHoursSpecification'] ) && function_exists( 'tp_hours_schedule' ) ) {
			$names = array( 1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
			$specs = array();
			foreach ( tp_hours_schedule() as $d => $range ) {
				if ( $range ) {
					$specs[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $names[ $d ], 'opens' => $range[0], 'closes' => $range[1] );
				}
			}
			if ( $specs ) {
				$node['openingHoursSpecification'] = $specs;
			}
		}
		if ( function_exists( 'tp_enabled_languages' ) ) {
			$langs = array();
			foreach ( (array) tp_enabled_languages() as $code => $row ) {
				$langs[] = is_array( $row ) && isset( $row[2] ) ? $row[2] : ( is_string( $code ) ? $code : '' );
			}
			$langs = array_values( array_filter( $langs ) );
			if ( $langs ) {
				$node['knowsLanguage'] = $langs;
			}
		}
		$services = function_exists( 'tp_services_list' ) ? tp_services_list() : array();
		if ( $services ) {
			$node['hasOfferCatalog'] = array(
				'@type'           => 'OfferCatalog',
				'name'            => __( 'Wheelchair accessible taxi services', 'taxi-peninsula' ),
				'itemListElement' => array_map(
					static function ( $s ) {
						return array(
							'@type'       => 'Offer',
							'itemOffered' => array_filter( array( '@type' => 'Service', 'name' => $s['title'], 'url' => $s['url'] ) ),
						);
					},
					$services
				),
			);
		}
		$ids = array();
		if ( tp_setting( 'abn' ) ) {
			$ids[] = array( '@type' => 'PropertyValue', 'propertyID' => 'ABN', 'value' => (string) tp_setting( 'abn' ) );
		}
		if ( tp_setting( 'cpv_number' ) ) {
			$ids[] = array( '@type' => 'PropertyValue', 'propertyID' => 'CPVV registration', 'value' => (string) tp_setting( 'cpv_number' ) );
		}
		if ( tp_setting( 'ndis_provider_no' ) ) {
			$ids[] = array( '@type' => 'PropertyValue', 'propertyID' => 'NDIS provider', 'value' => (string) tp_setting( 'ndis_provider_no' ) );
		}
		if ( $ids ) {
			$node['identifier'] = $ids;
		}
		$node['potentialAction'] = array(
			'@type'  => 'ReserveAction',
			'target' => array( '@type' => 'EntryPoint', 'urlTemplate' => strtok( tp_booking_page_url(), '#' ), 'actionPlatform' => array( 'http://schema.org/DesktopWebPlatform', 'http://schema.org/MobileWebPlatform' ) ),
			'result' => array( '@type' => 'Reservation', 'name' => __( 'Wheelchair taxi booking', 'taxi-peninsula' ) ),
		);
		return $node;
	}
);

// WebSite (with site search) and TaxiService nodes on the home page.
add_filter(
	'tp_schema_graph',
	static function ( $graph ) {
		if ( ! is_front_page() ) {
			return $graph;
		}
		$graph[] = array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'publisher'       => array( '@id' => home_url( '/#business' ) ),
			'inLanguage'      => 'en-AU',
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/?s={search_term_string}' ) ),
				'query-input' => 'required name=search_term_string',
			),
		);
		$graph[] = array(
			'@type'       => 'TaxiService',
			'@id'         => home_url( '/#taxi-service' ),
			'name'        => __( 'Wheelchair accessible taxi', 'taxi-peninsula' ),
			'provider'    => array( '@id' => home_url( '/#business' ) ),
			'areaServed'  => array_map( static fn( $a ) => array( '@type' => 'Place', 'name' => $a . ', VIC' ), tp_service_areas() ),
			'url'         => strtok( tp_booking_page_url(), '#' ),
			'description' => tp_opt( 'hero_text' ),
		);
		return $graph;
	}
);

/* -------------------------------------------------------------------------
 * Footer: suburbs, NAP, accreditation.
 * ---------------------------------------------------------------------- */

add_action(
	'tp_footer_columns',
	static function () {
		if ( ! tp_setting( 'footer_areas' ) ) {
			return;
		}
		$areas = get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 8, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
		if ( ! $areas ) {
			return;
		}
		echo '<div><h2 class="site-footer__heading">' . esc_html__( 'Suburbs we cover', 'taxi-peninsula' ) . '</h2><ul class="footer-menu footer-areas">';
		foreach ( $areas as $a ) {
			/* translators: %s: suburb */
			printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( get_permalink( $a ) ), esc_html( sprintf( __( 'Wheelchair taxi %s', 'taxi-peninsula' ), get_the_title( $a ) ) ) );
		}
		$all = get_post_type_archive_link( 'tp_area' );
		if ( $all ) {
			printf( '<li><a href="%1$s"><strong>%2$s</strong></a></li>', esc_url( $all ), esc_html__( 'All areas', 'taxi-peninsula' ) );
		}
		echo '</ul></div>';
	},
	5
);

/**
 * One-line name, address and phone, exactly as on the Google profile.
 */
function tp_footer_nap() {
	$street = trim( (string) tp_setting( 'biz_street' ) );
	$suburb = trim( (string) tp_setting( 'biz_suburb' ) );
	$addr   = $street || $suburb ? trim( $street . ( $street && $suburb ? ', ' : '' ) . $suburb . ' VIC ' . tp_setting( 'biz_postcode' ) ) : '';
	$parts  = array_filter( array( get_bloginfo( 'name' ), $addr, wp_strip_all_tags( tp_opt( 'phone_display' ) ) ) );
	$creds  = array_filter(
		array(
			tp_setting( 'abn' ) ? 'ABN ' . tp_setting( 'abn' ) : '',
			/* translators: %s: number */
			tp_setting( 'cpv_number' ) ? sprintf( __( 'CPVV registration %s', 'taxi-peninsula' ), tp_setting( 'cpv_number' ) ) : '',
			/* translators: %s: number */
			tp_setting( 'ndis_provider_no' ) ? sprintf( __( 'NDIS provider %s', 'taxi-peninsula' ), tp_setting( 'ndis_provider_no' ) ) : '',
		)
	);
	echo '<p class="site-footer__nap">' . esc_html( implode( ' · ', $parts ) ) . '</p>';
	if ( $creds ) {
		echo '<p class="site-footer__creds">' . esc_html( implode( ' · ', $creds ) ) . '</p>';
	}
}

/* -------------------------------------------------------------------------
 * Helpful official links (outbound links to trusted sources).
 * ---------------------------------------------------------------------- */

function tp_official_links() {
	$out = array();
	foreach ( preg_split( '/\R/', (string) tp_setting( 'official_links' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 2 && wp_http_validate_url( $p[1] ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

add_action(
	'tp_service_details_after',
	static function () {
		$links = tp_official_links();
		if ( ! $links ) {
			return;
		}
		echo '<aside class="official-links" aria-labelledby="official-title"><h2 id="official-title">' . esc_html__( 'Helpful official information', 'taxi-peninsula' ) . '</h2><ul>';
		foreach ( $links as $l ) {
			printf( '<li><a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a></li>', esc_url( $l[1] ), esc_html( $l[0] ), esc_html__( '(opens in a new tab)', 'taxi-peninsula' ) );
		}
		echo '</ul></aside>';
	}
);

/* -------------------------------------------------------------------------
 * Thin pages out of search results.
 * ---------------------------------------------------------------------- */

add_filter(
	'wp_robots',
	static function ( $robots ) {
		if ( tp_setting( 'noindex_thin' ) && ( is_search() || is_tag() || is_date() || is_author() || is_attachment() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

/* -------------------------------------------------------------------------
 * Speed.
 * ---------------------------------------------------------------------- */

// The home page photo is the largest thing on screen: fetch it first.
add_action(
	'wp_head',
	static function () {
		if ( ! is_front_page() || ! function_exists( 'tp_current_hero_image' ) ) {
			return;
		}
		$id = (int) tp_current_hero_image();
		if ( ! $id ) {
			return;
		}
		$src    = wp_get_attachment_image_url( $id, 'full' );
		$srcset = wp_get_attachment_image_srcset( $id, 'full' );
		if ( $src ) {
			printf( '<link rel="preload" as="image" href="%1$s"%2$s fetchpriority="high">' . "\n", esc_url( $src ), $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '" imagesizes="100vw"' : '' );
		}
	},
	2
);

add_filter(
	'image_editor_output_format',
	static function ( $formats ) {
		if ( tp_setting( 'webp_uploads' ) && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$formats['image/jpeg'] = 'image/webp';
			$formats['image/png']  = 'image/webp';
		}
		return $formats;
	}
);

/* -------------------------------------------------------------------------
 * robots.txt: AI crawlers.
 * ---------------------------------------------------------------------- */

function tp_ai_bots() {
	return array(
		// Bots that let AI assistants find and recommend you.
		'search'   => array( 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'Perplexity-User', 'Claude-SearchBot', 'Claude-User', 'DuckAssistBot' ),
		// Bots that only collect text to train AI models.
		'training' => array( 'GPTBot', 'Google-Extended', 'CCBot', 'ClaudeBot', 'anthropic-ai', 'Applebot-Extended', 'Bytespider', 'Meta-ExternalAgent', 'cohere-training-data-crawler' ),
	);
}

add_filter(
	'robots_txt',
	static function ( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$mode = (string) tp_setting( 'ai_crawlers' );
		$bots = tp_ai_bots();
		$out  = "\n# AI assistants (Taxi Peninsula theme: Taxi settings → Search engines & AI)\n";
		foreach ( $bots['search'] as $bot ) {
			$out .= "User-agent: {$bot}\n" . ( 'none' === $mode ? "Disallow: /\n\n" : "Allow: /\nDisallow: /wp-admin/\n\n" );
		}
		foreach ( $bots['training'] as $bot ) {
			$out .= "User-agent: {$bot}\n" . ( 'all' === $mode ? "Allow: /\n\n" : "Disallow: /\n\n" );
		}
		if ( tp_setting( 'llms_txt' ) ) {
			$out .= '# Summary for AI assistants: ' . home_url( '/llms.txt' ) . "\n";
		}
		return $output . $out;
	},
	20,
	2
);

/* -------------------------------------------------------------------------
 * /llms.txt and the IndexNow key file.
 * ---------------------------------------------------------------------- */

function tp_indexnow_key() {
	$key = (string) get_option( 'tp_indexnow_key' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = md5( wp_generate_password( 40, true, true ) );
		update_option( 'tp_indexnow_key', $key, false );
	}
	return $key;
}

add_action(
	'parse_request',
	static function () {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$path = '/' . ltrim( substr( $path, strlen( $base ) ), '/' );
		if ( '/llms.txt' === $path && tp_setting( 'llms_txt' ) ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Cache-Control: public, max-age=3600' );
			echo tp_llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text.
			exit;
		}
		if ( tp_setting( 'indexnow' ) && '/' . tp_indexnow_key() . '.txt' === $path ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo tp_indexnow_key(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hex key.
			exit;
		}
	},
	0
);

/**
 * Plain-language summary of the business for AI assistants (llms.txt format).
 */
function tp_llms_txt() {
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$lines = array( '# ' . $site, '' );
	$about = trim( wp_strip_all_tags( (string) ( tp_opt( 'hero_text' ) ?: get_bloginfo( 'description' ) ) ) );
	if ( $about ) {
		$lines[] = '> ' . $about;
		$lines[] = '';
	}
	$lines[] = sprintf( 'Wheelchair accessible taxi service in %s, Australia. Ramp and hoist vehicles with wheelchair restraints; drivers help passengers door to door. Carers travel too. Payment by card, cash, the Multi Purpose Taxi Program (MPTP) or NDIS / aged-care account invoices.', wp_strip_all_tags( (string) tp_opt( 'location' ) ) ?: 'Melbourne' );
	$lines[] = '';
	$lines[] = '## Book or contact';
	$lines[] = '- Book online: ' . strtok( tp_booking_page_url(), '#' );
	$lines[] = '- Phone: ' . wp_strip_all_tags( tp_opt( 'phone_display' ) );
	if ( tp_opt( 'email' ) ) {
		$lines[] = '- Email: ' . tp_opt( 'email' );
	}
	$quote = tp_page_url_by_template( 'quote' );
	if ( $quote ) {
		$lines[] = '- Quotes for long trips and events: ' . $quote;
	}
	if ( function_exists( 'tp_hours_status' ) && ! tp_opt( 'open_247' ) ) {
		$names = array( 1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );
		$hrs   = array();
		foreach ( tp_hours_schedule() as $d => $r ) {
			$hrs[] = $names[ $d ] . ' ' . ( $r ? $r[0] . '-' . $r[1] : 'closed' );
		}
		$lines[] = '- Phone hours: ' . implode( ', ', $hrs ) . ' (online booking any time)';
	}
	$lines[] = '';
	$lines[] = '## Services';
	foreach ( tp_services_list() as $s ) {
		$lines[] = '- ' . ( $s['url'] ? '[' . $s['title'] . '](' . $s['url'] . ')' : $s['title'] ) . ( $s['text'] ? ': ' . wp_strip_all_tags( $s['text'] ) : '' );
	}
	$areas = get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 60, 'orderby' => 'title', 'order' => 'ASC' ) );
	if ( $areas ) {
		$lines[] = '';
		$lines[] = '## Areas served';
		foreach ( $areas as $a ) {
			$lines[] = '- [' . get_the_title( $a ) . '](' . get_permalink( $a ) . ')';
		}
	}
	$faqs = get_posts( array( 'post_type' => 'tp_faq', 'numberposts' => 12, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
	if ( $faqs ) {
		$lines[] = '';
		$lines[] = '## Common questions';
		foreach ( $faqs as $f ) {
			$lines[] = '### ' . get_the_title( $f );
			$lines[] = wp_trim_words( wp_strip_all_tags( $f->post_content ), 80 );
			$lines[] = '';
		}
	}
	$lines[] = '## More';
	foreach ( array( 'ndis' => 'NDIS and aged care', 'fleet' => 'Our vehicles', 'faq' => 'All FAQs', 'contact' => 'Contact', 'about' => 'About us' ) as $key => $label ) {
		$url = tp_page_url_by_template( $key );
		if ( $url ) {
			$lines[] = '- [' . $label . '](' . $url . ')';
		}
	}
	return html_entity_decode( implode( "\n", $lines ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . "\n";
}

/* -------------------------------------------------------------------------
 * IndexNow: tell Bing about new and changed pages straight away.
 * ---------------------------------------------------------------------- */

add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( ! tp_setting( 'indexnow' ) || 'publish' !== $new || wp_is_post_revision( $post ) || ! is_post_type_viewable( $post->post_type ) || in_array( $post->post_type, array( 'tp_venue', 'attachment' ), true ) ) {
			return;
		}
		$queue   = (array) get_option( 'tp_indexnow_queue', array() );
		$queue[] = get_permalink( $post );
		update_option( 'tp_indexnow_queue', array_values( array_unique( array_slice( $queue, -500 ) ) ), false );
		if ( ! wp_next_scheduled( 'tp_indexnow_send' ) ) {
			wp_schedule_single_event( time() + 60, 'tp_indexnow_send' );
		}
	},
	10,
	3
);

add_action( 'tp_indexnow_send', 'tp_indexnow_send' );

/**
 * Send queued (or given) addresses to IndexNow. Returns HTTP status or 0.
 */
function tp_indexnow_send( $urls = array() ) {
	if ( empty( $urls ) ) {
		$urls = (array) get_option( 'tp_indexnow_queue', array() );
		delete_option( 'tp_indexnow_queue' );
	}
	$urls = (array) $urls;
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$urls = array_values( array_filter( array_unique( $urls ), static fn( $u ) => (string) wp_parse_url( $u, PHP_URL_HOST ) === $host ) );
	if ( ! $urls ) {
		return 0;
	}
	$key  = tp_indexnow_key();
	$r    = wp_remote_post(
		'https://api.indexnow.org/indexnow',
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode(
				array(
					'host'        => $host,
					'key'         => $key,
					'keyLocation' => home_url( '/' . $key . '.txt' ),
					'urlList'     => array_slice( $urls, 0, 10000 ),
				)
			),
		)
	);
	$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	$log  = array_slice( (array) get_option( 'tp_indexnow_log', array() ), -19 );
	$log[] = array( 'time' => current_time( 'mysql' ), 'count' => count( $urls ), 'code' => $code, 'first' => $urls[0] );
	update_option( 'tp_indexnow_log', $log, false );
	return $code;
}

/**
 * Every public page the site would put in its sitemap.
 */
function tp_all_public_urls() {
	$urls  = array( home_url( '/' ) );
	$types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'tp_venue' ) );
	foreach ( get_posts( array( 'post_type' => array_values( $types ), 'post_status' => 'publish', 'numberposts' => 2000, 'fields' => 'ids' ) ) as $id ) {
		$urls[] = get_permalink( $id );
	}
	if ( function_exists( 'tp_local_enabled' ) && tp_local_enabled() && tp_setting( 'local_pages_index' ) ) {
		$services = get_posts( array( 'post_type' => 'tp_service', 'numberposts' => 30 ) );
		foreach ( get_posts( array( 'post_type' => 'tp_area', 'numberposts' => 200 ) ) as $a ) {
			foreach ( $services as $s ) {
				$urls[] = tp_local_url( $a, $s );
			}
		}
	}
	return array_values( array_unique( $urls ) );
}
