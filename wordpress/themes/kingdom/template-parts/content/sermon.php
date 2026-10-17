<?php
/**
 * Sermon card.
 *
 * @package Kingdom
 */

$kingdom_thumb    = kingdom_sermon_thumb();
$kingdom_speakers = get_the_terms( get_the_ID(), 'kcs_speaker' );
$kingdom_series   = get_the_terms( get_the_ID(), 'kcs_series' );
$kingdom_date     = get_post_meta( get_the_ID(), '_kcs_sermon_date', true );
?>
<article <?php post_class( 'k-card k-sermon-card' ); ?> data-reveal data-tilt>
	<a class="k-card__media k-card__media--video" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $kingdom_thumb ) : ?><img src="<?php echo esc_url( $kingdom_thumb ); ?>" alt="" loading="lazy"><?php endif; ?>
		<span class="k-play"><?php echo kingdom_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php if ( get_post_meta( get_the_ID(), '_kcs_duration', true ) ) : ?><span class="k-duration"><?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_duration', true ) ); ?></span><?php endif; ?>
	</a>
	<div class="k-card__body">
		<?php if ( $kingdom_series && ! is_wp_error( $kingdom_series ) ) : ?><span class="k-eyebrow"><?php echo esc_html( $kingdom_series[0]->name ); ?></span><?php endif; ?>
		<h3 class="k-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<div class="k-meta">
			<?php if ( $kingdom_speakers && ! is_wp_error( $kingdom_speakers ) ) : ?><span><?php echo esc_html( $kingdom_speakers[0]->name ); ?></span><?php endif; ?>
			<span><?php echo esc_html( $kingdom_date ? date_i18n( get_option( 'date_format' ), strtotime( $kingdom_date ) ) : get_the_date() ); ?></span>
			<?php if ( get_post_meta( get_the_ID(), '_kcs_scripture', true ) ) : ?><span><?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_scripture', true ) ); ?></span><?php endif; ?>
		</div>
	</div>
</article>
