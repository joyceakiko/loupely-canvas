<?php
/**
 * Loupely Canvas - editor preview styling
 *
 * The design that styles your pages lives in the Head code box and prints on
 * the front end through wp_head. The block editor and the Custom HTML block
 * preview never loaded it, so previews looked unstyled. This carries the CSS
 * and font links from the Head code box into the editor as editor styles, so
 * the Custom HTML block preview looks like the front end.
 *
 * Only style and stylesheet links are carried over. Scripts in the Head box
 * are not run in the editor, so anything that depends on JavaScript (for
 * example a scroll reveal that starts hidden) shows its pre-script state in
 * the preview. The whole behavior can be turned off in Theme Settings.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Build editor CSS from the Head code box: inline <style> contents, plus any
 * stylesheet <link> tags turned into @import rules so fonts load too. Returns
 * '' when the setting is off or nothing usable is found.
 */
function lc_editor_preview_css(): string {
    if ( get_option( 'lc_editor_preview', '1' ) !== '1' ) {
        return '';
    }

    $head = (string) get_option( 'lc_head_html', '' );
    if ( trim( $head ) === '' ) {
        return '';
    }

    $imports = '';
    $styles  = '';

    // Stylesheet and font links become @import rules so they load in the iframe.
    if ( preg_match_all( '/<link\b[^>]*>/i', $head, $links ) ) {
        foreach ( $links[0] as $tag ) {
            if ( ! preg_match( '/rel\s*=\s*["\']?stylesheet["\']?/i', $tag ) ) {
                continue;
            }
            if ( preg_match( '/href\s*=\s*["\']([^"\']+)["\']/i', $tag, $m ) ) {
                $url = esc_url_raw( html_entity_decode( $m[1], ENT_QUOTES ) );
                if ( $url !== '' ) {
                    $imports .= '@import url("' . $url . '");' . "\n";
                }
            }
        }
    }

    // Inline <style> blocks.
    if ( preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', $head, $blocks ) ) {
        foreach ( $blocks[1] as $css ) {
            $styles .= $css . "\n";
        }
    }

    $combined = $imports . $styles;
    return trim( $combined ) === '' ? '' : $combined;
}


/**
 * Feed the Head code design into the block editor as editor styles. These
 * reach the iframed canvas and the Custom HTML block preview sandbox.
 */
function lc_add_block_editor_preview_styles( $settings, $context = null ) {
    $css = lc_editor_preview_css();
    if ( $css !== '' ) {
        if ( empty( $settings['styles'] ) || ! is_array( $settings['styles'] ) ) {
            $settings['styles'] = [];
        }
        $settings['styles'][] = [ 'css' => $css ];
    }
    return $settings;
}
add_filter( 'block_editor_settings_all', 'lc_add_block_editor_preview_styles', 10, 2 );


/**
 * The same design for the classic editor, appended to its content styles.
 */
function lc_add_classic_editor_preview_styles( $init ) {
    $css = lc_editor_preview_css();
    if ( $css !== '' ) {
        $existing            = isset( $init['content_style'] ) ? (string) $init['content_style'] : '';
        $init['content_style'] = $existing . "\n" . $css;
    }
    return $init;
}
add_filter( 'tiny_mce_before_init', 'lc_add_classic_editor_preview_styles' );
