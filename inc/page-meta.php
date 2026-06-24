<?php
/**
 * Loupely Canvas - per page settings
 *
 * Adds a single meta box to pages and posts that collects the per page
 * controls a hand-coder would otherwise wire up by hand every time: a header
 * and footer override, a hide-title hook, a full-width unwrap, a noindex flag,
 * a switch that turns off the site-wide head and body code for one page, a free
 * body class field, and per page head and body-end code boxes.
 *
 * One source of truth. lc_page_settings_render() prints the inputs and
 * lc_page_settings_save() reads and stores them. Both are nonce-agnostic, so
 * each caller verifies its own nonce: the Gutenberg meta box here, and the
 * Canvas Pro Canvas Pages editor, which mounts the same two functions so a
 * Canvas Page reaches every setting too. Front-end rendering reads the saved
 * post meta directly, so it applies to any page regardless of which editor
 * created it.
 *
 * Code-bearing fields are wrapped in wp_slash() before update_post_meta,
 * because the metadata API unslashes once on write; without the re-slash a
 * lone backslash or a JS hex escape in the head code would be eaten. The
 * custom textareas carry the lc-html-field class so the find and replace tool
 * works inside them.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ===========================================================
// META KEYS
// ===========================================================

/**
 * The post meta keys this feature owns, paired with the form field names they
 * read from. Kept in one place so the meta box, the save handler, and any
 * other editor that mounts this feature stay in agreement.
 */
function lc_page_settings_keys(): array {
	return [
		'header_mode'    => '_lc_header_mode',
		'header_custom'  => '_lc_header_custom',
		'footer_mode'    => '_lc_footer_mode',
		'footer_custom'  => '_lc_footer_custom',
		'hide_title'     => '_lc_hide_title',
		'hide_post_nav'  => '_lc_hide_post_nav',
		'unwrap'         => '_lc_unwrap',
		'noindex'        => '_lc_noindex',
		'disable_global' => '_lc_disable_global',
		'hide_archive_header' => '_lc_hide_archive_header',
		'body_class'     => '_lc_body_class',
		'page_head'      => '_lc_page_head',
		'page_body_end'  => '_lc_page_body_end',
	];
}


// ===========================================================
// META BOX REGISTRATION
// ===========================================================

