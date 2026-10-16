<?php
/**
 * Trust and answer-first content for search engines and AI assistants.
 *
 * - Quick answer: 2–3 plain sentences at the top of service, area, local
 *   and council pages (who you are, where you go, how to book, the price).
 *   Each page can have its own (the "Quick answer" box when editing).
 * - "Last updated" dates on pages and posts, plus article / web page data
 *   (author, published and updated dates, publisher).
 * - Author box on blog posts and guides: name, role, years of experience,
 *   short bio (Users → Profile → "About you as an author").
 * - [tp_credentials]: accreditations and checks (ABN, CPVV, NDIS, extra lines).
 * - Starter page "Driver Training & Safety" (draft with [CONFIRM] notes).
 * - Video data for the wheelchair safety video, so it can appear in Google
 *   video results.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['credentials_extra'] = array( 'search', __( 'Other accreditations and checks', 'taxi-peninsula' ), 'textarea', '', __( 'One per line, shown with [tp_credentials] and on the Driver Training & Safety page, e.g. "All drivers hold a current Working with Children Check". Only list things that are true.', 'taxi-peninsula' ) );
		$s['founded_year']      = array( 'search', __( 'Year the business started', 'taxi-peninsula' ), 'number', '', __( 'Used in the business data and the quick answer ("since 2012").', 'taxi-peninsula' ) );
		$s['show_updated']      = array( 'search', __( 'Show "Last updated" dates on pages and posts', 'taxi-peninsula' ), 'checkbox', true, '' );
		$s['quick_answers']     = array( 'search', __( 'Show a quick answer at the top of service, area and local pages', 'taxi-peninsula' ), 'checkbox', true, '' );
		return $s;
	}
);

/* -------------------------------------------------------------------------
 * Quick answer.
 * ---------------------------------------------------------------------- */

/**
 * Lowest "from" price across services, or 0.
 */
function tp_lowest_from_price() {
	$min = 0.0;
	foreach ( tp_services_list() as $s ) {
		$p = (float) $s['price'];
		if ( $p > 0 && ( ! $min || $p < $min ) ) {
			$min = $p;
		}
	}
	return $min;
}

/**
 * The quick-answer sentences for a place and/or service.
 *
 * @param string $place   Suburb or council name ('' for the whole service area).
 * @param int    $service Service post ID (0 for none).
 * @param int    $post_id Post to read a custom answer from.
 */
function tp_quick_answer_text( $place = '', $service = 0, $post_id = 0 ) {
	$custom = $post_id ? trim( (string) get_post_meta( $post_id, '_tp_quick_answer', true ) ) : '';
	if ( '' !== $custom ) {
		return $custom;
	}
	$site  = get_bloginfo( 'name' );
	$since = (int) tp_setting( 'founded_year' );
	$what  = $service ? strtolower( get_the_title( $service ) ) : __( 'wheelchair accessible taxis', 'taxi-peninsula' );
	$where = $place ?: wp_strip_all_tags( (string) tp_opt( 'location' ) );
	$price = $service ? (float) get_post_meta( $service, '_tp_from_price', true ) : 0;
	$price = $price ?: tp_lowest_from_price();
	/* translators: 1: business 2: service 3: place 4: " since 2012" or "" */
	$a = sprintf( __( '%1$s provides %2$s in %3$s%4$s, using ramp and hoist vans with wheelchair restraints and drivers who help you from door to door.', 'taxi-peninsula' ), $site, $what, $where, $since ? ' ' . sprintf( /* translators: %d: year */ __( 'since %d', 'taxi-peninsula' ), $since ) : '' );
	$b = __( 'We travel anywhere across Melbourne and the Mornington Peninsula, including hospitals, day programs and Melbourne and Avalon airports, and carers travel with you.', 'taxi-peninsula' );
	/* translators: 1: phone 2: price sentence */
	$c = sprintf( __( 'Book online in about two minutes or call %1$s. %2$s', 'taxi-peninsula' ), wp_strip_all_tags( tp_opt( 'phone_display' ) ), $price
		/* translators: %s: price */
		? sprintf( __( 'Fares start from %s and depend on distance and waiting time; MPTP, NDIS and aged-care accounts are accepted.', 'taxi-peninsula' ), '$' . number_format_i18n( $price, floor( $price ) == $price ? 0 : 2 ) )
		: __( 'Fares depend on distance and waiting time; MPTP, NDIS and aged-care accounts are accepted.', 'taxi-peninsula' ) );
	return $a . ' ' . $b . ' ' . $c;
}

