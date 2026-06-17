<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Single post. Renders your Single post markup, with tokens replaced, then
 * post navigation and comments. Comments use standard WordPress markup, so
 * you style them with your own CSS in the Head code box, the same way you
 * style everything else.
 *
 * Per page header and footer overrides apply here too, since the override
 * meta box is registered for posts as well as pages.
 */
if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        lc_render_single_post();

        // Within-post page links, for a post split with the Page Break block.
        // Outputs nothing for a single-page post. Style via .lc-page-links.
        wp_link_pages( [
            'before' => '<nav class="lc-page-links" aria-label="' . esc_attr__( 'Post page links', 'loupely-canvas' ) . '">',
            'after'  => '</nav>',
        ] );

        the_post_navigation( [
            'prev_text' => '%title',
            'next_text' => '%title',
        ] );

        if ( comments_open() || get_comments_number() ) {
            comments_template();
        }
    endwhile;
endif;
?>
</main>

<?php get_footer(); ?>
