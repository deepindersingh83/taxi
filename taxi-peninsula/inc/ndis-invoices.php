<?php
/**
 * NDIS invoices to plan managers: one tax invoice per participant per month,
 * in the shape plan managers process.
 *
 * - Provider name, ABN and NDIS registration number; invoice number and date,
 *   due date; participant name and NDIS number; one line per trip with the
 *   date of support, support item number, quantity, unit price and GST.
 * - Emailed to the plan manager (the booking's invoice email) with the
 *   invoice as a PDF and a bulk-claim CSV in the NDIA layout, plus a link to
 *   view it online. Office: Invoices → NDIS trip log.
 * - When on, trips with an NDIS number come off the monthly statement so
 *   nothing is billed twice.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['ndis_invoices']     = array( 'payments', __( 'Bill NDIS trips on NDIS invoices to plan managers', 'taxi-peninsula' ), 'checkbox', false, __( 'One tax invoice per participant per month, emailed to the plan manager with a PDF and a bulk-claim file. These trips then come off the monthly statement, so nothing is billed twice. Set the support item number below.', 'taxi-peninsula' ) );
		$s['ndis_gst']          = array( 'payments', __( 'GST on NDIS invoices', 'taxi-peninsula' ), 'select', 'free', __( 'NDIS supports are usually GST-free. Check with your accountant.', 'taxi-peninsula' ), array( 'free' => __( 'GST-free (NDIS supports)', 'taxi-peninsula' ), 'incl' => __( 'Fares include GST', 'taxi-peninsula' ) ) );
		$s['ndis_invoice_days'] = array( 'payments', __( 'NDIS invoices due after (days)', 'taxi-peninsula' ), 'number', 14, '' );
		$s['ndis_invoice_auto'] = array( 'payments', __( 'Email last month’s NDIS invoices automatically on the 1st', 'taxi-peninsula' ), 'checkbox', false, '' );
		return $s;
	}
);

function tp_ndis_invoices_on() {
	return (bool) tp_setting( 'ndis_invoices' );
}

/**
 * Participants with an NDIS number and a plan-manager email, for a month.
 */
function tp_ndis_invoice_groups( $month ) {
	$out = array();
	foreach ( tp_ndis_log_rows( $month ) as $key => $p ) {
		$p['trips'] = array_values( array_filter( $p['trips'], static fn( $b ) => 'cancelled' !== $b['status'] && '' !== (string) $b['fare'] && (float) $b['fare'] > 0 ) );
		if ( '' !== trim( (string) $p['ndis'] ) && is_email( $p['invoice_email'] ) && $p['trips'] ) {
			$out[ $key ] = $p;
		}
	}
	return $out;
}

/** Stored record (number, sent time), created on first use. */
function tp_ndis_invoice_record( $month, $key ) {
	$all = (array) get_option( 'tp_ndis_invoices', array() );
	$id  = $month . '|' . $key;
	if ( empty( $all[ $id ] ) ) {
		$seq        = (int) get_option( 'tp_ndis_invoice_seq', 0 ) + 1;
		$all[ $id ] = array( 'no' => $seq, 'sent' => 0, 'to' => '' );
		update_option( 'tp_ndis_invoice_seq', $seq, false );
		update_option( 'tp_ndis_invoices', $all, false );
	}
	return $all[ $id ];
}

function tp_ndis_invoice_number( $no ) {
	return 'N-' . str_pad( (string) $no, 5, '0', STR_PAD_LEFT );
}

function tp_ndis_invoice_token( $month, $key ) {
	return substr( hash_hmac( 'sha256', 'ndis-invoice|' . $month . '|' . $key, wp_salt( 'auth' ) ), 0, 24 );
}

function tp_ndis_invoice_url( $month, $key, $pdf = false ) {
	return add_query_arg( array_filter( array( 'tp_ndis_invoice' => $month, 'p' => $key, 't' => tp_ndis_invoice_token( $month, $key ), 'pdf' => $pdf ? 1 : 0 ) ), home_url( '/' ) );
}

/** The support item number (e.g. 04_590_0125_6_1) from the setting, which may also hold its name. */
function tp_ndis_item_code() {
	return preg_match( '/\d{2}_\d{3}_\d{4}_\d_\d(_T)?/', (string) tp_setting( 'ndis_support_item' ), $m ) ? $m[0] : '';
}

/**
 * Lines and totals for an invoice.
 */
