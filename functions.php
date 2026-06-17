<?php
/**
 * Loupely Canvas - functions.php
 *
 * Loader and theme setup. The feature code lives in /inc:
 *   inc/settings-page.php  Appearance settings: header, footer, head and body code
 *   inc/render.php         Outputs header, footer, head and body code with per page logic
 *   inc/post-templates.php Blog passthrough: post card, single post, archive, search and 404
 *   inc/editor-preview.php Carries the Head code design into the editor preview
 *   inc/page-meta.php      Per page header and footer override controls
 *   inc/starter-content.php  One click example header, footer and page
 *   inc/editor-tools.php   Loads the find and replace tool in the editor and settings
 *
 * The theme stays out of the way on the front end. Everything visible comes
 * from the HTML you paste. No injected block styles, no container widths.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LC_VERSION', '2.7.0' );


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
require get_template_directory() . '/inc/post-templates.php';
require get_template_directory() . '/inc/editor-preview.php';
require get_template_directory() . '/inc/settings-page.php';
require get_template_directory() . '/inc/page-meta.php';
require get_template_directory() . '/inc/starter-content.php';
require get_template_directory() . '/inc/editor-tools.php';
require get_template_directory() . '/inc/updater.php';
require get_template_directory() . '/inc/pro-panel.php';
