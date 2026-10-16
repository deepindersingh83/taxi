<?php
/**
 * Glossary list (/glossary/), grouped A–Z. See inc/glossary.php.
 *
 * @package TaxiPeninsula
 */

get_header();
get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => esc_html__( 'Accessible transport glossary', 'taxi-peninsula' ),
		'lead'  => esc_html__( 'Plain-English answers to the words you will hear when arranging accessible transport in Victoria.', 'taxi-peninsula' ),
	)
);

$tp_groups = array();
while ( have_posts() ) {
	the_post();
	$tp_letter                  = strtoupper( mb_substr( get_the_title(), 0, 1 ) );
	$tp_groups[ $tp_letter ][] = get_post();
}
ksort( $tp_groups );
?>
<div class="container section glossary">
	<?php if ( ! $tp_groups ) : ?>
		<p><?php esc_html_e( 'Terms are coming soon.', 'taxi-peninsula' ); ?></p>
	<?php else : ?>
		<nav class="glossary__letters" aria-label="<?php esc_attr_e( 'Jump to letter', 'taxi-peninsula' ); ?>">
			<?php foreach ( array_keys( $tp_groups ) as $tp_letter ) : ?>
				<a href="#letter-<?php echo esc_attr( sanitize_title( $tp_letter ) ); ?>"><?php echo esc_html( $tp_letter ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php foreach ( $tp_groups as $tp_letter => $tp_terms ) : ?>
			<section class="glossary__group" id="letter-<?php echo esc_attr( sanitize_title( $tp_letter ) ); ?>">
				<h2 class="glossary__letter"><?php echo esc_html( $tp_letter ); ?></h2>
				<dl class="glossary__list">
					<?php foreach ( $tp_terms as $tp_t ) : ?>
						<div class="glossary__item">
							<dt><a href="<?php echo esc_url( get_permalink( $tp_t ) ); ?>"><?php echo esc_html( get_the_title( $tp_t ) ); ?></a></dt>
							<dd><?php echo esc_html( tp_term_definition( $tp_t->ID ) ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php
get_template_part( 'template-parts/cta' );
get_footer();
