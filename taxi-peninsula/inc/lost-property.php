<?php
/**
 * Lost property: a form for passengers ([tp_lost_property], starter page
 * "Lost Property") and a matching list for the office (Bookings → Lost
 * property).
 *
 * The office (or a driver, through the office) logs items found in
 * vehicles. Each report from a passenger shows likely matches — same trip,
 * same driver or vehicle, close dates, similar words — and one click marks
 * the match and emails the passenger.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

function tp_lost_statuses() {
	return array(
		'open'     => __( 'Open', 'taxi-peninsula' ),
		'matched'  => __( 'Matched — waiting for pick-up', 'taxi-peninsula' ),
		'returned' => __( 'Returned', 'taxi-peninsula' ),
		'closed'   => __( 'Closed (not found / disposed)', 'taxi-peninsula' ),
	);
}

add_action(
	'init',
	static function () {
		register_post_type(
			'tp_lost',
			array(
				'labels'          => array(
					'name'          => __( 'Lost property', 'taxi-peninsula' ),
					'singular_name' => __( 'Lost property item', 'taxi-peninsula' ),
					'add_new'       => __( 'Log a found item', 'taxi-peninsula' ),
					'add_new_item'  => __( 'Log a found item', 'taxi-peninsula' ),
					'edit_item'     => __( 'Lost property', 'taxi-peninsula' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=tp_booking',
				'supports'        => array( 'title', 'thumbnail' ),
				'capability_type' => 'tp_booking',
				'map_meta_cap'    => true,
			)
		);
	}
);

add_filter(
	'tp_feature_shortcodes',
	static function ( $c ) {
		$c['lost'] = 'tp_lost_property';
		return $c;
	}
);

add_filter(
	'tp_starter_pages',
	static function ( $p ) {
		$p['lost'] = array(
			'title'   => __( 'Lost Property', 'taxi-peninsula' ),
			'content' => "<!-- wp:paragraph -->\n<p>" . __( 'Left something in one of our taxis? Tell us about it below. Drivers hand in everything they find, and we will contact you as soon as we have a match.', 'taxi-peninsula' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tp_lost_property]\n<!-- /wp:shortcode -->",
		);
		return $p;
	}
);

// The contact chooser's "Lost property" tile goes to the form.
add_filter(
	'tp_lost_property_url',
	static function ( $url ) {
		return tp_page_url_by_template( 'lost', $url );
	}
);

function tp_lost_meta( $id ) {
	$m = array( 'id' => (int) $id, 'title' => get_the_title( $id ) );
	foreach ( array( 'kind', 'status', 'name', 'email', 'phone', 'date', 'reference', 'route', 'description', 'colour', 'where', 'booking_id', 'match', 'driver_id', 'fleet_id' ) as $k ) {
		$m[ $k ] = (string) get_post_meta( $id, '_tp_l_' . $k, true );
	}
	$m['kind']   = $m['kind'] ?: 'found';
	$m['status'] = $m['status'] ?: 'open';
	return $m;
}

/* -------------------------------------------------------------------------
 * Passenger form.
 * ---------------------------------------------------------------------- */

