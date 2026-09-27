<?php
/**
 * Blog sidebar.
 *
 * @package TaxiPeninsula
 */

?>
<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Sidebar', 'taxi-peninsula' ); ?>">
	<section class="widget widget--book">
		<h2 class="widget-title"><?php esc_html_e( 'Book a wheelchair taxi', 'taxi-peninsula' ); ?></h2>
		<p><?php esc_html_e( 'Book online in two minutes or call our team.', 'taxi-peninsula' ); ?></p>
		<a class="btn btn--accent btn--block" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book online', 'taxi-peninsula' ); ?></a>
		<a class="btn btn--ghost btn--block" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
	</section>
	<?php if ( is_active_sidebar( 'sidebar-blog' ) ) : ?>
		<?php dynamic_sidebar( 'sidebar-blog' ); ?>
	<?php else : ?>
		<section class="widget">
			<h2 class="widget-title"><?php esc_html_e( 'Search', 'taxi-peninsula' ); ?></h2>
			<?php get_search_form(); ?>
		</section>
		<section class="widget">
			<h2 class="widget-title"><?php esc_html_e( 'Categories', 'taxi-peninsula' ); ?></h2>
			<ul><?php wp_list_categories( array( 'title_li' => '', 'show_count' => true ) ); ?></ul>
		</section>
	<?php endif; ?>
</aside>
