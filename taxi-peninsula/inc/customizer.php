<?php
/**
 * Customizer settings: contact details, hero content and booking options.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action( 'customize_register', 'tp_customize_register' );
function tp_customize_register( WP_Customize_Manager $wp_customize ) {
	$defaults = tp_defaults();

	$wp_customize->add_panel(
		'tp_panel',
		array(
			'title'    => __( 'Taxi Peninsula', 'taxi-peninsula' ),
			'priority' => 30,
		)
	);

	$sections = array(
		'tp_contact'  => __( 'Contact details', 'taxi-peninsula' ),
		'tp_hero'     => __( 'Home page hero', 'taxi-peninsula' ),
		'tp_bookings' => __( 'Bookings', 'taxi-peninsula' ),
		'tp_dynamic'  => __( 'Live numbers & hero messages', 'taxi-peninsula' ),
		'tp_video'    => __( 'Wheelchair safety video', 'taxi-peninsula' ),
	);
	foreach ( $sections as $id => $title ) {
		$wp_customize->add_section( $id, array( 'title' => $title, 'panel' => 'tp_panel' ) );
	}

	$fields = array(
		'phone_display'   => array( 'tp_contact', __( 'Phone (as displayed)', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'phone_link'      => array( 'tp_contact', __( 'Phone (international, for tap-to-call)', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'email'           => array( 'tp_contact', __( 'Email', 'taxi-peninsula' ), 'email', 'sanitize_email' ),
		'location'        => array( 'tp_contact', __( 'Location', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'hours'           => array( 'tp_contact', __( 'Opening hours', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'map_query'       => array( 'tp_contact', __( 'Map location (shown on the Contact page)', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'open_247'        => array( 'tp_contact', __( 'Open 24 hours, 7 days (tells Google your hours)', 'taxi-peninsula' ), 'checkbox', 'tp_sanitize_checkbox' ),
		'hero_eyebrow'    => array( 'tp_hero', __( 'Small heading', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'hero_title'      => array( 'tp_hero', __( 'Headline', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'hero_text'       => array( 'tp_hero', __( 'Intro text', 'taxi-peninsula' ), 'textarea', 'sanitize_textarea_field' ),
		'service_areas'   => array( 'tp_bookings', __( 'Service areas (one per line). Add other names and postcodes after "|", e.g. Frankston | Frankston South | 3199', 'taxi-peninsula' ), 'textarea', 'sanitize_textarea_field' ),
		'sms_number'      => array( 'tp_contact', __( 'Mobile for "Message us" by SMS (+61 format)', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'whatsapp_number' => array( 'tp_contact', __( 'WhatsApp number (+61 format, leave empty to hide)', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'since_year'      => array( 'tp_dynamic', __( 'Year you started (e.g. 2019) — shown as "since 2019"', 'taxi-peninsula' ), 'number', 'absint' ),
		'trips_offset'    => array( 'tp_dynamic', __( 'Trips completed before this website (added to the live count)', 'taxi-peninsula' ), 'number', 'absint' ),
		'stats_min'       => array( 'tp_dynamic', __( 'Only show the trip count once it reaches', 'taxi-peninsula' ), 'number', 'absint' ),
		'hero_moments'    => array( 'tp_dynamic', __( 'Time-of-day hero messages (one per line: HH:MM-HH:MM | message)', 'taxi-peninsula' ), 'textarea', 'sanitize_textarea_field' ),
		'video_title'     => array( 'tp_video', __( 'Video heading', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'video_url'       => array( 'tp_video', __( 'YouTube or Vimeo link (or upload a file below)', 'taxi-peninsula' ), 'url', 'esc_url_raw' ),
		'video_transcript'=> array( 'tp_video', __( 'Transcript (strongly recommended)', 'taxi-peninsula' ), 'textarea', 'sanitize_textarea_field' ),
		'notify_email'    => array( 'tp_bookings', __( 'Send new-booking alerts to', 'taxi-peninsula' ), 'email', 'sanitize_email' ),
		'min_notice'      => array( 'tp_bookings', __( 'Minimum notice for online bookings (minutes)', 'taxi-peninsula' ), 'number', 'absint' ),
		'show_mptp'       => array( 'tp_bookings', __( 'Ask about Multi Purpose Taxi Program (MPTP) membership', 'taxi-peninsula' ), 'checkbox', 'tp_sanitize_checkbox' ),
		'customer_emails' => array( 'tp_bookings', __( 'Email customers a confirmation and status updates', 'taxi-peninsula' ), 'checkbox', 'tp_sanitize_checkbox' ),
	);

	foreach ( $fields as $key => $f ) {
		$wp_customize->add_setting(
			'tp_' . $key,
			array(
				'default'           => $defaults[ $key ],
				'sanitize_callback' => $f[3],
			)
		);
		$wp_customize->add_control(
			'tp_' . $key,
			array(
				'section' => $f[0],
				'label'   => $f[1],
				'type'    => $f[2],
			)
		);
	}

	foreach ( array(
		'video_file'     => array( __( 'Video file (MP4) — optional instead of a link', 'taxi-peninsula' ), 'video' ),
		'video_captions' => array( __( 'Captions file (.vtt) for the uploaded video', 'taxi-peninsula' ), 'text/vtt' ),
		'video_poster'   => array( __( 'Cover image', 'taxi-peninsula' ), 'image' ),
	) as $key => $media ) {
		$wp_customize->add_setting( 'tp_' . $key, array( 'default' => '', 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				'tp_' . $key,
				array(
					'section'   => 'tp_video',
					'label'     => $media[0],
					'mime_type' => $media[1],
				)
			)
		);
	}

	$wp_customize->add_setting( 'tp_hero_image', array( 'default' => '', 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'tp_hero_image',
			array(
				'section'   => 'tp_hero',
				'label'     => __( 'Hero photo (optional)', 'taxi-peninsula' ),
				'mime_type' => 'image',
			)
		)
	);
}

function tp_sanitize_checkbox( $value ) {
	return (bool) $value;
}
