<?php
/**
 * Hero quick-book bar: From, To and date, carried into the full booking form.
 *
 * @package TaxiPeninsula
 */

$book   = tp_booking_page_url();
$action = strtok( $book, '#' );
$places = tp_places_available();
if ( $places ) {
	tp_booking_script_config();
}
?>
<form class="quickbook" method="get" action="<?php echo esc_url( $action ); ?>" aria-label="<?php esc_attr_e( 'Start a booking', 'taxi-peninsula' ); ?>" data-quickbook>
	<div class="quickbook__field">
		<label for="qb-from"><?php esc_html_e( 'From', 'taxi-peninsula' ); ?></label>
		<input id="qb-from" name="pickup" type="text" autocomplete="street-address" placeholder="<?php esc_attr_e( 'Pick-up address', 'taxi-peninsula' ); ?>" <?php echo $places ? 'data-places' : ''; ?>>
	</div>
	<div class="quickbook__field">
		<label for="qb-to"><?php esc_html_e( 'To', 'taxi-peninsula' ); ?></label>
		<input id="qb-to" name="dropoff" type="text" placeholder="<?php esc_attr_e( 'Where to?', 'taxi-peninsula' ); ?>" <?php echo $places ? 'data-places' : ''; ?>>
	</div>
	<div class="quickbook__field quickbook__field--date">
		<label for="qb-date"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></label>
		<input id="qb-date" name="date" type="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>">
	</div>
	<button type="submit" class="btn btn--accent quickbook__go"><?php esc_html_e( 'Continue', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></button>
</form>
