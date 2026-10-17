<?php
/**
 * Event card.
 *
 * @package Kingdom
 */

?>
<article <?php post_class( 'k-card k-event-card' ); ?> data-reveal>
	<a class="k-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?><?php the_post_thumbnail( 'kingdom-card', array( 'loading' => 'lazy' ) ); ?><?php endif; ?>
		<?php kingdom_event_badge(); ?>
	</a>
	<div class="k-card__body">
		<h3 class="k-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<ul class="k-event-meta">
			<li><?php echo kingdom_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( kingdom_event_date() ); ?></li>
			<?php if ( get_post_meta( get_the_ID(), '_kcs_location', true ) ) : ?>
				<li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_location', true ) ); ?></li>
			<?php endif; ?>
			<?php if ( get_post_meta( get_the_ID(), '_kcs_recurrence', true ) ) : ?>
				<li><?php echo kingdom_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_recurrence', true ) ); ?></li>
			<?php endif; ?>
		</ul>
	</div>
</article>
