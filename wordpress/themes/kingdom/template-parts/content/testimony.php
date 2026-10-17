<?php
/**
 * Testimony card.
 *
 * @package Kingdom
 */

$kingdom_person = get_post_meta( get_the_ID(), '_kcs_person', true );
?>
<figure <?php post_class( 'k-testimony' ); ?> data-reveal>
	<blockquote><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 45 ) ); ?></blockquote>
	<figcaption>
		<?php if ( has_post_thumbnail() ) : ?><?php the_post_thumbnail( 'thumbnail', array( 'class' => 'k-testimony__avatar', 'loading' => 'lazy' ) ); ?><?php endif; ?>
		<span><strong><?php echo esc_html( $kingdom_person ? $kingdom_person : get_the_title() ); ?></strong><?php if ( get_post_meta( get_the_ID(), '_kcs_location', true ) ) : ?><small><?php echo esc_html( get_post_meta( get_the_ID(), '_kcs_location', true ) ); ?></small><?php endif; ?></span>
		<a class="k-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read', 'kingdom' ); ?> <?php echo kingdom_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</figcaption>
</figure>
