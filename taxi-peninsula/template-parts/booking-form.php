<?php
/**
 * Booking form.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { errors: array, old: array }
 */

$errors = isset( $args['errors'] ) ? (array) $args['errors'] : array();
$old    = isset( $args['old'] ) ? (array) $args['old'] : array();

$val = static function ( $key, $default = '' ) use ( $old ) {
	return isset( $old[ $key ] ) ? $old[ $key ] : $default;
};

$err = static function ( $key ) use ( $errors ) {
	if ( empty( $errors[ $key ] ) ) {
		return;
	}
	printf( '<p class="field__error" id="err-%1$s">%2$s</p>', esc_attr( $key ), esc_html( $errors[ $key ] ) );
};

$invalid = static function ( $key ) use ( $errors ) {
	if ( ! empty( $errors[ $key ] ) ) {
		printf( ' aria-invalid="true" aria-describedby="err-%s"', esc_attr( $key ) );
	}
};

$current_url = ( is_singular() && ! is_front_page() ) ? get_permalink() : home_url( '/' );
$places      = tp_places_available();
$terms_url   = tp_terms_url();
tp_booking_script_config();
$areas       = tp_service_areas();
$today       = wp_date( 'Y-m-d' );
?>
<form class="booking-form" data-capacity="<?php echo esc_attr( wp_json_encode( tp_vehicle_capacity() ) ); ?>" data-messages="<?php echo esc_attr( wp_json_encode( array( 'chairs' => __( 'This vehicle takes one wheelchair. Choose "Maxi WAT" for two wheelchairs.', 'taxi-peninsula' ), 'chairsCall' => sprintf( /* translators: %s: phone number */ __( 'For more than two wheelchairs we send more than one vehicle — please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ), 'people' => __( 'This vehicle seats up to %d people including wheelchair users. Choose "Maxi WAT" or call us for a larger group.', 'taxi-peninsula' ), 'returnTime' => __( 'The return pick-up needs to be after the first pick-up time.', 'taxi-peninsula' ) ) ) ); ?>" method="post"<?php echo ! empty( $args['quickbook'] ) ? ' data-from-quickbook' : ''; ?> action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
	<input type="hidden" name="action" value="tp_booking">
	<input type="hidden" name="_tp_return" value="<?php echo esc_url( $current_url ); ?>">
	<?php wp_nonce_field( 'tp_booking', '_tp_nonce', false ); ?>
	<?php
	/**
	 * Extra hidden fields (a quote being accepted, a partner page).
	 *
	 * @param array $old Prefilled values.
	 */
	do_action( 'tp_booking_form_hidden', $old );
	?>


	<?php if ( $errors ) : ?>
		<div class="notice notice--error" role="alert" tabindex="-1" data-focus>
			<p><strong><?php esc_html_e( 'Please check the highlighted fields.', 'taxi-peninsula' ); ?></strong></p>
			<?php if ( ! empty( $errors['form'] ) ) : ?>
				<p><?php echo esc_html( $errors['form'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	$methods   = tp_payment_methods();
	$has_pay   = count( $methods ) > 1;
	$step_list = array(
		'trip'    => __( 'Trip', 'taxi-peninsula' ),
		'needs'   => __( 'Needs', 'taxi-peninsula' ),
		'pay'     => __( 'Payment', 'taxi-peninsula' ),
		'details' => __( 'Details', 'taxi-peninsula' ),
	);
	if ( ! $has_pay ) {
		unset( $step_list['pay'] );
	}
	?>
	<nav class="form-steps" aria-label="<?php esc_attr_e( 'Booking steps', 'taxi-peninsula' ); ?>" data-form-steps>
		<ol>
			<?php $n = 0; ?>
			<?php foreach ( $step_list as $key => $label ) : ?>
				<li data-step="<?php echo esc_attr( $key ); ?>"><a href="#bf-step-<?php echo esc_attr( $key ); ?>"><span class="form-steps__n"><?php echo esc_html( ++$n ); ?></span><span class="form-steps__label"><?php echo esc_html( $label ); ?></span></a></li>
			<?php endforeach; ?>
		</ol>
		<span class="form-steps__bar" aria-hidden="true"><span></span></span>
	</nav>

	<?php tp_booking_form_account_panel( $old ); ?>

	<fieldset class="booking-form__group" id="bf-step-trip" data-step-panel="trip">
		<legend><span class="step">1</span> <?php esc_html_e( 'Your trip', 'taxi-peninsula' ); ?></legend>

		<div class="field field--wide">
			<label for="bf-pickup"><?php esc_html_e( 'Pick-up address', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-pickup" name="pickup" type="text" required autocomplete="street-address" <?php echo $places ? 'data-places' : ''; ?> value="<?php echo esc_attr( $val( 'pickup' ) ); ?>" placeholder="<?php esc_attr_e( 'Street address, suburb', 'taxi-peninsula' ); ?>"<?php $invalid( 'pickup' ); ?>>
			<?php $err( 'pickup' ); ?>
			<?php tp_booking_form_address_picker( 'pickup' ); ?>
		</div>

		<div class="field field--wide">
			<label for="bf-dropoff"><?php esc_html_e( 'Drop-off address', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-dropoff" name="dropoff" type="text" required <?php echo $places ? 'data-places' : ''; ?> value="<?php echo esc_attr( $val( 'dropoff' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Frankston Hospital, Melbourne Airport', 'taxi-peninsula' ); ?>"<?php $invalid( 'dropoff' ); ?>>
			<?php $err( 'dropoff' ); ?>
			<?php tp_booking_form_address_picker( 'dropoff' ); ?>
		</div>

		<?php if ( ! $places && $areas ) : ?>
			<p class="field__hint field--wide areas-hint" id="bf-areas-hint">
				<?php
				/* translators: %s: list of suburbs */
				echo esc_html( sprintf( __( 'We cover %s and more — type the full street address.', 'taxi-peninsula' ), implode( ', ', array_slice( $areas, 0, 6 ) ) ) );
				?>
			</p>
		<?php endif; ?>

		<div class="field">
			<label for="bf-date"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-date" name="date" type="date" required min="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( tp_booking_max_date() ); ?>" value="<?php echo esc_attr( $val( 'date', $today ) ); ?>"<?php $invalid( 'date' ); ?>>
			<?php $err( 'date' ); ?>
		</div>

		<div class="field">
			<label for="bf-time"><?php esc_html_e( 'Pick-up time', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-time" name="time" type="time" required value="<?php echo esc_attr( $val( 'time' ) ); ?>"<?php $invalid( 'time' ); ?>>
			<?php $err( 'time' ); ?>
		</div>

		<div class="field field--wide">
			<label for="bf-flight"><?php esc_html_e( 'Arriving by plane? Flight number (optional)', 'taxi-peninsula' ); ?></label>
			<input id="bf-flight" name="flight" type="text" autocomplete="off" inputmode="text" maxlength="9" placeholder="<?php esc_attr_e( 'e.g. QF432', 'taxi-peninsula' ); ?>" value="<?php echo esc_attr( $val( 'flight' ) ); ?>" aria-describedby="bf-flight-hint"<?php $invalid( 'flight' ); ?>>
			<p class="field__hint" id="bf-flight-hint"><?php esc_html_e( 'For airport pick-ups we watch your flight and move the pick-up if it lands early or late. Set the pick-up time to when you expect to be ready.', 'taxi-peninsula' ); ?></p>
			<?php $err( 'flight' ); ?>
		</div>

		<?php if ( tp_fare_available() ) : ?>
			<div class="field field--wide fare-estimate" data-fare-estimate aria-live="polite" hidden data-note="<?php esc_attr_e( 'Estimate only, based on the usual driving route. The final fare depends on traffic, waiting time and route, and any MPTP subsidy is applied by the driver.', 'taxi-peninsula' ); ?>"></div>
		<?php endif; ?>

		<div class="field field--wide field--check">
			<input id="bf-return" name="return_trip" type="checkbox" value="1" data-toggle-target="#bf-return-wrap" <?php checked( $val( 'return_trip' ), '1' ); ?>>
			<label for="bf-return"><?php esc_html_e( 'I need a return trip', 'taxi-peninsula' ); ?></label>
		</div>

		<div class="field" id="bf-return-wrap" <?php echo $val( 'return_trip' ) ? '' : 'hidden'; ?>>
			<label for="bf-return-time"><?php esc_html_e( 'Return pick-up time (if known)', 'taxi-peninsula' ); ?></label>
			<input id="bf-return-time" name="return_time" type="time" value="<?php echo esc_attr( $val( 'return_time' ) ); ?>"<?php $invalid( 'return_time' ); ?>>
			<?php $err( 'return_time' ); ?>
		</div>

		<?php if ( tp_setting( 'recurring_enabled' ) ) : ?>
			<div class="field field--wide field--check">
				<input id="bf-repeat" name="repeat" type="checkbox" value="1" data-toggle-target="#bf-repeat-wrap" <?php checked( $val( 'repeat' ), '1' ); ?>>
				<label for="bf-repeat"><?php esc_html_e( 'This is a regular trip (e.g. dialysis, therapy, day program)', 'taxi-peninsula' ); ?></label>
			</div>

			<div class="field field--wide repeat-box" id="bf-repeat-wrap" <?php echo $val( 'repeat' ) ? '' : 'hidden'; ?>>
				<fieldset class="weekday-picker"<?php $invalid( 'repeat' ); ?>>
					<legend class="field__label"><?php esc_html_e( 'Repeat every', 'taxi-peninsula' ); ?></legend>
					<?php
					$old_days = array_map( 'intval', (array) $val( 'repeat_days', array() ) );
					global $wp_locale;
					for ( $n = 1; $n <= 7; $n++ ) :
						$full = $wp_locale->get_weekday( $n % 7 );
						?>
						<label class="weekday">
							<input type="checkbox" name="repeat_days[]" value="<?php echo esc_attr( $n ); ?>" <?php checked( in_array( $n, $old_days, true ) ); ?>>
							<span><abbr title="<?php echo esc_attr( $full ); ?>"><?php echo esc_html( $wp_locale->get_weekday_abbrev( $full ) ); ?></abbr></span>
						</label>
					<?php endfor; ?>
				</fieldset>
				<div class="field">
					<label for="bf-repeat-until"><?php esc_html_e( 'Until', 'taxi-peninsula' ); ?></label>
					<input id="bf-repeat-until" name="repeat_until" type="date" min="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( '+' . (int) tp_setting( 'recurring_max_weeks' ) . ' weeks' ) ) ); ?>" value="<?php echo esc_attr( $val( 'repeat_until' ) ); ?>">
				</div>
				<p class="field__hint">
					<?php
					/* translators: %d: weeks */
					echo esc_html( sprintf( __( 'Same times each day, for up to %d weeks. Each trip gets its own reference so it can be changed separately.', 'taxi-peninsula' ), (int) tp_setting( 'recurring_max_weeks' ) ) );
					?>
				</p>
				<?php $err( 'repeat' ); ?>
			</div>
		<?php endif; ?>
	</fieldset>

	<fieldset class="booking-form__group" id="bf-step-needs" data-step-panel="needs">
		<legend><span class="step">2</span> <?php esc_html_e( 'Accessibility needs', 'taxi-peninsula' ); ?></legend>

		<div class="field field--wide">
			<span class="field__label" id="bf-vehicle-label"><?php esc_html_e( 'Vehicle', 'taxi-peninsula' ); ?></span>
			<?php if ( ! empty( $args['auto_sedan'] ) ) : ?>
				<p class="notice notice--info vehicle-note" role="status"><?php esc_html_e( 'You said no wheelchair, so we have chosen a standard taxi. If anyone travels in a wheelchair or needs the ramp, choose a wheelchair accessible taxi below.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
			<div class="choice-cards" role="radiogroup" aria-labelledby="bf-vehicle-label">
				<?php
				$icons = array(
					'wat'      => 'wheelchair',
					'maxi_wat' => 'users',
					'sedan'    => 'star',
				);
				foreach ( tp_vehicle_types() as $key => $label ) :
					?>
					<label class="choice-card">
						<input type="radio" name="vehicle" value="<?php echo esc_attr( $key ); ?>" <?php checked( $val( 'vehicle', 'wat' ), $key ); ?>>
						<span class="choice-card__body"><?php tp_the_icon( $icons[ $key ] ); ?><span><?php echo esc_html( $label ); ?></span></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="field">
			<label for="bf-aid"><?php esc_html_e( 'Mobility aid', 'taxi-peninsula' ); ?></label>
			<select id="bf-aid" name="mobility_aid">
				<?php foreach ( tp_mobility_aids() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $val( 'mobility_aid', 'manual' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="field field--pair">
			<div>
				<label for="bf-passengers"><?php esc_html_e( 'Passengers', 'taxi-peninsula' ); ?></label>
				<input id="bf-passengers" name="passengers" type="number" inputmode="numeric" min="1" max="11" value="<?php echo esc_attr( $val( 'passengers', '1' ) ); ?>"<?php $invalid( 'passengers' ); ?>>
				<?php $err( 'passengers' ); ?>
			</div>
			<div>
				<label for="bf-wheelchairs"><?php esc_html_e( 'Wheelchairs', 'taxi-peninsula' ); ?></label>
				<input id="bf-wheelchairs" name="wheelchairs" type="number" inputmode="numeric" min="0" max="4" value="<?php echo esc_attr( $val( 'wheelchairs', '1' ) ); ?>"<?php $invalid( 'wheelchairs' ); ?>>
				<?php $err( 'wheelchairs' ); ?>
			</div>
		</div>

		<?php if ( tp_opt( 'show_mptp' ) ) : ?>
			<div class="field field--wide field--check">
				<input id="bf-mptp" name="mptp" type="checkbox" value="1" <?php checked( $val( 'mptp' ), '1' ); ?>>
				<label for="bf-mptp"><?php esc_html_e( 'I am a Multi Purpose Taxi Program (MPTP) member', 'taxi-peninsula' ); ?></label>
			</div>
		<?php endif; ?>

		<div class="field field--wide">
			<label for="bf-notes"><?php esc_html_e( 'Anything we should know?', 'taxi-peninsula' ); ?></label>
			<textarea id="bf-notes" name="notes" rows="3" placeholder="<?php esc_attr_e( 'e.g. wheelchair dimensions, carer travelling, assistance to the door, flight number', 'taxi-peninsula' ); ?>"><?php echo esc_textarea( $val( 'notes' ) ); ?></textarea>
		</div>
	</fieldset>

	<?php if ( $has_pay ) : ?>
	<fieldset class="booking-form__group" id="bf-step-pay" data-step-panel="pay">
		<legend><span class="step">3</span> <?php esc_html_e( 'Payment', 'taxi-peninsula' ); ?></legend>

		<div class="field field--wide">
			<span class="field__label" id="bf-payment-label"><?php esc_html_e( 'How will you pay?', 'taxi-peninsula' ); ?></span>
			<div class="radio-list" role="radiogroup" aria-labelledby="bf-payment-label">
				<?php foreach ( $methods as $key => $label ) : ?>
					<label class="radio-row">
						<input type="radio" name="payment" value="<?php echo esc_attr( $key ); ?>" data-show-when="<?php echo esc_attr( $key ); ?>" <?php checked( $val( 'payment', 'driver' ), $key ); ?>>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( isset( $methods['account'] ) ) : ?>
			<div class="field field--wide account-box" data-payment-panel="account" <?php echo 'account' === $val( 'payment' ) ? '' : 'hidden'; ?>>
				<div class="booking-form__group booking-form__group--nested">
					<div class="field">
						<label for="bf-invoice-name"><?php esc_html_e( 'Invoice to', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
						<input id="bf-invoice-name" name="invoice_name" type="text" value="<?php echo esc_attr( $val( 'invoice_name' ) ); ?>" placeholder="<?php esc_attr_e( 'Plan manager, provider or company', 'taxi-peninsula' ); ?>"<?php $invalid( 'invoice_name' ); ?>>
						<?php $err( 'invoice_name' ); ?>
					</div>
					<div class="field">
						<label for="bf-invoice-email"><?php esc_html_e( 'Invoice email', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
						<input id="bf-invoice-email" name="invoice_email" type="email" value="<?php echo esc_attr( $val( 'invoice_email' ) ); ?>"<?php $invalid( 'invoice_email' ); ?>>
						<?php $err( 'invoice_email' ); ?>
					</div>
					<div class="field field--wide">
						<label for="bf-ndis"><?php esc_html_e( 'NDIS number or purchase order (optional)', 'taxi-peninsula' ); ?></label>
						<input id="bf-ndis" name="ndis_number" type="text" value="<?php echo esc_attr( $val( 'ndis_number' ) ); ?>">
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( isset( $methods['online'] ) ) : ?>
			<p class="field field--wide field__hint" data-payment-panel="online" <?php echo 'online' === $val( 'payment' ) ? '' : 'hidden'; ?>>
				<?php esc_html_e( 'After you submit, you will be taken to Stripe’s secure checkout to pay the deposit. The rest of the fare is paid at the end of the trip.', 'taxi-peninsula' ); ?>
			</p>
		<?php endif; ?>
	</fieldset>
	<?php endif; ?>

	<fieldset class="booking-form__group" id="bf-step-details" data-step-panel="details">
		<legend><span class="step"><?php echo $has_pay ? '4' : '3'; ?></span> <?php esc_html_e( 'Your details', 'taxi-peninsula' ); ?></legend>

		<?php
		$me = is_user_logged_in() && function_exists( 'tp_is_account_user' ) && tp_is_account_user() ? wp_get_current_user() : null;
		if ( $me ) :
			$me_data = array(
				'name'  => trim( $me->first_name . ' ' . $me->last_name ) ?: $me->display_name,
				'phone' => (string) get_user_meta( $me->ID, 'tp_mobile', true ),
				'email' => $me->user_email,
			);
			?>
			<div class="field field--wide field--check use-me">
				<input id="bf-use-me" type="checkbox" data-use-me="<?php echo esc_attr( wp_json_encode( $me_data ) ); ?>">
				<label for="bf-use-me"><?php esc_html_e( 'I am the passenger — use my account details', 'taxi-peninsula' ); ?></label>
			</div>
		<?php endif; ?>

		<div class="field field--wide">
			<label for="bf-name"><?php esc_html_e( 'Full name', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-name" name="name" type="text" required autocomplete="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>"<?php $invalid( 'name' ); ?>>
			<?php $err( 'name' ); ?>
		</div>

		<div class="field">
			<label for="bf-phone"><?php esc_html_e( 'Mobile', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" value="<?php echo esc_attr( $val( 'phone' ) ); ?>" placeholder="04xx xxx xxx"<?php $invalid( 'phone' ); ?>>
			<?php $err( 'phone' ); ?>
		</div>

		<div class="field">
			<label for="bf-email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-email" name="email" type="email" required autocomplete="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>"<?php $invalid( 'email' ); ?>>
			<?php $err( 'email' ); ?>
		</div>

		<div class="field field--wide field--check">
			<input id="bf-consent" name="consent" type="checkbox" value="1" required <?php checked( $val( 'consent' ), '1' ); ?><?php $invalid( 'consent' ); ?>>
			<label for="bf-consent">
				<?php esc_html_e( 'I agree to be contacted by phone, SMS or email about this booking, and for you to use the mobility and health details I give to arrange the trip.', 'taxi-peninsula' ); ?>
				<?php if ( get_privacy_policy_url() ) : ?>
					<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'taxi-peninsula' ); ?></span></a>
				<?php endif; ?>
				<span class="req" aria-hidden="true">*</span>
			</label>
			<?php $err( 'consent' ); ?>
		</div>

		<?php if ( function_exists( 'tp_promos_active' ) && tp_promos_active() ) : ?>
			<div class="field">
				<label for="bf-promo"><?php esc_html_e( 'Promo code (optional)', 'taxi-peninsula' ); ?></label>
				<input id="bf-promo" name="promo" type="text" autocomplete="off" maxlength="20" value="<?php echo esc_attr( $val( 'promo' ) ); ?>"<?php $invalid( 'promo' ); ?>>
				<?php $err( 'promo' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( function_exists( 'tp_marketing_enabled' ) && tp_marketing_enabled() ) : ?>
			<div class="field field--wide field--check">
				<input id="bf-offers" name="offers" type="checkbox" value="1" <?php checked( $val( 'offers' ), '1' ); ?>>
				<label for="bf-offers"><?php echo esc_html( tp_marketing_consent_text() ); ?></label>
			</div>
		<?php endif; ?>

		<?php if ( $terms_url ) : ?>
			<div class="field field--wide field--check">
				<input id="bf-terms" name="terms" type="checkbox" value="1" required <?php checked( $val( 'terms' ), '1' ); ?><?php $invalid( 'terms' ); ?>>
				<label for="bf-terms">
					<?php
					printf(
						/* translators: %s: link to the terms page */
						esc_html__( 'I have read and accept the %s.', 'taxi-peninsula' ),
						'<a href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'terms and cancellation policy', 'taxi-peninsula' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'taxi-peninsula' ) . '</span></a>'
					);
					?>
					<span class="req" aria-hidden="true">*</span>
				</label>
				<?php $err( 'terms' ); ?>
			</div>
		<?php endif; ?>
	</fieldset>

	<?php tp_spam_fields(); ?>

	<?php get_template_part( 'template-parts/trip-summary', null, array( 'compact' => true ) ); ?>

	<div class="booking-form__submit">
		<button type="submit" class="btn btn--accent btn--lg"><?php esc_html_e( 'Request booking', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></button>
		<p class="booking-form__alt">
			<?php
			printf(
				/* translators: %s: phone link */
				esc_html__( 'Travelling in the next hour? Call %s', 'taxi-peninsula' ),
				'<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
			);
			?>
		</p>
	</div>
</form>
