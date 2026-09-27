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
		'hero_eyebrow'    => array( 'tp_hero', __( 'Small heading', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'hero_title'      => array( 'tp_hero', __( 'Headline', 'taxi-peninsula' ), 'text', 'sanitize_text_field' ),
		'hero_text'       => array( 'tp_hero', __( 'Intro text', 'taxi-peninsula' ), 'textarea', 'sanitize_textarea_field' ),
		'service_areas'   => array( 'tp_bookings', __( 'Service areas (one per line)', 'taxi-peninsula' ), 'textarea', 'sanitize_textarea_field' ),
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
