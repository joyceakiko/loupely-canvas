<?php
/**
 * Loupely Canvas - post templates (the blog passthrough system)
 *
 * Posts use a different rendering path than pages: a loop, not a single
 * Custom HTML block. This module keeps the Canvas promise on that path too.
 * You paste the markup for a post card and a single post into the settings
 * screen, using simple tokens, and the theme fills in only the dynamic
 * values. Nothing else is injected. What you write is what ships.
 *
 * Front-end templates (home.php, single.php, archive.php, search.php,
 * 404.php) call the helpers here. Token replacement escapes each dynamic
 * value individually; the surrounding markup is your raw HTML, gated on the
 * unfiltered_html capability the same way the header and footer boxes are.
 *
 * Per-post tokens (post card and single post boxes):
 *   {title} {permalink} {date} {content} {excerpt}
 *   {thumbnail} {thumbnail_medium} {thumbnail_full}
 *   {thumbnail_url} {thumbnail_medium_url} {thumbnail_full_url}
 *   {author} {author_avatar} {author_bio}
 *   {author_url} {categories} {tags} {post_class}
 *   {comment_count} {comments_link}
 *   {pagination}   archive pagination links (outputs nothing on single posts)
 *   {comments}     full comment thread (single post only; empty on card lists)
 * The lc_post_tokens filter lets a companion plugin add more per-post tokens.
 * Page-level tokens (archive header box):
 *   {archive_title} {archive_description} {search_form}
 * 404 box:
 *   {home_url} {search_form}
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ===========================================================
// PER-POST TOKENS
// ===========================================================

/**
 * Replace per-post tokens in a template with the current loop post's values.
 * Must run inside the loop, after the_post().
 *
 * $context controls which context-only tokens resolve:
 *   'card'   - {pagination} outputs archive links; {comments} is empty
 *   'single' - {comments} outputs the comment thread; {pagination} is empty
 */
