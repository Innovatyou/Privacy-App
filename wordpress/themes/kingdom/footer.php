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

<?php if ( get_theme_mod( 'kingdom_whatsapp_float' ) && get_theme_mod( 'kingdom_social_whatsapp' ) ) : ?>
	<a class="k-whatsapp" href="<?php echo esc_url( get_theme_mod( 'kingdom_social_whatsapp' ) ); ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?php echo kingdom_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
<?php endif; ?>
<button type="button" class="k-to-top" data-to-top aria-label="<?php esc_attr_e( 'Back to top', 'kingdom' ); ?>">↑</button>
<div class="k-modal" data-video-modal hidden><button type="button" class="k-modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Close', 'kingdom' ); ?>">×</button><div class="k-modal__body"></div></div>

<?php wp_footer(); ?>
</body>
</html>
