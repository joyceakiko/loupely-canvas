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

<?php wp_footer(); ?>
</body>
</html>
