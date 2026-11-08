<?php
/**
 * Ready-to-publish legal pages, added with the content pack:
 *
 *   /terms-and-conditions/   Terms & Conditions (customers tick "I accept" when booking)
 *   /privacy-policy/         Privacy Policy (written from the features you use)
 *   /cancellation-policy/    Cancellation Policy
 *   /accessibility/          Accessibility Statement
 *   /complaints/             Complaints & Feedback
 *
 * Fees, free-cancellation hours, waiting time and ways to pay are not typed
 * into the text: they come from Bookings → Settings, so the pages always match
 * what the booking form and emails say. Drafts made by "Create starter
 * content" that still have [CONFIRM] notes are replaced and published; pages
 * you have published yourself are never touched.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * The legal pages: key => [title, slug, content callback].
 */
function tp_legal_pages() {
	return array(
		'terms'         => array( __( 'Terms & Conditions', 'taxi-peninsula' ), 'terms-and-conditions', 'tp_legal_terms_content' ),
		'privacy'       => array( __( 'Privacy Policy', 'taxi-peninsula' ), 'privacy-policy', 'tp_legal_privacy_content' ),
		'cancellation'  => array( __( 'Cancellation Policy', 'taxi-peninsula' ), 'cancellation-policy', 'tp_legal_cancellation_content' ),
		'accessibility' => array( __( 'Accessibility Statement', 'taxi-peninsula' ), 'accessibility', 'tp_legal_accessibility_content' ),
		'complaints'    => array( __( 'Complaints & Feedback', 'taxi-peninsula' ), 'complaints', 'tp_legal_complaints_content' ),
	);
}

/** A shortcode block. */
function tp_legal_code( $code ) {
	return "<!-- wp:shortcode -->\n[" . $code . "]\n<!-- /wp:shortcode -->";
}

/** "<a href>" to another legal page, or plain text. */
function tp_legal_link( $key, $text ) {
	$id  = (int) ( ( (array) get_option( 'tp_pages', array() ) )[ $key ] ?? 0 );
	$url = $id && get_post( $id ) ? get_permalink( $id ) : ( isset( tp_legal_pages()[ $key ] ) ? '' : tp_page_url_by_template( $key ) );
	return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text );
}

/** A paragraph block with links allowed. */
function tp_legal_p( $html ) {
	return "<!-- wp:paragraph -->\n<p>" . wp_kses( $html, array( 'a' => array( 'href' => array() ), 'strong' => array() ) ) . "</p>\n<!-- /wp:paragraph -->";
}

function tp_legal_contact_line() {
	$phone = wp_strip_all_tags( tp_opt( 'phone_display' ) );
	$email = (string) tp_opt( 'email' );
	return tp_legal_p( sprintf( 'Phone <a href="%1$s">%2$s</a> or email <a href="mailto:%3$s">%3$s</a>.', esc_url( tp_phone_href() ), esc_html( $phone ), esc_html( $email ) ) );
}

