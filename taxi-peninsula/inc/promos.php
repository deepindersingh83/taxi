<?php
/**
 * Promo codes: first-trip and seasonal offers.
 *
 * Bookings → Promo codes. Each code is a percentage or a dollar amount off,
 * with optional start / end dates, a use limit and "first trip only". The
 * customer types the code on the booking form; it is checked straight away.
 * The discount is taken off when the office enters the fare, and shows on
 * the receipt, the Finance page and its CSV.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_promo',
			array(
				'labels'          => array(
					'name'          => __( 'Promo codes', 'taxi-peninsula' ),
					'singular_name' => __( 'Promo code', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Add promo code', 'taxi-peninsula' ),
					'edit_item'     => __( 'Edit promo code', 'taxi-peninsula' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => current_user_can( 'edit_tp_bookings' ) ? 'edit.php?post_type=tp_booking' : true,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'tp_promo', 'tp_promos' ),
				'map_meta_cap'    => true,
				'menu_icon'       => 'dashicons-tag',
			)
		);
	}
);

/**
 * Promo settings for a code post.
 */
function tp_promo_meta( $id ) {
	return array(
		'kind'   => get_post_meta( $id, '_tp_promo_kind', true ) ?: 'percent',
		'amount' => (float) get_post_meta( $id, '_tp_promo_amount', true ),
		'start'  => (string) get_post_meta( $id, '_tp_promo_start', true ),
		'end'    => (string) get_post_meta( $id, '_tp_promo_end', true ),
		'max'    => (int) get_post_meta( $id, '_tp_promo_max', true ),
		'first'  => (bool) get_post_meta( $id, '_tp_promo_first', true ),
		'text'   => (string) get_post_meta( $id, '_tp_promo_text', true ),
	);
}

/**
 * Find a published promo by code (case-insensitive). Returns post ID or 0.
 */
