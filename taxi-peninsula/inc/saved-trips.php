<?php
/**
 * Regular trips (My Account → Regular trips): save a trip such as
 * "Dialysis — Tue & Thu 7:30am", book the next one with one tap, or have it
 * booked automatically a week ahead. Each automatic booking is a normal
 * booking request: the customer gets the usual "booking received" email and
 * can change or cancel it like any other trip.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

const TP_SAVED_TRIP_LEAD_DAYS = 7;

function tp_saved_trips( $user_id = 0 ) {
	$list = get_user_meta( $user_id ?: get_current_user_id(), 'tp_saved_trips', true );
	return is_array( $list ) ? $list : array();
}

/**
 * Fields copied from the source booking into a saved trip.
 */
function tp_saved_trip_fields() {
	return array( 'name', 'phone', 'email', 'pickup', 'dropoff', 'passengers', 'wheelchairs', 'vehicle', 'mobility_aid', 'mptp', 'payment', 'invoice_name', 'invoice_email', 'ndis_number', 'notes' );
}

function tp_weekday_names() {
	global $wp_locale;
	$out = array();
	for ( $d = 1; $d <= 7; $d++ ) {
		$out[ $d ] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d % 7 ) );
	}
	return $out;
}

/**
 * Next date (Y-m-d) on one of the trip's weekdays, from tomorrow on.
 */
function tp_saved_trip_next_date( array $t ) {
	$d = new DateTimeImmutable( 'tomorrow', wp_timezone() );
	for ( $i = 0; $i < 14; $i++ ) {
		$day = $d->modify( "+$i days" );
		if ( in_array( (int) $day->format( 'N' ), (array) $t['days'], true ) ) {
			return $day->format( 'Y-m-d' );
		}
	}
	return '';
}

/* -------------------------------------------------------------------------
 * My Account tab.
 * ---------------------------------------------------------------------- */

add_filter(
	'tp_account_tabs',
	static function ( $tabs ) {
		$out = array();
		foreach ( $tabs as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'trips' === $k ) {
				$out['regular'] = __( 'Regular trips', 'taxi-peninsula' );
			}
		}
		return $out;
	},
	20
);

