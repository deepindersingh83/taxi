<?php
/**
 * Weather and road alerts.
 *
 * - Bureau of Meteorology warnings for Victoria, checked every hour and
 *   filtered to the districts you serve.
 * - Road alerts the office types in (closures, big events, crashes), each
 *   with an expiry time.
 *
 * Both appear on the driver screen and the dispatch board. Road alerts marked
 * "show on the website" (and, if switched on, weather warnings) also appear
 * as a banner across the public site.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'tp_settings_schema',
	static function ( $s ) {
		$s['alerts_weather'] = array( 'alerts', __( 'Check Bureau of Meteorology warnings', 'taxi-peninsula' ), 'checkbox', true, __( 'Checked every hour. Shown to drivers and on the dispatch board.', 'taxi-peninsula' ) );
		$s['alerts_bom_url'] = array( 'alerts', __( 'Warnings feed', 'taxi-peninsula' ), 'url', 'http://www.bom.gov.au/fwo/IDZ00059.warnings_vic.xml', __( 'The Bureau’s RSS feed of current Victorian warnings.', 'taxi-peninsula' ) );
		$s['alerts_include'] = array( 'alerts', __( 'Show warnings that mention', 'taxi-peninsula' ), 'text', 'Central, Melbourne, Mornington, West and South Gippsland', __( 'Comma separated. Central is the forecast district that covers Melbourne and the Mornington Peninsula.', 'taxi-peninsula' ) );
		$s['alerts_exclude'] = array( 'alerts', __( 'Skip warnings that mention', 'taxi-peninsula' ), 'text', 'Marine, Coastal Waters, Sheep Graziers, Surf, Bass Strait', __( 'Comma separated, e.g. boating warnings.', 'taxi-peninsula' ) );
		$s['alerts_public']  = array( 'alerts', __( 'Also show weather warnings to website visitors', 'taxi-peninsula' ), 'checkbox', false, __( 'A short banner with a link to the Bureau, e.g. "Severe thunderstorm warning — trips may run late".', 'taxi-peninsula' ) );
		return $s;
	}
);
add_filter(
	'tp_settings_sections',
	static function ( $s ) {
		$s['alerts'] = array( __( 'Weather & road alerts', 'taxi-peninsula' ), __( 'Add road alerts under Bookings → Alerts.', 'taxi-peninsula' ) );
		return $s;
	}
);

/* -------------------------------------------------------------------------
 * Weather warnings.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		if ( tp_setting( 'alerts_weather' ) && ! wp_next_scheduled( 'tp_alerts_fetch' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'tp_alerts_fetch' );
		}
	}
);
add_action( 'tp_alerts_fetch', 'tp_alerts_fetch_weather' );

/**
 * Words list from a comma-separated setting.
 */
function tp_alerts_words( $key ) {
	return array_values( array_filter( array_map( 'trim', explode( ',', (string) tp_setting( $key ) ) ) ) );
}

/**
 * Does a warning title match the include/exclude lists?
 */
function tp_alerts_relevant( $title ) {
	foreach ( tp_alerts_words( 'alerts_exclude' ) as $w ) {
		if ( false !== stripos( $title, $w ) ) {
			return false;
		}
	}
	$include = tp_alerts_words( 'alerts_include' );
	if ( ! $include ) {
		return true;
	}
	foreach ( $include as $w ) {
		if ( false !== stripos( $title, $w ) ) {
			return true;
		}
	}
	return false;
}

