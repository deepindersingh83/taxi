<?php
/**
 * "Pick up when ready" bookings — hospital discharges, day surgery, long
 * appointments. The customer books the day and the earliest likely time;
 * when they are ready they tap "I'm ready" on their booking page (or call),
 * and the office and driver are told straight away.
 *
 * The dispatch board shows these trips as "Waiting for the call" with a
 * "Ready now" button for phone calls, and lists today's waiting trips at
 * the top.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_is_waiting( array $b ) {
	return ! empty( $b['when_ready'] ) && empty( $b['ready_at'] ) && in_array( $b['status'], array( 'pending', 'confirmed', 'assigned', 'accepted' ), true );
}

/**
 * Mark a "when ready" trip as ready now.
 *
 * @param int    $post_id Booking.
 * @param string $by      'customer' or 'office'.
 * @return bool
 */
function tp_mark_ready( $post_id, $by ) {
	$b = tp_get_booking( $post_id );
	if ( ! tp_is_waiting( $b ) ) {
		return false;
	}
	$now = time();
	update_post_meta( $post_id, '_tp_ready_at', $now );
	// The pick-up time becomes "now" (rounded up to 5 minutes).
	$ready = (int) ( ceil( $now / 300 ) * 300 );
	if ( wp_date( 'Y-m-d', $ready ) === $b['date'] ) {
		update_post_meta( $post_id, '_tp_time', wp_date( 'H:i', $ready ) );
		update_post_meta( $post_id, '_tp_pickup_at', $b['date'] . ' ' . wp_date( 'H:i', $ready ) );
	}
	$b = tp_get_booking( $post_id );

	tp_in_site_lang(
		static function () use ( $post_id, $b, $by ) {
			$who  = 'customer' === $by ? __( 'The customer', 'taxi-peninsula' ) : __( 'The office', 'taxi-peninsula' );
			/* translators: 1: who 2: reference 3: name 4: pick-up */
			$note = sprintf( __( '%1$s says %3$s is ready to be picked up now (%2$s, %4$s).', 'taxi-peninsula' ), $who, $b['reference'], $b['name'], tp_short_place( $b['pickup'] ) ?: $b['pickup'] );
			tp_add_log( $post_id, array( 'note' => $note ) );
			if ( function_exists( 'tp_activity' ) ) {
				tp_activity( 'note', $note, $post_id );
			}
			if ( 'customer' === $by ) {
				wp_mail(
					tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
					/* translators: 1: reference 2: name */
					sprintf( __( 'READY for pick-up now: %1$s — %2$s', 'taxi-peninsula' ), $b['reference'], $b['name'] ),
					$note . "\n\n" . tp_booking_summary( $b ) . "\n" . admin_url( 'admin.php?page=tp-dispatch' ),
					tp_mail_headers( $b['email'] )
				);
				$office = trim( (string) tp_setting( 'sms_office_number' ) );
				if ( $office && tp_sms_enabled() ) {
					tp_booking_sms( $post_id, $office, $note, 'office' );
				}
			}
			if ( $b['driver_id'] && tp_sms_enabled() ) {
				/* translators: 1: reference 2: name 3: pick-up */
				tp_booking_sms( $post_id, get_user_meta( $b['driver_id'], 'tp_mobile', true ), sprintf( __( '%1$s: %2$s is ready now at %3$s.', 'taxi-peninsula' ), $b['reference'], $b['name'], $b['pickup'] ), 'driver' );
			}
		}
	);
	do_action( 'tp_booking_ready', $post_id, $by );
	return true;
}

/* -------------------------------------------------------------------------
 * Customer: "I'm ready" on the booking page.
 * ---------------------------------------------------------------------- */

