<?php
/**
 * Platform page shell.
 *
 * @package KingdomSaaS
 * @var string $route Route.
 * @var array  $vars  Variables for the route template.
 */

defined( 'ABSPATH' ) || exit;

$name   = ks_get( 'platform_name' );
$titles = array(
	'landing'       => $name . ' — ' . __( 'Church websites, partner portal & online giving', 'kingdom-saas' ),
	'signup'        => __( 'Start your free trial', 'kingdom-saas' ) . ' — ' . $name,
	'platform'      => __( 'Platform dashboard', 'kingdom-saas' ) . ' — ' . $name,
	'test-checkout' => __( 'Test checkout', 'kingdom-saas' ) . ' — ' . $name,
);
$color  = sanitize_hex_color( ks_get( 'brand_color' ) ) ? sanitize_hex_color( ks_get( 'brand_color' ) ) : '#5b2a86';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $titles[ $route ] ?? $name ); ?></title>
<meta name="description" content="<?php echo esc_attr( ks_get( 'tagline' ) ); ?>">
<?php if ( 'landing' !== $route ) : ?><meta name="robots" content="noindex"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( KS_URL . 'assets/css/saas.css?ver=' . KS_VERSION ); ?>">
<style>:root{--ks-brand:<?php echo esc_html( $color ); ?>}</style>
</head>
<body class="ks ks--<?php echo esc_attr( $route ); ?>">
<a class="ks-skip" href="#ks-main"><?php esc_html_e( 'Skip to content', 'kingdom-saas' ); ?></a>
<?php if ( 'platform' !== $route ) : ?>
<header class="ks-top">
	<div class="ks-wrap ks-top__in">
		<a class="ks-logo" href="<?php echo esc_url( ks_platform_url() ); ?>"><span class="ks-logo__mark" aria-hidden="true">✚</span><?php echo esc_html( $name ); ?></a>
		<nav class="ks-nav" aria-label="<?php esc_attr_e( 'Main', 'kingdom-saas' ); ?>">
			<a href="<?php echo esc_url( ks_platform_url( '#features' ) ); ?>"><?php esc_html_e( 'Features', 'kingdom-saas' ); ?></a>
			<a href="<?php echo esc_url( ks_platform_url( '#pricing' ) ); ?>"><?php esc_html_e( 'Pricing', 'kingdom-saas' ); ?></a>
			<a href="<?php echo esc_url( ks_platform_url( '#faq' ) ); ?>"><?php esc_html_e( 'FAQ', 'kingdom-saas' ); ?></a>
		</nav>
		<a class="ks-btn ks-btn--sm" href="<?php echo esc_url( ks_platform_url( 'signup/' ) ); ?>"><?php esc_html_e( 'Start free trial', 'kingdom-saas' ); ?></a>
	</div>
</header>
<?php endif; ?>
<main id="ks-main">
	<?php echo ks_template( $route, $vars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php if ( 'platform' !== $route ) : ?>
<footer class="ks-foot">
	<div class="ks-wrap ks-foot__in">
		<div><strong><?php echo esc_html( $name ); ?></strong><p><?php echo esc_html( ks_get( 'tagline' ) ); ?></p></div>
		<div><a href="<?php echo esc_url( ks_platform_url( 'signup/' ) ); ?>"><?php esc_html_e( 'Start free trial', 'kingdom-saas' ); ?></a><a href="mailto:<?php echo esc_attr( ks_get( 'support_email' ) ); ?>"><?php esc_html_e( 'Contact sales', 'kingdom-saas' ); ?></a><a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Log in', 'kingdom-saas' ); ?></a></div>
	</div>
	<p class="ks-wrap ks-foot__copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . $name ); ?></p>
</footer>
<?php endif; ?>
<script src="<?php echo esc_url( KS_URL . 'assets/js/saas.js?ver=' . KS_VERSION ); ?>" defer data-rest="<?php echo esc_url( get_rest_url( get_main_site_id(), 'ks/v1/' ) ); ?>"></script>
</body>
</html>