/**
 * Print the quick-answer box.
 */
function tp_the_quick_answer( $place = '', $service = 0, $post_id = 0 ) {
	if ( ! tp_setting( 'quick_answers' ) ) {
		return;
	}
	printf(
		'<div class="quick-answer" role="note"><p class="quick-answer__label">%1$s</p><p class="quick-answer__text">%2$s</p></div>',
		esc_html__( 'In short', 'taxi-peninsula' ),
		esc_html( tp_quick_answer_text( $place, $service, $post_id ) )
	);
}

// "Quick answer" box on service, area and page edit screens.
add_action(
	'add_meta_boxes',
	static function ( $type ) {
		if ( in_array( $type, array( 'tp_service', 'tp_area', 'page' ), true ) ) {
			add_meta_box( 'tp_quick_answer', __( 'Quick answer (for Google and AI assistants)', 'taxi-peninsula' ), 'tp_render_quick_answer_box', $type, 'normal', 'high' );
		}
	}
);

function tp_render_quick_answer_box( $post ) {
	wp_nonce_field( 'tp_quick_answer', 'tp_quick_answer_nonce' );
	$auto = 'tp_service' === $post->post_type ? tp_quick_answer_text( '', $post->ID ) : ( 'tp_area' === $post->post_type ? tp_quick_answer_text( $post->post_title ) : tp_quick_answer_text() );
	?>
	<p class="description"><?php esc_html_e( '2–3 plain sentences shown at the top of the page: who you are, where you go, how to book and the price. AI assistants and Google often quote this. Leave empty to use the automatic text:', 'taxi-peninsula' ); ?></p>
	<textarea class="large-text" rows="3" name="tp_quick_answer" placeholder="<?php echo esc_attr( $auto ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_tp_quick_answer', true ) ); ?></textarea>
	<?php
}

add_action(
	'save_post',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_quick_answer_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_quick_answer_nonce'] ), 'tp_quick_answer' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_tp_quick_answer', isset( $_POST['tp_quick_answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_quick_answer'] ) ) : '' );
	}
);

// Pages with a custom quick answer show it above the content.
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() || is_front_page() ) {
			return $content;
		}
		$qa = trim( (string) get_post_meta( get_the_ID(), '_tp_quick_answer', true ) );
		if ( '' === $qa || ! tp_setting( 'quick_answers' ) ) {
			return $content;
		}
		ob_start();
		tp_the_quick_answer( '', 0, get_the_ID() );
		return ob_get_clean() . $content;
	},
	5
);

/* -------------------------------------------------------------------------
 * Last updated + article / web page data.
 * ---------------------------------------------------------------------- */

/**
 * "Last updated 3 October 2026" for a post.
 */
function tp_updated_line( $post_id ) {
	if ( ! tp_setting( 'show_updated' ) ) {
		return '';
	}
	$mod = get_post_modified_time( 'U', true, $post_id );
	return sprintf(
		'<p class="updated-date">%1$s <time datetime="%2$s">%3$s</time></p>',
		esc_html__( 'Last updated', 'taxi-peninsula' ),
		esc_attr( gmdate( 'c', $mod ) ),
		esc_html( wp_date( 'j F Y', $mod ) )
	);
}

add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! in_the_loop() || ! is_main_query() || is_front_page() || ! is_singular( array( 'page', 'post', 'tp_event' ) ) ) {
			return $content;
		}
		$pages = (array) get_option( 'tp_pages', array() );
		foreach ( array( 'book', 'lookup', 'account', 'driver' ) as $k ) {
			if ( ! empty( $pages[ $k ] ) && is_page( $pages[ $k ] ) ) {
				return $content;
			}
		}
		$extra = tp_updated_line( get_the_ID() );
		if ( is_singular( 'post' ) ) {
			$extra .= tp_author_box( (int) get_post_field( 'post_author', get_the_ID() ) );
		}
		return $content . $extra;
	},
	20
);

/**
 * Person node for an author (name, role, bio, years of experience).
 */
