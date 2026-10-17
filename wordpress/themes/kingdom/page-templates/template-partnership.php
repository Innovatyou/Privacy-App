<?php
/**
 * Template Name: Partnership
 * Template Post Type: page
 *
 * Partnership vision, plans, benefits and partner login.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header( '', __( 'Join a family of partners carrying the gospel to the nations.', 'kingdom' ) );
?>
<section class="k-section">
	<div class="k-container">
		<div class="k-grid k-grid--3 k-mb">
			<div class="k-feature" data-reveal><span class="k-feature__icon"><?php echo kingdom_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Preach the gospel', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Fund crusades, media outreach and missions.', 'kingdom' ); ?></p></div>
			<div class="k-feature" data-reveal><span class="k-feature__icon"><?php echo kingdom_icon( 'hands' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Serve communities', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Support welfare, scholarships and outreach to the needy.', 'kingdom' ); ?></p></div>
			<div class="k-feature" data-reveal><span class="k-feature__icon"><?php echo kingdom_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Raise disciples', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Train leaders and plant churches across the nations.', 'kingdom' ); ?></p></div>
		</div>
		<?php
		if ( kingdom_content_has( 'kcs_partnership_plans' ) ) {
			while ( have_posts() ) {
				the_post();
				echo '<div class="k-entry__content">';
				the_content();
				echo '</div>';
			}
		} else {
			kingdom_section_heading( __( 'Plans', 'kingdom' ), __( 'Choose your partnership', 'kingdom' ) );
			echo kingdom_shortcode( '[kcs_partnership_plans]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			kingdom_section( 'page-content' );
		}
		?>
	</div>
</section>
<?php
kingdom_section( 'testimonies' );
kingdom_section( 'cta', array( 'title' => __( 'Already a partner?', 'kingdom' ), 'text' => __( 'Log in to give, view your history and manage your partnership.', 'kingdom' ), 'button' => __( 'Partner login', 'kingdom' ), 'url' => kingdom_plugin_active() ? kcs_page_url( 'partner_login' ) : wp_login_url() ) );
get_footer();
