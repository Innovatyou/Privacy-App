<?php
/**
 * Live stream player.
 *
 * @package KingdomChurchSuite
 * @var array  $status Live status.
 * @var string $embed  Embed HTML (already escaped/trusted).
 * @var bool   $chat   Show chat.
 * @var string $title  Title.
 */

defined( 'ABSPATH' ) || exit;

$host = wp_parse_url( home_url(), PHP_URL_HOST );
?>
<div class="kcs-live<?php echo $chat ? ' has-chat' : ''; ?><?php echo $status['is_live'] ? ' is-live' : ''; ?>" data-kcs-live>
	<div class="kcs-live__head">
		<?php if ( $status['is_live'] ) : ?>
			<span class="kcs-live-badge is-live"><span class="kcs-live-dot"></span><?php esc_html_e( 'LIVE NOW', 'kingdom-church-suite' ); ?></span>
		<?php elseif ( ! empty( $status['next_service'] ) ) : ?>
			<span class="kcs-live-badge"><?php /* translators: 1: service, 2: day, 3: time */ printf( esc_html__( 'Next: %1$s — %2$s %3$s', 'kingdom-church-suite' ), esc_html( $status['next_service']['name'] ), esc_html( $status['next_service']['day'] ), esc_html( date_i18n( get_option( 'time_format' ), strtotime( $status['next_service']['time'] ) ) ) ); ?></span>
		<?php endif; ?>
		<h3 class="kcs-live__title"><?php echo esc_html( $title ? $title : ( $status['title'] ? $status['title'] : __( 'Watch our service online', 'kingdom-church-suite' ) ) ); ?></h3>
	</div>
	<div class="kcs-live__body">
		<div class="kcs-live__player">
			<?php if ( $embed ) : ?>
				<div class="kcs-embed"><?php echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts or admin-provided embed. ?></div>
			<?php else : ?>
				<div class="kcs-live__offline"><p><?php esc_html_e( 'The live stream has not been configured yet.', 'kingdom-church-suite' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php if ( $chat ) : ?>
			<div class="kcs-live__chat">
				<iframe src="<?php echo esc_url( 'https://www.youtube.com/live_chat?v=' . rawurlencode( $status['video_id'] ) . '&embed_domain=' . rawurlencode( $host ) ); ?>" title="<?php esc_attr_e( 'Live chat', 'kingdom-church-suite' ); ?>" loading="lazy"></iframe>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( ! $status['is_live'] && ! empty( $status['upcoming'] ) ) : ?>
		<div class="kcs-live__upcoming">
			<h4><?php esc_html_e( 'Upcoming streams', 'kingdom-church-suite' ); ?></h4>
			<ul>
				<?php foreach ( $status['upcoming'] as $u ) : ?>
					<li><a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $u['video_id'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $u['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<div class="kcs-live__actions">
		<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( kcs_page_url( 'give' ) ); ?>"><?php esc_html_e( 'Give online', 'kingdom-church-suite' ); ?></a>
		<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( kcs_page_url( 'prayer' ) ); ?>"><?php esc_html_e( 'Request prayer', 'kingdom-church-suite' ); ?></a>
		<button type="button" class="kcs-btn kcs-btn--ghost" data-kcs-share><?php esc_html_e( 'Share', 'kingdom-church-suite' ); ?></button>
	</div>
</div>
