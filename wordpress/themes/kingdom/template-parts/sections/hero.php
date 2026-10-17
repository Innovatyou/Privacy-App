<?php
/**
 * Hero section.
 *
 * Args: variant (classic|video|split|minimal|live|mega), title, text, eyebrow.
 *
 * @package Kingdom
 */

$kingdom_args    = wp_parse_args( $args ?? array(), array( 'variant' => 'classic' ) );
$kingdom_variant = $kingdom_args['variant'];
$kingdom_eyebrow = $kingdom_args['eyebrow'] ?? get_theme_mod( 'kingdom_hero_eyebrow', __( 'Welcome home', 'kingdom' ) );
$kingdom_title   = $kingdom_args['title'] ?? get_theme_mod( 'kingdom_hero_title', __( 'A place to belong, believe and become', 'kingdom' ) );
$kingdom_text    = $kingdom_args['text'] ?? get_theme_mod( 'kingdom_hero_text', __( 'Join us this Sunday — in person or online — and experience the love of Jesus with a family that cares.', 'kingdom' ) );
$kingdom_image   = get_theme_mod( 'kingdom_hero_image' );
if ( ! $kingdom_image && is_page() && has_post_thumbnail() ) {
	$kingdom_image = get_the_post_thumbnail_url( null, 'kingdom-hero' );
}
if ( ! $kingdom_image ) {
	$kingdom_image = kingdom_image( 'kingdom_hero_image' );
}
$kingdom_video   = get_theme_mod( 'kingdom_hero_video' );
$kingdom_yt      = kingdom_youtube_id( get_theme_mod( 'kingdom_hero_youtube' ) );
$kingdom_overlay = min( 90, absint( get_theme_mod( 'kingdom_hero_overlay', 55 ) ) ) / 100;
$kingdom_b1t     = get_theme_mod( 'kingdom_hero_btn1_text', __( 'Plan your visit', 'kingdom' ) );
$kingdom_b1u     = kingdom_url( 'kingdom_hero_btn1_url', 'template-plan-visit.php' );
$kingdom_b2t     = get_theme_mod( 'kingdom_hero_btn2_text', __( 'Watch online', 'kingdom' ) );
$kingdom_b2u     = kingdom_url( 'kingdom_hero_btn2_url', 'template-watch-live.php', kingdom_plugin_active() ? kcs_page_url( 'watch_live' ) : '' );
$kingdom_times   = kingdom_service_times();

