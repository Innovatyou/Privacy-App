<?php
/**
 * Videos — the church's own YouTube/Vimeo list (Customizer or Church Admin → Website), or the latest sermon videos.
 *
 * @package Kingdom
 */

$kingdom_videos = kingdom_videos( 4 );
if ( ! $kingdom_videos ) {
	return;
}
$kingdom_first = array_shift( $kingdom_videos );
$kingdom_play  = function ( $v, $class ) {
	$attr = 'youtube' === $v['type'] ? 'data-video="' . esc_attr( $v['id'] ) . '"' : 'data-video-src="' . esc_attr( 'https://player.vimeo.com/video/' . $v['id'] . '?autoplay=1' ) . '"';
	return '<button type="button" class="k-video-thumb ' . esc_attr( $class ) . '" ' . $attr . ' style="--k-img:url(' . esc_url( $v['thumb'] ) . ')" aria-label="' . esc_attr( sprintf( /* translators: %s: video title */ __( 'Play: %s', 'kingdom' ), $v['title'] ? $v['title'] : __( 'video', 'kingdom' ) ) ) . '"><span class="k-play">' . kingdom_icon( 'play' ) . '</span></button>';
};
?>
<section class="k-section k-videos" id="videos">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Videos', 'kingdom' ), get_theme_mod( 'kingdom_videos_title', __( 'Watch & be blessed', 'kingdom' ) ) ); ?>
		<div class="k-videos__grid<?php echo $kingdom_videos ? '' : ' is-single'; ?>">
			<figure class="k-videos__main" data-reveal>
				<?php echo $kingdom_play( $kingdom_first, 'k-videos__thumb--main' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $kingdom_first['title'] ) : ?><figcaption><?php echo esc_html( $kingdom_first['title'] ); ?></figcaption><?php endif; ?>
			</figure>
			<?php if ( $kingdom_videos ) : ?>
				<ul class="k-videos__list">
					<?php foreach ( $kingdom_videos as $kingdom_v ) : ?>
						<li data-reveal><?php echo $kingdom_play( $kingdom_v, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $kingdom_v['title'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
