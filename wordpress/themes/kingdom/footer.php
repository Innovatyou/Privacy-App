<?php
/**
 * Footer.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

$services = kingdom_service_times();
$social   = kingdom_social_links();
?>
</main>

<footer class="k-footer">
	<?php if ( get_theme_mod( 'kingdom_footer_newsletter', true ) && kingdom_plugin_active() ) : ?>
		<div class="k-footer__newsletter">
			<div class="k-container">
				<?php echo do_shortcode( '[kcs_newsletter style="inline" title="' . esc_attr__( 'Get weekly encouragement', 'kingdom' ) . '" text="' . esc_attr__( 'Sermons, devotionals and church news — straight to your inbox.', 'kingdom' ) . '"]' ); ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="k-container k-footer__grid">
		<?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) || is_active_sidebar( 'footer-4' ) ) : ?>
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<div class="k-footer__col"><?php dynamic_sidebar( 'footer-' . $i ); ?></div>
			<?php endfor; ?>
		<?php else : ?>
			<div class="k-footer__col">
				<h3 class="k-footer__brand"><?php bloginfo( 'name' ); ?></h3>
				<?php if ( get_theme_mod( 'kingdom_brand_motto' ) ) : ?><p class="k-footer__motto"><?php echo esc_html( get_theme_mod( 'kingdom_brand_motto' ) ); ?></p><?php endif; ?>
				<p><?php echo esc_html( get_theme_mod( 'kingdom_footer_about', __( 'Raising a generation of believers who love God, love people and transform their world.', 'kingdom' ) ) ); ?></p>
				<?php if ( $social ) : ?>
					<div class="k-social">
						<?php foreach ( $social as $net => $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $net ) ); ?>"><?php echo kingdom_icon( $net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="k-footer__col">
				<h4><?php esc_html_e( 'Service times', 'kingdom' ); ?></h4>
				<ul class="k-footer__list">
					<?php foreach ( $services as $s ) : ?>
						<li><strong><?php echo esc_html( $s['name'] ); ?></strong><br><?php echo esc_html( $s['day'] . ' · ' . $s['time'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="k-footer__col">
				<h4><?php esc_html_e( 'Quick links', 'kingdom' ); ?></h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'k-footer__list',
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
			</div>
			<div class="k-footer__col">
				<h4><?php esc_html_e( 'Visit us', 'kingdom' ); ?></h4>
				<ul class="k-footer__list k-footer__contact">
					<?php if ( get_theme_mod( 'kingdom_address' ) ) : ?><li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo nl2br( esc_html( get_theme_mod( 'kingdom_address' ) ) ); ?></span></li><?php endif; ?>
					<?php if ( get_theme_mod( 'kingdom_phone' ) ) : ?><li><?php echo kingdom_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', get_theme_mod( 'kingdom_phone' ) ) ); ?>"><?php echo esc_html( get_theme_mod( 'kingdom_phone' ) ); ?></a></li><?php endif; ?>
					<?php if ( get_theme_mod( 'kingdom_email' ) ) : ?><li><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="mailto:<?php echo esc_attr( get_theme_mod( 'kingdom_email' ) ); ?>"><?php echo esc_html( get_theme_mod( 'kingdom_email' ) ); ?></a></li><?php endif; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

	<div class="k-footer__bottom">
		<div class="k-container">
			<p><?php echo esc_html( get_theme_mod( 'kingdom_copyright' ) ? get_theme_mod( 'kingdom_copyright' ) : '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. ' . __( 'All rights reserved.', 'kingdom' ) ); ?></p>
			<?php if ( function_exists( 'the_privacy_policy_link' ) ) { the_privacy_policy_link(); } ?>
		</div>
	</div>
</footer>

<?php $kingdom_wa = kingdom_whatsapp_chat(); ?>
<?php if ( $kingdom_wa ) : ?>
	<div class="k-wa" data-wa>
		<div class="k-wa__panel" id="k-wa-panel" role="dialog" aria-label="<?php esc_attr_e( 'Chat with us on WhatsApp', 'kingdom' ); ?>" hidden>
			<div class="k-wa__head">
				<span class="k-wa__avatar" aria-hidden="true"><?php echo kingdom_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span><strong><?php echo esc_html( $kingdom_wa['title'] ); ?></strong><small><?php esc_html_e( 'Typically replies within a few hours', 'kingdom' ); ?></small></span>
				<button type="button" class="k-wa__close" data-wa-toggle aria-label="<?php esc_attr_e( 'Close chat', 'kingdom' ); ?>">×</button>
			</div>
			<div class="k-wa__body">
				<p class="k-wa__bubble"><?php echo esc_html( $kingdom_wa['greeting'] ); ?></p>
				<?php foreach ( $kingdom_wa['contacts'] as $kingdom_c ) : ?>
					<a class="k-wa__contact" href="<?php echo esc_url( $kingdom_c['url'] ); ?>" target="_blank" rel="noopener"><span><?php echo esc_html( $kingdom_c['label'] ); ?></span><b><?php esc_html_e( 'Start chat', 'kingdom' ); ?> →</b></a>
				<?php endforeach; ?>
				<?php if ( ! empty( $kingdom_wa['hours'] ) ) : ?><p class="k-wa__hours"><?php echo esc_html( $kingdom_wa['hours'] ); ?></p><?php endif; ?>
			</div>
		</div>
		<button type="button" class="k-wa__btn" data-wa-toggle aria-expanded="false" aria-controls="k-wa-panel">
			<?php echo kingdom_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="k-wa__label"><?php esc_html_e( 'Chat with us', 'kingdom' ); ?></span>
		</button>
	</div>
<?php endif; ?>
<?php if ( get_theme_mod( 'kingdom_mobile_nav', true ) ) : ?>
	<?php
	$kingdom_tabs = array(
		array( 'home', __( 'Home', 'kingdom' ), home_url( '/' ) ),
		array( 'play', __( 'Watch', 'kingdom' ), kingdom_plugin_active() ? kcs_page_url( 'watch_live' ) : kingdom_url( '', 'template-watch-live.php' ) ),
		array( 'heart', __( 'Give', 'kingdom' ), kingdom_plugin_active() ? kcs_page_url( 'give' ) : kingdom_url( '', 'template-give.php' ) ),
		array( 'hands', __( 'Prayer', 'kingdom' ), kingdom_plugin_active() ? kcs_page_url( 'prayer' ) : kingdom_url( '', 'template-prayer.php' ) ),
	);
	?>
	<nav class="k-tabbar" aria-label="<?php esc_attr_e( 'Quick navigation', 'kingdom' ); ?>">
		<?php foreach ( $kingdom_tabs as $kingdom_tab ) : ?>
			<a href="<?php echo esc_url( $kingdom_tab[2] ); ?>"<?php echo untrailingslashit( $kingdom_tab[2] ) === untrailingslashit( home_url( add_query_arg( array() ) ) ) ? ' aria-current="page"' : ''; ?>><?php echo kingdom_icon( $kingdom_tab[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $kingdom_tab[1] ); ?></span></a>
		<?php endforeach; ?>
		<button type="button" data-nav-toggle aria-controls="k-nav" aria-expanded="false"><?php echo kingdom_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Menu', 'kingdom' ); ?></span></button>
	</nav>
<?php endif; ?>

<?php if ( get_theme_mod( 'kingdom_palette_switcher', true ) ) : ?>
	<div class="k-palette" data-palette-picker>
		<button type="button" class="k-palette__toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Choose site colours', 'kingdom' ); ?>"><?php echo kingdom_icon( 'palette' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<div class="k-palette__panel" hidden>
			<p><?php esc_html_e( 'Site colours', 'kingdom' ); ?></p>
			<div class="k-palette__swatches">
				<?php foreach ( kingdom_palettes() as $kingdom_slug => $kingdom_p ) : ?>
					<button type="button" data-palette="<?php echo esc_attr( $kingdom_slug ); ?>" title="<?php echo esc_attr( $kingdom_p[0] ); ?>" style="--sw-a:<?php echo esc_attr( $kingdom_p[1] ); ?>;--sw-b:<?php echo esc_attr( $kingdom_p[2] ); ?>"><span class="screen-reader-text"><?php echo esc_html( $kingdom_p[0] ); ?></span></button>
				<?php endforeach; ?>
			</div>
			<button type="button" class="k-palette__reset" data-palette=""><?php esc_html_e( 'Reset to church colours', 'kingdom' ); ?></button>
		</div>
	</div>
<?php endif; ?>

<button type="button" class="k-to-top" data-to-top aria-label="<?php esc_attr_e( 'Back to top', 'kingdom' ); ?>">↑</button>
<div class="k-modal" data-video-modal hidden><button type="button" class="k-modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Close', 'kingdom' ); ?>">×</button><div class="k-modal__body"></div></div>

<?php wp_footer(); ?>
</body>
</html>
