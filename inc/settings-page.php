<?php
/**
 * Loupely Canvas - settings page
 *
 * Appearance > Loupely Canvas. Four boxes:
 *   Header HTML        printed where the header goes
 *   Footer HTML        printed where the footer goes
 *   Head code          printed in wp_head (analytics, fonts, meta)
 *   Body end code       printed in wp_footer (chat widgets, late scripts)
 *
 * Raw HTML including style and script is preserved for users who have the
 * unfiltered_html capability (administrators on single site installs). For
 * anyone else the content is sanitized, and a notice explains that clearly
 * rather than failing silently.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


function lc_register_settings() {
    $fields = [ 'lc_header_html', 'lc_footer_html', 'lc_head_html', 'lc_body_end_html' ];
    foreach ( $fields as $field ) {
        register_setting( 'lc_html_settings', $field, [
            'type'              => 'string',
            'sanitize_callback' => 'lc_sanitize_raw_html',
            'default'           => '',
        ] );
    }

    register_setting( 'lc_html_settings', 'lc_hide_editor_menus', [
        'type'              => 'boolean',
        'sanitize_callback' => 'lc_sanitize_checkbox',
        'default'           => '',
    ] );
}
add_action( 'admin_init', 'lc_register_settings' );


/**
 * Normalize a checkbox to '1' or empty string.
 */
function lc_sanitize_checkbox( $value ) {
    return ( $value === '1' || $value === 1 || $value === true ) ? '1' : '';
}


/**
 * Preserve raw markup for trusted users, sanitize for everyone else.
 */
function lc_sanitize_raw_html( $value ) {
    if ( current_user_can( 'unfiltered_html' ) ) {
        return $value;
    }
    add_settings_error(
        'lc_html_settings',
        'lc_sanitized',
        __( 'Your account cannot save raw scripts or styles, so any style or script tags were removed. An administrator can save those.', 'loupely-canvas' ),
        'warning'
    );
    return wp_kses_post( (string) $value );
}


function lc_add_settings_page() {
    add_theme_page(
        __( 'Loupely Canvas', 'loupely-canvas' ),
        __( 'Loupely Canvas', 'loupely-canvas' ),
        'edit_theme_options',
        'lc-header-footer-html',
        'lc_render_settings_page'
    );
}
add_action( 'admin_menu', 'lc_add_settings_page' );


function lc_render_box( string $name, string $label, string $help ) {
    printf( '<h2 style="margin-top:28px;">%s</h2>', esc_html( $label ) );
    printf( '<p style="max-width:680px;color:#50575e;margin-top:0;">%s</p>', esc_html( $help ) );
    printf(
        '<textarea name="%1$s" class="lc-html-field" rows="12" spellcheck="false" aria-label="%2$s" style="width:100%%;font-family:Menlo,Consolas,monospace;font-size:13px;line-height:1.5;">%3$s</textarea>',
        esc_attr( $name ),
        esc_attr( $label ),
        esc_textarea( get_option( $name, '' ) )
    );
}


function lc_render_settings_page() {
    if ( ! current_user_can( 'edit_theme_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Loupely Canvas', 'loupely-canvas' ); ?></h1>

        <?php settings_errors( 'lc_html_settings' ); ?>

        <?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
            <div class="notice notice-warning inline" style="max-width:720px;">
                <p><?php echo esc_html__( 'Your account cannot save raw scripts or styles. Style and script tags in these boxes will be removed when you save. Ask an administrator if you need them.', 'loupely-canvas' ); ?></p>
            </div>
        <?php endif; ?>

        <p style="max-width:720px;color:#50575e;">
            <?php echo esc_html__( 'Paste raw HTML into any box below. The header and footer wrap every page. The head and body code run site wide. Tip: click into any box and press Ctrl+F or Cmd+F to find and replace inside it.', 'loupely-canvas' ); ?>
        </p>

        <?php if ( trim( (string) get_option( 'lc_header_html', '' ) ) === '' && trim( (string) get_option( 'lc_footer_html', '' ) ) === '' ) : ?>
            <?php lc_render_starter_button(); ?>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'lc_html_settings' ); ?>

            <?php
            lc_render_box(
                'lc_header_html',
                __( 'Header HTML', 'loupely-canvas' ),
                __( 'Printed at the top of every page, before your page content.', 'loupely-canvas' )
            );
            lc_render_box(
                'lc_footer_html',
                __( 'Footer HTML', 'loupely-canvas' ),
                __( 'Printed at the bottom of every page, after your page content.', 'loupely-canvas' )
            );
            lc_render_box(
                'lc_head_html',
                __( 'Head code', 'loupely-canvas' ),
                __( 'Printed inside the document head. Use for analytics, fonts, favicons, verification and meta tags.', 'loupely-canvas' )
            );
            lc_render_box(
                'lc_body_end_html',
                __( 'Body end code', 'loupely-canvas' ),
                __( 'Printed just before the closing body tag. Use for chat widgets and scripts that should load last.', 'loupely-canvas' )
            );
            ?>

            <h2 style="margin-top:28px;"><?php echo esc_html__( 'Editor menus', 'loupely-canvas' ); ?></h2>
            <p style="max-width:680px;color:#50575e;margin-top:0;">
                <?php echo esc_html__( 'This is a classic theme and does not use the block Patterns or Fonts screens. You can hide them from the Appearance menu to keep things tidy. This only hides the menu links and changes nothing on your live site.', 'loupely-canvas' ); ?>
            </p>
            <input type="hidden" name="lc_hide_editor_menus" value="0">
            <label>
                <input type="checkbox" name="lc_hide_editor_menus" value="1" <?php checked( get_option( 'lc_hide_editor_menus' ), '1' ); ?>>
                <?php echo esc_html__( 'Hide the Patterns and Fonts menus under Appearance', 'loupely-canvas' ); ?>
            </label>

            <?php submit_button( __( 'Save changes', 'loupely-canvas' ) ); ?>
        </form>
    </div>
    <?php
}
