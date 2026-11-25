<?php
/**
 * Quick booking form: everything on one short screen, for landing pages,
 * Google Ads, hotel and venue pages or a sidebar.
 *
 *   [tp_quick_booking]
 *   [tp_quick_booking heading="Airport transfer" dropoff="Melbourne Airport"]
 *
 * It sends a normal booking (same checks, SMS, emails and dispatch as the
 * main form). Settings → Quick booking form chooses the heading, button,
 * which fields show, which are required, the vehicle when the wheelchair
 * question is hidden, and up to three questions of your own (answers are
 * added to the booking notes).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['quickform'] = array( __( 'Quick booking form', 'taxi-peninsula' ), __( 'A one-screen booking form. Put [tp_quick_booking] on any page or in a widget — for example an ad landing page, a hotel page or the sidebar. Bookings from it work exactly like the main form.', 'taxi-peninsula' ) );
		return $s;
	}
);

add_filter(
	'tp_settings_tabs',
	static function ( $tabs ) {
		$tabs['quickform'] = array( __( 'Quick booking form', 'taxi-peninsula' ), array( 'quickform' ) );
		return $tabs;
	}
);

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$show = array(
			'hide'     => __( 'Hide', 'taxi-peninsula' ),
			'optional' => __( 'Show (optional)', 'taxi-peninsula' ),
			'required' => __( 'Show (required)', 'taxi-peninsula' ),
		);
		$s['qf_heading']    = array( 'quickform', __( 'Heading', 'taxi-peninsula' ), 'text', __( 'Book a taxi in one minute', 'taxi-peninsula' ), __( 'Leave empty for no heading. A page can set its own: [tp_quick_booking heading="Airport transfer"].', 'taxi-peninsula' ) );
		$s['qf_intro']      = array( 'quickform', __( 'Short text under the heading', 'taxi-peninsula' ), 'text', __( 'Tell us where and when — we confirm by SMS or email.', 'taxi-peninsula' ), '' );
		$s['qf_button']     = array( 'quickform', __( 'Button text', 'taxi-peninsula' ), 'text', __( 'Book now', 'taxi-peninsula' ), '' );
		$s['qf_email']      = array( 'quickform', __( 'Email', 'taxi-peninsula' ), 'select', 'optional', __( 'Without an email the customer gets updates by SMS only — or none at all if SMS is set to "Office alerts only".', 'taxi-peninsula' ), $show );
		$s['qf_chair']      = array( 'quickform', __( 'Ask “Is anyone travelling in a wheelchair?”', 'taxi-peninsula' ), 'checkbox', true, '' );
		$s['qf_vehicle']    = array( 'quickform', __( 'Vehicle when the wheelchair question is hidden', 'taxi-peninsula' ), 'select', 'sedan', '', array_merge( tp_vehicle_types() ) );
		$s['qf_passengers'] = array( 'quickform', __( 'Number of passengers', 'taxi-peninsula' ), 'select', 'hide', '', $show );
		$s['qf_return']     = array( 'quickform', __( 'Return trip time', 'taxi-peninsula' ), 'select', 'hide', __( 'Adds “Need a return trip? Pick-up time”.', 'taxi-peninsula' ), $show );
		$s['qf_flight']     = array( 'quickform', __( 'Flight number', 'taxi-peninsula' ), 'select', 'hide', '', $show );
		$s['qf_notes']      = array( 'quickform', __( 'Notes box', 'taxi-peninsula' ), 'select', 'optional', '', $show );
		$s['qf_notes_label'] = array( 'quickform', __( 'Notes box label', 'taxi-peninsula' ), 'text', __( 'Anything we should know?', 'taxi-peninsula' ), '' );
		foreach ( array( 1, 2, 3 ) as $n ) {
			/* translators: %d: question number */
			$s[ 'qf_q' . $n ]      = array( 'quickform', sprintf( __( 'Your question %d', 'taxi-peninsula' ), $n ), 'text', '', __( 'e.g. “Ward or clinic?”, “Hotel room number”, “Return pick-up time from the winery”. Leave empty to skip. Answers are added to the booking notes.', 'taxi-peninsula' ) );
			/* translators: %d: question number */
			$s[ 'qf_q' . $n . '_req' ] = array( 'quickform', sprintf( __( 'Question %d must be answered', 'taxi-peninsula' ), $n ), 'checkbox', false, '' );
		}
		return $s;
	}
);

/** "hide", "optional" or "required" for a field. */
function tp_qf_show( $key ) {
	$v = (string) tp_setting( 'qf_' . $key );
	return in_array( $v, array( 'hide', 'optional', 'required' ), true ) ? $v : 'hide';
}

/** Your own questions: [ n => [label, required] ]. */
function tp_qf_questions() {
	$out = array();
	foreach ( array( 1, 2, 3 ) as $n ) {
		$label = trim( (string) tp_setting( 'qf_q' . $n ) );
		if ( '' !== $label ) {
			$out[ $n ] = array( $label, (bool) tp_setting( 'qf_q' . $n . '_req' ) );
		}
	}
	return $out;
}

/**
 * Before the normal booking checks: fill in what the quick form leaves out,
 * check its own required fields and add answers to the notes.
 */