function lc_apply_post_tokens( string $template, string $context = 'card' ): string {
    $id = get_the_ID();

    // Thumbnail at three sizes. Each size returns an empty string when no
    // featured image is set, so the token leaves nothing behind rather than
    // a broken img tag.
    $thumbnail        = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'large' ) : '';
    $thumbnail_medium = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'medium' ) : '';
    $thumbnail_full   = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'full' ) : '';
    $thumbnail_url        = has_post_thumbnail( $id ) ? (string) get_the_post_thumbnail_url( $id, 'large' ) : '';
    $thumbnail_medium_url = has_post_thumbnail( $id ) ? (string) get_the_post_thumbnail_url( $id, 'medium' ) : '';
    $thumbnail_full_url   = has_post_thumbnail( $id ) ? (string) get_the_post_thumbnail_url( $id, 'full' ) : '';

    $categories = get_the_category_list( ', ', '', $id );
    $tags       = get_the_tag_list( '', ', ', '', $id );
    if ( is_wp_error( $tags ) ) {
        $tags = '';
    }

    $author_id     = (int) get_the_author_meta( 'ID' );
    $author_avatar = (string) get_avatar( $author_id, 96 );
    $author_bio    = wp_kses_post( (string) get_the_author_meta( 'description' ) );
    $author_url    = (string) get_the_author_meta( 'url' );

    $post_class = esc_attr( implode( ' ', get_post_class( '', $id ) ) );

    $comment_count = (int) get_comments_number( $id );
    $comments_link = (string) get_comments_link( $id );

    // {pagination}: archive pagination links. Meaningful on list contexts
    // (card, archive, search); empty on a single post where there is no loop
    // to paginate.
    if ( $context === 'card' ) {
        ob_start();
        lc_render_pagination();
        $pagination = (string) ob_get_clean();
    } else {
        $pagination = '';
    }

    // {comments}: the full comment thread. Only rendered on a single post
    // where comments are open or there are existing comments. Empty on card
    // lists, where loading a comment thread per post would be incorrect.
    if ( $context === 'single' && ( comments_open( $id ) || get_comments_number( $id ) > 0 ) ) {
        ob_start();
        comments_template();
        $comments = (string) ob_get_clean();
    } else {
        $comments = '';
    }

    // Hide title (Page settings) blanks the title token on this post's own
    // single view, so the title leaves the markup rather than rendering empty.
    // Scoped to the single view, so the post still shows its title in card
    // lists and archives.
    $hide_title = is_singular()
        && (int) get_queried_object_id() === (int) $id
        && get_post_meta( $id, '_lc_hide_title', true ) === '1';

    $replacements = [
        '{title}'              => $hide_title ? '' : esc_html( get_the_title( $id ) ),
        '{permalink}'          => esc_url( get_permalink( $id ) ),
        '{date}'               => esc_html( get_the_date( '', $id ) ),
        '{content}'            => apply_filters( 'the_content', get_the_content( null, false, $id ) ),
        '{excerpt}'            => esc_html( get_the_excerpt( $id ) ),
        '{thumbnail}'          => $thumbnail,
        '{thumbnail_medium}'   => $thumbnail_medium,
        '{thumbnail_full}'     => $thumbnail_full,
        '{thumbnail_url}'          => esc_url( $thumbnail_url ),
        '{thumbnail_medium_url}'   => esc_url( $thumbnail_medium_url ),
        '{thumbnail_full_url}'     => esc_url( $thumbnail_full_url ),
        '{author}'             => esc_html( get_the_author() ),
        '{author_avatar}'      => $author_avatar,
        '{author_bio}'         => $author_bio,
        '{author_url}'         => esc_url( $author_url ),
        '{categories}'         => (string) $categories,
        '{tags}'               => (string) $tags,
        '{post_class}'         => $post_class,
        '{comment_count}'      => esc_html( (string) $comment_count ),
        '{comments_link}'      => esc_url( $comments_link ),
        '{pagination}'         => $pagination,
        '{comments}'           => $comments,
    ];

    // Add-ons extend the per-post token map through this filter, which receives
    // the current post id, so a companion plugin can supply tokens the theme
    // does not know about (custom field values, for example) that resolve to the
    // post in the loop. Each added value is the add-on's responsibility to
    // escape, the same way the theme escapes its own tokens above.
    $replacements = apply_filters( 'lc_post_tokens', $replacements, $id );

    return strtr( $template, $replacements );
}


/**
 * Minimal fallback for a post card, used only when the box is empty so a
 * fresh install is not blank. No imposed visual styling; classes are yours
 * to target. Replace it by filling the Post card box on the settings screen.
 */
function lc_default_post_card(): string {
    return '<article class="lc-post">'
        . '<h2 class="lc-post-title"><a href="{permalink}">{title}</a></h2>'
        . '<p class="lc-post-date">{date}</p>'
        . '<div class="lc-post-excerpt">{excerpt}</div>'
        . '</article>';
}


/**
 * Minimal fallback for a single post, used only when the box is empty.
 */
function lc_default_single_post(): string {
    return '<article class="lc-post">'
        . '<h1 class="lc-post-title">{title}</h1>'
        . '<p class="lc-post-date">{date}</p>'
        . '<div class="lc-post-content">{content}</div>'
        . '</article>';
}


/**
 * Echo one post card (index and archive lists). Runs inside the loop.
 */
function lc_render_post_card(): void {
    $tpl = (string) get_option( 'lc_post_card_html', '' );
    if ( trim( $tpl ) === '' ) {
        $tpl = lc_default_post_card();
    }
    echo lc_apply_post_tokens( $tpl );
}


/**
 * Echo the single post body. Runs inside the loop.
 */
function lc_render_single_post(): void {
    $tpl = (string) get_option( 'lc_single_post_html', '' );
    if ( trim( $tpl ) === '' ) {
        $tpl = lc_default_single_post();
    }
    echo lc_apply_post_tokens( $tpl, 'single' );
}


// ===========================================================
// PAGE-LEVEL: ARCHIVE HEADER, INTRO PASSTHROUGH, PAGINATION
// ===========================================================