function tp_promo_find( $code ) {
	$code = strtoupper( trim( (string) $code ) );
	if ( '' === $code ) {
		return 0;
	}
	foreach ( get_posts( array( 'post_type' => 'tp_promo', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		if ( strtoupper( get_the_title( $id ) ) === $code ) {
			return (int) $id;
		}
	}
	return 0;
}

function tp_promos_active() {
	return (bool) get_posts( array( 'post_type' => 'tp_promo', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
}

/**
 * Number of bookings that used a code.
 */
function tp_promo_uses( $code ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_tp_promo' AND m.meta_value = %s AND p.post_type = 'tp_booking' AND p.post_status = 'publish'", strtoupper( $code ) ) );
}

/**
 * Check a code against a booking. Returns '' when fine, or an error message.
 *
 * @param string $code Code typed by the customer.
 * @param array  $d    Sanitized booking data.
 */
function tp_promo_check( $code, array $d ) {
	if ( '' === (string) $code ) {
		return '';
	}
	$id = tp_promo_find( $code );
	if ( ! $id ) {
		return __( 'That promo code was not found. Check the spelling or leave it empty.', 'taxi-peninsula' );
	}
	$m     = tp_promo_meta( $id );
	$today = wp_date( 'Y-m-d' );
	$trip  = $d['date'] ?? $today;
	if ( ( $m['start'] && $trip < $m['start'] ) || ( $m['end'] && $today > $m['end'] ) ) {
		return __( 'That promo code is not valid for this date.', 'taxi-peninsula' );
	}
	if ( $m['max'] > 0 && tp_promo_uses( $code ) >= $m['max'] ) {
		return __( 'That promo code has been fully used.', 'taxi-peninsula' );
	}
	if ( $m['first'] && tp_promo_customer_has_trips( $d ) ) {
		return __( 'That promo code is for your first trip with us only.', 'taxi-peninsula' );
	}
	return '';
}

/**
 * Has this customer (by phone, email or account) booked before?
 */
function tp_promo_customer_has_trips( array $d ) {
	$or = array( 'relation' => 'OR' );
	if ( ! empty( $d['phone'] ) ) {
		$or[] = array( 'key' => '_tp_phone', 'value' => $d['phone'] );
	}
	if ( ! empty( $d['email'] ) ) {
		$or[] = array( 'key' => '_tp_email', 'value' => $d['email'] );
	}
	if ( is_user_logged_in() ) {
		$or[] = array( 'key' => '_tp_customer_id', 'value' => get_current_user_id() );
	}
	if ( count( $or ) < 2 ) {
		return false;
	}
	return (bool) get_posts( array( 'post_type' => 'tp_booking', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => $or ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
}

/**
 * Short description, e.g. "10% off" or "$15 off".
 */
function tp_promo_label( $code ) {
	$id = tp_promo_find( $code );
	if ( ! $id ) {
		return '';
	}
	$m = tp_promo_meta( $id );
	/* translators: %s: discount */
	$off = sprintf( __( '%s off', 'taxi-peninsula' ), 'fixed' === $m['kind'] ? tp_money( $m['amount'] ) : rtrim( rtrim( number_format( $m['amount'], 2, '.', '' ), '0' ), '.' ) . '%' );
	return $m['text'] ? $off . ' — ' . $m['text'] : $off;
}

/**
 * Discount in dollars for a full fare.
 */
function tp_promo_discount( $code, $full ) {
	$id = tp_promo_find( $code );
	if ( ! $id || $full <= 0 ) {
		return 0.0;
	}
	$m   = tp_promo_meta( $id );
	$off = 'fixed' === $m['kind'] ? $m['amount'] : $full * $m['amount'] / 100;
	return round( min( $full, max( 0, $off ) ), 2 );
}

/**
 * Save the fare entered by the office. The promo discount (if any) is taken
 * off: `_tp_fare` is what the customer pays, `_tp_fare_full` the fare before
 * the discount and `_tp_discount` the difference.
 *
 * @param int          $post_id Booking ID.
 * @param string|float $full    Full fare, or '' to clear.
 * @return string|float The fare to pay.
 */
function tp_set_fare( $post_id, $full ) {
	if ( '' === $full ) {
		update_post_meta( $post_id, '_tp_fare', '' );
		delete_post_meta( $post_id, '_tp_fare_full' );
		delete_post_meta( $post_id, '_tp_discount' );
		return '';
	}
	$full     = round( max( 0, (float) $full ), 2 );
	$discount = tp_promo_discount( get_post_meta( $post_id, '_tp_promo', true ), $full );
	$pay      = round( $full - $discount, 2 );
	update_post_meta( $post_id, '_tp_fare', $pay );
	if ( $discount > 0 ) {
		update_post_meta( $post_id, '_tp_fare_full', $full );
		update_post_meta( $post_id, '_tp_discount', $discount );
	} else {
		delete_post_meta( $post_id, '_tp_fare_full' );
		delete_post_meta( $post_id, '_tp_discount' );
	}
	return $pay;
}

/**
 * The fare the office typed (before the discount), for the edit boxes.
 */
function tp_fare_input_value( $post_id ) {
	$full = get_post_meta( $post_id, '_tp_fare_full', true );
	return '' !== (string) $full ? $full : get_post_meta( $post_id, '_tp_fare', true );
}

/* -------------------------------------------------------------------------
 * Admin: code settings.
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tp_promo',
	static function () {
		add_meta_box( 'tp_promo_box', __( 'Offer', 'taxi-peninsula' ), 'tp_render_promo_box', 'tp_promo', 'normal', 'high' );
	}
);

function tp_render_promo_box( $post ) {
	$m = tp_promo_meta( $post->ID );
	wp_nonce_field( 'tp_promo_save', 'tp_promo_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'The title above is the code customers type, e.g. FIRSTRIDE or WINTER10. Letters and numbers only.', 'taxi-peninsula' ); ?></p>
	<table class="form-table" role="presentation">
		<tr><th><label for="tp_promo_kind"><?php esc_html_e( 'Discount', 'taxi-peninsula' ); ?></label></th>
			<td>
				<input type="number" step="0.01" min="0" id="tp_promo_amount" name="tp_promo_amount" value="<?php echo esc_attr( $m['amount'] ?: '' ); ?>" class="small-text" aria-label="<?php esc_attr_e( 'Amount', 'taxi-peninsula' ); ?>">
				<select id="tp_promo_kind" name="tp_promo_kind">
					<option value="percent" <?php selected( $m['kind'], 'percent' ); ?>><?php esc_html_e( '% off the fare', 'taxi-peninsula' ); ?></option>
					<option value="fixed" <?php selected( $m['kind'], 'fixed' ); ?>><?php esc_html_e( '$ off the fare', 'taxi-peninsula' ); ?></option>
				</select>
			</td></tr>
		<tr><th><label for="tp_promo_start"><?php esc_html_e( 'Valid for trips from', 'taxi-peninsula' ); ?></label></th>
			<td><input type="date" id="tp_promo_start" name="tp_promo_start" value="<?php echo esc_attr( $m['start'] ); ?>"> <label for="tp_promo_end"><?php esc_html_e( 'Can be used until', 'taxi-peninsula' ); ?></label> <input type="date" id="tp_promo_end" name="tp_promo_end" value="<?php echo esc_attr( $m['end'] ); ?>">
			<p class="description"><?php esc_html_e( 'Leave empty for no limit.', 'taxi-peninsula' ); ?></p></td></tr>
		<tr><th><label for="tp_promo_max"><?php esc_html_e( 'Maximum uses', 'taxi-peninsula' ); ?></label></th>
			<td><input type="number" min="0" id="tp_promo_max" name="tp_promo_max" value="<?php echo esc_attr( $m['max'] ?: '' ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Across all customers. Empty = unlimited.', 'taxi-peninsula' ); ?></span></td></tr>
		<tr><th><?php esc_html_e( 'First trip only', 'taxi-peninsula' ); ?></th>
			<td><label><input type="checkbox" name="tp_promo_first" value="1" <?php checked( $m['first'] ); ?>> <?php esc_html_e( 'Only for customers who have not booked before (matched by phone, email or account)', 'taxi-peninsula' ); ?></label></td></tr>
		<tr><th><label for="tp_promo_text"><?php esc_html_e( 'Short description', 'taxi-peninsula' ); ?></label></th>
			<td><input type="text" id="tp_promo_text" name="tp_promo_text" value="<?php echo esc_attr( $m['text'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Winter offer', 'taxi-peninsula' ); ?>"></td></tr>
	</table>
	<p>
		<?php
		/* translators: %d: number of bookings */
		echo esc_html( sprintf( _n( 'Used on %d booking so far.', 'Used on %d bookings so far.', tp_promo_uses( $post->post_title ), 'taxi-peninsula' ), tp_promo_uses( $post->post_title ) ) );
		?>
	</p>
	<?php
}

add_action(
	'save_post_tp_promo',
	static function ( $post_id, $post ) {
		if ( ! isset( $_POST['tp_promo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_promo_nonce'] ), 'tp_promo_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $post->post_title ) );
		if ( $code !== $post->post_title ) {
			remove_all_actions( 'save_post_tp_promo' );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $code ) );
		}
		$kind = isset( $_POST['tp_promo_kind'] ) && 'fixed' === $_POST['tp_promo_kind'] ? 'fixed' : 'percent';
		$amt  = isset( $_POST['tp_promo_amount'] ) ? max( 0, (float) $_POST['tp_promo_amount'] ) : 0;
		if ( 'percent' === $kind ) {
			$amt = min( 100, $amt );
		}
		$date = static function ( $k ) {
			$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		update_post_meta( $post_id, '_tp_promo_kind', $kind );
		update_post_meta( $post_id, '_tp_promo_amount', $amt );
		update_post_meta( $post_id, '_tp_promo_start', $date( 'tp_promo_start' ) );
		update_post_meta( $post_id, '_tp_promo_end', $date( 'tp_promo_end' ) );
		update_post_meta( $post_id, '_tp_promo_max', isset( $_POST['tp_promo_max'] ) ? absint( $_POST['tp_promo_max'] ) : 0 );
		update_post_meta( $post_id, '_tp_promo_first', empty( $_POST['tp_promo_first'] ) ? '' : '1' );
		update_post_meta( $post_id, '_tp_promo_text', isset( $_POST['tp_promo_text'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_promo_text'] ) ) : '' );
	},
	10,
	2
);

add_filter(
	'manage_tp_promo_posts_columns',
	static function () {
		return array(
			'cb'       => '<input type="checkbox">',
			'title'    => __( 'Code', 'taxi-peninsula' ),
			'tp_offer' => __( 'Offer', 'taxi-peninsula' ),
			'tp_dates' => __( 'Dates', 'taxi-peninsula' ),
			'tp_uses'  => __( 'Uses', 'taxi-peninsula' ),
		);
	}
);

add_action(
	'manage_tp_promo_posts_custom_column',
	static function ( $col, $id ) {
		$m = tp_promo_meta( $id );
		if ( 'tp_offer' === $col ) {
			echo esc_html( tp_promo_label( get_the_title( $id ) ) . ( $m['first'] ? ' · ' . __( 'first trip only', 'taxi-peninsula' ) : '' ) );
		} elseif ( 'tp_dates' === $col ) {
			echo esc_html( ( $m['start'] ?: '…' ) . ' – ' . ( $m['end'] ?: '…' ) );
		} elseif ( 'tp_uses' === $col ) {
			echo esc_html( tp_promo_uses( get_the_title( $id ) ) . ( $m['max'] ? ' / ' . $m['max'] : '' ) );
		}
	},
	10,
	2
);

// Note in the booking summary when the discount is known.
add_action(
	'tp_booking_created',
	static function ( $post_id, $ids ) {
		$code = get_post_meta( $post_id, '_tp_promo', true );
		if ( $code ) {
			foreach ( (array) $ids as $id ) {
				/* translators: 1: code 2: offer */
				tp_add_log( $id, array( 'note' => sprintf( __( 'Promo code %1$s (%2$s)', 'taxi-peninsula' ), $code, tp_promo_label( $code ) ) ) );
			}
		}
	},
	20,
	2
);
