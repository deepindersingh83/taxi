<?php
/**
 * Service page: what to expect, typical fares and questions.
 *
 * @package TaxiPeninsula
 */

$id     = isset( $args['id'] ) ? (int) $args['id'] : get_the_ID();
$expect = array_values( array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $id, '_tp_expect', true ) ) ) ) );
if ( ! $expect ) {
	$expect = array(
		__( 'Book online or call us — tell us about your wheelchair and anything the driver should know.', 'taxi-peninsula' ),
		__( 'We confirm by SMS or email, then text you your driver’s name and vehicle.', 'taxi-peninsula' ),
		__( 'On the day we text you when the driver is on the way and when they arrive.', 'taxi-peninsula' ),
		__( 'Your driver lowers the ramp, helps you aboard and secures your chair with four-point restraints.', 'taxi-peninsula' ),
		__( 'At the other end we help you to the door, reception or check-in.', 'taxi-peninsula' ),
	);
}
$price = (float) get_post_meta( $id, '_tp_from_price', true );
$note  = trim( (string) get_post_meta( $id, '_tp_fare_note', true ) );
$faq   = tp_parse_local_faq( get_post_meta( $id, '_tp_faq', true ) );
?>
<section class="service-detail" aria-labelledby="expect-title">
	<h2 id="expect-title"><?php esc_html_e( 'What to expect', 'taxi-peninsula' ); ?></h2>
	<ol class="timeline">
		<?php foreach ( $expect as $i => $step ) : ?>
			<li class="timeline__step"><span class="timeline__dot" aria-hidden="true"><?php echo esc_html( $i + 1 ); ?></span><p><?php echo esc_html( $step ); ?></p></li>
		<?php endforeach; ?>
	</ol>
</section>

<?php if ( $price || $note ) : ?>
	<section class="service-detail fare-card" aria-labelledby="fares-title">
		<h2 id="fares-title"><?php esc_html_e( 'Typical fares', 'taxi-peninsula' ); ?></h2>
		<?php if ( $price ) : ?>
			<?php /* translators: %s: price */ ?>
			<p class="fare-card__from"><?php echo esc_html( sprintf( __( 'From %s', 'taxi-peninsula' ), '$' . number_format_i18n( $price, floor( $price ) == $price ? 0 : 2 ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( $note ) : ?>
			<?php echo wp_kses_post( wpautop( esc_html( $note ) ) ); ?>
		<?php endif; ?>
		<p class="fare-card__small"><?php esc_html_e( 'Fares depend on distance, time of day and waiting time. Ask us for a quote before you book.', 'taxi-peninsula' ); ?></p>
	</section>
<?php endif; ?>

<?php if ( $faq ) : ?>
	<section class="service-detail" aria-labelledby="sfaq-title">
		<h2 id="sfaq-title"><?php esc_html_e( 'Common questions', 'taxi-peninsula' ); ?></h2>
		<div class="faq">
			<?php foreach ( $faq as $qa ) : ?>
				<?php tp_faq_schema_items( array( 'q' => $qa[0], 'a' => $qa[1] ) ); ?>
				<details class="faq__item"><summary><?php echo esc_html( $qa[0] ); ?></summary><div class="faq__answer prose"><?php echo wp_kses_post( wpautop( esc_html( $qa[1] ) ) ); ?></div></details>
			<?php endforeach; ?>
		</div>
	</section>
<?php else : ?>
	<?php $more = do_shortcode( '[tp_faq featured="yes" limit="4" search="no"]' ); ?>
	<?php if ( $more ) : ?>
		<section class="service-detail" aria-labelledby="sfaq-title">
			<h2 id="sfaq-title"><?php esc_html_e( 'Common questions', 'taxi-peninsula' ); ?></h2>
			<?php echo $more; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in shortcode. ?>
		</section>
	<?php endif; ?>
<?php endif; ?>
<?php do_action( 'tp_service_details_after', $id ); ?>
