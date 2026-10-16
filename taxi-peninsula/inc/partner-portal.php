<?php
/**
 * Partner portal: hospitals, aged-care homes and NDIS providers log in with
 * a coordinator account linked to their partner page, then see an
 * "Our organisation" tab in My Account with monthly usage, every trip booked
 * from the organisation, receipts/invoices and a CSV download.
 *
 * Link people under Marketing → Partner pages → (partner) → Portal users.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Partner pages a user can see in the portal.
 *
 * @return int[]
 */
function tp_user_venues( $user_id ) {
	if ( ! $user_id ) {
		return array();
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'   => 'tp_venue',
				'post_status' => 'publish',
				'numberposts' => 20,
				'fields'      => 'ids',
				'meta_query'  => array( array( 'key' => '_tp_v_user', 'value' => (int) $user_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
}

function tp_venue_users( $venue_id ) {
	return array_map( 'intval', (array) get_post_meta( $venue_id, '_tp_v_user' ) );
}

/* -------------------------------------------------------------------------
 * Admin: pick portal users on the partner page.
 * ---------------------------------------------------------------------- */

add_action(
	'tp_venue_box_after',
	static function ( $post ) {
		$linked = tp_venue_users( $post->ID );
		$people = get_users( array( 'role__in' => array( 'tp_coordinator' ), 'orderby' => 'display_name', 'number' => 200 ) );
		echo '<h3>' . esc_html__( 'Portal users', 'taxi-peninsula' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Coordinators ticked here see "Our organisation" in My Account: monthly usage, every trip booked from this partner, receipts and a CSV download.', 'taxi-peninsula' ) . '</p>';
		if ( ! $people ) {
			echo '<p>' . wp_kses_post( sprintf( /* translators: %s: link */ __( 'No coordinator accounts yet. <a href="%s">Add a user</a> with the Coordinator role first.', 'taxi-peninsula' ), esc_url( admin_url( 'user-new.php' ) ) ) ) . '</p>';
			return;
		}
		echo '<input type="hidden" name="tp_v_users_sent" value="1"><fieldset><legend class="screen-reader-text">' . esc_html__( 'Portal users', 'taxi-peninsula' ) . '</legend>';
		foreach ( $people as $u ) {
			$org = get_user_meta( $u->ID, 'tp_org', true );
			printf(
				'<label style="display:block"><input type="checkbox" name="tp_v_users[]" value="%1$d"%2$s> %3$s</label>',
				(int) $u->ID,
				checked( in_array( (int) $u->ID, $linked, true ), true, false ),
				esc_html( $u->display_name . ' — ' . $u->user_email . ( $org ? ' (' . $org . ')' : '' ) )
			);
		}
		echo '</fieldset>';
	}
);

add_action(
	'save_post_tp_venue',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_venue_nonce'], $_POST['tp_v_users_sent'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_venue_nonce'] ), 'tp_venue_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$want = array_map( 'absint', (array) ( $_POST['tp_v_users'] ?? array() ) );
		$want = array_values( array_filter( $want, static fn( $id ) => $id && user_can( $id, 'read' ) ) );
		delete_post_meta( $post_id, '_tp_v_user' );
		foreach ( array_unique( $want ) as $uid ) {
			add_post_meta( $post_id, '_tp_v_user', $uid );
		}
	},
	20
);

/* -------------------------------------------------------------------------
 * Trips for an organisation.
 * ---------------------------------------------------------------------- */

/**
 * Booking IDs for a partner in a month (Y-m): booked from its page or QR
 * code, or by one of its portal users.
 */
function tp_venue_booking_ids( $venue_id, $month ) {
	$base = array(
		'post_type'   => 'tp_booking',
		'post_status' => 'publish',
		'numberposts' => 1000,
		'fields'      => 'ids',
	);
	$date = array( 'key' => '_tp_date', 'value' => $month . '-', 'compare' => 'LIKE' );
	$ids  = get_posts( $base + array( 'meta_query' => array( $date, array( 'key' => '_tp_venue_id', 'value' => (int) $venue_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$users = tp_venue_users( $venue_id );
	if ( $users ) {
		$ids = array_merge( $ids, get_posts( $base + array( 'meta_query' => array( $date, array( 'key' => '_tp_customer_id', 'value' => $users, 'compare' => 'IN' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$ids = array_unique( array_map( 'intval', $ids ) );
	usort(
		$ids,
		static function ( $a, $b ) {
			return strcmp( get_post_meta( $a, '_tp_date', true ) . get_post_meta( $a, '_tp_time', true ), get_post_meta( $b, '_tp_date', true ) . get_post_meta( $b, '_tp_time', true ) );
		}
	);
	return $ids;
}

/**
 * Totals for a list of bookings.
 */
function tp_venue_usage( array $ids ) {
	$t = array( 'trips' => 0, 'completed' => 0, 'cancelled' => 0, 'no_show' => 0, 'upcoming' => 0, 'fares' => 0.0, 'owing' => 0.0 );
	foreach ( $ids as $id ) {
		$status = get_post_meta( $id, '_tp_status', true ) ?: 'pending';
		$fare   = (float) get_post_meta( $id, '_tp_fare', true );
		++$t['trips'];
		if ( 'completed' === $status ) {
			++$t['completed'];
		} elseif ( 'cancelled' === $status ) {
			++$t['cancelled'];
		} elseif ( 'no_show' === $status ) {
			++$t['no_show'];
		} else {
			++$t['upcoming'];
		}
		if ( in_array( $status, array( 'completed', 'no_show' ), true ) ) {
			$t['fares'] += $fare;
			if ( 'account' === get_post_meta( $id, '_tp_payment', true ) && 'paid' !== get_post_meta( $id, '_tp_invoice_status', true ) ) {
				$t['owing'] += max( 0, $fare - (float) get_post_meta( $id, '_tp_paid', true ) );
			}
		}
	}
	return $t;
}

function tp_portal_month( $raw ) {
	$raw = sanitize_text_field( (string) $raw );
	return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $raw ) ? $raw : wp_date( 'Y-m' );
}

/* -------------------------------------------------------------------------
 * My Account tab.
 * ---------------------------------------------------------------------- */

add_filter(
	'tp_account_tabs',
	static function ( $tabs ) {
		if ( tp_user_venues( get_current_user_id() ) ) {
			$tabs = array( 'organisation' => __( 'Our organisation', 'taxi-peninsula' ) ) + $tabs;
		}
		return $tabs;
	},
	5
);

add_action(
	'tp_account_tab_organisation',
	static function ( $user ) {
		$venues = tp_user_venues( $user->ID );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$venue = isset( $_GET['venue_id'] ) ? absint( $_GET['venue_id'] ) : 0;
		$venue = in_array( $venue, $venues, true ) ? $venue : $venues[0];
		$month = tp_portal_month( wp_unslash( $_GET['month'] ?? '' ) );
		// phpcs:enable
		$ids   = tp_venue_booking_ids( $venue, $month );
		$usage = tp_venue_usage( $ids );
		$name  = get_the_title( $venue );
		$label = wp_date( 'F Y', strtotime( $month . '-01' ) );
		?>
		<section class="account-section portal">
			<div class="portal__head">
				<h2><?php echo esc_html( $name ); ?></h2>
				<a class="btn btn--accent" href="<?php echo esc_url( add_query_arg( 'venue', $venue, tp_booking_page_url() ) ); ?>">
					<?php /* translators: %s: organisation */ echo esc_html( sprintf( __( 'Book from %s', 'taxi-peninsula' ), $name ) ); ?>
				</a>
			</div>
			<form method="get" class="inline-form" action="<?php echo esc_url( tp_account_url() ); ?>">
				<input type="hidden" name="tab" value="organisation">
				<?php if ( count( $venues ) > 1 ) : ?>
					<div class="field">
						<label for="portal-venue"><?php esc_html_e( 'Organisation', 'taxi-peninsula' ); ?></label>
						<select id="portal-venue" name="venue_id">
							<?php foreach ( $venues as $v ) : ?>
								<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $venue, $v ); ?>><?php echo esc_html( get_the_title( $v ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php else : ?>
					<input type="hidden" name="venue_id" value="<?php echo esc_attr( $venue ); ?>">
				<?php endif; ?>
				<div class="field">
					<label for="portal-month"><?php esc_html_e( 'Month', 'taxi-peninsula' ); ?></label>
					<input type="month" id="portal-month" name="month" value="<?php echo esc_attr( $month ); ?>">
				</div>
				<button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></button>
			</form>

			<h3><?php /* translators: %s: month */ echo esc_html( sprintf( __( 'Usage in %s', 'taxi-peninsula' ), $label ) ); ?></h3>
			<dl class="portal__stats">
				<div><dt><?php esc_html_e( 'Trips booked', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $usage['trips'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Completed', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $usage['completed'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Upcoming', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $usage['upcoming'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Cancelled / no-show', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $usage['cancelled'] + $usage['no_show'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Fares', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( tp_money( $usage['fares'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Account balance owing', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( tp_money( $usage['owing'] ) ); ?></dd></div>
			</dl>

			<?php if ( ! $ids ) : ?>
				<p class="empty-note"><?php esc_html_e( 'No trips in this month.', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<div class="table-scroll">
					<table class="data-table">
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Reference', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Passenger', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trip', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Fare', 'taxi-peninsula' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Receipt / invoice', 'taxi-peninsula' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $ids as $id ) : ?>
								<?php $b = tp_get_booking( $id ); ?>
								<tr>
									<td><?php echo esc_html( tp_format_pickup( $b ) ); ?></td>
									<td><?php echo esc_html( $b['reference'] ); ?></td>
									<td><?php echo esc_html( $b['name'] ); ?></td>
									<td><?php echo esc_html( $b['pickup'] . ' → ' . $b['dropoff'] ); ?></td>
									<td><?php echo esc_html( tp_statuses()[ $b['status'] ] ?? $b['status'] ); ?></td>
									<td><?php echo '' !== (string) $b['fare'] ? esc_html( tp_money( $b['fare'] ) ) : '—'; ?></td>
									<td><?php if ( in_array( $b['status'], array( 'completed', 'no_show' ), true ) ) : ?><a href="<?php echo esc_url( tp_receipt_url( $id ) ); ?>"><?php esc_html_e( 'View', 'taxi-peninsula' ); ?></a><?php else : ?>—<?php endif; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p><a class="btn btn--ghost" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'tp_portal_csv', 'venue_id' => $venue, 'month' => $month ), admin_url( 'admin-post.php' ) ), 'tp_portal_csv' ) ); ?>"><?php tp_the_icon( 'download' ); ?> <?php esc_html_e( 'Download CSV', 'taxi-peninsula' ); ?></a></p>
			<?php endif; ?>

			<?php
			$history = array();
			for ( $i = 5; $i >= 0; $i-- ) {
				$m             = wp_date( 'Y-m', strtotime( wp_date( 'Y-m-01' ) . " -$i months" ) );
				$history[ $m ] = tp_venue_usage( tp_venue_booking_ids( $venue, $m ) );
			}
			?>
			<h3><?php esc_html_e( 'Last 6 months', 'taxi-peninsula' ); ?></h3>
			<div class="table-scroll">
				<table class="data-table">
					<thead><tr><th scope="col"><?php esc_html_e( 'Month', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'Completed', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'Fares', 'taxi-peninsula' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $history as $m => $u ) : ?>
							<tr><td><a href="<?php echo esc_url( tp_account_url( array( 'tab' => 'organisation', 'venue_id' => $venue, 'month' => $m ) ) . '#account' ); ?>"><?php echo esc_html( wp_date( 'M Y', strtotime( $m . '-01' ) ) ); ?></a></td><td><?php echo esc_html( number_format_i18n( $u['trips'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $u['completed'] ) ); ?></td><td><?php echo esc_html( tp_money( $u['fares'] ) ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="field__hint"><?php esc_html_e( 'Shows trips booked from your partner page or QR code, and trips booked by anyone with portal access.', 'taxi-peninsula' ); ?></p>
		</section>
		<?php
	}
);

add_action(
	'admin_post_tp_portal_csv',
	static function () {
		check_admin_referer( 'tp_portal_csv' );
		$venue = isset( $_GET['venue_id'] ) ? absint( $_GET['venue_id'] ) : 0;
		if ( ! in_array( $venue, tp_user_venues( get_current_user_id() ), true ) && ! current_user_can( 'tp_view_finance' ) ) {
			wp_die( esc_html__( 'You do not have access to this organisation.', 'taxi-peninsula' ), 403 );
		}
		$month = tp_portal_month( wp_unslash( $_GET['month'] ?? '' ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_post_field( 'post_name', $venue ) . '-trips-' . $month ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date', 'Time', 'Reference', 'Passenger', 'NDIS number', 'Pick-up', 'Drop-off', 'Status', 'Fare', 'Payment', 'Invoice status' ), ',', '"', '' );
		foreach ( tp_venue_booking_ids( $venue, $month ) as $id ) {
			$b = tp_get_booking( $id );
			$row = array( $b['date'], $b['time'], $b['reference'], $b['name'], $b['ndis_number'], $b['pickup'], $b['dropoff'], tp_statuses()[ $b['status'] ] ?? $b['status'], $b['fare'], $b['payment'], $b['invoice'] );
			// Stop spreadsheet formulas sneaking in through typed text.
			fputcsv( $out, array_map( static fn( $v ) => preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v, $row ), ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);
