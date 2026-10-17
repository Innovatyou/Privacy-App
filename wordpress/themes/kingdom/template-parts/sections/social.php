<?php
/**
 * Social media connect.
 *
 * @package Kingdom
 */

$kingdom_social = kingdom_social_links();
if ( ! $kingdom_social ) {
	if ( current_user_can( 'edit_theme_options' ) ) {
		echo '<div class="k-container k-section"><div class="k-notice">' . esc_html__( 'Add your social profiles in Customize → Kingdom Church Theme → Social Profiles to show this section.', 'kingdom' ) . '</div></div>';
	}
	return;
}
$kingdom_labels = array(
	'facebook'  => array( 'Facebook', __( 'Like our page & join the live chat', 'kingdom' ) ),
	'instagram' => array( 'Instagram', __( 'Daily devotionals & stories', 'kingdom' ) ),
	'youtube'   => array( 'YouTube', __( 'Subscribe for every sermon', 'kingdom' ) ),
	'x'         => array( 'X', __( 'News & announcements', 'kingdom' ) ),
	'tiktok'    => array( 'TikTok', __( 'Short clips & youth content', 'kingdom' ) ),
	'whatsapp'  => array( 'WhatsApp', __( 'Join our broadcast channel', 'kingdom' ) ),
	'telegram'  => array( 'Telegram', __( 'Prayer & updates channel', 'kingdom' ) ),
);
$kingdom_tag = get_theme_mod( 'kingdom_social_hashtag' );
?>
<section class="k-section k-social-section">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Social', 'kingdom' ), get_theme_mod( 'kingdom_social_title', __( 'Connect with us online', 'kingdom' ) ), $kingdom_tag ? sprintf( /* translators: %s: hashtag */ __( 'Share your moments with %s', 'kingdom' ), $kingdom_tag ) : __( 'Stay close to church family all week long.', 'kingdom' ) ); ?>
		<div class="k-social-grid">
			<?php foreach ( $kingdom_social as $kingdom_net => $kingdom_url ) : ?>
				<a class="k-social-card k-social-card--<?php echo esc_attr( $kingdom_net ); ?>" href="<?php echo esc_url( $kingdom_url ); ?>" target="_blank" rel="noopener" data-tilt data-reveal>
					<span class="k-social-card__icon"><?php echo kingdom_icon( $kingdom_net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<strong><?php echo esc_html( $kingdom_labels[ $kingdom_net ][0] ?? ucfirst( $kingdom_net ) ); ?></strong>
					<small><?php echo esc_html( $kingdom_labels[ $kingdom_net ][1] ?? '' ); ?></small>
					<span class="k-social-card__cta"><?php esc_html_e( 'Connect', 'kingdom' ); ?> <?php echo kingdom_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
