<?php
/**
 * Latest sermons.
 *
 * Args: count, layout (grid|featured), title.
 *
 * @package Kingdom
 */

if ( ! post_type_exists( 'kcs_sermon' ) ) {
	return;
}
$kingdom_args = wp_parse_args( $args ?? array(), array( 'count' => 3, 'layout' => 'grid', 'title' => get_theme_mod( 'kingdom_sermons_title', __( 'Latest messages', 'kingdom' ) ) ) );
$kingdom_q    = new WP_Query( array( 'post_type' => 'kcs_sermon', 'posts_per_page' => (int) $kingdom_args['count'] + ( 'featured' === $kingdom_args['layout'] ? 1 : 0 ), 'no_found_rows' => true ) );
if ( ! $kingdom_q->have_posts() ) {
	return;
}
?>
<section class="k-section k-section--alt">
	<div class="k-container">
		<div class="k-section__bar">
			<?php kingdom_section_heading( __( 'Sermons', 'kingdom' ), $kingdom_args['title'], '', 'left' ); ?>
			<a class="k-link" href="<?php echo esc_url( get_post_type_archive_link( 'kcs_sermon' ) ); ?>"><?php esc_html_e( 'All sermons', 'kingdom' ); ?> <?php echo kingdom_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
		<?php if ( 'featured' === $kingdom_args['layout'] ) : ?>
			<?php
			$kingdom_q->the_post();
			$kingdom_yt = kingdom_youtube_id( get_post_meta( get_the_ID(), '_kcs_video_url', true ) );
			?>
			<article class="k-featured-sermon" data-reveal>
				<div class="k-featured-sermon__media">
					<?php if ( $kingdom_yt ) : ?>
						<button type="button" class="k-video-thumb" data-video="<?php echo esc_attr( $kingdom_yt ); ?>" style="--k-img:url(<?php echo esc_url( kingdom_sermon_thumb() ); ?>)"><span class="k-play"><?php echo kingdom_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
					<?php else : ?>
						<a class="k-video-thumb" href="<?php the_permalink(); ?>" style="--k-img:url(<?php echo esc_url( kingdom_sermon_thumb() ); ?>)"></a>
					<?php endif; ?>
				</div>
				<div class="k-featured-sermon__body">
					<span class="k-eyebrow"><?php esc_html_e( 'Latest message', 'kingdom' ); ?></span>
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					<div class="k-btns">
						<a class="k-btn" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Watch now', 'kingdom' ); ?></a>
						<?php if ( get_post_meta( get_the_ID(), '_kcs_audio_url', true ) ) : ?><a class="k-btn k-btn--outline" href="<?php the_permalink(); ?>#listen"><?php echo kingdom_icon( 'headphones' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Listen', 'kingdom' ); ?></a><?php endif; ?>
					</div>
				</div>
			</article>
		<?php endif; ?>
		<div class="k-grid k-grid--3">
			<?php
			while ( $kingdom_q->have_posts() ) :
				$kingdom_q->the_post();
				get_template_part( 'template-parts/content/sermon' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
