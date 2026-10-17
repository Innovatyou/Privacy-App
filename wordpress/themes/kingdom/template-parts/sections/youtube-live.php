<?php
/**
 * YouTube live stream — needs only a YouTube video ID or link (or a channel ID for "always live").
 *
 * @package Kingdom
 */

$kingdom_live = kingdom_youtube_live();
if ( ! $kingdom_live ) {
	return;
}
$kingdom_chat = get_theme_mod( 'kingdom_ytlive_chat', false ) && $kingdom_live['id'];
$kingdom_host = wp_parse_url( home_url(), PHP_URL_HOST );
?>
<section class="k-section k-section--dark k-ytlive" id="live">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Church online', 'kingdom' ), get_theme_mod( 'kingdom_ytlive_title', __( 'Worship with us live', 'kingdom' ) ), __( 'Join the service from anywhere in the world.', 'kingdom' ) ); ?>
		<div class="k-ytlive__grid<?php echo $kingdom_chat ? ' has-chat' : ''; ?>" data-reveal>
			<div class="k-ytlive__player">
				<iframe src="<?php echo esc_url( $kingdom_live['src'] ); ?>" title="<?php echo esc_attr( get_theme_mod( 'kingdom_ytlive_title', __( 'Worship with us live', 'kingdom' ) ) ); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe>
			</div>
			<?php if ( $kingdom_chat ) : ?>
				<div class="k-ytlive__chat">
					<iframe src="<?php echo esc_url( 'https://www.youtube.com/live_chat?v=' . rawurlencode( $kingdom_live['id'] ) . '&embed_domain=' . rawurlencode( $kingdom_host ) ); ?>" title="<?php esc_attr_e( 'Live chat', 'kingdom' ); ?>" loading="lazy"></iframe>
				</div>
			<?php endif; ?>
		</div>
		<p class="k-ytlive__actions">
			<span class="k-ytlive__dot" aria-hidden="true"></span>
			<a class="k-btn k-btn--light" href="<?php echo esc_url( $kingdom_live['watch'] ); ?>" target="_blank" rel="noopener"><?php echo kingdom_icon( 'youtube' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Watch on YouTube', 'kingdom' ); ?></a>
			<?php if ( get_theme_mod( 'kingdom_youtube' ) ) : ?>
				<a class="k-btn k-btn--glass" href="<?php echo esc_url( add_query_arg( 'sub_confirmation', 1, get_theme_mod( 'kingdom_youtube' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Subscribe', 'kingdom' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</section>
