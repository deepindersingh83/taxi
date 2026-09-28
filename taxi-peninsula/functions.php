<?php
/**
 * Taxi Peninsula theme bootstrap.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

define( 'TP_VERSION', '1.5.0' );
define( 'TP_DIR', get_template_directory() );
define( 'TP_URI', get_template_directory_uri() );

require TP_DIR . '/inc/helpers.php';
require TP_DIR . '/inc/settings.php';
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
require TP_DIR . '/inc/hours.php';
require TP_DIR . '/inc/design.php';

if ( is_admin() ) {
	require TP_DIR . '/inc/bookings-admin.php';
	require TP_DIR . '/inc/admin-tools.php';
	require TP_DIR . '/inc/starter-content.php';
}
