<?php
/**
 * Title: Call to action (yellow band)
 * Slug: taxi-peninsula/cta
 * Categories: taxi-peninsula
 * Keywords: call to action, cta, phone, book
 * Description: Bright band with phone and booking buttons.
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:group {"align":"full","className":"tp-cta-block","backgroundColor":"accent","textColor":"primary","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull tp-cta-block has-primary-color has-text-color has-accent-background-color has-background"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Need a wheelchair taxi today?', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Call our friendly team and we will get you moving.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a></div>
<!-- /wp:button -->
<!-- wp:button {"backgroundColor":"primary","textColor":"white"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background wp-element-button" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
