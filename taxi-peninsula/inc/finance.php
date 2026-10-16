<?php
/**
 * Invoices & payments screen for the Accounts (finance) role, admins and
 * booking managers: fares, GST, deposits, invoice status and a finance CSV.
 * Accounts staff cannot see or change dispatch.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_invoice_statuses() {
	return array(
		''     => __( 'Not invoiced', 'taxi-peninsula' ),
		'sent' => __( 'Invoice sent', 'taxi-peninsula' ),
		'paid' => __( 'Paid', 'taxi-peninsula' ),
	);
}

add_action( 'admin_menu', static function () {
	add_menu_page(
		__( 'Invoices & payments', 'taxi-peninsula' ),
		__( 'Invoices', 'taxi-peninsula' ),
		'tp_view_finance',
		'tp-finance',
		'tp_render_finance_page',
		'dashicons-money-alt',
		4
	);
	add_submenu_page( 'tp-finance', __( 'Invoices & payments', 'taxi-peninsula' ), __( 'Invoices & payments', 'taxi-peninsula' ), 'tp_view_finance', 'tp-finance', 'tp_render_finance_page', 0 );
} );

add_action( 'admin_enqueue_scripts', static function ( $hook ) {
	if ( false !== strpos( $hook, 'tp-finance' ) ) {
		wp_enqueue_style( 'tp-admin', TP_URI . '/assets/css/admin.css', array(), TP_VERSION );
		tp_admin_brand_vars();
	}
} );

/**
 * Filters from the request, with defaults (this month, finished + account trips).
 */
