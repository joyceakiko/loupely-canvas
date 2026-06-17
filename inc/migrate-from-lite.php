<?php
/**
 * Loupely Canvas - migrate from Loupely Canvas Lite
 *
 * Lite saves its global header and footer as theme mods under its own key,
 * which stay in the database after you switch themes. The full theme reads
 * its header and footer from its own options, so on switching they look lost.
 * This offers a one click import of that header and footer, plus a matching
 * starter blog layout for the token boxes. Both are gated so they never
 * overwrite anything you already set.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Header and footer saved by Lite, read from its theme mods.
 *
 * @return array{header:string,footer:string}
 */
function lc_lite_saved_chrome(): array {
    $mods = get_option( 'theme_mods_loupely-canvas-lite' );
    if ( ! is_array( $mods ) ) {
        return [ 'header' => '', 'footer' => '' ];
    }
    return [
        'header' => isset( $mods['lcl_header_html'] ) ? (string) $mods['lcl_header_html'] : '',
        'footer' => isset( $mods['lcl_footer_html'] ) ? (string) $mods['lcl_footer_html'] : '',
    ];
}


/**
 * True when Lite left a header or footer, at least one matching full theme box
 * is still empty, and the offer has not been dismissed.
 */
function lc_lite_migration_available(): bool {
    if ( get_option( 'lc_lite_migrate_dismissed' ) ) {
        return false;
    }
    $lite = lc_lite_saved_chrome();
    if ( trim( $lite['header'] ) === '' && trim( $lite['footer'] ) === '' ) {
        return false;
    }
    $have_header = trim( (string) get_option( 'lc_header_html', '' ) ) !== '';
    $have_footer = trim( (string) get_option( 'lc_footer_html', '' ) ) !== '';
    return ! ( $have_header && $have_footer );
}


/**
 * The migration notice, plus success messages, on the Themes screen and the
 * Loupely Canvas settings screen.
 */
function lc_lite_migration_notice() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, [ 'themes', 'appearance_page_lc-header-footer-html' ], true ) ) {
        return;
    }

    if ( isset( $_GET['lc_migrated'] ) ) {
        $what = sanitize_key( wp_unslash( $_GET['lc_migrated'] ) );
        if ( 'chrome' === $what ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Your header and footer were imported from Loupely Canvas Lite.', 'loupely-canvas' ) . '</p></div>';
        } elseif ( 'blog' === $what ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'A starter blog layout was added. Edit it under Blog templates, and style it from the Head code box.', 'loupely-canvas' ) . '</p></div>';
        }
    }

    if ( ! lc_lite_migration_available() ) {
        return;
    }

    $import  = wp_nonce_url( admin_url( 'admin-post.php?action=lc_migrate_lite_chrome' ), 'lc_migrate_lite', 'lc_migrate_nonce' );
    $dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=lc_migrate_lite_dismiss' ), 'lc_migrate_lite', 'lc_migrate_nonce' );

    echo '<div class="notice notice-info"><p>';
    echo esc_html__( 'Loupely Canvas found a header and footer saved by Loupely Canvas Lite. Your posts and pages already work. Import the header and footer into the full theme? This will not touch anything you have already set.', 'loupely-canvas' );
    echo '</p><p>';
    echo '<a href="' . esc_url( $import ) . '" class="button button-primary">' . esc_html__( 'Import header and footer', 'loupely-canvas' ) . '</a> ';
    echo '<a href="' . esc_url( $dismiss ) . '" class="button">' . esc_html__( 'No thanks', 'loupely-canvas' ) . '</a>';
    echo '</p></div>';
}
add_action( 'admin_notices', 'lc_lite_migration_notice' );


/**
 * Import Lite's header and footer into the full theme's boxes, each only when
 * the target box is empty.
 */
