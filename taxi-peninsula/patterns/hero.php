<?php
/**
 * Title: Hero banner
 * Slug: taxi-peninsula/hero
 * Categories: taxi-peninsula
 * Keywords: hero, banner, header, landing
 * Description: Large navy banner with headline, intro and booking buttons. Use at the top of a "Blank canvas" page.
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:group {"align":"full","className":"tp-hero-block","backgroundColor":"primary","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull tp-hero-block has-white-color has-text-color has-primary-background-color has-background"><!-- wp:paragraph {"className":"tp-eyebrow"} -->
<p class="tp-eyebrow"><?php esc_html_e( 'Wheelchair accessible taxis · Melbourne', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php esc_html_e( 'Accessible rides you can count on.', 'taxi-peninsula' ); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"tp-lead"} -->
<p class="tp-lead"><?php esc_html_e( 'Ramp and hoist equipped vehicles, trained drivers and door-to-door help across Melbourne and the Mornington Peninsula.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"accent","textColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-accent-background-color has-text-color has-background wp-element-button" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?></a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