add_action(
	'admin_post_nopriv_tp_booking',
	'tp_qf_prepare',
	1
);
add_action( 'admin_post_tp_booking', 'tp_qf_prepare', 1 );
function tp_qf_prepare() {
	if ( empty( $_POST['tp_quick'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the main handler.
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- the main handler checks the nonce and sanitizes every field.
	$errors = array();
	foreach ( array( 'email', 'passengers', 'flight', 'notes' ) as $f ) {
		$key = 'notes' === $f ? 'notes' : $f;
		if ( 'required' === tp_qf_show( $f ) && '' === trim( (string) ( $_POST[ $key ] ?? '' ) ) ) {
			$errors[ $key ] = __( 'Please fill this in.', 'taxi-peninsula' );
		}
	}
	if ( 'required' === tp_qf_show( 'return' ) && '' === trim( (string) ( $_POST['return_time'] ?? '' ) ) ) {
		$errors['return_time'] = __( 'Please fill this in.', 'taxi-peninsula' );
	}
	$answers = array();
	foreach ( tp_qf_questions() as $n => $q ) {
		$a = trim( sanitize_text_field( wp_unslash( $_POST[ 'qf_q' . $n ] ?? '' ) ) );
		if ( '' === $a && $q[1] ) {
			$errors[ 'qf_q' . $n ] = __( 'Please answer this.', 'taxi-peninsula' );
		}
		if ( '' !== $a ) {
			$answers[] = $q[0] . ' ' . $a;
		}
	}
	if ( $answers ) {
		$_POST['notes'] = trim( implode( "\n", $answers ) . "\n" . ( $_POST['notes'] ?? '' ) );
	}
	if ( ! empty( $_POST['return_time'] ) ) {
		$_POST['return_trip'] = '1';
	}
	if ( ! tp_setting( 'qf_chair' ) ) {
		$v                    = (string) tp_setting( 'qf_vehicle' );
		$_POST['vehicle']     = isset( tp_vehicle_types()[ $v ] ) ? $v : 'sedan';
		$_POST['wheelchairs'] = 'sedan' === $_POST['vehicle'] ? 0 : 1;
	}
	if ( empty( $_POST['passengers'] ) ) {
		$_POST['passengers'] = 1;
	}
	// One tick on the quick form covers both "contact me about this booking" and the terms.
	if ( ! empty( $_POST['agree'] ) ) {
		$_POST['consent'] = '1';
		$_POST['terms']   = '1';
	}
	if ( $errors ) {
		$return = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['_tp_return'] ?? '' ) ), home_url( '/' ) );
		tp_booking_fail( $return, $errors, wp_unslash( $_POST ) );
	}
	// phpcs:enable
}

// The quick form's single tick replaces the consent and terms errors.
add_filter(
	'tp_booking_fail_errors',
	static function ( $errors, $old ) {
		if ( ! empty( $old['tp_quick'] ) && ( isset( $errors['consent'] ) || isset( $errors['terms'] ) ) ) {
			$errors['agree'] = __( 'Please tick the box to book.', 'taxi-peninsula' );
		}
		return $errors;
	},
	10,
	2
);

add_shortcode(
	'tp_quick_booking',
	static function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'heading' => null,
				'intro'   => null,
				'button'  => null,
				'pickup'  => '',
				'dropoff' => '',
			),
			$atts,
			'tp_quick_booking'
		);
		ob_start();
		echo '<div class="tp-component">';
		if ( ! apply_filters( 'tp_booking_form_screen', false ) ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
			if ( isset( $_GET['booking'] ) && 'received' === $_GET['booking'] ) {
				$ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
				get_template_part(
					'template-parts/booking',
					'success',
					array(
						'token'     => isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '',
						'reference' => preg_match( '/^TP-[A-Z0-9]{6}$/', $ref ) ? $ref : '',
						'count'     => 1,
						'pay_error' => ! empty( $_GET['pay_error'] ),
					)
				);
			} else {
				$state = array( 'errors' => array(), 'old' => array() );
				if ( isset( $_GET['tp_form'] ) ) {
					$stored = get_transient( 'tp_form_' . sanitize_key( wp_unslash( $_GET['tp_form'] ) ) );
					if ( is_array( $stored ) ) {
						$state = $stored;
					}
				} else {
					$state['old'] = tp_booking_form_prefill( array_filter( array( 'pickup' => $atts['pickup'], 'dropoff' => $atts['dropoff'] ) ) );
				}
				$state['atts'] = $atts;
				get_template_part( 'template-parts/booking', 'quick', $state );
			}
			// phpcs:enable
		}
		echo '</div>';
		return ob_get_clean();
	}
);

// How to use it, on the settings tab.
add_filter(
	'tp_settings_checks',
	static function ( $c, $tab ) {
		if ( 'quickform' === $tab ) {
			$c[] = array( 'ok', __( 'How to use it', 'taxi-peninsula' ), __( 'Add a Shortcode block with [tp_quick_booking]. Per page you can change the heading and fill in an address: [tp_quick_booking heading="Airport transfer" dropoff="Melbourne Airport"].', 'taxi-peninsula' ) );
		}
		return $c;
	},
	10,
	2
);