function tp_alerts_fetch_weather() {
	$url = (string) tp_setting( 'alerts_bom_url' );
	if ( ! tp_setting( 'alerts_weather' ) || ! $url ) {
		return;
	}
	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 10,
			// The Bureau refuses requests without a browser-like user agent.
			'user-agent' => 'Mozilla/5.0 (compatible; ' . get_bloginfo( 'name' ) . ' alerts; +' . home_url( '/' ) . ')',
		)
	);
	$state = array( 'checked' => time(), 'items' => array(), 'error' => '' );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		$old            = (array) get_option( 'tp_weather_alerts', array() );
		$state['items'] = (array) ( $old['items'] ?? array() );
		$state['error'] = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
		update_option( 'tp_weather_alerts', $state, false );
		return;
	}
	$prev = libxml_use_internal_errors( true );
	$xml  = simplexml_load_string( wp_remote_retrieve_body( $response ), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_use_internal_errors( $prev );
	if ( ! $xml || ! isset( $xml->channel ) ) {
		$state['error'] = __( 'The feed could not be read.', 'taxi-peninsula' );
		update_option( 'tp_weather_alerts', $state, false );
		return;
	}
	foreach ( $xml->channel->item as $item ) {
		$title = sanitize_text_field( (string) $item->title );
		if ( '' === $title || ! tp_alerts_relevant( $title ) ) {
			continue;
		}
		$link             = esc_url_raw( (string) $item->link );
		$state['items'][] = array(
			'title' => $title,
			'link'  => wp_http_validate_url( $link ) ? $link : 'http://www.bom.gov.au/vic/warnings/',
			'date'  => strtotime( (string) $item->pubDate ) ?: time(),
		);
	}
	$state['items'] = array_slice( $state['items'], 0, 8 );
	update_option( 'tp_weather_alerts', $state, false );
}

function tp_weather_alerts() {
	$state = (array) get_option( 'tp_weather_alerts', array() );
	// Ignore a stale list if checks have stopped for a day.
	if ( ! tp_setting( 'alerts_weather' ) || empty( $state['checked'] ) || $state['checked'] < time() - DAY_IN_SECONDS ) {
		return array();
	}
	return (array) ( $state['items'] ?? array() );
}

/* -------------------------------------------------------------------------
 * Road alerts typed in by the office.
 * ---------------------------------------------------------------------- */

function tp_road_alerts( $public_only = false ) {
	$out = array();
	foreach ( (array) get_option( 'tp_road_alerts', array() ) as $a ) {
		if ( ! is_array( $a ) || (int) ( $a['until'] ?? 0 ) < time() ) {
			continue;
		}
		if ( $public_only && empty( $a['public'] ) ) {
			continue;
		}
		$out[] = $a;
	}
	return $out;
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( tp_admin_parent( 'bookings' ), __( 'Weather & road alerts', 'taxi-peninsula' ), __( 'Alerts', 'taxi-peninsula' ), 'edit_tp_bookings', 'tp-alerts', 'tp_render_alerts_page' );
	},
	30
);

add_action(
	'admin_post_tp_road_alert',
	static function () {
		if ( ! current_user_can( 'edit_tp_bookings' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'tp_road_alert' );
		$list = array_values( tp_road_alerts() );
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'add' === $do ) {
			$text  = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';
			$hours = isset( $_POST['hours'] ) ? max( 1, min( 24 * 14, absint( $_POST['hours'] ) ) ) : 4;
			if ( '' !== $text ) {
				$list[] = array(
					'id'     => wp_generate_password( 8, false ),
					'text'   => mb_substr( $text, 0, 200 ),
					'until'  => time() + $hours * HOUR_IN_SECONDS,
					'public' => ! empty( $_POST['public'] ),
					'by'     => wp_get_current_user()->display_name,
				);
				if ( function_exists( 'tp_activity' ) ) {
					/* translators: %s: alert */
					tp_activity( 'note', sprintf( __( 'Road alert added: %s', 'taxi-peninsula' ), $text ) );
				}
			}
		} elseif ( 'remove' === $do ) {
			$id   = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
			$list = array_values( array_filter( $list, static fn( $a ) => $a['id'] !== $id ) );
		} elseif ( 'check' === $do ) {
			tp_alerts_fetch_weather();
		}
		update_option( 'tp_road_alerts', $list, false );
		wp_safe_redirect( admin_url( 'admin.php?page=tp-alerts' ) );
		exit;
	}
);

