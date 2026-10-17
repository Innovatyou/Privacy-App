<?php
/**
 * Single event: details, map, register, add to calendar.
 *
 * @package Kingdom
 */

get_header();
while ( have_posts() ) :
	the_post();
	$kingdom_id       = get_the_ID();
	$kingdom_location = get_post_meta( $kingdom_id, '_kcs_location', true );
	$kingdom_address  = get_post_meta( $kingdom_id, '_kcs_address', true );
	$kingdom_online   = get_post_meta( $kingdom_id, '_kcs_online_url', true );
	$kingdom_register = get_post_meta( $kingdom_id, '_kcs_register_url', true );
	$kingdom_price    = get_post_meta( $kingdom_id, '_kcs_price', true );
	$kingdom_start    = get_post_meta( $kingdom_id, '_kcs_start', true );
	kingdom_page_header( '', '', has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'kingdom-hero' ) : '' );
	?>
	<article <?php post_class( 'k-section k-event' ); ?>>
		<div class="k-container k-sermon__grid">
			<div class="k-sermon__main">
				<?php if ( $kingdom_start && strtotime( $kingdom_start ) > current_time( 'timestamp' ) ) : // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested ?>
					<div class="kcs-countdown k-event__countdown" data-kcs-countdown="<?php echo esc_attr( ( new DateTimeImmutable( $kingdom_start, wp_timezone() ) )->getTimestamp() ); ?>">
						<div class="kcs-countdown__label"><?php esc_html_e( 'Starts in', 'kingdom' ); ?></div>
						<div class="kcs-countdown__clock"></div>
					</div>
				<?php endif; ?>
				<div class="k-entry__content"><?php the_content(); ?></div>
				<?php if ( $kingdom_address ) : ?>
					<div class="k-map k-map--small">
						<iframe src="<?php echo esc_url( 'https://maps.google.com/maps?q=' . rawurlencode( $kingdom_address ) . '&output=embed' ); ?>" loading="lazy" title="<?php esc_attr_e( 'Map', 'kingdom' ); ?>"></iframe>
					</div>
				<?php endif; ?>
				<?php kingdom_share_links(); ?>
			</div>
			<aside class="k-sermon__aside">
				<div class="k-panel">
					<ul class="k-facts">
						<li><?php echo kingdom_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'When', 'kingdom' ); ?></small><?php echo esc_html( kingdom_event_date() ); ?><?php echo get_post_meta( $kingdom_id, '_kcs_recurrence', true ) ? '<br><em>' . esc_html( get_post_meta( $kingdom_id, '_kcs_recurrence', true ) ) . '</em>' : ''; ?></span></li>
						<?php if ( $kingdom_location || $kingdom_address ) : ?>
							<li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Where', 'kingdom' ); ?></small><?php echo esc_html( $kingdom_location ); ?><?php echo $kingdom_address ? '<br>' . esc_html( $kingdom_address ) : ''; ?></span></li>
						<?php endif; ?>
						<li><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Cost', 'kingdom' ); ?></small><?php echo esc_html( $kingdom_price ? $kingdom_price : __( 'Free', 'kingdom' ) ); ?></span></li>
					</ul>
					<div class="k-btns k-btns--stack">
						<?php if ( $kingdom_register ) : ?><a class="k-btn k-btn--accent k-btn--block" href="<?php echo esc_url( $kingdom_register ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Register now', 'kingdom' ); ?></a><?php endif; ?>
						<?php if ( $kingdom_online ) : ?><a class="k-btn k-btn--block" href="<?php echo esc_url( $kingdom_online ); ?>" target="_blank" rel="noopener"><?php echo kingdom_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Join online', 'kingdom' ); ?></a><?php endif; ?>
						<?php if ( $kingdom_start && class_exists( 'KCS_Features' ) ) : ?>
							<a class="k-btn k-btn--outline k-btn--block" href="<?php echo esc_url( KCS_Features::google_calendar_url( $kingdom_id ) ); ?>" target="_blank" rel="noopener"><?php echo kingdom_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Google Calendar', 'kingdom' ); ?></a>
							<a class="k-btn k-btn--outline k-btn--block" href="<?php echo esc_url( add_query_arg( 'kcs_ics', $kingdom_id, home_url( '/' ) ) ); ?>"><?php echo kingdom_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Apple / Outlook (.ics)', 'kingdom' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</aside>
		</div>
	</article>
	<?php
endwhile;
get_footer();
