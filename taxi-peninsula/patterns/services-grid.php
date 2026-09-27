<?php
/**
 * Title: Services grid
 * Slug: taxi-peninsula/services-grid
 * Categories: taxi-peninsula
 * Keywords: services, grid, cards
 * Description: Cards for every published Service (updates automatically).
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:group {"align":"full","className":"tp-section-block","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull tp-section-block"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Our services', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:shortcode -->
[tp_services]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