add_action(
	'tp_account_tab_regular',
	static function ( $user ) {
		$trips  = tp_saved_trips( $user->ID );
		$recent = get_posts(
			array(
				'post_type'   => 'tp_booking',
				'post_status' => 'publish',
				'numberposts' => 30,
				'fields'      => 'ids',
				'orderby'     => 'date',
				'order'       => 'DESC',
				'meta_query'  => array( array( 'key' => '_tp_customer_id', 'value' => $user->ID ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$days   = tp_weekday_names();
		$book   = tp_booking_page_url();
		?>
		<section class="account-section regular-trips">
			<h2><?php esc_html_e( 'Regular trips', 'taxi-peninsula' ); ?></h2>
			<p><?php esc_html_e( 'Save trips you make often — dialysis, day programs, work — and book the next one in one tap, or let us book them automatically a week ahead.', 'taxi-peninsula' ); ?></p>

			<?php if ( $trips ) : ?>
				<ul class="regular-trips__list">
					<?php foreach ( $trips as $key => $t ) : ?>
						<?php $next = tp_saved_trip_next_date( $t ); ?>
						<li class="card regular-trip">
							<h3><?php echo esc_html( $t['label'] ); ?></h3>
							<p class="regular-trip__when">
								<?php echo esc_html( implode( ', ', array_intersect_key( $days, array_flip( (array) $t['days'] ) ) ) . ' · ' . tp_short_time( $t['time'] ) . ( $t['return_time'] ? ' · ' . sprintf( /* translators: %s: time */ __( 'return %s', 'taxi-peninsula' ), tp_short_time( $t['return_time'] ) ) : '' ) ); ?>
							</p>
							<p class="regular-trip__route"><?php echo esc_html( $t['data']['pickup'] . ' → ' . $t['data']['dropoff'] ); ?></p>
							<?php if ( ! empty( $t['auto'] ) ) : ?>
								<p class="notice notice--info">
									<?php
									echo ! empty( $t['pause_until'] ) && $t['pause_until'] >= wp_date( 'Y-m-d' )
										/* translators: %s: date */
										? esc_html( sprintf( __( 'Automatic booking is paused until %s.', 'taxi-peninsula' ), wp_date( 'j F', strtotime( $t['pause_until'] ) ) ) )
										: esc_html__( 'Booked automatically a week ahead. You get an email for each trip and can cancel any of them.', 'taxi-peninsula' );
									?>
								</p>
							<?php endif; ?>
							<div class="regular-trip__actions">
								<?php if ( $next ) : ?>
									<a class="btn btn--accent" href="<?php echo esc_url( add_query_arg( array( 'trip' => $key, 'date' => $next ), $book ) . '#book' ); ?>">
										<?php /* translators: %s: date */ echo esc_html( sprintf( __( 'Book %s', 'taxi-peninsula' ), wp_date( 'D j M', strtotime( $next ) ) ) ); ?>
									</a>
								<?php endif; ?>
								<details class="regular-trip__edit">
									<summary class="btn btn--ghost"><?php esc_html_e( 'Change', 'taxi-peninsula' ); ?></summary>
									<?php tp_saved_trip_form( $key, $t, array() ); ?>
								</details>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Save a regular trip', 'taxi-peninsula' ); ?></h3>
			<?php if ( ! $recent ) : ?>
				<p class="empty-note"><?php esc_html_e( 'Book a trip first — then you can save it here as a regular trip.', 'taxi-peninsula' ); ?></p>
			<?php else : ?>
				<?php tp_saved_trip_form( '', array(), $recent ); ?>
			<?php endif; ?>
		</section>
		<?php
	}
);

/**
 * Add / change form.
 *
 * @param string $key    Saved trip key ('' for new).
 * @param array  $t      Saved trip.
 * @param int[]  $recent Bookings to copy from (new trips only).
 */
function tp_saved_trip_form( $key, array $t, array $recent ) {
	$p    = 'st-' . ( $key ?: 'new' );
	$days = tp_weekday_names();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="booking-form regular-trip__form">
		<?php tp_account_form_fields( 'tp_account_saved_trip' ); ?>
		<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
		<?php if ( ! $key ) : ?>
			<div class="field field--wide">
				<label for="<?php echo esc_attr( $p ); ?>-from"><?php esc_html_e( 'Copy the details from', 'taxi-peninsula' ); ?></label>
				<select id="<?php echo esc_attr( $p ); ?>-from" name="booking" required>
					<?php foreach ( $recent as $id ) : ?>
						<?php $b = tp_get_booking( $id ); ?>
						<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $b['name'] . ': ' . ( tp_short_place( $b['pickup'] ) ?: $b['pickup'] ) . ' → ' . ( tp_short_place( $b['dropoff'] ) ?: $b['dropoff'] ) . ' (' . tp_format_pickup( $b, 'j M' ) . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>
		<div class="field">
			<label for="<?php echo esc_attr( $p ); ?>-label"><?php esc_html_e( 'Name', 'taxi-peninsula' ); ?></label>
			<input id="<?php echo esc_attr( $p ); ?>-label" name="label" type="text" required maxlength="40" value="<?php echo esc_attr( $t['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Dialysis', 'taxi-peninsula' ); ?>">
		</div>
		<fieldset class="field field--wide">
			<legend><?php esc_html_e( 'Days', 'taxi-peninsula' ); ?></legend>
			<div class="chips-check">
				<?php foreach ( $days as $n => $label ) : ?>
					<label><input type="checkbox" name="days[]" value="<?php echo esc_attr( $n ); ?>" <?php checked( in_array( $n, (array) ( $t['days'] ?? array() ), true ) ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<div class="field">
			<label for="<?php echo esc_attr( $p ); ?>-time"><?php esc_html_e( 'Pick-up time', 'taxi-peninsula' ); ?></label>
			<input id="<?php echo esc_attr( $p ); ?>-time" name="time" type="time" required value="<?php echo esc_attr( $t['time'] ?? '' ); ?>">
		</div>
		<div class="field">
			<label for="<?php echo esc_attr( $p ); ?>-return"><?php esc_html_e( 'Return pick-up (optional)', 'taxi-peninsula' ); ?></label>
			<input id="<?php echo esc_attr( $p ); ?>-return" name="return_time" type="time" value="<?php echo esc_attr( $t['return_time'] ?? '' ); ?>">
		</div>
		<div class="field field--wide field--check">
			<label><input type="checkbox" name="auto" value="1" <?php checked( ! empty( $t['auto'] ) ); ?>> <?php esc_html_e( 'Book these trips for me automatically, a week ahead', 'taxi-peninsula' ); ?></label>
		</div>
		<div class="field">
			<label for="<?php echo esc_attr( $p ); ?>-pause"><?php esc_html_e( 'Pause automatic booking until (optional)', 'taxi-peninsula' ); ?></label>
			<input id="<?php echo esc_attr( $p ); ?>-pause" name="pause_until" type="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $t['pause_until'] ?? '' ); ?>">
		</div>
		<div class="booking-form__submit">
			<button type="submit" class="btn btn--primary"><?php echo $key ? esc_html__( 'Save changes', 'taxi-peninsula' ) : esc_html__( 'Save regular trip', 'taxi-peninsula' ); ?></button>
			<?php if ( $key ) : ?>
				<button type="submit" name="delete" value="1" class="btn btn--ghost"><?php esc_html_e( 'Delete', 'taxi-peninsula' ); ?></button>
			<?php endif; ?>
		</div>
	</form>
	<?php
}

add_action(
	'admin_post_tp_account_saved_trip',
	static function () {
		tp_account_verify( 'tp_account_saved_trip' );
		$user_id = get_current_user_id();
		$trips   = tp_saved_trips( $user_id );
		$key     = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		if ( $key && ! isset( $trips[ $key ] ) ) {
			tp_account_back( array( 'tab' => 'regular' ) );
		}
		if ( $key && ! empty( $_POST['delete'] ) ) {
			unset( $trips[ $key ] );
			update_user_meta( $user_id, 'tp_saved_trips', $trips );
			tp_account_back( array( 'tab' => 'regular', 'saved' => 'deleted' ) );
		}
		$t = $key ? $trips[ $key ] : array();
		if ( ! $key ) {
			$bid = isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0;
			if ( ! tp_user_owns_booking( $bid, $user_id ) ) {
				tp_account_back( array( 'tab' => 'regular' ), array( __( 'Please choose one of your trips.', 'taxi-peninsula' ) ) );
			}
			$b         = tp_get_booking( $bid );
			$t['data'] = array_intersect_key( $b, array_flip( tp_saved_trip_fields() ) );
			$t['passenger'] = (string) get_post_meta( $bid, '_tp_passenger_key', true );
			$t['lang']      = (string) get_post_meta( $bid, '_tp_lang', true );
			$key       = 'r' . strtolower( wp_generate_password( 8, false ) );
		}
		$t['label']       = mb_substr( sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ), 0, 40 ) ?: __( 'Regular trip', 'taxi-peninsula' );
		$t['days']        = array_values( array_intersect( array_map( 'absint', (array) ( $_POST['days'] ?? array() ) ), range( 1, 7 ) ) );
		$time             = sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) );
		$ret              = sanitize_text_field( wp_unslash( $_POST['return_time'] ?? '' ) );
		$pause            = sanitize_text_field( wp_unslash( $_POST['pause_until'] ?? '' ) );
		$t['time']        = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
		$t['return_time'] = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $ret ) && $ret > $t['time'] ? $ret : '';
		$t['auto']        = ! empty( $_POST['auto'] );
		$t['pause_until'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $pause ) ? $pause : '';
		$errors           = array();
		if ( ! $t['days'] ) {
			$errors[] = __( 'Choose at least one day.', 'taxi-peninsula' );
		}
		if ( ! $t['time'] ) {
			$errors[] = __( 'Please choose a pick-up time.', 'taxi-peninsula' );
		}
		if ( $errors ) {
			tp_account_back( array( 'tab' => 'regular' ), $errors );
		}
		$trips[ $key ] = $t;
		update_user_meta( $user_id, 'tp_saved_trips', $trips );
		tp_account_back( array( 'tab' => 'regular', 'saved' => 'passenger' ) );
	}
);

// Booking form: ?trip=KEY&date=Y-m-d fills in a saved trip.
add_filter(
	'tp_booking_form_link_prefill',
	static function ( $old ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only prefill of the user's own saved trip.
		$key = isset( $_GET['trip'] ) ? sanitize_key( wp_unslash( $_GET['trip'] ) ) : '';
		if ( ! $key || ! is_user_logged_in() ) {
			return $old;
		}
		$t = tp_saved_trips()[ $key ] ?? null;
		if ( ! $t ) {
			return $old;
		}
		$old = array_merge( $old, $t['data'] );
		$old['time'] = $t['time'];
		$date        = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$old['date'] = $date;
		}
		if ( $t['return_time'] ) {
			$old['return_trip'] = 1;
			$old['return_time'] = $t['return_time'];
		}
		if ( $t['passenger'] ) {
			$old['passenger'] = $t['passenger'];
		}
		// phpcs:enable
		return $old;
	}
);

