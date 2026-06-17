<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Category, tag, date, and author archives. An archive header (your markup
 * or a minimal default heading), then the post list from your Post card
 * markup, then pagination.
 */
lc_render_archive_header( true );

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
