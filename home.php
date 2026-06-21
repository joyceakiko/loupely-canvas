<?php get_header(); ?>

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
 *
 * Per page settings read from the assigned Posts page apply here too. Full
 * width drops the main.lc-content wrapper.
 */
$lc_unwrap = function_exists( 'lc_page_is_unwrapped' ) && lc_page_is_unwrapped();
?>
<?php if ( ! $lc_unwrap ) : ?><main class="lc-content"><?php endif; ?>
<?php
lc_render_posts_page_intro();
if ( ! function_exists( 'lc_hide_archive_header' ) || ! lc_hide_archive_header() ) {
	lc_render_archive_header( false );
}

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
<?php if ( ! $lc_unwrap ) : ?></main><?php endif; ?>

<?php get_footer(); ?>
