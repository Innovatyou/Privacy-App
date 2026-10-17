<?php
/**
 * Search form.
 *
 * @package Kingdom
 */

$kingdom_id = wp_unique_id( 'search-' );
?>
<form role="search" method="get" class="k-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $kingdom_id ); ?>"><?php esc_html_e( 'Search for:', 'kingdom' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $kingdom_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search sermons, events, news…', 'kingdom' ); ?>">
	<button type="submit" aria-label="<?php esc_attr_e( 'Search', 'kingdom' ); ?>"><?php echo kingdom_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
</form>
