<?php
/**
 * Nothing found.
 *
 * @package TaxiPeninsula
 */

?>
<div class="empty-state">
	<h2><?php esc_html_e( 'Nothing here yet', 'taxi-peninsula' ); ?></h2>
	<?php if ( is_search() ) : ?>
		<p><?php esc_html_e( 'No results matched your search. Try different words.', 'taxi-peninsula' ); ?></p>
		<?php get_search_form(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Check back soon for news and travel tips.', 'taxi-peninsula' ); ?></p>
	<?php endif; ?>
</div>