function tp_ndis_invoice_data( $month, $key, array $p ) {
	$rec   = tp_ndis_invoice_record( $month, $key );
	$free  = 'incl' !== tp_setting( 'ndis_gst' );
	$lines = array();
	$t     = array( 'total' => 0.0, 'gst' => 0.0, 'paid' => 0.0, 'due' => 0.0 );
	foreach ( $p['trips'] as $b ) {
		$fare = (float) $b['fare'];
		$gst  = $free ? 0.0 : round( $fare / 11, 2 );
		$paid = 'paid' === $b['invoice'] ? $fare : min( $fare, (float) $b['paid'] );
		$from = tp_short_place( $b['pickup'] ) ?: $b['pickup'];
		$to   = tp_short_place( $b['dropoff'] ) ?: $b['dropoff'];
		$lines[] = array(
			'b'     => $b,
			'date'  => $b['date'],
			'desc'  => ( 'no_show' === $b['status'] ? __( 'Short-notice cancellation / no-show', 'taxi-peninsula' ) . ': ' : __( 'Wheelchair accessible taxi', 'taxi-peninsula' ) . ': ' ) . $from . ' → ' . $to,
			'qty'   => 1,
			'price' => $fare,
			'gst'   => $gst,
			'total' => $fare,
		);
		$t['total'] += $fare;
		$t['gst']   += $gst;
		$t['paid']  += $paid;
	}
	$t['due'] = max( 0, $t['total'] - $t['paid'] );
	$issued   = $rec['sent'] ? $rec['sent'] : time();
	return array(
		'no'     => tp_ndis_invoice_number( $rec['no'] ),
		'seq'    => $rec['no'],
		'issued' => $issued,
		'due_on' => $issued + max( 0, (int) tp_setting( 'ndis_invoice_days' ) ) * DAY_IN_SECONDS,
		'free'   => $free,
		'item'   => tp_ndis_item_code(),
		'lines'  => $lines,
		't'      => $t,
		'label'  => wp_date( 'F Y', strtotime( $month . '-01' ) ),
	);
}

/* -------------------------------------------------------------------------
 * PDF.
 * ---------------------------------------------------------------------- */

