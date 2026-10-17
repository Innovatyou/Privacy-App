<?php
/**
 * Sidebar.
 *
 * @package Kingdom
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside class="k-sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'kingdom' ); ?>">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