function lc_add_meta_box() {
	foreach ( [ 'page', 'post' ] as $type ) {
		add_meta_box(
			'lc_page_settings',
			__( 'Page settings (Loupely Canvas)', 'loupely-canvas' ),
			'lc_render_meta_box',
			$type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'lc_add_meta_box' );


/**
 * The Gutenberg meta box: its own nonce, then the shared field markup.
 */
function lc_render_meta_box( $post ) {
	wp_nonce_field( 'lc_save_meta', 'lc_meta_nonce' );
	lc_page_settings_render( $post );
}


// ===========================================================
// SHARED FIELD MARKUP
//
// Prints the inputs for one post. No nonce and no form wrapper, so it can be
// mounted inside any form that supplies its own. Field names match the keys
// read back in lc_page_settings_save().
// ===========================================================

function lc_page_settings_render( $post ) {
	$id        = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
	$post_type = $id > 0 ? (string) get_post_type( $id ) : 'page';

	$h_mode   = get_post_meta( $id, '_lc_header_mode', true ) ?: 'global';
	$f_mode   = get_post_meta( $id, '_lc_footer_mode', true ) ?: 'global';
	$h_custom = get_post_meta( $id, '_lc_header_custom', true );
	$f_custom = get_post_meta( $id, '_lc_footer_custom', true );

	/**
	 * The effective mode shown in the control, without changing stored meta.
	 * Canvas Pro uses this so a page that already carries a set assignment shows
	 * the set option selected here.
	 */
	$h_mode = (string) apply_filters( 'lc_page_settings_effective_mode', $h_mode, 'header', $id );
	$f_mode = (string) apply_filters( 'lc_page_settings_effective_mode', $f_mode, 'footer', $id );

	$hide_title = get_post_meta( $id, '_lc_hide_title', true ) === '1';
	$hide_nav   = get_post_meta( $id, '_lc_hide_post_nav', true ) === '1';
	$unwrap     = get_post_meta( $id, '_lc_unwrap', true ) === '1';
	$noindex    = get_post_meta( $id, '_lc_noindex', true ) === '1';
	$disable    = get_post_meta( $id, '_lc_disable_global', true ) === '1';
	$hide_arch  = get_post_meta( $id, '_lc_hide_archive_header', true ) === '1';
	$is_posts_page = ( $id > 0 && $id === (int) get_option( 'page_for_posts' ) );
	$body_class = get_post_meta( $id, '_lc_body_class', true );
	$head_code  = get_post_meta( $id, '_lc_page_head', true );
	$body_code  = get_post_meta( $id, '_lc_page_body_end', true );

	$head_css  = 'margin:18px 0 6px;font-size:13px;font-weight:600;color:#1a2420;';
	$help_css  = 'color:#7a9087;font-size:12px;margin:5px 0 0;max-width:680px;';
	$area_css  = 'width:100%;font-family:Menlo,Consolas,monospace;font-size:13px;line-height:1.5;';
	$check_css = 'display:block;font-size:13px;color:#4a5e52;margin:0 0 8px;';

	echo '<p style="color:#4a5e52;max-width:680px;margin-top:2px;">'
		. esc_html__( 'Settings for this page only. They override the site defaults here, and leave every other page untouched.', 'loupely-canvas' )
		. '</p>';

	// --- Header and footer overrides ---
	lc_ps_header_footer_control( 'header', __( 'Header', 'loupely-canvas' ), $h_mode, $h_custom, $head_css, $area_css, $id );
	lc_ps_header_footer_control( 'footer', __( 'Footer', 'loupely-canvas' ), $f_mode, $f_custom, $head_css, $area_css, $id );

	/**
	 * After both header and footer controls. Canvas Pro uses this to note when an
	 * assignment rule applies a set to this page.
	 */
	do_action( 'lc_page_settings_after_hf', $id );

	// --- Page options (checkboxes) ---
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Page options', 'loupely-canvas' ) . '</h4>';

	printf(
		'<label style="%1$s"><input type="checkbox" name="lc_hide_title" value="1"%2$s> %3$s</label>',
		esc_attr( $check_css ),
		checked( $hide_title, true, false ),
		esc_html__( 'Hide the title on this post or page', 'loupely-canvas' )
	);
	if ( $post_type === 'post' ) {
		echo '<p style="' . esc_attr( $help_css ) . 'margin:-3px 0 10px 22px;">'
			. esc_html__( 'Removes the title from this post and adds the lc-hide-title body class. A custom Single post template that uses its own title class can be hidden with that class.', 'loupely-canvas' )
			. '</p>';
		printf(
			'<label style="%1$s"><input type="checkbox" name="lc_hide_post_nav" value="1"%2$s> %3$s</label>',
			esc_attr( $check_css ),
			checked( $hide_nav, true, false ),
			esc_html__( 'Hide the previous and next post links on this post', 'loupely-canvas' )
		);
	} else {
		echo '<p style="' . esc_attr( $help_css ) . 'margin:-3px 0 10px 22px;">'
			. esc_html__( 'Adds the lc-hide-title body class. The theme prints no title on a page, so use this to hide a title that comes from your content or another plugin.', 'loupely-canvas' )
			. '</p>';
	}
	printf(
		'<label style="%1$s"><input type="checkbox" name="lc_unwrap" value="1"%2$s> %3$s</label>',
		esc_attr( $check_css ),
		checked( $unwrap, true, false ),
		esc_html__( 'Full width: render the content with no theme wrapper around it', 'loupely-canvas' )
	);
	printf(
		'<label style="%1$s"><input type="checkbox" name="lc_noindex" value="1"%2$s> %3$s</label>',
		esc_attr( $check_css ),
		checked( $noindex, true, false ),
		esc_html__( 'Ask search engines not to index this page (noindex)', 'loupely-canvas' )
	);
	printf(
		'<label style="%1$s"><input type="checkbox" name="lc_disable_global" value="1"%2$s> %3$s</label>',
		esc_attr( $check_css ),
		checked( $disable, true, false ),
		esc_html__( 'Skip the site-wide head and body code on this page', 'loupely-canvas' )
	);
	if ( $is_posts_page ) {
		printf(
			'<label style="%1$s"><input type="checkbox" name="lc_hide_archive_header" value="1"%2$s> %3$s</label>',
			esc_attr( $check_css ),
			checked( $hide_arch, true, false ),
			esc_html__( 'Hide the archive header on the blog page', 'loupely-canvas' )
		);
		echo '<p style="' . esc_attr( $help_css ) . 'margin:-3px 0 10px 22px;">'
			. esc_html__( 'This is the page assigned as your Posts page. Turn this on to drop the archive header here, so only the content above the post list shows.', 'loupely-canvas' )
			. '</p>';
	}

	// --- Body class ---
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Body class', 'loupely-canvas' ) . '</h4>';
	printf(
		'<input type="text" name="lc_body_class" value="%s" spellcheck="false" style="width:100%%;max-width:420px;" placeholder="%s">',
		esc_attr( $body_class ),
		esc_attr__( 'e.g. landing dark-hero', 'loupely-canvas' )
	);
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'Space-separated classes added to the body tag, so you can scope CSS to this page from your Head code box.', 'loupely-canvas' )
		. '</p>';

	// --- Per page head code ---
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Head code for this page', 'loupely-canvas' ) . '</h4>';
	printf(
		'<textarea name="lc_page_head" class="lc-html-field" rows="6" spellcheck="false" style="%s">%s</textarea>',
		esc_attr( $area_css ),
		esc_textarea( $head_code )
	);
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'Printed in the head on this page only. Use it for a one-off stylesheet, a meta tag, or structured data without touching the site-wide Head box.', 'loupely-canvas' )
		. '</p>';

	// --- Per page body end code ---
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Body end code for this page', 'loupely-canvas' ) . '</h4>';
	printf(
		'<textarea name="lc_page_body_end" class="lc-html-field" rows="6" spellcheck="false" style="%s">%s</textarea>',
		esc_attr( $area_css ),
		esc_textarea( $body_code )
	);
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'Printed just before the closing body tag on this page only. Use it for a page-specific script or widget.', 'loupely-canvas' )
		. '</p>';

	/**
	 * After the theme's own page-settings fields. A feature that adds more
	 * controls to this block, such as the SEO section, renders them here, so they
	 * appear in every editor that mounts lc_page_settings_render().
	 */
	do_action( 'lc_page_settings_after', $id );

	// The show/hide toggle for the custom header and footer boxes lives in
	// assets/page-meta.js, enqueued on the editor screens by lc_page_meta_assets().
}


