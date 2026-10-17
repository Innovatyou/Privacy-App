<?php
/**
 * Template Name: Partner Portal
 * Template Post Type: page
 *
 * Partner login for guests and the partner dashboard for logged-in partners.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header();
?>
<section class="k-section k-portal">
	<div class="k-container">
		<?php
		if ( kingdom_content_has( 'kcs_partner_dashboard' ) ) {
			while ( have_posts() ) {
				the_post();
				the_content();
			}
		} else {
			echo kingdom_shortcode( '[kcs_partner_dashboard]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<?php if ( ! is_user_logged_in() && kingdom_plugin_active() ) : ?>
			<p class="k-center k-mt"><?php esc_html_e( 'Not a partner yet?', 'kingdom' ); ?> <a href="<?php echo esc_url( kcs_page_url( 'partner_register' ) ); ?>"><?php esc_html_e( 'Join the partnership', 'kingdom' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
