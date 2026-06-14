<?php
/**
 * Loupely Canvas - starter content
 *
 * A single button on the settings screen that loads example header and
 * footer HTML into the empty boxes and creates an example page as a draft.
 * Nothing is created automatically and nothing is overwritten: it only fills
 * boxes that are empty, so it is safe to ignore or run once.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


function lc_render_starter_button() {
    $url = wp_nonce_url(
        admin_url( 'admin-post.php?action=lc_create_starter' ),
        'lc_starter',
        'lc_starter_nonce'
    );
    echo '<div class="notice notice-info inline" style="max-width:720px;">';
    echo '<p>' . esc_html__( 'New here? Load a working example you can edit instead of starting from a blank box.', 'loupely-canvas' ) . '</p>';
    echo '<p><a href="' . esc_url( $url ) . '" class="button button-secondary">' . esc_html__( 'Create starter content', 'loupely-canvas' ) . '</a></p>';
    echo '</div>';
}


function lc_handle_create_starter() {
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
    }
    check_admin_referer( 'lc_starter', 'lc_starter_nonce' );

    $dir = get_template_directory() . '/starter/';

    $header = lc_read_starter_file( $dir . 'example-header.html' );
    $footer = lc_read_starter_file( $dir . 'example-footer.html' );
    $page   = lc_read_starter_file( $dir . 'example-page.html' );

    if ( $header !== '' && trim( (string) get_option( 'lc_header_html', '' ) ) === '' ) {
        update_option( 'lc_header_html', $header );
    }
    if ( $footer !== '' && trim( (string) get_option( 'lc_footer_html', '' ) ) === '' ) {
        update_option( 'lc_footer_html', $footer );
    }

    $created = 0;
    if ( $page !== '' ) {
        $content = "<!-- wp:html -->\n" . $page . "\n<!-- /wp:html -->";
        $id = wp_insert_post( [
            'post_title'   => __( 'Example page', 'loupely-canvas' ),
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_content' => $content,
        ] );
        if ( $id && ! is_wp_error( $id ) ) {
            $created = 1;
        }
    }

    $redirect = add_query_arg(
        [ 'page' => 'lc-header-footer-html', 'lc_starter_done' => $created ],
        admin_url( 'themes.php' )
    );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_lc_create_starter', 'lc_handle_create_starter' );


function lc_read_starter_file( string $path ): string {
    if ( ! file_exists( $path ) ) {
        return '';
    }
    $contents = file_get_contents( $path );
    return $contents === false ? '' : $contents;
}


function lc_starter_done_notice() {
    if ( ! isset( $_GET['page'], $_GET['lc_starter_done'] ) || $_GET['page'] !== 'lc-header-footer-html' ) {
        return;
    }
    $created = $_GET['lc_starter_done'] === '1';
    echo '<div class="notice notice-success is-dismissible"><p>';
    if ( $created ) {
        echo esc_html__( 'Example header and footer loaded into any empty boxes, and an example page was created as a draft. Find it under Pages.', 'loupely-canvas' );
    } else {
        echo esc_html__( 'Example header and footer loaded into any empty boxes.', 'loupely-canvas' );
    }
    echo '</p></div>';
}
add_action( 'admin_notices', 'lc_starter_done_notice' );