/**
 * One header or footer override control: a mode select and its custom HTML box.
 */
function lc_ps_header_footer_control( string $part, string $label, string $mode, string $custom, string $head_css, string $area_css, int $id = 0 ) {
	$modes = [
		'global' => __( 'Use the global header and footer', 'loupely-canvas' ),
		'custom' => __( 'Use custom HTML for this page', 'loupely-canvas' ),
		'none'   => __( 'Show none on this page', 'loupely-canvas' ),
	];
	if ( $part === 'footer' ) {
		$modes['global'] = __( 'Use the global footer', 'loupely-canvas' );
		$modes['custom'] = __( 'Use custom HTML for this page', 'loupely-canvas' );
		$modes['none']   = __( 'Show no footer on this page', 'loupely-canvas' );
	} else {
		$modes['global'] = __( 'Use the global header', 'loupely-canvas' );
		$modes['none']   = __( 'Show no header on this page', 'loupely-canvas' );
	}

	/**
	 * Header and footer modes beyond the three the theme ships. Canvas Pro adds
	 * a saved set as a fourth choice through this filter. With nothing hooked,
	 * the control offers only the theme's own modes.
	 */
	$modes = (array) apply_filters( 'lc_page_settings_hf_modes', $modes, $part );

	$wrap_id = 'lc-' . $part . '-custom-wrap';

	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html( $label ) . '</h4>';
	printf( '<select name="lc_%1$s_mode" data-lc-part="%1$s">', esc_attr( $part ) );
	foreach ( $modes as $val => $text ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $mode, $val, false ), esc_html( $text ) );
	}
	echo '</select>';
	printf(
		'<div id="%1$s" data-lc-part="%2$s" data-lc-mode="custom" style="margin-top:8px;%3$s"><textarea name="lc_%2$s_custom" class="lc-html-field" rows="6" spellcheck="false" style="%4$s">%5$s</textarea></div>',
		esc_attr( $wrap_id ),
		esc_attr( $part ),
		$mode === 'custom' ? '' : 'display:none;',
		esc_attr( $area_css ),
		esc_textarea( $custom )
	);

	/**
	 * Extra controls for an added mode, like Canvas Pro's set picker. Fires after
	 * the custom box, so an added control wrapped with data-lc-part and
	 * data-lc-mode shows and hides alongside it from assets/page-meta.js.
	 */
	do_action( 'lc_page_settings_hf_extra', $part, $mode, $id );
}


