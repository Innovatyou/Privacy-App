<?php
/**
 * The current page's editor content (when not empty).
 *
 * Args: narrow (bool), class.
 *
 * @package Kingdom
 */

$kingdom_args = wp_parse_args( $args ?? array(), array( 'narrow' => true, 'class' => '' ) );
if ( ! is_singular() ) {
	return;
}
$kingdom_post = get_post();
if ( ! $kingdom_post || '' === trim( $kingdom_post->post_content ) ) {
	return;
}
?>
<section class="k-section <?php echo esc_attr( $kingdom_args['class'] ); ?>">
	<div class="k-container<?php echo $kingdom_args['narrow'] ? ' k-container--narrow' : ''; ?>">
		<div class="k-entry__content">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			rewind_posts();
			?>
		</div>
	</div>
</section>
