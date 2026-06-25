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

    register_setting( 'lc_html_settings', 'lc_seo_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'lc_sanitize_checkbox',
        'default'           => '1',
    ] );

    register_setting( 'lc_html_settings', 'lc_seo_defaults', [
        'type'              => 'array',
        'sanitize_callback' => 'lc_sanitize_seo_defaults',
        'default'           => [],
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

    $tc_rel = '/assets/token-copy.js';
    $tc_abs = get_template_directory() . $tc_rel;
    wp_enqueue_script(
        'lc-token-copy',
        get_template_directory_uri() . $tc_rel,
        [],
        file_exists( $tc_abs ) ? (string) filemtime( $tc_abs ) : LC_VERSION,
        true
    );
    wp_localize_script( 'lc-token-copy', 'lcTokenCopyL10n', [
        'copied' => __( 'Copied to clipboard', 'loupely-canvas' ),
        'copy'   => __( 'Copy', 'loupely-canvas' ),
    ] );

    // The site-wide SEO defaults block uses the media library for its image
    // fields and shows or hides with the SEO toggle, both handled by the SEO
    // script, so load it and the media frame on this screen too.
    wp_enqueue_media();
    $seo_rel = '/assets/seo.js';
    $seo_abs = get_template_directory() . $seo_rel;
    wp_enqueue_script(
        'lc-seo',
        get_template_directory_uri() . $seo_rel,
        [],
        file_exists( $seo_abs ) ? (string) filemtime( $seo_abs ) : LC_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'lc_enqueue_settings_assets' );


function lc_render_box( string $name, string $label, string $help, string $id = '', array $tokens = [] ) {
    $attr = $id !== '' ? sprintf( ' id="%s" class="lc-section"', esc_attr( $id ) ) : '';
    printf( '<h2%1$s style="margin-top:28px;">%2$s</h2>', $attr, esc_html( $label ) );
    printf( '<p style="max-width:680px;color:#50575e;margin-top:0;">%s</p>', esc_html( $help ) );
    if ( ! empty( $tokens ) ) {
        echo '<p class="lc-token-row" style="max-width:680px;margin:0 0 8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">';
        echo '<span style="color:#5c7f68;font-size:12px;">' . esc_html__( 'Tokens:', 'loupely-canvas' ) . '</span> ';
        foreach ( $tokens as $token ) {
            echo '<code class="lc-token">' . esc_html( $token ) . '</code>';
        }
        echo '</p>';
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
                <a href="#lc-sec-site-basics"><?php echo esc_html__( 'Site basics', 'loupely-canvas' ); ?></a>
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
            if ( function_exists( 'lc_render_site_basics' ) ) {
                lc_render_site_basics();
            }

            lc_render_box(
                'lc_header_html',
                __( 'Header HTML', 'loupely-canvas' ),
                __( 'Printed at the top of every page, before your page content. It accepts the tokens below. For {menu:header}, build a menu under Appearance, Menus and assign it to the Header location.', 'loupely-canvas' ),
                'lc-sec-header',
                [ '{logo}', '{site_title}', '{tagline}', '{home_url}', '{site_url}', '{year}', '{page_title}', '{current_url}', '{menu:header}', '{menu:your-menu-slug}' ]
            );
            lc_render_box(
                'lc_footer_html',
                __( 'Footer HTML', 'loupely-canvas' ),
                __( 'Printed at the bottom of every page, after your page content. It accepts the tokens below. For {menu:footer}, build a menu under Appearance, Menus and assign it to the Footer location.', 'loupely-canvas' ),
                'lc-sec-footer',
                [ '{logo}', '{site_title}', '{tagline}', '{home_url}', '{site_url}', '{year}', '{page_title}', '{current_url}', '{menu:footer}', '{menu:your-menu-slug}' ]
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
                    <ul class="lc-token-list" style="list-style:none;margin:0 0 8px;padding:0;font-size:12px;line-height:2.1;color:#50575e;">
                        <li><code class="lc-token">{title}</code> <?php echo esc_html__( 'the post title', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{permalink}</code> <?php echo esc_html__( 'the link to the post (use in an href)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{date}</code> <?php echo esc_html__( 'the published date', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{excerpt}</code> <?php echo esc_html__( 'a short summary (best for Post card)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{content}</code> <?php echo esc_html__( 'the full post body (best for Single post)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{thumbnail}</code> <?php echo esc_html__( 'featured image as an img tag at the large size', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{thumbnail_medium}</code> <?php echo esc_html__( 'featured image at medium size', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{thumbnail_full}</code> <?php echo esc_html__( 'featured image at full size', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{thumbnail_url}</code> <?php echo esc_html__( 'featured image URL at large size (use in src or CSS)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{thumbnail_medium_url}</code> <?php echo esc_html__( 'featured image URL at medium size', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{thumbnail_full_url}</code> <?php echo esc_html__( 'featured image URL at full size', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{author}</code> <?php echo esc_html__( 'the author name', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{author_avatar}</code> <?php echo esc_html__( 'the author photo as a ready img tag', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{author_bio}</code> <?php echo esc_html__( 'the author bio from their profile', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{author_url}</code> <?php echo esc_html__( 'the author website link (use in an href)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{categories}</code> <?php echo esc_html__( 'linked category names', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{tags}</code> <?php echo esc_html__( 'linked tag names', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{post_class}</code> <?php echo esc_html__( 'the post CSS classes (put in a class attribute on your wrapper)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{comment_count}</code> <?php echo esc_html__( 'the number of comments', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{comments_link}</code> <?php echo esc_html__( 'the link to the comments (use in an href)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{pagination}</code> <?php echo esc_html__( 'archive pagination links, for Post card only; empty on a single post', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{comments}</code> <?php echo esc_html__( 'the full comment thread, for Single post only; empty on card lists', 'loupely-canvas' ); ?></li>
                    </ul>
                    <p style="margin:8px 0 4px;font-weight:600;color:#1a2420;"><?php echo esc_html__( 'In Header and Footer:', 'loupely-canvas' ); ?></p>
                    <ul class="lc-token-list" style="list-style:none;margin:0 0 8px;padding:0;font-size:12px;line-height:2.1;color:#50575e;">
                        <li><code class="lc-token">{logo}</code> <?php echo esc_html__( 'the custom logo as a linked image', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{site_title}</code> <?php echo esc_html__( 'the site name', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{tagline}</code> <?php echo esc_html__( 'the site tagline', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{home_url}</code> <?php echo esc_html__( 'the homepage URL with trailing slash', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{site_url}</code> <?php echo esc_html__( 'the homepage URL without trailing slash (use in href attributes)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{year}</code> <?php echo esc_html__( 'the current four-digit year', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{page_title}</code> <?php echo esc_html__( 'the title of the current page or post', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{current_url}</code> <?php echo esc_html__( 'the full URL of the current page (use in href attributes)', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{menu:header}</code> <?php echo esc_html__( 'the menu assigned to the Header location', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{menu:footer}</code> <?php echo esc_html__( 'the menu assigned to the Footer location', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{menu:your-slug}</code> <?php echo esc_html__( 'any menu by its slug', 'loupely-canvas' ); ?></li>
                    </ul>
                    <p style="margin:8px 0 4px;font-weight:600;color:#1a2420;"><?php echo esc_html__( 'In Archive header:', 'loupely-canvas' ); ?></p>
                    <ul class="lc-token-list" style="list-style:none;margin:0 0 8px;padding:0;font-size:12px;line-height:2.1;color:#50575e;">
                        <li><code class="lc-token">{archive_title}</code> <?php echo esc_html__( 'the archive name, for example a category', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{archive_description}</code> <?php echo esc_html__( 'the archive description, if set', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{search_form}</code> <?php echo esc_html__( 'a ready search box (also works in the 404 box)', 'loupely-canvas' ); ?></li>
                    </ul>
                    <p style="margin:8px 0 4px;font-weight:600;color:#1a2420;"><?php echo esc_html__( 'In the 404 box:', 'loupely-canvas' ); ?></p>
                    <ul class="lc-token-list" style="list-style:none;margin:0 0 4px;padding:0;font-size:12px;line-height:2.1;color:#50575e;">
                        <li><code class="lc-token">{home_url}</code> <?php echo esc_html__( 'your homepage link', 'loupely-canvas' ); ?></li>
                        <li><code class="lc-token">{search_form}</code> <?php echo esc_html__( 'a ready search box', 'loupely-canvas' ); ?></li>
                    </ul>
                </div>
            </details>

            <?php
            lc_render_box(
                'lc_post_card_html',
                __( 'Post card', 'loupely-canvas' ),
                __( 'Shown for each post in a list: your blog page and the category, tag, and date archives. Keep it compact, link the title to {permalink}, and use {excerpt} rather than {content}.', 'loupely-canvas' ),
                'lc-sec-post-card',
                [ '{title}', '{permalink}', '{date}', '{excerpt}', '{thumbnail}', '{thumbnail_medium}', '{thumbnail_full}', '{thumbnail_url}', '{thumbnail_medium_url}', '{thumbnail_full_url}', '{author}', '{author_avatar}', '{author_bio}', '{author_url}', '{categories}', '{tags}', '{post_class}', '{comment_count}', '{comments_link}', '{pagination}' ]
            );
            lc_render_box(
                'lc_single_post_html',
                __( 'Single post', 'loupely-canvas' ),
                __( 'Shown when someone opens one post on its own page. This is the full read, so use {content} for the body.', 'loupely-canvas' ),
                'lc-sec-single-post',
                [ '{title}', '{permalink}', '{date}', '{content}', '{thumbnail}', '{thumbnail_medium}', '{thumbnail_full}', '{thumbnail_url}', '{thumbnail_medium_url}', '{thumbnail_full_url}', '{author}', '{author_avatar}', '{author_bio}', '{author_url}', '{categories}', '{tags}', '{post_class}', '{comment_count}', '{comments_link}', '{comments}' ]
            );
            lc_render_box(
                'lc_archive_header_html',
                __( 'Archive header', 'loupely-canvas' ),
                __( 'Optional heading above a list on archive and search pages. Leave it empty for a plain title on archives, and nothing on the blog page.', 'loupely-canvas' ),
                'lc-sec-archive-header',
                [ '{archive_title}', '{archive_description}', '{search_form}' ]
            );
            lc_render_box(
                'lc_error_404_html',
                __( '404 page', 'loupely-canvas' ),
                __( 'Shown when a URL is not found. Empty falls back to a short message with a link home.', 'loupely-canvas' ),
                'lc-sec-404',
                [ '{home_url}', '{search_form}' ]
            );
            ?>

            <h2 id="lc-sec-theme-settings" class="lc-section" style="margin-top:28px;"><?php echo esc_html__( 'Theme Settings', 'loupely-canvas' ); ?></h2>

            <h3 id="lc-sec-seo" class="lc-section" style="margin-bottom:6px;"><?php echo esc_html__( 'SEO output', 'loupely-canvas' ); ?></h3>
            <p style="max-width:680px;color:#50575e;margin-top:0;">
                <?php echo esc_html__( 'Canvas writes the meta description, Open Graph and Twitter tags, and the schema for your pages, posts, and custom post types. Turn this off to stop all of it, both the theme and the Canvas Pro SEO settings, so another SEO plugin can take over without two sets of tags fighting. Your saved SEO values are kept; they just stop printing.', 'loupely-canvas' ); ?>
            </p>
            <p style="max-width:680px;color:#5c7f68;background:#f1f6f2;border:1px solid #cfe0d3;border-radius:6px;padding:10px 14px;">
                <?php echo esc_html__( 'If you are using a different SEO plugin, you can turn off Canvas SEO here.', 'loupely-canvas' ); ?>
            </p>
            <input type="hidden" name="lc_seo_enabled" value="0">
            <label>
                <input type="checkbox" name="lc_seo_enabled" value="1" <?php checked( get_option( 'lc_seo_enabled', '1' ), '1' ); ?>>
                <?php echo esc_html__( 'Activate SEO features in Loupely Canvas', 'loupely-canvas' ); ?>
            </label>

            <?php
            $seo_on  = get_option( 'lc_seo_enabled', '1' ) === '1';
            $seo     = lc_seo_defaults_get();
            ?>
            <div class="lc-seo-defaults" <?php echo $seo_on ? '' : 'hidden'; ?>>
                <p style="max-width:680px;color:#50575e;">
                    <?php echo esc_html__( 'Site wide defaults and structured data. Each page, post, and custom post type sets its own SEO in the SEO section on the editor; these fill in where a page has not set its own.', 'loupely-canvas' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default meta description', 'loupely-canvas' ); ?></th>
                        <td>
                            <textarea name="lc_seo_defaults[default_description]" rows="2" class="large-text"><?php echo esc_textarea( $seo['default_description'] ); ?></textarea>
                            <p class="description"><?php echo esc_html__( 'Used on pages that do not set their own description.', 'loupely-canvas' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Default social image', 'loupely-canvas' ); ?></th>
                        <td>
                            <input type="url" id="lc_seo_default_image" name="lc_seo_defaults[default_image]" value="<?php echo esc_attr( $seo['default_image'] ); ?>" class="regular-text">
                            <button type="button" class="button lc-seo-pick-image" data-target="lc_seo_default_image"><?php echo esc_html__( 'Select', 'loupely-canvas' ); ?></button>
                            <button type="button" class="button lc-seo-clear-image" data-target="lc_seo_default_image"><?php echo esc_html__( 'Clear', 'loupely-canvas' ); ?></button>
                        </td>
                    </tr>
                    <tr><th colspan="2"><hr><h3 style="margin:8px 0;"><?php echo esc_html__( 'Organization schema (front page)', 'loupely-canvas' ); ?></h3></th></tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Organization name', 'loupely-canvas' ); ?></th>
                        <td><input type="text" name="lc_seo_defaults[org_name]" value="<?php echo esc_attr( $seo['org_name'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Logo URL', 'loupely-canvas' ); ?></th>
                        <td>
                            <input type="url" id="lc_seo_org_logo" name="lc_seo_defaults[org_logo]" value="<?php echo esc_attr( $seo['org_logo'] ); ?>" class="regular-text">
                            <button type="button" class="button lc-seo-pick-image" data-target="lc_seo_org_logo"><?php echo esc_html__( 'Select', 'loupely-canvas' ); ?></button>
                            <button type="button" class="button lc-seo-clear-image" data-target="lc_seo_org_logo"><?php echo esc_html__( 'Clear', 'loupely-canvas' ); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Social profile URLs', 'loupely-canvas' ); ?></th>
                        <td>
                            <textarea name="lc_seo_defaults[org_sameas]" rows="3" class="large-text" placeholder="https://example.com/you"><?php echo esc_textarea( $seo['org_sameas'] ); ?></textarea>
                            <p class="description"><?php echo esc_html__( 'One URL per line. Added to the Organization schema as sameAs.', 'loupely-canvas' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Twitter site handle', 'loupely-canvas' ); ?></th>
                        <td><input type="text" name="lc_seo_defaults[twitter_site]" value="<?php echo esc_attr( $seo['twitter_site'] ); ?>" class="regular-text" placeholder="@yoursite"></td>
                    </tr>
                </table>

                <h3 id="lc-sec-sitemap" style="margin-bottom:6px;margin-top:22px;"><?php echo esc_html__( 'Sitemap', 'loupely-canvas' ); ?></h3>
                <?php $lc_sitemap_url = home_url( '/wp-sitemap.xml' ); ?>
                <p style="max-width:680px;color:#50575e;margin-top:0;">
                    <?php echo esc_html__( 'WordPress builds an XML sitemap for your site automatically and keeps it up to date as you add pages, posts, and custom post types. Search engines use it to find your pages. Your sitemap is at:', 'loupely-canvas' ); ?>
                </p>
                <p style="margin:0 0 12px;">
                    <a href="<?php echo esc_url( $lc_sitemap_url ); ?>" target="_blank" rel="noopener" style="color:#5C7F68;font-family:Menlo,Consolas,monospace;font-size:13px;"><?php echo esc_html( $lc_sitemap_url ); ?></a>
                </p>
                <p style="max-width:680px;color:#50575e;">
                    <?php
                    printf(
                        /* translators: %s: link to Google Search Console. */
                        esc_html__( 'The sitemap is already listed in your robots.txt so search engines can discover it on their own. To have Google track it directly, submit the sitemap address once in %s.', 'loupely-canvas' ),
                        '<a href="https://search.google.com/search-console" target="_blank" rel="noopener" style="color:#5C7F68;">' . esc_html__( 'Google Search Console', 'loupely-canvas' ) . '</a>'
                    );
                    ?>
                </p>
            </div>

            <h3 style="margin-bottom:6px;margin-top:22px;"><?php echo esc_html__( 'Find and replace bar', 'loupely-canvas' ); ?></h3>
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

        <?php do_action( 'lc_settings_bottom' ); ?>
    </div>
    <?php
}
