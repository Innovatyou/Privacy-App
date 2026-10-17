<?php
/**
 * Tithe & Offering form.
 *
 * Override: yourtheme/kingdom-church-suite/tithe-offering.php
 *
 * @package KingdomChurchSuite
 * @var array  $types  Giving types (slug => label).
 * @var string $type   Preselected type.
 * @var string $title  Title.
 * @var string $region international|local.
 * @var string $name   Name.
 * @var string $email  Email.
 * @var string $phone  Phone.
 */

defined( 'ABSPATH' ) || exit;

$gateways = KCS_Payments::enabled_gateways();
$regions  = kcs_regions();
$region   = kcs_sanitize_region( $region );
$type     = isset( $types[ $type ] ) ? $type : (string) key( $types );
$icons    = array(
	'tithe'        => '✚',
	'offering'     => '♥',
	'thanksgiving' => '✦',
	'first-fruit'  => '❀',
	'seed'         => '✿',
);
$hints    = array(
	'tithe'        => __( 'A tenth of your increase', 'kingdom-church-suite' ),
	'offering'     => __( 'A freewill gift of love', 'kingdom-church-suite' ),
	'thanksgiving' => __( 'Celebrate what God has done', 'kingdom-church-suite' ),
	'first-fruit'  => __( 'The first of your harvest', 'kingdom-church-suite' ),
	'seed'         => __( 'Sow towards a harvest', 'kingdom-church-suite' ),
);

if ( ! $gateways ) {
	echo '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'Online giving is not available yet. Please check back soon.', 'kingdom-church-suite' ) . '</div>';
	if ( current_user_can( 'manage_kcs' ) ) {
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=kcs-settings&tab=payments' ) ) . '">' . esc_html__( 'Admin: enable a payment gateway →', 'kingdom-church-suite' ) . '</a></p>';
	}
	return;
}
?>
<form class="kcs-form kcs-pay-form kcs-tithe" data-kcs-payment-form data-kcs-region-scope data-region="<?php echo esc_attr( $region ); ?>">
	<?php if ( $title ) : ?><h3 class="kcs-form__title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>

	<fieldset class="kcs-tithe__types">
		<legend><?php esc_html_e( '1. What are you giving?', 'kingdom-church-suite' ); ?></legend>
		<?php foreach ( $types as $key => $label ) : ?>
			<label class="kcs-tithe__type">
				<input type="radio" name="purpose" value="<?php echo esc_attr( $key ); ?>" <?php checked( $type, $key ); ?>>
				<span>
					<i aria-hidden="true"><?php echo esc_html( $icons[ $key ] ?? '✦' ); ?></i>
					<strong><?php echo esc_html( $label ); ?></strong>
					<?php if ( isset( $hints[ $key ] ) ) : ?><small><?php echo esc_html( $hints[ $key ] ); ?></small><?php endif; ?>
				</span>
			</label>
		<?php endforeach; ?>
	</fieldset>

	<fieldset>
		<legend><?php esc_html_e( '2. How much?', 'kingdom-church-suite' ); ?></legend>
		<?php echo kcs_region_toggle( $region ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="currency" value="<?php echo esc_attr( kcs_region_currency( $region ) ); ?>" data-kcs-currency>
		<div class="kcs-amount-row">
			<span class="kcs-currency kcs-currency--fixed" aria-hidden="true"><?php foreach ( $regions as $rk => $r ) : ?><b class="kcs-in-<?php echo esc_attr( $rk ); ?>"><?php echo esc_html( kcs_currency_symbol( $r['currency'] ) . ' ' . $r['currency'] ); ?></b><?php endforeach; ?></span>
			<label class="kcs-amount"><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?>
				<input type="number" name="amount" min="<?php echo esc_attr( kcs_get_option( 'min_amount', 1 ) ); ?>" step="0.01" required inputmode="decimal">
			</label>
		</div>
		<?php foreach ( $regions as $rk => $r ) : ?>
			<div class="kcs-presets kcs-in-<?php echo esc_attr( $rk ); ?>" role="group" aria-label="<?php esc_attr_e( 'Quick amounts', 'kingdom-church-suite' ); ?>">
				<?php foreach ( kcs_region_amounts( $rk ) as $preset ) : ?>
					<button type="button" class="kcs-preset" data-amount="<?php echo esc_attr( $preset ); ?>"><?php echo esc_html( kcs_currency_symbol( $r['currency'] ) . number_format_i18n( $preset ) ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

		<?php if ( isset( $types['tithe'] ) ) : ?>
			<details class="kcs-tithe__calc" data-kcs-tithe-calc>
				<summary><?php esc_html_e( 'Tithe calculator', 'kingdom-church-suite' ); ?></summary>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'My income / increase', 'kingdom-church-suite' ); ?><input type="number" min="0" step="0.01" inputmode="decimal" data-kcs-income></label>
					<p class="kcs-tithe__ten"><span><?php esc_html_e( 'Your tithe (10%)', 'kingdom-church-suite' ); ?></span><strong data-kcs-tenth>0</strong><button type="button" class="kcs-btn kcs-btn--ghost" data-kcs-use-tenth><?php esc_html_e( 'Use this amount', 'kingdom-church-suite' ); ?></button></p>
				</div>
			</details>
		<?php endif; ?>
	</fieldset>

	<fieldset>
		<legend><?php esc_html_e( '3. Your details', 'kingdom-church-suite' ); ?></legend>
		<div class="kcs-grid-2">
			<label><?php esc_html_e( 'Full name', 'kingdom-church-suite' ); ?><input type="text" name="name" required value="<?php echo esc_attr( $name ); ?>" autocomplete="name"></label>
			<label><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input type="email" name="email" required value="<?php echo esc_attr( $email ); ?>" autocomplete="email"></label>
		</div>
		<label><?php esc_html_e( 'Phone (optional)', 'kingdom-church-suite' ); ?><input type="tel" name="phone" value="<?php echo esc_attr( $phone ); ?>" autocomplete="tel"></label>
		<label><?php esc_html_e( 'Prayer or note (optional)', 'kingdom-church-suite' ); ?><textarea name="note" rows="2" placeholder="<?php esc_attr_e( 'e.g. Thanksgiving for my new job', 'kingdom-church-suite' ); ?>"></textarea></label>
	</fieldset>

	<fieldset class="kcs-gateways">
		<legend><?php esc_html_e( '4. Payment method', 'kingdom-church-suite' ); ?></legend>
		<?php $first = true; foreach ( $gateways as $g ) : ?>
			<label class="kcs-gateway" data-currencies="<?php echo esc_attr( implode( ',', $g->currency_list() ) ); ?>">
				<input type="radio" name="gateway" value="<?php echo esc_attr( $g->id ); ?>" <?php checked( $first ); ?>>
				<span class="kcs-gateway__name kcs-gw-<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->display_title() ); ?></span>
			</label>
			<?php $first = false; endforeach; ?>
	</fieldset>

	<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
	<button type="submit" class="kcs-btn kcs-btn--lg kcs-btn--block"><?php esc_html_e( 'Give now', 'kingdom-church-suite' ); ?></button>
	<p class="kcs-secure">🔒 <?php esc_html_e( 'Secure payment. A receipt is emailed to you straight away.', 'kingdom-church-suite' ); ?></p>
	<div class="kcs-form-msg" aria-live="polite"></div>
</form>
