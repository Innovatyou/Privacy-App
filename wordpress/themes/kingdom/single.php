<?php
/**
 * Single post (and fallback for ministries, leaders, testimonies).
 *
 * @package Kingdom
 */

get_header();
while ( have_posts() ) :
	the_post();
	$kingdom_type = get_post_type();
	kingdom_page_header();
	?>
	<div class="k-container k-layout<?php echo 'post' === $kingdom_type && is_active_sidebar( 'sidebar-1' ) ? ' has-sidebar' : ''; ?>">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'k-entry k-layout__main' ); ?>>
			<?php if ( 'post' === $kingdom_type ) : ?>
				<?php kingdom_posted_on(); ?>
			<?php endif; ?>

			<?php if ( 'kcs_leader' === $kingdom_type ) : ?>
				<div class="k-profile">
					<?php if ( has_post_thumbnail() ) : ?><div class="k-profile__photo"><?php the_post_thumbnail( 'kingdom-portrait' ); ?></div><?php endif; ?>
					<div>
						<p class="k-eyebrow"><?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_role', true ) ); ?></p>
						<div class="k-entry__content"><?php the_content(); ?></div>
						<div class="k-social">
							<?php foreach ( array( 'facebook', 'instagram', 'x' ) as $kingdom_net ) : ?>
								<?php $kingdom_url = get_post_meta( get_the_ID(), '_kcs_' . $kingdom_net, true ); ?>
								<?php if ( $kingdom_url ) : ?><a href="<?php echo esc_url( $kingdom_url ); ?>" target="_blank" rel="noopener"><?php echo kingdom_icon( $kingdom_net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
							<?php endforeach; ?>
							<?php $kingdom_mail = get_post_meta( get_the_ID(), '_kcs_email', true ); ?>
							<?php if ( $kingdom_mail ) : ?><a href="mailto:<?php echo esc_attr( $kingdom_mail ); ?>"><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
						</div>
					</div>
				</div>
			<?php else : ?>
				<?php if ( has_post_thumbnail() && 'kcs_testimony' !== $kingdom_type ) : ?>
					<figure class="k-entry__media"><?php the_post_thumbnail( 'large' ); ?></figure>
				<?php endif; ?>

				<?php if ( 'kcs_ministry' === $kingdom_type ) : ?>
					<ul class="k-facts">
						<?php
						$kingdom_facts = array(
							'_kcs_leader_name'   => array( 'users', __( 'Leader', 'kingdom' ) ),
							'_kcs_meeting_time'  => array( 'clock', __( 'When', 'kingdom' ) ),
							'_kcs_meeting_place' => array( 'pin', __( 'Where', 'kingdom' ) ),
							'_kcs_contact_email' => array( 'mail', __( 'Contact', 'kingdom' ) ),
						);
						foreach ( $kingdom_facts as $kingdom_key => $kingdom_fact ) :
							$kingdom_val = get_post_meta( get_the_ID(), $kingdom_key, true );
							if ( $kingdom_val ) :
								?>
								<li><?php echo kingdom_icon( $kingdom_fact[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php echo esc_html( $kingdom_fact[1] ); ?></small><?php echo '_kcs_contact_email' === $kingdom_key ? '<a href="mailto:' . esc_attr( $kingdom_val ) . '">' . esc_html( $kingdom_val ) . '</a>' : esc_html( $kingdom_val ); ?></span></li>
								<?php
							endif;
						endforeach;
						?>
					</ul>
				<?php endif; ?>

				<?php if ( 'kcs_testimony' === $kingdom_type ) : ?>
					<?php $kingdom_yt = kingdom_youtube_id( get_post_meta( get_the_ID(), '_kcs_video_url', true ) ); ?>
					<?php if ( $kingdom_yt ) : ?>
						<div class="kcs-embed"><iframe src="<?php echo esc_url( 'https://www.youtube.com/embed/' . $kingdom_yt ); ?>" allowfullscreen loading="lazy" title="<?php the_title_attribute(); ?>"></iframe></div>
					<?php endif; ?>
				<?php endif; ?>

				<div class="k-entry__content">
					<?php
					the_content();
					wp_link_pages();
					?>
				</div>

				<?php if ( 'kcs_testimony' === $kingdom_type ) : ?>
					<p class="k-testimony__by">— <?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_person', true ) ); ?><?php echo get_post_meta( get_the_ID(), '_kcs_location', true ) ? ', ' . esc_html( get_post_meta( get_the_ID(), '_kcs_location', true ) ) : ''; ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php kingdom_share_links(); ?>

			<?php if ( 'post' === $kingdom_type ) : ?>
				<div class="k-tags"><?php the_tags( '', '' ); ?></div>
				<?php
				the_post_navigation(
					array(
						'prev_text' => '<small>' . __( 'Previous', 'kingdom' ) . '</small><span>%title</span>',
						'next_text' => '<small>' . __( 'Next', 'kingdom' ) . '</small><span>%title</span>',
					)
				);
				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
				?>
			<?php endif; ?>
		</article>
		<?php
		if ( 'post' === $kingdom_type ) {
			get_sidebar();
		}
		?>
	</div>
	<?php
endwhile;
get_footer();