function tp_ndis_invoice_pdf( $month, $key, array $p ) {
	$d    = tp_ndis_invoice_data( $month, $key, $p );
	$c    = tp_design_colors();
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$pdf  = new TP_PDF();
	$L    = 48;
	$R    = TP_PDF::W - 48;

	// Header.
	$pdf->text( $L, 64, __( 'TAX INVOICE', 'taxi-peninsula' ), 22, true, $c['primary'] );
	$pdf->text( $L, 82, __( 'NDIS supports', 'taxi-peninsula' ) . ' · ' . $d['label'], 10, false, '#4a5670' );
	$y = 60;
	foreach ( array_filter( array( $site, tp_setting( 'abn' ) ? 'ABN ' . tp_setting( 'abn' ) : '', tp_setting( 'ndis_provider_no' ) ? __( 'NDIS provider', 'taxi-peninsula' ) . ' ' . tp_setting( 'ndis_provider_no' ) : '', str_replace( "\n", ', ', (string) tp_setting( 'business_address' ) ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) . ' · ' . tp_opt( 'email' ) ) ) as $i => $line ) {
		$pdf->text( $R, $y, TP_PDF::fit( $line, 260, 0 === $i ? 12 : 9 ), 0 === $i ? 12 : 9, 0 === $i, 0 === $i ? $c['primary'] : '#16213a', 'right' );
		$y += 0 === $i ? 15 : 12;
	}
	$pdf->box( $L, max( 100, $y ) + 4, $R - $L, 3, $c['accent'] );
	$y = max( 100, $y ) + 28;

	// Invoice and participant details.
	$left  = array(
		__( 'Invoice number', 'taxi-peninsula' ) => $d['no'],
		__( 'Invoice date', 'taxi-peninsula' )   => wp_date( 'j M Y', $d['issued'] ),
		__( 'Due date', 'taxi-peninsula' )       => wp_date( 'j M Y', $d['due_on'] ),
		__( 'Period', 'taxi-peninsula' )         => $d['label'],
	);
	$right = array(
		__( 'Participant', 'taxi-peninsula' )    => $p['name'],
		__( 'NDIS number', 'taxi-peninsula' )    => $p['ndis'],
		__( 'Plan manager', 'taxi-peninsula' )   => $p['invoice_name'] ?: $p['invoice_email'],
		__( 'Email', 'taxi-peninsula' )          => $p['invoice_email'],
	);
	$ry = $y;
	foreach ( $left as $k => $v ) {
		$pdf->text( $L, $y, $k, 9, false, '#4a5670' );
		$pdf->text( $L + 92, $y, $v, 10, true );
		$y += 15;
	}
	foreach ( $right as $k => $v ) {
		$pdf->text( 300, $ry, $k, 9, false, '#4a5670' );
		$pdf->text( 382, $ry, TP_PDF::fit( $v, $R - 382, 10, true ), 10, true );
		$ry += 15;
	}
	$y = max( $y, $ry ) + 10;
	if ( $d['item'] || tp_setting( 'ndis_support_item' ) ) {
		$pdf->text( $L, $y, __( 'Support item', 'taxi-peninsula' ) . ': ' . TP_PDF::fit( (string) tp_setting( 'ndis_support_item' ), 440, 9 ), 9, false, '#16213a' );
		$y += 16;
	}

	// Lines.
	$cols = array( $L, $L + 62, $L + 280, 432, 0, 515 ); // Date, description, item; right edges of qty and price.
	$head = static function ( $y ) use ( $pdf, $cols, $L, $R, $c, $d ) {
		$pdf->box( $L, $y - 12, $R - $L, 20, '#eef2f7' );
		$pdf->text( $cols[0] + 4, $y, __( 'Date', 'taxi-peninsula' ), 8.5, true, '#4a5670' );
		$pdf->text( $cols[1], $y, __( 'Description', 'taxi-peninsula' ), 8.5, true, '#4a5670' );
		$pdf->text( $cols[2], $y, __( 'Item', 'taxi-peninsula' ), 8.5, true, '#4a5670' );
		$pdf->text( $cols[3], $y, __( 'Qty', 'taxi-peninsula' ), 8.5, true, '#4a5670', 'right' );
		$pdf->text( $cols[5] - 30, $y, __( 'Price', 'taxi-peninsula' ), 8.5, true, '#4a5670', 'right' );
		$pdf->text( $R - 4, $y, $d['free'] ? __( 'Amount', 'taxi-peninsula' ) : __( 'Amount incl. GST', 'taxi-peninsula' ), 8.5, true, '#4a5670', 'right' );
	};
	$y += 8;
	$head( $y );
	$y += 20;
	foreach ( $d['lines'] as $ln ) {
		if ( $y > TP_PDF::H - 150 ) {
			$pdf->add_page();
			$y = 60;
			$head( $y );
			$y += 20;
		}
		$pdf->text( $cols[0] + 4, $y, wp_date( 'j M Y', strtotime( $ln['date'] ) ), 9 );
		$desc = TP_PDF::wrap( $ln['desc'], $cols[2] - $cols[1] - 8, 9 );
		$pdf->text( $cols[1], $y, $desc[0], 9 );
		$pdf->text( $cols[2], $y, $d['item'] ?: '—', 8 );
		$pdf->text( $cols[3], $y, '1', 9, false, '#16213a', 'right' );
		$pdf->text( $cols[5] - 30, $y, tp_money( $ln['price'] ), 9, false, '#16213a', 'right' );
		$pdf->text( $R - 4, $y, tp_money( $ln['total'] ), 9, false, '#16213a', 'right' );
		$sub = trim( $ln['b']['reference'] . ( isset( $desc[1] ) ? ' · ' . implode( ' ', array_slice( $desc, 1 ) ) : '' ) );
		$pdf->text( $cols[1], $y + 11, TP_PDF::fit( $sub, $cols[2] - $cols[1] - 8, 7.5 ), 7.5, false, '#4a5670' );
		$pdf->line( $L, $y + 17, $R, $y + 17 );
		$y += 28;
	}

	// Totals.
	$y += 4;
	$rows = array( __( 'Total', 'taxi-peninsula' ) => tp_money( $d['t']['total'] ) );
	$rows[ $d['free'] ? __( 'GST (GST-free supports)', 'taxi-peninsula' ) : __( 'Includes GST of', 'taxi-peninsula' ) ] = tp_money( $d['t']['gst'] );
	if ( $d['t']['paid'] > 0 ) {
		$rows[ __( 'Paid', 'taxi-peninsula' ) ] = tp_money( $d['t']['paid'] );
	}
	$rows[ __( 'Amount due', 'taxi-peninsula' ) ] = tp_money( $d['t']['due'] );
	$last = array_key_last( $rows );
	foreach ( $rows as $k => $v ) {
		$big = $k === $last;
		$pdf->text( 400, $y, $k, $big ? 11 : 9.5, $big, $big ? $c['primary'] : '#16213a', 'right' );
		$pdf->text( $R - 4, $y, $v, $big ? 11 : 9.5, $big, $big ? $c['primary'] : '#16213a', 'right' );
		$y += $big ? 18 : 14;
	}

	// How to pay, terms.
	$pay = $d['t']['due'] > 0 && function_exists( 'tp_pay_options_text' ) ? trim( tp_pay_options_text( 'ndis', $d['seq'], $d['no'] ) ) : '';
	$y  += 10;
	if ( $y > TP_PDF::H - 120 ) {
		$pdf->add_page();
		$y = 60;
	}
	foreach ( array_filter( array( $pay, trim( (string) tp_setting( 'payment_terms' ) ) ) ) as $block ) {
		foreach ( TP_PDF::wrap( $block, $R - $L, 9 ) as $i => $line ) {
			$pdf->text( $L, $y, ltrim( $line, '- ' ), 9, 0 === $i && $block === $pay, '#16213a' );
			$y += 12;
		}
		$y += 6;
	}
	/* translators: %s: invoice number */
	$pdf->text( $L, TP_PDF::H - 36, sprintf( __( 'Please quote %s with your payment. Thank you.', 'taxi-peninsula' ), $d['no'] ), 8.5, false, '#4a5670' );
	return $pdf->output();
}

