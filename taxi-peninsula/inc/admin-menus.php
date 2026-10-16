<?php
/**
 * Admin menu layout. The old single "Bookings" menu is split into menus
 * that match how the business works:
 *
 * - Bookings: all bookings, add, dispatch board, change requests, calendar,
 *   run sheet, quotes, enquiries, lost property.
 * - Invoices: invoices & payments, NDIS trip log.
 * - Reports: owner dashboard, booking reports, leads & calls, activity log.
 * - Marketing: news & offers, promo codes, partner pages, events, Google
 *   profile, popular destinations.
 * - Website content: services, areas, fleet, FAQs, testimonials, partner
 *   logos, announcements.
 * - Taxi settings: settings, website setup, check connections, privacy.
 *
 * Each screen keeps its address (admin.php?page=…), so old links still work.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parent menu slug for a group.
 *
 * @param string $group bookings|invoices|reports|marketing|settings|website.
 */
function tp_admin_parent( $group ) {
	$parents = array(
		'bookings'  => 'edit.php?post_type=tp_booking',
		'invoices'  => 'tp-finance',
		'reports'   => 'tp-owner',
		'marketing' => 'tp-offers',
		'settings'  => 'tp-settings',
		'website'   => 'tp-website',
	);
	return $parents[ $group ] ?? $parents['bookings'];
}

/**
 * Admin URL of one of the theme's screens.
 */
function tp_admin_page_url( $slug, array $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . $slug ) );
}

// Top-level menus (each opens its first screen).
add_action(
	'admin_menu',
	static function () {
		add_menu_page( __( 'Owner dashboard', 'taxi-peninsula' ), __( 'Reports', 'taxi-peninsula' ), 'tp_view_finance', 'tp-owner', 'tp_render_owner_dashboard', 'dashicons-chart-area', 5 );
		add_menu_page( __( 'News & offers', 'taxi-peninsula' ), __( 'Marketing', 'taxi-peninsula' ), 'tp_manage_offers', 'tp-offers', 'tp_render_offers_page', 'dashicons-megaphone', 6 );
		add_submenu_page( 'tp-offers', __( 'News & offers', 'taxi-peninsula' ), __( 'News & offers', 'taxi-peninsula' ), 'tp_manage_offers', 'tp-offers', 'tp_render_offers_page', 0 );
		add_menu_page( __( 'Website content', 'taxi-peninsula' ), __( 'Website content', 'taxi-peninsula' ), 'edit_pages', 'tp-website', 'tp_render_website_hub', 'dashicons-admin-site-alt3', 21 );
		add_submenu_page( 'tp-website', __( 'Website content', 'taxi-peninsula' ), __( 'Overview', 'taxi-peninsula' ), 'edit_pages', 'tp-website', 'tp_render_website_hub', 0 );
		add_menu_page( __( 'Booking settings', 'taxi-peninsula' ), __( 'Taxi settings', 'taxi-peninsula' ), 'manage_options', 'tp-settings', 'tp_render_settings_page', 'dashicons-admin-settings', 81 );
	},
	5
);

// Post types: put each list in its menu.
add_filter(
	'register_post_type_args',
	static function ( $args, $type ) {
		$map = array(
			'tp_quote'       => 'bookings',
			'tp_message'     => 'bookings',
			'tp_lost'        => 'bookings',
			'tp_promo'       => 'marketing',
			'tp_venue'       => 'marketing',
			'tp_event'       => 'marketing',
			'tp_service'     => 'website',
			'tp_area'        => 'website',
			'tp_fleet'       => 'website',
			'tp_faq'         => 'website',
			'tp_testimonial' => 'website',
			'tp_partner'     => 'website',
			'tp_notice'      => 'website',
		);
		if ( isset( $map[ $type ] ) && ( ! isset( $args['show_ui'] ) || $args['show_ui'] ) ) {
			$args['show_in_menu'] = tp_admin_parent( $map[ $type ] );
		}
		return $args;
	},
	10,
	2
);

// FAQ topics lost their own menu when FAQs moved; add the link back.
add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'tp-website', __( 'FAQ topics', 'taxi-peninsula' ), __( 'FAQ topics', 'taxi-peninsula' ), 'manage_categories', 'edit-tags.php?taxonomy=tp_faq_topic&post_type=tp_faq' );
	},
	20
);

// Keep the right menu open on taxonomy and post-type screens that moved.
add_filter(
	'parent_file',
	static function ( $parent ) {
		$screen = get_current_screen();
		if ( $screen && 'tp_faq_topic' === $screen->taxonomy ) {
			return 'tp-website';
		}
		return $parent;
	}
);

/**
 * Website content: one page with every content type, its count and "Add new".
 */
function tp_render_website_hub() {
	$types = array( 'tp_service', 'tp_area', 'tp_fleet', 'tp_faq', 'tp_testimonial', 'tp_partner', 'tp_notice', 'page', 'post' );
	?>
	<div class="wrap tp-hub">
		<h1><?php esc_html_e( 'Website content', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Everything visitors read on the website. Bookings, invoices and marketing have their own menus.', 'taxi-peninsula' ); ?></p>
		<ul class="tp-hub__grid">
			<?php foreach ( $types as $type ) : ?>
				<?php
				$obj = get_post_type_object( $type );
				if ( ! $obj || ! current_user_can( $obj->cap->edit_posts ) ) {
					continue;
				}
				$n = (int) ( wp_count_posts( $type )->publish ?? 0 );
				?>
				<li class="tp-hub__item">
					<span class="dashicons <?php echo esc_attr( is_string( $obj->menu_icon ) && 0 === strpos( (string) $obj->menu_icon, 'dashicons-' ) ? $obj->menu_icon : ( 'post' === $type ? 'dashicons-admin-post' : 'dashicons-admin-page' ) ); ?>" aria-hidden="true"></span>
					<a class="tp-hub__name" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $type ) ); ?>"><?php echo esc_html( $obj->labels->name ); ?></a>
					<span class="tp-hub__count">
						<?php
						/* translators: %d: number published */
						echo esc_html( sprintf( _n( '%d published', '%d published', $n, 'taxi-peninsula' ), $n ) );
						?>
					</span>
					<?php if ( current_user_can( $obj->cap->create_posts ) ) : ?>
						<a class="button button-small" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $type ) ); ?>"><?php echo esc_html( $obj->labels->add_new_item ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}
