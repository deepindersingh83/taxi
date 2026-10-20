<?php
/**
 * Monthly statements for account customers: one statement per invoice email
 * (NDIS plan manager, aged-care home, business) per month, listing every
 * completed or no-show account trip, GST, payments and the balance, with
 * BPAY / PayID / bank details. A printable page (save as PDF from the
 * browser) with a private link, emailed by the office or automatically on
 * the 1st of each month.
 *
 * Invoices → Monthly statements.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['statements_auto'] = array( 'payments', __( 'Email monthly statements automatically', 'taxi-peninsula' ), 'checkbox', false, __( 'On the 1st of each month, every account customer with trips last month is emailed a statement. You can also send them by hand under Invoices → Monthly statements.', 'taxi-peninsula' ) );
		return $s;
	}
);

/**
 * Account trips in a month, grouped by invoice email.
 *
 * @param string $month Y-m.
 * @return array email => [name, email, ids[]]
 */
function tp_statement_groups( $month ) {
	$ids = function_exists( 'tp_trips_ids' ) ? tp_trips_ids( array( 'from' => $month . '-01', 'to' => $month . '-31', 'payment' => 'account', 'status' => array( 'completed', 'no_show' ) ) ) : null;
	$ids = null !== $ids ? $ids : get_posts(
		array(
			'post_type'   => 'tp_booking',
			'post_status' => 'publish',
			'numberposts' => 3000,
			'fields'      => 'ids',
			'meta_key'    => '_tp_pickup_at', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => '_tp_date', 'value' => $month . '-', 'compare' => 'LIKE' ),
				array( 'key' => '_tp_payment', 'value' => 'account' ),
				array( 'key' => '_tp_status', 'value' => array( 'completed', 'no_show' ), 'compare' => 'IN' ),
			),
		)
	);
	$groups = array();
	foreach ( $ids as $id ) {
		$email = strtolower( trim( (string) get_post_meta( $id, '_tp_invoice_email', true ) ) );
		if ( ! is_email( $email ) || ! apply_filters( 'tp_statement_include_trip', true, (int) $id ) ) {
			continue;
		}
		if ( ! isset( $groups[ $email ] ) ) {
			$groups[ $email ] = array( 'name' => (string) get_post_meta( $id, '_tp_invoice_name', true ), 'email' => $email, 'ids' => array() );
		}
		$groups[ $email ]['ids'][] = (int) $id;
	}
	ksort( $groups );
	return $groups;
}

/**
 * Stored record for a statement (number, sent time), created on first use.
 */
function tp_statement_record( $month, $email ) {
	$all = (array) get_option( 'tp_statements', array() );
	$key = $month . '|' . strtolower( $email );
	if ( empty( $all[ $key ] ) ) {
		$seq          = (int) get_option( 'tp_statement_seq', 0 ) + 1;
		$all[ $key ]  = array( 'no' => $seq, 'sent' => 0 );
		update_option( 'tp_statement_seq', $seq, false );
		update_option( 'tp_statements', $all, false );
	}
	return $all[ $key ];
}

function tp_statement_mark_sent( $month, $email ) {
	$all = (array) get_option( 'tp_statements', array() );
	$key = $month . '|' . strtolower( $email );
	if ( isset( $all[ $key ] ) ) {
		$all[ $key ]['sent'] = time();
		update_option( 'tp_statements', $all, false );
	}
}

function tp_statement_number( $no ) {
	return 'S-' . str_pad( (string) $no, 5, '0', STR_PAD_LEFT );
}

function tp_statement_token( $month, $email ) {
	return substr( hash_hmac( 'sha256', 'statement|' . $month . '|' . strtolower( $email ), wp_salt( 'auth' ) ), 0, 24 );
}

function tp_statement_url( $month, $email ) {
	return add_query_arg(
		array(
			'tp_statement' => $month,
			'to'           => rawurlencode( strtolower( $email ) ),
			't'            => tp_statement_token( $month, $email ),
		),
		home_url( '/' )
	);
}

/**
 * Lines and totals for a statement.
 */
