<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Page template.
 *
 * WordPress uses this file for all Pages (vs Posts).
 * Identical to index.php - full-width, zero interference.
 *
 * To use: set any page to this theme. Paste your HTML into
 * a Custom HTML block. Publish. Done.
 *
 * The header/footer pages (slug: site-header, site-footer)
 * are excluded from normal rendering by convention - set them
 * to a private status or simply don't link to them anywhere.
 */
if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        the_content();
    endwhile;
endif;
?>
</main>

<?php get_footer(); ?>
