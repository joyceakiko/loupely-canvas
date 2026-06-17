<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Blog index (the page assigned as Posts page under Settings, Reading).
 *
 * Order:
 *   1. The assigned Posts page content, rendered above the loop, so a pasted
 *      hero or intro shows here instead of being discarded by WordPress.
 *   2. An optional archive header (suppressed by default on the index).
 *   3. The post list, each item from your Post card markup.
 *   4. Pagination.
 */
lc_render_posts_page_intro();
lc_render_archive_header( false );

if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        lc_render_post_card();
    endwhile;
    lc_render_pagination();
endif;
?>
</main>

<?php get_footer(); ?>
