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
    // Only render pagination here when the user has not placed the
    // {pagination} token in their Post card template, to avoid double output.
    $lc_card_tpl = trim( (string) get_option( 'lc_post_card_html', '' ) );
    if ( strpos( $lc_card_tpl, '{pagination}' ) === false ) {
        lc_render_pagination();
    }
endif;
?>
</main>

<?php get_footer(); ?>
