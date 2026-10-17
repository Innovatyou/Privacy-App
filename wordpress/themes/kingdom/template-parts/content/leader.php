<?php
/**
 * Leader card.
 *
 * @package Kingdom
 */

?>
<article <?php post_class( 'k-leader' ); ?> data-reveal>
	<a class="k-leader__photo" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'kingdom-portrait', array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span class="k-leader__initials"><?php echo esc_html( mb_substr( get_the_title(), 0, 1 ) ); ?></span>
		<?php endif; ?>
	</a>
	<h3 class="k-leader__name"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	<p class="k-leader__role"><?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_role', true ) ); ?></p>
</article>
