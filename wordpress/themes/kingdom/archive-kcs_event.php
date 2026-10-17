<?php
/**
 * Event archive (upcoming first; ?past=1 for past events).
 *
 * @package Kingdom
 */

get_header();
$kingdom_past = ! empty( $_GET['past'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
kingdom_page_header( is_post_type_archive() ? ( $kingdom_past ? __( 'Past events', 'kingdom' ) : __( 'Upcoming events', 'kingdom' ) ) : '' );
?>
<section class="k-section">
	<div class="k-container">
		<div class="k-filters">
			<div class="k-filters__form">
				<a class="k-btn k-btn--sm<?php echo $kingdom_past ? ' k-btn--outline' : ''; ?>" href="<?php echo esc_url( get_post_type_archive_link( 'kcs_event' ) ); ?>"><?php esc_html_e( 'Upcoming', 'kingdom' ); ?></a>
				<a class="k-btn k-btn--sm<?php echo $kingdom_past ? '' : ' k-btn--outline'; ?>" href="<?php echo esc_url( add_query_arg( 'past', 1, get_post_type_archive_link( 'kcs_event' ) ) ); ?>"><?php esc_html_e( 'Past', 'kingdom' ); ?></a>
				<?php
				$kingdom_cats = get_terms( array( 'taxonomy' => 'kcs_event_cat', 'hide_empty' => true ) );
				if ( $kingdom_cats && ! is_wp_error( $kingdom_cats ) ) :
					?>
					<select data-filter-select aria-label="<?php esc_attr_e( 'Category', 'kingdom' ); ?>">
						<option value="<?php echo esc_url( get_post_type_archive_link( 'kcs_event' ) ); ?>"><?php esc_html_e( 'All categories', 'kingdom' ); ?></option>
						<?php foreach ( $kingdom_cats as $kingdom_cat ) : ?>
							<option value="<?php echo esc_url( get_term_link( $kingdom_cat ) ); ?>" <?php selected( is_tax( 'kcs_event_cat', $kingdom_cat->term_id ) ); ?>><?php echo esc_html( $kingdom_cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( have_posts() ) : ?>
			<div class="k-grid k-grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content/event' );
				endwhile;
				?>
			</div>
			<?php kingdom_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/none' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
