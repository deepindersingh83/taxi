<?php
/**
 * NDIS trip log: a monthly list of trips per participant for plan managers
 * and claims, as CSV or a printable page (save as PDF from the print dialog).
 *
 * - Office / accounts: Bookings → NDIS trip log.
 * - Coordinators: My Account → NDIS trip log (their own clients only).
 *
 * Trips are grouped by NDIS number (or the passenger's name when there is
 * no number). Completed trips and no-shows are listed; cancellations can be
 * included.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['ndis_provider_no'] = array( 'payments', __( 'NDIS provider registration number', 'taxi-peninsula' ), 'text', '', __( 'Shown on the NDIS trip log. Leave empty if you are not a registered provider.', 'taxi-peninsula' ) );
		$s['ndis_support_item'] = array( 'payments', __( 'NDIS support item for the trip log', 'taxi-peninsula' ), 'text', '', __( 'Optional: the support item number and name your plan managers ask for, from the current NDIS Pricing Arrangements (check it each financial year).', 'taxi-peninsula' ) );
		return $s;
	}
);

/**
 * Trips for a month, grouped by participant.
 *
 * @param string $month     YYYY-MM.
 * @param int    $user_id   Limit to bookings made by this account (0 = all).
 * @param bool   $cancelled Include cancelled trips.
 * @return array key => { name, ndis, invoice_name, invoice_email, trips[], total, km }
 */
function tp_ndis_log_rows( $month, $user_id = 0, $cancelled = false ) {
	$statuses = array_merge( array( 'completed', 'no_show' ), $cancelled ? array( 'cancelled' ) : array() );
	$meta     = array(
		array( 'key' => '_tp_date', 'value' => $month . '-', 'compare' => 'LIKE' ),
		array( 'key' => '_tp_status', 'value' => $statuses, 'compare' => 'IN' ),
		array(
			'relation' => 'OR',
			array( 'key' => '_tp_payment', 'value' => 'account' ),
			array( 'key' => '_tp_ndis_number', 'value' => '', 'compare' => '!=' ),
		),
	);
	if ( $user_id ) {
		$meta[] = array( 'key' => '_tp_customer_id', 'value' => (int) $user_id );
	}
	$ids = get_posts(
		array(
			'post_type'   => 'tp_booking',
			'post_status' => 'publish',
			'numberposts' => 2000,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		$b   = tp_get_booking( $id );
		$num = strtoupper( preg_replace( '/\s+/', '', (string) $b['ndis_number'] ) );
		$key = substr( md5( $num ? 'n:' . $num : 'p:' . strtolower( trim( $b['name'] ) ) ), 0, 12 );
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array(
				'name'          => $b['name'],
				'ndis'          => $b['ndis_number'],
				'invoice_name'  => $b['invoice_name'],
				'invoice_email' => $b['invoice_email'],
				'trips'         => array(),
				'total'         => 0.0,
				'km'            => 0.0,
			);
		}
		$out[ $key ]['trips'][] = $b;
		if ( '' !== (string) $b['fare'] && 'cancelled' !== $b['status'] ) {
			$out[ $key ]['total'] += (float) $b['fare'];
		}
		$out[ $key ]['km'] += (float) $b['distance_km'];
	}
	uasort( $out, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
	return $out;
}

function tp_ndis_log_month( $raw ) {
	$m = sanitize_text_field( (string) $raw );
	return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $m ) ? $m : wp_date( 'Y-m', strtotime( 'first day of last month' ) );
}

/**
 * Link to a participant's log (CSV or print).
 */
function tp_ndis_log_url( $month, $key, $format, $cancelled = false ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'    => 'tp_ndis_log',
				'month'     => $month,
				'p'         => $key,
				'format'    => $format,
				'cancelled' => $cancelled ? 1 : 0,
			),
			admin_url( 'admin-post.php' )
		),
		'tp_ndis_log'
	);
}

