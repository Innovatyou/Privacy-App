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
$is_ptn   = kcs_is_partner();
?>
<div class="kcs-plans" style="--kcs-cols:<?php echo (int) max( 1, min( 4, $columns ) ); ?>">
	<?php foreach ( $plans as $plan ) : ?>
		<article class="kcs-plan<?php echo $plan->is_featured ? ' is-featured' : ''; ?>">
			<?php if ( $plan->is_featured ) : ?><span class="kcs-plan__ribbon"><?php esc_html_e( 'Most popular', 'kingdom-church-suite' ); ?></span><?php endif; ?>
			<h3 class="kcs-plan__name"><?php echo esc_html( $plan->name ); ?></h3>
			<div class="kcs-plan__price">
				<?php if ( $plan->amount > 0 ) : ?>
					<strong><?php echo esc_html( kcs_format_money( $plan->amount, $plan->currency ) ); ?></strong>
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
			<?php if ( $is_ptn ) : ?>
				<a class="kcs-btn kcs-btn--block" href="<?php echo esc_url( kcs_page_url( 'partner_dashboard' ) ); ?>"><?php esc_html_e( 'Go to my dashboard', 'kingdom-church-suite' ); ?></a>
			<?php else : ?>
				<a class="kcs-btn kcs-btn--block" href="<?php echo esc_url( add_query_arg( 'plan', (int) $plan->id, $register ) ); ?>"><?php esc_html_e( 'Become a partner', 'kingdom-church-suite' ); ?></a>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
<p class="kcs-plans__login"><?php esc_html_e( 'Already a partner?', 'kingdom-church-suite' ); ?> <a href="<?php echo esc_url( kcs_page_url( 'partner_login' ) ); ?>"><?php esc_html_e( 'Log in to your dashboard', 'kingdom-church-suite' ); ?></a></p>
