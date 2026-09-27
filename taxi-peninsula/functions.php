<?php
/**
 * Taxi Peninsula theme bootstrap.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

define( 'TP_VERSION', '1.0.0' );
define( 'TP_DIR', get_template_directory() );
define( 'TP_URI', get_template_directory_uri() );

require TP_DIR . '/inc/helpers.php';
require TP_DIR . '/inc/setup.php';
require TP_DIR . '/inc/customizer.php';
require TP_DIR . '/inc/bookings-cpt.php';
require TP_DIR . '/inc/bookings-form.php';

if ( is_admin() ) {
	require TP_DIR . '/inc/bookings-admin.php';
}
