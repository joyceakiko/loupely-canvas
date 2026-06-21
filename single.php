<?php get_header(); ?>

<?php
/**
 * Single post. Renders your Single post markup, with tokens replaced, then
 * post navigation and comments. The {comments} token in your Single post
 * template places the comment thread wherever you put it. If you do not use
 * that token, comments render below your markup automatically when they are
 * open or there are existing comments.
 *
 * Per page settings apply here too, since the meta box is registered for
 * posts as well as pages. Full width drops the main.lc-content wrapper.
 */
$lc_unwrap = function_exists( 'lc_page_is_unwrapped' ) && lc_page_is_unwrapped();
?>
<?php if ( ! $lc_unwrap ) : ?><main class="lc-content"><?php endif; ?>
<?php
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

        // Previous and next post links, unless this post hides them under
        // Page settings. Style via .post-navigation.
        if ( ! ( function_exists( 'lc_hide_post_nav' ) && lc_hide_post_nav() ) ) {
            the_post_navigation( [
                'prev_text' => '%title',
                'next_text' => '%title',
            ] );
        }

        // Comments: only rendered here when the user has not placed the
        // {comments} token in their Single post template. When the token is
        // present, comments_template() already ran inside lc_render_single_post()
        // and running it again would duplicate the thread.
        $lc_single_tpl = trim( (string) get_option( 'lc_single_post_html', '' ) );
        $lc_comments_in_template = ( strpos( $lc_single_tpl, '{comments}' ) !== false );
        if ( ! $lc_comments_in_template && ( comments_open() || get_comments_number() ) ) {
            comments_template();
        }
    endwhile;
endif;
?>
<?php if ( ! $lc_unwrap ) : ?></main><?php endif; ?>

<?php get_footer(); ?>
