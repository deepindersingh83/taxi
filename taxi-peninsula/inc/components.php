<?php
/**
 * Page components (shortcodes): [tp_faq], [tp_fleet], [tp_ndis], [tp_testimonials].
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * FAQ items collected on this request, output as FAQPage structured data.
 */
function tp_faq_schema_items( $add = null ) {
	static $items = array();
	if ( is_array( $add ) ) {
		$items[] = $add;
	}
	return $items;
}

add_shortcode( 'tp_faq', 'tp_faq_shortcode' );
function tp_faq_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'topic' => '', 'limit' => -1 ), $atts, 'tp_faq' );
	$query = array(
		'post_type'   => 'tp_faq',
		'numberposts' => (int) $atts['limit'],
		'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	);
	if ( $atts['topic'] ) {
		$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array( 'taxonomy' => 'tp_faq_topic', 'field' => 'slug', 'terms' => sanitize_title( $atts['topic'] ) ),
		);
	}
	$faqs = get_posts( $query );
	if ( ! $faqs ) {
		return '';
	}

	// Group by topic (in topic order) unless a single topic was requested.
	$groups = array();
	foreach ( $faqs as $faq ) {
		$terms = $atts['topic'] ? array() : get_the_terms( $faq, 'tp_faq_topic' );
		$name  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
		$groups[ $name ][] = $faq;
	}

	ob_start();
	echo '<div class="tp-component faq">';
	if ( count( $groups ) > 1 ) {
		echo '<nav class="faq__topics" aria-label="' . esc_attr__( 'FAQ topics', 'taxi-peninsula' ) . '"><ul class="chips">';
		foreach ( array_keys( $groups ) as $name ) {
			printf( '<li><a class="chip" href="#faq-%s">%s</a></li>', esc_attr( sanitize_title( $name ?: 'general' ) ), esc_html( $name ?: __( 'General', 'taxi-peninsula' ) ) );
		}
		echo '</ul></nav>';
	}
	foreach ( $groups as $name => $items ) {
		if ( count( $groups ) > 1 ) {
			printf( '<h2 class="faq__group" id="faq-%s">%s</h2>', esc_attr( sanitize_title( $name ?: 'general' ) ), esc_html( $name ?: __( 'General', 'taxi-peninsula' ) ) );
		}
		foreach ( $items as $faq ) {
			$answer = apply_filters( 'the_content', $faq->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			tp_faq_schema_items(
				array(
					'q' => get_the_title( $faq ),
					'a' => wp_strip_all_tags( $answer ),
				)
			);
			printf(
				'<details class="faq__item"><summary>%1$s</summary><div class="faq__answer prose">%2$s</div></details>',
				esc_html( get_the_title( $faq ) ),
				wp_kses_post( $answer )
			);
		}
	}
	echo '</div>';
	return ob_get_clean();
}

add_shortcode( 'tp_fleet', 'tp_fleet_shortcode' );
function tp_fleet_shortcode( $atts = array() ) {
	$atts     = shortcode_atts( array( 'limit' => 0 ), $atts, 'tp_fleet' );
	$vehicles = tp_fleet_vehicles();
	if ( (int) $atts['limit'] > 0 ) {
		$vehicles = array_slice( $vehicles, 0, (int) $atts['limit'] );
	}
	$access   = tp_content_fields()['tp_fleet']['access'][2];
	ob_start();
	echo '<div class="tp-component fleet">';
	if ( ! $vehicles ) {
		echo '<p>' . esc_html__( 'Our fleet details are coming soon.', 'taxi-peninsula' ) . '</p>';
	}
	foreach ( $vehicles as $v ) {
		$acc      = get_post_meta( $v->ID, '_tp_access', true );
		$chairs   = get_post_meta( $v->ID, '_tp_wheelchairs', true );
		$seats    = get_post_meta( $v->ID, '_tp_seats', true );
		$features = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $v->ID, '_tp_features', true ) ) ) );
		?>
		<article class="fleet-card">
			<div class="fleet-card__media">
				<?php if ( has_post_thumbnail( $v ) ) : ?>
					<?php echo get_the_post_thumbnail( $v, 'tp-card', array( 'alt' => tp_thumbnail_alt( $v ) ) ); ?>
				<?php else : ?>
					<div class="fleet-card__placeholder" aria-hidden="true"><?php tp_the_icon( 'wheelchair' ); ?></div>
				<?php endif; ?>
			</div>
			<div class="fleet-card__body">
				<h2 class="fleet-card__title"><?php echo esc_html( get_the_title( $v ) ); ?></h2>
				<ul class="spec-list">
					<?php if ( $acc ) : ?>
						<li><?php tp_the_icon( 'wheelchair' ); ?><span><?php echo esc_html( $access[ $acc ] ?? $acc ); ?></span></li>
					<?php endif; ?>
					<?php if ( '' !== $chairs ) : ?>
						<li><?php tp_the_icon( 'check' ); ?><span>
							<?php
							/* translators: %d: wheelchairs */
							echo esc_html( sprintf( _n( '%d wheelchair', '%d wheelchairs', (int) $chairs, 'taxi-peninsula' ), (int) $chairs ) );
							?>
						</span></li>
					<?php endif; ?>
					<?php if ( '' !== $seats ) : ?>
						<li><?php tp_the_icon( 'users' ); ?><span>
							<?php
							/* translators: %d: seats */
							echo esc_html( sprintf( _n( '%d seated passenger', '%d seated passengers', (int) $seats, 'taxi-peninsula' ), (int) $seats ) );
							?>
						</span></li>
					<?php endif; ?>
				</ul>
				<div class="prose"><?php echo wp_kses_post( apply_filters( 'the_content', $v->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?></div>
				<?php if ( $features ) : ?>
					<ul class="tick-list">
						<?php foreach ( $features as $feature ) : ?>
							<li><?php tp_the_icon( 'check' ); ?><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}
	echo '</div>';
	return ob_get_clean();
}

