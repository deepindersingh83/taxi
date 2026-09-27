<?php
/**
 * Template Name: Policy / legal page
 *
 * For terms, cancellation, privacy and accessibility statements: a table of
 * contents built from the H2 headings, the last-updated date and a print button.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	$content  = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	$toc      = array();
	$content  = preg_replace_callback(
		'#<h2([^>]*)>(.*?)</h2>#is',
		static function ( $m ) use ( &$toc ) {
			$text = wp_strip_all_tags( $m[2] );
			if ( preg_match( '/\sid=["\']([^"\']+)["\']/', $m[1], $idm ) ) {
				$id = $idm[1];
				$attrs = $m[1];
			} else {
				$id    = 'section-' . sanitize_title( $text );
				$attrs = $m[1] . ' id="' . esc_attr( $id ) . '"';
			}
			$toc[] = array( $id, $text );
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$content
	);

	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'title' => get_the_title(),
			/* translators: %s: date */
			'meta'  => esc_html( sprintf( __( 'Last updated %s', 'taxi-peninsula' ), get_the_modified_date() ) ),
		)
	);
	?>
	<div class="container section policy">
		<?php if ( count( $toc ) > 2 ) : ?>
			<nav class="policy__toc" aria-labelledby="toc-title">
				<h2 id="toc-title" class="policy__toc-title"><?php esc_html_e( 'On this page', 'taxi-peninsula' ); ?></h2>
				<ol>
					<?php foreach ( $toc as $item ) : ?>
						<li><a href="#<?php echo esc_attr( $item[0] ); ?>"><?php echo esc_html( $item[1] ); ?></a></li>
					<?php endforeach; ?>
				</ol>
				<button type="button" class="btn btn--ghost policy__print" onclick="window.print()"><?php esc_html_e( 'Print this page', 'taxi-peninsula' ); ?></button>
			</nav>
		<?php endif; ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'prose entry-content policy__body' ); ?>>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content-filtered post content. ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
