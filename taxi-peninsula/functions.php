<?php
/**
 * Taxi Peninsula theme bootstrap.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

define( 'TP_VERSION', '2.7.3' );
define( 'TP_DIR', get_template_directory() );
define( 'TP_URI', get_template_directory_uri() );

require TP_DIR . '/inc/helpers.php';
require TP_DIR . '/inc/admin-menus.php';
require TP_DIR . '/inc/settings.php';
require TP_DIR . '/inc/languages.php';
require TP_DIR . '/inc/setup.php';
require TP_DIR . '/inc/customizer.php';
require TP_DIR . '/inc/security.php';
require TP_DIR . '/inc/hardening.php';
require TP_DIR . '/inc/bookings-cpt.php';
require TP_DIR . '/inc/content-types.php';
require TP_DIR . '/inc/bookings-form.php';
require TP_DIR . '/inc/sms.php';
require TP_DIR . '/inc/payments.php';
require TP_DIR . '/inc/maps.php';
require TP_DIR . '/inc/lookup.php';
require TP_DIR . '/inc/contact.php';
require TP_DIR . '/inc/components.php';
require TP_DIR . '/inc/drivers.php';
require TP_DIR . '/inc/trips.php';
require TP_DIR . '/inc/accounts.php';
require TP_DIR . '/inc/activity.php';
require TP_DIR . '/inc/privacy.php';
require TP_DIR . '/inc/review-invite.php';
require TP_DIR . '/inc/seo.php';
require TP_DIR . '/inc/analytics.php';
require TP_DIR . '/inc/local-seo.php';
require TP_DIR . '/inc/dynamic.php';
require TP_DIR . '/inc/google-reviews.php';
require TP_DIR . '/inc/destinations.php';
require TP_DIR . '/inc/blog.php';
require TP_DIR . '/inc/people.php';
require TP_DIR . '/inc/careers.php';
require TP_DIR . '/inc/home.php';
require TP_DIR . '/inc/area-map.php';
require TP_DIR . '/inc/menu.php';
require TP_DIR . '/inc/whatsapp.php';
require TP_DIR . '/inc/tracking.php';
require TP_DIR . '/inc/promos.php';
require TP_DIR . '/inc/flights.php';
require TP_DIR . '/inc/quotes.php';
require TP_DIR . '/inc/venues.php';
require TP_DIR . '/inc/ndis-log.php';
require TP_DIR . '/inc/lost-property.php';
require TP_DIR . '/inc/marketing.php';
require TP_DIR . '/inc/local-pages.php';
require TP_DIR . '/inc/gbp.php';
require TP_DIR . '/inc/push.php';
require TP_DIR . '/inc/review-widgets.php';
require TP_DIR . '/inc/events.php';
require TP_DIR . '/inc/dashboard.php';
require TP_DIR . '/inc/health.php';
require TP_DIR . '/inc/search.php';
require TP_DIR . '/inc/ai-search.php';
require TP_DIR . '/inc/eeat.php';
require TP_DIR . '/inc/councils.php';
require TP_DIR . '/inc/search-data.php';
require TP_DIR . '/inc/payment-info.php';
require TP_DIR . '/inc/incidents.php';
require TP_DIR . '/inc/callbacks.php';
require TP_DIR . '/inc/alerts.php';
require TP_DIR . '/inc/partner-portal.php';
require TP_DIR . '/inc/places.php';
require TP_DIR . '/inc/glossary.php';
require TP_DIR . '/inc/content-plan.php';
require TP_DIR . '/inc/photos.php';
require TP_DIR . '/inc/mail-check.php';
require TP_DIR . '/inc/hours.php';
require TP_DIR . '/inc/design.php';
require TP_DIR . '/inc/illustrations.php';
require TP_DIR . '/inc/brand.php';
require TP_DIR . '/inc/animations.php';

if ( is_admin() ) {
	require TP_DIR . '/inc/bookings-admin.php';
	require TP_DIR . '/inc/dispatch.php';
	require TP_DIR . '/inc/finance.php';
	require TP_DIR . '/inc/admin-tools.php';
	require TP_DIR . '/inc/starter-content.php';
}
