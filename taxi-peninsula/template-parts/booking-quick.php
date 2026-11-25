<?php
/**
 * Quick booking form (one screen). See inc/quick-form.php.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { errors, old, atts }
 */

$errors = $args['errors'] ?? array();
$old    = $args['old'] ?? array();
$atts   = $args['atts'] ?? array();
$val    = static fn( $k, $d = '' ) => isset( $old[ $k ] ) ? $old[ $k ] : $d;
$err    = static function ( $k ) use ( $errors ) {
	if ( ! empty( $errors[ $k ] ) ) {
		printf( '<p class="field__error" id="qf-err-%1$s">%2$s</p>', esc_attr( $k ), esc_html( $errors[ $k ] ) );
	}
};
$inv = static function ( $k ) use ( $errors ) {
	if ( ! empty( $errors[ $k ] ) ) {
		printf( ' aria-invalid="true" aria-describedby="qf-err-%s"', esc_attr( $k ) );
	}
};
$req = static fn( $show ) => 'required' === $show ? ' required' : '';
$star = static function ( $show ) {
	if ( 'required' === $show ) {
		echo ' <span class="req" aria-hidden="true">*</span>';
	}
};

$heading = null !== ( $atts['heading'] ?? null ) ? $atts['heading'] : tp_setting( 'qf_heading' );
$intro   = null !== ( $atts['intro'] ?? null ) ? $atts['intro'] : tp_setting( 'qf_intro' );
$button  = null !== ( $atts['button'] ?? null ) && '' !== $atts['button'] ? $atts['button'] : ( tp_setting( 'qf_button' ) ?: __( 'Book now', 'taxi-peninsula' ) );
$places  = tp_places_available();
$today   = wp_date( 'Y-m-d' );
$here    = ( is_singular() && ! is_front_page() ) ? get_permalink() : home_url( '/' );
$terms   = tp_terms_url();
$chairs  = (int) $val( 'wheelchairs', -1 );
$chair_v = $val( 'chair', $chairs > 0 ? 'yes' : ( 0 === $chairs ? 'no' : '' ) );
tp_booking_script_config();
static $tp_qf_n = 0;
$id = 'qf' . ( ++$tp_qf_n );
?>
<form class="quick-form card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="<?php echo esc_attr( $id ); ?>" novalidate>
	<?php if ( '' !== trim( (string) $heading ) ) : ?>
		<h2 class="quick-form__title"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>
	<?php if ( '' !== trim( (string) $intro ) ) : ?>
		<p class="quick-form__intro"><?php echo esc_html( $intro ); ?></p>
	<?php endif; ?>

	<input type="hidden" name="action" value="tp_booking">
	<input type="hidden" name="tp_quick" value="1">
	<input type="hidden" name="_tp_return" value="<?php echo esc_url( $here ); ?>">
	<?php wp_nonce_field( 'tp_booking', '_tp_nonce', false ); ?>
	<?php do_action( 'tp_booking_form_hidden', $old ); ?>

	<?php if ( $errors ) : ?>
		<div class="notice notice--error" role="alert" tabindex="-1" data-focus>
			<p><strong><?php esc_html_e( 'Please check the highlighted fields.', 'taxi-peninsula' ); ?></strong></p>
			<?php if ( ! empty( $errors['form'] ) ) : ?>
				<p><?php echo esc_html( $errors['form'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="quick-form__grid">
		<div class="field field--wide">
			<label for="<?php echo esc_attr( $id ); ?>-pickup"><?php esc_html_e( 'Pick-up address', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="<?php echo esc_attr( $id ); ?>-pickup" name="pickup" type="text" required autocomplete="street-address" <?php echo $places ? 'data-places' : ''; ?> value="<?php echo esc_attr( $val( 'pickup' ) ); ?>"<?php $inv( 'pickup' ); ?>>
			<?php $err( 'pickup' ); ?>
		</div>
		<div class="field field--wide">
			<label for="<?php echo esc_attr( $id ); ?>-dropoff"><?php esc_html_e( 'Drop-off address', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="<?php echo esc_attr( $id ); ?>-dropoff" name="dropoff" type="text" required <?php echo $places ? 'data-places' : ''; ?> value="<?php echo esc_attr( $val( 'dropoff' ) ); ?>"<?php $inv( 'dropoff' ); ?>>
			<?php $err( 'dropoff' ); ?>
		</div>
		<div class="field">
			<label for="<?php echo esc_attr( $id ); ?>-date"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="<?php echo esc_attr( $id ); ?>-date" name="date" type="date" required min="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( tp_booking_max_date() ); ?>" value="<?php echo esc_attr( $val( 'date', $today ) ); ?>"<?php $inv( 'date' ); ?>>
			<?php $err( 'date' ); ?>
		</div>
		<div class="field">
			<label for="<?php echo esc_attr( $id ); ?>-time"><?php esc_html_e( 'Pick-up time', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="<?php echo esc_attr( $id ); ?>-time" name="time" type="time" required value="<?php echo esc_attr( $val( 'time' ) ); ?>"<?php $inv( 'time' ); ?>>
			<?php $err( 'time' ); ?>
		</div>

		<?php if ( 'hide' !== tp_qf_show( 'return' ) ) : ?>
			<div class="field">
				<label for="<?php echo esc_attr( $id ); ?>-return"><?php esc_html_e( 'Return pick-up time', 'taxi-peninsula' ); ?><?php $star( tp_qf_show( 'return' ) ); ?></label>
				<input id="<?php echo esc_attr( $id ); ?>-return" name="return_time" type="time" value="<?php echo esc_attr( $val( 'return_time' ) ); ?>"<?php echo esc_attr( $req( tp_qf_show( 'return' ) ) ); ?><?php $inv( 'return_time' ); ?>>
				<?php $err( 'return_time' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( tp_setting( 'qf_chair' ) ) : ?>
			<fieldset class="field field--wide chair-q">
				<legend><?php esc_html_e( 'Is anyone travelling in a wheelchair or scooter?', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></legend>
				<div class="chair-q__options">
					<label class="chair-q__option"><input type="radio" name="chair" value="yes" required <?php checked( 'yes', $chair_v ); ?>> <span><?php tp_the_icon( 'wheelchair' ); ?> <?php esc_html_e( 'Yes', 'taxi-peninsula' ); ?></span></label>
					<label class="chair-q__option"><input type="radio" name="chair" value="no" <?php checked( 'no', $chair_v ); ?>> <span><?php esc_html_e( 'No', 'taxi-peninsula' ); ?></span></label>
				</div>
				<?php $err( 'chair' ); ?>
				<?php $err( 'vehicle' ); ?>
			</fieldset>
		<?php endif; ?>

		<?php if ( 'hide' !== tp_qf_show( 'passengers' ) ) : ?>
			<div class="field">
				<label for="<?php echo esc_attr( $id ); ?>-passengers"><?php esc_html_e( 'Passengers', 'taxi-peninsula' ); ?><?php $star( tp_qf_show( 'passengers' ) ); ?></label>
				<input id="<?php echo esc_attr( $id ); ?>-passengers" name="passengers" type="number" min="1" max="12" inputmode="numeric" value="<?php echo esc_attr( $val( 'passengers', 1 ) ); ?>"<?php echo esc_attr( $req( tp_qf_show( 'passengers' ) ) ); ?><?php $inv( 'passengers' ); ?>>
				<?php $err( 'passengers' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( 'hide' !== tp_qf_show( 'flight' ) ) : ?>
			<div class="field">
				<label for="<?php echo esc_attr( $id ); ?>-flight"><?php esc_html_e( 'Flight number', 'taxi-peninsula' ); ?><?php $star( tp_qf_show( 'flight' ) ); ?></label>
				<input id="<?php echo esc_attr( $id ); ?>-flight" name="flight" type="text" autocapitalize="characters" placeholder="QF123" value="<?php echo esc_attr( $val( 'flight' ) ); ?>"<?php echo esc_attr( $req( tp_qf_show( 'flight' ) ) ); ?><?php $inv( 'flight' ); ?>>
				<?php $err( 'flight' ); ?>
			</div>
		<?php endif; ?>

		<div class="field">
			<label for="<?php echo esc_attr( $id ); ?>-name"><?php esc_html_e( 'Full name', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="<?php echo esc_attr( $id ); ?>-name" name="name" type="text" required autocomplete="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>"<?php $inv( 'name' ); ?>>
			<?php $err( 'name' ); ?>
		</div>
		<div class="field">
			<label for="<?php echo esc_attr( $id ); ?>-phone"><?php esc_html_e( 'Mobile', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
			<input id="<?php echo esc_attr( $id ); ?>-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" value="<?php echo esc_attr( $val( 'phone' ) ); ?>"<?php $inv( 'phone' ); ?>>
			<?php $err( 'phone' ); ?>
		</div>

		<?php if ( 'hide' !== tp_qf_show( 'email' ) ) : ?>
			<div class="field field--wide">
				<label for="<?php echo esc_attr( $id ); ?>-email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?><?php $star( tp_qf_show( 'email' ) ); ?></label>
				<input id="<?php echo esc_attr( $id ); ?>-email" name="email" type="email" autocomplete="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>"<?php echo esc_attr( $req( tp_qf_show( 'email' ) ) ); ?><?php $inv( 'email' ); ?>>
				<?php $err( 'email' ); ?>
			</div>
		<?php endif; ?>

		<?php foreach ( tp_qf_questions() as $n => $q ) : ?>
			<div class="field field--wide">
				<label for="<?php echo esc_attr( $id . '-q' . $n ); ?>"><?php echo esc_html( $q[0] ); ?><?php $star( $q[1] ? 'required' : '' ); ?></label>
				<input id="<?php echo esc_attr( $id . '-q' . $n ); ?>" name="qf_q<?php echo (int) $n; ?>" type="text" value="<?php echo esc_attr( $val( 'qf_q' . $n ) ); ?>"<?php echo $q[1] ? ' required' : ''; ?><?php $inv( 'qf_q' . $n ); ?>>
				<?php $err( 'qf_q' . $n ); ?>
			</div>
		<?php endforeach; ?>

		<?php if ( 'hide' !== tp_qf_show( 'notes' ) ) : ?>
			<div class="field field--wide">
				<label for="<?php echo esc_attr( $id ); ?>-notes"><?php echo esc_html( tp_setting( 'qf_notes_label' ) ?: __( 'Anything we should know?', 'taxi-peninsula' ) ); ?><?php $star( tp_qf_show( 'notes' ) ); ?></label>
				<textarea id="<?php echo esc_attr( $id ); ?>-notes" name="notes" rows="2"<?php echo esc_attr( $req( tp_qf_show( 'notes' ) ) ); ?><?php $inv( 'notes' ); ?>><?php echo esc_textarea( $val( 'notes' ) ); ?></textarea>
				<?php $err( 'notes' ); ?>
			</div>
		<?php endif; ?>

		<div class="field field--wide field--check">
			<input id="<?php echo esc_attr( $id ); ?>-agree" name="agree" type="checkbox" value="1" required <?php checked( ! empty( $old['agree'] ) ); ?><?php $inv( 'agree' ); ?>>
			<label for="<?php echo esc_attr( $id ); ?>-agree">
				<?php if ( $terms ) : ?>
					<?php
					printf(
						/* translators: %s: link to the terms */
						esc_html__( 'I accept the %s and agree to be contacted about this booking.', 'taxi-peninsula' ),
						'<a href="' . esc_url( $terms ) . '" target="_blank" rel="noopener">' . esc_html__( 'terms and cancellation policy', 'taxi-peninsula' ) . '</a>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'I agree to be contacted about this booking.', 'taxi-peninsula' ); ?>
				<?php endif; ?>
				<span class="req" aria-hidden="true">*</span>
			</label>
			<?php $err( 'agree' ); ?>
		</div>
	</div>

	<?php tp_spam_fields(); ?>

	<div class="quick-form__submit">
		<button type="submit" class="btn btn--accent btn--lg"><?php echo esc_html( $button ); ?> <?php tp_the_icon( 'arrow' ); ?></button>
		<p class="quick-form__alt">
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