function tp_finance_filters( array $req ) {
	$from = isset( $req['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $req['from'] ) ? $req['from'] : wp_date( 'Y-m-01' );
	$to   = isset( $req['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $req['to'] ) ? $req['to'] : wp_date( 'Y-m-t' );
	$pay  = isset( $req['pay'] ) && isset( tp_all_payment_methods()[ $req['pay'] ] ) ? $req['pay'] : '';
	$inv  = isset( $req['inv'] ) && in_array( $req['inv'], array( 'none', 'sent', 'paid' ), true ) ? $req['inv'] : '';
	$q    = isset( $req['q'] ) ? sanitize_text_field( $req['q'] ) : '';
	return compact( 'from', 'to', 'pay', 'inv', 'q' );
}

/**
 * Finished trips (and paid deposits) matching the filters.
 *
 * @return array[]
 */
function tp_finance_rows( array $f ) {
	$meta = array(
		'relation' => 'AND',
		array( 'key' => '_tp_date', 'value' => array( $f['from'], $f['to'] ), 'compare' => 'BETWEEN' ),
		array(
			'relation' => 'OR',
			array( 'key' => '_tp_status', 'value' => tp_finished_statuses(), 'compare' => 'IN' ),
			array( 'key' => '_tp_paid', 'value' => 0, 'compare' => '>', 'type' => 'DECIMAL' ),
		),
	);
	if ( $f['pay'] ) {
		$meta[] = array( 'key' => '_tp_payment', 'value' => $f['pay'] );
	}
	if ( 'none' === $f['inv'] ) {
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => '_tp_invoice_status', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_tp_invoice_status', 'value' => '' ),
		);
	} elseif ( $f['inv'] ) {
		$meta[] = array( 'key' => '_tp_invoice_status', 'value' => $f['inv'] );
	}
	$ids  = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'numberposts' => 1000,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);
	$rows = array_map( 'tp_get_booking', $ids );
	if ( '' !== $f['q'] ) {
		$rows = array_values(
			array_filter(
				$rows,
				static fn( $b ) => false !== stripos( $b['invoice_name'] . ' ' . $b['name'] . ' ' . $b['reference'] . ' ' . $b['ndis_number'], $f['q'] )
			)
		);
	}
	return $rows;
}

function tp_render_finance_page() {
	if ( ! current_user_can( 'tp_view_finance' ) ) {
		return;
	}
	$req  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only filters, validated in tp_finance_filters().
	$f    = tp_finance_filters( $req );
	$rows = tp_finance_rows( $f );
	$pays = tp_all_payment_methods();
	$invs = tp_invoice_statuses();
	$sum  = array( 'fare' => 0, 'gst' => 0, 'paid' => 0, 'due' => 0, 'missing' => 0 );
	foreach ( $rows as $b ) {
		if ( '' === (string) $b['fare'] ) {
			$sum['missing']++;
			continue;
		}
		$sum['fare'] += (float) $b['fare'];
		$sum['gst']  += (float) $b['fare'] / 11;
		$sum['paid'] += $b['paid'];
		if ( 'account' === $b['payment'] && 'paid' !== $b['invoice'] ) {
			$sum['due'] += max( 0, (float) $b['fare'] - $b['paid'] );
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$saved = isset( $_GET['saved'] ) ? absint( $_GET['saved'] ) : 0;
	?>
	<div class="wrap tp-finance">
		<h1><?php esc_html_e( 'Invoices & payments', 'taxi-peninsula' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Finished trips and online deposits. Enter the final fare, then mark account invoices as sent or paid. Receipts become tax invoices once your ABN is set in Bookings → Settings.', 'taxi-peninsula' ); ?></p>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'taxi-peninsula' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! tp_setting( 'abn' ) && current_user_can( 'manage_options' ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Add your ABN under Bookings → Settings → Payments so receipts can be issued as tax invoices.', 'taxi-peninsula' ); ?></p></div>
		<?php endif; ?>

		<form method="get" class="tp-finance__filters">
			<input type="hidden" name="page" value="tp-finance">
			<label><?php esc_html_e( 'From', 'taxi-peninsula' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>"></label>
			<label><?php esc_html_e( 'To', 'taxi-peninsula' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>"></label>
			<label><?php esc_html_e( 'Payment', 'taxi-peninsula' ); ?>
				<select name="pay">
					<option value=""><?php esc_html_e( 'All', 'taxi-peninsula' ); ?></option>
					<?php foreach ( $pays as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['pay'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Invoice', 'taxi-peninsula' ); ?>
				<select name="inv">
					<option value=""><?php esc_html_e( 'Any', 'taxi-peninsula' ); ?></option>
					<option value="none" <?php selected( $f['inv'], 'none' ); ?>><?php echo esc_html( $invs[''] ); ?></option>
					<option value="sent" <?php selected( $f['inv'], 'sent' ); ?>><?php echo esc_html( $invs['sent'] ); ?></option>
					<option value="paid" <?php selected( $f['inv'], 'paid' ); ?>><?php echo esc_html( $invs['paid'] ); ?></option>
				</select>
			</label>
			<label><?php esc_html_e( 'Search', 'taxi-peninsula' ); ?> <input type="search" name="q" value="<?php echo esc_attr( $f['q'] ); ?>" placeholder="<?php esc_attr_e( 'Invoice to, passenger, ref, NDIS', 'taxi-peninsula' ); ?>"></label>
			<button class="button" type="submit"><?php esc_html_e( 'Filter', 'taxi-peninsula' ); ?></button>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'tp_finance_export' ), $f ), admin_url( 'admin-post.php' ) ), 'tp_finance_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'taxi-peninsula' ); ?></a>
		</form>

		<ul class="tp-tiles">
			<li><span class="tp-tiles__n"><?php echo esc_html( count( $rows ) ); ?></span><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( tp_money( $sum['fare'] ) ); ?></span><?php esc_html_e( 'Fares (incl. GST)', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( tp_money( $sum['gst'] ) ); ?></span><?php esc_html_e( 'GST', 'taxi-peninsula' ); ?></li>
			<li><span class="tp-tiles__n"><?php echo esc_html( tp_money( $sum['paid'] ) ); ?></span><?php esc_html_e( 'Paid online', 'taxi-peninsula' ); ?></li>
			<li class="<?php echo $sum['due'] > 0 ? 'is-alert' : ''; ?>"><span class="tp-tiles__n"><?php echo esc_html( tp_money( $sum['due'] ) ); ?></span><?php esc_html_e( 'Owing on account', 'taxi-peninsula' ); ?></li>
			<li class="<?php echo $sum['missing'] ? 'is-alert' : ''; ?>"><span class="tp-tiles__n"><?php echo esc_html( $sum['missing'] ); ?></span><?php esc_html_e( 'Missing a fare', 'taxi-peninsula' ); ?></li>
		</ul>

		<table class="widefat striped tp-finance__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Trip', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Passenger / invoice to', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Payment', 'taxi-peninsula' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Paid online', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Fare & invoice', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Document', 'taxi-peninsula' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No trips match these filters.', 'taxi-peninsula' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $b ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $b['reference'] ); ?></strong> <?php echo tp_status_badge( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br>
							<?php echo esc_html( tp_format_pickup( $b ) ); ?><br>
							<small><?php echo esc_html( tp_short_place( $b['pickup'] ) . ' → ' . tp_short_place( $b['dropoff'] ) ); ?></small>
						</td>
						<td>
							<?php echo esc_html( $b['name'] ); ?>
							<?php if ( 'account' === $b['payment'] ) : ?>
								<br><strong><?php echo esc_html( $b['invoice_name'] ); ?></strong><br><small><?php echo esc_html( $b['invoice_email'] ); ?><?php echo $b['ndis_number'] ? ' · NDIS ' . esc_html( $b['ndis_number'] ) : ''; ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $pays[ $b['payment'] ] ?? $b['payment'] ); ?><?php echo $b['mptp'] ? ' <span class="tp-tag">MPTP</span>' : ''; ?></td>
						<td class="num"><?php echo $b['paid'] > 0 ? esc_html( tp_money( $b['paid'] ) ) : '—'; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-finance__row">
								<input type="hidden" name="action" value="tp_finance_save">
								<input type="hidden" name="booking" value="<?php echo esc_attr( $b['id'] ); ?>">
								<input type="hidden" name="back" value="<?php echo esc_attr( add_query_arg( $f, admin_url( 'admin.php?page=tp-finance' ) ) ); ?>">
								<?php wp_nonce_field( 'tp_finance_' . $b['id'] ); ?>
								<label class="screen-reader-text" for="fare-<?php echo esc_attr( $b['id'] ); ?>"><?php esc_html_e( 'Fare incl. GST', 'taxi-peninsula' ); ?></label>
								<span class="tp-finance__money">$<input type="number" step="0.01" min="0" id="fare-<?php echo esc_attr( $b['id'] ); ?>" name="fare" value="<?php echo esc_attr( tp_fare_input_value( $b['id'] ) ); ?>" placeholder="<?php esc_attr_e( 'Fare', 'taxi-peninsula' ); ?>"></span>
								<?php if ( 'account' === $b['payment'] ) : ?>
									<label class="screen-reader-text" for="inv-<?php echo esc_attr( $b['id'] ); ?>"><?php esc_html_e( 'Invoice status', 'taxi-peninsula' ); ?></label>
									<select id="inv-<?php echo esc_attr( $b['id'] ); ?>" name="invoice">
										<?php foreach ( $invs as $k => $l ) : ?>
											<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $b['invoice'], $k ); ?>><?php echo esc_html( $l ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
								<button type="submit" class="button button-small"><?php esc_html_e( 'Save', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $b['reference'] ); ?></span></button>
							</form>
							<?php if ( '' !== (string) $b['fare'] ) : ?>
								<small>
									<?php
									/* translators: %s: GST amount */
									echo esc_html( sprintf( __( 'GST %s', 'taxi-peninsula' ), tp_money( (float) $b['fare'] / 11 ) ) );
									$tp_disc = (float) get_post_meta( $b['id'], '_tp_discount', true );
									if ( $tp_disc > 0 ) {
										/* translators: 1: discount 2: code 3: amount to pay */
										echo '<br>' . esc_html( sprintf( __( '−%1$s promo %2$s = %3$s', 'taxi-peninsula' ), tp_money( $tp_disc ), $b['promo'], tp_money( $b['fare'] ) ) );
									}
									?>
								</small>
							<?php endif; ?>
						</td>
						<td><a href="<?php echo esc_url( tp_receipt_url( $b['id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( tp_document_title( $b ) ); ?> ↗</a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

add_action( 'admin_post_tp_finance_save', 'tp_handle_finance_save' );
function tp_handle_finance_save() {
	$post_id = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
	check_admin_referer( 'tp_finance_' . $post_id );
	if ( ! current_user_can( 'tp_view_finance' ) || 'tp_booking' !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'You cannot change this booking.', 'taxi-peninsula' ), 403 );
	}
	$fare = isset( $_POST['fare'] ) ? sanitize_text_field( wp_unslash( $_POST['fare'] ) ) : '';
	$new  = '' === $fare ? '' : round( max( 0, (float) $fare ), 2 );
	if ( (string) $new !== (string) tp_fare_input_value( $post_id ) ) {
		tp_set_fare( $post_id, $new );
		/* translators: %s: fare */
		tp_add_log( $post_id, array( 'note' => sprintf( __( 'Fare set to %s', 'taxi-peninsula' ), '' === $new ? '—' : tp_money( $new ) ) ) );
	}
	if ( isset( $_POST['invoice'] ) ) {
		$inv = sanitize_key( wp_unslash( $_POST['invoice'] ) );
		if ( isset( tp_invoice_statuses()[ $inv ] ) && get_post_meta( $post_id, '_tp_invoice_status', true ) !== $inv ) {
			update_post_meta( $post_id, '_tp_invoice_status', $inv );
			tp_add_log( $post_id, array( 'note' => tp_invoice_statuses()[ $inv ] ) );
		}
	}
	$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : admin_url( 'admin.php?page=tp-finance' );
	wp_safe_redirect( add_query_arg( 'saved', 1, wp_validate_redirect( $back, admin_url( 'admin.php?page=tp-finance' ) ) ) );
	exit;
}

add_action( 'admin_post_tp_finance_export', 'tp_handle_finance_export' );
function tp_handle_finance_export() {
	if ( ! current_user_can( 'tp_view_finance' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'tp_finance_export' );
	$f    = tp_finance_filters( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in tp_finance_filters().
	$rows = tp_finance_rows( $f );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="invoices-' . $f['from'] . '-to-' . $f['to'] . '.csv"' );
	$out  = fopen( 'php://output', 'w' );
	$safe = static function ( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
	};
	fputcsv( $out, array( 'Invoice / reference', 'Trip date', 'Status', 'Passenger', 'Invoice to', 'Invoice email', 'NDIS number', 'Payment', 'MPTP', 'Fare incl GST', 'GST', 'Promo code', 'Discount', 'Paid online', 'Balance', 'Invoice status', 'From', 'To', 'Km' ), ',', '"', '' );
	foreach ( $rows as $b ) {
		$fare = '' === (string) $b['fare'] ? '' : (float) $b['fare'];
		fputcsv(
			$out,
			array_map(
				$safe,
				array(
					$b['reference'],
					$b['date'],
					tp_statuses()[ $b['status'] ] ?? $b['status'],
					$b['name'],
					$b['invoice_name'],
					$b['invoice_email'],
					$b['ndis_number'],
					tp_all_payment_methods()[ $b['payment'] ] ?? $b['payment'],
					$b['mptp'] ? 'Yes' : '',
					'' === $fare ? '' : number_format( $fare, 2, '.', '' ),
					'' === $fare ? '' : number_format( $fare / 11, 2, '.', '' ),
					$b['promo'],
					(float) get_post_meta( $b['id'], '_tp_discount', true ) > 0 ? number_format( (float) get_post_meta( $b['id'], '_tp_discount', true ), 2, '.', '' ) : '',
					$b['paid'] > 0 ? number_format( $b['paid'], 2, '.', '' ) : '',
					'' === $fare ? '' : number_format( max( 0, $fare - $b['paid'] ), 2, '.', '' ),
					tp_invoice_statuses()[ $b['invoice'] ] ?? '',
					$b['pickup'],
					$b['dropoff'],
					$b['distance_km'] > 0 ? $b['distance_km'] : '',
				)
			),
			',',
			'"',
			''
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