add_action(
	'tp_booking_status_after',
	static function ( $post_id ) {
		$b = tp_get_booking( $post_id );
		if ( ! empty( $_GET['ready'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<p class="notice notice--success" role="status" tabindex="-1" data-focus>' . esc_html__( 'Thanks — we have let the office and your driver know you are ready. We will text you when the driver is on the way.', 'taxi-peninsula' ) . '</p>';
		}
		if ( ! tp_is_waiting( $b ) || $b['date'] > wp_date( 'Y-m-d' ) ) {
			return;
		}
		?>
		<section class="card ready-box" aria-labelledby="ready-title">
			<h2 id="ready-title"><?php esc_html_e( 'Ready to be picked up?', 'taxi-peninsula' ); ?></h2>
			<p><?php esc_html_e( 'Tap the button once you have been discharged or your appointment is finished. We will send the driver straight away.', 'taxi-peninsula' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tp_ready">
				<input type="hidden" name="ref" value="<?php echo esc_attr( $b['reference'] ); ?>">
				<input type="hidden" name="t" value="<?php echo esc_attr( tp_booking_token( $post_id ) ); ?>">
				<button type="submit" class="btn btn--accent btn--lg"><?php tp_the_icon( 'check' ); ?> <?php esc_html_e( 'I’m ready', 'taxi-peninsula' ); ?></button>
			</form>
			<p class="field__hint"><?php printf( /* translators: %s: phone */ esc_html__( 'Or call us on %s.', 'taxi-peninsula' ), '<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>' ); ?></p>
		</section>
		<?php
	},
	5
);

add_action( 'admin_post_nopriv_tp_ready', 'tp_handle_ready' );
add_action( 'admin_post_tp_ready', 'tp_handle_ready' );
function tp_handle_ready() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the booking token is the proof of access, as on the booking page.
	$ref = isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '';
	$tok = isset( $_POST['t'] ) ? sanitize_text_field( wp_unslash( $_POST['t'] ) ) : '';
	// phpcs:enable
	$id = tp_find_booking( $ref );
	if ( ! $id || ! hash_equals( tp_booking_token( $id ), $tok ) ) {
		wp_die( esc_html__( 'This link has expired. Please call us.', 'taxi-peninsula' ), 403 );
	}
	if ( ! tp_rate_limit( 'tp_ready', 6, HOUR_IN_SECONDS ) ) {
		wp_die( esc_html__( 'Please call us.', 'taxi-peninsula' ), 429 );
	}
	tp_mark_ready( $id, 'customer' );
	wp_safe_redirect( add_query_arg( 'ready', 1, tp_booking_manage_url( $id ) ) );
	exit;
}

/* -------------------------------------------------------------------------
 * Office: dispatch board and booking screen.
 * ---------------------------------------------------------------------- */

function tp_ready_button( $post_id, $class = 'button button-primary button-small' ) {
	ob_start();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
		<input type="hidden" name="action" value="tp_ready_office"><input type="hidden" name="booking" value="<?php echo esc_attr( $post_id ); ?>">
		<?php wp_nonce_field( 'tp_ready_office_' . $post_id ); ?>
		<button class="<?php echo esc_attr( $class ); ?>"><?php esc_html_e( 'Ready now', 'taxi-peninsula' ); ?></button>
	</form>
	<?php
	return ob_get_clean();
}

add_action(
	'admin_post_tp_ready_office',
	static function () {
		$id = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_ready_office_' . $id );
		$ok = tp_mark_ready( $id, 'office' );
		set_transient( 'tp_admin_notice_' . get_current_user_id(), $ok ? array( 'success', __( 'Marked as ready. The pick-up time is now, and the driver has been told.', 'taxi-peninsula' ) ) : array( 'warning', __( 'That trip is not waiting for a call.', 'taxi-peninsula' ) ), 60 );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=tp-dispatch' ) );
		exit;
	}
);

add_action(
	'tp_dispatch_card_flags',
	static function ( $b ) {
		if ( tp_is_waiting( $b ) ) {
			echo '<p class="tp-dcard__flag tp-dcard__flag--waiting">' . esc_html__( 'Waiting for the call (when ready)', 'taxi-peninsula' ) . ' ' . tp_ready_button( $b['id'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		} elseif ( ! empty( $b['when_ready'] ) && $b['ready_at'] ) {
			/* translators: %s: time */
			echo '<p class="tp-dcard__flag tp-dcard__flag--ready">' . esc_html( sprintf( __( 'READY since %s', 'taxi-peninsula' ), wp_date( 'g:i a', $b['ready_at'] ) ) ) . '</p>';
		}
	}
);

// Today's waiting trips, at the top of the dispatch board.
add_action(
	'tp_dispatch_top',
	static function () {
		$ids = get_posts(
			array(
				'post_type'   => 'tp_booking',
				'post_status' => 'publish',
				'numberposts' => 30,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_tp_date', 'value' => wp_date( 'Y-m-d' ) ),
					array( 'key' => '_tp_when_ready', 'value' => '1' ),
				),
			)
		);
		$rows = array();
		foreach ( $ids as $id ) {
			$b = tp_get_booking( $id );
			if ( tp_is_waiting( $b ) || ( $b['ready_at'] && in_array( $b['status'], array( 'pending', 'confirmed', 'assigned', 'accepted' ), true ) ) ) {
				$rows[] = $b;
			}
		}
		if ( ! $rows ) {
			return;
		}
		usort( $rows, static fn( $x, $y ) => ( $y['ready_at'] <=> $x['ready_at'] ) );
		echo '<div class="notice notice-info inline tp-waiting"><p><strong>' . esc_html__( 'Pick up when ready — today', 'taxi-peninsula' ) . '</strong></p><ul class="ul-disc">';
		foreach ( $rows as $b ) {
			printf(
				'<li>%s <a href="%s">%s</a> · %s · %s %s</li>',
				$b['ready_at'] ? '<strong>' . esc_html( sprintf( /* translators: %s: time */ __( 'READY %s', 'taxi-peninsula' ), wp_date( 'g:i a', $b['ready_at'] ) ) ) . '</strong>' : esc_html__( 'Waiting', 'taxi-peninsula' ),
				esc_url( get_edit_post_link( $b['id'] ) ),
				esc_html( $b['reference'] ),
				esc_html( $b['name'] ),
				esc_html( tp_short_place( $b['pickup'] ) ?: $b['pickup'] ),
				$b['ready_at'] ? '' : tp_ready_button( $b['id'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			);
		}
		echo '</ul></div>';
	},
	5
);

add_action(
	'add_meta_boxes_tp_booking',
	static function ( $post ) {
		$b = tp_get_booking( $post->ID );
		if ( ! empty( $b['when_ready'] ) ) {
			add_meta_box(
				'tp-when-ready',
				__( 'Pick up when ready', 'taxi-peninsula' ),
				static function () use ( $b ) {
					if ( tp_is_waiting( $b ) ) {
						echo '<p>' . esc_html__( 'Waiting for the customer to call or tap "I’m ready".', 'taxi-peninsula' ) . '</p>' . tp_ready_button( $b['id'], 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
					} elseif ( $b['ready_at'] ) {
						/* translators: %s: time */
						echo '<p>' . esc_html( sprintf( __( 'Ready since %s.', 'taxi-peninsula' ), wp_date( 'D j M g:i a', $b['ready_at'] ) ) ) . '</p>';
					}
				},
				'tp_booking',
				'side',
				'high'
			);
		}
	}
);
