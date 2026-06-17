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
    $fields = [
        'lc_header_html', 'lc_footer_html', 'lc_head_html', 'lc_body_end_html',
        'lc_post_card_html', 'lc_single_post_html', 'lc_archive_header_html', 'lc_error_404_html',
    ];
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

    register_setting( 'lc_html_settings', 'lc_enable_find_replace', [
        'type'              => 'boolean',
        'sanitize_callback' => 'lc_sanitize_checkbox',
        'default'           => '1',
    ] );

    register_setting( 'lc_html_settings', 'lc_editor_preview', [
        'type'              => 'boolean',
        'sanitize_callback' => 'lc_sanitize_checkbox',
        'default'           => '1',
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


/**
 * Load the settings screen styles (sticky section nav, sage buttons and
 * checkboxes) and the scroll-spy script, only on our own settings page.
 */
function lc_enqueue_settings_assets( $hook ) {
    if ( $hook !== 'appearance_page_lc-header-footer-html' ) {
        return;
    }

    $css_rel = '/assets/admin-settings.css';
    $js_rel  = '/assets/settings-nav.js';
    $css_abs = get_template_directory() . $css_rel;
    $js_abs  = get_template_directory() . $js_rel;

    wp_enqueue_style(
        'lc-admin-settings',
        get_template_directory_uri() . $css_rel,
        [],
        file_exists( $css_abs ) ? (string) filemtime( $css_abs ) : LC_VERSION
    );

    wp_enqueue_script(
        'lc-settings-nav',
        get_template_directory_uri() . $js_rel,
        [],
        file_exists( $js_abs ) ? (string) filemtime( $js_abs ) : LC_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'lc_enqueue_settings_assets' );


function lc_render_box( string $name, string $label, string $help, string $id = '', string $tokens = '' ) {
    $attr = $id !== '' ? sprintf( ' id="%s" class="lc-section"', esc_attr( $id ) ) : '';
    printf( '<h2%1$s style="margin-top:28px;">%2$s</h2>', $attr, esc_html( $label ) );
    printf( '<p style="max-width:680px;color:#50575e;margin-top:0;">%s</p>', esc_html( $help ) );
    if ( $tokens !== '' ) {
        printf(
            '<p style="max-width:680px;margin:0 0 8px;font-family:Menlo,Consolas,monospace;font-size:12px;color:#5c7f68;">%s</p>',
            esc_html( $tokens )
        );
    }
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
    <div class="wrap lc-canvas-settings">
        <h1><?php echo esc_html__( 'Loupely Canvas', 'loupely-canvas' ); ?></h1>

        <?php settings_errors( 'lc_html_settings' ); ?>

        <?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
            <div class="notice notice-warning inline" style="max-width:720px;">
                <p><?php echo esc_html__( 'Your account cannot save raw scripts or styles. Style and script tags in these boxes will be removed when you save. Ask an administrator if you need them.', 'loupely-canvas' ); ?></p>
            </div>
        <?php endif; ?>

        <?php do_action( 'lc_settings_top' ); ?>

        <p style="max-width:720px;color:#50575e;">
            <?php echo esc_html__( 'Paste raw HTML into any box below. The header and footer wrap every page. The head and body code run site wide. Tip: click into any box and press Ctrl+F or Cmd+F to find and replace inside it.', 'loupely-canvas' ); ?>
        </p>

        <?php if ( trim( (string) get_option( 'lc_header_html', '' ) ) === '' && trim( (string) get_option( 'lc_footer_html', '' ) ) === '' ) : ?>
            <?php lc_render_starter_button(); ?>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'lc_html_settings' ); ?>

            <nav class="lc-settings-nav" aria-label="<?php echo esc_attr__( 'Jump to a settings section', 'loupely-canvas' ); ?>">
                <span class="lc-nav-label">&lt;jump-to&gt;</span>
                <a href="#lc-sec-header"><?php echo esc_html__( 'Header', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-footer"><?php echo esc_html__( 'Footer', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-head"><?php echo esc_html__( 'Head code', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-body"><?php echo esc_html__( 'Body end', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-blog"><?php echo esc_html__( 'Blog', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-post-card"><?php echo esc_html__( 'Post card', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-single-post"><?php echo esc_html__( 'Single post', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-archive-header"><?php echo esc_html__( 'Archive header', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-404"><?php echo esc_html__( '404', 'loupely-canvas' ); ?></a>
                <a href="#lc-sec-theme-settings"><?php echo esc_html__( 'Theme Settings', 'loupely-canvas' ); ?></a>
                <span class="lc-nav-save">
                    <?php submit_button( __( 'Save changes', 'loupely-canvas' ), 'primary', 'submit', false ); ?>
                </span>
            </nav>

            <?php
            lc_render_box(
                'lc_header_html',
                __( 'Header HTML', 'loupely-canvas' ),
                __( 'Printed at the top of every page, before your page content.', 'loupely-canvas' ),
                'lc-sec-header'
            );
            lc_render_box(
                'lc_footer_html',
                __( 'Footer HTML', 'loupely-canvas' ),
                __( 'Printed at the bottom of every page, after your page content.', 'loupely-canvas' ),
                'lc-sec-footer'
            );
            lc_render_box(
                'lc_head_html',
                __( 'Head code', 'loupely-canvas' ),
                __( 'Printed inside the document head. Use for analytics, fonts, favicons, verification and meta tags.', 'loupely-canvas' ),
                'lc-sec-head'
            );
            lc_render_box(
                'lc_body_end_html',
                __( 'Body end code', 'loupely-canvas' ),
                __( 'Printed just before the closing body tag. Use for chat widgets and scripts that should load last.', 'loupely-canvas' ),
                'lc-sec-body'
            );
            ?>

            <h2 id="lc-sec-blog" class="lc-section" style="margin-top:40px;border-top:1px solid #dcdcde;padding-top:28px;"><?php echo esc_html__( 'Blog templates', 'loupely-canvas' ); ?></h2>
            <p style="max-width:720px;color:#50575e;">
                <?php echo esc_html__( 'Pages and posts work differently. A page shows exactly the HTML you paste into it. Posts (your blog entries) are different: WordPress loops through many of them, so the theme needs a small template that says how a post should look. You write that template once here, using tokens for the parts that change from post to post, and the theme fills them in for every post.', 'loupely-canvas' ); ?>
            </p>
            <p style="max-width:720px;color:#50575e;">
                <?php echo esc_html__( 'There are three places a post shows up, and a box for each:', 'loupely-canvas' ); ?>
            </p>
            <ul style="max-width:720px;color:#50575e;list-style:disc;margin:0 0 14px 22px;">
                <li><?php echo esc_html__( 'Post card: how one post looks in a list (your blog page, and category, tag, and date archives). Use a short summary here, not the full text.', 'loupely-canvas' ); ?></li>
                <li><?php echo esc_html__( 'Single post: how one post looks on its own page, when someone clicks through to read it. This is where the full post body goes.', 'loupely-canvas' ); ?></li>
                <li><?php echo esc_html__( 'Archive header: the heading shown above a list, for example "Category: News" on a category page.', 'loupely-canvas' ); ?></li>
            </ul>
            <p style="max-width:720px;color:#50575e;">
                <?php echo esc_html__( 'Leave any box empty to use a plain default you can replace later. To run a blog, set a page as your Posts page under Settings, Reading. Anything you paste onto that page shows above the post list, so it can hold a blog intro or hero.', 'loupely-canvas' ); ?>
            </p>

            <details style="max-width:720px;margin:0 0 8px;border:1px solid #d5ded6;border-radius:6px;background:#f5f7f5;">
                <summary style="cursor:pointer;padding:10px 14px;font-weight:600;color:#1a2420;"><?php echo esc_html__( 'Token reference: what you can use, and where', 'loupely-canvas' ); ?></summary>
                <div style="padding:0 14px 12px;color:#50575e;">
                    <p style="margin:8px 0;"><?php echo esc_html__( 'A token is a placeholder the theme swaps for real content. Type the token in a box and it becomes that post\'s value on the front end.', 'loupely-canvas' ); ?></p>
                    <p style="margin:8px 0 4px;font-weight:600;color:#1a2420;"><?php echo esc_html__( 'In Post card and Single post (each post\'s own values):', 'loupely-canvas' ); ?></p>
                    <ul style="list-style:disc;margin:0 0 8px 22px;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.9;">
                        <li><?php echo esc_html__( '{title}: the post title', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{permalink}: the link to the post (use in an href)', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{date}: the published date', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{excerpt}: a short summary (best for Post card)', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{content}: the full post body (best for Single post)', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{thumbnail}: the featured image as a ready img tag', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{thumbnail_url}: just the featured image URL (use in src or CSS)', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{author}: the author name', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{categories}: linked category names', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{tags}: linked tag names', 'loupely-canvas' ); ?></li>
                    </ul>
                    <p style="margin:8px 0 4px;font-weight:600;color:#1a2420;"><?php echo esc_html__( 'In Archive header:', 'loupely-canvas' ); ?></p>
                    <ul style="list-style:disc;margin:0 0 8px 22px;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.9;">
                        <li><?php echo esc_html__( '{archive_title}: the archive name, for example a category', 'loupely-canvas' ); ?></li>
                        <li><?php echo esc_html__( '{archive_description}: the archive description, if set', 'loupely-canvas' ); ?></li>
                    </ul>
                    <p style="margin:8px 0 4px;font-weight:600;color:#1a2420;"><?php echo esc_html__( 'In the 404 box:', 'loupely-canvas' ); ?></p>
                    <ul style="list-style:disc;margin:0 0 4px 22px;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.9;">
                        <li><?php echo esc_html__( '{home_url}: your homepage link', 'loupely-canvas' ); ?></li>
                    </ul>
                </div>
            </details>

            <?php
            if ( function_exists( 'lc_render_blog_starter_button' ) ) {
                lc_render_blog_starter_button();
            }
            ?>

            <?php
            lc_render_box(
                'lc_post_card_html',
                __( 'Post card', 'loupely-canvas' ),
                __( 'Shown for each post in a list: your blog page and the category, tag, and date archives. Keep it compact, link the title to {permalink}, and use {excerpt} rather than {content}.', 'loupely-canvas' ),
                'lc-sec-post-card',
                __( 'Tokens: {title} {permalink} {date} {excerpt} {thumbnail} {thumbnail_url} {author} {categories} {tags}', 'loupely-canvas' )
            );
            lc_render_box(
                'lc_single_post_html',
                __( 'Single post', 'loupely-canvas' ),
                __( 'Shown when someone opens one post on its own page. This is the full read, so use {content} for the body.', 'loupely-canvas' ),
                'lc-sec-single-post',
                __( 'Tokens: {title} {permalink} {date} {content} {thumbnail} {thumbnail_url} {author} {categories} {tags}', 'loupely-canvas' )
            );
            lc_render_box(
                'lc_archive_header_html',
                __( 'Archive header', 'loupely-canvas' ),
                __( 'Optional heading above a list on archive and search pages. Leave it empty for a plain title on archives, and nothing on the blog page.', 'loupely-canvas' ),
                'lc-sec-archive-header',
                __( 'Tokens: {archive_title} {archive_description}', 'loupely-canvas' )
            );
            lc_render_box(
                'lc_error_404_html',
                __( '404 page', 'loupely-canvas' ),
                __( 'Shown when a URL is not found. Empty falls back to a short message with a link home.', 'loupely-canvas' ),
                'lc-sec-404',
                __( 'Tokens: {home_url}', 'loupely-canvas' )
            );
            ?>

            <h2 id="lc-sec-theme-settings" class="lc-section" style="margin-top:28px;"><?php echo esc_html__( 'Theme Settings', 'loupely-canvas' ); ?></h2>

            <h3 style="margin-bottom:6px;"><?php echo esc_html__( 'Find and replace bar', 'loupely-canvas' ); ?></h3>
            <p style="max-width:680px;color:#50575e;margin-top:0;">
                <?php echo esc_html__( 'Adds a Ctrl+F or Cmd+F find and replace bar inside the HTML boxes, the block editor, and these settings boxes. Turn it off to remove it from every editor.', 'loupely-canvas' ); ?>
            </p>
            <input type="hidden" name="lc_enable_find_replace" value="0">
            <label>
                <input type="checkbox" name="lc_enable_find_replace" value="1" <?php checked( get_option( 'lc_enable_find_replace', '1' ), '1' ); ?>>
                <?php echo esc_html__( 'Show the find and replace bar in the editor', 'loupely-canvas' ); ?>
            </label>

            <h3 style="margin-bottom:6px;margin-top:22px;"><?php echo esc_html__( 'Editor preview styling', 'loupely-canvas' ); ?></h3>
            <p style="max-width:680px;color:#50575e;margin-top:0;">
                <?php echo esc_html__( 'Loads the CSS and fonts from your Head code box into the editor, so the Custom HTML block preview looks like the front end instead of plain. Scripts are not run in the preview, so anything that depends on JavaScript shows its pre-script state.', 'loupely-canvas' ); ?>
            </p>
            <input type="hidden" name="lc_editor_preview" value="0">
            <label>
                <input type="checkbox" name="lc_editor_preview" value="1" <?php checked( get_option( 'lc_editor_preview', '1' ), '1' ); ?>>
                <?php echo esc_html__( 'Show my Head code design in the editor preview', 'loupely-canvas' ); ?>
            </label>

            <h3 style="margin-bottom:6px;margin-top:22px;"><?php echo esc_html__( 'Editor menus', 'loupely-canvas' ); ?></h3>
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
