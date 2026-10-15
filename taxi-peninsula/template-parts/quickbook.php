<?php
/**
 * Quick-book card: From, To, date, time and wheelchair — carried into the
 * full booking form on the Book a Taxi page.
 *
 * @package TaxiPeninsula
 *
 * @var array $args { context: hero|section }
 */

$book    = tp_booking_page_url();
$action  = strtok( $book, '#' );
$places  = tp_places_available();
$context = $args['context'] ?? 'hero';
$uid     = 'qb-' . sanitize_key( $context );
if ( $places ) {
	tp_booking_script_config();
}
?>
<form class="quickbook quickbook--<?php echo esc_attr( $context ); ?>" method="get" action="<?php echo esc_url( $action ); ?>" aria-label="<?php esc_attr_e( 'Start a booking', 'taxi-peninsula' ); ?>" data-quickbook>
	<div class="quickbook__field quickbook__field--from">
		<label for="<?php echo esc_attr( $uid ); ?>-from"><?php esc_html_e( 'From', 'taxi-peninsula' ); ?></label>
		<input id="<?php echo esc_attr( $uid ); ?>-from" name="pickup" type="text" autocomplete="street-address" placeholder="<?php esc_attr_e( 'Pick-up address', 'taxi-peninsula' ); ?>" <?php echo $places ? 'data-places' : ''; ?>>
	</div>
	<div class="quickbook__field quickbook__field--to">
		<label for="<?php echo esc_attr( $uid ); ?>-to"><?php esc_html_e( 'To', 'taxi-peninsula' ); ?></label>
		<input id="<?php echo esc_attr( $uid ); ?>-to" name="dropoff" type="text" placeholder="<?php esc_attr_e( 'Where to?', 'taxi-peninsula' ); ?>" <?php echo $places ? 'data-places' : ''; ?>>
	</div>
	<div class="quickbook__field quickbook__field--date">
		<label for="<?php echo esc_attr( $uid ); ?>-date"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></label>
		<input id="<?php echo esc_attr( $uid ); ?>-date" name="date" type="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>">
	</div>
	<div class="quickbook__field quickbook__field--time">
		<label for="<?php echo esc_attr( $uid ); ?>-time"><?php esc_html_e( 'Pick-up time', 'taxi-peninsula' ); ?></label>
		<input id="<?php echo esc_attr( $uid ); ?>-time" name="time" type="time" step="300">
	</div>
	<div class="quickbook__field quickbook__field--chair">
		<label for="<?php echo esc_attr( $uid ); ?>-chair"><?php esc_html_e( 'Wheelchair?', 'taxi-peninsula' ); ?></label>
		<select id="<?php echo esc_attr( $uid ); ?>-chair" name="wheelchairs">
			<option value="1"><?php esc_html_e( '1 wheelchair', 'taxi-peninsula' ); ?></option>
			<option value="2"><?php esc_html_e( '2 wheelchairs', 'taxi-peninsula' ); ?></option>
			<option value="0"><?php esc_html_e( 'No wheelchair', 'taxi-peninsula' ); ?></option>
		</select>
	</div>
	<button type="submit" class="btn btn--accent quickbook__go"><?php esc_html_e( 'Continue', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></button>
</form>
