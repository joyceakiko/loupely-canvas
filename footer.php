<?php
/**
 * Renders the site footer.
 *
 * Pulls from Appearance > Header & Footer HTML (the Footer box).
 * If that box is empty, falls back to a published page with the
 * slug 'site-footer'. If neither exists, outputs nothing.
 */
lc_render_footer();
?>

<?php
/**
 * Mount point for the Toy Kit guide runtime. Fires only when the Kit is
 * network-activated on this site, so visitors on sites without the Kit
 * see nothing added here.
 */
if ( function_exists( 'lc_tk_guide_mount' ) ) {
    do_action( 'lc_canvas_guide_mount' );
}
?>
<?php wp_footer(); ?>
</body>
</html>