/* -------------------------------------------------------------------------
 * Office page.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'invoices' ), __( 'NDIS trip log', 'taxi-peninsula' ), __( 'NDIS trip log', 'taxi-peninsula' ), 'tp_view_finance', 'tp-ndis-log', 'tp_render_ndis_log_page' );
	},
	41
);

function tp_render_ndis_log_page() {
	if ( ! current_user_can( 'tp_view_finance' ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$month     = tp_ndis_log_month( wp_unslash( $_GET['month'] ?? '' ) );
	$cancelled = ! empty( $_GET['cancelled'] );
	$sent      = isset( $_GET['emailed'] ) ? sanitize_text_field( wp_unslash( $_GET['emailed'] ) ) : '';
	// phpcs:enable
	$rows = tp_ndis_log_rows( $month, 0, $cancelled );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'NDIS trip log', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'A monthly list of trips for each participant, for plan managers and claims. Trips paid on account or with an NDIS number are included. Open "Print / PDF" and choose "Save as PDF" in the print dialog.', 'taxi-peninsula' ); ?></p>
		<?php if ( $sent ) : ?>
			<div class="notice notice-success"><p>
				<?php
				/* translators: %s: email */
				echo esc_html( sprintf( __( 'Trip log emailed to %s.', 'taxi-peninsula' ), $sent ) );
				?>
			</p></div>
		<?php endif; ?>
		<form method="get" class="tp-filters">
			<input type="hidden" name="post_type" value="tp_booking">
			<input type="hidden" name="page" value="tp-ndis-log">
			<label for="tp-ndis-month"><?php esc_html_e( 'Month', 'taxi-peninsula' ); ?></label>
			<input type="month" id="tp-ndis-month" name="month" value="<?php echo esc_attr( $month ); ?>">
			<label><input type="checkbox" name="cancelled" value="1" <?php checked( $cancelled ); ?>> <?php esc_html_e( 'Include cancellations', 'taxi-peninsula' ); ?></label>
			<?php submit_button( __( 'Show', 'taxi-peninsula' ), 'secondary', '', false ); ?>
			<a class="button" href="<?php echo esc_url( tp_ndis_log_url( $month, 'all', 'csv', $cancelled ) ); ?>"><?php esc_html_e( 'CSV for everyone', 'taxi-peninsula' ); ?></a>
		</form>
		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'No NDIS or account trips in this month.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Participant', 'taxi-peninsula' ); ?></th>
					<th><?php esc_html_e( 'NDIS number', 'taxi-peninsula' ); ?></th>
					<th><?php esc_html_e( 'Plan manager / invoice to', 'taxi-peninsula' ); ?></th>
					<th class="num"><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total', 'taxi-peninsula' ); ?></th>
					<th><?php esc_html_e( 'Log', 'taxi-peninsula' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $rows as $key => $p ) : ?>
						<tr>
							<td><?php echo esc_html( $p['name'] ); ?></td>
							<td><?php echo esc_html( $p['ndis'] ?: '—' ); ?></td>
							<td><?php echo esc_html( trim( $p['invoice_name'] . ' ' . ( $p['invoice_email'] ? '<' . $p['invoice_email'] . '>' : '' ) ) ?: '—' ); ?></td>
							<td class="num"><?php echo esc_html( count( $p['trips'] ) ); ?></td>
							<td class="num"><?php echo esc_html( tp_money( $p['total'] ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( tp_ndis_log_url( $month, $key, 'print', $cancelled ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print / PDF', 'taxi-peninsula' ); ?></a> ·
								<a href="<?php echo esc_url( tp_ndis_log_url( $month, $key, 'csv', $cancelled ) ); ?>"><?php esc_html_e( 'CSV', 'taxi-peninsula' ); ?></a>
								<?php if ( is_email( $p['invoice_email'] ) ) : ?>
									· <a href="<?php echo esc_url( tp_ndis_log_url( $month, $key, 'email', $cancelled ) ); ?>"><?php esc_html_e( 'Email to plan manager', 'taxi-peninsula' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Coordinator: My Account tab.
 * ---------------------------------------------------------------------- */

add_filter(
	'tp_account_tabs',
	static function ( $tabs, $coord ) {
		if ( $coord ) {
			$tabs['ndis'] = __( 'NDIS trip log', 'taxi-peninsula' );
		}
		return $tabs;
	},
	10,
	2
);

add_action(
	'tp_account_tab_ndis',
	static function ( $user ) {
		$month = tp_ndis_log_month( wp_unslash( $_GET['month'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows  = tp_ndis_log_rows( $month, $user->ID );
		?>
		<section class="account-section">
			<h2><?php esc_html_e( 'NDIS trip log', 'taxi-peninsula' ); ?></h2>
			<p><?php esc_html_e( 'Monthly trip lists for your clients, ready to send to plan managers.', 'taxi-peninsula' ); ?></p>
			<form method="get" class="inline-form" action="<?php echo esc_url( tp_account_url() ); ?>">
				<input type="hidden" name="tab" value="ndis">
				<div class="field">
					<label for="acc-ndis-month"><?php esc_html_e( 'Month', 'taxi-peninsula' ); ?></label>
					<input type="month" id="acc-ndis-month" name="month" value="<?php echo esc_attr( $month ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m' ) ); ?>">
				</div>
				<button type="submit" class="btn btn--ghost"><?php esc_html_e( 'Show', 'taxi-peninsula' ); ?></button>
			</form>
			<?php if ( ! $rows ) : ?>
				<p class="empty-note"><?php esc_html_e( 'No completed trips for your clients in this month.', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<div class="table-scroll">
					<table class="data-table">
						<thead><tr><th scope="col"><?php esc_html_e( 'Client', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'NDIS number', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'Total', 'taxi-peninsula' ); ?></th><th scope="col"><?php esc_html_e( 'Download', 'taxi-peninsula' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $rows as $key => $p ) : ?>
								<tr>
									<td><?php echo esc_html( $p['name'] ); ?></td>
									<td><?php echo esc_html( $p['ndis'] ?: '—' ); ?></td>
									<td><?php echo esc_html( count( $p['trips'] ) ); ?></td>
									<td><?php echo esc_html( tp_money( $p['total'] ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( tp_ndis_log_url( $month, $key, 'print' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'PDF', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $p['name'] ); ?></span></a> ·
										<a href="<?php echo esc_url( tp_ndis_log_url( $month, $key, 'csv' ) ); ?>"><?php esc_html_e( 'CSV', 'taxi-peninsula' ); ?><span class="screen-reader-text"> <?php echo esc_html( $p['name'] ); ?></span></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
);

/* -------------------------------------------------------------------------
 * Output: CSV, print, email.
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_tp_ndis_log', 'tp_handle_ndis_log' );
function tp_handle_ndis_log() {
	check_admin_referer( 'tp_ndis_log' );
	$office = current_user_can( 'tp_view_finance' );
	if ( ! $office && ! tp_is_coordinator() ) {
		wp_die( esc_html__( 'You cannot view this trip log.', 'taxi-peninsula' ), 403 );
	}
	$month     = tp_ndis_log_month( wp_unslash( $_GET['month'] ?? '' ) );
	$key       = isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : '';
	$format    = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'print';
	$cancelled = ! empty( $_GET['cancelled'] );
	$rows      = tp_ndis_log_rows( $month, $office ? 0 : get_current_user_id(), $cancelled );
	if ( 'all' !== $key ) {
		$rows = isset( $rows[ $key ] ) ? array( $key => $rows[ $key ] ) : array();
	}
	if ( ! $rows ) {
		wp_die( esc_html__( 'No trips found for this participant and month.', 'taxi-peninsula' ), 404 );
	}
	if ( function_exists( 'tp_activity' ) ) {
		/* translators: 1: month 2: format */
		tp_activity( 'privacy', sprintf( __( 'NDIS trip log %1$s downloaded (%2$s)', 'taxi-peninsula' ), $month, $format ) );
	}
	$slug = 'ndis-trips-' . $month . ( 'all' === $key ? '' : '-' . sanitize_title( reset( $rows )['name'] ) );

	if ( 'csv' === $format ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '.csv"' );
		tp_ndis_log_csv( $rows, fopen( 'php://output', 'w' ) );
		exit;
	}
	if ( 'email' === $format && $office ) {
		$p    = reset( $rows );
		$file = trailingslashit( get_temp_dir() ) . $slug . '.csv';
		$fh   = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		tp_ndis_log_csv( $rows, $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		wp_mail(
			$p['invoice_email'],
			/* translators: 1: participant 2: month 3: site */
			sprintf( __( 'Trip log for %1$s — %2$s — %3$s', 'taxi-peninsula' ), $p['name'], wp_date( 'F Y', strtotime( $month . '-01' ) ), $site ),
			/* translators: 1: participant 2: month 3: trips 4: total */
			sprintf( __( 'Hello,', 'taxi-peninsula' ) . "\n\n" . __( 'Attached is the trip log for %1$s for %2$s: %3$d trips, %4$s in total.', 'taxi-peninsula' ) . "\n\n" . __( 'Please contact us if you have any questions.', 'taxi-peninsula' ), $p['name'], wp_date( 'F Y', strtotime( $month . '-01' ) ), count( $p['trips'] ), tp_money( $p['total'] ) ) . "\n\n" . $site,
			tp_mail_headers( tp_opt( 'email' ) ),
			array( $file )
		);
		wp_delete_file( $file );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-ndis-log&month=' . $month . '&emailed=' . rawurlencode( $p['invoice_email'] ) ) );
		exit;
	}
	tp_ndis_log_print( $rows, $month );
	exit;
}

function tp_ndis_log_csv( array $rows, $out ) {
	$safe = static function ( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
	};
	$item = (string) tp_setting( 'ndis_support_item' );
	fputcsv( $out, array( 'Participant', 'NDIS number', 'Plan manager', 'Date', 'Pick-up time', 'Reference', 'From', 'To', 'Km', 'Status', 'Fare incl GST', 'GST', 'Support item' ), ',', '"', '' );
	foreach ( $rows as $p ) {
		foreach ( $p['trips'] as $b ) {
			$fare = '' === (string) $b['fare'] ? '' : (float) $b['fare'];
			fputcsv(
				$out,
				array_map(
					$safe,
					array(
						$p['name'],
						$p['ndis'],
						$p['invoice_name'],
						$b['date'],
						$b['time'],
						$b['reference'],
						$b['pickup'],
						$b['dropoff'],
						$b['distance_km'] > 0 ? number_format( $b['distance_km'], 1, '.', '' ) : '',
						tp_statuses()[ $b['status'] ] ?? $b['status'],
						'' === $fare || 'cancelled' === $b['status'] ? '' : number_format( $fare, 2, '.', '' ),
						'' === $fare || 'cancelled' === $b['status'] ? '' : number_format( $fare / 11, 2, '.', '' ),
						$item,
					)
				),
				',',
				'"',
				''
			);
		}
	}
}

function tp_ndis_log_print( array $rows, $month ) {
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	$c    = tp_design_colors();
	$site = get_bloginfo( 'name' );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( __( 'NDIS trip log', 'taxi-peninsula' ) . ' ' . wp_date( 'F Y', strtotime( $month . '-01' ) ) . ' — ' . $site ); ?></title>
	<style>
		body { font: 15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #16213a; background: #eef2f7; margin: 0; padding: 24px; }
		.doc { max-width: 980px; margin: 0 auto 24px; background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 24px rgb(0 0 0 / .08); page-break-after: always; }
		.doc:last-of-type { page-break-after: auto; }
		.head { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; border-bottom: 4px solid <?php echo esc_attr( $c['accent'] ); ?>; padding-bottom: 12px; margin-bottom: 16px; }
		h1 { margin: 0; font-size: 24px; color: <?php echo esc_attr( $c['primary'] ); ?>; }
		.biz { text-align: right; }
		dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 16px; margin: 0 0 16px; }
		dt { font-weight: 700; }
		table { width: 100%; border-collapse: collapse; }
		th, td { text-align: left; padding: 7px 6px; border-bottom: 1px solid #d5dcea; vertical-align: top; }
		th { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #4a5670; }
		.n { text-align: right; white-space: nowrap; }
		tfoot td { font-weight: 700; border-top: 2px solid #16213a; }
		.note { color: #4a5670; font-size: 13px; }
		.actions { max-width: 980px; margin: 0 auto 16px; display: flex; gap: 12px; }
		.actions button { font: inherit; font-weight: 700; padding: 10px 18px; border-radius: 8px; border: 0; background: <?php echo esc_attr( $c['primary'] ); ?>; color: #fff; cursor: pointer; }
		@media print { body { background: #fff; padding: 0; } .doc { box-shadow: none; padding: 0; margin: 0; } .actions { display: none; } }
	</style>
</head>
<body>
	<div class="actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'taxi-peninsula' ); ?></button></div>
	<?php foreach ( $rows as $p ) : ?>
		<main class="doc">
			<header class="head">
				<div>
					<h1><?php esc_html_e( 'NDIS trip log', 'taxi-peninsula' ); ?></h1>
					<p><?php echo esc_html( wp_date( 'F Y', strtotime( $month . '-01' ) ) ); ?></p>
				</div>
				<div class="biz">
					<strong><?php echo esc_html( $site ); ?></strong><br>
					<?php if ( tp_setting( 'abn' ) ) : ?>
						<?php esc_html_e( 'ABN', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_setting( 'abn' ) ); ?><br>
					<?php endif; ?>
					<?php if ( tp_setting( 'ndis_provider_no' ) ) : ?>
						<?php esc_html_e( 'NDIS provider', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_setting( 'ndis_provider_no' ) ); ?><br>
					<?php endif; ?>
					<?php echo esc_html( wp_strip_all_tags( tp_opt( 'phone_display' ) ) ); ?>
				</div>
			</header>
			<dl>
				<dt><?php esc_html_e( 'Participant', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $p['name'] ); ?></dd>
				<dt><?php esc_html_e( 'NDIS number', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $p['ndis'] ?: '—' ); ?></dd>
				<dt><?php esc_html_e( 'Plan manager / invoice to', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $p['invoice_name'] ?: '—' ); ?></dd>
				<?php if ( tp_setting( 'ndis_support_item' ) ) : ?>
					<dt><?php esc_html_e( 'Support item', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( tp_setting( 'ndis_support_item' ) ); ?></dd>
				<?php endif; ?>
			</dl>
			<table>
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Time', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reference', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'From → To', 'taxi-peninsula' ); ?></th>
					<th scope="col" class="n"><?php esc_html_e( 'Km', 'taxi-peninsula' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></th>
					<th scope="col" class="n"><?php esc_html_e( 'Fare incl. GST', 'taxi-peninsula' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $p['trips'] as $b ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'D j M', strtotime( $b['date'] ) ) ); ?></td>
							<td><?php echo esc_html( $b['time'] ? wp_date( 'g:i a', strtotime( $b['date'] . ' ' . $b['time'] ) ) : '' ); ?></td>
							<td><?php echo esc_html( $b['reference'] ); ?></td>
							<td><?php echo esc_html( $b['pickup'] ); ?> → <?php echo esc_html( $b['dropoff'] ); ?></td>
							<td class="n"><?php echo $b['distance_km'] > 0 ? esc_html( number_format_i18n( $b['distance_km'], 1 ) ) : ''; ?></td>
							<td><?php echo esc_html( tp_statuses()[ $b['status'] ] ?? $b['status'] ); ?></td>
							<td class="n"><?php echo '' !== (string) $b['fare'] && 'cancelled' !== $b['status'] ? esc_html( tp_money( $b['fare'] ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="4">
							<?php
							/* translators: %d: trips */
							echo esc_html( sprintf( _n( '%d trip', '%d trips', count( $p['trips'] ), 'taxi-peninsula' ), count( $p['trips'] ) ) );
							?>
						</td>
						<td class="n"><?php echo $p['km'] > 0 ? esc_html( number_format_i18n( $p['km'], 1 ) ) : ''; ?></td>
						<td><?php esc_html_e( 'Total', 'taxi-peninsula' ); ?></td>
						<td class="n"><?php echo esc_html( tp_money( $p['total'] ) ); ?></td>
					</tr>
				</tfoot>
			</table>
			<p class="note">
				<?php
				/* translators: %s: GST */
				echo esc_html( sprintf( __( 'GST included: %s. Tax invoices for each trip are available from the receipt links in our emails, or on request.', 'taxi-peninsula' ), tp_money( $p['total'] / 11 ) ) );
				?>
			</p>
		</main>
	<?php endforeach; ?>
</body>
</html>
	<?php
}
