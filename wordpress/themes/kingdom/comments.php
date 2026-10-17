<?php
/**
 * Comments.
 *
 * @package Kingdom
 */

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="k-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="k-comments__title">
			<?php
			/* translators: %s: number of comments */
			printf( esc_html( _n( '%s comment', '%s comments', get_comments_number(), 'kingdom' ) ), esc_html( number_format_i18n( get_comments_number() ) ) );
			?>
		</h2>
		<ol class="k-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php comment_form( array( 'class_submit' => 'k-btn' ) ); ?>
</section>
