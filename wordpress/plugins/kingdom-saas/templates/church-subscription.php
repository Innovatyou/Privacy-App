<?php
/**
 * Church Admin → Subscription.
 *
 * @package KingdomSaaS
 * @var object   $sub    Subscription.
 * @var callable $fields Hidden fields helper.
 */

defined( 'ABSPATH' ) || exit;

$plans    = KS_Plans::all();
$current  = KS_Plans::get( $sub->plan );
$due      = KS_Tenants::due_at( $sub );
$left     = KS_Tenants::days_until( $due );
$max      = (int) ( $current['max_partners'] ?? 0 );
$used     = KS_Tenants::partner_count( $sub->blog_id );
$local    = strtoupper( (string) ks_get( 'local_currency' ) );
$invoices = KS_Billing::invoices( $sub->blog_id, 20 );
$pending  = isset( $_GET['invoice'] ) ? KS_Billing::invoice( sanitize_text_field( wp_unslash( $_GET['invoice'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paid     = isset( $_GET['ks_paid'] ) ? (int) $_GET['ks_paid'] : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date     = function ( $gmt ) {
	return $gmt ? date_i18n( get_option( 'date_format' ), strtotime( $gmt . ' UTC' ) ) : '—';
};
?>
<?php if ( 1 === $paid ) : ?>
	<div class="kcs-alert kcs-alert--success" role="status"><?php esc_html_e( 'Payment received — thank you! Your subscription is active.', 'kingdom-saas' ); ?></div>
<?php elseif ( 0 === $paid ) : ?>
	<div class="kcs-alert kcs-alert--error" role="status"><?php esc_html_e( 'We could not confirm that payment. If you were charged, it will be confirmed automatically within a few minutes.', 'kingdom-saas' ); ?></div>
<?php endif; ?>
<?php if ( ! KS_Tenants::is_live( $sub ) ) : ?>
	<div class="ks-banner ks-banner--suspended"><span><?php esc_html_e( 'Your website is paused for visitors. Choose a plan below and it comes straight back online.', 'kingdom-saas' ); ?></span></div>
<?php endif; ?>

<?php if ( $pending && 'pending' === $pending->status && 'bank' === $pending->gateway && (int) $pending->blog_id === (int) $sub->blog_id ) : ?>
	<section class="kca-card ks-bank">
		<h3><?php esc_html_e( 'Pay by bank transfer', 'kingdom-saas' ); ?></h3>
		<?php /* translators: 1: amount, 2: reference */ ?>
		<p><?php printf( esc_html__( 'Transfer %1$s and use %2$s as the payment reference. Your plan starts as soon as we confirm it.', 'kingdom-saas' ), '<strong>' . esc_html( ks_money( $pending->amount, $pending->currency ) ) . '</strong>', '<code>' . esc_html( $pending->reference ) . '</code>' ); ?></p>
		<pre class="ks-pre"><?php echo esc_html( ks_get( 'bank_details' ) ); ?></pre>
	</section>
<?php endif; ?>

<div class="kca-kpis">
	<div class="kca-kpi"><span><?php esc_html_e( 'Plan', 'kingdom-saas' ); ?></span><strong><?php echo esc_html( $current['name'] ?? $sub->plan ); ?></strong><small><?php echo wp_kses_post( ks_badge( $sub->status ) ); ?></small></div>
	<div class="kca-kpi">
		<span><?php echo 'trialing' === $sub->status ? esc_html__( 'Trial ends', 'kingdom-saas' ) : ( $sub->cancel_at_period_end ? esc_html__( 'Ends on', 'kingdom-saas' ) : esc_html__( 'Next payment', 'kingdom-saas' ) ); ?></span>
		<strong><?php echo esc_html( $date( $due ) ); ?></strong>
		<?php /* translators: %d: days */ ?>
		<small><?php echo null === $left ? '' : ( $left >= 0 ? esc_html( sprintf( _n( '%d day left', '%d days left', $left, 'kingdom-saas' ), $left ) ) : esc_html__( 'Overdue', 'kingdom-saas' ) ); ?></small>
	</div>
	<div class="kca-kpi"><span><?php esc_html_e( 'Partners', 'kingdom-saas' ); ?></span><strong><?php echo (int) $used; ?><?php echo $max ? ' / ' . (int) $max : ''; ?></strong><small><?php echo $max ? '<i class="ks-meter"><b style="width:' . esc_attr( min( 100, round( $used / max( 1, $max ) * 100 ) ) ) . '%"></b></i>' : esc_html__( 'Unlimited', 'kingdom-saas' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></small></div>
	<div class="kca-kpi"><span><?php esc_html_e( 'Website address', 'kingdom-saas' ); ?></span><strong class="ks-url"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></strong><small><a href="<?php echo esc_url( add_query_arg( 'view', 'domain', kcs_page_url( 'church_admin' ) ) ); ?>"><?php echo ! empty( $current['custom_domain'] ) ? esc_html__( 'Connect your own domain', 'kingdom-saas' ) : esc_html__( 'Own domain on Growth', 'kingdom-saas' ); ?> →</a></small></div>
</div>

<form method="post" class="kca-card kcs-form ks-checkout" data-ks-prices data-r="<?php echo esc_attr( $sub->region ); ?>" data-i="<?php echo esc_attr( $sub->billing_interval ); ?>">
	<?php echo call_user_func( $fields, 'checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<h3><?php echo 'active' === $sub->status ? esc_html__( 'Change plan or pay ahead', 'kingdom-saas' ) : esc_html__( 'Choose your plan', 'kingdom-saas' ); ?></h3>
	<div class="ks-toggles">
		<div class="ks-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Currency', 'kingdom-saas' ); ?>">
			<label><input type="radio" name="region" value="international" <?php checked( 'local' !== $sub->region ); ?>><span>🌍 <?php esc_html_e( 'International', 'kingdom-saas' ); ?> <b>USD</b></span></label>
			<?php if ( $local ) : ?><label><input type="radio" name="region" value="local" <?php checked( 'local', $sub->region ); ?>><span>📍 <?php echo esc_html( ks_get( 'local_label' ) ); ?> <b><?php echo esc_html( $local ); ?></b></span></label><?php endif; ?>
		</div>
		<div class="ks-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Billing period', 'kingdom-saas' ); ?>">
			<label><input type="radio" name="interval" value="month" <?php checked( 'year' !== $sub->billing_interval ); ?>><span><?php esc_html_e( 'Monthly', 'kingdom-saas' ); ?></span></label>
			<label><input type="radio" name="interval" value="year" <?php checked( 'year', $sub->billing_interval ); ?>><span><?php esc_html_e( 'Yearly', 'kingdom-saas' ); ?> <b><?php esc_html_e( '2 months free', 'kingdom-saas' ); ?></b></span></label>
		</div>
	</div>
	<div class="ks-plans">
		<?php foreach ( $plans as $id => $p ) : ?>
			<label class="ks-plan<?php echo $p['featured'] ? ' is-featured' : ''; ?>">
				<input type="radio" name="plan" value="<?php echo esc_attr( $id ); ?>" <?php checked( $sub->plan, $id ); ?> required>
				<span class="ks-plan__body">
					<strong><?php echo esc_html( $p['name'] ); ?><?php echo $id === $sub->plan ? ' <em>' . esc_html__( 'Current', 'kingdom-saas' ) . '</em>' : ''; ?></strong>
					<span class="ks-price">
						<?php foreach ( array( 'international', 'local' ) as $r ) : ?>
							<?php foreach ( array( 'month', 'year' ) as $iv ) : ?>
								<?php $pr = KS_Plans::price( $id, $iv, $r ); ?>
								<span class="ks-p ks-p--<?php echo esc_attr( $r . '-' . $iv ); ?>"><?php echo esc_html( ks_money( $pr['amount'], $pr['currency'] ) ); ?><small>/<?php echo 'year' === $iv ? esc_html__( 'year', 'kingdom-saas' ) : esc_html__( 'month', 'kingdom-saas' ); ?></small></span>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</span>
					<small><?php echo esc_html( $p['desc'] ); ?></small>
					<ul><?php foreach ( array_filter( array_map( 'trim', explode( "\n", $p['highlights'] ) ) ) as $h ) : ?><li><?php echo esc_html( $h ); ?></li><?php endforeach; ?></ul>
				</span>
			</label>
		<?php endforeach; ?>
	</div>
	<?php $gws = KS_Billing::gateways(); ?>
	<?php if ( $gws ) : ?>
		<fieldset class="ks-gw">
			<legend><?php esc_html_e( 'Pay with', 'kingdom-saas' ); ?></legend>
			<?php $first = true; foreach ( $gws as $gid => $glabel ) : ?>
				<label class="kca-chip"><input type="radio" name="gateway" value="<?php echo esc_attr( $gid ); ?>" <?php checked( $first ); ?>> <?php echo esc_html( $glabel ); ?></label>
				<?php $first = false; endforeach; ?>
		</fieldset>
		<button class="kcs-btn kcs-btn--lg"><?php esc_html_e( 'Continue to payment', 'kingdom-saas' ); ?></button>
	<?php else : ?>
		<p class="kcs-alert kcs-alert--info"><?php esc_html_e( 'Online payment is being set up. Please contact us to pay.', 'kingdom-saas' ); ?> <?php echo esc_html( ks_get( 'support_email' ) ); ?></p>
	<?php endif; ?>
</form>

<?php if ( 'active' === $sub->status ) : ?>
	<form method="post" class="kca-card ks-cancel">
		<?php if ( $sub->cancel_at_period_end ) : ?>
			<?php echo call_user_func( $fields, 'resume' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php /* translators: %s: date */ ?>
			<p><?php printf( esc_html__( 'Your subscription ends on %s.', 'kingdom-saas' ), esc_html( $date( $due ) ) ); ?></p>
			<button class="kcs-btn"><?php esc_html_e( 'Keep my subscription', 'kingdom-saas' ); ?></button>
		<?php else : ?>
			<?php echo call_user_func( $fields, 'cancel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="kcs-muted"><?php esc_html_e( 'Cancelling keeps your website online until the end of the period you have paid for.', 'kingdom-saas' ); ?></p>
			<button class="kcs-btn kcs-btn--ghost" onclick="return confirm('<?php echo esc_js( __( 'Cancel at the end of this period?', 'kingdom-saas' ) ); ?>')"><?php esc_html_e( 'Cancel subscription', 'kingdom-saas' ); ?></button>
		<?php endif; ?>
	</form>
<?php endif; ?>

<section class="kca-card">
	<h3><?php esc_html_e( 'Billing history', 'kingdom-saas' ); ?></h3>
	<div class="kca-table">
		<table>
			<thead><tr><th><?php esc_html_e( 'Date', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Plan', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-saas' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $invoices as $inv ) : ?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Date', 'kingdom-saas' ); ?>"><?php echo esc_html( $date( $inv->created_at ) ); ?><small><?php echo esc_html( $inv->reference ); ?></small></td>
					<td data-label="<?php esc_attr_e( 'Plan', 'kingdom-saas' ); ?>"><?php echo esc_html( ( KS_Plans::get( $inv->plan )['name'] ?? $inv->plan ) . ' · ' . ( 'year' === $inv->billing_interval ? __( 'yearly', 'kingdom-saas' ) : __( 'monthly', 'kingdom-saas' ) ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Amount', 'kingdom-saas' ); ?>"><?php echo esc_html( ks_money( $inv->amount, $inv->currency ) ); ?><small><?php echo esc_html( $inv->gateway ); ?></small></td>
					<td data-label="<?php esc_attr_e( 'Status', 'kingdom-saas' ); ?>"><?php echo wp_kses_post( ks_badge( $inv->status ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $invoices ) : ?><tr><td colspan="4" class="kcs-muted"><?php esc_html_e( 'No payments yet.', 'kingdom-saas' ); ?></td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</section>
