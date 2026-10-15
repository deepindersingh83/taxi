<?php
/**
 * Main menu extras: a services mega-menu and dropdown toggle buttons.
 *
 * The menu item that links to your services (the Services page or the
 * services archive) opens a panel listing every service with its icon and a
 * one-line description, plus a "Book now" card. Add the CSS class "mega" to
 * any other top-level menu item (Appearance → Menus → Screen Options → CSS
 * classes) to give it the same panel. Items that already have their own
 * sub-items keep a normal dropdown.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Should this menu item open the services mega-menu?
 */
function tp_menu_is_mega( $item, $args, $depth ) {
	if ( 0 !== (int) $depth || empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return false;
	}
	$classes = (array) $item->classes;
	if ( in_array( 'mega', $classes, true ) ) {
		return true;
	}
	if ( in_array( 'menu-item-has-children', $classes, true ) ) {
		return false;
	}
	$targets = array_filter(
		array(
			tp_page_url_by_template( 'services' ),
			get_post_type_archive_link( 'tp_service' ),
			home_url( '/services/' ),
		)
	);
	$url = untrailingslashit( strtok( (string) $item->url, '#?' ) );
	foreach ( $targets as $t ) {
		if ( $url && untrailingslashit( $t ) === $url ) {
			return true;
		}
	}
	return false;
}

add_filter(
	'nav_menu_css_class',
	static function ( $classes, $item, $args, $depth ) {
		if ( tp_menu_is_mega( $item, $args, $depth ) ) {
			$classes[] = 'has-mega';
		}
		return $classes;
	},
	10,
	4
);

add_filter(
	'walker_nav_menu_start_el',
	static function ( $output, $item, $depth, $args ) {
		if ( ! tp_menu_is_mega( $item, $args, $depth ) ) {
			return $output;
		}
		return $output . tp_mega_panel( 'mega-' . $item->ID );
	},
	10,
	4
);

/**
 * The services panel.
 */
function tp_mega_panel( $id ) {
	$services = array_slice( tp_services_list(), 0, 8 );
	if ( ! $services ) {
		return '';
	}
	$book = tp_booking_page_url();
	ob_start();
	?>
	<div class="mega" id="<?php echo esc_attr( $id ); ?>">
		<div class="mega__inner">
			<ul class="mega__list">
				<?php foreach ( $services as $s ) : ?>
					<li>
						<a class="mega__item" href="<?php echo esc_url( $s['url'] ?: $book ); ?>">
							<span class="mega__icon" aria-hidden="true"><?php echo tp_illustration( $s['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span class="mega__text">
								<span class="mega__title"><?php echo esc_html( $s['title'] ); ?></span>
								<span class="mega__desc"><?php echo esc_html( wp_trim_words( $s['text'], 11 ) ); ?></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="mega__card">
				<p class="mega__card-title"><?php esc_html_e( 'Book a wheelchair taxi', 'taxi-peninsula' ); ?></p>
				<p><?php esc_html_e( 'Booking takes about two minutes. We confirm by SMS or email.', 'taxi-peninsula' ); ?></p>
				<a class="btn btn--accent" href="<?php echo esc_url( $book ); ?>"><?php esc_html_e( 'Book now', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<a class="mega__call" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?> <?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
