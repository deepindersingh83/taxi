<?php
/**
 * Shown after a booking request is received: what happens next, calendar,
 * view booking, book the return trip.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { reference: string, token: string, count: int, pay_error: bool }
 */

$ref       = isset( $args['reference'] ) ? $args['reference'] : '';
$count     = isset( $args['count'] ) ? max( 1, (int) $args['count'] ) : 1;
$pay_error = ! empty( $args['pay_error'] );
$post_id   = $ref ? tp_find_booking( $ref ) : 0;
$valid     = $post_id && ! empty( $args['token'] ) && hash_equals( tp_booking_token( $post_id ), $args['token'] );
$b         = $valid ? tp_get_booking( $post_id ) : null;
$lookup    = tp_page_url_by_template( 'lookup' );
$account   = tp_page_url_by_template( 'account' );
?>
<div class="booking-success" role="status" tabindex="-1" data-focus>
	<?php echo tp_illustration( 'success', 'booking-success__illo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
	<h2 class="booking-success__title"><?php esc_html_e( 'Thanks — your booking request is in!', 'taxi-peninsula' ); ?></h2>
	<?php if ( $ref ) : ?>
		<p class="booking-success__ref"><?php esc_html_e( 'Your reference', 'taxi-peninsula' ); ?> <strong><?php echo esc_html( $ref ); ?></strong></p>
	<?php endif; ?>

	<?php if ( $b ) : ?>
		<div class="booking-success__trip">
			<p class="booking-success__when"><?php echo esc_html( tp_format_pickup( $b ) ); ?></p>
			<p><?php echo esc_html( $b['pickup'] ); ?> <span aria-hidden="true">→</span><span class="screen-reader-text"><?php esc_html_e( 'to', 'taxi-peninsula' ); ?></span> <?php echo esc_html( $b['dropoff'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $count > 1 ) : ?>
		<p class="booking-success__series">
			<?php
			/* translators: %d: number of trips */
			echo esc_html( sprintf( _n( '%d trip has been requested.', '%d trips have been requested.', $count, 'taxi-peninsula' ), $count ) );
			?>
			<?php esc_html_e( 'Each trip has its own reference, listed in your confirmation email.', 'taxi-peninsula' ); ?>
		</p>
	<?php endif; ?>
	<?php if ( $pay_error ) : ?>
		<p class="notice notice--error"><?php esc_html_e( 'We saved your booking but could not open the online payment page. You can pay the driver instead, or try again from your booking page.', 'taxi-peninsula' ); ?></p>
	<?php endif; ?>

	<h3 class="booking-success__next"><?php esc_html_e( 'What happens next', 'taxi-peninsula' ); ?></h3>
	<ol class="next-steps">
		<li>
			<span class="next-steps__icon"><?php tp_the_icon( 'check' ); ?></span>
			<span><strong><?php esc_html_e( 'We confirm your booking', 'taxi-peninsula' ); ?></strong><br><?php esc_html_e( 'By SMS or email — usually within the hour during opening times.', 'taxi-peninsula' ); ?></span>
		</li>
		<li>
			<span class="next-steps__icon"><?php tp_the_icon( 'users' ); ?></span>
			<span><strong><?php esc_html_e( 'Your driver is assigned', 'taxi-peninsula' ); ?></strong><br><?php esc_html_e( 'We text you their first name and the vehicle.', 'taxi-peninsula' ); ?></span>
		</li>
		<li>
			<span class="next-steps__icon"><?php tp_the_icon( 'phone' ); ?></span>
			<span><strong><?php esc_html_e( 'On the day', 'taxi-peninsula' ); ?></strong><br><?php esc_html_e( 'You get a text when your driver is on the way and when they arrive.', 'taxi-peninsula' ); ?></span>
		</li>
	</ol>

	<p class="print-only print-brand"><?php echo esc_html( get_bloginfo( 'name' ) . ' · ' . tp_opt( 'phone_display' ) ); ?></p>
	<div class="booking-success__actions">
		<button type="button" class="btn btn--ghost" data-print><?php tp_the_icon( 'book' ); ?> <?php esc_html_e( 'Print', 'taxi-peninsula' ); ?></button>
		<?php if ( $b ) : ?>
			<a class="btn btn--primary" href="<?php echo esc_url( tp_booking_ics_url( $post_id ) ); ?>" download><?php tp_the_icon( 'calendar' ); ?> <?php esc_html_e( 'Add to calendar', 'taxi-peninsula' ); ?></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( tp_booking_manage_url( $post_id ) ); ?>"><?php esc_html_e( 'View booking', 'taxi-peninsula' ); ?></a>
			<?php
			$return_url = tp_user_owns_booking( $post_id )
				? add_query_arg( array( 'rebook' => $post_id, 'swap' => 1 ), tp_booking_page_url() )
				: add_query_arg( array( 'pickup' => $b['dropoff'], 'dropoff' => $b['pickup'], 'date' => $b['date'] ), tp_booking_page_url() );
			?>
			<?php if ( ! $b['return_trip'] ) : ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( $return_url . '#book' ); ?>"><?php esc_html_e( 'Book the return trip', 'taxi-peninsula' ); ?></a>
			<?php endif; ?>
		<?php elseif ( $lookup ) : ?>
			<a class="btn btn--ghost" href="<?php echo esc_url( $lookup ); ?>"><?php esc_html_e( 'Manage my booking', 'taxi-peninsula' ); ?></a>
		<?php endif; ?>
		<a class="btn btn--ghost" href="<?php echo esc_url( remove_query_arg( array( 'booking', 'ref', 't', 'trips' ) ) ); ?>#book"><?php esc_html_e( 'Make another booking', 'taxi-peninsula' ); ?></a>
	</div>

	<?php if ( tp_is_account_user() ) : ?>
		<p><a href="<?php echo esc_url( tp_account_url() ); ?>"><?php esc_html_e( 'See all your trips in My Account', 'taxi-peninsula' ); ?></a></p>
	<?php elseif ( $account && ! is_user_logged_in() ) : ?>
		<p class="booking-success__account">
			<?php
			printf(
				/* translators: %s: link */
				esc_html__( 'Book often? %s to see every trip in one place and rebook in one tap.', 'taxi-peninsula' ),
				'<a href="' . esc_url( $account ) . '">' . esc_html__( 'Create a free account', 'taxi-peninsula' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<p class="booking-success__call">
		<?php
		printf(
			/* translators: %s: phone link */
			esc_html__( 'Need to change something? Call %s and quote your reference.', 'taxi-peninsula' ),
			'<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
		);
		?>
	</p>
</div>