function tp_render_alerts_page() {
	$state = (array) get_option( 'tp_weather_alerts', array() );
	?>
	<div class="wrap tp-alerts">
		<h1><?php esc_html_e( 'Weather & road alerts', 'taxi-peninsula' ); ?></h1>
		<p><?php esc_html_e( 'Drivers see these at the top of their jobs screen, and they show on the dispatch board.', 'taxi-peninsula' ); ?></p>

		<h2><?php esc_html_e( 'Add a road alert', 'taxi-peninsula' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tp_road_alert"><input type="hidden" name="do" value="add">
			<?php wp_nonce_field( 'tp_road_alert' ); ?>
			<p><label for="tp-ra-text"><?php esc_html_e( 'Alert', 'taxi-peninsula' ); ?></label><br>
				<input type="text" class="large-text" id="tp-ra-text" name="text" maxlength="200" required placeholder="<?php esc_attr_e( 'e.g. Peninsula Link closed southbound at Skye Rd — allow 20 extra minutes', 'taxi-peninsula' ); ?>"></p>
			<p><label for="tp-ra-hours"><?php esc_html_e( 'Remove after', 'taxi-peninsula' ); ?></label>
				<select id="tp-ra-hours" name="hours">
					<?php foreach ( array( 2 => __( '2 hours', 'taxi-peninsula' ), 4 => __( '4 hours', 'taxi-peninsula' ), 8 => __( '8 hours', 'taxi-peninsula' ), 24 => __( '1 day', 'taxi-peninsula' ), 72 => __( '3 days', 'taxi-peninsula' ), 168 => __( '1 week', 'taxi-peninsula' ) ) as $h => $l ) : ?>
						<option value="<?php echo esc_attr( $h ); ?>" <?php selected( 4, $h ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				&nbsp; <label><input type="checkbox" name="public" value="1"> <?php esc_html_e( 'Also show on the website', 'taxi-peninsula' ); ?></label></p>
			<?php submit_button( __( 'Add alert', 'taxi-peninsula' ), 'primary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Current road alerts', 'taxi-peninsula' ); ?></h2>
		<?php $roads = tp_road_alerts(); ?>
		<?php if ( ! $roads ) : ?>
			<p><?php esc_html_e( 'None.', 'taxi-peninsula' ); ?></p>
		<?php else : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Alert', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Until', 'taxi-peninsula' ); ?></th><th><?php esc_html_e( 'Website', 'taxi-peninsula' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $roads as $a ) : ?>
				<tr>
					<td><?php echo esc_html( $a['text'] ); ?><br><small><?php echo esc_html( $a['by'] ?? '' ); ?></small></td>
					<td><?php echo esc_html( wp_date( 'D j M g:ia', (int) $a['until'] ) ); ?></td>
					<td><?php echo ! empty( $a['public'] ) ? esc_html__( 'Yes', 'taxi-peninsula' ) : '—'; ?></td>
					<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="tp_road_alert"><input type="hidden" name="do" value="remove"><input type="hidden" name="id" value="<?php echo esc_attr( $a['id'] ); ?>"><?php wp_nonce_field( 'tp_road_alert' ); ?><button class="button-link button-link-delete"><?php esc_html_e( 'Remove', 'taxi-peninsula' ); ?></button></form></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Bureau of Meteorology warnings', 'taxi-peninsula' ); ?></h2>
		<?php if ( ! tp_setting( 'alerts_weather' ) ) : ?>
			<p><?php printf( /* translators: %s: settings link */ esc_html__( 'Weather checks are off. Turn them on in %s.', 'taxi-peninsula' ), '<a href="' . esc_url( admin_url( 'admin.php?page=tp-settings' ) ) . '">' . esc_html__( 'Settings', 'taxi-peninsula' ) . '</a>' ); ?></p>
		<?php else : ?>
			<p class="description">
				<?php
				echo ! empty( $state['checked'] )
					/* translators: %s: time */
					? esc_html( sprintf( __( 'Last checked %s.', 'taxi-peninsula' ), wp_date( 'D j M g:ia', (int) $state['checked'] ) ) )
					: esc_html__( 'Not checked yet.', 'taxi-peninsula' );
				if ( ! empty( $state['error'] ) ) {
					/* translators: %s: error */
					echo ' <strong>' . esc_html( sprintf( __( 'Last check failed: %s', 'taxi-peninsula' ), $state['error'] ) ) . '</strong>';
				}
				?>
			</p>
			<?php tp_render_alerts_box( 'admin', false ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="tp_road_alert"><input type="hidden" name="do" value="check"><?php wp_nonce_field( 'tp_road_alert' ); ?><?php submit_button( __( 'Check now', 'taxi-peninsula' ), 'secondary', 'submit', false ); ?></form>
			<p class="description"><?php esc_html_e( 'Data: Australian Government Bureau of Meteorology (bom.gov.au).', 'taxi-peninsula' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Display.
 * ---------------------------------------------------------------------- */

/**
 * Alerts list for staff screens.
 *
 * @param string $context 'driver', 'dispatch' or 'admin'.
 * @param bool   $roads   Include road alerts.
 */
function tp_render_alerts_box( $context, $roads = true ) {
	$weather = tp_weather_alerts();
	$road    = $roads ? tp_road_alerts() : array();
	if ( ! $weather && ! $road ) {
		if ( 'admin' === $context ) {
			echo '<p>' . esc_html__( 'No current warnings for your area.', 'taxi-peninsula' ) . '</p>';
		}
		return;
	}
	$admin = 'driver' !== $context;
	printf( '<div class="%s" role="region" aria-label="%s">', esc_attr( $admin ? 'notice notice-warning inline tp-alerts-box' : 'tp-alerts-box tp-alerts-box--front' ), esc_attr__( 'Weather and road alerts', 'taxi-peninsula' ) );
	echo '<ul class="tp-alerts-box__list">';
	foreach ( $road as $a ) {
		/* translators: %s: time */
		printf( '<li><strong>%s</strong> %s <small>(%s)</small></li>', esc_html__( 'Road:', 'taxi-peninsula' ), esc_html( $a['text'] ), esc_html( sprintf( __( 'until %s', 'taxi-peninsula' ), wp_date( 'D g:ia', (int) $a['until'] ) ) ) );
	}
	foreach ( $weather as $w ) {
		printf( '<li><strong>%s</strong> <a href="%s" target="_blank" rel="noopener">%s</a></li>', esc_html__( 'Weather:', 'taxi-peninsula' ), esc_url( $w['link'] ), esc_html( $w['title'] ) );
	}
	echo '</ul></div>';
}

add_action(
	'tp_driver_jobs_top',
	static function () {
		tp_render_alerts_box( 'driver' );
	}
);
add_action(
	'tp_dispatch_top',
	static function () {
		tp_render_alerts_box( 'dispatch' );
	}
);

// Public banner, under any announcements.
add_action(
	'tp_after_notices',
	static function () {
		$items = array();
		foreach ( tp_road_alerts( true ) as $a ) {
			$items[] = esc_html( $a['text'] );
		}
		if ( tp_setting( 'alerts_public' ) ) {
			foreach ( array_slice( tp_weather_alerts(), 0, 1 ) as $w ) {
				/* translators: %s: warning title */
				$items[] = esc_html( sprintf( __( '%s Trips may take longer than usual.', 'taxi-peninsula' ), rtrim( $w['title'], '. ' ) . '.' ) ) . ' <a href="' . esc_url( $w['link'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Bureau of Meteorology', 'taxi-peninsula' ) . '</a>';
			}
		}
		if ( ! $items ) {
			return;
		}
		printf( '<div class="announce announce--warning announce--alert" role="region" aria-label="%s"><div class="container announce__inner"><p class="announce__text">%s<span>%s</span></p></div></div>', esc_attr__( 'Travel alert', 'taxi-peninsula' ), tp_icon( 'shield' ), implode( ' · ', $items ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
);
