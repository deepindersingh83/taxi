<?php
/**
 * Title: Blog: local destination guide
 * Slug: taxi-peninsula/post-local-guide
 * Categories: taxi-peninsula
 * Keywords: blog, guide, local, hospital, destination
 * Description: Outline for posts like "Wheelchair taxi to [hospital]: what to expect".
 * Block Types: core/post-content
 * Post Types: post
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Writing guide — replace every grey box with your own words. Aim for 600–1,000 words of real, local detail. Delete this box when done.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( 'Introduce the place and who this guide is for (patients, visitors, carers). Mention the suburb by name.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Getting there by wheelchair taxi', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Typical travel time from nearby suburbs, best pick-up/drop-off point, accessible entrance.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Where we drop you off', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Describe the drop-off zone, kerb ramps, how far it is to reception, where to wait for pick-up.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Tips for appointments', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php esc_html_e( 'Book your return trip at the same time if you know when you will finish.', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php esc_html_e( 'Add your appointment time and department to the booking notes.', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php esc_html_e( 'Regular treatment? Set up a repeat booking so every trip is sorted.', 'taxi-peninsula' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Book your trip', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Book online in two minutes or call us on', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?>.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"accent","textColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-accent-background-color has-text-color has-background wp-element-button" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a wheelchair taxi', 'taxi-peninsula' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
