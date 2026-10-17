<?php
/**
 * Partnership plans grid.
 *
 * @package KingdomChurchSuite
 * @var array $plans   Plans.
 * @var int   $columns Columns.
 */

defined( 'ABSPATH' ) || exit;

$freqs    = kcs_frequencies();
$register = kcs_page_url( 'partner_register' );
$partner  = is_user_logged_in() ? KCS_Partners::get_by_user( get_current_user_id() ) : null;
$is_ptn   = (bool) $partner;
$region   = $partner ? kcs_currency_region( $partner->currency ) : 'international';
?>
<div class="kcs-plans-wrap" data-kcs-region-scope data-region="<?php echo esc_attr( $region ); ?>">
<?php echo kcs_region_toggle( $region ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div class="kcs-plans" style="--kcs-cols:<?php echo (int) max( 1, min( 4, $columns ) ); ?>">
	<?php foreach ( $plans as $plan ) : ?>
		<article class="kcs-plan<?php echo $plan->is_featured ? ' is-featured' : ''; ?><?php echo $is_ptn && (int) $partner->plan_id === (int) $plan->id ? ' is-current' : ''; ?>" data-tilt>
			<?php if ( $plan->is_featured ) : ?><span class="kcs-plan__ribbon"><?php esc_html_e( 'Most popular', 'kingdom-church-suite' ); ?></span><?php endif; ?>
			<h3 class="kcs-plan__name"><?php echo esc_html( $plan->name ); ?></h3>
			<div class="kcs-plan__price">
				<?php if ( ! KCS_Plans::is_custom( $plan ) ) : ?>
					<strong><?php echo KCS_Plans::price_html( $plan, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
					<span>/ <?php echo esc_html( strtolower( $freqs[ $plan->frequency ] ?? $plan->frequency ) ); ?></span>
				<?php else : ?>
					<strong><?php esc_html_e( 'Any amount', 'kingdom-church-suite' ); ?></strong>
				<?php endif; ?>
			</div>
			<?php if ( $plan->description ) : ?><p class="kcs-plan__desc"><?php echo esc_html( $plan->description ); ?></p><?php endif; ?>
			<ul class="kcs-plan__benefits">
				<?php foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $plan->benefits ) ) ) as $benefit ) : ?>
					<li><?php echo esc_html( $benefit ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $is_ptn && (int) $partner->plan_id === (int) $plan->id ) : ?>
				<a class="kcs-btn kcs-btn--block kcs-btn--ghost" href="<?php echo esc_url( kcs_page_url( 'partner_dashboard' ) ); ?>"><?php esc_html_e( 'Your current plan', 'kingdom-church-suite' ); ?></a>
			<?php elseif ( $is_ptn ) : ?>
				<?php $mine = KCS_Plans::price( $plan, $region ); ?>
				<a class="kcs-btn kcs-btn--block" href="<?php echo esc_url( add_query_arg( array( 'kcs_action' => 'upgrade', 'plan' => (int) $plan->id ), kcs_page_url( 'partner_dashboard' ) ) ); ?>"><?php echo $mine['amount'] > $partner->amount && $mine['currency'] === $partner->currency ? esc_html__( 'Upgrade to this plan', 'kingdom-church-suite' ) : esc_html__( 'Switch to this plan', 'kingdom-church-suite' ); ?></a>
			<?php else : ?>
				<?php foreach ( array_keys( kcs_regions() ) as $rk ) : ?>
					<a class="kcs-btn kcs-btn--block kcs-in-<?php echo esc_attr( $rk ); ?>" href="<?php echo esc_url( add_query_arg( array( 'plan' => (int) $plan->id, 'region' => $rk ), $register ) ); ?>"><?php esc_html_e( 'Become a partner', 'kingdom-church-suite' ); ?></a>
				<?php endforeach; ?>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
</div>
<p class="kcs-plans__login"><?php esc_html_e( 'Already a partner?', 'kingdom-church-suite' ); ?> <a href="<?php echo esc_url( kcs_page_url( 'partner_login' ) ); ?>"><?php esc_html_e( 'Log in to your dashboard', 'kingdom-church-suite' ); ?></a></p>
