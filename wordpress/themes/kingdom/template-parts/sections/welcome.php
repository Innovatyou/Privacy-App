<?php
/**
 * Welcome / pastor message.
 *
 * @package Kingdom
 */

$kingdom_img = kingdom_image( 'kingdom_welcome_image' );
$kingdom_btn = get_theme_mod( 'kingdom_welcome_btn_text', __( 'Our story', 'kingdom' ) );
?>
<section class="k-section k-welcome">
	<div class="k-container k-split">
		<div class="k-split__media" data-reveal>
			<?php if ( $kingdom_img ) : ?>
				<img src="<?php echo esc_url( $kingdom_img ); ?>" alt="<?php echo esc_attr( get_theme_mod( 'kingdom_pastor_name' ) ); ?>" loading="lazy">
			<?php else : ?>
				<div class="k-placeholder"><?php echo kingdom_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
			<span class="k-split__accent"></span>
		</div>
		<div class="k-split__content" data-reveal>
			<span class="k-eyebrow"><?php echo esc_html( get_theme_mod( 'kingdom_welcome_eyebrow', __( 'Welcome', 'kingdom' ) ) ); ?></span>
			<h2 class="k-section__title"><?php echo esc_html( get_theme_mod( 'kingdom_welcome_title', __( 'You are not here by accident', 'kingdom' ) ) ); ?></h2>
			<div class="k-prose"><?php echo wp_kses_post( wpautop( get_theme_mod( 'kingdom_welcome_text', __( 'We are a family of believers passionate about Jesus, prayer and the Word. Whether you are exploring faith for the first time or looking for a church home, there is a place for you here.', 'kingdom' ) ) ) ); ?></div>
			<?php if ( get_theme_mod( 'kingdom_pastor_name', __( 'Pastor John & Mary Doe', 'kingdom' ) ) ) : ?>
				<p class="k-signature"><strong><?php echo esc_html( get_theme_mod( 'kingdom_pastor_name', __( 'Pastor John & Mary Doe', 'kingdom' ) ) ); ?></strong><br><small><?php echo esc_html( get_theme_mod( 'kingdom_pastor_role', __( 'Lead Pastors', 'kingdom' ) ) ); ?></small></p>
			<?php endif; ?>
			<?php if ( $kingdom_btn ) : ?>
				<a class="k-btn" href="<?php echo esc_url( kingdom_url( 'kingdom_welcome_btn_url', 'template-about.php' ) ); ?>"><?php echo esc_html( $kingdom_btn ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
