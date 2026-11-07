<?php
/**
 * Quick price from two addresses, with "Text me this quote" (inc/lead-tools.php).
 *
 * Args: heading (bool, default true).
 *
 * @package TaxiPeninsula
 */

if ( ! function_exists( 'tp_text_quote_available' ) || ! tp_text_quote_available() ) {
	return;
}
tp_booking_script_config();
$tq_id  = wp_unique_id( 'tq-' );
$tq_sms = tp_sms_enabled();
?>
<div class="text-quote card" data-text-quote data-url="<?php echo esc_url( rest_url( 'taxi-peninsula/v1/text-quote' ) ); ?>" data-book="<?php echo esc_url( tp_booking_page_url() ); ?>">
	<?php if ( ! isset( $args['heading'] ) || $args['heading'] ) : ?>
		<h2 class="text-quote__title"><?php esc_html_e( 'What will my trip cost?', 'taxi-peninsula' ); ?></h2>
	<?php endif; ?>
	<form class="text-quote__ask" data-tq-ask novalidate>
		<div class="field">
			<label for="<?php echo esc_attr( $tq_id ); ?>-from"><?php esc_html_e( 'From', 'taxi-peninsula' ); ?></label>
			<input id="<?php echo esc_attr( $tq_id ); ?>-from" name="from" type="text" required autocomplete="street-address" <?php echo tp_places_available() ? 'data-places' : ''; ?> placeholder="<?php esc_attr_e( 'Pick-up address', 'taxi-peninsula' ); ?>">
		</div>
		<div class="field">
			<label for="<?php echo esc_attr( $tq_id ); ?>-to"><?php esc_html_e( 'To', 'taxi-peninsula' ); ?></label>
			<input id="<?php echo esc_attr( $tq_id ); ?>-to" name="to" type="text" required <?php echo tp_places_available() ? 'data-places' : ''; ?> placeholder="<?php esc_attr_e( 'e.g. Frankston Hospital', 'taxi-peninsula' ); ?>">
		</div>
		<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Get a price', 'taxi-peninsula' ); ?></button>
	</form>
	<div class="text-quote__result" data-tq-result hidden tabindex="-1">
		<p class="text-quote__price" data-tq-price aria-live="polite"></p>
		<p class="text-quote__note"><?php esc_html_e( 'An estimate for a wheelchair accessible taxi on the usual route. Traffic and waiting can change the final fare.', 'taxi-peninsula' ); ?></p>
		<p class="text-quote__actions"><a class="btn btn--accent" href="#" data-tq-book><?php esc_html_e( 'Book this trip', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a></p>
		<?php if ( $tq_sms ) : ?>
			<form class="text-quote__send" data-tq-send novalidate>
				<div class="field">
					<label for="<?php echo esc_attr( $tq_id ); ?>-phone"><?php esc_html_e( 'Not ready to book? Text me this quote', 'taxi-peninsula' ); ?></label>
					<div class="text-quote__row">
						<input id="<?php echo esc_attr( $tq_id ); ?>-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="04xx xxx xxx">
						<button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Text me', 'taxi-peninsula' ); ?></button>
					</div>
				</div>
				<p class="hp" aria-hidden="true"><label><?php esc_html_e( 'Leave empty', 'taxi-peninsula' ); ?> <input type="text" name="hp" tabindex="-1" autocomplete="off"></label></p>
				<p class="text-quote__small"><?php esc_html_e( 'One text with the price and a booking link. We may call once to help you book.', 'taxi-peninsula' ); ?></p>
			</form>
		<?php endif; ?>
		<p class="text-quote__status" data-tq-status role="status" aria-live="polite"></p>
	</div>
	<p class="text-quote__error notice notice--error" data-tq-error hidden role="alert"></p>
</div>
