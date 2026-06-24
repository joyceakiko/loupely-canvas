<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Search results. Same post list as archives. When nothing matched, a short
 * message and a search form, so a visitor can try again without leaving.
 */
lc_render_archive_header( true );

if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        lc_render_post_card();
    endwhile;
    // Only render pagination here when the user has not placed the
    // {pagination} token in their Post card template, to avoid double output.
    $lc_card_tpl = trim( (string) get_option( 'lc_post_card_html', '' ) );
    if ( strpos( $lc_card_tpl, '{pagination}' ) === false ) :
        lc_render_pagination();
    endif;
else :
    echo '<p class="lc-no-results">' . esc_html__( 'Nothing matched your search. Try different words.', 'loupely-canvas' ) . '</p>';
    echo lc_search_form();
endif;
?>
</main>

<?php get_footer(); ?>
