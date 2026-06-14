<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Main content loop.
 *
 * For standard pages: outputs the page's blocks/HTML exactly as authored.
 * No wrapper divs with max-width, no padding, no column constraints.
 * The Custom HTML block content renders directly into the page.
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