function tp_statement_data( array $ids ) {
	$rows = array();
	$t    = array( 'fare' => 0.0, 'gst' => 0.0, 'paid' => 0.0, 'owing' => 0.0 );
	foreach ( $ids as $id ) {
		$b     = tp_get_booking( $id );
		$fare  = '' === (string) $b['fare'] ? 0.0 : (float) $b['fare'];
		$paid  = 'paid' === $b['invoice'] ? $fare : min( $fare, (float) $b['paid'] );
		$owing = max( 0, $fare - $paid );
		$rows[] = array( 'b' => $b, 'fare' => $fare, 'gst' => $fare / 11, 'paid' => $paid, 'owing' => $owing );
		$t['fare']  += $fare;
		$t['gst']   += $fare / 11;
		$t['paid']  += $paid;
		$t['owing'] += $owing;
	}
	return array( $rows, $t );
}

/* -------------------------------------------------------------------------
 * The statement page (?tp_statement=Y-m&to=email&t=token).
 * ---------------------------------------------------------------------- */

add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the check.
		if ( empty( $_GET['tp_statement'] ) ) {
			return;
		}
		$month = sanitize_text_field( wp_unslash( $_GET['tp_statement'] ) );
		$email = strtolower( sanitize_email( rawurldecode( wp_unslash( $_GET['to'] ?? '' ) ) ) );
		$tok   = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );
		// phpcs:enable
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) || ! is_email( $email ) || ! hash_equals( tp_statement_token( $month, $email ), $tok ) ) {
			wp_die( esc_html__( 'This statement link is not valid. Please contact us for a copy.', 'taxi-peninsula' ), '', array( 'response' => 403 ) );
		}
		$group = tp_statement_groups( $month )[ $email ] ?? null;
		if ( ! $group ) {
			wp_die( esc_html__( 'There are no account trips for this month.', 'taxi-peninsula' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		tp_render_statement( $month, $group );
		exit;
	},
	1
);

function tp_render_statement( $month, array $group ) {
	$rec                = tp_statement_record( $month, $group['email'] );
	$no                 = tp_statement_number( $rec['no'] );
	list( $rows, $tot ) = tp_statement_data( $group['ids'] );
	$site               = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$c                  = tp_design_colors();
	$label              = wp_date( 'F Y', strtotime( $month . '-01' ) );
	$terms              = trim( (string) tp_setting( 'payment_terms' ) );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( sprintf( /* translators: 1: month 2: number */ __( 'Statement %2$s — %1$s', 'taxi-peninsula' ), $label, $no ) . ' — ' . $site ); ?></title>
	<style>
		body { font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #16213a; background: #eef2f7; margin: 0; padding: 24px; }
		.doc { max-width: 960px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 24px rgb(0 0 0 / .08); }
		.doc__head { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; border-bottom: 4px solid <?php echo esc_attr( $c['accent'] ); ?>; padding-bottom: 16px; margin-bottom: 24px; }
		h1 { margin: 0; font-size: 28px; color: <?php echo esc_attr( $c['primary'] ); ?>; }
		h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .05em; color: #4a5670; margin: 0 0 4px; }
		.biz { text-align: right; } .biz strong { font-size: 18px; color: <?php echo esc_attr( $c['primary'] ); ?>; }
		.summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 0 0 24px; }
		.summary div { padding: 12px; background: #f3f7fc; border-radius: 8px; } .summary strong { display: block; font-size: 20px; }
		table { width: 100%; border-collapse: collapse; font-size: 14px; }
		th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #d5dcea; vertical-align: top; }
		td.n, th.n { text-align: right; white-space: nowrap; }
		tfoot td { font-weight: 700; border-top: 2px solid #16213a; }
		.note { color: #4a5670; font-size: 13px; }
		.bpay-box { display: inline-block; padding: .75rem 1rem; border: 2px solid #1b3c6e; border-radius: .5rem; color: #1b3c6e; } .bpay-box p { margin: 0; } .bpay-box__brand { font-weight: 800; font-style: italic; }
		.pay-options__list { padding-left: 1.2em; }
		.actions { max-width: 960px; margin: 16px auto 0; } .actions button { font: inherit; font-weight: 700; padding: 10px 18px; border-radius: 8px; border: 0; background: <?php echo esc_attr( $c['primary'] ); ?>; color: #fff; cursor: pointer; }
		@media (max-width: 700px) { body { padding: 8px; } .doc { padding: 20px; } .summary { grid-template-columns: 1fr 1fr; } .biz { text-align: left; } .wide { display: none; } }
		@media print { body { background: #fff; padding: 0; } .doc { box-shadow: none; padding: 0; } .actions { display: none; } }
	</style>
</head>
<body>
	<main class="doc">
		<header class="doc__head">
			<div>
				<h1><?php esc_html_e( 'Statement', 'taxi-peninsula' ); ?></h1>
				<p><?php esc_html_e( 'Number', 'taxi-peninsula' ); ?>: <strong><?php echo esc_html( $no ); ?></strong><br>
					<?php esc_html_e( 'Period', 'taxi-peninsula' ); ?>: <?php echo esc_html( $label ); ?><br>
					<?php esc_html_e( 'Date', 'taxi-peninsula' ); ?>: <?php echo esc_html( wp_date( 'j F Y' ) ); ?></p>
				<h2><?php esc_html_e( 'To', 'taxi-peninsula' ); ?></h2>
				<p><strong><?php echo esc_html( $group['name'] ?: $group['email'] ); ?></strong><br><?php echo esc_html( $group['email'] ); ?></p>
			</div>
			<div class="biz">
				<strong><?php echo esc_html( $site ); ?></strong><br>
				<?php if ( tp_setting( 'abn' ) ) : ?>ABN <?php echo esc_html( tp_setting( 'abn' ) ); ?><br><?php endif; ?>
				<?php echo nl2br( esc_html( (string) tp_setting( 'business_address' ) ) ); ?><br>
				<?php echo esc_html( tp_opt( 'phone_display' ) . ' · ' . tp_opt( 'email' ) ); ?>
			</div>
		</header>
		<div class="summary">
			<div><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?><strong><?php echo esc_html( count( $rows ) ); ?></strong></div>
			<div><?php esc_html_e( 'Total incl. GST', 'taxi-peninsula' ); ?><strong><?php echo esc_html( tp_money( $tot['fare'] ) ); ?></strong></div>
			<div><?php esc_html_e( 'Paid', 'taxi-peninsula' ); ?><strong><?php echo esc_html( tp_money( $tot['paid'] ) ); ?></strong></div>
			<div><?php esc_html_e( 'Balance due', 'taxi-peninsula' ); ?><strong><?php echo esc_html( tp_money( $tot['owing'] ) ); ?></strong></div>
		</div>
		<table>
			<thead><tr><th><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Invoice', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Passenger', 'taxi-peninsula' ); ?></th><th class="wide"><?php esc_html_e( 'Trip', 'taxi-peninsula' ); ?></th><th class="n"><?php esc_html_e( 'Amount', 'taxi-peninsula' ); ?></th><th class="n wide"><?php esc_html_e( 'GST', 'taxi-peninsula' ); ?></th><th class="n"><?php esc_html_e( 'Due', 'taxi-peninsula' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<?php $b = $r['b']; ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'j M', strtotime( $b['date'] ) ) ); ?></td>
						<td><a href="<?php echo esc_url( tp_receipt_url( $b['id'] ) ); ?>"><?php echo esc_html( $b['reference'] ); ?></a><?php echo 'no_show' === $b['status'] ? '<br><small>' . esc_html__( 'No-show', 'taxi-peninsula' ) . '</small>' : ''; ?></td>
						<td><?php echo esc_html( $b['name'] ); ?><?php echo $b['ndis_number'] ? '<br><small>' . esc_html( $b['ndis_number'] ) . '</small>' : ''; ?></td>
						<td class="wide"><?php echo esc_html( ( tp_short_place( $b['pickup'] ) ?: $b['pickup'] ) . ' → ' . ( tp_short_place( $b['dropoff'] ) ?: $b['dropoff'] ) ); ?></td>
						<td class="n"><?php echo esc_html( tp_money( $r['fare'] ) ); ?></td>
						<td class="n wide"><?php echo esc_html( tp_money( $r['gst'] ) ); ?></td>
						<td class="n"><?php echo esc_html( $r['owing'] > 0 ? tp_money( $r['owing'] ) : __( 'Paid', 'taxi-peninsula' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot><tr><td colspan="4" class="wide"><?php esc_html_e( 'Total', 'taxi-peninsula' ); ?></td><td class="n"><?php echo esc_html( tp_money( $tot['fare'] ) ); ?></td><td class="n wide"><?php echo esc_html( tp_money( $tot['gst'] ) ); ?></td><td class="n"><?php echo esc_html( tp_money( $tot['owing'] ) ); ?></td></tr></tfoot>
		</table>
		<?php if ( $tot['owing'] > 0 && function_exists( 'tp_pay_options_html' ) && tp_pay_options_available() ) : ?>
			<h2 style="margin-top:24px"><?php esc_html_e( 'How to pay', 'taxi-peninsula' ); ?></h2>
			<?php echo tp_pay_options_html( 'statement', $rec['no'], $no ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php endif; ?>
		<?php if ( $terms ) : ?>
			<p class="note"><?php echo esc_html( $terms ); ?></p>
		<?php endif; ?>
		<p class="note"><?php esc_html_e( 'Each trip has its own tax invoice (click the invoice number). Amounts include GST.', 'taxi-peninsula' ); ?></p>
	</main>
	<p class="actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'taxi-peninsula' ); ?></button></p>
</body>
</html>
	<?php
}

/* -------------------------------------------------------------------------
 * Sending.
 * ---------------------------------------------------------------------- */

function tp_send_statement( $month, array $group ) {
	$rec                = tp_statement_record( $month, $group['email'] );
	$no                 = tp_statement_number( $rec['no'] );
	list( $rows, $tot ) = tp_statement_data( $group['ids'] );
	$site               = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$label              = wp_date( 'F Y', strtotime( $month . '-01' ) );
	$ok                 = false;
	tp_in_site_lang(
		static function () use ( $month, $group, $rows, $tot, $no, $site, $label, $rec, &$ok ) {
			/* translators: %s: name */
			$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $group['name'] ?: __( 'there', 'taxi-peninsula' ) ) . "\n\n";
			/* translators: 1: month 2: count */
			$body .= sprintf( _n( 'Here is your statement for %1$s: %2$d trip.', 'Here is your statement for %1$s: %2$d trips.', count( $rows ), 'taxi-peninsula' ), $label, count( $rows ) ) . "\n\n";
			$body .= __( 'Statement', 'taxi-peninsula' ) . ': ' . $no . "\n";
			$body .= __( 'Total incl. GST', 'taxi-peninsula' ) . ': ' . tp_money( $tot['fare'] ) . "\n";
			$body .= __( 'Balance due', 'taxi-peninsula' ) . ': ' . tp_money( $tot['owing'] ) . "\n\n";
			$body .= __( 'View, print or save the statement', 'taxi-peninsula' ) . ': ' . tp_statement_url( $month, $group['email'] ) . "\n\n";
			if ( $tot['owing'] > 0 && function_exists( 'tp_pay_options_text' ) ) {
				$body .= tp_pay_options_text( 'statement', $rec['no'], $no ) . "\n";
			}
			/* translators: %s: phone */
			$body .= sprintf( __( 'Questions? Reply to this email or call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) . "\n\n" . $site . "\n";
			$ok    = wp_mail(
				$group['email'],
				/* translators: 1: site 2: month 3: number */
				sprintf( __( '%1$s statement for %2$s (%3$s)', 'taxi-peninsula' ), $site, $label, $no ),
				$body,
				tp_mail_headers( tp_opt( 'email' ) )
			);
		}
	);
	if ( $ok ) {
		tp_statement_mark_sent( $month, $group['email'] );
	}
	return $ok;
}

// The 1st of the month: send last month's statements (once).
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'tp_statements_run' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 07:20' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'tp_statements_run' );
		}
	}
);
add_action(
	'tp_statements_run',
	static function () {
		if ( ! tp_setting( 'statements_auto' ) || '01' !== wp_date( 'd' ) ) {
			return;
		}
		$month = wp_date( 'Y-m', strtotime( 'first day of last month' ) );
		if ( get_option( 'tp_statements_auto_done' ) === $month ) {
			return;
		}
		update_option( 'tp_statements_auto_done', $month, false );
		foreach ( tp_statement_groups( $month ) as $group ) {
			$rec = tp_statement_record( $month, $group['email'] );
			if ( empty( $rec['sent'] ) ) {
				tp_send_statement( $month, $group );
			}
		}
	}
);

/* -------------------------------------------------------------------------
 * Admin: Invoices → Monthly statements.
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'invoices' ), __( 'Monthly statements', 'taxi-peninsula' ), __( 'Monthly statements', 'taxi-peninsula' ), 'tp_view_finance', 'tp-statements', 'tp_render_statements_page' );
	},
	30
);

add_action(
	'admin_post_tp_send_statements',
	static function () {
		if ( ! current_user_can( 'tp_view_finance' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_send_statements' );
		$month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : '';
		$only  = isset( $_POST['email'] ) ? strtolower( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : '';
		$sent  = 0;
		$fail  = 0;
		if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			foreach ( tp_statement_groups( $month ) as $email => $group ) {
				if ( $only && $only !== $email ) {
					continue;
				}
				if ( tp_send_statement( $month, $group ) ) {
					++$sent;
				} else {
					++$fail;
				}
			}
		}
		/* translators: 1: sent 2: failed */
		set_transient( 'tp_admin_notice_' . get_current_user_id(), array( $fail ? 'warning' : 'success', sprintf( __( 'Statements emailed: %1$d. Failed: %2$d.', 'taxi-peninsula' ), $sent, $fail ) ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-statements&month=' . rawurlencode( $month ) ) );
		exit;
	}
);

function tp_render_statements_page() {
	$month  = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$month  = preg_match( '/^\d{4}-\d{2}$/', $month ) ? $month : wp_date( 'Y-m', strtotime( 'first day of last month' ) );
	$groups = tp_statement_groups( $month );
	$all    = (array) get_option( 'tp_statements', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Monthly statements', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'One statement a month for each account customer (grouped by invoice email): every completed and no-show trip, GST, payments and the balance, with your BPAY / bank details. Customers can print it or save it as a PDF.', 'taxi-peninsula' ); ?>
			<?php echo tp_setting( 'statements_auto' ) ? esc_html__( 'Statements are emailed automatically on the 1st.', 'taxi-peninsula' ) : wp_kses_post( sprintf( /* translators: %s: link */ __( 'To email them automatically on the 1st, turn it on in <a href="%s">Taxi settings → Payments & invoices</a>.', 'taxi-peninsula' ), esc_url( tp_settings_url( 'payments' ) ) ) ); ?></p>
		<form method="get"><input type="hidden" name="page" value="tp-statements">
			<label for="tp-st-month"><?php esc_html_e( 'Month', 'taxi-peninsula' ); ?></label>
			<input type="month" id="tp-st-month" name="month" value="<?php echo esc_attr( $month ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m' ) ); ?>">
			<?php submit_button( __( 'Show', 'taxi-peninsula' ), 'secondary', '', false ); ?>
		</form>
		<?php if ( ! $groups ) : ?>
			<p><?php esc_html_e( 'No completed account trips with an invoice email in this month.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="margin-top:12px">
				<thead><tr><th><?php esc_html_e( 'Invoice to', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Trips', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Total', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Balance due', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Emailed', 'taxi-peninsula' ); ?></th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $groups as $email => $g ) : ?>
						<?php
						list( , $tot ) = tp_statement_data( $g['ids'] );
						$rec           = $all[ $month . '|' . $email ] ?? array();
						?>
						<tr>
							<td><strong><?php echo esc_html( $g['name'] ?: '—' ); ?></strong><br><?php echo esc_html( $email ); ?></td>
							<td><?php echo esc_html( count( $g['ids'] ) ); ?></td>
							<td><?php echo esc_html( tp_money( $tot['fare'] ) ); ?></td>
							<td><?php echo esc_html( tp_money( $tot['owing'] ) ); ?></td>
							<td><?php echo ! empty( $rec['sent'] ) ? esc_html( wp_date( 'j M g:ia', $rec['sent'] ) ) : '—'; ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( tp_statement_url( $month, $email ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'taxi-peninsula' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
									<input type="hidden" name="action" value="tp_send_statements"><input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>"><input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
									<?php wp_nonce_field( 'tp_send_statements' ); ?>
									<button class="button button-small"><?php echo ! empty( $rec['sent'] ) ? esc_html__( 'Email again', 'taxi-peninsula' ) : esc_html__( 'Email', 'taxi-peninsula' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
				<input type="hidden" name="action" value="tp_send_statements"><input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>">
				<?php wp_nonce_field( 'tp_send_statements' ); ?>
				<?php submit_button( __( 'Email every statement for this month', 'taxi-peninsula' ), 'primary', 'submit', false, array( 'onclick' => 'return confirm(' . wp_json_encode( __( 'Email all statements for this month now?', 'taxi-peninsula' ) ) . ')' ) ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