// ===========================================================
// SHARED SAVE
//
// Reads the field names from $_POST and stores the meta. Nonce-agnostic: the
// caller verifies its own nonce and capability first. Code fields are gated on
// unfiltered_html and re-slashed so the metadata API's single unslash on write
// leaves the value intact.
// ===========================================================

function lc_page_settings_save( int $post_id ) {
	$can_html = current_user_can( 'unfiltered_html' );

	// Header and footer mode plus custom HTML.
	$allowed = (array) apply_filters( 'lc_page_settings_modes', [ 'global', 'custom', 'none' ] );
	foreach ( [ 'header', 'footer' ] as $part ) {
		$mode = isset( $_POST[ 'lc_' . $part . '_mode' ] ) ? sanitize_key( $_POST[ 'lc_' . $part . '_mode' ] ) : 'global';
		if ( ! in_array( $mode, $allowed, true ) ) {
			$mode = 'global';
		}
		update_post_meta( $post_id, '_lc_' . $part . '_mode', $mode );

		if ( isset( $_POST[ 'lc_' . $part . '_custom' ] ) ) {
			$raw   = (string) wp_unslash( $_POST[ 'lc_' . $part . '_custom' ] );
			$value = $can_html ? $raw : wp_kses_post( $raw );
			update_post_meta( $post_id, '_lc_' . $part . '_custom', wp_slash( $value ) );
		}
	}

	// Checkboxes: store '1' when checked, otherwise an empty string.
	foreach ( [ 'hide_title', 'hide_post_nav', 'unwrap', 'noindex', 'disable_global', 'hide_archive_header' ] as $flag ) {
		update_post_meta( $post_id, '_lc_' . $flag, empty( $_POST[ 'lc_' . $flag ] ) ? '' : '1' );
	}

	// Body class: keep only valid HTML class tokens.
	if ( isset( $_POST['lc_body_class'] ) ) {
		$raw     = (string) wp_unslash( $_POST['lc_body_class'] );
		$tokens  = preg_split( '/\s+/', trim( $raw ) ) ?: [];
		$clean   = array_filter( array_map( 'sanitize_html_class', $tokens ) );
		update_post_meta( $post_id, '_lc_body_class', implode( ' ', $clean ) );
	}

	// Per page head and body-end code.
	foreach ( [ 'page_head', 'page_body_end' ] as $code_field ) {
		if ( isset( $_POST[ 'lc_' . $code_field ] ) ) {
			$raw   = (string) wp_unslash( $_POST[ 'lc_' . $code_field ] );
			$value = $can_html ? $raw : wp_kses_post( $raw );
			update_post_meta( $post_id, '_lc_' . $code_field, wp_slash( $value ) );
		}
	}

	/**
	 * After the theme's own fields are stored. An extension that added a mode or
	 * field, such as Canvas Pro's set picker, saves its meta here. Runs only from
	 * a verified save, since the caller checks the nonce and capability first.
	 */
	do_action( 'lc_after_page_settings_save', $post_id, $can_html );
}


/**
 * The save_post hook for the Gutenberg meta box. Verifies the meta box nonce,
 * skips autosave, checks the edit capability, then runs the shared save.
 */
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
	lc_page_settings_save( (int) $post_id );
}
add_action( 'save_post', 'lc_save_meta' );


// ===========================================================
// EDITOR ASSETS
//
// Loads the small toggle script on the post and page editor screens, where the
// settings meta box appears. Behavior lives in assets/page-meta.js, not inline.
// ===========================================================

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


// ===========================================================
// FRONT END APPLICATION
//
// Reads the saved meta for the current singular object and applies it. Each
// reader guards on is_singular() so it is inert on archives, the home page,
// and search. These helpers are also what render.php and the templates consult.
// ===========================================================

/**
 * The post being viewed, or 0 when the current request is not a singular page
 * or post. Used by the single-post navigation control, which only applies on a
 * true single post.
 */
function lc_singular_id(): int {
	return is_singular() ? (int) get_queried_object_id() : 0;
}

/**
 * The post whose per page settings apply to the current request.
 *
 * A singular page or post resolves to itself. The blog index resolves to the
 * page assigned as the Posts page under Settings, Reading, because that index
 * is not a singular query (it is the home query) yet it has a real page behind
 * it whose settings should still take effect. True archives, like category and
 * tag listings and search, have no single page to attach settings to, so they
 * resolve to 0 and the per page code stays inert there.
 */
