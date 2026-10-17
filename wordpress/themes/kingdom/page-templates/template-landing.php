<?php
/**
 * Template Name: Landing Page (no header/footer)
 * Template Post Type: page
 *
 * Blank canvas for campaigns, conferences and crusades.
 *
 * @package Kingdom
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'k-landing' ); ?>>
<?php wp_body_open(); ?>
<main id="content">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