/**
 * Compute the title and description for the current archive context.
 * Returns [ title, description ], both already escaped for output.
 */
function lc_archive_header_values(): array {
    if ( is_search() ) {
        $title = sprintf(
            /* translators: %s: search query. */
            esc_html__( 'Search results for: %s', 'loupely-canvas' ),
            esc_html( get_search_query() )
        );
        return [ $title, '' ];
    }

    if ( is_home() ) {
        $posts_page_id = (int) get_option( 'page_for_posts' );
        $title = $posts_page_id > 0 ? esc_html( get_the_title( $posts_page_id ) ) : esc_html( get_bloginfo( 'name' ) );
        return [ $title, '' ];
    }

    return [
        esc_html( wp_strip_all_tags( get_the_archive_title() ) ),
        wp_kses_post( get_the_archive_description() ),
    ];
}


/**
 * Echo the archive header.
 *
 * If the box is filled, your markup is used with page-level tokens replaced.
 * If it is empty and a default is allowed (real archives and search, where a
 * page with no heading is confusing), a minimal single-heading fallback is
 * used. On the blog index the default is suppressed, because the posts page
 * content rendered above the loop is the intended hero there.
 */
function lc_render_archive_header( bool $allow_default = false ): void {
    $tpl = (string) get_option( 'lc_archive_header_html', '' );

    if ( trim( $tpl ) === '' ) {
        if ( ! $allow_default ) {
            return;
        }
        $tpl = '<header class="lc-archive-header"><h1>{archive_title}</h1></header>';
    }

    list( $title, $desc ) = lc_archive_header_values();

    echo strtr( $tpl, [
        '{archive_title}'       => $title,
        '{archive_description}' => $desc,
        '{search_form}'         => lc_search_form(),
    ] );
}


/**
 * On the blog index, echo the content of the page assigned as the Posts page
 * (Settings, Reading) above the loop. WordPress discards that page's content
 * by default; this gives it back, so a pasted hero or intro shows with the
 * post list beneath it.
 */
function lc_render_posts_page_intro(): void {
    $posts_page_id = (int) get_option( 'page_for_posts' );
    if ( $posts_page_id <= 0 ) {
        return;
    }
    $page = get_post( $posts_page_id );
    if ( ! $page || $page->post_status !== 'publish' ) {
        return;
    }
    if ( trim( (string) $page->post_content ) === '' ) {
        return;
    }
    echo apply_filters( 'the_content', $page->post_content );
}


/**
 * Echo the posts pagination, wrapped in a class you can style.
 */
function lc_render_pagination(): void {
    the_posts_pagination( [
        'mid_size'           => 1,
        'class'              => 'lc-pagination',
        'prev_text'          => esc_html__( 'Previous', 'loupely-canvas' ),
        'next_text'          => esc_html__( 'Next', 'loupely-canvas' ),
        'screen_reader_text' => esc_html__( 'Posts navigation', 'loupely-canvas' ),
    ] );
}


// ===========================================================
// 404
// ===========================================================

/**
 * Echo the 404 body. Your markup from the box, or a minimal default.
 */
function lc_render_404(): void {
    $tpl = (string) get_option( 'lc_error_404_html', '' );
    if ( trim( $tpl ) === '' ) {
        $tpl = '<section class="lc-404">'
            . '<h1>' . esc_html__( 'Page not found', 'loupely-canvas' ) . '</h1>'
            . '<p><a href="{home_url}">' . esc_html__( 'Go to the homepage', 'loupely-canvas' ) . '</a></p>'
            . '</section>';
    }
    echo strtr( $tpl, [
        '{home_url}'    => esc_url( home_url( '/' ) ),
        '{search_form}' => lc_search_form(),
    ] );
}


/**
 * Return the site search form markup, for the {search_form} token and the
 * no-results search page. This is the core search form, which the theme opts
 * into as HTML5 markup, so you style it from the Head code box like the rest.
 */
function lc_search_form(): string {
    return (string) get_search_form( [ 'echo' => false ] );
}
