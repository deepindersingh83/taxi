<?php
/**
 * People & trust: public driver profiles (with consent), fleet photo galleries,
 * the wheelchair-safety video, and partner logos (with permission).
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Admin media pickers (driver photo, fleet gallery).
 * ---------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', static function ( $hook ) {
	$screen = get_current_screen();
	$needs  = in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) || ( $screen && 'tp_fleet' === $screen->post_type );
	if ( $needs && current_user_can( 'upload_files' ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'tp-admin', TP_URI . '/assets/js/admin.js', array( 'jquery' ), TP_VERSION, true );
	}
} );

/**
 * Thumbnail previews for a list of attachment IDs.
 */
function tp_media_previews( array $ids ) {
	$html = '';
	foreach ( $ids as $id ) {
		$html .= wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'width:72px;height:72px;object-fit:cover;border-radius:6px' ) );
	}
	return $html;
}

/* -------------------------------------------------------------------------
 * Driver profiles.
 * ---------------------------------------------------------------------- */

add_action( 'show_user_profile', 'tp_driver_profile_fields', 11 );
add_action( 'edit_user_profile', 'tp_driver_profile_fields', 11 );
function tp_driver_profile_fields( WP_User $user ) {
	if ( ! user_can( $user, 'tp_driver_jobs' ) ) {
		return;
	}
	$photo = (int) get_user_meta( $user->ID, 'tp_photo', true );
	?>
	<h2><?php esc_html_e( 'Public driver profile', 'taxi-peninsula' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Shown on the "Meet the drivers" section and to passengers once this driver is assigned to their trip. Only the first name is used.', 'taxi-peninsula' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Show on the website', 'taxi-peninsula' ); ?></th>
			<td><label><input type="checkbox" name="tp_public" value="1" <?php checked( get_user_meta( $user->ID, 'tp_public', true ), '1' ); ?>> <?php esc_html_e( 'Yes — the driver has agreed to their first name, photo and bio being shown publicly.', 'taxi-peninsula' ); ?></label></td>
		</tr>
		<tr>
			<th><label for="tp_bio"><?php esc_html_e( 'Short bio', 'taxi-peninsula' ); ?></label></th>
			<td><textarea name="tp_bio" id="tp_bio" rows="3" class="large-text" maxlength="400" placeholder="<?php esc_attr_e( 'e.g. Driving on the Peninsula for 8 years. Loves the footy and a good chat.', 'taxi-peninsula' ); ?>"><?php echo esc_textarea( get_user_meta( $user->ID, 'tp_bio', true ) ); ?></textarea></td>
		</tr>
		<?php if ( current_user_can( 'upload_files' ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Photo', 'taxi-peninsula' ); ?></th>
				<td>
					<div class="tp-media" data-tp-media data-multiple="0">
						<input type="hidden" name="tp_photo" value="<?php echo esc_attr( $photo ?: '' ); ?>">
						<div class="tp-media__preview"><?php echo $photo ? tp_media_previews( array( $photo ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?></div>
						<button type="button" class="button" data-tp-media-pick><?php esc_html_e( 'Choose photo', 'taxi-peninsula' ); ?></button>
						<button type="button" class="button-link" data-tp-media-clear><?php esc_html_e( 'Remove', 'taxi-peninsula' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'A friendly, well-lit head-and-shoulders photo works best. Add alt text in the Media Library.', 'taxi-peninsula' ); ?></p>
				</td>
			</tr>
		<?php endif; ?>
	</table>
	<?php
}

add_action( 'personal_options_update', 'tp_save_driver_profile' );
add_action( 'edit_user_profile_update', 'tp_save_driver_profile' );
function tp_save_driver_profile( $user_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- core verifies the profile nonce.
	if ( ! current_user_can( 'edit_user', $user_id ) || ! user_can( $user_id, 'tp_driver_jobs' ) ) {
		return;
	}
	update_user_meta( $user_id, 'tp_public', empty( $_POST['tp_public'] ) ? '' : '1' );
	update_user_meta( $user_id, 'tp_bio', isset( $_POST['tp_bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_bio'] ) ) : '' );
	if ( isset( $_POST['tp_photo'] ) && current_user_can( 'upload_files' ) ) {
		$photo = absint( $_POST['tp_photo'] );
		update_user_meta( $user_id, 'tp_photo', ( $photo && wp_attachment_is_image( $photo ) ) ? $photo : '' );
	}
	// phpcs:enable
	delete_transient( 'tp_public_drivers' );
}

/**
 * Public data for a driver who has opted in, or null.
 */
function tp_public_driver( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || '1' !== get_user_meta( $user_id, 'tp_public', true ) ) {
		return null;
	}
	return array(
		'name'  => $user->first_name ?: strtok( $user->display_name, ' ' ),
		'bio'   => get_user_meta( $user_id, 'tp_bio', true ),
		'photo' => (int) get_user_meta( $user_id, 'tp_photo', true ),
	);
}

/**
 * Driver photo or an initial as a fallback avatar.
 */
function tp_driver_avatar( array $d, $size = 'medium' ) {
	if ( $d['photo'] ) {
		return wp_get_attachment_image( $d['photo'], $size, false, array( 'class' => 'driver-card__photo', 'alt' => sprintf( /* translators: %s: first name */ __( 'Photo of %s', 'taxi-peninsula' ), $d['name'] ) ) );
	}
	return '<span class="driver-card__initial" aria-hidden="true">' . esc_html( mb_substr( $d['name'], 0, 1 ) ) . '</span>';
}

add_shortcode( 'tp_drivers', static function () {
	$drivers = array_filter( array_map( static function ( $u ) {
		return tp_public_driver( $u->ID );
	}, tp_drivers() ) );
	if ( ! $drivers ) {
		return '';
	}
	ob_start();
	echo '<ul class="tp-component driver-grid">';
	foreach ( $drivers as $d ) {
		echo '<li class="driver-card">';
		echo tp_driver_avatar( $d ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		echo '<h3 class="driver-card__name">' . esc_html( $d['name'] ) . '</h3>';
		if ( $d['bio'] ) {
			echo '<p>' . esc_html( $d['bio'] ) . '</p>';
		}
		echo '</li>';
	}
	echo '</ul>';
	return ob_get_clean();
} );

/* -------------------------------------------------------------------------
 * Fleet gallery (lightbox on the front end).
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes_tp_fleet', static function () {
	add_meta_box( 'tp_fleet_gallery', __( 'Photo gallery', 'taxi-peninsula' ), 'tp_render_gallery_box', 'tp_fleet', 'normal', 'default' );
} );

function tp_render_gallery_box( WP_Post $post ) {
	$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post->ID, '_tp_gallery', true ) ) ) );
	wp_nonce_field( 'tp_gallery', '_tp_gallery_nonce' );
	?>
	<div class="tp-media" data-tp-media data-multiple="1">
		<input type="hidden" name="tp_gallery" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
		<div class="tp-media__preview"><?php echo tp_media_previews( $ids ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?></div>
		<button type="button" class="button" data-tp-media-pick><?php esc_html_e( 'Choose photos', 'taxi-peninsula' ); ?></button>
		<button type="button" class="button-link" data-tp-media-clear><?php esc_html_e( 'Clear', 'taxi-peninsula' ); ?></button>
	</div>
	<p class="description"><?php esc_html_e( 'Show the ramp or hoist, the restraints and the inside of the vehicle. Give every photo alt text and a caption — they are read out in the full-screen viewer.', 'taxi-peninsula' ); ?></p>
	<?php
}

add_action( 'save_post_tp_fleet', static function ( $post_id ) {
	if ( ! isset( $_POST['_tp_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_gallery_nonce'] ) ), 'tp_gallery' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw = isset( $_POST['tp_gallery'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_gallery'] ) ) : '';
	$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ), 'wp_attachment_is_image' );
	update_post_meta( $post_id, '_tp_gallery', implode( ',', $ids ) );
} );

/**
 * Gallery items for a vehicle (featured image first).
 *
 * @return array[] Each: id, thumb, full, alt, caption.
 */
function tp_fleet_gallery( $post_id ) {
	$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, '_tp_gallery', true ) ) ) );
	if ( has_post_thumbnail( $post_id ) ) {
		array_unshift( $ids, (int) get_post_thumbnail_id( $post_id ) );
	}
	$out = array();
	foreach ( array_unique( $ids ) as $id ) {
		$full = wp_get_attachment_image_src( $id, 'large' );
		if ( ! $full ) {
			continue;
		}
		$out[] = array(
			'id'      => $id,
			'full'    => $full[0],
			'alt'     => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ?: get_the_title( $post_id ),
			'caption' => wp_get_attachment_caption( $id ),
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Wheelchair-safety video.
 * ---------------------------------------------------------------------- */

add_filter( 'upload_mimes', static function ( $mimes ) {
	$mimes['vtt'] = 'text/vtt';
	return $mimes;
} );

/**
 * Parse a YouTube / Vimeo link. Returns [provider, id] or null.
 */
function tp_parse_video_url( $url ) {
	if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $url, $m ) ) {
		return array( 'youtube', $m[1] );
	}
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', (string) $url, $m ) ) {
		return array( 'vimeo', $m[1] );
	}
	return null;
}

