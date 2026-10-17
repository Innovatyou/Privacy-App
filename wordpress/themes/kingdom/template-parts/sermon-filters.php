<?php
/**
 * Sermon library filters (series, speaker, topic, search) + podcast links.
 *
 * @package Kingdom
 */

if ( ! taxonomy_exists( 'kcs_series' ) ) {
	return;
}
$kingdom_archive = get_post_type_archive_link( 'kcs_sermon' );
$kingdom_current = get_queried_object();
$kingdom_taxes   = array(
	'kcs_series'  => __( 'All series', 'kingdom' ),
	'kcs_speaker' => __( 'All speakers', 'kingdom' ),
	'kcs_topic'   => __( 'All topics', 'kingdom' ),
);
?>
<div class="k-filters" data-reveal>
	<form class="k-filters__form" method="get" action="<?php echo esc_url( $kingdom_archive ); ?>" data-filter-form>
		<?php foreach ( $kingdom_taxes as $kingdom_tax => $kingdom_label ) : ?>
			<?php $kingdom_terms = get_terms( array( 'taxonomy' => $kingdom_tax, 'hide_empty' => true ) ); ?>
			<?php if ( $kingdom_terms && ! is_wp_error( $kingdom_terms ) ) : ?>
				<select aria-label="<?php echo esc_attr( $kingdom_label ); ?>" data-filter-select>
					<option value="<?php echo esc_url( $kingdom_archive ); ?>"><?php echo esc_html( $kingdom_label ); ?></option>
					<?php foreach ( $kingdom_terms as $kingdom_term ) : ?>
						<option value="<?php echo esc_url( get_term_link( $kingdom_term ) ); ?>" <?php selected( $kingdom_current instanceof WP_Term && $kingdom_current->term_id === $kingdom_term->term_id ); ?>><?php echo esc_html( $kingdom_term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		<?php endforeach; ?>
		<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search sermons…', 'kingdom' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
		<input type="hidden" name="post_type" value="kcs_sermon">
		<button class="k-btn k-btn--sm" type="submit"><?php esc_html_e( 'Search', 'kingdom' ); ?></button>
	</form>
	<?php if ( kingdom_plugin_active() ) : ?>
		<a class="k-link" href="<?php echo esc_url( get_feed_link( 'sermon-podcast' ) ); ?>"><?php echo kingdom_icon( 'headphones' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Podcast feed', 'kingdom' ); ?></a>
	<?php endif; ?>
</div>
