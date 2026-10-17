<?php
/**
 * Post card.
 *
 * @package Kingdom
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'k-card' ); ?> data-reveal>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="k-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'kingdom-card', array( 'loading' => 'lazy' ) ); ?></a>
	<?php endif; ?>
	<div class="k-card__body">
		<?php if ( 'post' === get_post_type() ) : ?>
			<span class="k-eyebrow"><?php echo esc_html( get_the_date() ); ?></span>
		<?php endif; ?>
		<h3 class="k-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="k-card__text"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<a class="k-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'kingdom' ); ?> <?php echo kingdom_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</div>
</article>
