<?php
/**
 * Church sign-up.
 *
 * @package KingdomSaaS
 * @var array|null $flash Flash message.
 */

defined( 'ABSPATH' ) || exit;

$old   = $flash[2] ?? array();
$v     = function ( $k, $default = '' ) use ( $old ) {
	return isset( $old[ $k ] ) ? (string) $old[ $k ] : $default;
};
$plan  = sanitize_key( $_GET['plan'] ?? $v( 'plan', 'growth' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$plans = KS_Plans::all();
$plan  = isset( $plans[ $plan ] ) ? $plan : 'growth';
$local = strtoupper( (string) ks_get( 'local_currency' ) );
$pals  = array( 'royal' => array( __( 'Royal', 'kingdom-saas' ), '#5b2a86' ), 'ocean' => array( __( 'Ocean', 'kingdom-saas' ), '#1d4ed8' ), 'emerald' => array( __( 'Emerald', 'kingdom-saas' ), '#047857' ), 'crimson' => array( __( 'Crimson', 'kingdom-saas' ), '#b91c1c' ), 'jordan' => array( __( 'Jordan', 'kingdom-saas' ), '#0f766e' ) );
?>
<section class="ks-band ks-signup">
	<div class="ks-wrap ks-signup__in">
		<div class="ks-signup__intro">
			<span class="ks-eyebrow"><?php /* translators: %d: days */ printf( esc_html__( '%d-day free trial · no card needed', 'kingdom-saas' ), (int) ks_get( 'trial_days' ) ); ?></span>
			<h1><?php esc_html_e( 'Create your church website', 'kingdom-saas' ); ?></h1>
			<p class="ks-lead"><?php esc_html_e( 'You will get your website, partner portal, online giving and Church Admin dashboard straight away.', 'kingdom-saas' ); ?></p>
			<ul class="ks-ticks">
				<li><?php esc_html_e( 'Ready-made pages for your church', 'kingdom-saas' ); ?></li>
				<li><?php esc_html_e( 'Partnership plans and partner dashboards', 'kingdom-saas' ); ?></li>
				<li><?php esc_html_e( 'Tithe & offering with Paystack, Stripe and more', 'kingdom-saas' ); ?></li>
				<li><?php esc_html_e( 'Connect your own domain on Growth and Ministry', 'kingdom-saas' ); ?></li>
			</ul>
		</div>
		<form method="post" class="ks-card ks-form" data-ks-signup novalidate>
			<?php if ( $flash ) : ?><div class="ks-alert ks-alert--<?php echo esc_attr( $flash[1] ); ?>" role="alert"><?php echo esc_html( $flash[0] ); ?></div><?php endif; ?>
			<input type="hidden" name="ks_do" value="signup">
			<?php wp_nonce_field( 'ks_signup', '_ks' ); ?>
			<fieldset>
				<legend><?php esc_html_e( '1. Your church', 'kingdom-saas' ); ?></legend>
				<label><?php esc_html_e( 'Church name', 'kingdom-saas' ); ?><input name="church_name" required maxlength="120" value="<?php echo esc_attr( $v( 'church_name' ) ); ?>" placeholder="Grace Chapel International" data-ks-church></label>
				<label><?php esc_html_e( 'Website address', 'kingdom-saas' ); ?>
					<span class="ks-domain"><input name="subdomain" required maxlength="40" pattern="[a-z0-9-]{3,40}" value="<?php echo esc_attr( $v( 'subdomain' ) ); ?>" placeholder="gracechapel" autocapitalize="none" autocomplete="off" spellcheck="false" data-ks-subdomain><span>.<?php echo esc_html( ks_get( 'base_domain' ) ); ?></span></span>
				</label>
				<p class="ks-hint" data-ks-subdomain-msg aria-live="polite"><?php esc_html_e( 'You can connect your own domain later.', 'kingdom-saas' ); ?></p>
				<fieldset class="ks-palettes">
					<legend><?php esc_html_e( 'Website colours', 'kingdom-saas' ); ?></legend>
					<?php foreach ( $pals as $k => $pal ) : ?>
						<label class="ks-swatch"><input type="radio" name="palette" value="<?php echo esc_attr( $k ); ?>" <?php checked( $v( 'palette', 'royal' ), $k ); ?>><span style="--sw:<?php echo esc_attr( $pal[1] ); ?>"></span><?php echo esc_html( $pal[0] ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			</fieldset>
			<fieldset>
				<legend><?php esc_html_e( '2. Your account', 'kingdom-saas' ); ?></legend>
				<label><?php esc_html_e( 'Your name', 'kingdom-saas' ); ?><input name="name" required autocomplete="name" value="<?php echo esc_attr( $v( 'name' ) ); ?>"></label>
				<label><?php esc_html_e( 'Email', 'kingdom-saas' ); ?><input type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( $v( 'email' ) ); ?>"></label>
				<label><?php esc_html_e( 'Password', 'kingdom-saas' ); ?><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
			</fieldset>
			<fieldset>
				<legend><?php esc_html_e( '3. Plan after your trial', 'kingdom-saas' ); ?></legend>
				<div class="ks-pick">
					<?php foreach ( $plans as $id => $p ) : ?>
						<label class="ks-pick__opt"><input type="radio" name="plan" value="<?php echo esc_attr( $id ); ?>" <?php checked( $plan, $id ); ?>><span><strong><?php echo esc_html( $p['name'] ); ?></strong><small><?php echo esc_html( ks_money( $p['usd'], 'USD' ) . ( $local ? ' · ' . ks_money( $p['local'], $local ) : '' ) ); ?> / <?php esc_html_e( 'month', 'kingdom-saas' ); ?></small></span></label>
					<?php endforeach; ?>
				</div>
				<?php if ( $local ) : ?>
					<div class="ks-seg ks-seg--block" role="radiogroup" aria-label="<?php esc_attr_e( 'Billing currency', 'kingdom-saas' ); ?>">
						<label><input type="radio" name="region" value="international" <?php checked( 'local' !== $v( 'region' ) ); ?>><span>🌍 <?php esc_html_e( 'Pay in USD', 'kingdom-saas' ); ?></span></label>
						<label><input type="radio" name="region" value="local" <?php checked( 'local', $v( 'region' ) ); ?>><span>📍 <?php /* translators: %s: currency */ printf( esc_html__( 'Pay in %s', 'kingdom-saas' ), esc_html( $local ) ); ?></span></label>
					</div>
				<?php endif; ?>
			</fieldset>
			<input type="text" name="website" class="ks-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<label class="ks-check"><input type="checkbox" name="terms" value="1" required <?php checked( (bool) $v( 'terms' ) ); ?>> <?php esc_html_e( 'I agree to the terms of service and privacy policy.', 'kingdom-saas' ); ?></label>
			<button class="ks-btn ks-btn--lg ks-btn--block"><?php esc_html_e( 'Create my church website', 'kingdom-saas' ); ?></button>
			<p class="ks-small ks-center"><?php esc_html_e( 'Already have a church site?', 'kingdom-saas' ); ?> <a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Log in', 'kingdom-saas' ); ?></a></p>
		</form>
	</div>
</section>