function tp_author_person( $user_id ) {
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return null;
	}
	$p = array(
		'@type'    => 'Person',
		'@id'      => home_url( '/#author-' . $user_id ),
		'name'     => $u->display_name,
		'worksFor' => array( '@id' => home_url( '/#business' ) ),
	);
	$role = trim( (string) get_user_meta( $user_id, 'tp_author_role', true ) );
	if ( $role ) {
		$p['jobTitle'] = $role;
	}
	if ( $u->description ) {
		$p['description'] = wp_strip_all_tags( $u->description );
	}
	$about = tp_page_url_by_template( 'about' );
	if ( $about ) {
		$p['url'] = $about;
	}
	return $p;
}

add_filter(
	'tp_schema_graph',
	static function ( $graph ) {
		if ( ! is_singular() || is_front_page() ) {
			return $graph;
		}
		$post = get_queried_object();
		$node = array(
			'@type'         => 'post' === $post->post_type ? 'BlogPosting' : 'WebPage',
			'@id'           => get_permalink( $post ) . '#page',
			'url'           => get_permalink( $post ),
			'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
			'datePublished' => get_post_time( 'c', true, $post ),
			'dateModified'  => get_post_modified_time( 'c', true, $post ),
			'publisher'     => array( '@id' => home_url( '/#business' ) ),
			'inLanguage'    => 'en-AU',
		);
		if ( has_post_thumbnail( $post ) ) {
			$node['image'] = get_the_post_thumbnail_url( $post, 'large' );
		}
		if ( 'post' === $post->post_type ) {
			$author = tp_author_person( (int) $post->post_author );
			if ( $author ) {
				$graph[]        = $author;
				$node['author'] = array( '@id' => $author['@id'] );
			}
			$node['mainEntityOfPage'] = get_permalink( $post );
		}
		$graph[] = $node;
		return $graph;
	},
	20
);

/* -------------------------------------------------------------------------
 * Author box (expertise).
 * ---------------------------------------------------------------------- */

