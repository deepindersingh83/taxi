<?php
/**
 * Feedback after every trip goes where it can be acted on:
 * - low ratings (1–2 stars), comments that mention a problem (late, rude,
 *   unsafe, hurt…) and "please contact me" requests are added to the
 *   incident and complaint register automatically, linked to the trip,
 *   driver and vehicle;
 * - customers can ask to be contacted from the rating form;
 * - optionally, a text after each completed trip asks for a rating.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['sms_feedback'] = array( 'sms', __( 'Text customers a "How was your trip?" link after each completed trip', 'taxi-peninsula' ), 'checkbox', false, __( 'Useful for passengers who do not read email. Low ratings and problems go straight to the incident and complaint register.', 'taxi-peninsula' ) );
		return $s;
	}
);

/**
 * Words in a comment that mean "a person should look at this".
 *
 * @return array [ safety words, complaint words ]
 */
function tp_feedback_words() {
	return apply_filters(
		'tp_feedback_words',
		array(
			array( 'hurt', 'injur', 'fell', 'fall', 'unsafe', 'dangerous', 'not strapped', 'not secured', 'not restrained', 'loose', 'bruise', 'accident', 'speeding' ),
			array( 'late', 'rude', 'never came', 'didn\'t come', 'did not come', 'no show', 'no-show', 'overcharg', 'charged too', 'wrong address', 'complain', 'disappoint', 'unprofessional', 'dirty', 'smell', 'waited', 'missed my appointment' ),
		)
	);
}

/**
 * Should this feedback go to the register? Returns '' or an incident type.
 */
function tp_feedback_incident_type( $rating, $comment, $contact ) {
	$text = strtolower( (string) $comment );
	list( $safety, $complaint ) = tp_feedback_words();
	$has = static fn( $w ) => '' !== $text && preg_match( '/\b' . preg_quote( $w, '/' ) . '/u', $text );
	foreach ( $safety as $w ) {
		if ( $has( $w ) ) {
			return 'safety';
		}
	}
	if ( $rating <= 2 || $contact ) {
		return 'complaint';
	}
	foreach ( $complaint as $w ) {
		if ( $has( $w ) ) {
			return 'complaint';
		}
	}
	return '';
}

add_action(
	'tp_rating_form_fields',
	static function ( $id ) {
		?>
		<div class="field field--check">
			<label><input type="checkbox" name="contact_me" value="1" id="<?php echo esc_attr( $id ); ?>-contact"> <?php esc_html_e( 'Something went wrong — please contact me about this trip', 'taxi-peninsula' ); ?></label>
		</div>
		<?php
	}
);

add_action(
	'tp_trip_rated',
	static function ( $post_id, $rating, $comment ) {
		$contact = ! empty( $_POST['contact_me'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in tp_handle_rate_trip().
		$type    = tp_feedback_incident_type( $rating, $comment, $contact );
		if ( ! $type || ! function_exists( 'tp_add_incident' ) ) {
			return;
		}
		$b = tp_get_booking( $post_id );
		tp_in_site_lang(
			static function () use ( $post_id, $b, $rating, $comment, $contact, $type ) {
				$what = sprintf( /* translators: 1: stars 2: comment */ __( 'Customer rating %1$d★. %2$s', 'taxi-peninsula' ), $rating, $comment ?: __( '(no comment)', 'taxi-peninsula' ) );
				if ( $contact ) {
					$what .= "\n" . __( 'The customer asked us to contact them.', 'taxi-peninsula' );
				}
				$id = tp_add_incident(
					$type,
					/* translators: 1: reference 2: name */
					sprintf( __( 'Trip feedback: %1$s — %2$s', 'taxi-peninsula' ), $b['reference'], $b['name'] ),
					array(
						'status'     => 'open',
						'when'       => $b['date'] . 'T' . $b['time'],
						'reference'  => $b['reference'],
						'booking_id' => $post_id,
						'driver_id'  => $b['driver_id'],
						'fleet_id'   => $b['fleet_id'],
						'people'     => trim( $b['name'] . ' ' . $b['phone'] . ' ' . $b['email'] ),
						'what'       => $what,
						'followup'   => wp_date( 'Y-m-d', strtotime( $contact || 'safety' === $type ? '+1 day' : '+3 days' ) ),
						'source'     => 'rating:' . $post_id,
					)
				);
				if ( $id ) {
					tp_add_log( $post_id, array( 'note' => __( 'Feedback added to the incident and complaint register.', 'taxi-peninsula' ) ) );
					if ( $contact || 'safety' === $type ) {
						wp_mail(
							tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
							'safety' === $type
								/* translators: %s: reference */
								? sprintf( __( 'SAFETY concern in trip feedback — %s', 'taxi-peninsula' ), $b['reference'] )
								/* translators: %s: reference */
								: sprintf( __( 'Customer asked to be contacted — %s', 'taxi-peninsula' ), $b['reference'] ),
							$what . "\n\n" . tp_booking_summary( $b ) . "\n" . admin_url( 'post.php?action=edit&post=' . $id ),
							tp_mail_headers( $b['email'] )
						);
					}
				}
			}
		);
	},
	10,
	3
);

// Optional text asking for feedback once the trip is completed.
add_action(
	'tp_booking_status_changed',
	static function ( $post_id, $old, $status ) {
		if ( 'completed' !== $status || $old === $status || ! tp_setting( 'sms_feedback' ) || ! tp_sms_enabled() || get_post_meta( $post_id, '_tp_feedback_asked', true ) ) {
			return;
		}
		$b = tp_get_booking( $post_id );
		if ( ! $b['phone'] ) {
			return;
		}
		update_post_meta( $post_id, '_tp_feedback_asked', time() );
		tp_in_booking_lang(
			$post_id,
			static function () use ( $post_id, $b ) {
				$site = function_exists( 'tp_sms_site_name' ) ? tp_sms_site_name() : get_bloginfo( 'name' );
				/* translators: 1: site name 2: link */
				tp_booking_sms( $post_id, $b['phone'], sprintf( __( '%1$s: thanks for travelling with us. How was your trip? %2$s', 'taxi-peninsula' ), $site, tp_booking_manage_url( $post_id ) . '#rate' ), 'customer' );
			}
		);
	},
	30,
	3
);