function tp_legal_terms_content() {
	$name = get_bloginfo( 'name' );
	$out  = array(
		tp_legal_p( sprintf( 'These terms apply to every trip booked with %s, online, by phone, by text or through an account. By booking you agree to them. Nothing in these terms limits your rights under the Australian Consumer Law.', esc_html( $name ) ) ),
		tp_pack_blocks(
			array(
				array( 'h2' => 'Booking a trip' ),
				array( 'p' => 'A booking is confirmed when we send you a confirmation by SMS or email. Please give us accurate details: pick-up and drop-off addresses, the time, the number of passengers, and whether anyone travels in a wheelchair or scooter, including its size and weight. We use these details to send the right vehicle.' ),
				array( 'p' => 'If we cannot do a trip as booked, for example because no suitable vehicle is available, we tell you as soon as possible and do not charge you for it.' ),
				array( 'h2' => 'Fares and payment' ),
				array( 'p' => 'The fare shown on the booking form is an estimate. The final fare depends on the actual route, traffic, waiting time and any tolls. If we give you a fixed quote in writing, we charge that amount for the trip as quoted; changes to the trip, such as extra stops or extra waiting, may change the price.' ),
				array( 'p' => 'Fares are charged in line with the rules for commercial passenger vehicles in Victoria. We accept Multi Purpose Taxi Program (MPTP) cards, and NDIS, aged-care and business accounts can be invoiced.' ),
			)
		),
		tp_legal_code( 'tp_pay_options' ),
		tp_pack_blocks( array( array( 'h2' => 'Cancellations, waiting and no-shows' ) ) ),
		tp_legal_code( 'tp_cancellation_policy' ),
		tp_legal_p( 'You can cancel or change a booking from the link in your confirmation, on the ' . tp_legal_link( 'lookup', 'Manage my booking' ) . ' page, or by calling us.' ),
		tp_pack_blocks(
			array(
				array( 'h2' => 'Wheelchairs, scooters and mobility aids' ),
				array( 'p' => 'Our drivers secure wheelchairs with four-point restraints, and passengers wear a seatbelt. For everyone’s safety the driver may decline to carry a mobility aid that is too large or heavy for the vehicle or cannot be secured safely. Telling us about your chair when you book avoids this.' ),
				array( 'p' => 'Assistance animals travel with their handler at no extra charge.' ),
				array( 'h2' => 'Travelling safely' ),
				array(
					'ul' => array(
						'Everyone wears a seatbelt or is secured in their wheelchair, as the law requires.',
						'No smoking or vaping in the vehicle.',
						'Tell us when booking if you need a child restraint. Parents and carers are responsible for supervising children.',
						'The driver may refuse or end a trip if a passenger’s behaviour puts anyone at risk.',
						'Where the law allows, reasonable cleaning or repair costs may be charged if a vehicle is soiled or damaged on purpose or through carelessness.',
					),
				),
				array( 'h2' => 'Delays' ),
				array( 'p' => 'We plan pick-ups to arrive on time and add time for loading wheelchairs, but traffic, weather, road closures and emergencies can cause delays. If we are running late we will let you know. For flights and important appointments, please book with time to spare.' ),
				array( 'h2' => 'Lost property' ),
			)
		),
		tp_legal_p( 'Report anything left in a vehicle on our ' . tp_legal_link( 'lost', 'Lost property' ) . ' page or by phone. We keep items found in our vehicles for you to collect.' ),
		tp_pack_blocks( array( array( 'h2' => 'Privacy and complaints' ) ) ),
		tp_legal_p( 'How we handle your personal information is explained in our ' . tp_legal_link( 'privacy', 'Privacy Policy' ) . '. If something goes wrong, please tell us — see ' . tp_legal_link( 'complaints', 'Complaints & Feedback' ) . '.' ),
		tp_pack_blocks(
			array(
				array( 'h2' => 'Changes to these terms' ),
				array( 'p' => 'We may update these terms from time to time. The version on this page when you book applies to that booking.' ),
				array( 'h2' => 'Contact us' ),
			)
		),
		tp_legal_contact_line(),
	);
	return implode( "\n\n", $out );
}

function tp_legal_privacy_content() {
	return tp_legal_code( 'tp_privacy_policy' ) . "\n\n" . tp_legal_code( 'tp_privacy_request' );
}

