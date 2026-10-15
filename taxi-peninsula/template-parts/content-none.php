<?php
/**
 * Nothing found.
 *
 * @package TaxiPeninsula
 */

?>
<div class="empty-state empty-state--illustrated">
	<?php echo tp_illustration( 'empty-list', 'empty-state__illo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
	<h2><?php esc_html_e( 'Nothing here yet', 'taxi-peninsula' ); ?></h2>
	<?php if ( is_search() ) : ?>
		<p><?php esc_html_e( 'No results matched your search. Try different words — or book or call us and we will help.', 'taxi-peninsula' ); ?></p>
		<?php get_search_form(); ?>
		<p class="empty-state__actions">
			<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a taxi', 'taxi-peninsula' ); ?></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'Check back soon for news and travel tips.', 'taxi-peninsula' ); ?></p>
	<?php endif; ?>
</div>