add_action( 'show_user_profile', 'tp_author_fields' );
add_action( 'edit_user_profile', 'tp_author_fields' );
function tp_author_fields( WP_User $user ) {
	if ( ! user_can( $user, 'edit_posts' ) ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'About you as an author', 'taxi-peninsula' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Shown under your blog posts and guides. Real experience builds trust with readers and with Google. "Biographical Info" above is used as your short bio.', 'taxi-peninsula' ); ?></p>
	<table class="form-table" role="presentation">
		<tr><th><label for="tp_author_role"><?php esc_html_e( 'Role', 'taxi-peninsula' ); ?></label></th><td><input type="text" class="regular-text" id="tp_author_role" name="tp_author_role" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tp_author_role', true ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Owner and wheelchair taxi driver', 'taxi-peninsula' ); ?>"></td></tr>
		<tr><th><label for="tp_author_years"><?php esc_html_e( 'Years of experience', 'taxi-peninsula' ); ?></label></th><td><input type="number" min="0" max="70" class="small-text" id="tp_author_years" name="tp_author_years" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tp_author_years', true ) ); ?>"> <?php esc_html_e( 'years driving or running wheelchair accessible transport', 'taxi-peninsula' ); ?></td></tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'tp_save_author_fields' );
add_action( 'edit_user_profile_update', 'tp_save_author_fields' );
function tp_save_author_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['tp_author_role'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verifies the profile nonce.
		return;
	}
	update_user_meta( $user_id, 'tp_author_role', sanitize_text_field( wp_unslash( $_POST['tp_author_role'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_user_meta( $user_id, 'tp_author_years', isset( $_POST['tp_author_years'] ) ? min( 70, absint( $_POST['tp_author_years'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}

/**
 * "SO" for "Sam Owner" — shown instead of a Gravatar photo (no outside service).
 */
function tp_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$out   = '';
	foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $p ) {
		$out .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
	}
	return $out ?: '?';
}

/**
 * "Written by" box.
 */
function tp_author_box( $user_id ) {
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return '';
	}
	$role  = trim( (string) get_user_meta( $user_id, 'tp_author_role', true ) );
	$years = (int) get_user_meta( $user_id, 'tp_author_years', true );
	$bio   = trim( (string) $u->description );
	if ( ! $role && ! $bio ) {
		return '';
	}
	$line = $role . ( $years ? ( $role ? ', ' : '' ) . sprintf( /* translators: %d: years */ _n( '%d year in wheelchair accessible transport', '%d years in wheelchair accessible transport', $years, 'taxi-peninsula' ), $years ) : '' );
	return sprintf(
		'<aside class="author-box" aria-label="%1$s">%2$s<div><p class="author-box__name">%3$s <strong>%4$s</strong></p>%5$s%6$s</div></aside>',
		esc_attr__( 'About the author', 'taxi-peninsula' ),
		'<span class="author-box__img" aria-hidden="true">' . esc_html( tp_initials( $u->display_name ) ) . '</span>',
		esc_html__( 'Written by', 'taxi-peninsula' ),
		esc_html( $u->display_name ),
		$line ? '<p class="author-box__role">' . esc_html( $line ) . '</p>' : '',
		$bio ? '<p class="author-box__bio">' . esc_html( $bio ) . '</p>' : ''
	);
}

/* -------------------------------------------------------------------------
 * Credentials and the Driver Training & Safety page.
 * ---------------------------------------------------------------------- */

function tp_credentials_list() {
	$out = array();
	if ( tp_setting( 'cpv_number' ) ) {
		/* translators: %s: number */
		$out[] = sprintf( __( 'Registered with Commercial Passenger Vehicles Victoria — %s', 'taxi-peninsula' ), tp_setting( 'cpv_number' ) );
	}
	if ( tp_setting( 'ndis_provider_no' ) ) {
		/* translators: %s: number */
		$out[] = sprintf( __( 'NDIS registered provider — %s', 'taxi-peninsula' ), tp_setting( 'ndis_provider_no' ) );
	}
	if ( tp_setting( 'abn' ) ) {
		$out[] = 'ABN ' . tp_setting( 'abn' );
	}
	foreach ( preg_split( '/\R/', (string) tp_setting( 'credentials_extra' ) ) as $line ) {
		if ( '' !== trim( $line ) ) {
			$out[] = trim( $line );
		}
	}
	return $out;
}

add_shortcode(
	'tp_credentials',
	static function () {
		$list = tp_credentials_list();
		if ( ! $list ) {
			return current_user_can( 'manage_options' ) ? '<p class="notice notice--info">' . esc_html__( 'Add your accreditation numbers under Taxi settings → Settings → Search engines & AI.', 'taxi-peninsula' ) . '</p>' : '';
		}
		$html = '<ul class="tick-list credentials">';
		foreach ( $list as $c ) {
			$html .= '<li>' . tp_icon( 'shield' ) . esc_html( $c ) . '</li>';
		}
		return $html . '</ul>';
	}
);

add_filter(
	'tp_starter_pages',
	static function ( $p ) {
		$c  = "<!-- wp:paragraph -->\n<p>" . __( 'Your safety, and the safety of your wheelchair, comes first. Here is how we train our drivers and look after our vehicles.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		$c .= "<!-- wp:heading -->\n<h2>" . __( 'How are your drivers trained?', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . __( '[CONFIRM] Describe your training: wheelchair loading and restraint, manual handling, disability awareness, dementia and autism awareness, first aid, and how often it is refreshed.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		$c .= "<!-- wp:heading -->\n<h2>" . __( 'How is my wheelchair secured?', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . __( '[CONFIRM] Describe your restraint system (four-point tie-downs and a lap-sash seatbelt for the passenger), and the standard your equipment meets.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		$c .= "<!-- wp:heading -->\n<h2>" . __( 'How are the vehicles checked?', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . __( '[CONFIRM] Daily ramp/hoist and restraint checks, servicing schedule, cleaning.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		$c .= "<!-- wp:heading -->\n<h2>" . __( 'What checks do your drivers have?', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . __( '[CONFIRM] e.g. driver accreditation, police checks, Working with Children Check, NDIS Worker Screening.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		$c .= "<!-- wp:heading -->\n<h2>" . __( 'Accreditations', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:shortcode -->\n[tp_credentials]\n<!-- /wp:shortcode -->";
		$p['safety'] = array(
			'title'   => __( 'Driver Training & Safety', 'taxi-peninsula' ),
			'status'  => 'draft',
			'content' => $c,
		);
		return $p;
	}
);

/* -------------------------------------------------------------------------
 * Video data for the safety video.
 * ---------------------------------------------------------------------- */

/**
 * VideoObject for the safety video, or null.
 */
function tp_video_schema() {
	if ( ! function_exists( 'tp_video_configured' ) || ! tp_video_configured() ) {
		return null;
	}
	$remote = tp_parse_video_url( tp_opt( 'video_url' ) );
	$file   = (int) tp_opt( 'video_file' );
	$poster = (int) tp_opt( 'video_poster' );
	$thumb  = $poster ? wp_get_attachment_image_url( $poster, 'large' ) : '';
	$node   = array(
		'@type'       => 'VideoObject',
		'name'        => tp_opt( 'video_title' ) ?: __( 'How we secure your wheelchair', 'taxi-peninsula' ),
		'description' => wp_trim_words( (string) ( tp_opt( 'video_transcript' ) ?: __( 'How our drivers load, secure and unload a wheelchair in a wheelchair accessible taxi.', 'taxi-peninsula' ) ), 40 ),
		'publisher'   => array( '@id' => home_url( '/#business' ) ),
	);
	if ( $file ) {
		$node['contentUrl'] = wp_get_attachment_url( $file );
		$node['uploadDate'] = get_post_time( 'c', true, $file );
	} elseif ( ! empty( $remote[1] ) ) {
		$node['embedUrl'] = 'youtube' === $remote[0] ? 'https://www.youtube.com/embed/' . $remote[1] : 'https://player.vimeo.com/video/' . $remote[1];
		$thumb            = $thumb ?: ( 'youtube' === $remote[0] ? 'https://i.ytimg.com/vi/' . $remote[1] . '/hqdefault.jpg' : '' );
		$first            = get_option( 'tp_video_first_seen' );
		if ( ! is_array( $first ) || ( $first['url'] ?? '' ) !== tp_opt( 'video_url' ) ) {
			$first = array( 'url' => tp_opt( 'video_url' ), 'date' => gmdate( 'c' ) );
			update_option( 'tp_video_first_seen', $first, false );
		}
		$node['uploadDate'] = $first['date'];
	}
	if ( $thumb ) {
		$node['thumbnailUrl'] = $thumb;
	}
	$transcript = trim( (string) tp_opt( 'video_transcript' ) );
	if ( $transcript ) {
		$node['transcript'] = $transcript;
	}
	return $node;
}

// Add the video to the page data wherever the video section is shown.
add_action(
	'tp_video_shown',
	static function () {
		$GLOBALS['tp_video_on_page'] = true;
	}
);
add_filter(
	'tp_schema_graph',
	static function ( $graph ) {
		if ( ! empty( $GLOBALS['tp_video_on_page'] ) ) {
			$v = tp_video_schema();
			if ( $v ) {
				$graph[] = $v;
			}
		}
		return $graph;
	},
	30
);

/**
 * Accessibility questions everyone asks: [ [question, answer], … ].
 */
function tp_access_faq() {
	return array(
		array( __( 'Can I travel in my power wheelchair or scooter?', 'taxi-peninsula' ), __( 'Yes. Most manual and power wheelchairs fit, and you stay seated in your chair. Tell us the make, width and weight when you book so we send the right vehicle. Mobility scooters can travel when they can be safely restrained — please check with us first.', 'taxi-peninsula' ) ),
		array( __( 'Can a carer or family member come too?', 'taxi-peninsula' ), __( 'Yes. Carers, family and assistance animals are welcome. Include everyone in the passenger count so we send a vehicle with enough seats.', 'taxi-peninsula' ) ),
		array( __( 'How do I pay?', 'taxi-peninsula' ), __( 'Pay the driver by card or cash, use your Multi Purpose Taxi Program (MPTP) card, or choose "Account / invoice" for NDIS plan managers, aged-care providers and businesses.', 'taxi-peninsula' ) ),
	);
}

// Service and area pages print the date after their own sections.
add_action(
	'tp_area_after_content',
	static function ( $area ) {
		echo tp_updated_line( $area->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
	},
	20
);
add_action(
	'tp_service_details_after',
	static function ( $id ) {
		if ( is_singular( 'tp_service' ) ) {
			echo tp_updated_line( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		}
	},
	20
);