function tp_legal_cancellation_content() {
	return implode(
		"\n\n",
		array(
			tp_legal_p( 'Plans change — especially around hospital appointments and discharges. Here is how cancelling, changing and waiting work.' ),
			tp_legal_code( 'tp_cancellation_policy' ),
			tp_pack_blocks(
				array(
					array( 'h2' => 'How to cancel or change a booking' ),
					array(
						'ul' => array(
							'Use the link in your booking confirmation (SMS or email).',
							'Or call us — any time, day or night.',
							'Regular bookings (for example dialysis or day programs) can be paused for a date without cancelling the whole series.',
						),
					),
					array( 'h2' => 'Appointments that run late' ),
					array( 'p' => 'Appointments and hospital discharges often run over. Book a “pick up when ready” return trip and tap “I’m ready” on your booking page or call us when you are done — the waiting rules above apply only once the driver has arrived at the time you asked for.' ),
					array( 'h2' => 'If we have to cancel' ),
					array( 'p' => 'If we cannot do your trip, we tell you as soon as possible and do not charge you. Any deposit you paid for that trip is refunded in full.' ),
				)
			),
			tp_legal_p( 'The full rules are in our ' . tp_legal_link( 'terms', 'Terms & Conditions' ) . '.' ),
		)
	);
}

function tp_legal_accessibility_content() {
	$name = get_bloginfo( 'name' );
	return implode(
		"\n\n",
		array(
			tp_legal_p( sprintf( 'Many of our passengers use wheelchairs, have low vision, are hard of hearing or prefer a language other than English. %s wants booking a ride to be as easy as the ride itself.', esc_html( $name ) ) ),
			tp_pack_blocks(
				array(
					array( 'h2' => 'Our website' ),
					array( 'p' => 'We aim to meet the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA. The site is built to:' ),
					array(
						'ul' => array(
							'work with a keyboard alone, with a visible focus outline and a “Skip to content” link;',
							'work with screen readers, with labelled form fields and clear error messages;',
							'keep text readable when zoomed to 200% and on small phone screens;',
							'use colours with strong contrast and never rely on colour alone;',
							'switch off animations when your device asks for reduced motion;',
							'show key pages in Vietnamese, Chinese, Greek, Italian and Arabic.',
						),
					),
					array( 'h2' => 'Other ways to book' ),
					array( 'p' => 'You never have to use the website. You can book by phone, by text message, or ask us to call you back. Carers, family members, support coordinators and care homes can book on a passenger’s behalf.' ),
					array( 'h2' => 'Our vehicles' ),
					array( 'p' => 'Our wheelchair accessible vehicles have rear ramps or hydraulic hoists, four-point wheelchair restraints and lap-sash seatbelts. Drivers help you from the door to the vehicle and at the other end. Assistance animals are always welcome.' ),
					array( 'h2' => 'Something not working for you?' ),
					array( 'p' => 'If any part of the website or our service is hard to use, please tell us what happened and what you were trying to do. We will help you finish your booking straight away and fix the problem.' ),
				)
			),
			tp_legal_contact_line(),
		)
	);
}

function tp_legal_complaints_content() {
	return implode(
		"\n\n",
		array(
			tp_legal_p( 'We want to hear when something goes wrong — and when it goes right. Feedback helps us look after passengers better, and making a complaint will never affect the service you receive.' ),
			tp_pack_blocks(
				array(
					array( 'h2' => 'How to tell us' ),
					array(
						'ul' => array(
							'Use the form below or the “How was your trip?” link we send after a trip.',
							'Phone or email us (details at the bottom of this page).',
							'Ask a carer, family member, advocate or support coordinator to contact us for you.',
						),
					),
					array( 'p' => 'Please include the date and time of the trip, the pick-up address and what happened. If you have a booking reference, add it.' ),
					array( 'h2' => 'What happens next' ),
					array(
						'ul' => array(
							'We confirm that we have received your complaint.',
							'We look into it, which may include talking with the driver and checking booking records.',
							'We tell you what we found and what we will do to put it right.',
						),
					),
					array( 'h2' => 'If you are not happy with our answer' ),
					array( 'p' => 'You can contact:' ),
					array(
						'ul' => array(
							'Commercial Passenger Vehicles Victoria (CPVV), the regulator for taxis and booked vehicles in Victoria — cpv.vic.gov.au;',
							'the NDIS Quality and Safeguards Commission, for complaints about NDIS-funded supports — ndiscommission.gov.au;',
							'Consumer Affairs Victoria — consumer.vic.gov.au.',
						),
					),
				)
			),
			tp_legal_code( 'tp_contact_form' ),
			tp_legal_contact_line(),
		)
	);
}

