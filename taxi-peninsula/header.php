<?php
/**
 * Site header.
 *
 * @package TaxiPeninsula
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0b2a5b">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'taxi-peninsula' ); ?></a>

<div class="topbar">
	<div class="container topbar__inner">
		<ul class="topbar__contact">
			<li><a href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><span><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></span></a></li>
			<li class="topbar__email"><a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php tp_the_icon( 'mail' ); ?><span><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></span></a></li>
			<?php $tp_lookup = tp_page_url_by_template( 'lookup' ); ?>
			<?php if ( $tp_lookup ) : ?>
				<li><a href="<?php echo esc_url( $tp_lookup ); ?>"><?php tp_the_icon( 'calendar' ); ?><span><?php esc_html_e( 'Manage my booking', 'taxi-peninsula' ); ?></span></a></li>
			<?php endif; ?>
			<li class="topbar__hours"><?php tp_the_icon( 'clock' ); ?><span><?php echo esc_html( tp_opt( 'hours' ) ); ?></span></li>
		</ul>
		<div class="a11y-tools" role="group" aria-label="<?php esc_attr_e( 'Display options', 'taxi-peninsula' ); ?>">
			<span class="a11y-tools__label"><?php tp_the_icon( 'text' ); ?><span><?php esc_html_e( 'Text size', 'taxi-peninsula' ); ?></span></span>
			<button type="button" class="a11y-btn" data-text-size="sm" aria-pressed="false" aria-label="<?php esc_attr_e( 'Smaller text', 'taxi-peninsula' ); ?>">A<sup>−</sup></button>
			<button type="button" class="a11y-btn" data-text-size="md" aria-pressed="true" aria-label="<?php esc_attr_e( 'Default text size', 'taxi-peninsula' ); ?>">A</button>
			<button type="button" class="a11y-btn" data-text-size="lg" aria-pressed="false" aria-label="<?php esc_attr_e( 'Larger text', 'taxi-peninsula' ); ?>">A<sup>+</sup></button>
			<button type="button" class="a11y-btn" data-text-size="xl" aria-pressed="false" aria-label="<?php esc_attr_e( 'Largest text', 'taxi-peninsula' ); ?>">A<sup>++</sup></button>
			<button type="button" class="a11y-btn a11y-btn--contrast" data-contrast-toggle aria-pressed="false"><?php tp_the_icon( 'contrast' ); ?><span><?php esc_html_e( 'High contrast', 'taxi-peninsula' ); ?></span></button>
		</div>
	</div>
</div>

<header class="site-header">
	<div class="container site-header__inner">
		<div class="brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="brand__mark"><?php tp_the_icon( 'wheelchair' ); ?></span>
					<span class="brand__text">
						<span class="brand__name"><?php bloginfo( 'name' ); ?></span>
						<span class="brand__tag"><?php esc_html_e( 'Wheelchair Accessible Taxis', 'taxi-peninsula' ); ?></span>
					</span>
				</a>
			<?php endif; ?>
		</div>

		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
			<span class="nav-toggle__open"><?php tp_the_icon( 'menu' ); ?></span>
			<span class="nav-toggle__close"><?php tp_the_icon( 'close' ); ?></span>
			<span class="nav-toggle__label"><?php esc_html_e( 'Menu', 'taxi-peninsula' ); ?></span>
		</button>

		<nav id="primary-nav" class="primary-nav" aria-label="<?php esc_attr_e( 'Main', 'taxi-peninsula' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => 'tp_menu_fallback',
					'depth'          => 2,
				)
			);
			?>
			<div class="primary-nav__cta">
				<a class="btn btn--ghost" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
				<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book now', 'taxi-peninsula' ); ?></a>
			</div>
		</nav>
	</div>
</header>

<main id="main" class="site-main" tabindex="-1">
