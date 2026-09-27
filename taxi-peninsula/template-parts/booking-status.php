<?php
/**
 * Booking status for the customer, with change/cancel requests and payment.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { booking: array, errors: array, requested: bool, paid: string }
 */

$b         = $args['booking'];
$errors    = (array) ( $args['errors'] ?? array() );
$token     = tp_booking_token( $b['id'] );
$return    = ( is_singular() && ! is_front_page() ) ? get_permalink() : home_url( '/' );
$vehicles  = tp_vehicle_types();
$aids      = tp_mobility_aids();
$payments  = array_merge( array( 'driver' => __( 'Pay the driver', 'taxi-peninsula' ), 'account' => __( 'Account / invoice', 'taxi-peninsula' ), 'online' => __( 'Online deposit', 'taxi-peninsula' ) ), tp_payment_methods() );
$pickup_ts = strtotime( get_gmt_from_date( $b['date'] . ' ' . $b['time'] . ':00' ) . ' UTC' );
$is_open   = in_array( $b['status'], array( 'pending', 'confirmed', 'assigned' ), true ) && $pickup_ts > time();
$driver    = $b['driver_id'] ? get_userdata( $b['driver_id'] ) : null;
$series    = tp_series_ids( $b['series'] );

$steps = array(
	'pending'   => __( 'Received', 'taxi-peninsula' ),
	'confirmed' => __( 'Confirmed', 'taxi-peninsula' ),
	'assigned'  => __( 'Driver assigned', 'taxi-peninsula' ),
	'completed' => __( 'Completed', 'taxi-peninsula' ),
);
$order = array_keys( $steps );
$pos   = array_search( $b['status'], $order, true );
?>
<div class="booking-status card">
	<?php if ( ! empty( $args['requested'] ) ) : ?>
		<div class="notice notice--success" role="status" tabindex="-1" data-focus>
			<p><strong><?php esc_html_e( 'Thanks — we have received your request.', 'taxi-peninsula' ); ?></strong> <?php esc_html_e( 'Our team will contact you to confirm.', 'taxi-peninsula' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( '1' === ( $args['paid'] ?? '' ) ) : ?>
		<div class="notice notice--success" role="status" tabindex="-1" data-focus>
			<p><strong><?php esc_html_e( 'Payment complete — thank you.', 'taxi-peninsula' ); ?></strong> <?php esc_html_e( 'It can take a minute for the payment to show below.', 'taxi-peninsula' ); ?></p>
		</div>
	<?php elseif ( in_array( $args['paid'] ?? '', array( '0', 'error' ), true ) ) : ?>
		<div class="notice notice--error" role="alert"><p><?php esc_html_e( 'The payment was not completed. You can try again below or pay the driver.', 'taxi-peninsula' ); ?></p></div>
	<?php endif; ?>

	<div class="booking-status__head">
		<div>
			<p class="booking-status__ref"><?php esc_html_e( 'Booking', 'taxi-peninsula' ); ?> <strong><?php echo esc_html( $b['reference'] ); ?></strong></p>
			<h2 class="booking-status__when"><?php echo esc_html( tp_format_pickup( $b ) ); ?></h2>
		</div>
		<?php echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	</div>

	<?php if ( 'cancelled' === $b['status'] ) : ?>
		<p class="notice notice--error"><?php esc_html_e( 'This booking has been cancelled.', 'taxi-peninsula' ); ?></p>
	<?php else : ?>
		<ol class="progress" aria-label="<?php esc_attr_e( 'Booking progress', 'taxi-peninsula' ); ?>">
			<?php
			foreach ( $steps as $key => $label ) :
				$i     = array_search( $key, $order, true );
				$class = false !== $pos && $i < $pos ? 'is-done' : ( $i === $pos ? 'is-current' : '' );
				?>
				<li class="<?php echo esc_attr( $class ); ?>"<?php echo $i === $pos ? ' aria-current="step"' : ''; ?>><span><?php echo esc_html( $label ); ?></span></li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<dl class="details">
		<div><dt><?php esc_html_e( 'From', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $b['pickup'] ); ?></dd></div>
		<div><dt><?php esc_html_e( 'To', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $b['dropoff'] ); ?></dd></div>
		<?php if ( $b['return_trip'] ) : ?>
			<div><dt><?php esc_html_e( 'Return trip', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $b['return_time'] ?: __( 'Time to be arranged', 'taxi-peninsula' ) ); ?></dd></div>
		<?php endif; ?>
		<div><dt><?php esc_html_e( 'Vehicle', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $vehicles[ $b['vehicle'] ] ?? $b['vehicle'] ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Passengers', 'taxi-peninsula' ); ?></dt><dd>
			<?php
			/* translators: 1: passengers, 2: wheelchairs */
			echo esc_html( sprintf( __( '%1$d passenger(s), %2$d wheelchair(s)', 'taxi-peninsula' ), $b['passengers'], $b['wheelchairs'] ) );
			?>
			· <?php echo esc_html( $aids[ $b['mobility_aid'] ] ?? '' ); ?></dd></div>
		<?php if ( $driver ) : ?>
			<div><dt><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $driver->first_name ?: $driver->display_name ); ?><?php echo $b['fleet_id'] ? ' · ' . esc_html( tp_fleet_label( $b['fleet_id'] ) ) : ''; ?></dd></div>
		<?php endif; ?>
		<div><dt><?php esc_html_e( 'Payment', 'taxi-peninsula' ); ?></dt><dd>
			<?php echo esc_html( $payments[ $b['payment'] ] ?? $b['payment'] ); ?>
			<?php if ( $b['paid'] > 0 ) : ?>
				<span class="tp-badge tp-badge--completed">
					<?php
					/* translators: %s: amount */
					echo esc_html( sprintf( __( '%s paid', 'taxi-peninsula' ), tp_money( $b['paid'] ) ) );
					?>
				</span>
			<?php endif; ?>
		</dd></div>
		<?php if ( $b['notes'] ) : ?>
			<div><dt><?php esc_html_e( 'Your notes', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $b['notes'] ); ?></dd></div>
		<?php endif; ?>
	</dl>

	<?php if ( 'online' === $b['payment'] && $b['paid'] <= 0 && $is_open && tp_payments_available() ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="booking-status__pay">
			<input type="hidden" name="action" value="tp_pay">
			<input type="hidden" name="ref" value="<?php echo esc_attr( $b['reference'] ); ?>">
			<input type="hidden" name="t" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="_tp_return" value="<?php echo esc_url( $return ); ?>">
			<?php wp_nonce_field( 'tp_pay_' . $b['id'], '_tp_nonce', false ); ?>
			<button type="submit" class="btn btn--accent">
				<?php
				/* translators: %s: amount */
				echo esc_html( sprintf( __( 'Pay %s deposit securely', 'taxi-peninsula' ), tp_money( (float) tp_setting( 'deposit_amount' ) ) ) );
				?>
			</button>
		</form>
	<?php endif; ?>

	<?php if ( count( $series ) > 1 ) : ?>
		<details class="series">
			<summary>
				<?php
				/* translators: %d: number of trips */
				echo esc_html( sprintf( __( 'This is part of a repeat booking (%d trips)', 'taxi-peninsula' ), count( $series ) ) );
				?>
			</summary>
			<ul>
				<?php
				foreach ( $series as $sid ) :
					$s = tp_get_booking( $sid );
					?>
					<li<?php echo (int) $sid === (int) $b['id'] ? ' aria-current="true"' : ''; ?>>
						<a href="<?php echo esc_url( tp_booking_manage_url( $sid ) ); ?>"><?php echo esc_html( tp_format_pickup( $s ) ); ?></a>
						<?php echo tp_status_badge( $s['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php endif; ?>

	<?php if ( $is_open ) : ?>
		<?php if ( ! empty( $b['request'] ) && is_array( $b['request'] ) ) : ?>
			<p class="notice notice--info">
				<?php
				$types = tp_request_types();
				/* translators: %s: request type */
				echo esc_html( sprintf( __( 'Your %s is with our team. We will contact you shortly.', 'taxi-peninsula' ), strtolower( $types[ $b['request']['type'] ] ?? '' ) ) );
				?>
			</p>
		<?php endif; ?>

		<details class="request"<?php echo ! empty( $errors['request'] ) ? ' open' : ''; ?>>
			<summary class="btn btn--ghost"><?php esc_html_e( 'Change or cancel this booking', 'taxi-peninsula' ); ?></summary>
			<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_booking_request">
				<input type="hidden" name="ref" value="<?php echo esc_attr( $b['reference'] ); ?>">
				<input type="hidden" name="t" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="_tp_return" value="<?php echo esc_url( $return ); ?>">
				<?php wp_nonce_field( 'tp_request_' . $b['id'], '_tp_nonce', false ); ?>

				<?php if ( ! empty( $errors['request'] ) ) : ?>
					<p class="field__error" role="alert"><?php echo esc_html( $errors['request'] ); ?></p>
				<?php endif; ?>

				<fieldset class="radio-list">
					<legend class="field__label"><?php esc_html_e( 'What would you like to do?', 'taxi-peninsula' ); ?></legend>
					<label class="radio-row"><input type="radio" name="request_type" value="change" checked> <span><?php esc_html_e( 'Change the time, address or details', 'taxi-peninsula' ); ?></span></label>
					<label class="radio-row"><input type="radio" name="request_type" value="cancel"> <span><?php esc_html_e( 'Cancel this trip', 'taxi-peninsula' ); ?></span></label>
					<?php if ( count( $series ) > 1 ) : ?>
						<label class="radio-row"><input type="radio" name="request_type" value="cancel_series"> <span><?php esc_html_e( 'Cancel all remaining trips in this repeat booking', 'taxi-peninsula' ); ?></span></label>
					<?php endif; ?>
				</fieldset>
				<div class="field">
					<label for="rq-message"><?php esc_html_e( 'Details', 'taxi-peninsula' ); ?></label>
					<textarea id="rq-message" name="request_message" rows="3" placeholder="<?php esc_attr_e( 'e.g. Please change the pick-up to 10:15am', 'taxi-peninsula' ); ?>"></textarea>
				</div>
				<p class="field__hint"><?php esc_html_e( 'Requests are handled by our team. For changes within the next two hours, please call us.', 'taxi-peninsula' ); ?></p>
				<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Send request', 'taxi-peninsula' ); ?></button>
			</form>
		</details>
	<?php endif; ?>

	<p class="booking-status__call">
		<?php
		printf(
			/* translators: %s: phone link */
			esc_html__( 'Questions about your trip? Call %s.', 'taxi-peninsula' ),
			'<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
		);
		?>
	</p>
</div>
