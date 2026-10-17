<?php
/**
 * Upcoming events.
 *
 * Args: count, layout (grid|list).
 *
 * @package Kingdom
 */

if ( ! post_type_exists( 'kcs_event' ) ) {
	return;
}
$kingdom_args = wp_parse_args( $args ?? array(), array( 'count' => 3, 'layout' => 'grid' ) );
$kingdom_q    = new WP_Query(
	array(
		'post_type'      => 'kcs_event',
		'posts_per_page' => (int) $kingdom_args['count'],
		'meta_key'       => '_kcs_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_kcs_start', 'value' => current_time( 'Y-m-d\TH:i' ), 'compare' => '>=' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	)
);
if ( ! $kingdom_q->have_posts() ) {
	return;
}
?>
<section class="k-section">
	<div class="k-container">
		<div class="k-section__bar">
			<?php kingdom_section_heading( __( 'Events', 'kingdom' ), get_theme_mod( 'kingdom_events_title', __( 'Upcoming events', 'kingdom' ) ), '', 'left' ); ?>
			<a class="k-link" href="<?php echo esc_url( get_post_type_archive_link( 'kcs_event' ) ); ?>"><?php esc_html_e( 'All events', 'kingdom' ); ?> <?php echo kingdom_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
		<?php if ( 'list' === $kingdom_args['layout'] ) : ?>
			<div class="k-event-list">
				<?php
				while ( $kingdom_q->have_posts() ) :
					$kingdom_q->the_post();
					?>
					<a class="k-event-row" href="<?php the_permalink(); ?>" data-reveal>
						<?php kingdom_event_badge(); ?>
						<span class="k-event-row__body"><strong><?php the_title(); ?></strong><small><?php echo esc_html( kingdom_event_date() ); ?><?php echo get_post_meta( get_the_ID(), '_kcs_location', true ) ? ' · ' . esc_html( get_post_meta( get_the_ID(), '_kcs_location', true ) ) : ''; ?></small></span>
						<?php echo kingdom_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				<?php endwhile; ?>
			</div>
		<?php else : ?>
			<div class="k-grid k-grid--3">
				<?php
				while ( $kingdom_q->have_posts() ) :
					$kingdom_q->the_post();
					get_template_part( 'template-parts/content/event' );
				endwhile;
				?>
			</div>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>
	</div>
</section>