function lc_settings_post_id(): int {
	if ( is_singular() ) {
		return (int) get_queried_object_id();
	}
	if ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			return $posts_page;
		}
	}
	return 0;
}

/**
 * Whether the site-wide head and body code should be skipped on this page.
 * render.php calls this before printing the global Head and Body end boxes.
 */
function lc_global_code_disabled(): bool {
	$id = lc_settings_post_id();
	return $id > 0 && get_post_meta( $id, '_lc_disable_global', true ) === '1';
}

/**
 * Whether this page opts out of the theme content wrapper. The page, single,
 * and blog index templates call this to decide whether to print the
 * main.lc-content element.
 */
function lc_page_is_unwrapped(): bool {
	$id = lc_settings_post_id();
	return $id > 0 && get_post_meta( $id, '_lc_unwrap', true ) === '1';
}

/**
 * Whether single.php should skip the previous and next post links on this post.
 */
function lc_hide_post_nav(): bool {
	$id = lc_singular_id();
	return $id > 0 && get_post_meta( $id, '_lc_hide_post_nav', true ) === '1';
}

/**
 * Whether the blog index should drop the archive header. Set on the page
 * assigned as the Posts page, and read here on the blog index, where
 * lc_settings_post_id() resolves to that page.
 */
function lc_hide_archive_header(): bool {
	$id = lc_settings_post_id();
	return $id > 0 && get_post_meta( $id, '_lc_hide_archive_header', true ) === '1';
}

/**
 * Add the per page body classes: the free class field, plus the hide-title and
 * unwrapped hooks so CSS can target either state.
 */
function lc_apply_body_class( array $classes ): array {
	$id = lc_settings_post_id();
	if ( $id < 1 ) {
		return $classes;
	}

	$extra = (string) get_post_meta( $id, '_lc_body_class', true );
	if ( $extra !== '' ) {
		foreach ( preg_split( '/\s+/', $extra ) as $token ) {
			$token = sanitize_html_class( $token );
			if ( $token !== '' ) {
				$classes[] = $token;
			}
		}
	}
	if ( get_post_meta( $id, '_lc_hide_title', true ) === '1' ) {
		$classes[] = 'lc-hide-title';
	}
	if ( get_post_meta( $id, '_lc_unwrap', true ) === '1' ) {
		$classes[] = 'lc-unwrapped';
	}
	return $classes;
}
add_filter( 'body_class', 'lc_apply_body_class' );

/**
 * When hide-title is set, hide the title element the default templates render
 * (.lc-post-title), scoped to the lc-hide-title body class so it only affects
 * this page. The matching token is also blanked in post-templates.php, so a
 * custom template that keeps the .lc-post-title class is covered here, and any
 * other title class is covered by the body-class hook plus the blanked token.
 */
function lc_print_hide_title_style(): void {
	$id = lc_settings_post_id();
	if ( $id > 0 && get_post_meta( $id, '_lc_hide_title', true ) === '1' ) {
		echo '<style>body.lc-hide-title .lc-post-title{display:none;}</style>' . "\n";
	}
}
add_action( 'wp_head', 'lc_print_hide_title_style', 100 );

/**
 * Print the noindex robots tag early in the head when this page asks for it.
 */
function lc_print_noindex(): void {
	$id = lc_settings_post_id();
	if ( $id > 0 && get_post_meta( $id, '_lc_noindex', true ) === '1' ) {
		echo '<meta name="robots" content="noindex" />' . "\n";
	}
}
add_action( 'wp_head', 'lc_print_noindex', 1 );

/**
 * Print the per page head code, after the site-wide Head box so it can build on
 * or override it. Runs whether or not the site-wide code is disabled.
 */
function lc_print_page_head_code(): void {
	$id = lc_settings_post_id();
	if ( $id < 1 ) {
		return;
	}
	$code = (string) get_post_meta( $id, '_lc_page_head', true );
	if ( trim( $code ) !== '' ) {
		echo "\n" . $code . "\n";
	}
}
add_action( 'wp_head', 'lc_print_page_head_code', 100 );

/**
 * Print the per page body-end code, after the site-wide Body end box.
 */
function lc_print_page_body_end_code(): void {
	$id = lc_settings_post_id();
	if ( $id < 1 ) {
		return;
	}
	$code = (string) get_post_meta( $id, '_lc_page_body_end', true );
	if ( trim( $code ) !== '' ) {
		echo "\n" . $code . "\n";
	}
}
add_action( 'wp_footer', 'lc_print_page_body_end_code', 100 );