add_shortcode( 'tp_ndis', 'tp_ndis_shortcode' );
function tp_ndis_shortcode() {
	$book    = tp_booking_page_url();
	$account = tp_setting( 'invoice_enabled' ) ? add_query_arg( 'payment', 'account', $book ) : $book;
	ob_start();
	?>
	<div class="tp-component">
		<ol class="steps steps--cards">
			<li><strong><?php esc_html_e( 'Book online or call', 'taxi-peninsula' ); ?></strong><span><?php esc_html_e( 'Participants, family, support coordinators and providers can all book.', 'taxi-peninsula' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Choose "Account / invoice"', 'taxi-peninsula' ); ?></strong><span><?php esc_html_e( 'Tell us who to invoice and add the NDIS number or purchase order.', 'taxi-peninsula' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Set up regular trips', 'taxi-peninsula' ); ?></strong><span><?php esc_html_e( 'Tick "regular trip" to book the same run every week.', 'taxi-peninsula' ); ?></span></li>
			<li><strong><?php esc_html_e( 'Track every trip', 'taxi-peninsula' ); ?></strong><span><?php esc_html_e( 'Each trip has its own reference you can check on Manage My Booking.', 'taxi-peninsula' ); ?></span></li>
		</ol>
		<p class="cta-inline">
			<a class="btn btn--accent btn--lg" href="<?php echo esc_url( $account ); ?>"><?php esc_html_e( 'Book on account', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
			<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( tp_page_url_by_template( 'contact', tp_email_href() ) ); ?>"><?php esc_html_e( 'Ask about an account', 'taxi-peninsula' ); ?></a>
		</p>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'tp_testimonials', static function ( $atts ) {
	$atts = shortcode_atts( array( 'limit' => 6 ), $atts, 'tp_testimonials' );
	ob_start();
	get_template_part( 'template-parts/testimonials', null, array( 'limit' => (int) $atts['limit'], 'heading' => false ) );
	return ob_get_clean();
} );

/* ---- Shortcodes used by page templates and block patterns ---- */

add_shortcode( 'tp_services', static function () {
	ob_start();
	echo '<div class="tp-component services">';
	foreach ( tp_services_list() as $s ) {
		get_template_part( 'template-parts/service-card', null, $s );
	}
	echo '</div>';
	return ob_get_clean();
} );

add_shortcode( 'tp_areas', static function () {
	$links = tp_area_links();
	ob_start();
	echo '<ul class="tp-component chips">';
	foreach ( tp_service_areas() as $area ) {
		$link = $links[ strtolower( $area ) ] ?? '';
		echo '<li>';
		if ( $link ) {
			printf( '<a class="chip" href="%s">%s%s</a>', esc_url( $link ), tp_icon( 'pin' ), esc_html( $area ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup.
		} else {
			printf( '<span class="chip">%s%s</span>', tp_icon( 'pin' ), esc_html( $area ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</li>';
	}
	echo '</ul>';
	return ob_get_clean();
} );

add_shortcode( 'tp_contact_details', static function () {
	ob_start();
	?>
	<div class="tp-component contact-card contact-card--tall">
		<p class="contact-card__label"><?php esc_html_e( 'Call us', 'taxi-peninsula' ); ?></p>
		<a class="contact-card__phone" href="<?php echo esc_url( tp_phone_href() ); ?>"><?php tp_the_icon( 'phone' ); ?><?php echo esc_html( tp_opt( 'phone_display' ) ); ?></a>
		<ul class="icon-list">
			<li><?php tp_the_icon( 'mail' ); ?><a href="<?php echo esc_attr( tp_email_href() ); ?>"><?php echo esc_html( antispambot( tp_opt( 'email' ) ) ); ?></a></li>
			<li><?php tp_the_icon( 'pin' ); ?><span><?php echo esc_html( tp_opt( 'location' ) ); ?></span></li>
			<li><?php tp_the_icon( 'clock' ); ?><span><?php echo esc_html( tp_opt( 'hours' ) ); ?></span></li>
		</ul>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * Google Maps embed of your service area (no API key needed). Set the place in
 * Appearance → Customize → Taxi Peninsula → Contact details → "Map location".
 */
add_shortcode( 'tp_map', static function ( $atts ) {
	$atts  = shortcode_atts( array( 'q' => '' ), $atts, 'tp_map' );
	$place = $atts['q'] ?: tp_opt( 'map_query' );
	if ( ! $place ) {
		return '';
	}
	return sprintf(
		'<div class="tp-component map-embed"><iframe title="%1$s" src="%2$s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>',
		/* translators: %s: place */
		esc_attr( sprintf( __( 'Map of %s', 'taxi-peninsula' ), $place ) ),
		esc_url( 'https://www.google.com/maps?q=' . rawurlencode( $place ) . '&output=embed' )
	);
} );