function lc_handle_migrate_lite_chrome() {
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
    }
    check_admin_referer( 'lc_migrate_lite', 'lc_migrate_nonce' );

    $lite = lc_lite_saved_chrome();
    if ( trim( $lite['header'] ) !== '' && trim( (string) get_option( 'lc_header_html', '' ) ) === '' ) {
        update_option( 'lc_header_html', $lite['header'] );
    }
    if ( trim( $lite['footer'] ) !== '' && trim( (string) get_option( 'lc_footer_html', '' ) ) === '' ) {
        update_option( 'lc_footer_html', $lite['footer'] );
    }

    wp_safe_redirect( add_query_arg( 'lc_migrated', 'chrome', admin_url( 'themes.php?page=lc-header-footer-html' ) ) );
    exit;
}
add_action( 'admin_post_lc_migrate_lite_chrome', 'lc_handle_migrate_lite_chrome' );


/**
 * Remember that the offer was declined.
 */
function lc_handle_migrate_lite_dismiss() {
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
    }
    check_admin_referer( 'lc_migrate_lite', 'lc_migrate_nonce' );
    update_option( 'lc_lite_migrate_dismissed', '1' );
    $back = wp_get_referer();
    wp_safe_redirect( $back ? $back : admin_url( 'themes.php' ) );
    exit;
}
add_action( 'admin_post_lc_migrate_lite_dismiss', 'lc_handle_migrate_lite_dismiss' );


/**
 * Starter blog markup, token based and structural. Styling lives in the Head
 * code box, the same as the rest of the theme.
 */
function lc_blog_starter_card(): string {
    return "<article class=\"post-card\">\n"
        . "  <h2 class=\"post-card-title\"><a href=\"{permalink}\">{title}</a></h2>\n"
        . "  <p class=\"post-card-meta\">{date} &middot; {author}</p>\n"
        . "  {thumbnail}\n"
        . "  <div class=\"post-card-excerpt\">{excerpt}</div>\n"
        . "  <p class=\"post-card-more\"><a href=\"{permalink}\">Read more</a></p>\n"
        . "</article>";
}

function lc_blog_starter_single(): string {
    return "<article class=\"post-single\">\n"
        . "  <h1 class=\"post-single-title\">{title}</h1>\n"
        . "  <p class=\"post-single-meta\">{date} &middot; {author}</p>\n"
        . "  {thumbnail}\n"
        . "  <div class=\"post-single-body\">{content}</div>\n"
        . "  <p class=\"post-single-terms\">{categories}</p>\n"
        . "</article>";
}


/**
 * Button shown in the Blog templates section when the Post card and Single
 * post boxes are both empty.
 */
function lc_render_blog_starter_button(): void {
    if ( trim( (string) get_option( 'lc_post_card_html', '' ) ) !== '' || trim( (string) get_option( 'lc_single_post_html', '' ) ) !== '' ) {
        return;
    }
    $url = wp_nonce_url( admin_url( 'admin-post.php?action=lc_migrate_blog_starter' ), 'lc_migrate_lite', 'lc_migrate_nonce' );
    echo '<p style="margin:0 0 14px;">';
    echo '<a href="' . esc_url( $url ) . '" class="button button-secondary">' . esc_html__( 'Load a starter blog layout', 'loupely-canvas' ) . '</a>';
    echo ' <span style="color:#50575e;">' . esc_html__( 'Fills the Post card and Single post boxes with example markup you can edit. Style it from the Head code box.', 'loupely-canvas' ) . '</span>';
    echo '</p>';
}


/**
 * Fill the Post card and Single post boxes with the starter layout, each only
 * when empty.
 */
function lc_handle_migrate_blog_starter() {
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
    }
    check_admin_referer( 'lc_migrate_lite', 'lc_migrate_nonce' );

    if ( trim( (string) get_option( 'lc_post_card_html', '' ) ) === '' ) {
        update_option( 'lc_post_card_html', lc_blog_starter_card() );
    }
    if ( trim( (string) get_option( 'lc_single_post_html', '' ) ) === '' ) {
        update_option( 'lc_single_post_html', lc_blog_starter_single() );
    }

    wp_safe_redirect( add_query_arg( 'lc_migrated', 'blog', admin_url( 'themes.php?page=lc-header-footer-html' ) ) );
    exit;
}
add_action( 'admin_post_lc_migrate_blog_starter', 'lc_handle_migrate_blog_starter' );
