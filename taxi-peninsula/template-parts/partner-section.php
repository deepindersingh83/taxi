<?php
/**
 * Partner section: heading, two numbers, a button, and two rows of logos
 * moving in opposite directions (pause on hover, focus or the Pause button;
 * still for visitors who turn off motion).
 *
 * Args: group (travel|funding), inset (bool: inside page content).
 *
 * @package TaxiPeninsula
 */

$group = 'funding' === ( $args['group'] ?? '' ) ? 'funding' : 'travel';
$items = tp_partner_items( $group );
if ( ! $items ) {
	return;
}
$text  = tp_partner_section_text( $group );
$stats = tp_partner_stats( $group );
$id    = 'pp-' . $group . '-' . wp_unique_id();
$kinds = tp_partner_kinds();
$icons = array(
	'building' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>',
	'car'      => '<path d="M5 17h14l-1.5-6h-11zM7 17v2M17 17v2M6.5 11 8 6h8l1.5 5"/>',
	'shield'   => '<path d="M9 12l2 2 4-4M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"/>',
	'people'   => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6M14.5 14.5c2.8-.6 6.5 1 6.5 5.5"/>',
);
// "[words]" in the heading are highlighted.
$title = preg_replace( '/\[([^\]]+)\]/', '<mark>$1</mark>', esc_html( $text['title'] ) );

// Two rows when there are enough logos; each row repeated so it fills wide screens.
$rows = count( $items ) >= 6 ? array( array(), array() ) : array( array() );
foreach ( $items as $i => $item ) {
	$rows[ $i % count( $rows ) ][] = $item;
}
$moving = count( $items ) >= 4;

$tile = static function ( $item, $hidden ) use ( $kinds ) {
	$attrs = $hidden ? ' tabindex="-1" aria-hidden="true"' : '';
	$tag   = $item['url'] ? 'a' : 'span';
	$href  = $item['url'] ? ' href="' . esc_url( $item['url'] ) . '"' . ( 0 === strpos( $item['url'], home_url() ) ? '' : ' target="_blank" rel="noopener"' ) : '';
	if ( $item['logo'] ) {
		$img = wp_get_attachment_image( $item['logo'], 'medium', false, array( 'class' => 'pp-tile__img', 'alt' => $item['name'], 'loading' => 'eager', 'fetchpriority' => 'low', 'decoding' => 'async' ) );
		return '<' . $tag . ' class="pp-tile"' . $href . $attrs . ( $hidden || ! $item['url'] ? '' : ' title="' . esc_attr( $item['name'] ) . '"' ) . '>' . $img . '</' . $tag . '>';
	}
	$kind = $kinds[ $item['kind'] ] ?? '';
	return '<' . $tag . ' class="pp-tile pp-tile--text"' . $href . $attrs . '><b>' . esc_html( $item['name'] ) . '</b>' . ( $kind ? '<small>' . esc_html( $kind ) . '</small>' : '' ) . '</' . $tag . '>';
};
?>
<section class="pp pp--<?php echo esc_attr( $group ); ?><?php echo empty( $args['inset'] ) ? '' : ' pp--inset'; ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>" data-pp>
	<div class="pp__in">
		<div class="pp__copy">
			<span class="pp__chip"><?php echo esc_html( $text['chip'] ); ?></span>
			<h2 id="<?php echo esc_attr( $id ); ?>" class="pp__title"><?php echo wp_kses( $title, array( 'mark' => array() ) ); ?></h2>
			<?php if ( $text['intro'] ) : ?>
				<p class="pp__lead"><?php echo esc_html( $text['intro'] ); ?></p>
			<?php endif; ?>
			<?php if ( $stats ) : ?>
				<ul class="pp__stats">
					<?php foreach ( $stats as $s ) : ?>
						<li class="pp__stat">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo $icons[ $s[2] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed icons. ?></svg>
							<strong data-count="<?php echo esc_attr( $s[0] ); ?>"><?php echo esc_html( number_format_i18n( $s[0] ) ); ?></strong>
							<span><?php echo esc_html( $s[1] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="pp__actions">
				<a class="btn btn--accent pp__btn" href="<?php echo esc_url( $text['btn'][1] ); ?>"><?php echo esc_html( $text['btn'][0] ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
				<?php if ( $text['more'][1] ) : ?>
					<a class="pp__more" href="<?php echo esc_url( $text['more'][1] ); ?>"><?php echo esc_html( $text['more'][0] ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<div class="pp__rows<?php echo $moving ? '' : ' pp__rows--still'; ?>">
			<?php foreach ( $rows as $r => $row ) : ?>
				<?php
				$fill = $row;
				while ( $moving && count( $fill ) < 8 ) {
					$fill = array_merge( $fill, $row );
				}
				$speed = max( 30, count( $fill ) * 6 );
				?>
				<div class="pp__marquee">
					<div class="pp__track<?php echo $r ? ' pp__track--rev' : ''; ?>" style="--pp-speed: <?php echo esc_attr( $speed ); ?>s">
						<ul class="pp__list">
							<?php foreach ( $fill as $n => $item ) : ?>
								<li><?php echo $tile( $item, $n >= count( $row ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $tile. ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $moving ) : ?>
							<ul class="pp__list" aria-hidden="true">
								<?php foreach ( $fill as $item ) : ?>
									<li><?php echo $tile( $item, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $tile. ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			<?php if ( $moving ) : ?>
				<button class="pp__pause" type="button" data-pp-pause aria-pressed="false">
					<svg viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path class="pp__ico-pause" d="M2 1h3v10H2zM7 1h3v10H7z"/><path class="pp__ico-play" d="M3 1l8 5-8 5z"/></svg>
					<span class="pp__pause-label" data-play="<?php esc_attr_e( 'Play logos', 'taxi-peninsula' ); ?>" data-pause="<?php esc_attr_e( 'Pause logos', 'taxi-peninsula' ); ?>"><?php esc_html_e( 'Pause logos', 'taxi-peninsula' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</div>
</section>
