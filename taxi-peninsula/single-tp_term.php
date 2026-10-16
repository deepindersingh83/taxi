<?php
/**
 * Glossary term (/glossary/{term}/). See inc/glossary.php.
 *
 * @package TaxiPeninsula
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-header',
		null,
		array(
			/* translators: %s: term */
			'title' => esc_html( sprintf( __( 'What is %s?', 'taxi-peninsula' ), get_the_title() ) ),
		)
	);
	$tp_others = get_posts( array( 'post_type' => 'tp_term', 'numberposts' => 30, 'post__not_in' => array( get_the_ID() ), 'orderby' => 'title', 'order' => 'ASC' ) );
	?>
	<div class="container layout-sidebar section glossary-term">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'layout-sidebar__main' ); ?>>
			<div class="quick-answer" role="note">
				<p class="quick-answer__label"><?php esc_html_e( 'In short', 'taxi-peninsula' ); ?></p>
				<p class="quick-answer__text"><?php echo esc_html( tp_term_definition( get_the_ID() ) ); ?></p>
			</div>
			<div class="prose entry-content">
				<?php the_content(); ?>
			</div>
			<?php echo tp_updated_line( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<p class="cta-inline">
				<a class="btn btn--accent btn--lg" href="<?php echo esc_url( tp_booking_page_url() ); ?>"><?php esc_html_e( 'Book a wheelchair taxi', 'taxi-peninsula' ); ?> <?php tp_the_icon( 'arrow' ); ?></a>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( get_post_type_archive_link( 'tp_term' ) ); ?>"><?php esc_html_e( 'All glossary terms', 'taxi-peninsula' ); ?></a>
			</p>
		</article>
		<?php if ( $tp_others ) : ?>
			<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'More terms', 'taxi-peninsula' ); ?>">
				<section class="widget">
					<h2 class="widget-title"><?php esc_html_e( 'More terms', 'taxi-peninsula' ); ?></h2>
					<ul class="link-list">
						<?php foreach ( $tp_others as $tp_o ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $tp_o ) ); ?>"><?php echo esc_html( get_the_title( $tp_o ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			</aside>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_template_part( 'template-parts/cta' );
get_footer();
