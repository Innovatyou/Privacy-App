<?php
/**
 * Partner dashboard.
 *
 * @package KingdomChurchSuite
 * @var object      $partner  Partner.
 * @var object|null $plan     Plan.
 * @var array       $plans    Active plans.
 * @var array       $payments Payments.
 * @var WP_User     $user     User.
 */

defined( 'ABSPATH' ) || exit;

$freqs    = kcs_frequencies();
$statuses = KCS_Partners::statuses();
$purposes = kcs_giving_purposes();
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$welcome = ! empty( $_GET['welcome'] );
$action  = isset( $_GET['kcs_action'] ) ? sanitize_key( $_GET['kcs_action'] ) : '';
$tab     = 'pay' === $action ? 'give' : ( 'upgrade' === $action ? 'plan' : 'overview' );
$changed = isset( $_GET['plan_changed'] ) ? sanitize_key( $_GET['plan_changed'] ) : '';
$focus   = isset( $_GET['plan'] ) ? absint( $_GET['plan'] ) : 0;
// phpcs:enable
$completed = array_filter(
	$payments,
	function ( $p ) {
		return 'completed' === $p->status;
	}
);
?>
<div class="kcs-dashboard" data-kcs-tabs data-active="<?php echo esc_attr( $tab ); ?>">
	<header class="kcs-dashboard__head">
		<div>
			<h2><?php /* translators: %s: first name */ printf( esc_html__( 'Welcome, %s 👋', 'kingdom-church-suite' ), esc_html( $partner->first_name ) ); ?></h2>
			<p><?php esc_html_e( 'Partner ID', 'kingdom-church-suite' ); ?>: <strong><?php echo esc_html( $partner->partner_code ); ?></strong> · <?php echo kcs_status_badge( $partner->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="kcs-tier kcs-tier--<?php echo (int) $tier; ?>" title="<?php esc_attr_e( 'Partnership level', 'kingdom-church-suite' ); ?>"><?php echo esc_html( str_repeat( '★', (int) $tier ) ); ?> <?php echo esc_html( $plan ? $plan->name : __( 'Custom', 'kingdom-church-suite' ) ); ?></span></p>
		</div>
		<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'kingdom-church-suite' ); ?></a>
	</header>

	<?php $due_in = KCS_Access::days_to_due( $partner ); ?>
	<?php if ( null !== $due_in && $due_in <= 5 ) : ?>
		<div class="kcs-alert <?php echo $due_in < 0 ? 'kcs-alert--error' : ''; ?>">
			<?php
			if ( $due_in < 0 ) {
				/* translators: %d: days */
				printf( esc_html__( 'Your partnership seed was due %d day(s) ago. Renew now to keep access to your dashboard.', 'kingdom-church-suite' ), absint( $due_in ) );
			} else {
				/* translators: %d: days */
				printf( esc_html__( 'Your next partnership seed is due in %d day(s).', 'kingdom-church-suite' ), (int) $due_in );
			}
			?>
			<button type="button" class="kcs-btn" data-tab-link="give"><?php esc_html_e( 'Give now', 'kingdom-church-suite' ); ?></button>
		</div>
	<?php endif; ?>

	<?php if ( $changed ) : ?>
		<div class="kcs-alert kcs-alert--success"><?php /* translators: %s: upgraded/downgraded/switched */ printf( esc_html__( 'Your partnership has been %s. Thank you! You can give your first seed on the new plan below.', 'kingdom-church-suite' ), esc_html( $changed ) ); ?></div>
	<?php endif; ?>

	<?php if ( $welcome ) : ?>
		<div class="kcs-alert kcs-alert--success"><?php esc_html_e( 'Welcome to the partnership family! Complete your first seed below to activate your partnership.', 'kingdom-church-suite' ); ?></div>
	<?php endif; ?>

	<nav class="kcs-tabs" role="tablist">
		<button type="button" role="tab" data-tab="overview"><?php esc_html_e( 'Overview', 'kingdom-church-suite' ); ?></button>
		<button type="button" role="tab" data-tab="give"><?php esc_html_e( 'Give', 'kingdom-church-suite' ); ?></button>
		<button type="button" role="tab" data-tab="pastors"><?php esc_html_e( 'Chat with a pastor', 'kingdom-church-suite' ); ?></button>
		<button type="button" role="tab" data-tab="news"><?php esc_html_e( 'Updates', 'kingdom-church-suite' ); ?><?php echo $news ? ' <em class="kcs-count">' . count( $news ) . '</em>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<button type="button" role="tab" data-tab="plan"><?php esc_html_e( 'Upgrade plan', 'kingdom-church-suite' ); ?></button>
		<button type="button" role="tab" data-tab="history"><?php esc_html_e( 'Giving history', 'kingdom-church-suite' ); ?></button>
		<button type="button" role="tab" data-tab="profile"><?php esc_html_e( 'Profile', 'kingdom-church-suite' ); ?></button>
	</nav>

	<section class="kcs-tab-panel" data-panel="overview">
		<div class="kcs-stats">
			<div class="kcs-stat"><span><?php esc_html_e( 'Partnership', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( $plan ? $plan->name : __( 'Custom', 'kingdom-church-suite' ) ); ?></strong></div>
			<div class="kcs-stat"><span><?php esc_html_e( 'Commitment', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( kcs_format_money( $partner->amount, $partner->currency ) ); ?></strong><small><?php echo esc_html( $freqs[ $partner->frequency ] ?? '' ); ?></small></div>
			<div class="kcs-stat"><span><?php esc_html_e( 'Next due', 'kingdom-church-suite' ); ?></span><strong><?php echo $partner->next_due_date && 'one_time' !== $partner->frequency ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $partner->next_due_date ) ) ) : '—'; ?></strong></div>
			<div class="kcs-stat"><span><?php esc_html_e( 'Total given', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( kcs_format_money( $partner->total_given, $partner->currency ) ); ?></strong><small><?php /* translators: %d: number of gifts */ printf( esc_html( _n( '%d gift', '%d gifts', count( $completed ), 'kingdom-church-suite' ) ), count( $completed ) ); ?></small></div>
		</div>
		<?php $benefits = $plan ? array_filter( array_map( 'trim', explode( "\n", (string) $plan->benefits ) ) ) : array(); ?>
		<?php if ( $benefits ) : ?>
			<div class="kcs-benefits">
				<h3><?php /* translators: %s: plan */ printf( esc_html__( 'Your %s benefits', 'kingdom-church-suite' ), esc_html( $plan->name ) ); ?></h3>
				<ul class="kcs-plan__benefits"><?php foreach ( $benefits as $b ) : ?><li><?php echo esc_html( $b ); ?></li><?php endforeach; ?></ul>
				<button type="button" class="kcs-btn kcs-btn--ghost" data-tab-link="plan"><?php esc_html_e( 'Compare & upgrade', 'kingdom-church-suite' ); ?></button>
			</div>
		<?php endif; ?>
		<div class="kcs-cta-card">
			<div>
				<h3><?php esc_html_e( 'Thank you for standing with us', 'kingdom-church-suite' ); ?></h3>
				<p><?php esc_html_e( 'Your partnership is helping us preach the gospel, support families and build the kingdom.', 'kingdom-church-suite' ); ?></p>
			</div>
			<button type="button" class="kcs-btn" data-tab-link="give"><?php esc_html_e( 'Give now', 'kingdom-church-suite' ); ?></button>
		</div>
	</section>

	<section class="kcs-tab-panel" data-panel="give">
		<?php
		echo kcs_get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'payment-form',
			array(
				'context'  => 'partnership',
				'purpose'  => 'partnership',
				'amounts'  => array(),
				'title'    => __( 'Give your partnership seed', 'kingdom-church-suite' ),
				'amount'   => (float) $partner->amount,
				'currency' => $partner->currency,
				'name'     => '',
				'email'    => '',
				'partner'  => $partner,
			)
		);
		?>
		<p class="kcs-muted"><?php esc_html_e( 'Want to give a tithe, offering or towards a project instead?', 'kingdom-church-suite' ); ?> <a href="<?php echo esc_url( kcs_page_url( 'give' ) ); ?>"><?php esc_html_e( 'Use the general giving page', 'kingdom-church-suite' ); ?></a></p>
	</section>

	<section class="kcs-tab-panel" data-panel="pastors">
		<h3><?php esc_html_e( 'Chat with a pastor on WhatsApp', 'kingdom-church-suite' ); ?></h3>
		<p class="kcs-muted">
			<?php
			echo $tier >= $tiers
				? esc_html__( 'As a top-level partner you can reach every pastor directly.', 'kingdom-church-suite' )
				: esc_html__( 'These pastors are available to partners on your plan. Upgrade to reach more of the pastoral team.', 'kingdom-church-suite' );
			?>
		</p>
		<div class="kcs-pastors">
			<?php foreach ( $pastors as $pastor ) : ?>
				<a class="kcs-pastor" href="<?php echo esc_url( KCS_Pastors::whatsapp_link( $pastor, $partner ) ); ?>" target="_blank" rel="noopener">
					<?php if ( $pastor['photo'] ) : ?><img src="<?php echo esc_url( $pastor['photo'] ); ?>" alt=""><?php else : ?><span class="kcs-pastor__avatar"><?php echo esc_html( mb_substr( $pastor['name'], 0, 1 ) ); ?></span><?php endif; ?>
					<span class="kcs-pastor__body"><strong><?php echo esc_html( $pastor['name'] ); ?></strong><small><?php echo esc_html( $pastor['role'] ); ?></small></span>
					<span class="kcs-pastor__wa"><?php esc_html_e( 'Chat', 'kingdom-church-suite' ); ?></span>
				</a>
			<?php endforeach; ?>
			<?php if ( ! $pastors ) : ?><p class="kcs-muted"><?php esc_html_e( 'Pastoral chat is not set up for your plan yet.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
		</div>
		<?php if ( $tier < $tiers ) : ?><p><button type="button" class="kcs-btn kcs-btn--ghost" data-tab-link="plan"><?php esc_html_e( 'See plans with more pastors', 'kingdom-church-suite' ); ?></button></p><?php endif; ?>
	</section>

	<section class="kcs-tab-panel" data-panel="news">
		<h3><?php esc_html_e( 'Partner updates', 'kingdom-church-suite' ); ?></h3>
		<div class="kcs-news">
			<?php foreach ( $news as $item ) : ?>
				<article class="kcs-news__item kcs-news__item--<?php echo esc_attr( $item->kind ); ?>">
					<span class="kcs-news__kind"><?php echo esc_html( array( 'news' => __( 'Announcement', 'kingdom-church-suite' ), 'event' => __( 'Event', 'kingdom-church-suite' ), 'resource' => __( 'Resource', 'kingdom-church-suite' ) )[ $item->kind ] ?? '' ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ), $item->created_at ) ); ?></span>
					<h4><?php echo esc_html( $item->title ); ?></h4>
					<div><?php echo wp_kses_post( wpautop( KCS_Email::replace( $item->body, array( 'first_name' => $partner->first_name, 'name' => trim( $partner->first_name . ' ' . $partner->last_name ), 'email' => $partner->email, 'unsubscribe_url' => '' ) ) ) ); ?></div>
					<?php if ( $item->link ) : ?><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( $item->link ); ?>" target="_blank" rel="noopener"><?php echo 'resource' === $item->kind ? esc_html__( 'Open resource', 'kingdom-church-suite' ) : esc_html__( 'Learn more', 'kingdom-church-suite' ); ?></a><?php endif; ?>
				</article>
			<?php endforeach; ?>
			<?php if ( ! $news ) : ?><p class="kcs-muted"><?php esc_html_e( 'No updates yet. The church team will post partner news here.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
		</div>
	</section>

	<section class="kcs-tab-panel" data-panel="plan">
		<h3><?php esc_html_e( 'Choose your partnership level', 'kingdom-church-suite' ); ?></h3>
		<p class="kcs-muted"><?php esc_html_e( 'Upgrade, downgrade or switch at any time. Your next due date stays the same.', 'kingdom-church-suite' ); ?></p>
		<div class="kcs-plans kcs-plans--compare">
			<?php
			$per_month = array( 'weekly' => 52 / 12, 'monthly' => 1, 'quarterly' => 1 / 3, 'yearly' => 1 / 12, 'one_time' => 1 / 12 );
			$current_v = (float) $partner->amount * ( $per_month[ $partner->frequency ] ?? 1 );
			foreach ( $plans as $pl ) :
				$is_current = (int) $pl->id === (int) $partner->plan_id;
				$custom     = $pl->allow_custom_amount || $pl->amount <= 0;
				$value      = (float) $pl->amount * ( $per_month[ $pl->frequency ] ?? 1 );
				$label      = $custom ? __( 'Choose amount', 'kingdom-church-suite' ) : ( $value > $current_v ? __( 'Upgrade', 'kingdom-church-suite' ) : ( $value < $current_v ? __( 'Downgrade', 'kingdom-church-suite' ) : __( 'Switch', 'kingdom-church-suite' ) ) );
				?>
				<form method="post" class="kcs-plan<?php echo $is_current ? ' is-current' : ''; ?><?php echo $pl->is_featured ? ' is-featured' : ''; ?><?php echo $focus === (int) $pl->id ? ' is-focus' : ''; ?>">
					<?php if ( $is_current ) : ?><span class="kcs-plan__ribbon"><?php esc_html_e( 'Your plan', 'kingdom-church-suite' ); ?></span><?php elseif ( $pl->is_featured ) : ?><span class="kcs-plan__ribbon"><?php esc_html_e( 'Most popular', 'kingdom-church-suite' ); ?></span><?php endif; ?>
					<h4 class="kcs-plan__name"><?php echo esc_html( $pl->name ); ?></h4>
					<div class="kcs-plan__price">
						<?php if ( ! $custom ) : ?>
							<strong><?php echo esc_html( kcs_format_money( $pl->amount, $pl->currency ) ); ?></strong> <span>/ <?php echo esc_html( strtolower( $freqs[ $pl->frequency ] ?? '' ) ); ?></span>
						<?php else : ?>
							<strong><?php esc_html_e( 'Your amount', 'kingdom-church-suite' ); ?></strong>
						<?php endif; ?>
					</div>
					<ul class="kcs-plan__benefits"><?php foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $pl->benefits ) ) ) as $b ) : ?><li><?php echo esc_html( $b ); ?></li><?php endforeach; ?></ul>
					<?php if ( $custom ) : ?>
						<div class="kcs-grid-2">
							<label for="kcs-amt-<?php echo (int) $pl->id; ?>"><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?> (<?php echo esc_html( $partner->currency ); ?>)<input id="kcs-amt-<?php echo (int) $pl->id; ?>" type="number" step="0.01" min="1" name="amount" value="<?php echo esc_attr( $is_current ? $partner->amount : '' ); ?>" required></label>
							<label for="kcs-frq-<?php echo (int) $pl->id; ?>"><?php esc_html_e( 'Frequency', 'kingdom-church-suite' ); ?>
								<select id="kcs-frq-<?php echo (int) $pl->id; ?>" name="frequency"><?php foreach ( $freqs as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $partner->frequency, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
							</label>
						</div>
					<?php endif; ?>
					<input type="hidden" name="plan_id" value="<?php echo (int) $pl->id; ?>">
					<input type="hidden" name="kcs_form" value="change_plan">
					<?php wp_nonce_field( 'kcs_change_plan', '_kcs_nonce' ); ?>
					<?php if ( $is_current && ! $custom ) : ?>
						<button type="button" class="kcs-btn kcs-btn--block kcs-btn--ghost" disabled><?php esc_html_e( 'Current plan', 'kingdom-church-suite' ); ?></button>
					<?php else : ?>
						<button type="submit" class="kcs-btn kcs-btn--block"><?php echo esc_html( $is_current ? __( 'Update amount', 'kingdom-church-suite' ) : $label ); ?></button>
					<?php endif; ?>
				</form>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="kcs-tab-panel" data-panel="history">
		<?php if ( $payments ) : ?>
			<div class="kcs-table-wrap">
				<table class="kcs-table">
					<thead><tr><th><?php esc_html_e( 'Date', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Reference', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Purpose', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $payments as $p ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->paid_at ? $p->paid_at : $p->created_at ) ) ); ?></td>
							<td><code><?php echo esc_html( $p->reference ); ?></code></td>
							<td><?php echo esc_html( $purposes[ $p->purpose ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $p->purpose ) ) ); ?></td>
							<td><?php echo esc_html( kcs_format_money( $p->amount, $p->currency ) ); ?></td>
							<td><?php echo kcs_status_badge( $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="kcs-muted"><?php esc_html_e( 'No payments yet.', 'kingdom-church-suite' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="kcs-tab-panel" data-panel="profile">
		<div class="kcs-grid-2 kcs-grid-2--top">
			<form method="post" class="kcs-form">
				<h3 class="kcs-form__title"><?php esc_html_e( 'My details', 'kingdom-church-suite' ); ?></h3>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'First name', 'kingdom-church-suite' ); ?><input type="text" name="first_name" value="<?php echo esc_attr( $partner->first_name ); ?>" required></label>
					<label><?php esc_html_e( 'Last name', 'kingdom-church-suite' ); ?><input type="text" name="last_name" value="<?php echo esc_attr( $partner->last_name ); ?>"></label>
				</div>
				<label><?php esc_html_e( 'Phone', 'kingdom-church-suite' ); ?><input type="tel" name="phone" value="<?php echo esc_attr( $partner->phone ); ?>"></label>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Country', 'kingdom-church-suite' ); ?><input type="text" name="country" value="<?php echo esc_attr( $partner->country ); ?>"></label>
					<label><?php esc_html_e( 'City', 'kingdom-church-suite' ); ?><input type="text" name="city" value="<?php echo esc_attr( $partner->city ); ?>"></label>
				</div>
				<label><?php esc_html_e( 'Address', 'kingdom-church-suite' ); ?><textarea name="address" rows="2"><?php echo esc_textarea( $partner->address ); ?></textarea></label>
				<label class="kcs-check"><input type="checkbox" name="email_opt_in" value="1" <?php checked( (int) $partner->email_opt_in, 1 ); ?>> <?php esc_html_e( 'Receive partner updates & newsletter', 'kingdom-church-suite' ); ?></label>
				<?php if ( 'paused' === $partner->status ) : ?>
					<label class="kcs-check"><input type="checkbox" name="resume" value="1"> <?php esc_html_e( 'Resume my partnership', 'kingdom-church-suite' ); ?></label>
				<?php else : ?>
					<label class="kcs-check"><input type="checkbox" name="pause" value="1"> <?php esc_html_e( 'Pause my partnership (stop reminders)', 'kingdom-church-suite' ); ?></label>
				<?php endif; ?>
				<input type="hidden" name="kcs_form" value="profile">
				<?php wp_nonce_field( 'kcs_profile', '_kcs_nonce' ); ?>
				<button type="submit" class="kcs-btn"><?php esc_html_e( 'Save changes', 'kingdom-church-suite' ); ?></button>
			</form>

			<form method="post" class="kcs-form">
				<h3 class="kcs-form__title"><?php esc_html_e( 'Change password', 'kingdom-church-suite' ); ?></h3>
				<label><?php esc_html_e( 'Current password', 'kingdom-church-suite' ); ?><input type="password" name="current" required autocomplete="current-password"></label>
				<label><?php esc_html_e( 'New password', 'kingdom-church-suite' ); ?><input type="password" name="new" minlength="8" required autocomplete="new-password"></label>
				<label><?php esc_html_e( 'Confirm new password', 'kingdom-church-suite' ); ?><input type="password" name="new2" minlength="8" required autocomplete="new-password"></label>
				<input type="hidden" name="kcs_form" value="password">
				<?php wp_nonce_field( 'kcs_password', '_kcs_nonce' ); ?>
				<button type="submit" class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Update password', 'kingdom-church-suite' ); ?></button>
			</form>
		</div>
	</section>
	<?php if ( $pastors ) : ?>
		<button type="button" class="kcs-wa-fab" data-tab-link="pastors" aria-label="<?php esc_attr_e( 'Chat with a pastor on WhatsApp', 'kingdom-church-suite' ); ?>"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 21l1.6-4.6A8.5 8.5 0 1 1 8 19.6z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1.2-1.2-1.7-1-1 .7a4 4 0 0 1-2-2l.7-1-1-1.7z"/></svg></button>
	<?php endif; ?>
</div>
