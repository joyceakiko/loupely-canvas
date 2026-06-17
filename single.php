<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Single post. Renders your Single post markup, with tokens replaced.
 * Per page header and footer overrides apply here too, since the override
 * meta box is registered for posts as well as pages.
 */
if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        lc_render_single_post();
    endwhile;
endif;
?>
</main>

<?php get_footer(); ?>
