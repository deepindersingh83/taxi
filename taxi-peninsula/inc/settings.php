<?php
/**
 * Integration settings (SMS, payments, spam protection, reviews, recurring trips).
 *
 * Stored in the `tp_settings` option and edited under Bookings → Settings.
 * Any key can be overridden from wp-config.php with a constant named
 * TP_<KEY IN UPPERCASE>, e.g. define( 'TP_STRIPE_SECRET_KEY', 'sk_live_…' );
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings schema: key => [section, label, type, default, help, choices?].
 */
function tp_settings_schema() {
	return array(
		// SMS.
		'sms_provider'          => array( 'sms', __( 'SMS provider', 'taxi-peninsula' ), 'select', 'none', '', array( 'none' => __( 'Off', 'taxi-peninsula' ), 'clicksend' => 'ClickSend', 'twilio' => 'Twilio' ) ),
		'clicksend_username'    => array( 'sms', __( 'ClickSend username', 'taxi-peninsula' ), 'text', '', '' ),
		'clicksend_api_key'     => array( 'sms', __( 'ClickSend API key', 'taxi-peninsula' ), 'secret', '', '' ),
		'twilio_account_sid'    => array( 'sms', __( 'Twilio Account SID', 'taxi-peninsula' ), 'text', '', '' ),
		'twilio_auth_token'     => array( 'sms', __( 'Twilio Auth Token', 'taxi-peninsula' ), 'secret', '', '' ),
		'sms_from'              => array( 'sms', __( 'Sender ID or number', 'taxi-peninsula' ), 'text', '', __( 'An approved alphanumeric sender (e.g. TaxiPen, max 11 characters) or your SMS number in +61 format.', 'taxi-peninsula' ) ),
		'sms_office_number'     => array( 'sms', __( 'Office mobile for new-booking alerts', 'taxi-peninsula' ), 'text', '', __( 'Leave empty to skip office SMS alerts.', 'taxi-peninsula' ) ),
		'sms_customer_received' => array( 'sms', __( 'Text customers when a booking is received', 'taxi-peninsula' ), 'checkbox', true, '' ),
		'sms_customer_status'   => array( 'sms', __( 'Text customers when a booking is confirmed, a driver is assigned or it is cancelled', 'taxi-peninsula' ), 'checkbox', true, '' ),
		'sms_driver_assigned'   => array( 'sms', __( 'Text drivers when a job is assigned to them', 'taxi-peninsula' ), 'checkbox', true, '' ),

		// Payments.
		'payments_enabled'      => array( 'payments', __( 'Offer online deposit with Stripe', 'taxi-peninsula' ), 'checkbox', false, '' ),
		'stripe_secret_key'     => array( 'payments', __( 'Stripe secret key', 'taxi-peninsula' ), 'secret', '', __( 'Starts with sk_live_ or sk_test_.', 'taxi-peninsula' ) ),
		'stripe_webhook_secret' => array( 'payments', __( 'Stripe webhook signing secret', 'taxi-peninsula' ), 'secret', '', '' ),
		'deposit_amount'        => array( 'payments', __( 'Deposit amount (AUD)', 'taxi-peninsula' ), 'number', 20, '' ),
		'invoice_enabled'       => array( 'payments', __( 'Offer "account / invoice" for NDIS, aged care and business customers', 'taxi-peninsula' ), 'checkbox', true, '' ),

		// Spam protection.
		'captcha_provider'      => array( 'captcha', __( 'Spam protection', 'taxi-peninsula' ), 'select', 'none', __( 'Protects the booking, contact and booking-lookup forms.', 'taxi-peninsula' ), array( 'none' => __( 'Off (honeypot + rate limits only)', 'taxi-peninsula' ), 'turnstile' => 'Cloudflare Turnstile', 'recaptcha' => 'Google reCAPTCHA v2 (checkbox)' ) ),
		'captcha_site_key'      => array( 'captcha', __( 'Site key', 'taxi-peninsula' ), 'text', '', '' ),
		'captcha_secret_key'    => array( 'captcha', __( 'Secret key', 'taxi-peninsula' ), 'secret', '', '' ),

		// Reviews.
		'google_reviews_url'    => array( 'reviews', __( 'Google reviews link', 'taxi-peninsula' ), 'url', '', __( 'Your Google Business Profile reviews URL.', 'taxi-peninsula' ) ),
		'google_rating'         => array( 'reviews', __( 'Google star rating', 'taxi-peninsula' ), 'text', '', __( 'e.g. 4.9 — copy it from your Google profile. Leave empty to hide.', 'taxi-peninsula' ) ),
		'google_review_count'   => array( 'reviews', __( 'Number of Google reviews', 'taxi-peninsula' ), 'number', '', '' ),

		// Recurring.
		'recurring_enabled'     => array( 'recurring', __( 'Allow customers to request repeat trips', 'taxi-peninsula' ), 'checkbox', true, '' ),
		'recurring_max_weeks'   => array( 'recurring', __( 'Longest repeat period (weeks)', 'taxi-peninsula' ), 'number', 12, '' ),
	);
}

function tp_settings_sections() {
	return array(
		'sms'       => array( __( 'SMS notifications', 'taxi-peninsula' ), __( 'Messages are sent through your own ClickSend or Twilio account and billed by them.', 'taxi-peninsula' ) ),
		'payments'  => array( __( 'Payments & invoicing', 'taxi-peninsula' ), sprintf( /* translators: %s: webhook URL */ __( 'In Stripe, add a webhook for the "checkout.session.completed" event pointing to: %s', 'taxi-peninsula' ), '<code>' . esc_html( rest_url( 'taxi-peninsula/v1/stripe' ) ) . '</code>' ) ),
		'captcha'   => array( __( 'Spam protection', 'taxi-peninsula' ), '' ),
		'reviews'   => array( __( 'Google reviews', 'taxi-peninsula' ), __( 'Shown with your testimonials on the home page.', 'taxi-peninsula' ) ),
		'recurring' => array( __( 'Recurring trips', 'taxi-peninsula' ), '' ),
	);
}

