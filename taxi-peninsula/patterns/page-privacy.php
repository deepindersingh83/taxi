<?php
/**
 * Title: Privacy policy page
 * Slug: taxi-peninsula/page-privacy
 * Categories: taxi-peninsula
 * Keywords: privacy, policy, APP, personal information
 * Description: Privacy policy that follows the Australian Privacy Principles and updates from your settings, with a request form.
 * Block Types: core/post-content
 * Post Types: page
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:paragraph {"className":"tp-todo"} -->
<p class="tp-todo"><?php esc_html_e( '[Draft — the policy below is written from your settings (Bookings → Settings → Privacy): the services you use, where data goes overseas and how long you keep it. Have it checked by your adviser, add anything specific to your business here, then publish. Delete this note before publishing.]', 'taxi-peninsula' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[tp_privacy_policy]
<!-- /wp:shortcode -->

<!-- wp:shortcode -->
[tp_privacy_request]
<!-- /wp:shortcode -->
