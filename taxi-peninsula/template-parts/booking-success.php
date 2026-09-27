<?php
/**
 * Shown after a booking request is received.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { reference: string }
 */

$ref       = isset( $args['reference'] ) ? $args['reference'] : '';
$count     = isset( $args['count'] ) ? max( 1, (int) $args['count'] ) : 1;
$pay_error = ! empty( $args['pay_error'] );
$lookup    = tp_page_url_by_template( 'lookup' );
?>
<div class="booking-success" role="status" tabindex="-1" data-focus>
	<span class="booking-success__icon"><?php tp_the_icon( 'check' ); ?></span>
	<h2><?php esc_html_e( 'Booking request received', 'taxi-peninsula' ); ?></h2>
	<?php if ( $ref ) : ?>
		<p class="booking-success__ref"><?php esc_html_e( 'Your reference', 'taxi-peninsula' ); ?> <strong><?php echo esc_html( $ref ); ?></strong></p>
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
	<p><?php esc_html_e( 'We will confirm your booking by phone, SMS or email shortly. A copy of your request has been sent to your inbox.', 'taxi-peninsula' ); ?></p>
	<?php if ( $lookup ) : ?>
		<p><?php printf( /* translators: %s: link */ esc_html__( 'You can check its status any time on the %s page.', 'taxi-peninsula' ), '<a href="' . esc_url( $lookup ) . '">' . esc_html__( 'Manage my booking', 'taxi-peninsula' ) . '</a>' ); ?></p>
	<?php endif; ?>
	<p>
		<?php
		printf(
			/* translators: %s: phone link */
			esc_html__( 'Need to change something? Call %s and quote your reference.', 'taxi-peninsula' ),
			'<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
		);
		?>
	</p>
	<a class="btn btn--primary" href="<?php echo esc_url( remove_query_arg( array( 'booking', 'ref' ) ) ); ?>#book"><?php esc_html_e( 'Make another booking', 'taxi-peninsula' ); ?></a>
</div>
