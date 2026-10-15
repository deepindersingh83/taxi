<?php
/**
 * WhatsApp: one floating button that opens WhatsApp (the app on phones,
 * WhatsApp Web on computers) straight to your number with a message already
 * typed. On the Manage My Booking page the booking reference is included.
 *
 * Settings: Appearance → Customize → Taxi Peninsula → Contact details
 * (WhatsApp number, show the button, message).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * wa.me link to the business number with a typed message, or ''.
 */
function tp_whatsapp_link( $message = '' ) {
	$num = preg_replace( '/\D/', '', (string) tp_opt( 'whatsapp_number' ) );
	if ( ! $num ) {
		return '';
	}
	if ( 0 === strpos( $num, '0' ) ) {
		$num = '61' . substr( $num, 1 );
	}
	return 'https://wa.me/' . $num . ( '' !== $message ? '?text=' . rawurlencode( $message ) : '' );
}

function tp_whatsapp_chat_enabled() {
	return (bool) tp_whatsapp_link() && get_theme_mod( 'tp_whatsapp_chat', true );
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_setting( 'tp_whatsapp_chat', array( 'default' => true, 'sanitize_callback' => 'tp_sanitize_checkbox' ) );
		$wp_customize->add_control(
			'tp_whatsapp_chat',
			array(
				'section'     => 'tp_contact',
				'type'        => 'checkbox',
				'label'       => __( 'Show the "WhatsApp us" button on every page', 'taxi-peninsula' ),
				'description' => __( 'Needs the WhatsApp number above.', 'taxi-peninsula' ),
			)
		);
		$wp_customize->add_setting( 'tp_whatsapp_message', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control(
			'tp_whatsapp_message',
			array(
				'section'     => 'tp_contact',
				'type'        => 'text',
				'label'       => __( 'Message typed in for the customer', 'taxi-peninsula' ),
				'input_attrs' => array( 'placeholder' => __( 'Hi, I would like to book a wheelchair taxi.', 'taxi-peninsula' ) ),
			)
		);
	},
	30
);

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( tp_whatsapp_chat_enabled() ) {
			$classes[] = 'tp-has-wa';
		}
		return $classes;
	}
);

// The floating button replaces the older WhatsApp corner link.
add_filter(
	'tp_show_whatsapp_fab',
	static function ( $show ) {
		return $show && ! tp_whatsapp_chat_enabled();
	}
);

add_action(
	'wp_footer',
	static function () {
		if ( ! tp_whatsapp_chat_enabled() ) {
			return;
		}
		$pages = (array) get_option( 'tp_pages', array() );
		if ( ! empty( $pages['driver'] ) && is_page( $pages['driver'] ) ) {
			return; // Not on the driver screen.
		}
		$message = get_theme_mod( 'tp_whatsapp_message', '' ) ?: __( 'Hi, I would like to book a wheelchair taxi.', 'taxi-peninsula' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ref = isset( $_GET['ref'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ) : '';
		if ( preg_match( '/^TP-[A-Z0-9]{6}$/', $ref ) ) {
			/* translators: %s: booking reference */
			$message = sprintf( __( 'Hi, I have a question about my booking %s.', 'taxi-peninsula' ), $ref );
		}
		?>
		<a class="whatsapp-button" href="<?php echo esc_url( tp_whatsapp_link( $message ) ); ?>" target="_blank" rel="noopener">
			<?php echo tp_whatsapp_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span class="whatsapp-button__label"><?php esc_html_e( 'WhatsApp us', 'taxi-peninsula' ); ?></span>
			<span class="screen-reader-text"><?php esc_html_e( '(opens WhatsApp)', 'taxi-peninsula' ); ?></span>
		</a>
		<?php
	},
	5
);

/**
 * WhatsApp logo mark (used to identify the link).
 */
function tp_whatsapp_icon() {
	return '<svg class="icon icon-whatsapp" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15h-.01a8.23 8.23 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.17.24-.64.81-.79.98-.14.17-.29.19-.54.06-.25-.12-1.050-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.42.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.42h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.050.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28Z"/></svg>';
}
