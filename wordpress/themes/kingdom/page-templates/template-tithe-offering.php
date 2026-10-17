<?php
/**
 * Template Name: Tithe & Offering
 * Template Post Type: page
 *
 * Tithe, offering, thanksgiving and first-fruit giving with an International (USD) / Local switch.
 *
 * @package Kingdom
 */

get_header();
kingdom_page_header( '', __( 'Honour the Lord with your wealth, with the firstfruits of all your crops. — Proverbs 3:9', 'kingdom' ) );
?>
<section class="k-section">
	<div class="k-container k-tithe">
		<div class="k-tithe__intro" data-reveal>
			<span class="k-eyebrow"><?php esc_html_e( 'Worship through giving', 'kingdom' ); ?></span>
			<h2><?php esc_html_e( 'Bring your tithe and offering', 'kingdom' ); ?></h2>
			<p><?php esc_html_e( 'Giving is an act of worship and trust. Your tithes and offerings keep the doors open, support missions and outreach, and care for families in our community.', 'kingdom' ); ?></p>
			<blockquote class="k-tithe__verse">
				<p><?php esc_html_e( '“Bring the whole tithe into the storehouse… Test me in this,” says the Lord Almighty, “and see if I will not throw open the floodgates of heaven.”', 'kingdom' ); ?></p>
				<cite><?php esc_html_e( 'Malachi 3:10', 'kingdom' ); ?></cite>
			</blockquote>
			<ul class="k-tithe__points">
				<li><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><strong><?php esc_html_e( 'International givers', 'kingdom' ); ?></strong> <?php esc_html_e( 'give in US dollars by card or PayPal. This is selected for you.', 'kingdom' ); ?></span></li>
				<li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><strong><?php esc_html_e( 'Local givers', 'kingdom' ); ?></strong> <?php esc_html_e( 'switch to the local currency and pay by card, bank transfer, USSD or mobile money.', 'kingdom' ); ?></span></li>
				<li><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><strong><?php esc_html_e( 'Instant receipt', 'kingdom' ); ?></strong> <?php esc_html_e( 'is emailed to you for every gift.', 'kingdom' ); ?></span></li>
			</ul>
		</div>
		<div class="k-tithe__form k-card" data-reveal>
			<?php
			if ( kingdom_content_has( 'kcs_tithe' ) ) {
				the_post();
				the_content();
			} elseif ( kingdom_plugin_active() ) {
				echo kingdom_shortcode( '[kcs_tithe]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p>' . esc_html__( 'Activate the Kingdom Church Suite plugin to accept tithes and offerings online.', 'kingdom' ) . '</p>';
			}
			?>
		</div>
	</div>
</section>

<section class="k-section k-section--alt">
	<div class="k-container">
		<?php kingdom_section_heading( __( 'Ways to give', 'kingdom' ), __( 'What each gift means', 'kingdom' ) ); ?>
		<div class="k-grid k-grid--4">
			<div class="k-feature" data-reveal data-tilt><span class="k-feature__icon"><?php echo kingdom_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Tithe', 'kingdom' ); ?></h3><p><?php esc_html_e( 'The first tenth of your income, returned to God in obedience and trust.', 'kingdom' ); ?></p></div>
			<div class="k-feature" data-reveal data-tilt><span class="k-feature__icon"><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Offering', 'kingdom' ); ?></h3><p><?php esc_html_e( 'A freewill gift above the tithe, given cheerfully as the Spirit leads.', 'kingdom' ); ?></p></div>
			<div class="k-feature" data-reveal data-tilt><span class="k-feature__icon"><?php echo kingdom_icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'Thanksgiving', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Celebrate a testimony — a birthday, new job, healing or answered prayer.', 'kingdom' ); ?></p></div>
			<div class="k-feature" data-reveal data-tilt><span class="k-feature__icon"><?php echo kingdom_icon( 'hands' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php esc_html_e( 'First fruit & seed', 'kingdom' ); ?></h3><p><?php esc_html_e( 'Honour God with the first of a new season, or sow towards a harvest.', 'kingdom' ); ?></p></div>
		</div>
	</div>
</section>

<section class="k-section">
	<div class="k-container k-container--narrow">
		<?php kingdom_section_heading( __( 'Questions', 'kingdom' ), __( 'Giving FAQ', 'kingdom' ) ); ?>
		<div class="k-faq" data-reveal>
			<details><summary><?php esc_html_e( 'Which currency should I choose?', 'kingdom' ); ?></summary><p><?php esc_html_e( 'If you live outside the country, keep International (US dollars). If you live here, tap Local to give in the local currency with local payment options.', 'kingdom' ); ?></p></details>
			<details><summary><?php esc_html_e( 'Is online giving secure?', 'kingdom' ); ?></summary><p><?php esc_html_e( 'Yes. Payments are processed by trusted providers such as Paystack, Flutterwave, Stripe and PayPal. We never see or store your card details.', 'kingdom' ); ?></p></details>
			<details><summary><?php esc_html_e( 'Will I get a receipt?', 'kingdom' ); ?></summary><p><?php esc_html_e( 'A receipt is emailed to you as soon as your payment is confirmed. Partners can also see every gift in their partner dashboard.', 'kingdom' ); ?></p></details>
			<details><summary><?php esc_html_e( 'Can I still give in church?', 'kingdom' ); ?></summary><p><?php esc_html_e( 'Of course. Offering envelopes are available at every service.', 'kingdom' ); ?></p></details>
		</div>
	</div>
</section>
<?php
get_footer();