add_shortcode( 'tp_lost_property', 'tp_lost_shortcode' );
function tp_lost_shortcode() {
	list( $errors, $old ) = tp_form_state();
	$sent = ! empty( $_GET['lost_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$f    = static function ( $name, $label, $o = array() ) use ( $errors, $old ) {
		tp_form_field( 'lp', $name, $label, array_merge( array( 'errors' => $errors, 'value' => $old[ $name ] ?? ( $o['default'] ?? '' ) ), $o ) );
	};
	ob_start();
	?>
	<div class="tp-component card lost-form" id="lost">
		<?php if ( $sent ) : ?>
			<?php tp_form_success( __( 'Thanks — we are looking', 'taxi-peninsula' ), __( 'We have your report and will check with the driver and our found items. We will contact you as soon as we know more.', 'taxi-peninsula' ) ); ?>
		<?php else : ?>
			<?php if ( $errors ) : ?>
				<div class="notice notice--error" role="alert" tabindex="-1" data-focus><p><?php echo esc_html( $errors['form'] ?? __( 'Please check the highlighted fields.', 'taxi-peninsula' ) ); ?></p></div>
			<?php endif; ?>
			<form class="booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="tp_lost">
				<input type="hidden" name="_tp_return" value="<?php echo esc_url( tp_form_return_url() ); ?>">
				<?php wp_nonce_field( 'tp_lost', '_tp_nonce', false ); ?>
				<fieldset class="booking-form__group">
					<legend><?php esc_html_e( 'What did you lose?', 'taxi-peninsula' ); ?></legend>
					<?php
					$f( 'item', __( 'Item', 'taxi-peninsula' ), array( 'required' => true, 'attrs' => 'placeholder="' . esc_attr__( 'e.g. black handbag, hearing aid, phone', 'taxi-peninsula' ) . '"' ) );
					$f( 'colour', __( 'Colour and brand (if known)', 'taxi-peninsula' ) );
					$f( 'description', __( 'Anything that helps us recognise it', 'taxi-peninsula' ), array( 'type' => 'textarea' ) );
					?>
				</fieldset>
				<fieldset class="booking-form__group">
					<legend><?php esc_html_e( 'Your trip', 'taxi-peninsula' ); ?></legend>
					<?php
					$f( 'date', __( 'Date of the trip', 'taxi-peninsula' ), array( 'type' => 'date', 'required' => true, 'default' => wp_date( 'Y-m-d' ), 'attrs' => 'max="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"' ) );
					$f( 'reference', __( 'Booking reference (if you have it)', 'taxi-peninsula' ), array( 'attrs' => 'placeholder="TP-XXXXXX" autocomplete="off"' ) );
					$f( 'route', __( 'From and to', 'taxi-peninsula' ), array( 'attrs' => 'placeholder="' . esc_attr__( 'e.g. Frankston Hospital to Mornington', 'taxi-peninsula' ) . '"' ) );
					?>
				</fieldset>
				<fieldset class="booking-form__group">
					<legend><?php esc_html_e( 'How can we reach you?', 'taxi-peninsula' ); ?></legend>
					<?php
					$f( 'name', __( 'Your name', 'taxi-peninsula' ), array( 'required' => true, 'attrs' => 'autocomplete="name"' ) );
					$f( 'phone', __( 'Mobile', 'taxi-peninsula' ), array( 'type' => 'tel', 'required' => true, 'attrs' => 'autocomplete="tel"' ) );
					$f( 'email', __( 'Email (optional)', 'taxi-peninsula' ), array( 'type' => 'email', 'attrs' => 'autocomplete="email"' ) );
					?>
				</fieldset>
				<?php tp_spam_fields(); ?>
				<div class="booking-form__submit">
					<button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Report lost item', 'taxi-peninsula' ); ?></button>
					<p class="field__hint"><?php printf( /* translators: %s: phone */ esc_html__( 'Urgent — medication, keys, a mobility aid? Call %s.', 'taxi-peninsula' ), '<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>' ); ?></p>
				</div>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_nopriv_tp_lost', 'tp_handle_lost' );
add_action( 'admin_post_tp_lost', 'tp_handle_lost' );
function tp_handle_lost() {
	$return = isset( $_POST['_tp_return'] ) ? esc_url_raw( wp_unslash( $_POST['_tp_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in tp_form_guard().
	$return = remove_query_arg( array( 'tp_form', 'lost_sent' ), wp_validate_redirect( $return, home_url( '/' ) ) );
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- sanitized per field.
	tp_form_guard( 'tp_lost', $return, $raw, '#lost', 'lost_sent' );

	$d = array(
		'item'        => sanitize_text_field( $raw['item'] ?? '' ),
		'colour'      => sanitize_text_field( $raw['colour'] ?? '' ),
		'description' => sanitize_textarea_field( $raw['description'] ?? '' ),
		'date'        => sanitize_text_field( $raw['date'] ?? '' ),
		'reference'   => strtoupper( sanitize_text_field( $raw['reference'] ?? '' ) ),
		'route'       => sanitize_text_field( $raw['route'] ?? '' ),
		'name'        => sanitize_text_field( $raw['name'] ?? '' ),
		'phone'       => sanitize_text_field( $raw['phone'] ?? '' ),
		'email'       => sanitize_email( $raw['email'] ?? '' ),
	);
	$errors = array();
	if ( '' === $d['item'] ) {
		$errors['item'] = __( 'Please tell us what you lost.', 'taxi-peninsula' );
	}
	if ( '' === $d['name'] ) {
		$errors['name'] = __( 'Please enter your name.', 'taxi-peninsula' );
	}
	if ( strlen( preg_replace( '/\D/', '', $d['phone'] ) ) < 8 ) {
		$errors['phone'] = __( 'Please enter a phone number so we can call you.', 'taxi-peninsula' );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['date'] ) || $d['date'] > wp_date( 'Y-m-d' ) ) {
		$errors['date'] = __( 'Please enter the date of the trip.', 'taxi-peninsula' );
	}
	if ( $errors ) {
		$errors['form'] = __( 'Please check the highlighted fields.', 'taxi-peninsula' );
		tp_booking_fail( $return, $errors, $raw, '#lost' );
	}

	$id = wp_insert_post( array( 'post_type' => 'tp_lost', 'post_status' => 'publish', 'post_title' => $d['item'] ) );
	if ( ! $id || is_wp_error( $id ) ) {
		/* translators: %s: phone */
		tp_booking_fail( $return, array( 'form' => sprintf( __( 'Sorry, we could not save your report. Please call %s.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) ), $raw, '#lost' );
	}
	unset( $d['item'] );
	foreach ( $d as $k => $v ) {
		update_post_meta( $id, '_tp_l_' . $k, $v );
	}
	update_post_meta( $id, '_tp_l_kind', 'report' );
	update_post_meta( $id, '_tp_l_status', 'open' );
	$bid = tp_find_booking( $d['reference'] );
	if ( $bid ) {
		update_post_meta( $id, '_tp_l_booking_id', $bid );
		update_post_meta( $id, '_tp_l_driver_id', (int) get_post_meta( $bid, '_tp_driver_id', true ) );
		update_post_meta( $id, '_tp_l_fleet_id', (int) get_post_meta( $bid, '_tp_fleet_id', true ) );
		/* translators: %s: item */
		tp_add_log( $bid, array( 'note' => sprintf( __( 'Passenger reported lost property: %s', 'taxi-peninsula' ), get_the_title( $id ) ) ) );
	}
	tp_rate_limit( 'tp_lost', 5, HOUR_IN_SECONDS, true );
	if ( function_exists( 'tp_activity' ) ) {
		/* translators: %s: item */
		tp_activity( 'enquiry', sprintf( __( 'Lost property reported: %s', 'taxi-peninsula' ), get_the_title( $id ) ), $bid );
	}
	$matches = tp_lost_matches( $id );
	tp_in_site_lang(
		static function () use ( $id, $d, $matches ) {
			$body = sprintf( "%s\n%s %s\n%s: %s\n%s: %s\n%s: %s\n\n%s\n", get_the_title( $id ), $d['colour'], $d['description'], __( 'Trip date', 'taxi-peninsula' ), $d['date'], __( 'Reference', 'taxi-peninsula' ), $d['reference'] ?: '—', __( 'Route', 'taxi-peninsula' ), $d['route'] ?: '—', $d['name'] . ' ' . $d['phone'] . ' ' . $d['email'] );
			if ( $matches ) {
				/* translators: %d: count */
				$body .= sprintf( _n( '%d possible match among found items.', '%d possible matches among found items.', count( $matches ), 'taxi-peninsula' ), count( $matches ) ) . "\n";
			}
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				/* translators: %s: item */
				sprintf( __( 'Lost property report: %s', 'taxi-peninsula' ), get_the_title( $id ) ),
				$body . "\n" . __( 'Open lost property', 'taxi-peninsula' ) . ': ' . admin_url( 'post.php?action=edit&post=' . $id ),
				tp_mail_headers( $d['email'] )
			);
		}
	);
	wp_safe_redirect( add_query_arg( 'lost_sent', 1, $return ) . '#lost' );
	exit;
}

/* -------------------------------------------------------------------------
 * Matching.
 * ---------------------------------------------------------------------- */

/**
 * Likely matches for an item, best first: [ id => score ].
 */
function tp_lost_matches( $id ) {
	$a    = tp_lost_meta( $id );
	$want = 'report' === $a['kind'] ? 'found' : 'report';
	$ids  = get_posts(
		array(
			'post_type'   => 'tp_lost',
			'numberposts' => 200,
			'fields'      => 'ids',
			'exclude'     => array( $id ),
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => '_tp_l_kind', 'value' => $want ),
				array( 'key' => '_tp_l_status', 'value' => 'open' ),
			),
		)
	);
	$words = static function ( $m ) {
		$text = strtolower( $m['title'] . ' ' . $m['colour'] . ' ' . $m['description'] );
		$stop = array( 'the', 'and', 'with', 'a', 'an', 'of', 'in', 'my', 'it', 'is', 'on', 'for' );
		return array_diff( array_unique( preg_split( '/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY ) ), $stop );
	};
	$aw  = $words( $a );
	$out = array();
	foreach ( $ids as $other ) {
		$b     = tp_lost_meta( $other );
		$score = 0;
		if ( $a['booking_id'] && $a['booking_id'] === $b['booking_id'] ) {
			$score += 50;
		}
		if ( $a['driver_id'] && $a['driver_id'] === $b['driver_id'] ) {
			$score += 15;
		}
		if ( $a['fleet_id'] && $a['fleet_id'] === $b['fleet_id'] ) {
			$score += 15;
		}
		if ( $a['date'] && $b['date'] ) {
			$days = abs( strtotime( $a['date'] ) - strtotime( $b['date'] ) ) / DAY_IN_SECONDS;
			$score += $days < 1 ? 20 : ( $days <= 3 ? 10 : ( $days > 14 ? -20 : 0 ) );
		}
		$score += 10 * count( array_intersect( $aw, $words( $b ) ) );
		if ( $score >= 20 ) {
			$out[ $other ] = $score;
		}
	}
	arsort( $out );
	return array_slice( $out, 0, 5, true );
}

/* -------------------------------------------------------------------------
 * Admin.
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tp_lost',
	static function () {
		add_meta_box( 'tp_lost_box', __( 'Details', 'taxi-peninsula' ), 'tp_render_lost_box', 'tp_lost', 'normal', 'high' );
		add_meta_box( 'tp_lost_match', __( 'Possible matches', 'taxi-peninsula' ), 'tp_render_lost_match_box', 'tp_lost', 'normal', 'default' );
	}
);

function tp_render_lost_box( $post ) {
	$m = tp_lost_meta( $post->ID );
	wp_nonce_field( 'tp_lost_save', 'tp_lost_nonce' );
	$is_new = 'auto-draft' === $post->post_status;
	?>
	<p class="description">
		<?php
		echo esc_html(
			'report' === $m['kind'] && ! $is_new
				? __( 'Reported by a passenger. The title is the item.', 'taxi-peninsula' )
				: __( 'An item found in a vehicle. Put the item in the title, e.g. "Black umbrella".', 'taxi-peninsula' )
		);
		?>
	</p>
	<input type="hidden" name="tp_l[kind]" value="<?php echo esc_attr( $is_new ? 'found' : $m['kind'] ); ?>">
	<table class="form-table" role="presentation">
		<tr><th><label for="tp_l_status"><?php esc_html_e( 'Status', 'taxi-peninsula' ); ?></label></th><td>
			<select id="tp_l_status" name="tp_l[status]">
				<?php foreach ( tp_lost_statuses() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m['status'], $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
		<?php
		$rows = array(
			'date'        => array( 'report' === $m['kind'] ? __( 'Trip date', 'taxi-peninsula' ) : __( 'Date found', 'taxi-peninsula' ), 'date' ),
			'reference'   => array( __( 'Booking reference', 'taxi-peninsula' ), 'text' ),
			'colour'      => array( __( 'Colour / brand', 'taxi-peninsula' ), 'text' ),
			'description' => array( __( 'Description', 'taxi-peninsula' ), 'textarea' ),
			'route'       => array( __( 'Route', 'taxi-peninsula' ), 'text' ),
			'where'       => array( __( 'Where it is kept now', 'taxi-peninsula' ), 'text' ),
			'name'        => array( __( 'Passenger name', 'taxi-peninsula' ), 'text' ),
			'phone'       => array( __( 'Passenger phone', 'taxi-peninsula' ), 'tel' ),
			'email'       => array( __( 'Passenger email', 'taxi-peninsula' ), 'email' ),
		);
		if ( 'found' === $m['kind'] || $is_new ) {
			unset( $rows['route'], $rows['name'], $rows['phone'], $rows['email'] );
		}
		foreach ( $rows as $k => $r ) {
			printf( '<tr><th><label for="tp_l_%1$s">%2$s</label></th><td>', esc_attr( $k ), esc_html( $r[0] ) );
			if ( 'textarea' === $r[1] ) {
				printf( '<textarea class="large-text" rows="2" id="tp_l_%1$s" name="tp_l[%1$s]">%2$s</textarea>', esc_attr( $k ), esc_textarea( $m[ $k ] ) );
			} else {
				printf( '<input type="%3$s" class="regular-text" id="tp_l_%1$s" name="tp_l[%1$s]" value="%2$s">', esc_attr( $k ), esc_attr( $m[ $k ] ), esc_attr( $r[1] ) );
			}
			echo '</td></tr>';
		}
		?>
	</table>
	<?php
	if ( $m['booking_id'] && get_post( $m['booking_id'] ) ) {
		$b = tp_get_booking( $m['booking_id'] );
		printf( '<p>%1$s <a href="%2$s">%3$s</a> · %4$s</p>', esc_html__( 'Trip:', 'taxi-peninsula' ), esc_url( get_edit_post_link( $m['booking_id'] ) ), esc_html( $b['reference'] ), esc_html( tp_format_pickup( $b ) . ( $b['driver_id'] ? ' · ' . get_the_author_meta( 'display_name', $b['driver_id'] ) : '' ) ) );
	}
}

function tp_render_lost_match_box( $post ) {
	$m = tp_lost_meta( $post->ID );
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p>' . esc_html__( 'Save the item to see possible matches.', 'taxi-peninsula' ) . '</p>';
		return;
	}
	if ( $m['match'] && get_post( $m['match'] ) ) {
		printf( '<p><strong>%1$s</strong> <a href="%2$s">%3$s</a></p>', esc_html__( 'Matched with:', 'taxi-peninsula' ), esc_url( get_edit_post_link( (int) $m['match'] ) ), esc_html( get_the_title( (int) $m['match'] ) ) );
		return;
	}
	$matches = tp_lost_matches( $post->ID );
	if ( ! $matches ) {
		echo '<p>' . esc_html( 'report' === $m['kind'] ? __( 'No found items look like this yet. Log found items with "Log a found item".', 'taxi-peninsula' ) : __( 'No passenger reports look like this yet.', 'taxi-peninsula' ) ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><tbody>';
	foreach ( $matches as $other => $score ) {
		$o      = tp_lost_meta( $other );
		$report = 'report' === $m['kind'] ? $post->ID : $other;
		$found  = 'report' === $m['kind'] ? $other : $post->ID;
		$url    = wp_nonce_url( admin_url( 'admin-post.php?action=tp_lost_match&report=' . $report . '&found=' . $found ), 'tp_lost_match_' . $report . '_' . $found );
		printf(
			'<tr><td><a href="%1$s"><strong>%2$s</strong></a><br><span class="description">%3$s</span></td><td>%4$s</td><td><a class="button" href="%5$s">%6$s</a></td></tr>',
			esc_url( get_edit_post_link( $other ) ),
			esc_html( $o['title'] ),
			esc_html( trim( $o['colour'] . ' ' . $o['description'] ) ),
			esc_html( $o['date'] ),
			esc_url( $url ),
			esc_html( 'report' === $o['kind'] || is_email( $m['email'] ) || $m['phone'] ? __( 'Match & tell passenger', 'taxi-peninsula' ) : __( 'Match', 'taxi-peninsula' ) )
		);
	}
	echo '</tbody></table>';
}

add_action(
	'save_post_tp_lost',
	static function ( $post_id ) {
		if ( ! isset( $_POST['tp_lost_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tp_lost_nonce'] ), 'tp_lost_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['tp_l'] ) && is_array( $_POST['tp_l'] ) ? wp_unslash( $_POST['tp_l'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		update_post_meta( $post_id, '_tp_l_kind', 'report' === ( $in['kind'] ?? '' ) ? 'report' : 'found' );
		update_post_meta( $post_id, '_tp_l_status', isset( tp_lost_statuses()[ $in['status'] ?? '' ] ) ? $in['status'] : 'open' );
		foreach ( array( 'date', 'colour', 'route', 'where', 'name', 'phone' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				update_post_meta( $post_id, '_tp_l_' . $k, sanitize_text_field( $in[ $k ] ) );
			}
		}
		if ( isset( $in['email'] ) ) {
			update_post_meta( $post_id, '_tp_l_email', sanitize_email( $in['email'] ) );
		}
		if ( isset( $in['description'] ) ) {
			update_post_meta( $post_id, '_tp_l_description', sanitize_textarea_field( $in['description'] ) );
		}
		if ( isset( $in['reference'] ) ) {
			$ref = strtoupper( sanitize_text_field( $in['reference'] ) );
			update_post_meta( $post_id, '_tp_l_reference', $ref );
			$bid = tp_find_booking( $ref );
			update_post_meta( $post_id, '_tp_l_booking_id', $bid ?: '' );
			update_post_meta( $post_id, '_tp_l_driver_id', $bid ? (int) get_post_meta( $bid, '_tp_driver_id', true ) : '' );
			update_post_meta( $post_id, '_tp_l_fleet_id', $bid ? (int) get_post_meta( $bid, '_tp_fleet_id', true ) : '' );
		}
	}
);

add_action( 'admin_post_tp_lost_match', 'tp_handle_lost_match' );
function tp_handle_lost_match() {
	$report = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;
	$found  = isset( $_GET['found'] ) ? absint( $_GET['found'] ) : 0;
	check_admin_referer( 'tp_lost_match_' . $report . '_' . $found );
	if ( ! current_user_can( 'edit_post', $report ) || 'tp_lost' !== get_post_type( $report ) || 'tp_lost' !== get_post_type( $found ) ) {
		wp_die( '', 403 );
	}
	foreach ( array( array( $report, $found ), array( $found, $report ) ) as $pair ) {
		update_post_meta( $pair[0], '_tp_l_match', $pair[1] );
		update_post_meta( $pair[0], '_tp_l_status', 'matched' );
	}
	$r     = tp_lost_meta( $report );
	$f     = tp_lost_meta( $found );
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$where = $f['where'] ?: (string) tp_opt( 'location' );
	if ( is_email( $r['email'] ) ) {
		/* translators: %s: name */
		$body  = sprintf( __( 'Hi %s,', 'taxi-peninsula' ), $r['name'] ) . "\n\n";
		/* translators: %s: item */
		$body .= sprintf( __( 'Good news — we think we have found your %s.', 'taxi-peninsula' ), $r['title'] ) . "\n\n";
		/* translators: 1: phone */
		$body .= sprintf( __( 'Please call us on %1$s to confirm it is yours and to arrange collection, or ask us to bring it on your next trip.', 'taxi-peninsula' ), tp_opt( 'phone_display' ) ) . "\n";
		if ( $where ) {
			/* translators: %s: place */
			$body .= sprintf( __( 'It is kept at: %s', 'taxi-peninsula' ), $where ) . "\n";
		}
		wp_mail(
			$r['email'],
			/* translators: %s: site */
			sprintf( __( 'We may have found your lost item — %s', 'taxi-peninsula' ), $site ),
			$body . "\n" . $site,
			tp_mail_headers( tp_opt( 'email' ) )
		);
	}
	if ( $r['phone'] && function_exists( 'tp_sms_enabled' ) && tp_sms_enabled() ) {
		/* translators: 1: item 2: phone 3: site */
		tp_send_sms( $r['phone'], sprintf( __( 'Good news: we may have found your %1$s. Please call %2$s to arrange collection. %3$s', 'taxi-peninsula' ), $r['title'], wp_strip_all_tags( tp_opt( 'phone_display' ) ), function_exists( 'tp_sms_site_name' ) ? tp_sms_site_name() : $site ) );
	}
	if ( $r['booking_id'] ) {
		/* translators: %s: item */
		tp_add_log( (int) $r['booking_id'], array( 'note' => sprintf( __( 'Lost property matched: %s', 'taxi-peninsula' ), $r['title'] ) ) );
	}
	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $report . '&message=1' ) );
	exit;
}

add_filter(
	'manage_tp_lost_posts_columns',
	static function () {
		return array(
			'cb'         => '<input type="checkbox">',
			'title'      => __( 'Item', 'taxi-peninsula' ),
			'tp_photo'   => __( 'Photo', 'taxi-peninsula' ),
			'tp_kind'    => __( 'Type', 'taxi-peninsula' ),
			'tp_date'    => __( 'Date', 'taxi-peninsula' ),
			'tp_who'     => __( 'Passenger / trip', 'taxi-peninsula' ),
			'tp_matches' => __( 'Possible matches', 'taxi-peninsula' ),
			'tp_status'  => __( 'Status', 'taxi-peninsula' ),
		);
	}
);
add_action(
	'manage_tp_lost_posts_custom_column',
	static function ( $col, $id ) {
		$m = tp_lost_meta( $id );
		switch ( $col ) {
			case 'tp_photo':
				echo has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, array( 60, 60 ) ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'tp_kind':
				echo esc_html( 'report' === $m['kind'] ? __( 'Reported lost', 'taxi-peninsula' ) : __( 'Found in vehicle', 'taxi-peninsula' ) );
				break;
			case 'tp_date':
				echo esc_html( $m['date'] );
				break;
			case 'tp_who':
				echo esc_html( trim( $m['name'] . ' ' . $m['reference'] ) ?: '—' );
				break;
			case 'tp_matches':
				$n = 'open' === $m['status'] ? count( tp_lost_matches( $id ) ) : 0;
				echo $n ? '<strong>' . esc_html( $n ) . '</strong>' : '—';
				break;
			case 'tp_status':
				echo esc_html( tp_lost_statuses()[ $m['status'] ] ?? $m['status'] );
				break;
		}
	},
	10,
	2
);

// Privacy: lost-property reports are removed with old messages.
add_action(
	'tp_privacy_cleanup',
	static function () {
		$months = (int) tp_setting( 'retention_messages_months' );
		if ( $months <= 0 ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'tp_lost', 'numberposts' => 200, 'fields' => 'ids', 'date_query' => array( array( 'before' => $months . ' months ago' ) ) ) ) as $id ) {
			wp_delete_post( $id, true );
		}
	}
);

/* -------------------------------------------------------------------------
 * Drivers: "Found an item" on the driver screen, with a photo.
 * ---------------------------------------------------------------------- */

/**
 * The "Found an item" form for a job (or with no job when $b is empty).
 *
 * @param array  $b   Booking, or array() for an item not linked to a trip.
 * @param string $day Day being viewed (to come back to).
 */
function tp_driver_found_form( array $b = array(), $day = '' ) {
	$bid = (int) ( $b['id'] ?? 0 );
	$uid = 'found-' . ( $bid ?: 'any' );
	?>
	<details class="job__found<?php echo $bid ? '' : ' card driver-found'; ?>">
		<summary><?php tp_the_icon( 'search' ); ?> <?php echo esc_html( $bid ? __( 'Found an item after this trip?', 'taxi-peninsula' ) : __( 'Found an item in the vehicle?', 'taxi-peninsula' ) ); ?></summary>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="job__found-form">
			<input type="hidden" name="action" value="tp_driver_found">
			<input type="hidden" name="booking" value="<?php echo esc_attr( $bid ); ?>">
			<input type="hidden" name="day" value="<?php echo esc_attr( $day ); ?>">
			<?php wp_nonce_field( 'tp_found_' . $bid ); ?>
			<div class="field">
				<label for="<?php echo esc_attr( $uid ); ?>-photo"><?php esc_html_e( 'Photo', 'taxi-peninsula' ); ?></label>
				<input type="file" id="<?php echo esc_attr( $uid ); ?>-photo" name="photo" accept="image/*">
				<p class="field__hint"><?php esc_html_e( 'Take a photo or choose one. It helps the passenger recognise it.', 'taxi-peninsula' ); ?></p>
			</div>
			<div class="field">
				<label for="<?php echo esc_attr( $uid ); ?>-item"><?php esc_html_e( 'What is it?', 'taxi-peninsula' ); ?> <span class="req" aria-hidden="true">*</span></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-item" name="item" required maxlength="100" placeholder="<?php esc_attr_e( 'e.g. black umbrella', 'taxi-peninsula' ); ?>">
			</div>
			<div class="field">
				<label for="<?php echo esc_attr( $uid ); ?>-where"><?php esc_html_e( 'Where is it now?', 'taxi-peninsula' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-where" name="where" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. in the glovebox, handed in at the depot', 'taxi-peninsula' ); ?>">
			</div>
			<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Send to the office', 'taxi-peninsula' ); ?></button>
		</form>
	</details>
	<?php
}

add_action( 'admin_post_tp_driver_found', 'tp_handle_driver_found' );
function tp_handle_driver_found() {
	$bid = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
	check_admin_referer( 'tp_found_' . $bid );
	$manager = current_user_can( 'edit_tp_bookings' );
	if ( ! $manager && ! current_user_can( 'tp_driver_jobs' ) ) {
		wp_die( '', 403 );
	}
	if ( $bid && ( 'tp_booking' !== get_post_type( $bid ) || ( ! $manager && (int) get_post_meta( $bid, '_tp_driver_id', true ) !== get_current_user_id() ) ) ) {
		wp_die( esc_html__( 'This trip is not assigned to you.', 'taxi-peninsula' ), 403 );
	}
	$day  = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '';
	$back = add_query_arg( array_filter( array( 'jobs_day' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : '' ) ), tp_page_url_by_template( 'driver', home_url( '/' ) ) );
	$item = isset( $_POST['item'] ) ? sanitize_text_field( wp_unslash( $_POST['item'] ) ) : '';
	if ( '' === $item ) {
		wp_safe_redirect( add_query_arg( 'found', 'missing', $back ) . ( $bid ? '#job-' . $bid : '' ) );
		exit;
	}
	$user  = wp_get_current_user();
	$where = isset( $_POST['where'] ) ? sanitize_text_field( wp_unslash( $_POST['where'] ) ) : '';
	$id    = wp_insert_post( array( 'post_type' => 'tp_lost', 'post_status' => 'publish', 'post_title' => $item, 'post_author' => $user->ID ) );
	if ( ! $id || is_wp_error( $id ) ) {
		wp_die( esc_html__( 'Could not save the item. Please call the office.', 'taxi-peninsula' ) );
	}
	$meta = array(
		'kind'      => 'found',
		'status'    => 'open',
		/* translators: %s: driver name */
		'where'     => $where ?: sprintf( __( 'With driver %s', 'taxi-peninsula' ), $user->display_name ),
		'date'      => $bid ? (string) get_post_meta( $bid, '_tp_date', true ) : wp_date( 'Y-m-d' ),
		'reference' => $bid ? (string) get_post_meta( $bid, '_tp_reference', true ) : '',
		'booking_id' => $bid ?: '',
		'driver_id' => $bid ? (int) get_post_meta( $bid, '_tp_driver_id', true ) : $user->ID,
		'fleet_id'  => $bid ? (int) get_post_meta( $bid, '_tp_fleet_id', true ) : '',
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, '_tp_l_' . $k, $v );
	}

	// Photo: images only, up to 10 MB.
	$photo = '';
	if ( ! empty( $_FILES['photo']['name'] ) && empty( $_FILES['photo']['error'] ) && (int) $_FILES['photo']['size'] <= 10 * MB_IN_BYTES ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$check = wp_check_filetype_and_ext( $_FILES['photo']['tmp_name'], sanitize_file_name( wp_unslash( $_FILES['photo']['name'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $check['type'] && 0 === strpos( $check['type'], 'image/' ) ) {
			$att = media_handle_upload( 'photo', $id, array( 'post_title' => $item ), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'heic' => 'image/heic' ) ) );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $id, $att );
				$photo = wp_get_attachment_url( $att );
			}
		}
	}

	if ( $bid ) {
		/* translators: %s: item */
		tp_add_log( $bid, array( 'note' => sprintf( __( 'Driver found an item: %s', 'taxi-peninsula' ), $item ) ) );
	}
	if ( function_exists( 'tp_activity' ) ) {
		/* translators: %s: item */
		tp_activity( 'enquiry', sprintf( __( 'Found item logged by driver: %s', 'taxi-peninsula' ), $item ), $bid );
	}
	$matches = tp_lost_matches( $id );
	tp_in_site_lang(
		static function () use ( $id, $item, $meta, $user, $photo, $matches ) {
			$body = sprintf(
				/* translators: 1: driver 2: item 3: where */
				__( '%1$s found: %2$s. Now: %3$s.', 'taxi-peninsula' ),
				$user->display_name,
				$item,
				$meta['where']
			) . "\n";
			if ( $meta['reference'] ) {
				$body .= __( 'Trip', 'taxi-peninsula' ) . ': ' . $meta['reference'] . "\n";
			}
			if ( $matches ) {
				/* translators: %d: count */
				$body .= sprintf( _n( '%d passenger report may match.', '%d passenger reports may match.', count( $matches ), 'taxi-peninsula' ), count( $matches ) ) . "\n";
			}
			$body .= "\n";
			if ( $photo ) {
				$body .= __( 'Photo', 'taxi-peninsula' ) . ': ' . $photo . "\n";
			}
			$body .= __( 'Open lost property', 'taxi-peninsula' ) . ': ' . admin_url( 'post.php?action=edit&post=' . $id );
			wp_mail(
				tp_opt( 'notify_email' ) ?: get_option( 'admin_email' ),
				/* translators: %s: item */
				sprintf( __( 'Item found in a vehicle: %s', 'taxi-peninsula' ), $item ),
				$body,
				tp_mail_headers()
			);
		}
	);
	wp_safe_redirect( add_query_arg( 'found', $photo ? 'photo' : 'sent', $back ) . ( $bid ? '#job-' . $bid : '' ) );
	exit;
}

// Message on the driver screen after sending.
add_action(
	'tp_driver_jobs_top',
	static function () {
		$found = isset( $_GET['found'] ) ? sanitize_key( wp_unslash( $_GET['found'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sent' === $found || 'photo' === $found ) {
			echo '<p class="notice notice--success" role="status" tabindex="-1" data-focus>' . esc_html( 'photo' === $found ? __( 'Thanks — the office has the item details and photo.', 'taxi-peninsula' ) : __( 'Thanks — the office has the item details.', 'taxi-peninsula' ) ) . '</p>';
		} elseif ( 'missing' === $found ) {
			echo '<p class="notice notice--error" role="alert" tabindex="-1" data-focus>' . esc_html__( 'Please say what the item is.', 'taxi-peninsula' ) . '</p>';
		}
	}
);
