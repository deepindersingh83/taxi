<?php
/**
 * Bookings → Setup: one-click starter content (pages, FAQs, services, area
 * pages, draft fleet vehicles and menus). Safe to run more than once: anything
 * that already exists is skipped.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', static function () {
	add_submenu_page(
		'edit.php?post_type=tp_booking',
		__( 'Website setup', 'taxi-peninsula' ),
		__( 'Setup', 'taxi-peninsula' ),
		'manage_options',
		'tp-setup',
		'tp_render_setup_page'
	);
}, 30 );

function tp_starter_pages() {
	return array(
		'book'    => array(
			'title'    => __( 'Book a Taxi', 'taxi-peninsula' ),
			'template' => 'page-templates/booking.php',
			'content'  => '',
		),
		'lookup'  => array(
			'title'   => __( 'Manage My Booking', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Check the status of your booking, see your driver once assigned, pay an online deposit, or ask us to change or cancel a trip.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_booking_lookup]\n<!-- /wp:shortcode -->",
		),
		'fleet'   => array(
			'title'   => __( 'Our Fleet', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Every vehicle is fitted for wheelchair passengers, with restraints for your chair and a seatbelt for you. Tell us about your chair when booking and we will send the right vehicle.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_fleet]\n<!-- /wp:shortcode -->",
		),
		'faq'     => array(
			'title'   => __( 'FAQ', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Answers to the questions passengers and carers ask us most. Can’t find what you need? Give us a call.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_faq]\n<!-- /wp:shortcode -->",
		),
		'ndis'    => array(
			'title'   => __( 'NDIS & Aged Care', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'We provide regular, reliable wheelchair accessible transport for NDIS participants, aged-care residents and the people who support them — day programs, therapy, work, appointments and social outings.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . __( 'For plan managers, coordinators and providers', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . __( 'You can book on a participant’s behalf and choose to be invoiced. Add the NDIS number or your purchase order to each booking and we will include it on the invoice.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_ndis]\n<!-- /wp:shortcode -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . __( 'Common questions', 'taxi-peninsula' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:shortcode -->\n[tp_faq topic=\"payments-ndis\"]\n<!-- /wp:shortcode -->",
		),
		'contact' => array(
			'title'    => __( 'Contact Us', 'taxi-peninsula' ),
			'template' => 'page-templates/contact.php',
			'content'  => '',
		),
		'driver'  => array(
			'title'   => __( 'Driver Jobs', 'taxi-peninsula' ),
			'content' => "<!-- wp:shortcode -->\n[tp_driver_jobs]\n<!-- /wp:shortcode -->",
		),
		'about'         => array(
			'title'    => __( 'About Us', 'taxi-peninsula' ),
			'template' => 'page-templates/about.php',
			'pattern'  => 'page-about',
		),
		'services'      => array(
			'title'    => __( 'Our Services', 'taxi-peninsula' ),
			'template' => 'page-templates/services.php',
			'content'  => '',
		),
		'terms'         => array(
			'title'    => __( 'Terms & Cancellation Policy', 'taxi-peninsula' ),
			'template' => 'page-templates/policy.php',
			'pattern'  => 'page-terms',
			'status'   => 'draft',
		),
		'careers'       => array(
			'title'    => __( 'Drive With Us', 'taxi-peninsula' ),
			'template' => 'page-templates/careers.php',
			'pattern'  => 'page-careers',
			'status'   => 'draft',
		),
		'complaints'    => array(
			'title'    => __( 'Complaints & Feedback', 'taxi-peninsula' ),
			'template' => 'page-templates/policy.php',
			'pattern'  => 'page-complaints',
			'status'   => 'draft',
		),
		'accessibility' => array(
			'title'    => __( 'Accessibility Statement', 'taxi-peninsula' ),
			'template' => 'page-templates/policy.php',
			'pattern'  => 'page-accessibility',
			'status'   => 'draft',
		),
	);
}

/**
 * Block markup from one of the theme's patterns.
 */
function tp_pattern_content( $slug ) {
	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'taxi-peninsula/' . $slug );
	if ( $pattern && ! empty( $pattern['content'] ) ) {
		return $pattern['content'];
	}
	$file = TP_DIR . '/patterns/' . $slug . '.php';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	ob_start();
	include $file;
	return trim( ob_get_clean() );
}

/**
 * Draft blog posts with writing outlines: [title, pattern, category].
 */