add_shortcode( 'tp_video', static function () {
	ob_start();
	get_template_part( 'template-parts/video' );
	return ob_get_clean();
} );

function tp_video_configured() {
	return (bool) ( tp_parse_video_url( tp_opt( 'video_url' ) ) || (int) tp_opt( 'video_file' ) );
}

/* -------------------------------------------------------------------------
 * Partners (logos shown only with permission).
 * ---------------------------------------------------------------------- */

add_action( 'init', static function () {
	register_post_type(
		'tp_partner',
		array(
			'labels'          => array(
				'name'               => __( 'Partners', 'taxi-peninsula' ),
				'singular_name'      => __( 'Partner', 'taxi-peninsula' ),
				'add_new_item'       => __( 'Add partner', 'taxi-peninsula' ),
				'edit_item'          => __( 'Edit partner', 'taxi-peninsula' ),
				'featured_image'     => __( 'Logo', 'taxi-peninsula' ),
				'set_featured_image' => __( 'Set logo', 'taxi-peninsula' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_rest'    => true,
			'menu_icon'       => 'dashicons-groups',
			'menu_position'   => 26,
			'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
			'capability_type' => 'page',
		)
	);
} );

add_action( 'add_meta_boxes_tp_partner', static function () {
	add_meta_box( 'tp_partner_box', __( 'Partner details', 'taxi-peninsula' ), static function ( WP_Post $post ) {
		wp_nonce_field( 'tp_partner', '_tp_partner_nonce' );
		?>
		<p><label for="tp_partner_url"><strong><?php esc_html_e( 'Website (optional)', 'taxi-peninsula' ); ?></strong></label><br>
			<input type="url" id="tp_partner_url" name="tp_partner_url" class="widefat" value="<?php echo esc_attr( get_post_meta( $post->ID, '_tp_url', true ) ); ?>"></p>
		<p><label><input type="checkbox" name="tp_partner_ok" value="1" <?php checked( get_post_meta( $post->ID, '_tp_permission', true ), '1' ); ?>> <strong><?php esc_html_e( 'We have written permission to show this logo', 'taxi-peninsula' ); ?></strong></label><br>
			<span class="description"><?php esc_html_e( 'The logo stays hidden until this is ticked.', 'taxi-peninsula' ); ?></span></p>
		<?php
	}, 'tp_partner', 'normal', 'high' );
} );

add_action( 'save_post_tp_partner', static function ( $post_id ) {
	if ( ! isset( $_POST['_tp_partner_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_tp_partner_nonce'] ) ), 'tp_partner' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_tp_url', isset( $_POST['tp_partner_url'] ) ? esc_url_raw( wp_unslash( $_POST['tp_partner_url'] ) ) : '' );
	update_post_meta( $post_id, '_tp_permission', empty( $_POST['tp_partner_ok'] ) ? '' : '1' );
} );

/**
 * Partners with a logo and permission.
 *
 * @return WP_Post[]
 */
function tp_partners() {
	return array_values(
		array_filter(
			get_posts(
				array(
					'post_type'   => 'tp_partner',
					'numberposts' => 24,
					'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
					'meta_key'    => '_tp_permission',
					'meta_value'  => '1',
				)
			),
			'has_post_thumbnail'
		)
	);
}

add_shortcode( 'tp_partners', static function () {
	ob_start();
	get_template_part( 'template-parts/partners' );
	return ob_get_clean();
} );
