<?php
/**
 * Title: Terms & cancellation policy page
 * Slug: taxi-peninsula/page-terms
 * Categories: taxi-peninsula
 * Keywords: terms, conditions, cancellation, policy, legal
 * Description: Structured draft for your booking terms and cancellation policy, with [CONFIRM] notes where your real policy is needed.
 * Block Types: core/post-content
 * Post Types: page
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Draft template — review every section, replace each [CONFIRM] note with your real policy, and have it checked before publishing.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'About these terms', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'These terms apply to bookings made online, by phone or by email with ', 'taxi-peninsula' ); ?><?php echo esc_html( get_bloginfo( 'name' ) ); ?><?php esc_html_e( '. By booking, you agree to them.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Making a booking', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'A booking request is confirmed only when we confirm it by phone, SMS or email. Please give accurate pick-up details, the number of passengers and wheelchairs, and the type and size of any mobility aid.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Fares and payment', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[CONFIRM: how fares are charged — e.g. metered fares in line with Victorian regulations, or a quoted fixed fare — and accepted payment methods (card, cash, MPTP, account). Online fare estimates are a guide only.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Deposits', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[CONFIRM: when a deposit is required, how much, and whether it is deducted from the fare.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Changes and cancellations', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'You can ask to change or cancel a booking using the Manage My Booking page or by calling us.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[CONFIRM: how much notice you need (e.g. at least 2 hours), and any cancellation fee or deposit refund rules.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'No-shows and waiting time', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[CONFIRM: how long the driver will wait at pick-up, whether waiting time is charged, and what happens if the passenger is not there.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Wheelchairs, mobility aids and safety', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'For everyone’s safety, wheelchairs are secured with restraints and passengers wear a seatbelt. Drivers may decline to carry a mobility aid that cannot be secured safely. Please tell us its size and weight when booking.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Assistance animals', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Assistance animals are always welcome. Please tell us about any other animal when booking.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Lost property', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'If you leave something behind, contact us as soon as possible and quote your booking reference.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Complaints and feedback', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We want to hear about anything we could do better. Contact us first and we will try to resolve it quickly. You can also contact Commercial Passenger Vehicles Victoria (CPVV), the state regulator.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Privacy', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We use your details only to provide and manage your trips. See our Privacy Policy for more.', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Contact us', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Phone:', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?> · <?php esc_html_e( 'Email:', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_opt( 'email' ) ); ?></p>
<!-- /wp:paragraph -->