/* -------------------------------------------------------------------------
 * Automatic booking, once a day.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'tp_saved_trips_run' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 06:10' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'tp_saved_trips_run' );
		}
	}
);
add_action( 'tp_saved_trips_run', 'tp_run_saved_trips' );

/**
 * Book every automatic regular trip falling on the date $days ahead.
 *
 * @return int Bookings created.
 */
function tp_run_saved_trips( $days = TP_SAVED_TRIP_LEAD_DAYS ) {
	$date = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+' . (int) $days . ' days' );
	$dow  = (int) $date->format( 'N' );
	$ymd  = $date->format( 'Y-m-d' );
	$made = 0;
	foreach ( get_users( array( 'meta_key' => 'tp_saved_trips', 'fields' => 'ID', 'number' => 500 ) ) as $user_id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$user = get_userdata( $user_id );
		if ( ! $user || ! user_can( $user, 'tp_book_account' ) ) {
			continue;
		}
		foreach ( tp_saved_trips( $user_id ) as $key => $t ) {
			if ( empty( $t['auto'] ) || ! in_array( $dow, (array) $t['days'], true ) || ( ! empty( $t['pause_until'] ) && $t['pause_until'] >= $ymd ) ) {
				continue;
			}
			$exists = get_posts(
				array(
					'post_type'   => 'tp_booking',
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array( 'key' => '_tp_saved_trip', 'value' => $user_id . ':' . $key ),
						array( 'key' => '_tp_date', 'value' => $ymd ),
					),
				)
			);
			if ( $exists ) {
				continue;
			}
			$ids = array();
			$leg = array_merge( $t['data'], array( 'date' => $ymd, 'time' => $t['time'], 'return_trip' => 0, 'return_time' => '' ) );
			$ids[] = tp_create_saved_trip_booking( $leg, $user_id, $key, $t );
			if ( $t['return_time'] ) {
				$back  = array_merge( $leg, array( 'pickup' => $t['data']['dropoff'], 'dropoff' => $t['data']['pickup'], 'time' => $t['return_time'] ) );
				$ids[] = tp_create_saved_trip_booking( $back, $user_id, $key, $t );
			}
			$ids = array_filter( $ids );
			if ( $ids ) {
				$made += count( $ids );
				do_action( 'tp_booking_created', $ids[0], $ids );
			}
		}
	}
	return $made;
}

function tp_create_saved_trip_booking( array $d, $user_id, $key, array $t ) {
	$ref = tp_new_reference();
	$id  = wp_insert_post( array_merge( array( 'post_type' => 'tp_booking', 'post_status' => 'publish' ), tp_booking_post_fields( $ref, $d ) ), true );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_tp_reference', $ref );
	update_post_meta( $id, '_tp_status', 'pending' );
	tp_save_booking_meta( $id, $d );
	update_post_meta( $id, '_tp_customer_id', $user_id );
	update_post_meta( $id, '_tp_saved_trip', $user_id . ':' . $key );
	if ( $t['passenger'] ) {
		update_post_meta( $id, '_tp_passenger_key', $t['passenger'] );
	}
	if ( ! empty( $t['lang'] ) ) {
		update_post_meta( $id, '_tp_lang', $t['lang'] );
	}
	tp_in_site_lang(
		static function () use ( $id, $t ) {
			/* translators: %s: regular trip name */
			tp_add_log( $id, array( 'note' => sprintf( __( 'Booked automatically from the customer’s regular trip "%s".', 'taxi-peninsula' ), $t['label'] ) ) );
		}
	);
	return $id;
}
