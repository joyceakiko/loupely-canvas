<?php
/**
 * Loupely Canvas - per page header and footer override
 *
 * Adds a meta box to pages and posts so a single page can use the global
 * header and footer, supply its own custom HTML, or show none at all.
 * The custom textareas carry the lc-html-field class so the find and
 * replace tool works inside them too.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


function lc_add_meta_box() {
    foreach ( [ 'page', 'post' ] as $type ) {
        add_meta_box(
            'lc_header_footer',
            __( 'Header and footer (Loupely Canvas)', 'loupely-canvas' ),
            'lc_render_meta_box',
            $type,
            'normal',
            'default'
        );
    }
}
add_action( 'add_meta_boxes', 'lc_add_meta_box' );


function lc_render_meta_box( $post ) {
    wp_nonce_field( 'lc_save_meta', 'lc_meta_nonce' );

    $h_mode   = get_post_meta( $post->ID, '_lc_header_mode', true ) ?: 'global';
    $f_mode   = get_post_meta( $post->ID, '_lc_footer_mode', true ) ?: 'global';
    $h_custom = get_post_meta( $post->ID, '_lc_header_custom', true );
    $f_custom = get_post_meta( $post->ID, '_lc_footer_custom', true );

    $modes = [
        'global' => __( 'Use the global header and footer', 'loupely-canvas' ),
        'custom' => __( 'Use custom HTML for this page', 'loupely-canvas' ),
        'none'   => __( 'Show none on this page', 'loupely-canvas' ),
    ];

    echo '<p style="color:#50575e;max-width:680px;">' . esc_html__( 'Override the site header and footer for this page only. Custom boxes accept raw HTML, and Ctrl+F or Cmd+F finds inside them.', 'loupely-canvas' ) . '</p>';

    // Header control
    echo '<h4 style="margin-bottom:6px;">' . esc_html__( 'Header', 'loupely-canvas' ) . '</h4>';
    echo '<select name="lc_header_mode" data-lc-target="lc-header-custom-wrap">';
    foreach ( $modes as $val => $text ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $h_mode, $val, false ), esc_html( $text ) );
    }
    echo '</select>';
    printf(
        '<div id="lc-header-custom-wrap" style="margin-top:8px;%s"><textarea name="lc_header_custom" class="lc-html-field" rows="6" spellcheck="false" style="width:100%%;font-family:Menlo,Consolas,monospace;font-size:13px;">%s</textarea></div>',
        $h_mode === 'custom' ? '' : 'display:none;',
        esc_textarea( $h_custom )
    );

    // Footer control
    echo '<h4 style="margin-bottom:6px;margin-top:18px;">' . esc_html__( 'Footer', 'loupely-canvas' ) . '</h4>';
    echo '<select name="lc_footer_mode" data-lc-target="lc-footer-custom-wrap">';
    foreach ( $modes as $val => $text ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $f_mode, $val, false ), esc_html( $text ) );
    }
    echo '</select>';
    printf(
        '<div id="lc-footer-custom-wrap" style="margin-top:8px;%s"><textarea name="lc_footer_custom" class="lc-html-field" rows="6" spellcheck="false" style="width:100%%;font-family:Menlo,Consolas,monospace;font-size:13px;">%s</textarea></div>',
        $f_mode === 'custom' ? '' : 'display:none;',
        esc_textarea( $f_custom )
    );

    // The show/hide toggle for the custom boxes is in assets/page-meta.js,
    // enqueued on the post editor screens by lc_page_meta_assets().
}


function lc_save_meta( $post_id ) {
    if ( ! isset( $_POST['lc_meta_nonce'] ) || ! wp_verify_nonce( $_POST['lc_meta_nonce'], 'lc_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $allowed = [ 'global', 'custom', 'none' ];

    foreach ( [ 'header', 'footer' ] as $part ) {
        $mode_key = 'lc_' . $part . '_mode';
        $mode = isset( $_POST[ $mode_key ] ) ? sanitize_key( $_POST[ $mode_key ] ) : 'global';
        if ( ! in_array( $mode, $allowed, true ) ) {
            $mode = 'global';
        }
        update_post_meta( $post_id, '_lc_' . $part . '_mode', $mode );

        $custom_key = 'lc_' . $part . '_custom';
        if ( isset( $_POST[ $custom_key ] ) ) {
            $raw = (string) wp_unslash( $_POST[ $custom_key ] );
            $value = current_user_can( 'unfiltered_html' ) ? $raw : wp_kses_post( $raw );
            update_post_meta( $post_id, '_lc_' . $part . '_custom', $value );
        }
    }
}
add_action( 'save_post', 'lc_save_meta' );


/**
 * Load the small toggle script on the post and page editor screens, where the
 * override meta box appears. Behavior lives in assets/page-meta.js, not inline.
 */
function lc_page_meta_assets( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }
    $rel = '/assets/page-meta.js';
    $abs = get_template_directory() . $rel;
    $ver = file_exists( $abs ) ? (string) filemtime( $abs ) : LC_VERSION;
    wp_enqueue_script( 'lc-page-meta', get_template_directory_uri() . $rel, [], $ver, true );
}
add_action( 'admin_enqueue_scripts', 'lc_page_meta_assets' );
