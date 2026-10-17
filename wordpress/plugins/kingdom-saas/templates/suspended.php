<?php
/**
 * Shown to visitors while a church site is paused.
 *
 * @package KingdomSaaS
 * @var object $sub Subscription.
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?php echo esc_html( $sub->church_name ); ?></title>
<style>
:root{color-scheme:light dark}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f6f3fa;color:#1f1a26}
.box{max-width:480px;text-align:center;background:#fff;border-radius:20px;padding:40px 28px;box-shadow:0 30px 60px -40px rgba(40,10,80,.45)}
.ic{width:64px;height:64px;margin:0 auto 16px;border-radius:50%;display:grid;place-items:center;background:#efe7f8;color:#5b2a86;font-size:28px}
h1{font-size:1.5rem;margin:0 0 8px}p{margin:0;color:#6b6475}
a{color:#5b2a86;font-weight:600}
@media (prefers-color-scheme:dark){body{background:#15121a;color:#f2eef7}.box{background:#211c29}p{color:#b6aec2}.ic{background:#2e2539;color:#d6b8ff}a{color:#d6b8ff}}
</style>
</head>
<body>
<div class="box">
	<div class="ic" aria-hidden="true">✚</div>
	<h1><?php echo esc_html( $sub->church_name ); ?></h1>
	<p><?php esc_html_e( 'Our website is taking a short break and will be back soon. Thank you for your patience.', 'kingdom-saas' ); ?></p>
	<p style="margin-top:16px;font-size:14px"><a href="<?php echo esc_url( wp_login_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Church staff log in', 'kingdom-saas' ); ?></a></p>
</div>
</body>
</html>
