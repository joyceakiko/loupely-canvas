<?php
/**
 * Loupely Canvas - editor tools
 *
 * Loads the find and replace script everywhere people edit HTML in this
 * theme: the block editor (Custom HTML blocks and the code editor view),
 * the settings screen, and the per page override boxes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


function lc_register_find_replace_script() {
    if ( get_option( 'lc_enable_find_replace', '1' ) !== '1' ) {
        return;
    }

    $rel = '/assets/find-replace.js';
    $abs = get_template_directory() . $rel;
    $ver = file_exists( $abs ) ? (string) filemtime( $abs ) : LC_VERSION;

    wp_enqueue_script(
        'lc-find-replace',
        get_template_directory_uri() . $rel,
        [],
        $ver,
        true
    );
}


// Block editor: Custom HTML blocks, Edit as HTML, and the code editor view.
function lc_enqueue_find_replace_editor() {
    lc_register_find_replace_script();
}
add_action( 'enqueue_block_editor_assets', 'lc_enqueue_find_replace_editor' );


// Admin screens that hold our HTML boxes: the settings page and the page
// and post editors (for the per page override boxes and classic editor).
function lc_enqueue_find_replace_admin( $hook ) {
    $screens = [
        'appearance_page_lc-header-footer-html',
        'post.php',
        'post-new.php',
    ];
    if ( in_array( $hook, $screens, true ) ) {
        lc_register_find_replace_script();
    }
}
add_action( 'admin_enqueue_scripts', 'lc_enqueue_find_replace_admin' );


// ===========================================================
// OPTIONAL: HIDE THE PATTERNS AND FONTS MENUS
//
// This is a classic theme and does not use the block Patterns or Font
// Library screens. When the setting is on, remove those links from the
// Appearance menu. This only hides the menu items; the underlying screens
// and core functionality are untouched, so it is safe and reversible.
// Matching is done by slug so it survives WordPress slug changes.
// ===========================================================

function lc_hide_editor_menus() {
    if ( ! get_option( 'lc_hide_editor_menus' ) ) {
        return;
    }
    global $submenu;
    if ( empty( $submenu['themes.php'] ) ) {
        return;
    }
    foreach ( $submenu['themes.php'] as $item ) {
        $slug = isset( $item[2] ) ? strtolower( (string) $item[2] ) : '';
        if ( $slug === '' ) {
            continue;
        }
        if ( strpos( $slug, 'pattern' ) !== false || strpos( $slug, 'font' ) !== false ) {
            remove_submenu_page( 'themes.php', $item[2] );
        }
    }
}
add_action( 'admin_menu', 'lc_hide_editor_menus', 999 );
