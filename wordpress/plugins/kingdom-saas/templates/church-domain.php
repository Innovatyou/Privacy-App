<?php
/**
 * Church Admin → Custom domain.
 *
 * @package KingdomSaaS
 * @var object   $sub     Subscription.
 * @var array    $domains Domains.
 * @var callable $fields  Hidden fields helper.
 */

defined( 'ABSPATH' ) || exit;

$allowed = (bool) KS_Plans::feature( $sub->plan, 'custom_domain' );
$d       = $domains ? $domains[0] : null;
?>
<?php if ( ! empty( $_GET['ks_connected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="kcs-alert kcs-alert--success" role="status"><?php esc_html_e( 'Your domain is connected. You are now on your new address.', 'kingdom-saas' ); ?></div>
<?php endif; ?>
<section class="kca-card">
	<h3><?php esc_html_e( 'Your website address', 'kingdom-saas' ); ?></h3>
	<p class="ks-addr"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a>
	<?php if ( $d && $d->is_primary ) : ?>
		<?php /* translators: %s: old address */ ?>
		<small class="kcs-muted"><?php printf( esc_html__( '%s redirects here.', 'kingdom-saas' ), esc_html( $sub->subdomain ) ); ?></small>
	<?php endif; ?>
	</p>
</section>

<?php if ( ! $allowed ) : ?>
	<section class="kca-card ks-upsell">
		<h3><?php esc_html_e( 'Use your own domain', 'kingdom-saas' ); ?></h3>
		<p><?php esc_html_e( 'Open your website at yourchurch.org instead of a platform address. Custom domains are included on the Growth and Ministry plans.', 'kingdom-saas' ); ?></p>
		<a class="kcs-btn" href="<?php echo esc_url( add_query_arg( 'view', 'subscription', kcs_page_url( 'church_admin' ) ) ); ?>"><?php esc_html_e( 'Upgrade plan', 'kingdom-saas' ); ?></a>
	</section>
<?php endif; ?>

<?php if ( $d ) : ?>
	<section class="kca-card">
		<div class="ks-domain-head">
			<h3><?php echo esc_html( $d->domain ); ?></h3>
			<?php echo wp_kses_post( ks_badge( $d->status ) ); ?>
		</div>
		<?php if ( 'verified' === $d->status && $d->is_primary ) : ?>
			<p><?php esc_html_e( 'Connected. Visitors who use your old address are redirected here automatically, and a free security certificate (https) is issued for it.', 'kingdom-saas' ); ?></p>
		<?php else : ?>
			<?php if ( $d->last_error ) : ?><p class="kcs-alert kcs-alert--info"><?php echo esc_html( $d->last_error ); ?></p><?php endif; ?>
			<p><?php esc_html_e( 'Log in where you bought your domain (for example GoDaddy, Namecheap, Whogohost or Cloudflare), open its DNS settings and add these records:', 'kingdom-saas' ); ?></p>
			<div class="kca-table">
				<table>
					<thead><tr><th><?php esc_html_e( 'Type', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Host / name', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Value / points to', 'kingdom-saas' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( KS_Domains::records( $d ) as $r ) : ?>
						<tr><td data-label="<?php esc_attr_e( 'Type', 'kingdom-saas' ); ?>"><strong><?php echo esc_html( $r[0] ); ?></strong></td><td data-label="<?php esc_attr_e( 'Host', 'kingdom-saas' ); ?>"><code><?php echo esc_html( $r[1] ); ?></code></td><td data-label="<?php esc_attr_e( 'Value', 'kingdom-saas' ); ?>"><code class="ks-copy"><?php echo esc_html( $r[2] ); ?></code></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="kcs-muted"><?php esc_html_e( 'DNS changes usually work within an hour but can take up to 24 hours. We check automatically, or press “Check now”.', 'kingdom-saas' ); ?></p>
			<?php if ( $allowed ) : ?>
				<form method="post" class="kca-inline"><?php echo call_user_func( $fields, 'domain_verify', $d->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn"><?php esc_html_e( 'Check now', 'kingdom-saas' ); ?></button></form>
			<?php endif; ?>
		<?php endif; ?>
		<form method="post" class="kca-inline"><?php echo call_user_func( $fields, 'domain_remove', $d->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost" onclick="return confirm('<?php echo esc_js( __( 'Remove this domain? Your website goes back to its platform address.', 'kingdom-saas' ) ); ?>')"><?php esc_html_e( 'Remove domain', 'kingdom-saas' ); ?></button></form>
	</section>
<?php elseif ( $allowed ) : ?>
	<form method="post" class="kca-card kcs-form">
		<?php echo call_user_func( $fields, 'domain_add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<h3><?php esc_html_e( 'Connect your domain', 'kingdom-saas' ); ?></h3>
		<label><?php esc_html_e( 'Domain you own', 'kingdom-saas' ); ?><input name="domain" required placeholder="yourchurch.org" autocomplete="off" autocapitalize="none" spellcheck="false"></label>
		<p class="kcs-muted"><?php esc_html_e( 'Tip: enter www.yourchurch.org if your domain provider does not support pointing the bare domain to another address.', 'kingdom-saas' ); ?></p>
		<button class="kcs-btn"><?php esc_html_e( 'Add domain', 'kingdom-saas' ); ?></button>
	</form>
<?php endif; ?>
