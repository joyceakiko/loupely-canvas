<?php
/**
 * Loupely Canvas - render helpers
 *
 * Decides what header and footer to output, honoring per page overrides
 * first, then the global settings, then the legacy page slug fallback.
 * Also prints the global head and body end code.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Render the site header.
 *
 * Order of precedence:
 *   1. Per page override (none, or custom HTML for this page)
 *   2. Global header HTML from the settings screen
 *   3. A published page with the slug site-header (legacy fallback)
 */
function lc_render_header(): void {
    $obj = get_queried_object();
    if ( $obj instanceof WP_Post ) {
        $mode = get_post_meta( $obj->ID, '_lc_header_mode', true );
        if ( $mode === 'none' ) {
            return;
        }
        if ( $mode === 'custom' ) {
            $custom = get_post_meta( $obj->ID, '_lc_header_custom', true );
            if ( trim( (string) $custom ) !== '' ) {
                echo $custom;
                return;
            }
        }
    }

    $html = get_option( 'lc_header_html', '' );
    if ( trim( (string) $html ) !== '' ) {
        echo $html;
        return;
    }

    lc_render_page_by_slug( 'site-header' );
}


/**
 * Render the site footer. Same precedence as the header.
 */
function lc_render_footer(): void {
    $obj = get_queried_object();
    if ( $obj instanceof WP_Post ) {
        $mode = get_post_meta( $obj->ID, '_lc_footer_mode', true );
        if ( $mode === 'none' ) {
            return;
        }
        if ( $mode === 'custom' ) {
            $custom = get_post_meta( $obj->ID, '_lc_footer_custom', true );
            if ( trim( (string) $custom ) !== '' ) {
                echo $custom;
                return;
            }
        }
    }

    $html = get_option( 'lc_footer_html', '' );
    if ( trim( (string) $html ) !== '' ) {
        echo $html;
        return;
    }

    lc_render_page_by_slug( 'site-footer' );
}


/**
 * Legacy fallback: render a published page's content by slug.
 */
function lc_render_page_by_slug( string $slug ): void {
    $page = get_page_by_path( $slug );
    if ( ! $page || $page->post_status !== 'publish' ) {
        return;
    }
    echo apply_filters( 'the_content', $page->post_content );
}


// ===========================================================
// GLOBAL HEAD AND BODY CODE INJECTION
//
// Lets people add analytics, fonts, favicons, verification and
// meta tags without editing theme files. Printed late so it can
// override or supplement core output.
// ===========================================================

function lc_print_head_code(): void {
    $code = get_option( 'lc_head_html', '' );
    if ( trim( (string) $code ) !== '' ) {
        echo "\n" . $code . "\n";
    }
}
add_action( 'wp_head', 'lc_print_head_code', 99 );


function lc_print_body_end_code(): void {
    $code = get_option( 'lc_body_end_html', '' );
    if ( trim( (string) $code ) !== '' ) {
        echo "\n" . $code . "\n";
    }
}
add_action( 'wp_footer', 'lc_print_body_end_code', 99 );
