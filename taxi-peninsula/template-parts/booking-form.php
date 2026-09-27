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
$areas       = tp_service_areas();
$today       = wp_date( 'Y-m-d' );
?>
<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
	<input type="hidden" name="action" value="tp_booking">
	<input type="hidden" name="_tp_return" value="<?php echo esc_url( $current_url ); ?>">
	<?php wp_nonce_field( 'tp_booking', '_tp_nonce', false ); ?>


	<?php if ( $errors ) : ?>
		<div class="notice notice--error" role="alert" tabindex="-1" data-focus>
			<p><strong><?php esc_html_e( 'Please check the highlighted fields.', 'taxi-peninsula' ); ?></strong></p>
			<?php if ( ! empty( $errors['form'] ) ) : ?>
				<p><?php echo esc_html( $errors['form'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<fieldset class="booking-form__group">
		<legend><span class="step">1</span> <?php esc_html_e( 'Your trip', 'taxi-peninsula' ); ?></legend>

		<div class="field field--wide">
			<label for="bf-pickup"><?php esc_html_e( 'Pick-up address', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-pickup" name="pickup" type="text" required autocomplete="street-address" list="tp-areas" value="<?php echo esc_attr( $val( 'pickup' ) ); ?>" placeholder="<?php esc_attr_e( 'Street address, suburb', 'taxi-peninsula' ); ?>"<?php $invalid( 'pickup' ); ?>>
			<?php $err( 'pickup' ); ?>
		</div>

		<div class="field field--wide">
			<label for="bf-dropoff"><?php esc_html_e( 'Drop-off address', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-dropoff" name="dropoff" type="text" required list="tp-areas" value="<?php echo esc_attr( $val( 'dropoff' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Frankston Hospital, Melbourne Airport', 'taxi-peninsula' ); ?>"<?php $invalid( 'dropoff' ); ?>>
			<?php $err( 'dropoff' ); ?>
		</div>

		<datalist id="tp-areas">
			<?php foreach ( $areas as $area ) : ?>
				<option value="<?php echo esc_attr( $area ); ?>"></option>
			<?php endforeach; ?>
		</datalist>

		<div class="field">
			<label for="bf-date"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-date" name="date" type="date" required min="<?php echo esc_attr( $today ); ?>" value="<?php echo esc_attr( $val( 'date', $today ) ); ?>"<?php $invalid( 'date' ); ?>>
			<?php $err( 'date' ); ?>
		</div>

		<div class="field">
			<label for="bf-time"><?php esc_html_e( 'Pick-up time', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="bf-time" name="time" type="time" required step="300" value="<?php echo esc_attr( $val( 'time' ) ); ?>"<?php $invalid( 'time' ); ?>>
			<?php $err( 'time' ); ?>
		</div>

		<div class="field field--wide field--check">
			<input id="bf-return" name="return_trip" type="checkbox" value="1" data-toggle-target="#bf-return-wrap" <?php checked( $val( 'return_trip' ), '1' ); ?>>
			<label for="bf-return"><?php esc_html_e( 'I need a return trip', 'taxi-peninsula' ); ?></label>
		</div>

		<div class="field" id="bf-return-wrap" <?php echo $val( 'return_trip' ) ? '' : 'hidden'; ?>>
			<label for="bf-return-time"><?php esc_html_e( 'Return pick-up time (if known)', 'taxi-peninsula' ); ?></label>
			<input id="bf-return-time" name="return_time" type="time" step="300" value="<?php echo esc_attr( $val( 'return_time' ) ); ?>"<?php $invalid( 'return_time' ); ?>>
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

	<fieldset class="booking-form__group">
		<legend><span class="step">2</span> <?php esc_html_e( 'Accessibility needs', 'taxi-peninsula' ); ?></legend>

		<div class="field field--wide">
			<span class="field__label" id="bf-vehicle-label"><?php esc_html_e( 'Vehicle', 'taxi-peninsula' ); ?></span>
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
				<input id="bf-passengers" name="passengers" type="number" inputmode="numeric" min="1" max="11" value="<?php echo esc_attr( $val( 'passengers', '1' ) ); ?>">
			</div>
			<div>
				<label for="bf-wheelchairs"><?php esc_html_e( 'Wheelchairs', 'taxi-peninsula' ); ?></label>
				<input id="bf-wheelchairs" name="wheelchairs" type="number" inputmode="numeric" min="0" max="4" value="<?php echo esc_attr( $val( 'wheelchairs', '1' ) ); ?>">
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

	<?php $methods = tp_payment_methods(); ?>
	<?php if ( count( $methods ) > 1 ) : ?>
	<fieldset class="booking-form__group">
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

	<fieldset class="booking-form__group">
		<legend><span class="step"><?php echo count( $methods ) > 1 ? '4' : '3'; ?></span> <?php esc_html_e( 'Your details', 'taxi-peninsula' ); ?></legend>

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
			<label for="bf-consent"><?php esc_html_e( 'I agree to be contacted by phone, SMS or email about this booking.', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<?php $err( 'consent' ); ?>
		</div>
	</fieldset>

	<?php tp_spam_fields(); ?>

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
