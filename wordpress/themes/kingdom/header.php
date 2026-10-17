<?php
/**
 * Header.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

$services = kingdom_service_times();
$social   = kingdom_social_links();
$cta_text = get_theme_mod( 'kingdom_header_cta_text', __( 'Give', 'kingdom' ) );
$cta_url  = get_theme_mod( 'kingdom_header_cta_url' );
if ( ! $cta_url && kingdom_plugin_active() ) {
	$cta_url = kcs_page_url( 'give' );
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="k-skip" href="#content"><?php esc_html_e( 'Skip to content', 'kingdom' ); ?></a>

<?php if ( get_theme_mod( 'kingdom_topbar', true ) ) : ?>
	<div class="k-topbar">
		<div class="k-container k-topbar__inner">
			<div class="k-topbar__info">
				<?php if ( $services ) : ?>
					<span><?php echo kingdom_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $services[0]['day'] . ' ' . $services[0]['time'] . ( isset( $services[1] ) && $services[1]['day'] === $services[0]['day'] ? ' & ' . $services[1]['time'] : '' ) ); ?></span>
				<?php endif; ?>
				<?php if ( get_theme_mod( 'kingdom_phone' ) ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', get_theme_mod( 'kingdom_phone' ) ) ); ?>"><?php echo kingdom_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( get_theme_mod( 'kingdom_phone' ) ); ?></a>
				<?php endif; ?>
				<?php if ( get_theme_mod( 'kingdom_email' ) ) : ?>
					<a href="mailto:<?php echo esc_attr( get_theme_mod( 'kingdom_email' ) ); ?>" class="k-hide-sm"><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( get_theme_mod( 'kingdom_email' ) ); ?></a>
				<?php endif; ?>
			</div>
			<div class="k-topbar__links">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'topbar',
						'container'      => false,
						'menu_class'     => 'k-topbar__menu',
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
				<?php if ( kingdom_plugin_active() && current_user_can( 'manage_kcs' ) ) : ?>
					<a href="<?php echo esc_url( kcs_page_url( 'church_admin' ) ); ?>"><?php esc_html_e( 'Church admin', 'kingdom' ); ?></a>
				<?php endif; ?>
				<?php if ( kingdom_plugin_active() ) : ?>
					<a href="<?php echo esc_url( is_user_logged_in() && kcs_is_partner() ? kcs_page_url( 'partner_dashboard' ) : kcs_page_url( 'partner_login' ) ); ?>"><?php echo is_user_logged_in() && kcs_is_partner() ? esc_html__( 'My partner dashboard', 'kingdom' ) : esc_html__( 'Partner login', 'kingdom' ); ?></a>
				<?php endif; ?>
				<?php foreach ( $social as $net => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $net ) ); ?>" class="k-hide-sm"><?php echo kingdom_icon( $net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php endif; ?>

<header class="k-header" data-header>
	<div class="k-container k-header__inner">
		<div class="k-brand<?php echo get_theme_mod( 'kingdom_logo_light' ) ? ' has-light-logo' : ''; ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
				<?php if ( get_theme_mod( 'kingdom_logo_light' ) ) : ?>
					<a class="k-brand__light" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><img src="<?php echo esc_url( get_theme_mod( 'kingdom_logo_light' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></a>
				<?php endif; ?>
			<?php else : ?>
				<a class="k-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><span class="k-brand__mark" aria-hidden="true"></span><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
			<?php if ( get_theme_mod( 'kingdom_show_tagline' ) && get_bloginfo( 'description' ) ) : ?>
				<span class="k-brand__tagline"><?php bloginfo( 'description' ); ?></span>
			<?php endif; ?>
		</div>

		<nav class="k-nav" id="k-nav" aria-label="<?php esc_attr_e( 'Primary', 'kingdom' ); ?>" data-nav>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'k-menu',
					'fallback_cb'    => 'kingdom_fallback_menu',
				)
			);
			?>
			<div class="k-nav__mobile-extra">
				<?php if ( get_theme_mod( 'kingdom_palette_switcher', true ) ) : ?>
					<div class="k-nav__palette">
						<span><?php esc_html_e( 'Site colours', 'kingdom' ); ?></span>
						<div class="k-palette__swatches">
							<?php foreach ( kingdom_palettes() as $kingdom_slug => $kingdom_p ) : ?>
								<button type="button" data-palette="<?php echo esc_attr( $kingdom_slug ); ?>" title="<?php echo esc_attr( $kingdom_p[0] ); ?>" style="--sw-a:<?php echo esc_attr( $kingdom_p[1] ); ?>;--sw-b:<?php echo esc_attr( $kingdom_p[2] ); ?>"><span class="screen-reader-text"><?php echo esc_html( $kingdom_p[0] ); ?></span></button>
							<?php endforeach; ?>
						</div>
						<?php kingdom_custom_colour_fields( 'k-nav-pal' ); ?>
					</div>
				<?php endif; ?>
				<?php if ( class_exists( 'KCS_PWA' ) && KCS_PWA::enabled() ) : ?>
					<div class="k-nav__app">
						<button type="button" class="k-btn k-btn--outline k-btn--block" data-kcs-install hidden><?php echo kingdom_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Install our app', 'kingdom' ); ?></button>
						<button type="button" class="k-btn k-btn--outline k-btn--block" data-kcs-push hidden data-off-label="<?php esc_attr_e( 'Turn on notifications', 'kingdom' ); ?>" data-on-label="<?php esc_attr_e( 'Notifications on', 'kingdom' ); ?>"><?php echo kingdom_icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-kcs-push-label><?php esc_html_e( 'Turn on notifications', 'kingdom' ); ?></span></button>
					</div>
				<?php endif; ?>
				<?php if ( $cta_url ) : ?>
					<a class="k-btn k-btn--accent k-btn--block" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
				<?php endif; ?>
			</div>
		</nav>

		<div class="k-header__actions">
			<?php if ( kingdom_plugin_active() && get_theme_mod( 'kingdom_live_badge', true ) ) : ?>
				<?php echo do_shortcode( '[kcs_live_badge]' ); ?>
			<?php endif; ?>
			<?php if ( get_theme_mod( 'kingdom_header_search', true ) ) : ?>
				<button type="button" class="k-icon-btn" data-search-toggle aria-label="<?php esc_attr_e( 'Search', 'kingdom' ); ?>"><?php echo kingdom_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<?php endif; ?>
			<?php if ( get_theme_mod( 'kingdom_dark_toggle', true ) ) : ?>
				<button type="button" class="k-icon-btn" data-theme-toggle aria-label="<?php esc_attr_e( 'Toggle dark mode', 'kingdom' ); ?>"><span class="k-when-light"><?php echo kingdom_icon( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span class="k-when-dark"><?php echo kingdom_icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
			<?php endif; ?>
			<?php if ( $cta_url && get_theme_mod( 'kingdom_header_cta', true ) ) : ?>
				<a class="k-btn k-btn--accent k-btn--sm k-hide-sm" href="<?php echo esc_url( $cta_url ); ?>"><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $cta_text ); ?></a>
			<?php endif; ?>
			<button type="button" class="k-icon-btn k-nav-toggle" data-nav-toggle aria-controls="k-nav" aria-expanded="false" aria-label="<?php esc_attr_e( 'Menu', 'kingdom' ); ?>"><span class="k-when-closed"><?php echo kingdom_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span class="k-when-open"><?php echo kingdom_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
		</div>
	</div>
	<div class="k-search-panel" data-search-panel hidden>
		<div class="k-container"><?php get_search_form(); ?></div>
	</div>
</header>

<main id="content" class="k-main">
