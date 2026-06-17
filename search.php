<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Search results. Same post list as archives, with a no-results line when
 * nothing matched.
 */
lc_render_archive_header( true );

if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        lc_render_post_card();
    endwhile;
    lc_render_pagination();
else :
    echo '<p class="lc-no-results">' . esc_html__( 'Nothing matched your search.', 'loupely-canvas' ) . '</p>';
endif;
?>
</main>

<?php get_footer(); ?>
