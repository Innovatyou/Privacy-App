<?php
/**
 * Ministry card.
 *
 * @package Kingdom
 */

$kingdom_icon = get_post_meta( get_the_ID(), '_kcs_icon', true );
?>
<article <?php post_class( 'k-card k-ministry-card' ); ?> data-reveal data-tilt>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="k-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'kingdom-card', array( 'loading' => 'lazy' ) ); ?></a>
	<?php endif; ?>
	<div class="k-card__body">
		<?php if ( $kingdom_icon && ! has_post_thumbnail() ) : ?><span class="k-ministry-card__icon"><?php echo esc_html( $kingdom_icon ); ?></span><?php endif; ?>
		<h3 class="k-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="k-card__text"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php if ( get_post_meta( get_the_ID(), '_kcs_meeting_time', true ) ) : ?>
			<p class="k-meta"><span><?php echo kingdom_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_meeting_time', true ) ); ?></span></p>
		<?php endif; ?>
	</div>
</article>