function tp_starter_posts() {
	return array(
		array( __( 'Wheelchair taxi to Frankston Hospital: what to expect', 'taxi-peninsula' ), 'post-local-guide', __( 'Local guides', 'taxi-peninsula' ) ),
		array( __( 'Travelling to Melbourne Airport with a power wheelchair: step by step', 'taxi-peninsula' ), 'post-travel-tips', __( 'Travel tips', 'taxi-peninsula' ) ),
		array( __( 'Your first wheelchair taxi trip: what happens on the day', 'taxi-peninsula' ), 'post-travel-tips', __( 'Travel tips', 'taxi-peninsula' ) ),
	);
}

/**
 * Starter FAQs: [topic, question, answer]. Review these against your actual policies.
 */
function tp_starter_faqs() {
	$phone = tp_opt( 'phone_display' );
	return array(
		array( __( 'Wheelchairs & accessibility', 'taxi-peninsula' ), __( 'What size and weight of wheelchair can you carry?', 'taxi-peninsula' ), __( 'Our vehicles take most standard manual and power wheelchairs and many mobility scooters. If your chair is especially wide, long, tall or heavy, tell us its measurements and weight (with you seated) when you book, so we can confirm the right vehicle before the day.', 'taxi-peninsula' ) ),
		array( __( 'Wheelchairs & accessibility', 'taxi-peninsula' ), __( 'How is my wheelchair secured?', 'taxi-peninsula' ), __( 'Your chair is secured to the floor with restraints at the front and back, and you wear a lap-sash seatbelt anchored to the vehicle. Power chairs should be switched off with the brakes on. Our drivers are trained to load and secure chairs safely — let them know if your chair has any special tie-down points.', 'taxi-peninsula' ) ),
		array( __( 'Wheelchairs & accessibility', 'taxi-peninsula' ), __( 'Do I have to stay in my wheelchair?', 'taxi-peninsula' ), __( 'No. You can travel seated in your wheelchair, or transfer to a vehicle seat if you are able to and prefer to. Tell us your preference when booking.', 'taxi-peninsula' ) ),
		array( __( 'Wheelchairs & accessibility', 'taxi-peninsula' ), __( 'Will the driver help me to the door?', 'taxi-peninsula' ), __( 'Yes. Our service is door-to-door. Add any extra help you need — for example assistance from your front door, or into a hospital reception — in the booking notes.', 'taxi-peninsula' ) ),
		array( __( 'Booking', 'taxi-peninsula' ), __( 'How far ahead should I book?', 'taxi-peninsula' ), sprintf( /* translators: %s: phone */ __( 'Wheelchair taxis are in high demand, so booking a day or more ahead is best — especially for early-morning, airport and hospital trips. For a same-day or urgent trip, call us on %s.', 'taxi-peninsula' ), $phone ) ),
		array( __( 'Booking', 'taxi-peninsula' ), __( 'Can I book the same trip every week?', 'taxi-peninsula' ), __( 'Yes. When booking online, tick "This is a regular trip", choose the days and an end date. Each trip gets its own reference, so you can change or cancel one without affecting the rest.', 'taxi-peninsula' ) ),
		array( __( 'Booking', 'taxi-peninsula' ), __( 'How do I change or cancel a booking?', 'taxi-peninsula' ), sprintf( /* translators: %s: phone */ __( 'Use the "Manage My Booking" page with your reference and mobile number, or the link in your confirmation email or SMS. For changes within the next two hours, please call %s.', 'taxi-peninsula' ), $phone ) ),
		array( __( 'Booking', 'taxi-peninsula' ), __( 'Can a carer or family member travel with me?', 'taxi-peninsula' ), __( 'Yes, carers and family are welcome. Include everyone in the passenger count when you book so we send a vehicle with enough seats.', 'taxi-peninsula' ) ),
		array( __( 'Booking', 'taxi-peninsula' ), __( 'Can I bring my assistance dog?', 'taxi-peninsula' ), __( 'Yes. Assistance animals are always welcome. If you are travelling with a pet that is not an assistance animal, please mention it in the booking notes so we can check first.', 'taxi-peninsula' ) ),
		array( __( 'Payments & NDIS', 'taxi-peninsula' ), __( 'How can I pay?', 'taxi-peninsula' ), __( 'You can pay the driver at the end of the trip, choose "Account / invoice" if you are booking for an NDIS participant, aged-care resident or business, or pay a deposit online when that option is shown on the booking form.', 'taxi-peninsula' ) ),
		array( __( 'Payments & NDIS', 'taxi-peninsula' ), __( 'Do you accept the Multi Purpose Taxi Program (MPTP)?', 'taxi-peninsula' ), __( 'Tick the MPTP box when booking and bring your MPTP card. The subsidy is applied to eligible fares in line with the program’s rules.', 'taxi-peninsula' ) ),
		array( __( 'Payments & NDIS', 'taxi-peninsula' ), __( 'Can you invoice NDIS plan managers?', 'taxi-peninsula' ), __( 'Choose "Account / invoice" when booking and enter the plan manager or organisation and their email address, plus the participant’s NDIS number or a purchase order if you have one.', 'taxi-peninsula' ) ),
	);
}

