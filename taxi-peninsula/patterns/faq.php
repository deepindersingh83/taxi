<?php
/**
 * Title: FAQ accordion
 * Slug: taxi-peninsula/faq
 * Categories: taxi-peninsula
 * Keywords: faq, questions, accordion
 * Description: Frequently asked questions (edit them under FAQs).
 *
 * @package TaxiPeninsula
 */

?>
<!-- wp:group {"align":"full","className":"tp-section-block","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull tp-section-block"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Frequently asked questions', 'taxi-peninsula' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:shortcode -->
[tp_faq]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->