$kingdom_buttons = function () use ( $kingdom_b1t, $kingdom_b1u, $kingdom_b2t, $kingdom_b2u, $kingdom_yt ) {
	echo '<div class="k-btns">';
	if ( $kingdom_b1t ) {
		echo '<a class="k-btn k-btn--accent k-btn--lg" href="' . esc_url( $kingdom_b1u ) . '">' . esc_html( $kingdom_b1t ) . '</a>';
	}
	if ( $kingdom_b2t ) {
		echo '<a class="k-btn k-btn--glass k-btn--lg" href="' . esc_url( $kingdom_b2u ) . '">' . kingdom_icon( 'play' ) . ' ' . esc_html( $kingdom_b2t ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( $kingdom_yt ) {
		echo '<button type="button" class="k-play-btn" data-video="' . esc_attr( $kingdom_yt ) . '" aria-label="' . esc_attr__( 'Play video', 'kingdom' ) . '">' . kingdom_icon( 'play' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div>';
};
?>

<?php if ( 'split' === $kingdom_variant ) : ?>
	<section class="k-hero k-hero--split">
		<div class="k-container k-hero__split">
			<div class="k-hero__content" data-reveal>
				<?php if ( $kingdom_eyebrow ) : ?><span class="k-eyebrow"><?php echo esc_html( $kingdom_eyebrow ); ?></span><?php endif; ?>
				<h1 class="k-hero__title"><?php echo esc_html( $kingdom_title ); ?></h1>
				<p class="k-hero__text"><?php echo esc_html( $kingdom_text ); ?></p>
				<?php $kingdom_buttons(); ?>
			</div>
			<div class="k-hero__visual" data-reveal>
				<div class="k-hero__image"<?php echo $kingdom_image ? ' style="--k-img:url(' . esc_url( $kingdom_image ) . ')"' : ''; ?>></div>
				<?php if ( $kingdom_times ) : ?>
					<div class="k-floating-card">
						<span class="k-eyebrow"><?php esc_html_e( 'Join us', 'kingdom' ); ?></span>
						<?php foreach ( array_slice( $kingdom_times, 0, 3 ) as $kingdom_s ) : ?>
							<p><strong><?php echo esc_html( $kingdom_s['name'] ); ?></strong><br><?php echo esc_html( $kingdom_s['day'] . ' · ' . $kingdom_s['time'] ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

<?php elseif ( 'minimal' === $kingdom_variant ) : ?>
	<section class="k-hero k-hero--minimal">
		<div class="k-container k-center" data-reveal>
			<?php if ( $kingdom_eyebrow ) : ?><span class="k-eyebrow"><?php echo esc_html( $kingdom_eyebrow ); ?></span><?php endif; ?>
			<h1 class="k-hero__title k-hero__title--xl"><?php echo esc_html( $kingdom_title ); ?></h1>
			<p class="k-hero__text"><?php echo esc_html( $kingdom_text ); ?></p>
			<?php $kingdom_buttons(); ?>
		</div>
	</section>

<?php elseif ( 'live' === $kingdom_variant ) : ?>
	<section class="k-hero k-hero--live">
		<div class="k-container">
			<div class="k-hero__live-head" data-reveal>
				<h1 class="k-hero__title"><?php echo esc_html( $kingdom_title ); ?></h1>
				<?php echo kingdom_shortcode( '[kcs_service_countdown]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php echo kingdom_shortcode( '[kcs_livestream chat="yes"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</section>

<?php else : ?>
	<section class="k-hero k-hero--<?php echo esc_attr( $kingdom_variant ); ?> k-hero--cover"<?php echo $kingdom_image ? ' style="--k-img:url(' . esc_url( $kingdom_image ) . ')"' : ''; ?>>
		<?php if ( 'video' === $kingdom_variant && $kingdom_video ) : ?>
			<video class="k-hero__video" autoplay muted loop playsinline preload="metadata"<?php echo $kingdom_image ? ' poster="' . esc_url( $kingdom_image ) . '"' : ''; ?>><source src="<?php echo esc_url( $kingdom_video ); ?>" type="video/mp4"></video>
		<?php endif; ?>
		<div class="k-hero__overlay" style="opacity:<?php echo esc_attr( $kingdom_overlay ); ?>"></div>
		<div class="k-hero__scene" aria-hidden="true" data-parallax>
			<span class="k-orb k-orb--1"></span><span class="k-orb k-orb--2"></span><span class="k-orb k-orb--3"></span>
			<span class="k-cross3d"><i></i><i></i></span>
			<span class="k-ring3d"></span>
		</div>
		<div class="k-container k-hero__inner<?php echo 'classic' === $kingdom_variant ? ' k-center' : ''; ?>">
			<div class="k-hero__content" data-reveal>
				<?php if ( $kingdom_eyebrow ) : ?><span class="k-eyebrow k-eyebrow--light"><?php echo esc_html( $kingdom_eyebrow ); ?></span><?php endif; ?>
				<h1 class="k-hero__title"><?php echo esc_html( $kingdom_title ); ?></h1>
				<p class="k-hero__text"><?php echo esc_html( $kingdom_text ); ?></p>
				<?php $kingdom_buttons(); ?>
				<?php if ( 'video' === $kingdom_variant ) : ?>
					<div class="k-hero__countdown"><?php echo kingdom_shortcode( '[kcs_service_countdown]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( 'mega' === $kingdom_variant ) : ?>
			<div class="k-hero__strip">
				<div class="k-container k-hero__strip-inner">
					<?php foreach ( array_slice( $kingdom_times, 0, 4 ) as $kingdom_s ) : ?>
						<div><small><?php echo esc_html( $kingdom_s['name'] ); ?></small><strong><?php echo esc_html( $kingdom_s['day'] . ' ' . $kingdom_s['time'] ); ?></strong></div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( 'mega' !== $kingdom_variant ) : ?><a href="#k-after-hero" class="k-scroll-cue" aria-label="<?php esc_attr_e( 'Scroll down', 'kingdom' ); ?>"></a><?php endif; ?>
	</section>
	<span id="k-after-hero"></span>
<?php endif; ?>
