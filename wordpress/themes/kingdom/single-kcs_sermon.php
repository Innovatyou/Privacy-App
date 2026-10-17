<?php
/**
 * Single sermon: video, audio player, notes, scripture, series navigation.
 *
 * @package Kingdom
 */

get_header();
while ( have_posts() ) :
	the_post();
	$kingdom_id       = get_the_ID();
	$kingdom_video    = get_post_meta( $kingdom_id, '_kcs_video_url', true );
	$kingdom_audio    = get_post_meta( $kingdom_id, '_kcs_audio_url', true );
	$kingdom_notes    = get_post_meta( $kingdom_id, '_kcs_notes_url', true );
	$kingdom_script   = get_post_meta( $kingdom_id, '_kcs_scripture', true );
	$kingdom_date     = get_post_meta( $kingdom_id, '_kcs_sermon_date', true );
	$kingdom_speakers = get_the_terms( $kingdom_id, 'kcs_speaker' );
	$kingdom_series   = get_the_terms( $kingdom_id, 'kcs_series' );
	$kingdom_embed    = $kingdom_video ? wp_oembed_get( $kingdom_video ) : '';
	kingdom_page_header();
	?>
	<article <?php post_class( 'k-section k-sermon' ); ?>>
		<div class="k-container k-sermon__grid">
			<div class="k-sermon__main">
				<?php if ( $kingdom_embed ) : ?>
					<div class="kcs-embed"><?php echo $kingdom_embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core oEmbed output. ?></div>
				<?php elseif ( has_post_thumbnail() ) : ?>
					<figure class="k-entry__media"><?php the_post_thumbnail( 'large' ); ?></figure>
				<?php endif; ?>

				<?php if ( $kingdom_audio ) : ?>
					<div class="k-audio" id="listen">
						<span><?php echo kingdom_icon( 'headphones' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Listen', 'kingdom' ); ?></span>
						<audio controls preload="none" src="<?php echo esc_url( $kingdom_audio ); ?>"></audio>
						<label class="k-audio__speed"><?php esc_html_e( 'Speed', 'kingdom' ); ?>
							<select data-audio-speed><option value="1">1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select>
						</label>
					</div>
				<?php endif; ?>

				<div class="k-entry__content"><?php the_content(); ?></div>
				<?php kingdom_share_links(); ?>
			</div>

			<aside class="k-sermon__aside">
				<div class="k-panel">
					<ul class="k-facts">
						<?php if ( $kingdom_speakers && ! is_wp_error( $kingdom_speakers ) ) : ?>
							<li><?php echo kingdom_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Speaker', 'kingdom' ); ?></small><a href="<?php echo esc_url( get_term_link( $kingdom_speakers[0] ) ); ?>"><?php echo esc_html( $kingdom_speakers[0]->name ); ?></a></span></li>
						<?php endif; ?>
						<li><?php echo kingdom_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Date', 'kingdom' ); ?></small><?php echo esc_html( $kingdom_date ? date_i18n( get_option( 'date_format' ), strtotime( $kingdom_date ) ) : get_the_date() ); ?></span></li>
						<?php if ( $kingdom_script ) : ?>
							<li><?php echo kingdom_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Scripture', 'kingdom' ); ?></small><a href="<?php echo esc_url( 'https://www.biblegateway.com/passage/?search=' . rawurlencode( $kingdom_script ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $kingdom_script ); ?></a></span></li>
						<?php endif; ?>
						<?php if ( $kingdom_series && ! is_wp_error( $kingdom_series ) ) : ?>
							<li><?php echo kingdom_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Series', 'kingdom' ); ?></small><a href="<?php echo esc_url( get_term_link( $kingdom_series[0] ) ); ?>"><?php echo esc_html( $kingdom_series[0]->name ); ?></a></span></li>
						<?php endif; ?>
					</ul>
					<div class="k-btns k-btns--stack">
						<?php if ( $kingdom_audio ) : ?><a class="k-btn k-btn--outline k-btn--block" href="<?php echo esc_url( $kingdom_audio ); ?>" download><?php echo kingdom_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Download audio', 'kingdom' ); ?></a><?php endif; ?>
						<?php if ( $kingdom_notes ) : ?><a class="k-btn k-btn--outline k-btn--block" href="<?php echo esc_url( $kingdom_notes ); ?>" target="_blank" rel="noopener"><?php echo kingdom_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Sermon notes', 'kingdom' ); ?></a><?php endif; ?>
						<?php if ( kingdom_plugin_active() ) : ?><a class="k-btn k-btn--accent k-btn--block" href="<?php echo esc_url( kcs_page_url( 'give' ) ); ?>"><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Give', 'kingdom' ); ?></a><?php endif; ?>
					</div>
				</div>

				<?php
				if ( $kingdom_series && ! is_wp_error( $kingdom_series ) ) :
					$kingdom_more = new WP_Query(
						array(
							'post_type'      => 'kcs_sermon',
							'posts_per_page' => 5,
							'post__not_in'   => array( $kingdom_id ),
							'no_found_rows'  => true,
							'tax_query'      => array( array( 'taxonomy' => 'kcs_series', 'terms' => $kingdom_series[0]->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						)
					);
					if ( $kingdom_more->have_posts() ) :
						?>
						<div class="k-panel">
							<h3><?php esc_html_e( 'More in this series', 'kingdom' ); ?></h3>
							<ul class="k-mini-list">
								<?php
								while ( $kingdom_more->have_posts() ) :
									$kingdom_more->the_post();
									?>
									<li><a href="<?php the_permalink(); ?>"><?php if ( kingdom_sermon_thumb() ) : ?><img src="<?php echo esc_url( kingdom_sermon_thumb() ); ?>" alt="" loading="lazy"><?php endif; ?><span><?php the_title(); ?></span></a></li>
								<?php endwhile; ?>
							</ul>
						</div>
						<?php
					endif;
					wp_reset_postdata();
				endif;
				?>
			</aside>
		</div>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="k-container k-container--narrow">';
		comments_template();
		echo '</div>';
	}
endwhile;
get_footer();
