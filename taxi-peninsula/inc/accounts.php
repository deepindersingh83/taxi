<?php
/**
 * Customer and coordinator accounts: [tp_account] (My Account), sign-up,
 * passenger / client profiles, saved addresses, one-tap rebooking, receipts
 * and tax invoices, and trip ratings.
 *
 * - Customer: books for themselves or family.
 * - Coordinator / facility: NDIS coordinators, aged-care homes and hospitals
 *   booking for many passengers ("clients"), usually on account.
 *
 * Bookings made while signed in are linked to the account. Older bookings can
 * be added with their reference + phone number (the same check as Manage My Booking).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Who is who.
 * ---------------------------------------------------------------------- */

function tp_is_account_user( $user = null ) {
	$user = $user ?: wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'tp_book_account' ) && ! user_can( $user, 'edit_tp_bookings' );
}

function tp_is_coordinator( $user = null ) {
	$user = $user ?: wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'tp_book_for_many' );
}

function tp_account_url( $args = array() ) {
	return add_query_arg( $args, tp_page_url_by_template( 'account', home_url( '/' ) ) );
}

/**
 * Whether a user may see a booking in their account.
 */
function tp_user_owns_booking( $post_id, $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	return $user_id && 'tp_booking' === get_post_type( $post_id ) && (int) get_post_meta( $post_id, '_tp_customer_id', true ) === (int) $user_id;
}

/* ---- Keep account users on the front end, like drivers ---- */

add_filter(
	'login_redirect',
	static function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && tp_is_account_user( $user ) ) {
			return tp_account_url();
		}
		if ( $user instanceof WP_User && user_can( $user, 'tp_view_finance' ) && ! user_can( $user, 'edit_tp_bookings' ) ) {
			return admin_url( 'admin.php?page=tp-finance' );
		}
		return $redirect;
	},
	20,
	3
);

add_action(
	'admin_init',
	static function () {
		if ( wp_doing_ajax() || ! tp_is_account_user() ) {
			return;
		}
		global $pagenow;
		if ( 'admin-post.php' !== $pagenow ) {
			wp_safe_redirect( tp_account_url() );
			exit;
		}
	}
);

add_filter(
	'show_admin_bar',
	static function ( $show ) {
		return tp_is_account_user() ? false : $show;
	},
	20
);

/* -------------------------------------------------------------------------
 * Stored profiles (user meta).
 * ---------------------------------------------------------------------- */

/**
 * Passenger (or client) profiles: id => fields.
 */
function tp_passengers( $user_id = 0 ) {
	$list = get_user_meta( $user_id ?: get_current_user_id(), 'tp_passengers', true );
	return is_array( $list ) ? $list : array();
}

function tp_passenger_fields() {
	return array(
		'name'     => array( __( 'Full name', 'taxi-peninsula' ), 'text', true ),
		'phone'    => array( __( 'Mobile (for the driver to call)', 'taxi-peninsula' ), 'tel', false ),
		'aid'      => array( __( 'Mobility aid', 'taxi-peninsula' ), 'aid', false ),
		'width'    => array( __( 'Chair width (cm)', 'taxi-peninsula' ), 'number', false ),
		'length'   => array( __( 'Chair length (cm)', 'taxi-peninsula' ), 'number', false ),
		'weight'   => array( __( 'Chair + passenger weight (kg)', 'taxi-peninsula' ), 'number', false ),
		'ndis'     => array( __( 'NDIS number / reference', 'taxi-peninsula' ), 'text', false ),
		'needs'    => array( __( 'Needs and notes for the driver', 'taxi-peninsula' ), 'textarea', false ),
	);
}

/**
 * One line the driver sees in the trip notes.
 */
function tp_passenger_notes( array $p ) {
	$bits = array();
	if ( ! empty( $p['width'] ) || ! empty( $p['length'] ) ) {
		/* translators: 1: width, 2: length */
		$bits[] = sprintf( __( 'Chair %1$s × %2$s cm', 'taxi-peninsula' ), $p['width'] ?: '?', $p['length'] ?: '?' );
	}
	if ( ! empty( $p['weight'] ) ) {
		/* translators: %s: weight */
		$bits[] = sprintf( __( 'about %s kg with passenger', 'taxi-peninsula' ), $p['weight'] );
	}
	$line = implode( ', ', $bits );
	if ( ! empty( $p['needs'] ) ) {
		$line .= ( $line ? '. ' : '' ) . $p['needs'];
	}
	return $line;
}

function tp_saved_addresses( $user_id = 0 ) {
	$list = get_user_meta( $user_id ?: get_current_user_id(), 'tp_addresses', true );
	return is_array( $list ) ? $list : array();
}

function tp_add_saved_address( $user_id, $address, $label = '' ) {
	$address = trim( (string) $address );
	if ( '' === $address ) {
		return;
	}
	$list = tp_saved_addresses( $user_id );
	foreach ( $list as $a ) {
		if ( 0 === strcasecmp( $a['address'], $address ) ) {
			return;
		}
	}
	$list[ 'a' . wp_generate_password( 8, false ) ] = array(
		'label'   => $label ?: tp_short_place( $address ) ?: $address,
		'address' => $address,
	);
	update_user_meta( $user_id, 'tp_addresses', array_slice( $list, -30, null, true ) );
}

/* -------------------------------------------------------------------------
 * Booking form integration: prefill, profiles, rebook, link to the account.
 * ---------------------------------------------------------------------- */

/**
 * Data the booking form needs for a signed-in account user.
 */
function tp_booking_form_account_data() {
	if ( ! tp_is_account_user() ) {
		return null;
	}
	$user = wp_get_current_user();
	return array(
		'coordinator' => tp_is_coordinator(),
		'self'        => array(
			'name'  => trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name,
			'phone' => get_user_meta( $user->ID, 'tp_mobile', true ),
			'email' => $user->user_email,
		),
		'passengers'  => tp_passengers(),
		'addresses'   => tp_saved_addresses(),
		'invoice'     => array(
			'name'  => get_user_meta( $user->ID, 'tp_invoice_name', true ),
			'email' => get_user_meta( $user->ID, 'tp_invoice_email', true ),
		),
	);
}

/**
 * Prefill values for the booking form: the account's own details, and
 * ?rebook=ID (same trip again) or ?rebook=ID&swap=1 (the return journey).
 */
