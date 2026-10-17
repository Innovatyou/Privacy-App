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
	<meta name="viewport" content="width=device-width, initial-scale=1">
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
		<div class="k-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="k-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
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
				<?php if ( $cta_url ) : ?>
					<a class="k-btn k-btn--accent k-btn--block" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
				<?php endif; ?>
			</div>
		</nav>

		<div class="k-header__actions">
			<?php if ( kingdom_plugin_active() && get_theme_mod( 'kingdom_live_badge', true ) ) : ?>
				<?php echo do_shortcode( '[kcs_live_badge]' ); ?>
			<?php endif; ?>
			<button type="button" class="k-icon-btn" data-search-toggle aria-label="<?php esc_attr_e( 'Search', 'kingdom' ); ?>"><?php echo kingdom_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			<?php if ( get_theme_mod( 'kingdom_dark_toggle', true ) ) : ?>
				<button type="button" class="k-icon-btn" data-theme-toggle aria-label="<?php esc_attr_e( 'Toggle dark mode', 'kingdom' ); ?>"><span class="k-when-light"><?php echo kingdom_icon( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span class="k-when-dark"><?php echo kingdom_icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
			<?php endif; ?>
			<?php if ( $cta_url ) : ?>
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