/* -------------------------------------------------------------------------
 * Bulk-claim CSV (NDIA bulk payment request layout).
 * ---------------------------------------------------------------------- */

function tp_ndis_bulk_csv( $month, array $groups ) {
	$fh   = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$abn  = preg_replace( '/\D/', '', (string) tp_setting( 'abn' ) );
	$reg  = preg_replace( '/\D/', '', (string) tp_setting( 'ndis_provider_no' ) );
	$item = tp_ndis_item_code();
	$gst  = 'incl' === tp_setting( 'ndis_gst' ) ? 'P1' : 'P2';
	fputcsv( $fh, array( 'RegistrationNumber', 'NDISNumber', 'SupportsDeliveredFrom', 'SupportsDeliveredTo', 'SupportNumber', 'ClaimReference', 'Quantity', 'Hours', 'UnitPrice', 'GSTCode', 'AuthorisedBy', 'ParticipantApproved', 'InKindFundingProgram', 'ClaimType', 'CancellationReason', 'ABN of Support Provider' ), ',', '"', '' );
	foreach ( $groups as $p ) {
		foreach ( $p['trips'] as $b ) {
			$canc = 'no_show' === $b['status'];
			fputcsv( $fh, array( $reg, preg_replace( '/\D/', '', (string) $p['ndis'] ), $b['date'], $b['date'], $item, substr( $b['reference'], 0, 50 ), 1, '', number_format( (float) $b['fare'], 2, '.', '' ), $gst, '', '', '', $canc ? 'CANC' : '', $canc ? 'NSDO' : '', $abn ), ',', '"', '' );
		}
	}
	rewind( $fh );
	$csv = stream_get_contents( $fh );
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	return $csv;
}

/* -------------------------------------------------------------------------
 * Sending.
 * ---------------------------------------------------------------------- */