function tp_booking_form_prefill( array $old ) {
	$acc = tp_booking_form_account_data();
	if ( ! $acc ) {
		return $old;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only prefill of the user's own booking.
	$rebook = isset( $_GET['rebook'] ) ? absint( $_GET['rebook'] ) : 0;
	if ( $rebook && tp_user_owns_booking( $rebook ) ) {
		$b    = tp_get_booking( $rebook );
		$swap = ! empty( $_GET['swap'] );
		foreach ( array( 'name', 'phone', 'email', 'passengers', 'wheelchairs', 'vehicle', 'mobility_aid', 'mptp', 'payment', 'invoice_name', 'invoice_email', 'ndis_number', 'notes' ) as $key ) {
			$old[ $key ] = $old[ $key ] ?? $b[ $key ];
		}
		$old['pickup']    = $swap ? $b['dropoff'] : $b['pickup'];
		$old['dropoff']   = $swap ? $b['pickup'] : $b['dropoff'];
		$old['passenger'] = get_post_meta( $rebook, '_tp_passenger_key', true );
		if ( ! $swap ) {
			$old['time'] = $old['time'] ?? $b['time'];
		}
		$old['rebooked'] = $b['reference'];
	}
	// phpcs:enable

	if ( ! $acc['coordinator'] ) {
		foreach ( $acc['self'] as $key => $value ) {
			if ( empty( $old[ $key ] ) && $value ) {
				$old[ $key ] = $value;
			}
		}
	} else {
		$old['email'] = $old['email'] ?? $acc['self']['email'];
		if ( ! isset( $old['payment'] ) && tp_setting( 'invoice_enabled' ) ) {
			$old['payment'] = 'account';
		}
		if ( empty( $old['invoice_name'] ) ) {
			$old['invoice_name'] = $acc['invoice']['name'] ?: get_user_meta( get_current_user_id(), 'tp_org', true );
		}
		if ( empty( $old['invoice_email'] ) ) {
			$old['invoice_email'] = $acc['invoice']['email'] ?: $acc['self']['email'];
		}
	}
	return $old;
}

/**
 * "Who is travelling?" and saved-address pickers, shown at the top of the booking form.
 */
function tp_booking_form_account_panel( array $old ) {
	$acc = tp_booking_form_account_data();
	if ( ! $acc ) {
		return;
	}
	$aids = tp_mobility_aids();
	$data = array();
	foreach ( $acc['passengers'] as $id => $p ) {
		$data[ $id ] = array(
			'name'  => $p['name'] ?? '',
			'phone' => $p['phone'] ?? '',
			'aid'   => isset( $aids[ $p['aid'] ?? '' ] ) ? $p['aid'] : '',
			'ndis'  => $p['ndis'] ?? '',
			'notes' => tp_passenger_notes( $p ),
		);
	}
	$label = $acc['coordinator'] ? __( 'Which client is travelling?', 'taxi-peninsula' ) : __( 'Who is travelling?', 'taxi-peninsula' );
	?>
	<div class="account-box account-box--form" data-account-prefill="<?php echo esc_attr( wp_json_encode( array( 'passengers' => $data, 'self' => $acc['coordinator'] ? null : $acc['self'] ) ) ); ?>">
		<?php if ( ! empty( $old['rebooked'] ) ) : ?>
			<p class="notice notice--info">
				<?php
				/* translators: %s: booking reference */
				echo esc_html( sprintf( __( 'Details copied from %s. Choose the date and check the times, then send.', 'taxi-peninsula' ), $old['rebooked'] ) );
				?>
			</p>
		<?php endif; ?>
		<div class="field">
			<label for="bf-passenger"><?php echo esc_html( $label ); ?></label>
			<select id="bf-passenger" name="passenger" data-passenger-select>
				<?php if ( ! $acc['coordinator'] ) : ?>
					<option value=""><?php esc_html_e( 'Me', 'taxi-peninsula' ); ?></option>
				<?php else : ?>
					<option value=""><?php esc_html_e( '— New client —', 'taxi-peninsula' ); ?></option>
				<?php endif; ?>
				<?php foreach ( $acc['passengers'] as $id => $p ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $old['passenger'] ?? '', $id ); ?>><?php echo esc_html( $p['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="field__hint"><?php echo esc_html( $acc['coordinator'] ? __( 'Choosing a client fills in their details and wheelchair notes.', 'taxi-peninsula' ) : __( 'Choosing a saved passenger fills in their details and wheelchair notes.', 'taxi-peninsula' ) ); ?></p>
		</div>
		<label class="field--check account-box__save"><input type="checkbox" name="save_passenger" value="1"> <span><?php echo esc_html( $acc['coordinator'] ? __( 'Save this person as a client in my account', 'taxi-peninsula' ) : __( 'Save this passenger to my account', 'taxi-peninsula' ) ); ?></span></label>
		<label class="field--check account-box__save"><input type="checkbox" name="save_addresses" value="1" checked> <span><?php esc_html_e( 'Remember these addresses', 'taxi-peninsula' ); ?></span></label>
	</div>
	<?php
}

/**
 * Saved-address picker under an address field.
 */
function tp_booking_form_address_picker( $target ) {
	$acc = tp_booking_form_account_data();
	if ( ! $acc || ! $acc['addresses'] ) {
		return;
	}
	$id = 'bf-saved-' . $target;
	?>
	<div class="saved-address">
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Use a saved address', 'taxi-peninsula' ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" data-address-for="bf-<?php echo esc_attr( $target ); ?>">
			<option value=""><?php esc_html_e( 'Use a saved address…', 'taxi-peninsula' ); ?></option>
			<?php foreach ( $acc['addresses'] as $a ) : ?>
				<option value="<?php echo esc_attr( $a['address'] ); ?>"><?php echo esc_html( $a['label'] . ' — ' . $a['address'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
}

/**
 * Link new bookings to the signed-in account and save profiles/addresses if asked.
 * Runs inside the booking handler, after its nonce check.
 */
add_action( 'tp_booking_created', 'tp_link_booking_to_account', 5, 2 );
function tp_link_booking_to_account( $first_id, $ids ) {
	if ( ! tp_is_account_user() ) {
		return;
	}
	$user_id = get_current_user_id();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in tp_handle_booking().
	$key        = isset( $_POST['passenger'] ) ? sanitize_key( wp_unslash( $_POST['passenger'] ) ) : '';
	$passengers = tp_passengers( $user_id );
	$b          = tp_get_booking( $first_id );

	if ( ( ! $key || ! isset( $passengers[ $key ] ) ) && ! empty( $_POST['save_passenger'] ) ) {
		$key                = 'p' . strtolower( wp_generate_password( 8, false ) );
		$passengers[ $key ] = array(
			'name'  => $b['name'],
			'phone' => $b['phone'],
			'aid'   => $b['mobility_aid'],
			'ndis'  => $b['ndis_number'],
			'needs' => $b['notes'],
		);
		update_user_meta( $user_id, 'tp_passengers', $passengers );
	}
	if ( ! empty( $_POST['save_addresses'] ) ) {
		tp_add_saved_address( $user_id, $b['pickup'] );
		tp_add_saved_address( $user_id, $b['dropoff'] );
	}
	// phpcs:enable
	foreach ( (array) $ids as $id ) {
		update_post_meta( $id, '_tp_customer_id', $user_id );
		if ( $key && isset( $passengers[ $key ] ) ) {
			update_post_meta( $id, '_tp_passenger_key', $key );
		}
	}
	if ( tp_is_coordinator() ) {
		$org = get_user_meta( $user_id, 'tp_org', true );
		/* translators: 1: coordinator name, 2: organisation */
		tp_add_log( $first_id, array( 'note' => sprintf( __( 'Booked by %1$s (%2$s)', 'taxi-peninsula' ), wp_get_current_user()->display_name, $org ) ) );
	}
}

/* -------------------------------------------------------------------------
 * My Account page.
 * ---------------------------------------------------------------------- */

add_shortcode( 'tp_account', 'tp_account_shortcode' );
function tp_account_shortcode() {
	ob_start();
	echo '<div class="tp-component account" id="account">';
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
	$flash = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '';
	$state = array( 'errors' => array(), 'old' => array() );
	if ( isset( $_GET['tp_form'] ) ) {
		$stored = get_transient( 'tp_form_' . sanitize_key( wp_unslash( $_GET['tp_form'] ) ) );
		if ( is_array( $stored ) ) {
			$state = $stored;
		}
	}
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'trips';
	// phpcs:enable

	if ( ! is_user_logged_in() ) {
		if ( ! empty( $_GET['bye'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<p class="notice notice--success" role="status" tabindex="-1" data-focus>' . esc_html__( 'Your account has been deleted. Thank you for travelling with us.', 'taxi-peninsula' ) . '</p>';
		}
		tp_account_signin( $state );
		echo '</div>';
		return ob_get_clean();
	}
	if ( ! tp_is_account_user() ) {
		echo '<div class="card"><p>' . esc_html__( 'You are signed in as staff. Customer accounts are for passengers and coordinators.', 'taxi-peninsula' ) . '</p>';
		if ( current_user_can( 'edit_tp_bookings' ) ) {
			echo '<p><a class="btn btn--primary" href="' . esc_url( admin_url( 'admin.php?page=tp-dispatch' ) ) . '">' . esc_html__( 'Open the dispatch board', 'taxi-peninsula' ) . '</a></p>';
		} elseif ( current_user_can( 'tp_driver_jobs' ) ) {
			echo '<p><a class="btn btn--primary" href="' . esc_url( tp_page_url_by_template( 'driver', home_url( '/' ) ) ) . '">' . esc_html__( 'Go to my jobs', 'taxi-peninsula' ) . '</a></p>';
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	$user  = wp_get_current_user();
	$coord = tp_is_coordinator();
	$tabs  = array(
		'trips'      => __( 'My trips', 'taxi-peninsula' ),
		'passengers' => $coord ? __( 'Clients', 'taxi-peninsula' ) : __( 'Passengers', 'taxi-peninsula' ),
		'addresses'  => __( 'Saved addresses', 'taxi-peninsula' ),
		'details'    => __( 'My details', 'taxi-peninsula' ),
	);
	/**
	 * Filters the My Account tabs. Extra tabs render on `tp_account_tab_{key}`.
	 *
	 * @param array $tabs  key => label.
	 * @param bool  $coord Coordinator account.
	 */
	$tabs  = apply_filters( 'tp_account_tabs', $tabs, $coord );
	$tab   = isset( $tabs[ $tab ] ) ? $tab : 'trips';
	$org   = get_user_meta( $user->ID, 'tp_org', true );
	?>
	<header class="account__head">
		<div>
			<h2 class="account__hello">
				<?php
				/* translators: %s: first name */
				echo esc_html( sprintf( __( 'Hello, %s', 'taxi-peninsula' ), $user->first_name ?: $user->display_name ) );
				?>
			</h2>
			<?php if ( $coord && $org ) : ?>
				<p class="account__org"><?php echo esc_html( $org ); ?> · <?php esc_html_e( 'Coordinator account', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
		</div>
		<p class="account__actions">
			<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a trip', 'taxi-peninsula' ); ?></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( wp_logout_url( tp_account_url() ) ); ?>"><?php esc_html_e( 'Sign out', 'taxi-peninsula' ); ?></a>
		</p>
	</header>
	<nav class="account__tabs" aria-label="<?php esc_attr_e( 'My account', 'taxi-peninsula' ); ?>">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( tp_account_url( 'trips' === $key ? array() : array( 'tab' => $key ) ) . '#account' ); ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
	$messages = array(
		'passenger' => __( 'Saved.', 'taxi-peninsula' ),
		'deleted'   => __( 'Removed.', 'taxi-peninsula' ),
		'address'   => __( 'Address saved.', 'taxi-peninsula' ),
		'details'   => __( 'Your details are updated.', 'taxi-peninsula' ),
		'password'  => __( 'Your password has been changed.', 'taxi-peninsula' ),
		'linked'    => __( 'Booking added to your account.', 'taxi-peninsula' ),
		'rated'     => __( 'Thank you for rating your trip!', 'taxi-peninsula' ),
		'welcome'   => __( 'Welcome! Your account is ready. Your next booking will appear here automatically.', 'taxi-peninsula' ),
	);
	if ( isset( $messages[ $flash ] ) ) {
		echo '<p class="notice notice--success" role="status" tabindex="-1" data-focus>' . esc_html( $messages[ $flash ] ) . '</p>';
	}
	if ( ! empty( $state['errors'] ) ) {
		echo '<div class="notice notice--error" role="alert" tabindex="-1" data-focus>';
		foreach ( $state['errors'] as $e ) {
			echo '<p>' . esc_html( $e ) . '</p>';
		}
		echo '</div>';
	}

	switch ( $tab ) {
		case 'passengers':
			tp_account_passengers_tab( $coord );
			break;
		case 'addresses':
			tp_account_addresses_tab();
			break;
		case 'details':
			tp_account_details_tab( $user, $coord );
			break;
		case 'trips':
			tp_account_trips_tab( $user, $coord, $state );
			break;
		default:
			if ( has_action( 'tp_account_tab_' . $tab ) ) {
				do_action( 'tp_account_tab_' . $tab, $user, $coord );
				break;
			}
			tp_account_trips_tab( $user, $coord, $state );
	}
	echo '</div>';
	return ob_get_clean();
}

/**
 * Hidden fields for account forms.
 */
function tp_account_form_fields( $action ) {
	printf( '<input type="hidden" name="action" value="%s">', esc_attr( $action ) );
	wp_nonce_field( $action, '_tp_nonce', false );
}

function tp_account_signin( array $state ) {
	$old = (array) $state['old'];
	?>
	<?php if ( ! empty( $state['errors'] ) ) : ?>
		<div class="notice notice--error" role="alert" tabindex="-1" data-focus>
			<?php foreach ( $state['errors'] as $e ) : ?>
				<p><?php echo esc_html( $e ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<div class="account__auth">
		<section class="card" aria-labelledby="acc-signin">
			<h2 id="acc-signin"><?php esc_html_e( 'Sign in', 'taxi-peninsula' ); ?></h2>
			<?php wp_login_form( array( 'redirect' => tp_account_url(), 'label_username' => __( 'Email address', 'taxi-peninsula' ) ) ); ?>
			<p><a href="<?php echo esc_url( wp_lostpassword_url( tp_account_url() ) ); ?>"><?php esc_html_e( 'Forgotten your password?', 'taxi-peninsula' ); ?></a></p>
		</section>
		<section class="card" aria-labelledby="acc-join">
			<h2 id="acc-join"><?php esc_html_e( 'Create an account', 'taxi-peninsula' ); ?></h2>
			<ul class="tick-list">
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'See all your trips and their status in one place', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Rebook a regular trip in one tap', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Save passengers, wheelchair details and addresses', 'taxi-peninsula' ); ?></li>
				<li><?php tp_the_icon( 'check' ); ?><?php esc_html_e( 'Download receipts and invoices', 'taxi-peninsula' ); ?></li>
			</ul>
			<p class="field__hint acc-driver-note">
				<?php
				$tp_careers = tp_page_url_by_template( 'careers' );
				esc_html_e( 'Drivers: create a normal account, then the office gives it driver access.', 'taxi-peninsula' );
				if ( $tp_careers ) {
					echo ' <a href="' . esc_url( $tp_careers ) . '">' . esc_html__( 'Want to drive with us? Apply here.', 'taxi-peninsula' ) . '</a>';
				}
				?>
			</p>
			<form class="booking-form account__join" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<?php tp_account_form_fields( 'tp_account_register' ); ?>
				<fieldset class="radio-list">
					<legend class="field__label"><?php esc_html_e( 'I am booking for', 'taxi-peninsula' ); ?></legend>
					<label class="radio-row"><input type="radio" name="type" value="customer" <?php checked( ( $old['type'] ?? 'customer' ), 'customer' ); ?> data-toggle-org="0"> <span><?php esc_html_e( 'Myself or a family member', 'taxi-peninsula' ); ?></span></label>
					<label class="radio-row"><input type="radio" name="type" value="coordinator" <?php checked( ( $old['type'] ?? '' ), 'coordinator' ); ?> data-toggle-org="1"> <span><?php esc_html_e( 'Clients or residents (NDIS coordinator, aged care, hospital)', 'taxi-peninsula' ); ?></span></label>
				</fieldset>
				<div class="field" data-org-field>
					<label for="acc-org"><?php esc_html_e( 'Organisation name', 'taxi-peninsula' ); ?></label>
					<input id="acc-org" name="org" type="text" autocomplete="organization" value="<?php echo esc_attr( $old['org'] ?? '' ); ?>">
				</div>
				<div class="field">
					<label for="acc-name"><?php esc_html_e( 'Your full name', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
					<input id="acc-name" name="name" type="text" required autocomplete="name" value="<?php echo esc_attr( $old['name'] ?? '' ); ?>">
				</div>
				<div class="field">
					<label for="acc-email"><?php esc_html_e( 'Email', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
					<input id="acc-email" name="email" type="email" required autocomplete="email" value="<?php echo esc_attr( $old['email'] ?? '' ); ?>">
				</div>
				<div class="field">
					<label for="acc-phone"><?php esc_html_e( 'Mobile', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
					<input id="acc-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" value="<?php echo esc_attr( $old['phone'] ?? '' ); ?>">
				</div>
				<div class="field">
					<label for="acc-pass"><?php esc_html_e( 'Choose a password', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
					<input id="acc-pass" name="password" type="password" required minlength="10" autocomplete="new-password" aria-describedby="acc-pass-hint">
					<p class="field__hint" id="acc-pass-hint"><?php esc_html_e( 'At least 10 characters.', 'taxi-peninsula' ); ?></p>
				</div>
				<?php tp_spam_fields(); ?>
				<button type="submit" class="btn btn--accent"><?php esc_html_e( 'Create account', 'taxi-peninsula' ); ?></button>
			</form>
		</section>
	</div>
	<?php
}

/**
 * Trips: upcoming and past, with manage, rebook, receipt and rating.
 */
function tp_account_trips_tab( WP_User $user, $coord, array $state ) {
	$passengers = tp_passengers( $user->ID );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$filter = isset( $_GET['who'] ) ? sanitize_key( wp_unslash( $_GET['who'] ) ) : '';
	$meta   = array( array( 'key' => '_tp_customer_id', 'value' => $user->ID ) );
	if ( $filter && isset( $passengers[ $filter ] ) ) {
		$meta[] = array( 'key' => '_tp_passenger_key', 'value' => $filter );
	}
	$ids      = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 300,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array_merge( array( 'relation' => 'AND' ), $meta ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);
	$upcoming = array();
	$past     = array();
	foreach ( $ids as $id ) {
		$b = tp_get_booking( $id );
		// Active trips from yesterday on are "upcoming"; anything older or finished is past.
		if ( in_array( $b['status'], tp_active_statuses(), true ) && $b['date'] >= wp_date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
			$upcoming[] = $b;
		} else {
			$past[] = $b;
		}
	}
	$past = array_slice( array_reverse( $past ), 0, 50 );
	$next = $upcoming ? array_shift( $upcoming ) : null;
	$year = 0;
	foreach ( $past as $b ) {
		$year += ( 'completed' === $b['status'] && substr( $b['date'], 0, 4 ) === wp_date( 'Y' ) ) ? 1 : 0;
	}
	?>
	<?php if ( $next ) : ?>
		<?php tp_account_next_trip( $next ); ?>
	<?php endif; ?>
	<ul class="tp-tiles account__stats">
		<li><span class="tp-tiles__n"><?php echo esc_html( count( $upcoming ) + ( $next ? 1 : 0 ) ); ?></span><?php esc_html_e( 'Upcoming trips', 'taxi-peninsula' ); ?></li>
		<li><span class="tp-tiles__n"><?php echo esc_html( $year ); ?></span>
			<?php
			/* translators: %s: year */
			echo esc_html( sprintf( __( 'Trips in %s', 'taxi-peninsula' ), wp_date( 'Y' ) ) );
			?>
		</li>
		<li><span class="tp-tiles__n"><?php echo esc_html( count( $passengers ) ); ?></span><?php echo esc_html( $coord ? __( 'Saved clients', 'taxi-peninsula' ) : __( 'Saved passengers', 'taxi-peninsula' ) ); ?></li>
	</ul>
	<?php if ( $coord && $passengers ) : ?>
		<form class="account__filter" method="get" action="<?php echo esc_url( tp_account_url() ); ?>#account">
			<label for="acc-who"><?php esc_html_e( 'Show trips for', 'taxi-peninsula' ); ?></label>
			<select id="acc-who" name="who" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'All clients', 'taxi-peninsula' ); ?></option>
				<?php foreach ( $passengers as $pid => $p ) : ?>
					<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $filter, $pid ); ?>><?php echo esc_html( $p['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></button></noscript>
		</form>
	<?php endif; ?>

	<section aria-labelledby="acc-up">
		<h3 id="acc-up" class="account__title"><?php echo esc_html( $next ? __( 'Later trips', 'taxi-peninsula' ) : __( 'Upcoming trips', 'taxi-peninsula' ) ); ?></h3>
		<?php if ( ! $upcoming && $next ) : ?>
			<p class="account__none"><?php esc_html_e( 'Nothing else booked yet.', 'taxi-peninsula' ); ?></p>
		<?php elseif ( ! $upcoming ) : ?>
			<?php tp_the_empty_state( 'empty-list', __( 'No upcoming trips', 'taxi-peninsula' ), __( 'Where to next? Book online in about two minutes.', 'taxi-peninsula' ), array( __( 'Book a trip', 'taxi-peninsula' ), tp_booking_page_url() ) ); ?>
		<?php endif; ?>
		<?php foreach ( $upcoming as $b ) : ?>
			<?php tp_account_trip_card( $b, $passengers, $coord ); ?>
		<?php endforeach; ?>
	</section>

	<section aria-labelledby="acc-past">
		<h3 id="acc-past" class="account__title"><?php esc_html_e( 'Past trips', 'taxi-peninsula' ); ?></h3>
		<?php if ( ! $past ) : ?>
			<p><?php esc_html_e( 'Your finished trips will appear here, with receipts.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
		<?php foreach ( $past as $b ) : ?>
			<?php tp_account_trip_card( $b, $passengers, $coord ); ?>
		<?php endforeach; ?>
	</section>

	<details class="account__link card">
		<summary><?php esc_html_e( 'Add a booking you made before you had an account', 'taxi-peninsula' ); ?></summary>
		<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php tp_account_form_fields( 'tp_account_link' ); ?>
			<div class="field">
				<label for="acc-ref"><?php esc_html_e( 'Booking reference', 'taxi-peninsula' ); ?></label>
				<input id="acc-ref" name="ref" type="text" placeholder="TP-ABC123" autocomplete="off" required>
			</div>
			<div class="field">
				<label for="acc-lphone"><?php esc_html_e( 'Phone number on the booking', 'taxi-peninsula' ); ?></label>
				<input id="acc-lphone" name="phone" type="tel" inputmode="tel" required>
			</div>
			<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Add booking', 'taxi-peninsula' ); ?></button>
		</form>
	</details>
	<?php
}

/**
 * "Your next trip": big card with countdown, progress and the driver.
 */
function tp_account_next_trip( array $b ) {
	$start  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $b['date'] . ' ' . ( $b['time'] ?: '00:00' ), wp_timezone() );
	$driver = $b['driver_id'] ? tp_public_driver( $b['driver_id'] ) : null;
	$dname  = '';
	if ( $b['driver_id'] && ! $driver ) {
		$u     = get_userdata( $b['driver_id'] );
		$dname = $u ? ( $u->first_name ?: strtok( $u->display_name, ' ' ) ) : '';
	}
	$steps = array( 'confirmed', 'assigned', 'on_way', 'arrived' );
	$map   = array( 'pending' => -1, 'confirmed' => 0, 'assigned' => 1, 'accepted' => 1, 'on_way' => 2, 'arrived' => 3, 'onboard' => 3 );
	$pos   = $map[ $b['status'] ] ?? -1;
	$label = array(
		'confirmed' => __( 'Confirmed', 'taxi-peninsula' ),
		'assigned'  => __( 'Driver assigned', 'taxi-peninsula' ),
		'on_way'    => __( 'On the way', 'taxi-peninsula' ),
		'arrived'   => __( 'Arrived', 'taxi-peninsula' ),
	);
	?>
	<section class="next-trip" aria-labelledby="next-trip-title">
		<div class="next-trip__main">
			<p class="next-trip__kicker" id="next-trip-title"><?php esc_html_e( 'Your next trip', 'taxi-peninsula' ); ?></p>
			<p class="next-trip__when"><?php echo esc_html( tp_format_pickup( $b, 'l j F' ) ); ?><br><span><?php echo esc_html( tp_format_pickup( $b, 'g:i a' ) ); ?></span></p>
			<?php if ( $start ) : ?>
				<p class="next-trip__countdown" data-countdown="<?php echo esc_attr( $start->getTimestamp() ); ?>"><?php echo esc_html( human_time_diff( time(), $start->getTimestamp() ) ); ?></p>
			<?php endif; ?>
			<p class="next-trip__ref"><?php echo esc_html( $b['reference'] . ' · ' . $b['name'] ); ?></p>
			<p class="next-trip__route"><?php echo esc_html( $b['pickup'] ); ?> <span aria-hidden="true">→</span><span class="screen-reader-text"><?php esc_html_e( 'to', 'taxi-peninsula' ); ?></span> <?php echo esc_html( $b['dropoff'] ); ?></p>
			<ol class="next-trip__steps" aria-label="<?php esc_attr_e( 'Trip progress', 'taxi-peninsula' ); ?>">
				<?php foreach ( $steps as $i => $key ) : ?>
					<li class="<?php echo esc_attr( $i < $pos ? 'is-done' : ( $i === $pos ? 'is-current' : '' ) ); ?>"<?php echo $i === $pos ? ' aria-current="step"' : ''; ?>><?php echo esc_html( $label[ $key ] ); ?></li>
				<?php endforeach; ?>
			</ol>
			<p class="next-trip__actions">
				<a class="btn btn--accent" href="<?php echo esc_url( tp_booking_manage_url( $b['id'] ) ); ?>"><?php esc_html_e( 'View or change', 'taxi-peninsula' ); ?></a>
				<a class="btn btn--light" href="<?php echo esc_url( tp_booking_ics_url( $b['id'] ) ); ?>" download><?php tp_the_icon( 'calendar' ); ?> <?php esc_html_e( 'Add to calendar', 'taxi-peninsula' ); ?></a>
			</p>
		</div>
		<div class="next-trip__driver">
			<?php if ( $driver ) : ?>
				<?php echo tp_driver_avatar( $driver, 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<p><span><?php esc_html_e( 'Your driver', 'taxi-peninsula' ); ?></span><strong><?php echo esc_html( $driver['name'] ); ?></strong></p>
			<?php elseif ( $dname ) : ?>
				<span class="driver-card__initial" aria-hidden="true"><?php echo esc_html( mb_substr( $dname, 0, 1 ) ); ?></span>
				<p><span><?php esc_html_e( 'Your driver', 'taxi-peninsula' ); ?></span><strong><?php echo esc_html( $dname ); ?></strong></p>
			<?php else : ?>
				<?php echo tp_illustration( 'wheelchair', 'next-trip__illo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<p><span><?php esc_html_e( 'Driver', 'taxi-peninsula' ); ?></span><strong><?php esc_html_e( 'To be assigned', 'taxi-peninsula' ); ?></strong></p>
			<?php endif; ?>
			<?php if ( $b['fleet_id'] ) : ?>
				<p class="next-trip__vehicle"><?php echo esc_html( tp_fleet_label( $b['fleet_id'] ) ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

function tp_account_trip_card( array $b, array $passengers, $coord ) {
	$finished = in_array( $b['status'], tp_finished_statuses(), true );
	$book     = tp_booking_page_url();
	$pkey     = get_post_meta( $b['id'], '_tp_passenger_key', true );
	?>
	<article class="trip card trip--<?php echo esc_attr( $b['status'] ); ?>">
		<header class="trip__head">
			<p class="trip__when"><strong><?php echo esc_html( tp_format_pickup( $b ) ); ?></strong></p>
			<?php echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</header>
		<p class="trip__route"><?php echo esc_html( $b['pickup'] ); ?> <span aria-hidden="true">→</span><span class="screen-reader-text"><?php esc_html_e( 'to', 'taxi-peninsula' ); ?></span> <?php echo esc_html( $b['dropoff'] ); ?></p>
		<p class="trip__meta">
			<?php echo esc_html( $b['reference'] ); ?>
			<?php if ( $coord || ( $pkey && isset( $passengers[ $pkey ] ) ) ) : ?>
				· <?php echo esc_html( $b['name'] ); ?>
			<?php endif; ?>
		</p>
		<p class="trip__actions">
			<a class="btn btn--ghost" href="<?php echo esc_url( tp_booking_manage_url( $b['id'] ) ); ?>"><?php echo esc_html( $finished ? __( 'Details', 'taxi-peninsula' ) : __( 'View or change', 'taxi-peninsula' ) ); ?><span class="screen-reader-text"> <?php echo esc_html( $b['reference'] ); ?></span></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( 'rebook', $b['id'], $book ) . '#book' ); ?>"><?php esc_html_e( 'Book again', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $b['reference'] ); ?></span></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'rebook' => $b['id'], 'swap' => 1 ), $book ) . '#book' ); ?>"><?php esc_html_e( 'Book the return', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $b['reference'] ); ?></span></a>
			<?php if ( $finished || $b['paid'] > 0 ) : ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( tp_receipt_url( $b['id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( tp_document_title( $b ) ); ?><span class="screen-reader-text"> <?php echo esc_html( $b['reference'] ); ?> <?php esc_html_e( '(opens in a new tab)', 'taxi-peninsula' ); ?></span></a>
			<?php endif; ?>
		</p>
		<?php if ( 'completed' === $b['status'] ) : ?>
			<?php tp_rating_block( $b, tp_account_url() ); ?>
		<?php endif; ?>
	</article>
	<?php
}

function tp_account_passengers_tab( $coord ) {
	$list   = tp_passengers();
	$fields = tp_passenger_fields();
	$aids   = tp_mobility_aids();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
	$cur  = $list[ $edit ] ?? array();
	?>
	<p><?php echo esc_html( $coord ? __( 'Save each client once — their details and wheelchair notes fill in automatically when you book for them.', 'taxi-peninsula' ) : __( 'Save the people you book for, with their wheelchair details, so booking takes seconds.', 'taxi-peninsula' ) ); ?></p>
	<?php if ( $list ) : ?>
		<ul class="profile-list">
			<?php foreach ( $list as $id => $p ) : ?>
				<li class="card profile">
					<div>
						<strong><?php echo esc_html( $p['name'] ); ?></strong>
						<span><?php echo esc_html( implode( ' · ', array_filter( array( $p['phone'] ?? '', $aids[ $p['aid'] ?? '' ] ?? '', $p['ndis'] ? 'NDIS ' . $p['ndis'] : '' ) ) ) ); ?></span>
						<?php $notes = tp_passenger_notes( $p ); ?>
						<?php if ( $notes ) : ?>
							<span class="profile__notes"><?php echo esc_html( $notes ); ?></span>
						<?php endif; ?>
					</div>
					<p class="profile__actions">
						<a class="btn btn--ghost" href="<?php echo esc_url( tp_account_url( array( 'tab' => 'passengers', 'edit' => $id ) ) . '#acc-pform' ); ?>"><?php esc_html_e( 'Edit', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $p['name'] ); ?></span></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php tp_account_form_fields( 'tp_account_passenger' ); ?>
							<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
							<input type="hidden" name="delete" value="1">
							<button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Remove', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $p['name'] ); ?></span></button>
						</form>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<form class="booking-form card" id="acc-pform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h3 class="account__title"><?php echo esc_html( $cur ? __( 'Edit details', 'taxi-peninsula' ) : ( $coord ? __( 'Add a client', 'taxi-peninsula' ) : __( 'Add a passenger', 'taxi-peninsula' ) ) ); ?></h3>
		<?php tp_account_form_fields( 'tp_account_passenger' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $cur ? $edit : '' ); ?>">
		<div class="booking-form__group">
			<?php foreach ( $fields as $key => $f ) : ?>
				<?php
				if ( 'ndis' === $key && ! $coord && ! tp_setting( 'invoice_enabled' ) ) {
					continue;
				}
				$fid = 'pf-' . $key;
				$v   = $cur[ $key ] ?? '';
				?>
				<div class="field<?php echo in_array( $key, array( 'name', 'needs' ), true ) ? ' field--wide' : ''; ?>">
					<label for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $f[0] ); ?><?php echo $f[2] ? ' <span class="req" aria-hidden="true">*</span>' : ''; ?></label>
					<?php if ( 'aid' === $f[1] ) : ?>
						<select id="<?php echo esc_attr( $fid ); ?>" name="p[<?php echo esc_attr( $key ); ?>]">
							<?php foreach ( $aids as $ak => $al ) : ?>
								<option value="<?php echo esc_attr( $ak ); ?>" <?php selected( $v ?: 'manual', $ak ); ?>><?php echo esc_html( $al ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( 'textarea' === $f[1] ) : ?>
						<textarea id="<?php echo esc_attr( $fid ); ?>" name="p[<?php echo esc_attr( $key ); ?>]" rows="3" placeholder="<?php esc_attr_e( 'e.g. needs help with the front door, carer travels on Tuesdays, hearing impaired — please text', 'taxi-peninsula' ); ?>"><?php echo esc_textarea( $v ); ?></textarea>
					<?php else : ?>
						<input id="<?php echo esc_attr( $fid ); ?>" name="p[<?php echo esc_attr( $key ); ?>]" type="<?php echo esc_attr( $f[1] ); ?>" value="<?php echo esc_attr( $v ); ?>"<?php echo $f[2] ? ' required' : ''; ?><?php echo 'number' === $f[1] ? ' min="0" max="400" inputmode="numeric"' : ''; ?>>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Save', 'taxi-peninsula' ); ?></button>
		<?php if ( $cur ) : ?>
			<a class="btn btn--ghost" href="<?php echo esc_url( tp_account_url( array( 'tab' => 'passengers' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'taxi-peninsula' ); ?></a>
		<?php endif; ?>
	</form>
	<?php
}

function tp_account_addresses_tab() {
	$list = tp_saved_addresses();
	?>
	<p><?php esc_html_e( 'Addresses you use often — home, the hospital, a day program. Pick them from a list when you book.', 'taxi-peninsula' ); ?></p>
	<?php if ( $list ) : ?>
		<ul class="profile-list">
			<?php foreach ( $list as $id => $a ) : ?>
				<li class="card profile">
					<div><strong><?php echo esc_html( $a['label'] ); ?></strong><span><?php echo esc_html( $a['address'] ); ?></span></div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="profile__actions">
						<?php tp_account_form_fields( 'tp_account_address' ); ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
						<input type="hidden" name="delete" value="1">
						<button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Remove', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $a['label'] ); ?></span></button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<form class="booking-form card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h3 class="account__title"><?php esc_html_e( 'Add an address', 'taxi-peninsula' ); ?></h3>
		<?php tp_account_form_fields( 'tp_account_address' ); ?>
		<div class="booking-form__group">
			<div class="field">
				<label for="ad-label"><?php esc_html_e( 'Name', 'taxi-peninsula' ); ?></label>
				<input id="ad-label" name="label" type="text" placeholder="<?php esc_attr_e( 'e.g. Home, Dialysis', 'taxi-peninsula' ); ?>">
			</div>
			<div class="field field--wide">
				<label for="ad-address"><?php esc_html_e( 'Address', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
				<input id="ad-address" name="address" type="text" required autocomplete="street-address" <?php echo tp_places_available() ? 'data-places' : ''; ?>>
			</div>
		</div>
		<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Save address', 'taxi-peninsula' ); ?></button>
	</form>
	<?php
}

function tp_account_details_tab( WP_User $user, $coord ) {
	?>
	<form class="booking-form card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h3 class="account__title"><?php esc_html_e( 'Contact details', 'taxi-peninsula' ); ?></h3>
		<?php tp_account_form_fields( 'tp_account_details' ); ?>
		<div class="booking-form__group">
			<div class="field">
				<label for="ac-first"><?php esc_html_e( 'First name', 'taxi-peninsula' ); ?></label>
				<input id="ac-first" name="first_name" type="text" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>">
			</div>
			<div class="field">
				<label for="ac-last"><?php esc_html_e( 'Last name', 'taxi-peninsula' ); ?></label>
				<input id="ac-last" name="last_name" type="text" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>">
			</div>
			<div class="field">
				<label for="ac-phone"><?php esc_html_e( 'Mobile', 'taxi-peninsula' ); ?></label>
				<input id="ac-phone" name="phone" type="tel" autocomplete="tel" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tp_mobile', true ) ); ?>">
			</div>
			<div class="field">
				<label for="ac-email"><?php esc_html_e( 'Email (sign-in)', 'taxi-peninsula' ); ?></label>
				<input id="ac-email" type="email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly aria-describedby="ac-email-hint">
				<p class="field__hint" id="ac-email-hint">
					<?php
					/* translators: %s: phone */
					echo esc_html( sprintf( __( 'To change your email, call us on %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) );
					?>
				</p>
			</div>
			<?php if ( $coord ) : ?>
				<div class="field field--wide">
					<label for="ac-org"><?php esc_html_e( 'Organisation', 'taxi-peninsula' ); ?></label>
					<input id="ac-org" name="org" type="text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tp_org', true ) ); ?>">
				</div>
				<div class="field">
					<label for="ac-iname"><?php esc_html_e( 'Send invoices to (name)', 'taxi-peninsula' ); ?></label>
					<input id="ac-iname" name="invoice_name" type="text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tp_invoice_name', true ) ); ?>">
				</div>
				<div class="field">
					<label for="ac-iemail"><?php esc_html_e( 'Invoice email', 'taxi-peninsula' ); ?></label>
					<input id="ac-iemail" name="invoice_email" type="email" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tp_invoice_email', true ) ); ?>">
				</div>
			<?php endif; ?>
		</div>
		<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Save details', 'taxi-peninsula' ); ?></button>
	</form>

	<form class="booking-form card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h3 class="account__title"><?php esc_html_e( 'Change password', 'taxi-peninsula' ); ?></h3>
		<?php tp_account_form_fields( 'tp_account_password' ); ?>
		<div class="booking-form__group">
			<div class="field">
				<label for="ac-cur"><?php esc_html_e( 'Current password', 'taxi-peninsula' ); ?></label>
				<input id="ac-cur" name="current" type="password" autocomplete="current-password" required>
			</div>
			<div class="field">
				<label for="ac-new"><?php esc_html_e( 'New password (10+ characters)', 'taxi-peninsula' ); ?></label>
				<input id="ac-new" name="new" type="password" autocomplete="new-password" minlength="10" required>
			</div>
		</div>
		<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Change password', 'taxi-peninsula' ); ?></button>
	</form>
	<?php
	tp_account_privacy_panel( $user );
}

/* -------------------------------------------------------------------------
 * Account form handlers.
 * ---------------------------------------------------------------------- */

/**
 * Verify an account form, or bounce back.
 */
function tp_account_verify( $action, $logged_in = true ) {
	$ok = isset( $_POST['_tp_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), $action );
	if ( ! $ok || ( $logged_in && ! tp_is_account_user() ) ) {
		wp_safe_redirect( tp_account_url() );
		exit;
	}
}

function tp_account_back( $args, $errors = array(), $old = array() ) {
	if ( $errors ) {
		$key = strtolower( wp_generate_password( 16, false ) );
		set_transient( 'tp_form_' . $key, array( 'errors' => $errors, 'old' => $old ), 15 * MINUTE_IN_SECONDS );
		$args['tp_form'] = $key;
	}
	wp_safe_redirect( tp_account_url( $args ) . '#account' );
	exit;
}

add_action( 'admin_post_nopriv_tp_account_register', 'tp_handle_account_register' );
add_action( 'admin_post_tp_account_register', 'tp_handle_account_register' );
function tp_handle_account_register() {
	tp_account_verify( 'tp_account_register', false );
	$raw = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
	$old = array(
		'type'  => 'coordinator' === ( $raw['type'] ?? '' ) ? 'coordinator' : 'customer',
		'org'   => sanitize_text_field( $raw['org'] ?? '' ),
		'name'  => sanitize_text_field( $raw['name'] ?? '' ),
		'email' => sanitize_email( $raw['email'] ?? '' ),
		'phone' => sanitize_text_field( $raw['phone'] ?? '' ),
	);
	if ( tp_is_honeypot_hit() ) {
		tp_account_back( array() );
	}
	if ( ! tp_rate_limit( 'register', 5, HOUR_IN_SECONDS, false ) ) {
		tp_account_back( array(), array( __( 'Too many sign-ups from your connection. Please try again later or call us.', 'taxi-peninsula' ) ), $old );
	}
	if ( ! tp_verify_captcha() ) {
		tp_account_back( array(), array( tp_captcha_error_message() ), $old );
	}
	$errors   = array();
	$password = (string) ( $raw['password'] ?? '' );
	if ( '' === $old['name'] ) {
		$errors[] = __( 'Please enter your name.', 'taxi-peninsula' );
	}
	if ( ! is_email( $old['email'] ) ) {
		$errors[] = __( 'Please enter a valid email address.', 'taxi-peninsula' );
	} elseif ( email_exists( $old['email'] ) ) {
		$errors[] = __( 'There is already an account with that email. Please sign in, or use “Forgotten your password?”.', 'taxi-peninsula' );
	}
	if ( ! preg_match( '/^[0-9 +()\-]{8,20}$/', $old['phone'] ) ) {
		$errors[] = __( 'Please enter a valid mobile number.', 'taxi-peninsula' );
	}
	if ( strlen( $password ) < 10 ) {
		$errors[] = __( 'Please choose a password of at least 10 characters.', 'taxi-peninsula' );
	}
	if ( 'coordinator' === $old['type'] && '' === $old['org'] ) {
		$errors[] = __( 'Please enter your organisation’s name.', 'taxi-peninsula' );
	}
	if ( $errors ) {
		tp_account_back( array(), $errors, $old );
	}
	tp_rate_limit( 'register', 5, HOUR_IN_SECONDS, true );

	$parts   = explode( ' ', $old['name'], 2 );
	$login   = sanitize_user( strstr( $old['email'], '@', true ), true ) ?: 'customer';
	$base    = $login;
	$i       = 1;
	while ( username_exists( $login ) ) {
		$login = $base . ++$i;
	}
	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $old['email'],
			'user_pass'    => $password,
			'display_name' => $old['name'],
			'first_name'   => $parts[0],
			'last_name'    => $parts[1] ?? '',
			'role'         => 'coordinator' === $old['type'] ? 'tp_coordinator' : 'tp_customer',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		tp_account_back( array(), array( $user_id->get_error_message() ), $old );
	}
	update_user_meta( $user_id, 'tp_mobile', $old['phone'] );
	if ( 'coordinator' === $old['type'] ) {
		update_user_meta( $user_id, 'tp_org', $old['org'] );
		update_user_meta( $user_id, 'tp_invoice_name', $old['org'] );
		update_user_meta( $user_id, 'tp_invoice_email', $old['email'] );
		tp_in_site_lang(
			static function () use ( $old, $user_id ) {
				wp_mail(
					tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
					/* translators: %s: organisation */
					sprintf( __( 'New coordinator account: %s', 'taxi-peninsula' ), $old['org'] ),
					sprintf( "%s\n%s\n%s\n%s\n\n%s", $old['org'], $old['name'], $old['email'], $old['phone'], admin_url( 'user-edit.php?user_id=' . $user_id ) ),
					tp_mail_headers( $old['email'] )
				);
			}
		);
	}
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id );
	tp_account_back( array( 'saved' => 'welcome' ) );
}

add_action( 'admin_post_tp_account_passenger', 'tp_handle_account_passenger' );
function tp_handle_account_passenger() {
	tp_account_verify( 'tp_account_passenger' );
	$list = tp_passengers();
	$id   = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	if ( ! empty( $_POST['delete'] ) ) {
		unset( $list[ $id ] );
		update_user_meta( get_current_user_id(), 'tp_passengers', $list );
		tp_account_back( array( 'tab' => 'passengers', 'saved' => 'deleted' ) );
	}
	$raw = isset( $_POST['p'] ) && is_array( $_POST['p'] ) ? wp_unslash( $_POST['p'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
	$p   = array();
	foreach ( tp_passenger_fields() as $key => $f ) {
		$v = $raw[ $key ] ?? '';
		switch ( $f[1] ) {
			case 'number':
				$p[ $key ] = '' === $v ? '' : min( 400, absint( $v ) );
				break;
			case 'textarea':
				$p[ $key ] = sanitize_textarea_field( $v );
				break;
			case 'aid':
				$p[ $key ] = isset( tp_mobility_aids()[ $v ] ) ? $v : 'manual';
				break;
			default:
				$p[ $key ] = sanitize_text_field( $v );
		}
	}
	if ( '' === $p['name'] ) {
		tp_account_back( array( 'tab' => 'passengers' ), array( __( 'Please enter a name.', 'taxi-peninsula' ) ) );
	}
	if ( ! $id || ! isset( $list[ $id ] ) ) {
		if ( count( $list ) >= 200 ) {
			tp_account_back( array( 'tab' => 'passengers' ), array( __( 'You have reached the limit of saved people. Please remove some first.', 'taxi-peninsula' ) ) );
		}
		$id = 'p' . strtolower( wp_generate_password( 8, false ) );
	}
	$list[ $id ] = $p;
	uasort( $list, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
	update_user_meta( get_current_user_id(), 'tp_passengers', $list );
	tp_account_back( array( 'tab' => 'passengers', 'saved' => 'passenger' ) );
}

add_action( 'admin_post_tp_account_address', 'tp_handle_account_address' );
function tp_handle_account_address() {
	tp_account_verify( 'tp_account_address' );
	$user_id = get_current_user_id();
	if ( ! empty( $_POST['delete'] ) ) {
		$list = tp_saved_addresses();
		unset( $list[ isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '' ] );
		update_user_meta( $user_id, 'tp_addresses', $list );
		tp_account_back( array( 'tab' => 'addresses', 'saved' => 'deleted' ) );
	}
	$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
	if ( '' === $address ) {
		tp_account_back( array( 'tab' => 'addresses' ), array( __( 'Please enter an address.', 'taxi-peninsula' ) ) );
	}
	tp_add_saved_address( $user_id, $address, isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '' );
	tp_account_back( array( 'tab' => 'addresses', 'saved' => 'address' ) );
}

add_action( 'admin_post_tp_account_details', 'tp_handle_account_details' );
function tp_handle_account_details() {
	tp_account_verify( 'tp_account_details' );
	$user_id = get_current_user_id();
	$first   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last    = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	wp_update_user(
		array(
			'ID'           => $user_id,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => trim( $first . ' ' . $last ) ?: wp_get_current_user()->display_name,
		)
	);
	update_user_meta( $user_id, 'tp_mobile', isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
	if ( tp_is_coordinator() ) {
		update_user_meta( $user_id, 'tp_org', isset( $_POST['org'] ) ? sanitize_text_field( wp_unslash( $_POST['org'] ) ) : '' );
		update_user_meta( $user_id, 'tp_invoice_name', isset( $_POST['invoice_name'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_name'] ) ) : '' );
		update_user_meta( $user_id, 'tp_invoice_email', isset( $_POST['invoice_email'] ) ? sanitize_email( wp_unslash( $_POST['invoice_email'] ) ) : '' );
	}
	tp_account_back( array( 'tab' => 'details', 'saved' => 'details' ) );
}

add_action( 'admin_post_tp_account_password', 'tp_handle_account_password' );
function tp_handle_account_password() {
	tp_account_verify( 'tp_account_password' );
	$user    = wp_get_current_user();
	$current = isset( $_POST['current'] ) ? (string) wp_unslash( $_POST['current'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
	$new     = isset( $_POST['new'] ) ? (string) wp_unslash( $_POST['new'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
		tp_account_back( array( 'tab' => 'details' ), array( __( 'Your current password was not right.', 'taxi-peninsula' ) ) );
	}
	if ( strlen( $new ) < 10 ) {
		tp_account_back( array( 'tab' => 'details' ), array( __( 'Please choose a password of at least 10 characters.', 'taxi-peninsula' ) ) );
	}
	wp_set_password( $new, $user->ID );
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID );
	tp_account_back( array( 'tab' => 'details', 'saved' => 'password' ) );
}

add_action( 'admin_post_tp_account_link', 'tp_handle_account_link' );
function tp_handle_account_link() {
	tp_account_verify( 'tp_account_link' );
	if ( ! tp_rate_limit( 'link', 10, 15 * MINUTE_IN_SECONDS, false ) ) {
		tp_account_back( array(), array( __( 'Too many attempts. Please wait a few minutes.', 'taxi-peninsula' ) ) );
	}
	$ref     = isset( $_POST['ref'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['ref'] ) ) ) : '';
	$phone   = tp_phone_digits( isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
	$post_id = tp_find_booking( $ref );
	$stored  = $post_id ? tp_phone_digits( get_post_meta( $post_id, '_tp_phone', true ) ) : '';
	$owner   = $post_id ? (int) get_post_meta( $post_id, '_tp_customer_id', true ) : 0;
	if ( ! $post_id || strlen( $phone ) < 8 || ! hash_equals( substr( $stored, -8 ), substr( $phone, -8 ) ) || ( $owner && get_current_user_id() !== $owner ) ) {
		tp_rate_limit( 'link', 10, 15 * MINUTE_IN_SECONDS, true );
		tp_account_back( array(), array( __( 'We could not find a booking with that reference and phone number.', 'taxi-peninsula' ) ) );
	}
	$series = get_post_meta( $post_id, '_tp_series', true );
	foreach ( $series ? tp_series_ids( $series ) : array( $post_id ) as $id ) {
		if ( ! get_post_meta( $id, '_tp_customer_id', true ) ) {
			update_post_meta( $id, '_tp_customer_id', get_current_user_id() );
		}
	}
	tp_account_back( array( 'saved' => 'linked' ) );
}

/* -------------------------------------------------------------------------
 * Ratings.
 * ---------------------------------------------------------------------- */

/**
 * "How was your trip?" form, or the rating already given.
 *
 * @param array  $b      Booking.
 * @param string $return Page to come back to.
 */
function tp_rating_block( array $b, $return ) {
	if ( 'completed' !== $b['status'] ) {
		return;
	}
	if ( $b['rating'] ) {
		/* translators: %d: stars */
		echo '<p class="trip__rated">' . esc_html( sprintf( _n( 'You rated this trip %d star. Thank you!', 'You rated this trip %d stars. Thank you!', $b['rating'], 'taxi-peninsula' ), $b['rating'] ) ) . '</p>';
		tp_review_invite( $b );
		return;
	}
	$id = 'rate-' . $b['id'];
	?>
	<details class="rate" id="<?php echo esc_attr( $id ); ?>">
		<summary class="btn btn--ghost"><?php tp_the_icon( 'star' ); ?> <?php esc_html_e( 'Rate this trip', 'taxi-peninsula' ); ?></summary>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rate__form">
			<input type="hidden" name="action" value="tp_rate_trip">
			<input type="hidden" name="ref" value="<?php echo esc_attr( $b['reference'] ); ?>">
			<input type="hidden" name="t" value="<?php echo esc_attr( tp_booking_token( $b['id'] ) ); ?>">
			<input type="hidden" name="_tp_return" value="<?php echo esc_url( $return ); ?>">
			<?php wp_nonce_field( 'tp_rate_' . $b['id'], '_tp_nonce', false ); ?>
			<fieldset class="rate__stars">
				<legend class="field__label"><?php esc_html_e( 'How was your trip?', 'taxi-peninsula' ); ?></legend>
				<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
					<input type="radio" id="<?php echo esc_attr( $id . '-' . $i ); ?>" name="rating" value="<?php echo esc_attr( $i ); ?>" required>
					<label for="<?php echo esc_attr( $id . '-' . $i ); ?>" title="<?php /* translators: %d: stars */ echo esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'taxi-peninsula' ), $i ) ); ?>"><?php tp_the_icon( 'star' ); ?><span class="screen-reader-text"><?php /* translators: %d: stars */ echo esc_html( sprintf( _n( '%d star', '%d stars', $i, 'taxi-peninsula' ), $i ) ); ?></span></label>
				<?php endfor; ?>
			</fieldset>
			<div class="field">
				<label for="<?php echo esc_attr( $id ); ?>-c"><?php esc_html_e( 'Anything you would like to tell us? (optional)', 'taxi-peninsula' ); ?></label>
				<textarea id="<?php echo esc_attr( $id ); ?>-c" name="comment" rows="2" maxlength="1000"></textarea>
			</div>
			<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Send rating', 'taxi-peninsula' ); ?></button>
		</form>
	</details>
	<?php
}

add_action( 'admin_post_nopriv_tp_rate_trip', 'tp_handle_rate_trip' );
add_action( 'admin_post_tp_rate_trip', 'tp_handle_rate_trip' );
function tp_handle_rate_trip() {
	$post_id = tp_lookup_posted_booking();
	$return  = isset( $_POST['_tp_return'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ), tp_account_url() ) : tp_account_url();
	if ( ! $post_id || ! isset( $_POST['_tp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_nonce'] ) ), 'tp_rate_' . $post_id ) ) {
		wp_safe_redirect( $return );
		exit;
	}
	$rating = isset( $_POST['rating'] ) ? max( 1, min( 5, absint( $_POST['rating'] ) ) ) : 0;
	if ( $rating && 'completed' === get_post_meta( $post_id, '_tp_status', true ) && ! get_post_meta( $post_id, '_tp_rating', true ) ) {
		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
		update_post_meta( $post_id, '_tp_rating', $rating );
		update_post_meta( $post_id, '_tp_rating_comment', $comment );
		update_post_meta( $post_id, '_tp_rated_at', current_time( 'mysql' ) );
		tp_in_site_lang(
			static function () use ( $post_id, $rating, $comment ) {
				/* translators: 1: stars, 2: comment */
				tp_add_log( $post_id, array( 'note' => sprintf( __( 'Customer rating: %1$d★ %2$s', 'taxi-peninsula' ), $rating, $comment ) ) );
			}
		);
		tp_review_invite_email( $post_id );
		if ( $rating <= 3 ) {
			$b = tp_get_booking( $post_id );
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				/* translators: 1: stars, 2: reference */
				sprintf( __( 'Low rating (%1$d★) for %2$s', 'taxi-peninsula' ), $rating, $b['reference'] ),
				$comment . "\n\n" . tp_booking_summary( $b ) . "\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				tp_mail_headers( $b['email'] )
			);
		}
	}
	$args = false !== strpos( $return, 'ref=' ) ? array( 'rated' => 1 ) : array( 'saved' => 'rated' );
	wp_safe_redirect( add_query_arg( $args, $return ) );
	exit;
}

/* -------------------------------------------------------------------------
 * Receipts and tax invoices.
 * ---------------------------------------------------------------------- */

function tp_receipt_url( $post_id ) {
	return add_query_arg(
		array(
			'tp_receipt' => (int) $post_id,
			't'          => tp_booking_token( $post_id ),
		),
		home_url( '/' )
	);
}

/**
 * "Tax invoice" (fare + ABN set), "Receipt" (fare set) or "Trip summary".
 */
function tp_document_title( array $b ) {
	if ( '' !== (string) $b['fare'] && tp_setting( 'abn' ) ) {
		return __( 'Tax invoice', 'taxi-peninsula' );
	}
	return '' !== (string) $b['fare'] ? __( 'Receipt', 'taxi-peninsula' ) : __( 'Trip summary', 'taxi-peninsula' );
}

add_action( 'template_redirect', 'tp_render_receipt', 1 );
function tp_render_receipt() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- access controlled by HMAC token or capability.
	if ( empty( $_GET['tp_receipt'] ) ) {
		return;
	}
	$post_id = absint( $_GET['tp_receipt'] );
	$token   = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '';
	// phpcs:enable
	$allowed = 'tp_booking' === get_post_type( $post_id ) && (
		( $token && hash_equals( tp_booking_token( $post_id ), $token ) )
		|| tp_user_owns_booking( $post_id )
		|| current_user_can( 'tp_view_finance' )
		|| current_user_can( 'edit_post', $post_id )
	);
	if ( ! $allowed ) {
		wp_die( esc_html__( 'This receipt link is not valid.', 'taxi-peninsula' ), '', array( 'response' => 403 ) );
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );

	$b        = tp_get_booking( $post_id );
	$title    = tp_document_title( $b );
	$fare     = '' !== (string) $b['fare'] ? (float) $b['fare'] : null;
	$gst      = null !== $fare ? round( $fare / 11, 2 ) : null;
	$balance  = null !== $fare ? max( 0, $fare - $b['paid'] ) : null;
	$site     = get_bloginfo( 'name' );
	$abn      = tp_setting( 'abn' );
	$address  = tp_setting( 'business_address' );
	$issued   = tp_trip_time( $post_id, 'completed' ) ?: time();
	$vehicles = tp_vehicle_types();
	$c        = tp_design_colors();
	$paid_in  = 'paid' === $b['invoice'] || ( 'account' !== $b['payment'] && 'completed' === $b['status'] );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title . ' ' . $b['reference'] . ' — ' . $site ); ?></title>
	<style>
		body { font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #16213a; background: #eef2f7; margin: 0; padding: 24px; }
		.doc { max-width: 760px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 24px rgb(0 0 0 / .08); }
		.doc__brand { display: flex; align-items: center; gap: 12px; margin: -40px -40px 28px; padding: 20px 40px; background: <?php echo esc_attr( $c['primary'] ); ?>; border-radius: 12px 12px 0 0; color: #fff; font-size: 20px; }
		.doc__logo { height: 44px; width: auto; background: #fff; padding: 6px 10px; border-radius: 8px; }
		.doc__mark { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 10px; background: <?php echo esc_attr( $c['accent'] ); ?>; color: <?php echo esc_attr( $c['on_accent'] ); ?>; }
		.doc__mark svg { width: 28px; height: 28px; }
		@media (max-width: 600px) { .doc__brand { margin: -20px -20px 20px; padding: 16px 20px; } }
		@media print { .doc__brand { margin: 0 0 20px; border-radius: 8px; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
		.doc__head { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; border-bottom: 4px solid <?php echo esc_attr( $c['accent'] ); ?>; padding-bottom: 16px; margin-bottom: 24px; }
		h1 { margin: 0; font-size: 28px; color: <?php echo esc_attr( $c['primary'] ); ?>; }
		h2 { font-size: 15px; text-transform: uppercase; letter-spacing: .05em; color: #4a5670; margin: 0 0 4px; }
		.biz { text-align: right; } .biz strong { font-size: 18px; color: <?php echo esc_attr( $c['primary'] ); ?>; }
		.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
		table { width: 100%; border-collapse: collapse; margin: 16px 0; }
		th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #d5dcea; vertical-align: top; }
		td.n, th.n { text-align: right; white-space: nowrap; }
		tfoot td { font-weight: 700; }
		.total td { font-size: 18px; border-top: 2px solid #16213a; }
		.note { color: #4a5670; font-size: 14px; }
		.stamp { display: inline-block; padding: 4px 12px; border: 2px solid #0f7a3a; color: #0f7a3a; border-radius: 6px; font-weight: 700; text-transform: uppercase; }
		.actions { max-width: 760px; margin: 16px auto 0; display: flex; gap: 12px; }
		.actions button, .actions a { font: inherit; font-weight: 700; padding: 10px 18px; border-radius: 8px; border: 2px solid <?php echo esc_attr( $c['primary'] ); ?>; background: <?php echo esc_attr( $c['primary'] ); ?>; color: #fff; cursor: pointer; text-decoration: none; }
		.actions a { background: #fff; color: <?php echo esc_attr( $c['primary'] ); ?>; }
		@media (max-width: 600px) { body { padding: 8px; } .doc { padding: 20px; } .cols { grid-template-columns: 1fr; } .biz { text-align: left; } }
		@media print { body { background: #fff; padding: 0; } .doc { box-shadow: none; padding: 0; } .actions { display: none; } }
	</style>
</head>
<body>
	<main class="doc">
		<div class="doc__brand">
			<?php $tp_logo = (int) get_theme_mod( 'custom_logo' ); ?>
			<?php if ( $tp_logo ) : ?>
				<?php echo wp_get_attachment_image( $tp_logo, 'medium', false, array( 'alt' => $site, 'class' => 'doc__logo' ) ); ?>
			<?php else : ?>
				<span class="doc__mark"><?php echo tp_icon( 'wheelchair' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><strong><?php echo esc_html( $site ); ?></strong>
			<?php endif; ?>
		</div>
		<header class="doc__head">
			<div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p>
					<?php esc_html_e( 'Number', 'taxi-peninsula' ); ?>: <strong><?php echo esc_html( $b['reference'] ); ?></strong><br>
					<?php esc_html_e( 'Date', 'taxi-peninsula' ); ?>: <?php echo esc_html( wp_date( 'j F Y', $issued ) ); ?>
				</p>
			</div>
			<div class="biz">
				<strong><?php echo esc_html( $site ); ?></strong><br>
				<?php if ( $abn ) : ?>
					ABN <?php echo esc_html( $abn ); ?><br>
				<?php endif; ?>
				<?php echo $address ? nl2br( esc_html( $address ) ) . '<br>' : ''; ?>
				<?php echo esc_html( tp_opt( 'phone_display' ) ); ?><br>
				<?php echo esc_html( tp_opt( 'email' ) ); ?>
			</div>
		</header>

		<div class="cols">
			<section>
				<h2><?php echo esc_html( 'account' === $b['payment'] ? __( 'Bill to', 'taxi-peninsula' ) : __( 'Passenger', 'taxi-peninsula' ) ); ?></h2>
				<p>
					<?php if ( 'account' === $b['payment'] && $b['invoice_name'] ) : ?>
						<strong><?php echo esc_html( $b['invoice_name'] ); ?></strong><br>
						<?php echo esc_html( $b['invoice_email'] ); ?><br>
						<?php esc_html_e( 'Passenger', 'taxi-peninsula' ); ?>: <?php echo esc_html( $b['name'] ); ?><br>
						<?php if ( $b['ndis_number'] ) : ?>
							<?php esc_html_e( 'NDIS number / reference', 'taxi-peninsula' ); ?>: <?php echo esc_html( $b['ndis_number'] ); ?>
						<?php endif; ?>
					<?php else : ?>
						<strong><?php echo esc_html( $b['name'] ); ?></strong><br><?php echo esc_html( $b['email'] ); ?>
					<?php endif; ?>
				</p>
			</section>
			<section>
				<h2><?php esc_html_e( 'Trip', 'taxi-peninsula' ); ?></h2>
				<p>
					<?php echo esc_html( tp_format_pickup( $b ) ); ?><br>
					<?php echo esc_html( $vehicles[ $b['vehicle'] ] ?? $b['vehicle'] ); ?><br>
					<?php echo esc_html( tp_statuses()[ $b['status'] ] ?? $b['status'] ); ?>
				</p>
			</section>
		</div>

		<table>
			<thead><tr><th scope="col"><?php esc_html_e( 'Description', 'taxi-peninsula' ); ?></th><th scope="col" class="n"><?php esc_html_e( 'Amount (incl. GST)', 'taxi-peninsula' ); ?></th></tr></thead>
			<tbody>
				<tr>
					<td>
						<?php esc_html_e( 'Wheelchair accessible taxi', 'taxi-peninsula' ); ?><br>
						<span class="note"><?php echo esc_html( $b['pickup'] ); ?> → <?php echo esc_html( $b['dropoff'] ); ?><?php echo $b['distance_km'] > 0 ? ' (' . esc_html( number_format_i18n( $b['distance_km'], 1 ) ) . ' km)' : ''; ?></span>
					</td>
					<?php $tp_disc = (float) get_post_meta( $post_id, '_tp_discount', true ); ?>
					<td class="n"><?php echo null !== $fare ? esc_html( tp_money( $tp_disc > 0 ? $fare + $tp_disc : $fare ) ) : '—'; ?></td>
				</tr>
				<?php if ( null !== $fare && $tp_disc > 0 ) : ?>
					<tr>
						<td>
							<?php
							/* translators: %s: promo code */
							echo esc_html( sprintf( __( 'Promo code %s', 'taxi-peninsula' ), $b['promo'] ) );
							?>
						</td>
						<td class="n">−<?php echo esc_html( tp_money( $tp_disc ) ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
			<?php if ( null !== $fare ) : ?>
				<tfoot>
					<tr><td><?php esc_html_e( 'GST included', 'taxi-peninsula' ); ?></td><td class="n"><?php echo esc_html( tp_money( $gst ) ); ?></td></tr>
					<?php if ( $b['paid'] > 0 ) : ?>
						<tr><td><?php esc_html_e( 'Paid online (deposit)', 'taxi-peninsula' ); ?></td><td class="n">−<?php echo esc_html( tp_money( $b['paid'] ) ); ?></td></tr>
					<?php endif; ?>
					<tr class="total">
						<td><?php echo esc_html( 'account' === $b['payment'] && ! $paid_in ? __( 'Amount due', 'taxi-peninsula' ) : __( 'Total paid', 'taxi-peninsula' ) ); ?></td>
						<td class="n"><?php echo esc_html( tp_money( 'account' === $b['payment'] && ! $paid_in ? $balance : $fare ) ); ?></td>
					</tr>
				</tfoot>
			<?php endif; ?>
		</table>
		<style>.bpay-box{display:inline-block;margin:8px 0;padding:10px 16px;border:2px solid #1b3664;border-radius:8px;min-width:260px}.bpay-box p{margin:2px 0}.bpay-box__brand{font-weight:800;font-style:italic;color:#1b3664;font-size:18px}.bpay-box__small{font-size:11px;color:#4a5670;max-width:340px}.pay-options__list{padding-left:18px}</style>
		<?php do_action( 'tp_receipt_after_totals', $b, ( null !== $fare && 'account' === $b['payment'] && ! $paid_in ) ? (float) $balance : 0 ); ?>

		<?php if ( $paid_in && null !== $fare ) : ?>
			<p><span class="stamp"><?php esc_html_e( 'Paid', 'taxi-peninsula' ); ?></span></p>
		<?php endif; ?>
		<?php if ( null === $fare ) : ?>
			<p class="note"><?php esc_html_e( 'The fare has not been entered yet. If you need a tax invoice for this trip, please contact us and quote the number above.', 'taxi-peninsula' ); ?></p>
		<?php elseif ( ! $abn ) : ?>
			<p class="note"><?php esc_html_e( 'This is a receipt, not a tax invoice.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
		<?php if ( $b['mptp'] ) : ?>
			<p class="note"><?php esc_html_e( 'Multi Purpose Taxi Program (MPTP) member.', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
		<?php if ( 'account' === $b['payment'] && ! $paid_in && tp_setting( 'payment_terms' ) ) : ?>
			<p class="note"><?php echo nl2br( esc_html( tp_setting( 'payment_terms' ) ) ); ?></p>
		<?php endif; ?>
	</main>
	<div class="actions">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'taxi-peninsula' ); ?></button>
		<a href="<?php echo esc_url( tp_booking_manage_url( $post_id ) ); ?>"><?php esc_html_e( 'Back to booking', 'taxi-peninsula' ); ?></a>
	</div>
</body>
</html>
	<?php
	exit;
}

/**
 * Header "My account" menu: one place for everyone — passengers,
 * coordinators, drivers and staff — to sign in or register, plus the
 * contact details.
 */
function tp_the_account_menu() {
	$account = tp_page_url_by_template( 'account' );
	$lookup  = tp_page_url_by_template( 'lookup' );
	$driver  = tp_page_url_by_template( 'driver' );
	$user    = wp_get_current_user();
	$label   = $user->exists() ? ( $user->first_name ?: $user->display_name ) : __( 'My account', 'taxi-peninsula' );
	$links   = array();
	if ( ! $user->exists() ) {
		$signin = $account ?: wp_login_url();
		$links[] = array( $signin . ( $account ? '#acc-signin' : '' ), __( 'Sign in', 'taxi-peninsula' ), 'users', 'primary' );
		if ( $account ) {
			$links[] = array( $account . '#acc-join', __( 'Create an account', 'taxi-peninsula' ), 'check', '' );
		}
	} else {
		if ( tp_is_account_user() && $account ) {
			$links[] = array( $account, tp_is_coordinator() ? __( 'My clients’ trips', 'taxi-peninsula' ) : __( 'My trips', 'taxi-peninsula' ), 'calendar', 'primary' );
		}
		if ( current_user_can( 'tp_driver_jobs' ) && $driver ) {
			$links[] = array( $driver, __( 'My jobs', 'taxi-peninsula' ), 'wheelchair', 'primary' );
		}
		if ( current_user_can( 'edit_tp_bookings' ) ) {
			$links[] = array( admin_url( 'admin.php?page=tp-dispatch' ), __( 'Dispatch board', 'taxi-peninsula' ), 'calendar', 'primary' );
		} elseif ( current_user_can( 'edit_posts' ) || current_user_can( 'tp_view_finance' ) ) {
			$links[] = array( admin_url(), __( 'Dashboard', 'taxi-peninsula' ), 'calendar', 'primary' );
		}
	}
	if ( $lookup ) {
		$links[] = array( $lookup, __( 'Manage a booking', 'taxi-peninsula' ), 'search', '' );
	}
	$id = 'acct-menu';
	?>
	<div class="acct" data-acct>
		<button type="button" class="acct__btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>">
			<?php tp_the_icon( 'users' ); ?><span class="acct__label"><?php echo esc_html( $label ); ?></span><?php tp_the_icon( 'chevron-down', 'acct__chev' ); ?>
		</button>
		<div class="acct__menu" id="<?php echo esc_attr( $id ); ?>" hidden>
			<?php if ( ! $user->exists() ) : ?>
				<p class="acct__intro"><?php esc_html_e( 'Passengers, coordinators and drivers all sign in here.', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
			<ul class="acct__list">
				<?php foreach ( $links as $l ) : ?>
					<li><a class="acct__link<?php echo 'primary' === $l[3] ? ' acct__link--primary' : ''; ?>" href="<?php echo esc_url( $l[0] ); ?>"><?php tp_the_icon( $l[2] ); ?><span><?php echo esc_html( $l[1] ); ?></span></a></li>
				<?php endforeach; ?>
				<?php if ( $user->exists() ) : ?>
					<li><a class="acct__link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php tp_the_icon( 'arrow' ); ?><span><?php esc_html_e( 'Sign out', 'taxi-peninsula' ); ?></span></a></li>
				<?php endif; ?>
			</ul>
			<p class="acct__heading"><?php esc_html_e( 'Contact us', 'taxi-peninsula' ); ?></p>
			<ul class="acct__list">
				<li><a class="acct__link" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><span><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></span></a></li>
				<li><a class="acct__link" href="<?php echo esc_attr( tp_email_href() ); ?>"><?php tp_the_icon( 'mail' ); ?><span><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></span></a></li>
				<?php if ( tp_sms_href() ) : ?>
					<li><a class="acct__link" href="<?php echo esc_attr( tp_sms_href() ); ?>"><?php tp_the_icon( 'message' ); ?><span><?php esc_html_e( 'Text us', 'taxi-peninsula' ); ?></span></a></li>
				<?php endif; ?>
				<?php do_action( 'tp_account_menu_contact' ); ?>
			</ul>
		</div>
	</div>
	<?php
}
