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
	<meta name="theme-color" content="<?php echo esc_attr( tp_design_colors()['primary'] ); ?>" data-light="<?php echo esc_attr( tp_design_colors()['primary'] ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<span id="top" class="screen-reader-text" tabindex="-1"></span>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'taxi-peninsula' ); ?></a>

<?php tp_render_notices(); ?>
<?php do_action( 'tp_after_notices' ); ?>

<?php $tp_topbar = tp_design( 'topbar' ); ?>
<?php if ( 'none' !== $tp_topbar ) : ?>
<div class="topbar" role="region" aria-label="<?php esc_attr_e( 'Opening hours and account', 'taxi-peninsula' ); ?>">
	<div class="container topbar__inner">
		<p class="topbar__hours"><?php tp_the_icon( 'clock' ); ?><?php tp_the_hours_badge(); ?></p>
		<?php tp_the_account_menu(); ?>
	</div>
</div>
<?php endif; ?>

<header class="site-header">
	<div class="container site-header__inner">
		<div class="brand">
			<?php tp_the_brand(); ?>
		</div>

		<?php tp_the_language_switcher( 'header' ); ?>

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
			<?php if ( tp_design( 'header_phone' ) || tp_design( 'header_book' ) ) : ?>
				<div class="primary-nav__cta">
					<?php if ( tp_design( 'header_phone' ) ) : ?>
						<a class="btn btn--ghost" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_design( 'header_phone_text' ) ?: tp_opt( 'phone_display' ) ); ?></a>
					<?php endif; ?>
					<?php if ( tp_design( 'header_book' ) ) : ?>
						<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php echo esc_html( tp_design( 'header_book_text' ) ?: __( 'Book now', 'taxi-peninsula' ) ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</nav>
	</div>
</header>

<main id="main" class="site-main" tabindex="-1">