function tp_render_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$pages = (array) get_option( 'tp_pages', array() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done  = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : null;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Website setup', 'taxi-peninsula' ); ?></h1>
		<?php if ( null !== $done ) : ?>
			<div class="notice notice-success"><p>
				<?php
				/* translators: %d: number of items */
				echo esc_html( sprintf( _n( '%d item created. Please review the new content before going live.', '%d items created. Please review the new content before going live.', $done, 'taxi-peninsula' ), $done ) );
				?>
			</p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Create the pages and example content this theme needs in one click. Anything that already exists is left alone.', 'taxi-peninsula' ); ?></p>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'Pages: Book a Taxi, Manage My Booking, Our Fleet, FAQ, NDIS & Aged Care, Contact Us, Driver Jobs, About Us and Our Services', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Draft Terms & Cancellation Policy, Complaints & Feedback, Accessibility Statement and Drive With Us pages with [CONFIRM] notes for your real policies', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Three draft blog posts with writing outlines', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Frequently asked questions, grouped by topic', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Service pages (airport, medical, NDIS…) and one page per service area', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Two example fleet vehicles, saved as drafts for you to edit and publish', 'taxi-peninsula' ); ?></li>
			<li><?php esc_html_e( 'Main and footer menus (only if none are assigned yet)', 'taxi-peninsula' ); ?></li>
		</ul>
		<p><strong><?php esc_html_e( 'Testimonials are not created — add real ones from your passengers under Testimonials.', 'taxi-peninsula' ); ?></strong></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_create_starter">
			<?php wp_nonce_field( 'tp_create_starter' ); ?>
			<?php submit_button( __( 'Create starter content', 'taxi-peninsula' ), 'primary large' ); ?>
		</form>

		<h2><?php esc_html_e( 'Feature pages', 'taxi-peninsula' ); ?></h2>
		<table class="widefat striped" style="max-width:48rem">
			<tbody>
				<?php foreach ( tp_starter_pages() as $key => $page ) : ?>
					<?php
					$url   = tp_page_url_by_template( $key );
					$draft = ! $url && ! empty( $pages[ $key ] ) && 'draft' === get_post_status( $pages[ $key ] );
					?>
					<tr>
						<td><?php echo esc_html( $page['title'] ); ?></td>
						<td>
							<?php if ( $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a>
							<?php elseif ( $draft ) : ?>
								<strong><?php esc_html_e( 'Draft', 'taxi-peninsula' ); ?></strong> — <a href="<?php echo esc_url( get_edit_post_link( $pages[ $key ] ) ); ?>"><?php esc_html_e( 'review the [CONFIRM] notes and publish', 'taxi-peninsula' ); ?></a>
							<?php else : ?>
								<em><?php esc_html_e( 'Not created yet', 'taxi-peninsula' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Page templates & patterns', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'When you add a page, choose a template in the Page panel → Template: About Us, Contact Us, Services, Blog, Book a Taxi, Drive with us, Policy / legal page, Full width, or Blank canvas. Click the + inserter → Patterns → "Taxi Peninsula" for ready-made sections (hero, features, steps, call to action, services, areas, FAQ, contact + map, booking form) and full-page starters for About, Terms, Accessibility, Drive With Us and seasonal event pages, and blog posts. Live sections: trust numbers, suburb checker, popular destinations, drivers, safety video and partner logos.', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'Security check', 'taxi-peninsula' ); ?></h2>
		<?php tp_render_security_status(); ?>
		<p class="description"><?php esc_html_e( 'Server-level steps (web server headers, wp-config.php settings, file permissions) are listed in the theme README under "Hosting hardening".', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'Shortcodes', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'You can also place these on any page:', 'taxi-peninsula' ); ?></p>
		<p><code>[tp_booking_form]</code> <code>[tp_booking_lookup]</code> <code>[tp_contact_form]</code> <code>[tp_faq]</code> <code>[tp_faq topic="booking"]</code> <code>[tp_fleet]</code> <code>[tp_ndis]</code> <code>[tp_testimonials]</code> <code>[tp_driver_jobs]</code> <code>[tp_services]</code> <code>[tp_areas]</code> <code>[tp_contact_details]</code> <code>[tp_map]</code> <code>[tp_stats]</code> <code>[tp_suburb_checker]</code> <code>[tp_destinations]</code> <code>[tp_drivers]</code> <code>[tp_video]</code> <code>[tp_partners]</code> <code>[tp_driver_apply]</code></p>
	</div>
	<?php
}

add_action( 'admin_post_tp_create_starter', 'tp_create_starter_content' );
function tp_create_starter_content() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'tp_create_starter' );
	$created = 0;

	// Pages.
	$pages = (array) get_option( 'tp_pages', array() );
	foreach ( tp_starter_pages() as $key => $page ) {
		if ( tp_page_url_by_template( $key ) || ( ! empty( $pages[ $key ] ) && get_post( $pages[ $key ] ) && 'trash' !== get_post_status( $pages[ $key ] ) ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => $page['status'] ?? 'publish',
				'post_title'   => $page['title'],
				'post_content' => isset( $page['pattern'] ) ? tp_pattern_content( $page['pattern'] ) : $page['content'],
				'meta_input'   => empty( $page['template'] ) ? array() : array( '_wp_page_template' => $page['template'] ),
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$pages[ $key ] = $id;
			$created++;
		}
	}
	update_option( 'tp_pages', $pages );

	// Static front page + blog page, if the site still shows latest posts on the home page.
	if ( 'posts' === get_option( 'show_on_front' ) ) {
		$home = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => __( 'Home', 'taxi-peninsula' ) ) );
		$blog = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => __( 'Blog', 'taxi-peninsula' ) ) );
		if ( $home && $blog && ! is_wp_error( $home ) && ! is_wp_error( $blog ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
			update_option( 'page_for_posts', $blog );
			$created += 2;
		}
	}

	// Draft blog posts with writing outlines.
	if ( ! get_option( 'tp_starter_posts_done' ) ) {
		foreach ( tp_starter_posts() as $post ) {
			$cat = term_exists( $post[2], 'category' );
			if ( ! $cat ) {
				$cat = wp_insert_term( $post[2], 'category' );
			}
			$id = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'draft',
					'post_title'    => $post[0],
					'post_content'  => tp_pattern_content( $post[1] ),
					'post_category' => is_wp_error( $cat ) ? array() : array( (int) $cat['term_id'] ),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$created++;
			}
		}
		update_option( 'tp_starter_posts_done', 1 );
	}

	// FAQs.
	if ( ! get_posts( array( 'post_type' => 'tp_faq', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		foreach ( tp_starter_faqs() as $i => $faq ) {
			$term = term_exists( $faq[0], 'tp_faq_topic' );
			if ( ! $term ) {
				$term = wp_insert_term( $faq[0], 'tp_faq_topic', array( 'slug' => sanitize_title( str_replace( '&', '', $faq[0] ) ) ) );
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'tp_faq',
					'post_status'  => 'publish',
					'post_title'   => $faq[1],
					'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $faq[2] ) . "</p>\n<!-- /wp:paragraph -->",
					'menu_order'   => $i,
				)
			);
			if ( $id && ! is_wp_error( $id ) && ! is_wp_error( $term ) ) {
				wp_set_object_terms( $id, (int) $term['term_id'], 'tp_faq_topic' );
				$created++;
			}
		}
	}

	// Services.
	if ( ! get_posts( array( 'post_type' => 'tp_service', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		foreach ( tp_default_services() as $i => $s ) {
			$blocks = '';
			foreach ( preg_split( "/\n\n+/", $s['content'] ) as $para ) {
				$blocks .= "<!-- wp:paragraph -->\n<p>" . esc_html( $para ) . "</p>\n<!-- /wp:paragraph -->\n\n";
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'tp_service',
					'post_status'  => 'publish',
					'post_title'   => $s['title'],
					'post_excerpt' => $s['excerpt'],
					'post_content' => trim( $blocks ),
					'menu_order'   => $i,
					'meta_input'   => array( '_tp_icon' => $s['icon'] ),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$created++;
			}
		}
	}

	// Area pages (one per service area, skipping airports and existing pages).
	$existing = tp_area_links();
	foreach ( tp_service_areas() as $i => $area ) {
		if ( isset( $existing[ strtolower( $area ) ] ) || false !== stripos( $area, 'airport' ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'tp_area',
				'post_status'  => 'publish',
				'post_title'   => $area,
				/* translators: %s: suburb */
				'post_excerpt' => sprintf( __( 'Wheelchair accessible taxis in %s — door-to-door trips to hospitals, appointments, the airport and around town.', 'taxi-peninsula' ), $area ),
				'menu_order'   => $i,
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$created++;
		}
	}

	// Draft fleet examples.
	if ( ! get_posts( array( 'post_type' => 'tp_fleet', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		$fleet = array(
			array( __( 'Wheelchair Accessible Van (example)', 'taxi-peninsula' ), 'ramp', 1, 4, __( 'Our everyday wheelchair taxi. Rear-entry ramp, room for one wheelchair plus passengers.', 'taxi-peninsula' ) ),
			array( __( 'Maxi WAT (example)', 'taxi-peninsula' ), 'hoist', 2, 6, __( 'Larger vehicle with a hydraulic hoist for heavier power chairs, or two wheelchairs travelling together.', 'taxi-peninsula' ) ),
		);
		foreach ( $fleet as $i => $v ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'tp_fleet',
					'post_status'  => 'draft',
					'post_title'   => $v[0],
					'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $v[4] ) . "</p>\n<!-- /wp:paragraph -->",
					'menu_order'   => $i,
					'meta_input'   => array(
						'_tp_access'      => $v[1],
						'_tp_wheelchairs' => $v[2],
						'_tp_seats'       => $v[3],
						'_tp_features'    => __( "Four-point wheelchair restraints\nLap-sash seatbelt\nAir-conditioned", 'taxi-peninsula' ),
					),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$created++;
			}
		}
	}

	$created += tp_create_starter_menus( $pages );

	wp_safe_redirect( admin_url( 'edit.php?post_type=tp_booking&page=tp-setup&created=' . $created ) );
	exit;
}

/**
 * Create and assign menus if the locations are empty. Returns number created.
 */
function tp_create_starter_menus( array $pages ) {
	$locations = get_nav_menu_locations();
	$created   = 0;

	$page_item = static function ( $menu_id, $key, $order ) use ( $pages ) {
		if ( empty( $pages[ $key ] ) ) {
			return;
		}
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-object-id' => (int) $pages[ $key ],
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $order,
			)
		);
	};
	$link_item = static function ( $menu_id, $title, $url, $order ) {
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'    => $title,
				'menu-item-url'      => $url,
				'menu-item-type'     => 'custom',
				'menu-item-status'   => 'publish',
				'menu-item-position' => $order,
			)
		);
	};

	if ( empty( $locations['primary'] ) && ! wp_get_nav_menu_object( 'Main menu' ) ) {
		$menu_id = wp_create_nav_menu( 'Main menu' );
		if ( ! is_wp_error( $menu_id ) ) {
			if ( ! empty( $pages['services'] ) ) {
				$page_item( $menu_id, 'services', 1 );
			} else {
				$link_item( $menu_id, __( 'Services', 'taxi-peninsula' ), get_post_type_archive_link( 'tp_service' ), 1 );
			}
			$page_item( $menu_id, 'about', 2 );
			$page_item( $menu_id, 'fleet', 3 );
			$page_item( $menu_id, 'ndis', 4 );
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page ) {
				$link_item( $menu_id, __( 'Blog', 'taxi-peninsula' ), get_permalink( $posts_page ), 5 );
			}
			$page_item( $menu_id, 'contact', 6 );
			$locations['primary'] = $menu_id;
			$created++;
		}
	}

	if ( empty( $locations['footer'] ) && ! wp_get_nav_menu_object( 'Footer menu' ) ) {
		$menu_id = wp_create_nav_menu( 'Footer menu' );
		if ( ! is_wp_error( $menu_id ) ) {
			$page_item( $menu_id, 'book', 1 );
			$page_item( $menu_id, 'lookup', 2 );
			$link_item( $menu_id, __( 'Areas we cover', 'taxi-peninsula' ), get_post_type_archive_link( 'tp_area' ), 3 );
			$page_item( $menu_id, 'faq', 4 );
			$page_item( $menu_id, 'contact', 5 );
			$page_item( $menu_id, 'terms', 7 );
			$page_item( $menu_id, 'complaints', 8 );
			$page_item( $menu_id, 'accessibility', 8 );
			$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
			if ( $privacy && 'publish' === get_post_status( $privacy ) ) {
				$link_item( $menu_id, __( 'Privacy policy', 'taxi-peninsula' ), get_permalink( $privacy ), 6 );
			}
			$locations['footer'] = $menu_id;
			$created++;
		}
	}

	set_theme_mod( 'nav_menu_locations', $locations );
	return $created;
}