/** Is this page still an unfinished starter draft? */
function tp_legal_is_unfinished( $post ) {
	return $post && 'publish' !== $post->post_status && ( '' === trim( $post->post_content ) || false !== strpos( $post->post_content, '[CONFIRM' ) || false !== strpos( $post->post_content, 'tp-todo' ) || false !== strpos( $post->post_content, 'privacy-policy-tutorial' ) );
}

/** The existing post for a legal page, if any. */
function tp_legal_find( $key, $slug ) {
	$pages = (array) get_option( 'tp_pages', array() );
	$post  = ! empty( $pages[ $key ] ) ? get_post( (int) $pages[ $key ] ) : null;
	if ( $post && 'trash' === $post->post_status ) {
		$post = null;
	}
	if ( ! $post && 'privacy' === $key && get_option( 'wp_page_for_privacy_policy' ) ) {
		$post = get_post( (int) get_option( 'wp_page_for_privacy_policy' ) );
		$post = $post && 'trash' !== $post->post_status ? $post : null;
	}
	return $post ?: get_page_by_path( $slug );
}

/** Legal pages still to add: count. */
function tp_legal_pending() {
	$n = 0;
	foreach ( tp_legal_pages() as $key => $p ) {
		$post = tp_legal_find( $key, $p[1] );
		$n   += ! $post || tp_legal_is_unfinished( $post ) ? 1 : 0;
	}
	return $n;
}

/**
 * Add or finish the legal pages. Returns how many were published.
 */
function tp_legal_import() {
	$pages = (array) get_option( 'tp_pages', array() );
	$done  = 0;
	// Two passes: links between the pages need them all to exist first.
	foreach ( array( 1, 2 ) as $pass ) {
		foreach ( tp_legal_pages() as $key => $p ) {
			$post = tp_legal_find( $key, $p[1] );
			if ( $post && ! tp_legal_is_unfinished( $post ) && empty( $pages[ '_new_' . $key ] ) ) {
				$pages[ $key ] = $post->ID;
				continue;
			}
			$args = array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $p[0],
				'post_content' => call_user_func( $p[2] ),
				'meta_input'   => array( '_wp_page_template' => 'page-templates/policy.php' ),
			);
			if ( $post ) {
				$args['ID'] = $post->ID;
				if ( 1 === $pass && 'publish' !== $post->post_status ) {
					$args['post_name'] = $p[1];
				}
				wp_update_post( $args );
				$id = $post->ID;
			} else {
				$args['post_name'] = $p[1];
				$id                = wp_insert_post( $args );
			}
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			$pages[ $key ]          = $id;
			$pages[ '_new_' . $key ] = 1;
			update_option( 'tp_pages', $pages );
			if ( 1 === $pass ) {
				++$done;
			}
		}
	}
	foreach ( array_keys( tp_legal_pages() ) as $key ) {
		unset( $pages[ '_new_' . $key ] );
	}
	update_option( 'tp_pages', $pages );
	if ( ! empty( $pages['privacy'] ) && 'publish' === get_post_status( $pages['privacy'] ) ) {
		update_option( 'wp_page_for_privacy_policy', (int) $pages['privacy'] );
	}
	return $done;
}

/** Footer links to the published legal pages. */
function tp_legal_footer_links() {
	$pages = (array) get_option( 'tp_pages', array() );
	$links = array();
	foreach ( tp_legal_pages() as $key => $p ) {
		$id = (int) ( $pages[ $key ] ?? 0 );
		if ( 'privacy' === $key && ! $id ) {
			$id = (int) get_option( 'wp_page_for_privacy_policy' );
		}
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$links[] = '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
		}
	}
	if ( $links ) {
		echo '<p class="site-footer__legal">' . implode( ' · ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
}
