<?php
/**
 * Loupely Canvas - functions.php
 *
 * Loader and theme setup. The feature code lives in /inc:
 *   inc/settings-page.php  Appearance settings: header, footer, head and body code
 *   inc/render.php         Outputs header, footer, head and body code with per page logic
 *   inc/global-tokens.php  Header and footer tokens: site fields, year, and nav menus
 *   inc/site-basics.php    Site basics panel: logo, favicon, and menus in one place
 *   inc/post-templates.php Blog passthrough: post card, single post, archive, search and 404
 *   inc/editor-preview.php Carries the Head code design into the editor preview
 *   inc/page-meta.php      Per page settings: header and footer override, title, code, more
 *   inc/starter-content.php  One click example header, footer and page
 *   inc/reset-content.php  Reset the theme settings, or wipe all content, with a typed confirmation
 *   inc/editor-tools.php   Loads the find and replace tool in the editor and settings
 *   inc/lite-migration.php Offers to import a Lite site's header, footer, and per page settings
 *
 * The theme stays out of the way on the front end. Everything visible comes
 * from the HTML you paste. No injected block styles, no container widths.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LC_VERSION', '2.21.0' );


// ===========================================================
// THEME SETUP
// ===========================================================

function lc_theme_setup() {
    load_theme_textdomain( 'loupely-canvas', get_template_directory() . '/languages' );

    add_theme_support( 'title-tag' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
    ] );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'custom-logo', [
        'flex-height' => true,
        'flex-width'  => true,
    ] );
}
add_action( 'after_setup_theme', 'lc_theme_setup' );


// ===========================================================
// ENQUEUE FRONT END STYLES
// ===========================================================

function lc_enqueue_assets() {
    wp_enqueue_style(
        'loupely-canvas',
        get_stylesheet_uri(),
        [],
        LC_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'lc_enqueue_assets' );


// ===========================================================
// COMMENT REPLY SCRIPT
//
// Loads the core threaded-reply script on single posts when threaded
// comments are open, so a Reply link moves the comment form under the
// comment it answers instead of doing nothing.
// ===========================================================

function lc_enqueue_comment_reply() {
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'lc_enqueue_comment_reply' );


// ===========================================================
// PINGBACK LINK
//
// Prints the pingback endpoint in the head on single posts that accept
// pings, so other sites can register a pingback against them.
// ===========================================================

function lc_pingback_header() {
    if ( is_singular() && pings_open() ) {
        printf( '<link rel="pingback" href="%s">' . "\n", esc_url( get_bloginfo( 'pingback_url' ) ) );
    }
}
add_action( 'wp_head', 'lc_pingback_header' );


// ===========================================================
// REMOVE WORDPRESS ADMIN BAR TOP MARGIN
// ===========================================================

function lc_remove_admin_bar_margin() {
    echo '<style>html { margin-top: 0 !important; }</style>';
}
add_action( 'wp_head', 'lc_remove_admin_bar_margin', 99 );


// ===========================================================
// REMOVE BLOCK EDITOR INLINE STYLES THAT OVERRIDE PAGE HTML
// ===========================================================

function lc_remove_block_inline_styles() {
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
}
add_action( 'init', 'lc_remove_block_inline_styles' );


// ===========================================================
// LOAD FEATURE MODULES
// ===========================================================

require get_template_directory() . '/inc/render.php';
require get_template_directory() . '/inc/global-tokens.php';
require get_template_directory() . '/inc/post-templates.php';
require get_template_directory() . '/inc/editor-preview.php';
require get_template_directory() . '/inc/settings-page.php';
require get_template_directory() . '/inc/site-basics.php';
require get_template_directory() . '/inc/page-meta.php';
require get_template_directory() . '/inc/starter-content.php';
require get_template_directory() . '/inc/reset-content.php';
require get_template_directory() . '/inc/editor-tools.php';
require get_template_directory() . '/inc/updater.php';
require get_template_directory() . '/inc/pro-panel.php';
require get_template_directory() . '/inc/lite-migration.php';
