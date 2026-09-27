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

	<div class="hp" aria-hidden="true">
		<label for="tp_website">Website</label>
		<input type="text" id="tp_website" name="tp_website" tabindex="-1" autocomplete="off">
	</div>

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

	<fieldset class="booking-form__group">
		<legend><span class="step">3</span> <?php esc_html_e( 'Your details', 'taxi-peninsula' ); ?></legend>

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
