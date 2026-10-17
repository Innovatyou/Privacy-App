<?php
/**
 * 404.
 *
 * @package Kingdom
 */

get_header();
?>
<section class="k-404 k-section">
	<div class="k-container k-container--narrow k-center">
		<p class="k-404__code">404</p>
		<h1><?php esc_html_e( 'This page seems to have wandered off', 'kingdom' ); ?></h1>
		<p><?php esc_html_e( '"For the Son of Man came to seek and to save the lost." — Luke 19:10. Let us help you find your way.', 'kingdom' ); ?></p>
		<?php get_search_form(); ?>
		<p class="k-btns k-btns--center">
			<a class="k-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'kingdom' ); ?></a>
			<?php if ( post_type_exists( 'kcs_sermon' ) ) : ?>
				<a class="k-btn k-btn--outline" href="<?php echo esc_url( get_post_type_archive_link( 'kcs_sermon' ) ); ?>"><?php esc_html_e( 'Watch a sermon', 'kingdom' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</section>
<?php
get_footer();
