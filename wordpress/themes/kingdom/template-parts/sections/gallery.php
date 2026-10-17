<?php
/**
 * Church photo gallery. Each photo is set in Customize → Church Images & Gallery.
 *
 * @package Kingdom
 */

$kingdom_items = array();
for ( $kingdom_i = 1; $kingdom_i <= 6; $kingdom_i++ ) {
	$kingdom_items[] = array( kingdom_image( 'kingdom_gallery_' . $kingdom_i ), get_theme_mod( 'kingdom_gallery_' . $kingdom_i . '_caption', '' ) );
}
?>
<section class="k-section k-gallery-section">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Gallery', 'kingdom' ), get_theme_mod( 'kingdom_gallery_title', __( 'Life at church', 'kingdom' ) ) ); ?>
		<div class="k-gallery">
			<?php foreach ( $kingdom_items as $kingdom_n => $kingdom_item ) : ?>
				<figure class="k-gallery__item k-gallery__item--<?php echo (int) $kingdom_n + 1; ?>" data-tilt data-reveal>
					<img src="<?php echo esc_url( $kingdom_item[0] ); ?>" alt="<?php echo esc_attr( $kingdom_item[1] ? $kingdom_item[1] : get_bloginfo( 'name' ) ); ?>" loading="lazy">
					<?php if ( $kingdom_item[1] ) : ?><figcaption><?php echo esc_html( $kingdom_item[1] ); ?></figcaption><?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
		<?php if ( current_user_can( 'customize' ) ) : ?>
			<p class="k-center k-mt"><a class="k-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=kingdom_images' ) ); ?>"><?php esc_html_e( 'Change these photos', 'kingdom' ); ?> →</a></p>
		<?php endif; ?>
	</div>
</section>
