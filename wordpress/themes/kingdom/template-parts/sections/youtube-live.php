<?php
/**
 * YouTube live stream.
 *
 * With the church's channel set, the section shows the live stream as soon as it starts,
 * the waiting room when a stream is scheduled, and the replay video otherwise. Open pages
 * check /wp-json/kingdom/v1/live every minute and switch by themselves.
 *
 * @package Kingdom
 */

$kingdom_live = kingdom_youtube_live();
if ( ! $kingdom_live || ( ! $kingdom_live['embed'] && 'channel' !== $kingdom_live['mode'] ) ) {
	return;
}
$kingdom_title = get_theme_mod( 'kingdom_ytlive_title', __( 'Worship with us live', 'kingdom' ) );
?>
<section class="k-section k-section--dark k-ytlive is-<?php echo esc_attr( $kingdom_live['state'] ); ?>" id="live"
	<?php if ( 'channel' === $kingdom_live['mode'] ) : ?>
		data-ytlive="<?php echo esc_url( rest_url( 'kingdom/v1/live' ) ); ?>" data-state="<?php echo esc_attr( $kingdom_live['state'] ); ?>" data-video="<?php echo esc_attr( $kingdom_live['video_id'] ); ?>" data-starts="<?php echo (int) $kingdom_live['starts_at']; ?>"
	<?php endif; ?>>
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Church online', 'kingdom' ), $kingdom_title, __( 'Join the service from anywhere in the world.', 'kingdom' ) ); ?>
		<?php if ( 'channel' === $kingdom_live['mode'] ) : ?>
			<p class="k-ytlive__status" aria-live="polite"><span class="k-ytlive__badge" data-ytlive-label><?php echo esc_html( $kingdom_live['label'] ); ?></span><span class="k-ytlive__countdown" data-ytlive-countdown hidden></span></p>
		<?php endif; ?>
		<div class="k-ytlive__grid<?php echo $kingdom_live['chat'] ? ' has-chat' : ''; ?>" data-reveal>
			<div class="k-ytlive__player" data-ytlive-player>
				<?php if ( $kingdom_live['embed'] ) : ?>
					<iframe src="<?php echo esc_url( $kingdom_live['embed'] ); ?>" title="<?php echo esc_attr( $kingdom_live['title'] ? $kingdom_live['title'] : $kingdom_title ); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe>
				<?php else : ?>
					<div class="k-ytlive__idle"><?php echo kingdom_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p><?php esc_html_e( 'The stream will appear here as soon as we go live.', 'kingdom' ); ?></p></div>
				<?php endif; ?>
			</div>
			<div class="k-ytlive__chat" data-ytlive-chat<?php echo $kingdom_live['chat'] ? '' : ' hidden'; ?>>
				<?php if ( $kingdom_live['chat'] ) : ?>
					<iframe src="<?php echo esc_url( $kingdom_live['chat'] ); ?>" title="<?php esc_attr_e( 'Live chat', 'kingdom' ); ?>" loading="lazy"></iframe>
				<?php endif; ?>
			</div>
		</div>
		<p class="k-ytlive__actions">
			<a class="k-btn k-btn--light" data-ytlive-watch href="<?php echo esc_url( $kingdom_live['watch'] ); ?>" target="_blank" rel="noopener"><?php echo kingdom_icon( 'youtube' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Watch on YouTube', 'kingdom' ); ?></a>
			<?php if ( get_theme_mod( 'kingdom_youtube' ) ) : ?>
				<a class="k-btn k-btn--glass" href="<?php echo esc_url( add_query_arg( 'sub_confirmation', 1, get_theme_mod( 'kingdom_youtube' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Subscribe', 'kingdom' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</section>
