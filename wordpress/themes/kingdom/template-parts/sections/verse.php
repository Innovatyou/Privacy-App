<?php
/**
 * Verse of the day.
 *
 * @package Kingdom
 */

if ( ! kingdom_plugin_active() ) {
	return;
}
$kingdom_v = KCS_Features::verse_of_the_day();
?>
<section class="k-verse-band">
	<div class="k-container k-container--narrow k-center" data-reveal>
		<span class="k-eyebrow k-eyebrow--light"><?php esc_html_e( 'Verse of the day', 'kingdom' ); ?></span>
		<p class="k-verse-band__text">“<?php echo esc_html( $kingdom_v['text'] ); ?>”</p>
		<cite><?php echo esc_html( $kingdom_v['ref'] ); ?></cite>
	</div>
</section>
