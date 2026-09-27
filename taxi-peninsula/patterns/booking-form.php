<?php
/**
 * Title: Booking form
 * Slug: taxi-peninsula/booking-form
 * Categories: taxi-peninsula
 * Keywords: booking, form, book
 * Description: The full online booking form.
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:group {"align":"full","className":"tp-section-block","backgroundColor":"surface-alt","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull tp-section-block has-surface-alt-background-color has-background"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Book your accessible taxi', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:shortcode -->
[tp_booking_form]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
