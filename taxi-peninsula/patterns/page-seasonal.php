<?php
/**
 * Title: Seasonal / event landing page
 * Slug: taxi-peninsula/page-seasonal
 * Categories: taxi-peninsula
 * Keywords: seasonal, event, christmas, footy, finals, racing, promotion
 * Description: Landing page for an event or season (footy finals, Christmas, spring racing). Schedule it with Publish → Schedule and set "Unpublish automatically".
 * Block Types: core/post-content
 * Post Types: page
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Tip: use the "Blank canvas" or "Full width" template. Schedule go-live with Publish → Schedule, and set the end date in "Unpublish automatically" (right-hand panel). Add an Announcement linking here while it runs.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"align":"full","className":"tp-hero-block","backgroundColor":"primary","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull tp-hero-block has-white-color has-primary-background-color has-text-color has-background"><!-- wp:paragraph {"className":"tp-eyebrow"} -->
<p class="tp-eyebrow"><?php esc_html_e( '[Event dates]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php esc_html_e( 'Wheelchair taxis for [event name]', 'taxi-peninsula' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tp-lead"} -->
<p class="tp-lead"><?php esc_html_e( 'Book early — accessible vehicles are in high demand during [event]. We will get you there and home again.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"accent","textColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-accent-background-color has-text-color has-background wp-element-button" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book your trip', 'taxi-peninsula' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tp-section-block","layout":{"type":"constrained"}} -->
<div class="wp-block-group tp-section-block"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Drop-off and pick-up points', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Where accessible drop-off is, which gate to use, how pick-ups work after the event.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Tips for the day', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php esc_html_e( 'Book your return trip when you book — tick "I need a return trip".', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php esc_html_e( 'Allow extra time for traffic around the venue.', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->
