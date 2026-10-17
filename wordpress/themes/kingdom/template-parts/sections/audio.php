<?php
/**
 * Audio — MP3 messages (the church's own list or the latest sermon recordings) and an optional
 * Spotify / SoundCloud / Apple Podcasts player.
 *
 * @package Kingdom
 */

$kingdom_tracks  = kingdom_audio_tracks( 4 );
$kingdom_podcast = kingdom_podcast_embed( get_theme_mod( 'kingdom_audio_embed', '' ) );
if ( ! $kingdom_tracks && ! $kingdom_podcast ) {
	return;
}
?>
<section class="k-section k-section--alt k-audio-section" id="audio">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Audio', 'kingdom' ), get_theme_mod( 'kingdom_audio_title', __( 'Listen to the Word', 'kingdom' ) ), __( 'Messages to listen to at home, at work or on the road.', 'kingdom' ) ); ?>
		<div class="k-audio-section__grid<?php echo $kingdom_podcast && $kingdom_tracks ? ' has-podcast' : ''; ?>">
			<?php if ( $kingdom_tracks ) : ?>
				<ol class="k-tracks">
					<?php foreach ( $kingdom_tracks as $kingdom_i => $kingdom_t ) : ?>
						<li class="k-track" data-reveal>
							<span class="k-track__no" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $kingdom_i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
							<div class="k-track__body">
								<strong><?php echo $kingdom_t['url'] ? '<a href="' . esc_url( $kingdom_t['url'] ) . '">' . esc_html( $kingdom_t['title'] ) . '</a>' : esc_html( $kingdom_t['title'] ); ?></strong>
								<?php if ( $kingdom_t['meta'] ) : ?><small><?php echo esc_html( $kingdom_t['meta'] ); ?></small><?php endif; ?>
								<audio controls preload="none" src="<?php echo esc_url( $kingdom_t['src'] ); ?>"></audio>
							</div>
							<a class="k-icon-btn k-track__dl" href="<?php echo esc_url( $kingdom_t['src'] ); ?>" download aria-label="<?php echo esc_attr( sprintf( /* translators: %s: title */ __( 'Download %s', 'kingdom' ), $kingdom_t['title'] ) ); ?>"><?php echo kingdom_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
			<?php if ( $kingdom_podcast ) : ?>
				<div class="k-podcast" data-reveal>
					<iframe src="<?php echo esc_url( $kingdom_podcast['src'] ); ?>" height="<?php echo (int) $kingdom_podcast['height']; ?>" title="<?php esc_attr_e( 'Podcast player', 'kingdom' ); ?>" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
