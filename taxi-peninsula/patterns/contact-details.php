<?php
/**
 * Title: Contact details + map
 * Slug: taxi-peninsula/contact-details
 * Categories: taxi-peninsula
 * Keywords: contact, map, phone, address
 * Description: Phone, email, hours and a map side by side.
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:group {"align":"full","className":"tp-section-block","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull tp-section-block"><!-- wp:columns {"className":"tp-contact-columns"} -->
<div class="wp-block-columns tp-contact-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:shortcode -->
[tp_contact_details]
<!-- /wp:shortcode --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:shortcode -->
[tp_map]
<!-- /wp:shortcode --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
