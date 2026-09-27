<?php
/**
 * Site footer.
 *
 * @package TaxiPeninsula
 */

?>
</main>

<footer class="site-footer">
	<div class="container site-footer__grid">
		<div class="site-footer__brand">
			<p class="site-footer__name"><?php tp_the_icon( 'wheelchair' ); ?> <?php bloginfo( 'name' ); ?></p>
			<p><?php echo esc_html( get_bloginfo( 'description' ) ?: __( 'Wheelchair accessible taxis for Melbourne and the Mornington Peninsula.', 'taxi-peninsula' ) ); ?></p>
			<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a taxi online', 'taxi-peninsula' ); ?></a>
		</div>

		<div>
			<h2 class="site-footer__heading"><?php esc_html_e( 'Contact', 'taxi-peninsula' ); ?></h2>
			<ul class="icon-list">
				<li><?php tp_the_icon( 'phone' ); ?><a href="<?php echo esc_url( tp_phone_href() ); ?>"><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a></li>
				<li><?php tp_the_icon( 'mail' ); ?><a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></a></li>
				<li><?php tp_the_icon( 'pin' ); ?><span><?php echo esc_html( tp_opt( 'location' ) ); ?></span></li>
				<li><?php tp_the_icon( 'clock' ); ?><span><?php echo esc_html( tp_opt( 'hours' ) ); ?></span></li>
			</ul>
		</div>

		<div>
			<h2 class="site-footer__heading"><?php esc_html_e( 'Links', 'taxi-peninsula' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'fallback_cb'    => 'tp_menu_fallback',
					'depth'          => 1,
				)
			);
			?>
		</div>

		<?php if ( is_active_sidebar( 'sidebar-footer' ) ) : ?>
			<div><?php dynamic_sidebar( 'sidebar-footer' ); ?></div>
		<?php endif; ?>
	</div>

	<div class="container site-footer__bottom">
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'taxi-peninsula' ); ?></p>
		<?php if ( function_exists( 'the_privacy_policy_link' ) ) : ?>
			<p><?php the_privacy_policy_link(); ?></p>
		<?php endif; ?>
	</div>
</footer>

<a class="call-fab" href="<?php echo esc_url( tp_phone_href() ); ?>">
	<?php tp_the_icon( 'phone' ); ?>
	<span><?php esc_html_e( 'Call now', 'taxi-peninsula' ); ?></span>
</a>

<?php wp_footer(); ?>
</body>
</html>
