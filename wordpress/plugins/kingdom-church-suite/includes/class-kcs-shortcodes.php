<?php
/**
 * Shortcodes.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes.
 */
class KCS_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public static function init() {
		$map = array(
			'kcs_partnership_plans' => 'plans',
			'kcs_partner_register'  => 'register',
			'kcs_partner_login'     => 'login',
			'kcs_partner_dashboard' => 'dashboard',
			'kcs_give'              => 'give',
			'kcs_tithe'             => 'tithe',
			'kcs_payment_status'    => 'payment_status',
			'kcs_newsletter'        => 'newsletter',
			'kcs_livestream'        => 'livestream',
			'kcs_live_badge'        => 'live_badge',
			'kcs_prayer_form'       => 'prayer_form',
			'kcs_prayer_wall'       => 'prayer_wall',
			'kcs_service_countdown' => 'countdown',
			'kcs_service_times'     => 'service_times',
			'kcs_sermons'           => 'sermons',
			'kcs_events'            => 'events',
			'kcs_verse'             => 'verse',
			'kcs_testimony_form'    => 'testimony_form',
			'kcs_social'            => 'social',
			'kcs_visit_form'        => 'visit_form',
		);
		foreach ( $map as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}
	}

	/**
	 * Partnership plans grid.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function plans( $atts ) {
		$atts = shortcode_atts( array( 'columns' => 4 ), $atts, 'kcs_partnership_plans' );
		return kcs_get_template( 'plans', array( 'plans' => KCS_Plans::all( true ), 'columns' => (int) $atts['columns'] ) );
	}

	/**
	 * Registration form.
	 *
	 * @return string
	 */
	public static function register() {
		if ( kcs_is_partner() ) {
			return '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'You are already a partner.', 'kingdom-church-suite' ) . ' <a href="' . esc_url( kcs_page_url( 'partner_dashboard' ) ) . '">' . esc_html__( 'Go to your dashboard →', 'kingdom-church-suite' ) . '</a></div>';
		}
		return KCS_Portal::messages() . kcs_get_template(
			'partner-register',
			array(
				'plans'    => KCS_Plans::all( true ),
				'selected' => isset( $_GET['plan'] ) ? absint( $_GET['plan'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'user'     => wp_get_current_user(),
			)
		);
	}

	/**
	 * Login form.
	 *
	 * @return string
	 */
	public static function login() {
		if ( is_user_logged_in() ) {
			return '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'You are logged in.', 'kingdom-church-suite' ) . ' <a href="' . esc_url( kcs_page_url( 'partner_dashboard' ) ) . '">' . esc_html__( 'Open dashboard', 'kingdom-church-suite' ) . '</a> · <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Log out', 'kingdom-church-suite' ) . '</a></div>';
		}
		return KCS_Portal::messages() . kcs_get_template( 'partner-login', array() );
	}

	/**
	 * Partner dashboard.
	 *
	 * @return string
	 */
	public static function dashboard() {
		if ( ! is_user_logged_in() ) {
			return KCS_Portal::messages() . kcs_get_template( 'partner-login', array( 'notice' => __( 'Please log in to access your partner dashboard.', 'kingdom-church-suite' ) ) );
		}
		$partner = KCS_Partners::get_by_user( get_current_user_id() );
		if ( ! $partner ) {
			return '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'You are not a partner yet.', 'kingdom-church-suite' ) . ' <a class="kcs-btn" href="' . esc_url( kcs_page_url( 'partner_register' ) ) . '">' . esc_html__( 'Become a partner', 'kingdom-church-suite' ) . '</a></div>';
		}
		$payments = KCS_Payments::query( array( 'partner_id' => $partner->id, 'per_page' => 50 ) );
		$vars     = array(
			'partner'  => $partner,
			'plan'     => $partner->plan_id ? KCS_Plans::get( $partner->plan_id ) : null,
			'plans'    => KCS_Plans::all( true ),
			'payments' => $payments['items'],
			'user'     => wp_get_current_user(),
			'lock'     => KCS_Access::lock_reason( $partner ),
			'tier'     => KCS_Access::tier( $partner ),
			'tiers'    => count( KCS_Access::ranked_plans() ),
			'pastors'  => KCS_Pastors::for_partner( $partner ),
			'news'     => KCS_Announcements::for_partner( $partner ),
		);
		// Locked partners only see the renewal screen until their payment clears.
		return KCS_Portal::messages() . kcs_get_template( $vars['lock'] ? 'partner-locked' : 'partner-dashboard', $vars );
	}

	/**
	 * Give / donation form.
	 *
	 * @param array $atts purpose, amounts, title.
	 * @return string
	 */
	public static function give( $atts ) {
		$atts = shortcode_atts(
			array(
				'purpose' => '',
				'amounts' => '',
				'title'   => '',
			),
			$atts,
			'kcs_give'
		);
		$user = wp_get_current_user();
		return kcs_get_template(
			'payment-form',
			array(
				'context'  => 'donation',
				'purpose'  => sanitize_key( $atts['purpose'] ),
				'amounts'  => $atts['amounts'] ? array_filter( array_map( 'floatval', explode( ',', $atts['amounts'] ) ) ) : kcs_region_amounts( 'international' ),
				'title'    => $atts['title'],
				'amount'   => '',
				'currency' => kcs_get_option( 'currency', 'USD' ),
				'name'     => $user->exists() ? $user->display_name : '',
				'email'    => $user->exists() ? $user->user_email : '',
				'partner'  => null,
			)
		);
	}

	/**
	 * Tithe & Offering form.
	 *
	 * @param array $atts type (preselected giving type), title.
	 * @return string
	 */
	public static function tithe( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'  => isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'tithe', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'title' => __( 'Give your tithe & offering', 'kingdom-church-suite' ),
			),
			$atts,
			'kcs_tithe'
		);
		$user    = wp_get_current_user();
		$partner = $user->exists() ? KCS_Partners::get_by_user( $user->ID ) : null;
		return kcs_get_template(
			'tithe-offering',
			array(
				'types'  => kcs_tithe_types(),
				'type'   => sanitize_key( $atts['type'] ),
				'title'  => $atts['title'],
				'region' => $partner ? kcs_currency_region( $partner->currency ) : 'international',
				'name'   => $user->exists() ? $user->display_name : '',
				'email'  => $user->exists() ? $user->user_email : '',
				'phone'  => $partner ? $partner->phone : '',
			)
		);
	}

	/**
	 * Payment result page.
	 *
	 * @return string
	 */
	public static function payment_status() {
		$ref     = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$payment = $ref ? KCS_Payments::get_by_reference( $ref ) : null;
		return kcs_get_template(
			'payment-status',
			array(
				'payment' => $payment,
				'offline' => ! empty( $_GET['kcs_offline'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	/**
	 * Newsletter form.
	 *
	 * @param array $atts title, text, style (card|inline).
	 * @return string
	 */
	public static function newsletter( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Stay connected', 'kingdom-church-suite' ),
				'text'  => __( 'Get sermons, devotionals and event updates in your inbox.', 'kingdom-church-suite' ),
				'style' => 'card',
			),
			$atts,
			'kcs_newsletter'
		);
		ob_start();
		?>
		<form class="kcs-newsletter kcs-newsletter--<?php echo esc_attr( $atts['style'] ); ?>" data-kcs-newsletter>
			<?php if ( $atts['title'] ) : ?><h3 class="kcs-newsletter__title"><?php echo esc_html( $atts['title'] ); ?></h3><?php endif; ?>
			<?php if ( $atts['text'] ) : ?><p class="kcs-newsletter__text"><?php echo esc_html( $atts['text'] ); ?></p><?php endif; ?>
			<div class="kcs-newsletter__fields">
				<input type="text" name="name" placeholder="<?php esc_attr_e( 'Your name', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Your name', 'kingdom-church-suite' ); ?>">
				<input type="email" name="email" required placeholder="<?php esc_attr_e( 'Email address', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Email address', 'kingdom-church-suite' ); ?>">
				<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
				<button type="submit" class="kcs-btn"><?php esc_html_e( 'Subscribe', 'kingdom-church-suite' ); ?></button>
			</div>
			<div class="kcs-form-msg" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Live stream player.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function livestream( $atts ) {
		return KCS_Livestream::render( (array) $atts );
	}

	/**
	 * Live badge (updates via REST polling).
	 *
	 * @return string
	 */
	public static function live_badge() {
		$status = KCS_Livestream::status();
		$url    = kcs_page_url( 'watch_live' );
		return '<a href="' . esc_url( $url ) . '" class="kcs-live-badge' . ( $status['is_live'] ? ' is-live' : '' ) . '" data-kcs-live-badge><span class="kcs-live-dot"></span><span class="kcs-live-text">' . ( $status['is_live'] ? esc_html__( 'LIVE NOW', 'kingdom-church-suite' ) : esc_html__( 'Watch Online', 'kingdom-church-suite' ) ) . '</span></a>';
	}

	/**
	 * Prayer form.
	 *
	 * @return string
	 */
	public static function prayer_form() {
		ob_start();
		?>
		<form class="kcs-form kcs-prayer-form" data-kcs-prayer>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Name (optional)', 'kingdom-church-suite' ); ?><input type="text" name="name"></label>
				<label><?php esc_html_e( 'Email (optional)', 'kingdom-church-suite' ); ?><input type="email" name="email"></label>
			</div>
			<label><?php esc_html_e( 'Phone (optional)', 'kingdom-church-suite' ); ?><input type="tel" name="phone"></label>
			<label><?php esc_html_e( 'Your prayer request', 'kingdom-church-suite' ); ?><textarea name="request" rows="5" required></textarea></label>
			<label class="kcs-check"><input type="checkbox" name="is_private" value="1" checked> <?php esc_html_e( 'Keep my request private (only the prayer team will see it)', 'kingdom-church-suite' ); ?></label>
			<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<button type="submit" class="kcs-btn"><?php esc_html_e( 'Submit prayer request', 'kingdom-church-suite' ); ?></button>
			<div class="kcs-form-msg" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Prayer wall.
	 *
	 * @param array $atts limit.
	 * @return string
	 */
	public static function prayer_wall( $atts ) {
		$atts  = shortcode_atts( array( 'limit' => 12 ), $atts, 'kcs_prayer_wall' );
		$items = KCS_Prayer::wall( (int) $atts['limit'] );
		if ( ! $items ) {
			return '';
		}
		ob_start();
		echo '<div class="kcs-prayer-wall"><h3>' . esc_html__( 'Prayer Wall', 'kingdom-church-suite' ) . '</h3><div class="kcs-cards">';
		foreach ( $items as $item ) {
			?>
			<article class="kcs-card kcs-prayer-card<?php echo 'answered' === $item->status ? ' is-answered' : ''; ?>">
				<?php if ( 'answered' === $item->status ) : ?><span class="kcs-badge kcs-badge--completed"><?php esc_html_e( 'Answered', 'kingdom-church-suite' ); ?></span><?php endif; ?>
				<p><?php echo esc_html( wp_trim_words( $item->request, 60 ) ); ?></p>
				<footer>
					<small>— <?php echo esc_html( $item->name ? $item->name : __( 'Anonymous', 'kingdom-church-suite' ) ); ?>, <?php echo esc_html( human_time_diff( strtotime( $item->created_at ), current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested ?> <?php esc_html_e( 'ago', 'kingdom-church-suite' ); ?></small>
					<button type="button" class="kcs-pray-btn" data-kcs-pray="<?php echo (int) $item->id; ?>">🙏 <?php esc_html_e( 'I prayed', 'kingdom-church-suite' ); ?> <span><?php echo (int) $item->prayer_count; ?></span></button>
				</footer>
			</article>
			<?php
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	/**
	 * Countdown to next service.
	 *
	 * @param array $atts title.
	 * @return string
	 */
	public static function countdown( $atts ) {
		$atts = shortcode_atts( array( 'title' => '' ), $atts, 'kcs_service_countdown' );
		$next = KCS_Livestream::next_service();
		if ( ! $next ) {
			return '';
		}
		ob_start();
		?>
		<div class="kcs-countdown" data-kcs-countdown="<?php echo esc_attr( $next['timestamp'] ); ?>">
			<div class="kcs-countdown__label"><?php echo esc_html( $atts['title'] ? $atts['title'] : sprintf( /* translators: 1: service name, 2: day */ __( 'Next: %1$s · %2$s', 'kingdom-church-suite' ), $next['name'], $next['day'] ) ); ?></div>
			<div class="kcs-countdown__clock" aria-live="off"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Service times list.
	 *
	 * @return string
	 */
	public static function service_times() {
		$items = KCS_Livestream::schedule();
		if ( ! $items ) {
			return '';
		}
		$out = '<ul class="kcs-service-times">';
		foreach ( $items as $s ) {
			$out .= '<li><strong>' . esc_html( $s['name'] ) . '</strong><span>' . esc_html( date_i18n( 'l', strtotime( $s['day'] ) ) . ' · ' . date_i18n( get_option( 'time_format' ), strtotime( $s['time'] ) ) ) . '</span></li>';
		}
		return $out . '</ul>';
	}

	/**
	 * Latest sermons.
	 *
	 * @param array $atts count, series.
	 * @return string
	 */
	public static function sermons( $atts ) {
		$atts = shortcode_atts( array( 'count' => 3, 'series' => '' ), $atts, 'kcs_sermons' );
		$args = array( 'post_type' => 'kcs_sermon', 'posts_per_page' => (int) $atts['count'] );
		if ( $atts['series'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'kcs_series', 'field' => 'slug', 'terms' => sanitize_title( $atts['series'] ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		$q   = new WP_Query( $args );
		$out = '<div class="kcs-cards">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$yt    = KCS_Post_Types::youtube_id( get_post_meta( get_the_ID(), '_kcs_video_url', true ) );
			$thumb = has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'medium_large' ) : ( $yt ? 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' : '' );
			$out  .= '<article class="kcs-card">' . ( $thumb ? '<a href="' . esc_url( get_permalink() ) . '"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy"></a>' : '' ) . '<h4><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4><small>' . esc_html( get_post_meta( get_the_ID(), '_kcs_scripture', true ) ) . '</small></article>';
		}
		wp_reset_postdata();
		return $out . '</div>';
	}

	/**
	 * Upcoming events.
	 *
	 * @param array $atts count.
	 * @return string
	 */
	public static function events( $atts ) {
		$atts = shortcode_atts( array( 'count' => 3 ), $atts, 'kcs_events' );
		$q    = new WP_Query(
			array(
				'post_type'      => 'kcs_event',
				'posts_per_page' => (int) $atts['count'],
				'meta_key'       => '_kcs_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array( array( 'key' => '_kcs_start', 'value' => current_time( 'Y-m-d\TH:i' ), 'compare' => '>=' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$out  = '<ul class="kcs-events">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$out .= '<li><a href="' . esc_url( get_permalink() ) . '"><strong>' . esc_html( get_the_title() ) . '</strong></a><span>' . esc_html( KCS_Post_Types::format_event_date( get_the_ID() ) ) . '</span></li>';
		}
		wp_reset_postdata();
		return $out . '</ul>';
	}

	/**
	 * Public testimony submission form.
	 *
	 * @return string
	 */
	public static function testimony_form() {
		ob_start();
		?>
		<form class="kcs-form kcs-testimony-form" data-kcs-testimony>
			<h3 class="kcs-form__title"><?php esc_html_e( 'Share your testimony', 'kingdom-church-suite' ); ?></h3>
			<div class="kcs-grid-2">
				<label for="kcs-t-name"><?php esc_html_e( 'Your name', 'kingdom-church-suite' ); ?><input id="kcs-t-name" type="text" name="name" required></label>
				<label for="kcs-t-loc"><?php esc_html_e( 'City / country (optional)', 'kingdom-church-suite' ); ?><input id="kcs-t-loc" type="text" name="location"></label>
			</div>
			<div class="kcs-grid-2">
				<label for="kcs-t-email"><?php esc_html_e( 'Email (not published)', 'kingdom-church-suite' ); ?><input id="kcs-t-email" type="email" name="email"></label>
				<label for="kcs-t-title"><?php esc_html_e( 'Headline (optional)', 'kingdom-church-suite' ); ?><input id="kcs-t-title" type="text" name="title" placeholder="<?php esc_attr_e( 'e.g. Healed after 3 years', 'kingdom-church-suite' ); ?>"></label>
			</div>
			<label for="kcs-t-story"><?php esc_html_e( 'What has God done?', 'kingdom-church-suite' ); ?><textarea id="kcs-t-story" name="story" rows="5" required minlength="20"></textarea></label>
			<label for="kcs-t-video"><?php esc_html_e( 'Video link (optional)', 'kingdom-church-suite' ); ?><input id="kcs-t-video" type="url" name="video_url" placeholder="https://youtu.be/…"></label>
			<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<button type="submit" class="kcs-btn"><?php esc_html_e( 'Submit testimony', 'kingdom-church-suite' ); ?></button>
			<div class="kcs-form-msg" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Plan-your-visit form.
	 *
	 * @return string
	 */
	public static function visit_form() {
		$services = KCS_Livestream::schedule();
		ob_start();
		?>
		<form class="kcs-form kcs-visit-form" data-kcs-visit>
			<h3 class="kcs-form__title"><?php esc_html_e( 'Plan your visit', 'kingdom-church-suite' ); ?></h3>
			<div class="kcs-grid-2">
				<label for="kcs-v-name"><?php esc_html_e( 'Your name', 'kingdom-church-suite' ); ?><input id="kcs-v-name" name="name" required autocomplete="name"></label>
				<label for="kcs-v-phone"><?php esc_html_e( 'Phone / WhatsApp', 'kingdom-church-suite' ); ?><input id="kcs-v-phone" name="phone" type="tel" autocomplete="tel"></label>
				<label for="kcs-v-email"><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input id="kcs-v-email" name="email" type="email" autocomplete="email"></label>
				<label for="kcs-v-date"><?php esc_html_e( 'Which day?', 'kingdom-church-suite' ); ?><input id="kcs-v-date" name="visit_date" type="date" min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></label>
				<label for="kcs-v-service"><?php esc_html_e( 'Service', 'kingdom-church-suite' ); ?><select id="kcs-v-service" name="service"><?php foreach ( $services as $s ) : ?><option><?php echo esc_html( $s['name'] . ' — ' . $s['day'] . ' ' . date_i18n( get_option( 'time_format' ), strtotime( $s['time'] ) ) ); ?></option><?php endforeach; ?></select></label>
				<div class="kcs-grid-2">
					<label for="kcs-v-adults"><?php esc_html_e( 'Adults', 'kingdom-church-suite' ); ?><input id="kcs-v-adults" name="adults" type="number" min="1" max="50" value="1"></label>
					<label for="kcs-v-kids"><?php esc_html_e( 'Children', 'kingdom-church-suite' ); ?><input id="kcs-v-kids" name="kids" type="number" min="0" max="50" value="0"></label>
				</div>
			</div>
			<label for="kcs-v-notes"><?php esc_html_e( 'Anything we should know? (optional)', 'kingdom-church-suite' ); ?><textarea id="kcs-v-notes" name="notes" rows="3"></textarea></label>
			<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<button type="submit" class="kcs-btn"><?php esc_html_e( 'Save my seat', 'kingdom-church-suite' ); ?></button>
			<div class="kcs-form-msg" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Social media connect buttons.
	 *
	 * @return string
	 */
	public static function social() {
		$nets = array(
			'facebook'  => array( 'Facebook', __( 'Like our page', 'kingdom-church-suite' ) ),
			'instagram' => array( 'Instagram', __( 'Follow us', 'kingdom-church-suite' ) ),
			'youtube'   => array( 'YouTube', __( 'Subscribe', 'kingdom-church-suite' ) ),
			'x'         => array( 'X', __( 'Follow', 'kingdom-church-suite' ) ),
			'tiktok'    => array( 'TikTok', __( 'Follow', 'kingdom-church-suite' ) ),
		);
		$out = '';
		foreach ( $nets as $key => $n ) {
			$url = kcs_get_option( 'social_' . $key );
			if ( $url ) {
				$out .= '<a class="kcs-social kcs-social--' . esc_attr( $key ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><strong>' . esc_html( $n[0] ) . '</strong><span>' . esc_html( $n[1] ) . '</span></a>';
			}
		}
		return $out ? '<div class="kcs-socials">' . $out . '</div>' : '';
	}

	/**
	 * Verse of the day.
	 *
	 * @return string
	 */
	public static function verse() {
		$v = KCS_Features::verse_of_the_day();
		return '<blockquote class="kcs-verse"><p>“' . esc_html( $v['text'] ) . '”</p><cite>' . esc_html( $v['ref'] ) . '</cite></blockquote>';
	}
}