/**
 * Read a setting (constant override → saved option → default).
 *
 * @param string $key Setting key.
 * @return mixed
 */
function tp_setting( $key ) {
	$const = 'TP_' . strtoupper( $key );
	if ( defined( $const ) ) {
		return constant( $const );
	}
	static $saved = null;
	if ( null === $saved ) {
		$saved = (array) get_option( 'tp_settings', array() );
	}
	if ( array_key_exists( $key, $saved ) ) {
		return $saved[ $key ];
	}
	$schema = tp_settings_schema();
	return isset( $schema[ $key ] ) ? $schema[ $key ][3] : '';
}

add_action( 'admin_menu', 'tp_settings_menu', 20 );
function tp_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=tp_booking',
		__( 'Booking settings', 'taxi-peninsula' ),
		__( 'Settings', 'taxi-peninsula' ),
		'manage_options',
		'tp-settings',
		'tp_render_settings_page'
	);
}

add_action( 'admin_init', 'tp_register_settings' );
function tp_register_settings() {
	register_setting(
		'tp_settings',
		'tp_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tp_sanitize_settings',
			'default'           => array(),
		)
	);

	foreach ( tp_settings_sections() as $id => $section ) {
		add_settings_section(
			'tp_' . $id,
			$section[0],
			static function () use ( $section ) {
				if ( $section[1] ) {
					echo '<p>' . wp_kses( $section[1], array( 'code' => array() ) ) . '</p>';
				}
			},
			'tp-settings'
		);
	}

	foreach ( tp_settings_schema() as $key => $field ) {
		add_settings_field(
			'tp_' . $key,
			$field[1],
			'tp_render_setting_field',
			'tp-settings',
			'tp_' . $field[0],
			array(
				'key'       => $key,
				'field'     => $field,
				'label_for' => 'checkbox' === $field[2] ? null : 'tp_setting_' . $key,
			)
		);
	}
}

function tp_render_setting_field( $args ) {
	$key   = $args['key'];
	$field = $args['field'];
	$type  = $field[2];
	$id    = 'tp_setting_' . $key;
	$name  = 'tp_settings[' . $key . ']';
	$value = tp_setting( $key );

	if ( defined( 'TP_' . strtoupper( $key ) ) ) {
		echo '<p><em>' . esc_html__( 'Set in wp-config.php', 'taxi-peninsula' ) . '</em></p>';
		return;
	}

	switch ( $type ) {
		case 'checkbox':
			printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_html__( 'Enabled', 'taxi-peninsula' ) );
			break;
		case 'select':
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( $field[5] as $k => $label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;
		case 'secret':
			printf(
				'<input type="password" class="regular-text" id="%1$s" name="%2$s" value="" autocomplete="new-password" placeholder="%3$s">',
				esc_attr( $id ),
				esc_attr( $name ),
				$value ? esc_attr__( '•••••••• saved — leave blank to keep', 'taxi-peninsula' ) : ''
			);
			break;
		default:
			printf(
				'<input type="%1$s" class="regular-text" id="%2$s" name="%3$s" value="%4$s"%5$s>',
				esc_attr( 'url' === $type ? 'url' : ( 'number' === $type ? 'number' : 'text' ) ),
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				'number' === $type ? ' step="any" min="0"' : ''
			);
	}

	if ( $field[4] ) {
		echo '<p class="description">' . esc_html( $field[4] ) . '</p>';
	}
}

function tp_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$old   = (array) get_option( 'tp_settings', array() );
	$clean = array();

	foreach ( tp_settings_schema() as $key => $field ) {
		$raw = isset( $input[ $key ] ) ? $input[ $key ] : null;
		switch ( $field[2] ) {
			case 'checkbox':
				$clean[ $key ] = ! empty( $raw );
				break;
			case 'select':
				$clean[ $key ] = isset( $field[5][ $raw ] ) ? $raw : $field[3];
				break;
			case 'secret':
				$raw           = is_string( $raw ) ? trim( $raw ) : '';
				$clean[ $key ] = '' === $raw ? ( $old[ $key ] ?? '' ) : sanitize_text_field( $raw );
				break;
			case 'number':
				$clean[ $key ] = ( null === $raw || '' === $raw ) ? '' : (float) $raw;
				break;
			case 'url':
				$clean[ $key ] = esc_url_raw( (string) $raw );
				break;
			default:
				$clean[ $key ] = sanitize_text_field( (string) $raw );
		}
	}
	return $clean;
}

function tp_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap tp-settings">
		<h1><?php esc_html_e( 'Booking settings', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Contact details, hero text and service areas are under Appearance → Customize → Taxi Peninsula.', 'taxi-peninsula' ); ?></p>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'tp_settings' );
			do_settings_sections( 'tp-settings' );
			submit_button();
			?>
		</form>

		<?php if ( 'none' !== tp_setting( 'sms_provider' ) ) : ?>
			<hr>
			<h2><?php esc_html_e( 'Send a test SMS', 'taxi-peninsula' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_test_sms">
				<?php wp_nonce_field( 'tp_test_sms' ); ?>
				<label for="tp_test_to" class="screen-reader-text"><?php esc_html_e( 'Mobile number', 'taxi-peninsula' ); ?></label>
				<input type="tel" id="tp_test_to" name="to" placeholder="04xx xxx xxx" required>
				<?php submit_button( __( 'Send test', 'taxi-peninsula' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