function tp_send_ndis_invoice( $month, $key, array $p ) {
	$d    = tp_ndis_invoice_data( $month, $key, $p );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$dir  = trailingslashit( get_temp_dir() ) . 'tp-ndis-' . wp_generate_password( 8, false ) . '/';
	wp_mkdir_p( $dir );
	$slug = sanitize_file_name( $d['no'] . '-' . sanitize_title( $p['name'] ) );
	$ok   = false;
	tp_in_site_lang(
		static function () use ( $month, $key, $p, $d, $site, $dir, $slug, &$ok ) {
			file_put_contents( $dir . $slug . '.pdf', tp_ndis_invoice_pdf( $month, $key, $p ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . $slug . '-bulk-claim.csv', tp_ndis_bulk_csv( $month, array( $key => $p ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			/* translators: %s: plan manager */
			$body  = sprintf( __( 'Hello %s,', 'taxi-peninsula' ), $p['invoice_name'] ?: __( 'there', 'taxi-peninsula' ) ) . "\n\n";
			/* translators: 1: participant 2: month */
			$body .= sprintf( __( 'Please find attached our NDIS invoice for %1$s for %2$s.', 'taxi-peninsula' ), $p['name'], $d['label'] ) . "\n\n";
			$body .= __( 'Invoice number', 'taxi-peninsula' ) . ': ' . $d['no'] . "\n";
			$body .= __( 'Participant', 'taxi-peninsula' ) . ': ' . $p['name'] . "\n";
			$body .= __( 'NDIS number', 'taxi-peninsula' ) . ': ' . $p['ndis'] . "\n";
			if ( $d['item'] ) {
				$body .= __( 'Support item', 'taxi-peninsula' ) . ': ' . $d['item'] . "\n";
			}
			/* translators: %d: trips */
			$body .= sprintf( _n( '%d trip', '%d trips', count( $d['lines'] ), 'taxi-peninsula' ), count( $d['lines'] ) ) . "\n";
			$body .= __( 'Amount due', 'taxi-peninsula' ) . ': ' . tp_money( $d['t']['due'] ) . ( $d['free'] ? ' (' . __( 'GST-free', 'taxi-peninsula' ) . ')' : '' ) . "\n";
			$body .= __( 'Due date', 'taxi-peninsula' ) . ': ' . wp_date( 'j F Y', $d['due_on'] ) . "\n\n";
			$body .= __( 'Attached: the invoice (PDF) and a bulk-claim file (CSV).', 'taxi-peninsula' ) . "\n";
			$body .= __( 'View it online', 'taxi-peninsula' ) . ': ' . tp_ndis_invoice_url( $month, $key ) . "\n\n";
			if ( $d['t']['due'] > 0 && function_exists( 'tp_pay_options_text' ) ) {
				$body .= tp_pay_options_text( 'ndis', $d['seq'], $d['no'] ) . "\n";
			}
			/* translators: %s: phone */
			$body .= sprintf( __( 'Questions? Reply to this email or call %s.', 'taxi-peninsula' ), wp_strip_all_tags( tp_opt( 'phone_display' ) ) ) . "\n\n" . $site . "\n";
			$ok = wp_mail(
				$p['invoice_email'],
				/* translators: 1: number 2: participant 3: month 4: site */
				sprintf( __( 'NDIS invoice %1$s — %2$s — %3$s — %4$s', 'taxi-peninsula' ), $d['no'], $p['name'], $d['label'], $site ),
				$body,
				tp_mail_headers( tp_opt( 'email' ) ),
				array( $dir . $slug . '.pdf', $dir . $slug . '-bulk-claim.csv' )
			);
		}
	);
	foreach ( (array) glob( $dir . '*' ) as $f ) {
		wp_delete_file( $f );
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	if ( $ok ) {
		$all = (array) get_option( 'tp_ndis_invoices', array() );
		$id  = $month . '|' . $key;
		$all[ $id ]['sent'] = $all[ $id ]['sent'] ?: time(); // The invoice date stays the first send.
		$all[ $id ]['to']   = $p['invoice_email'];
		update_option( 'tp_ndis_invoices', $all, false );
		foreach ( $p['trips'] as $b ) {
			tp_add_log( $b['id'], array( 'note' => sprintf( /* translators: 1: invoice 2: email */ __( 'On NDIS invoice %1$s, emailed to %2$s.', 'taxi-peninsula' ), $d['no'], $p['invoice_email'] ) ) );
		}
	}
	return $ok;
}

// The 1st of the month: send last month's NDIS invoices (once each).
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'tp_ndis_invoices_run' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 07:25' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'tp_ndis_invoices_run' );
		}
	}
);
add_action(
	'tp_ndis_invoices_run',
	static function () {
		if ( '01' !== wp_date( 'd' ) || ! tp_ndis_invoices_on() || ! tp_setting( 'ndis_invoice_auto' ) ) {
			return;
		}
		$month = wp_date( 'Y-m', strtotime( 'first day of last month' ) );
		$all   = (array) get_option( 'tp_ndis_invoices', array() );
		foreach ( tp_ndis_invoice_groups( $month ) as $key => $p ) {
			if ( empty( $all[ $month . '|' . $key ]['sent'] ) ) {
				tp_send_ndis_invoice( $month, $key, $p );
			}
		}
	}
);

// NDIS trips are billed on their own invoice, not the monthly statement.
add_filter(
	'tp_statement_include_trip',
	static function ( $include, $id ) {
		return $include && ! ( tp_ndis_invoices_on() && '' !== trim( (string) get_post_meta( $id, '_tp_ndis_number', true ) ) && is_email( get_post_meta( $id, '_tp_invoice_email', true ) ) );
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Online copy (?tp_ndis_invoice=Y-m&p=key&t=token[&pdf=1]).
 * ---------------------------------------------------------------------- */

add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the check.
		if ( empty( $_GET['tp_ndis_invoice'] ) ) {
			return;
		}
		$month = sanitize_text_field( wp_unslash( $_GET['tp_ndis_invoice'] ) );
		$key   = sanitize_key( wp_unslash( $_GET['p'] ?? '' ) );
		$tok   = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );
		$pdf   = ! empty( $_GET['pdf'] );
		// phpcs:enable
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) || ! hash_equals( tp_ndis_invoice_token( $month, $key ), $tok ) ) {
			wp_die( esc_html__( 'This invoice link is not valid. Please contact us for a copy.', 'taxi-peninsula' ), '', array( 'response' => 403 ) );
		}
		$p = tp_ndis_invoice_groups( $month )[ $key ] ?? null;
		if ( ! $p ) {
			wp_die( esc_html__( 'This invoice is no longer available. Please contact us for a copy.', 'taxi-peninsula' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( $pdf ) {
			tp_ndis_invoice_send_pdf( $month, $key, $p );
		}
		tp_render_ndis_invoice( $month, $key, $p );
		exit;
	},
	1
);

function tp_ndis_invoice_send_pdf( $month, $key, array $p ) {
	$d = tp_ndis_invoice_data( $month, $key, $p );
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $d['no'] . '-' . sanitize_title( $p['name'] ) ) . '.pdf"' );
	echo tp_ndis_invoice_pdf( $month, $key, $p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF bytes.
	exit;
}

function tp_render_ndis_invoice( $month, $key, array $p ) {
	$d    = tp_ndis_invoice_data( $month, $key, $p );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$c    = tp_design_colors();
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $d['no'] . ' — ' . $p['name'] . ' — ' . $site ); ?></title>
	<style>
		body { font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #16213a; background: #eef2f7; margin: 0; padding: 24px; }
		.doc { max-width: 960px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 24px rgb(0 0 0 / .08); }
		.head { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; border-bottom: 4px solid <?php echo esc_attr( $c['accent'] ); ?>; padding-bottom: 16px; margin-bottom: 20px; }
		h1 { margin: 0; font-size: 28px; color: <?php echo esc_attr( $c['primary'] ); ?>; }
		.biz { text-align: right; }
		dl { display: grid; grid-template-columns: max-content 1fr max-content 1fr; gap: 4px 16px; margin: 0 0 20px; } dt { color: #4a5670; } dd { margin: 0; font-weight: 700; }
		table { width: 100%; border-collapse: collapse; font-size: 14px; } th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #d5dcea; vertical-align: top; } th { background: #eef2f7; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #4a5670; }
		.n { text-align: right; white-space: nowrap; } tfoot td { font-weight: 700; } tfoot tr:last-child td { font-size: 17px; color: <?php echo esc_attr( $c['primary'] ); ?>; border-top: 2px solid #16213a; }
		small { color: #4a5670; } .note { color: #4a5670; font-size: 13px; }
		.bpay-box { display: inline-block; padding: .75rem 1rem; border: 2px solid #1b3c6e; border-radius: .5rem; color: #1b3c6e; } .bpay-box p { margin: 0; } .bpay-box__brand { font-weight: 800; font-style: italic; }
		.actions { max-width: 960px; margin: 16px auto 0; display: flex; gap: 12px; } .actions a, .actions button { font: inherit; font-weight: 700; padding: 10px 18px; border-radius: 8px; border: 0; background: <?php echo esc_attr( $c['primary'] ); ?>; color: #fff; cursor: pointer; text-decoration: none; }
		@media (max-width: 700px) { body { padding: 8px; } .doc { padding: 20px; } dl { grid-template-columns: max-content 1fr; } .biz { text-align: left; } }
		@media print { body { background: #fff; padding: 0; } .doc { box-shadow: none; padding: 0; } .actions { display: none; } }
	</style>
</head>
<body>
	<main class="doc">
		<header class="head">
			<div>
				<h1><?php esc_html_e( 'Tax invoice', 'taxi-peninsula' ); ?></h1>
				<p><?php echo esc_html( __( 'NDIS supports', 'taxi-peninsula' ) . ' · ' . $d['label'] ); ?></p>
			</div>
			<div class="biz">
				<strong><?php echo esc_html( $site ); ?></strong><br>
				<?php if ( tp_setting( 'abn' ) ) : ?>ABN <?php echo esc_html( tp_setting( 'abn' ) ); ?><br><?php endif; ?>
				<?php if ( tp_setting( 'ndis_provider_no' ) ) : ?><?php esc_html_e( 'NDIS provider', 'taxi-peninsula' ); ?> <?php echo esc_html( tp_setting( 'ndis_provider_no' ) ); ?><br><?php endif; ?>
				<?php echo nl2br( esc_html( (string) tp_setting( 'business_address' ) ) ); ?>
			</div>
		</header>
		<dl>
			<dt><?php esc_html_e( 'Invoice number', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $d['no'] ); ?></dd>
			<dt><?php esc_html_e( 'Participant', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $p['name'] ); ?></dd>
			<dt><?php esc_html_e( 'Invoice date', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( wp_date( 'j M Y', $d['issued'] ) ); ?></dd>
			<dt><?php esc_html_e( 'NDIS number', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $p['ndis'] ); ?></dd>
			<dt><?php esc_html_e( 'Due date', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( wp_date( 'j M Y', $d['due_on'] ) ); ?></dd>
			<dt><?php esc_html_e( 'Plan manager', 'taxi-peninsula' ); ?></dt><dd><?php echo esc_html( $p['invoice_name'] ?: $p['invoice_email'] ); ?></dd>
		</dl>
		<table>
			<thead><tr><th><?php esc_html_e( 'Date', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Description', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Support item', 'taxi-peninsula' ); ?></th><th class="n"><?php esc_html_e( 'Qty', 'taxi-peninsula' ); ?></th><th class="n"><?php esc_html_e( 'Amount', 'taxi-peninsula' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $d['lines'] as $ln ) : ?>
					<tr><td><?php echo esc_html( wp_date( 'j M Y', strtotime( $ln['date'] ) ) ); ?></td><td><?php echo esc_html( $ln['desc'] ); ?><br><small><?php echo esc_html( $ln['b']['reference'] ); ?></small></td><td><?php echo esc_html( $d['item'] ?: '—' ); ?></td><td class="n">1</td><td class="n"><?php echo esc_html( tp_money( $ln['total'] ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr><td colspan="4" class="n"><?php esc_html_e( 'Total', 'taxi-peninsula' ); ?></td><td class="n"><?php echo esc_html( tp_money( $d['t']['total'] ) ); ?></td></tr>
				<tr><td colspan="4" class="n"><?php echo esc_html( $d['free'] ? __( 'GST (GST-free supports)', 'taxi-peninsula' ) : __( 'Includes GST of', 'taxi-peninsula' ) ); ?></td><td class="n"><?php echo esc_html( tp_money( $d['t']['gst'] ) ); ?></td></tr>
				<?php if ( $d['t']['paid'] > 0 ) : ?><tr><td colspan="4" class="n"><?php esc_html_e( 'Paid', 'taxi-peninsula' ); ?></td><td class="n"><?php echo esc_html( tp_money( $d['t']['paid'] ) ); ?></td></tr><?php endif; ?>
				<tr><td colspan="4" class="n"><?php esc_html_e( 'Amount due', 'taxi-peninsula' ); ?></td><td class="n"><?php echo esc_html( tp_money( $d['t']['due'] ) ); ?></td></tr>
			</tfoot>
		</table>
		<?php if ( $d['t']['due'] > 0 && function_exists( 'tp_pay_options_html' ) && tp_pay_options_available() ) : ?>
			<h2><?php esc_html_e( 'How to pay', 'taxi-peninsula' ); ?></h2>
			<?php echo tp_pay_options_html( 'ndis', $d['seq'], $d['no'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php endif; ?>
		<?php if ( tp_setting( 'payment_terms' ) ) : ?><p class="note"><?php echo nl2br( esc_html( tp_setting( 'payment_terms' ) ) ); ?></p><?php endif; ?>
	</main>
	<p class="actions"><a href="<?php echo esc_url( tp_ndis_invoice_url( $month, $key, true ) ); ?>"><?php esc_html_e( 'Download PDF', 'taxi-peninsula' ); ?></a><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'taxi-peninsula' ); ?></button></p>
</body>
</html>
	<?php
}

/* -------------------------------------------------------------------------
 * Office: buttons on Invoices → NDIS trip log.
 * ---------------------------------------------------------------------- */

function tp_ndis_invoice_admin_url( $month, $key, $do ) {
	return wp_nonce_url( add_query_arg( array( 'action' => 'tp_ndis_invoice', 'month' => $month, 'p' => $key, 'do' => $do ), admin_url( 'admin-post.php' ) ), 'tp_ndis_invoice' );
}

add_action(
	'tp_ndis_log_top',
	static function ( $month ) {
		if ( ! tp_ndis_invoices_on() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Want to send each plan manager a proper NDIS tax invoice (PDF plus bulk-claim file) instead of listing these trips on the monthly statement?', 'taxi-peninsula' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=tp-settings&tab=payments' ) ) . '">' . esc_html__( 'Turn on NDIS invoices in Settings → Payments & invoices', 'taxi-peninsula' ) . '</a></p></div>';
			return;
		}
		$groups = tp_ndis_invoice_groups( $month );
		$all    = (array) get_option( 'tp_ndis_invoices', array() );
		$unsent = count( array_filter( array_keys( $groups ), static fn( $k ) => empty( $all[ $month . '|' . $k ]['sent'] ) ) );
		echo '<p>';
		/* translators: 1: invoices 2: not sent yet */
		echo esc_html( sprintf( __( 'NDIS invoices for this month: %1$d (%2$d not emailed yet).', 'taxi-peninsula' ), count( $groups ), $unsent ) ) . ' ';
		if ( $unsent ) {
			echo '<a class="button button-primary" href="' . esc_url( tp_ndis_invoice_admin_url( $month, 'all', 'email' ) ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Email every NDIS invoice not sent yet to its plan manager?', 'taxi-peninsula' ) ) ) . ')">' . esc_html__( 'Email all unsent NDIS invoices', 'taxi-peninsula' ) . '</a> ';
		}
		if ( $groups ) {
			echo '<a class="button" href="' . esc_url( tp_ndis_invoice_admin_url( $month, 'all', 'csv' ) ) . '">' . esc_html__( 'Bulk-claim CSV (all participants)', 'taxi-peninsula' ) . '</a>';
		}
		echo '</p>';
		if ( ! tp_ndis_item_code() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Add the NDIS support item number (e.g. 04_590_0125_6_1) in Settings → Payments & invoices. Plan managers need it on every line.', 'taxi-peninsula' ) . '</p></div>';
		}
	}
);

add_action(
	'tp_ndis_log_row_actions',
	static function ( $month, $key ) {
		if ( ! tp_ndis_invoices_on() || ! isset( tp_ndis_invoice_groups( $month )[ $key ] ) ) {
			return;
		}
		$rec = ( (array) get_option( 'tp_ndis_invoices', array() ) )[ $month . '|' . $key ] ?? array();
		echo '<br><strong>' . esc_html__( 'NDIS invoice', 'taxi-peninsula' ) . ':</strong> ';
		echo '<a href="' . esc_url( tp_ndis_invoice_admin_url( $month, $key, 'pdf' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'PDF', 'taxi-peninsula' ) . '</a> · ';
		if ( ! empty( $rec['sent'] ) ) {
			/* translators: 1: number 2: date */
			echo esc_html( sprintf( __( '%1$s sent %2$s', 'taxi-peninsula' ), tp_ndis_invoice_number( $rec['no'] ), wp_date( 'j M', $rec['sent'] ) ) ) . ' · <a href="' . esc_url( tp_ndis_invoice_admin_url( $month, $key, 'email' ) ) . '">' . esc_html__( 'Resend', 'taxi-peninsula' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( tp_ndis_invoice_admin_url( $month, $key, 'email' ) ) . '">' . esc_html__( 'Email to plan manager', 'taxi-peninsula' ) . '</a>';
		}
	},
	10,
	2
);

add_action(
	'admin_post_tp_ndis_invoice',
	static function () {
		check_admin_referer( 'tp_ndis_invoice' );
		if ( ! current_user_can( 'tp_view_finance' ) ) {
			wp_die( '', 403 );
		}
		$month  = tp_ndis_log_month( wp_unslash( $_GET['month'] ?? '' ) );
		$key    = sanitize_key( wp_unslash( $_GET['p'] ?? '' ) );
		$do     = sanitize_key( wp_unslash( $_GET['do'] ?? '' ) );
		$groups = tp_ndis_invoice_groups( $month );
		$back   = admin_url( 'admin.php?page=tp-ndis-log&month=' . $month );
		if ( 'csv' === $do ) {
			$pick = 'all' === $key ? $groups : array_intersect_key( $groups, array( $key => 1 ) );
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="ndis-bulk-claim-' . $month . '.csv"' );
			echo tp_ndis_bulk_csv( $month, $pick ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
			exit;
		}
		if ( ! tp_ndis_invoices_on() ) {
			wp_safe_redirect( $back );
			exit;
		}
		if ( 'pdf' === $do && isset( $groups[ $key ] ) ) {
			tp_ndis_invoice_send_pdf( $month, $key, $groups[ $key ] );
		}
		if ( 'email' === $do ) {
			$all  = (array) get_option( 'tp_ndis_invoices', array() );
			$sent = 0;
			$fail = 0;
			foreach ( $groups as $k => $p ) {
				if ( ( 'all' === $key && empty( $all[ $month . '|' . $k ]['sent'] ) ) || $k === $key ) {
					tp_send_ndis_invoice( $month, $k, $p ) ? $sent++ : $fail++;
				}
			}
			/* translators: 1: sent 2: failed */
			set_transient( 'tp_admin_notice_' . get_current_user_id(), array( $fail ? 'warning' : 'success', sprintf( __( 'NDIS invoices emailed: %1$d. Failed: %2$d.', 'taxi-peninsula' ), $sent, $fail ) ), 60 );
		}
		wp_safe_redirect( $back );
		exit;
	}
);
