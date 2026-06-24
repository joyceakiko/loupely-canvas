<?php
/**
 * Loupely Canvas - SEO and schema
 *
 * Adds an SEO section to the per-page settings block: an SEO title, a meta
 * description, a social share image, a schema type, and a JSON-LD box with a
 * button that builds the structured data from the fields above. Because the
 * section hooks lc_page_settings_after, it appears in every editor that mounts
 * the shared page-settings render, so a page edited in the block editor and the
 * same page opened in the Canvas Pro editor read and write the same fields.
 *
 * The front end prints the title override, the meta description, Open Graph and
 * Twitter tags, and the page JSON-LD. Site-wide defaults and any extra schema
 * come in through filters, so Canvas Pro can layer an Organization schema and
 * fallback values on top without this file knowing about it.
 *
 * The noindex control is not duplicated here: it lives in Page options above,
 * and page-meta.php prints the robots tag for it.
 *
 * The JSON-LD value is re-slashed before update_post_meta, because the metadata
 * API unslashes once on write and a backslash inside a JSON string would
 * otherwise be eaten. The box carries the lc-html-field class so the find and
 * replace tool works inside it. On output the JSON-LD is only printed when it
 * parses, so a half-finished block never reaches the page as broken markup.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ===========================================================
// SCHEMA TYPES
// ===========================================================

/**
 * The post meta keys this feature owns, in one place so the editor, the save,
 * and the wipe stay in agreement.
 */
function lc_seo_meta_keys(): array {
	return [ '_lc_seo_title', '_lc_seo_desc', '_lc_seo_image', '_lc_seo_type', '_lc_seo_jsonld' ];
}

/**
 * The schema date for a post, as ISO 8601, or an empty string when there is no
 * public date to use yet. A page or post carries a real publication date only
 * once it is published or scheduled. A draft has none, so the schema leaves the
 * date out until the post goes live and the schema is built again. A scheduled
 * post uses its scheduled date, which is the date it will publish on.
 */
function lc_seo_schema_date( int $id, string $which ): string {
	if ( $id < 1 ) {
		return '';
	}
	$status = get_post_status( $id );
	if ( ! in_array( $status, [ 'publish', 'future' ], true ) ) {
		return '';
	}
	return $which === 'modified'
		? (string) get_post_modified_time( 'c', false, $id )
		: (string) get_post_time( 'c', false, $id );
}

/**
 * The schema types offered in the per-page select. The key is stored and read
 * by the schema builder in assets/seo.js; the label is shown in the editor.
 */
function lc_seo_schema_types(): array {
	return [
		''             => __( 'None', 'loupely-canvas' ),
		'WebPage'      => __( 'Web page', 'loupely-canvas' ),
		'Article'      => __( 'Article', 'loupely-canvas' ),
		'Product'      => __( 'Product', 'loupely-canvas' ),
		'Organization' => __( 'Organization', 'loupely-canvas' ),
		'LocalBusiness'=> __( 'Local business', 'loupely-canvas' ),
		'FAQPage'      => __( 'FAQ page', 'loupely-canvas' ),
	];
}


// ===========================================================
// PER PAGE SEO SECTION
//
// Rendered through lc_page_settings_after, so it shows in the block editor meta
// box and in the Canvas Pro editor alike. No nonce and no form wrapper: the
// caller supplies its own, the same contract the rest of the block follows.
// ===========================================================

function lc_seo_render_section( $id ) {
	// When Canvas SEO is turned off, hide the SEO fields everywhere they would
	// otherwise show, pages, posts, and custom post types alike. Saved values are
	// left untouched; they simply stop appearing and stop printing.
	if ( get_option( 'lc_seo_enabled', '1' ) !== '1' ) {
		return;
	}

	$id = $id instanceof WP_Post ? (int) $id->ID : (int) $id;

	$title  = (string) get_post_meta( $id, '_lc_seo_title', true );
	$desc   = (string) get_post_meta( $id, '_lc_seo_desc', true );
	$image  = (string) get_post_meta( $id, '_lc_seo_image', true );
	$type   = (string) get_post_meta( $id, '_lc_seo_type', true );
	$jsonld = (string) get_post_meta( $id, '_lc_seo_jsonld', true );

	// With nothing chosen yet, preselect the type that fits the post type, so the
	// Generate button produces schema on the first click. None stays available.
	$post_type     = $id > 0 ? (string) get_post_type( $id ) : 'page';
	$default_type  = ( $post_type === 'post' ) ? 'Article' : 'WebPage';
	$selected_type = $type !== '' ? $type : $default_type;

	$head_css = 'margin:18px 0 6px;font-size:13px;font-weight:600;color:#1a2420;';
	$help_css = 'color:#7a9087;font-size:12px;margin:5px 0 0;max-width:680px;';
	$area_css = 'width:100%;font-family:Menlo,Consolas,monospace;font-size:13px;line-height:1.5;';

	$placeholder_title = $id > 0 ? (string) get_the_title( $id ) : '';
	$page_url          = $id > 0 ? (string) get_permalink( $id ) : '';
	$site_name         = (string) get_bloginfo( 'name' );
	$published         = lc_seo_schema_date( $id, 'published' );
	$modified          = lc_seo_schema_date( $id, 'modified' );
	$author            = $id > 0 ? (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $id ) ) : '';

	printf(
		'<div class="lc-seo" data-home-url="%1$s" data-page-url="%2$s" data-page-title="%3$s" data-site-name="%4$s" data-published="%5$s" data-modified="%6$s" data-author="%7$s">',
		esc_attr( home_url( '/' ) ),
		esc_attr( $page_url ),
		esc_attr( $placeholder_title ),
		esc_attr( $site_name ),
		esc_attr( $published ),
		esc_attr( $modified ),
		esc_attr( $author )
	);

	echo '<h4 style="' . esc_attr( $head_css ) . 'margin-top:24px;border-top:1px solid #e3e8e4;padding-top:18px;">'
		. esc_html__( 'SEO', 'loupely-canvas' ) . '</h4>';
	echo '<p style="' . esc_attr( $help_css ) . 'margin-bottom:6px;">'
		. esc_html__( 'Search and social settings for this page. The noindex switch is under Page options above.', 'loupely-canvas' )
		. '</p>';

	// SEO title.
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'SEO title', 'loupely-canvas' ) . '</h4>';
	printf(
		'<input type="text" name="lc_seo_title" class="lc-seo-title" value="%s" spellcheck="false" style="width:100%%;max-width:520px;" placeholder="%s">',
		esc_attr( $title ),
		esc_attr( $placeholder_title )
	);
	echo ' <span class="lc-seo-count" data-for="title" data-max="60" aria-live="polite"></span>';
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'Overrides the browser tab and search result title. Aim for about 50 to 60 characters so it is not cut off in search. Leave blank to use the page title.', 'loupely-canvas' )
		. '</p>';

	// Meta description.
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Meta description', 'loupely-canvas' ) . '</h4>';
	printf(
		'<textarea name="lc_seo_desc" class="lc-seo-desc" rows="2" spellcheck="true" style="width:100%%;">%s</textarea>',
		esc_textarea( $desc )
	);
	echo '<span class="lc-seo-count" data-for="desc" data-max="160" aria-live="polite" style="display:block;"></span>';
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'The summary shown under the title in search results and link previews. Aim for about 140 to 160 characters.', 'loupely-canvas' )
		. '</p>';

	// Social share image.
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Social share image', 'loupely-canvas' ) . '</h4>';
	printf(
		'<input type="url" id="lc_seo_image" name="lc_seo_image" class="lc-seo-image" value="%s" spellcheck="false" style="width:100%%;max-width:520px;">',
		esc_attr( $image )
	);
	echo ' <button type="button" class="button lc-seo-pick-image" data-target="lc_seo_image">' . esc_html__( 'Select image', 'loupely-canvas' ) . '</button>';
	echo ' <button type="button" class="button lc-seo-clear-image" data-target="lc_seo_image">' . esc_html__( 'Clear', 'loupely-canvas' ) . '</button>';
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'Used for Open Graph and Twitter cards. Falls back to the featured image, then the site default. This same image is included as the image in the page schema you build below, which is the image search engines expect there, so there is no separate schema image to set.', 'loupely-canvas' )
		. '</p>';

	// Schema type.
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Schema type', 'loupely-canvas' ) . '</h4>';
	echo '<select name="lc_seo_type" class="lc-seo-type">';
	foreach ( lc_seo_schema_types() as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $selected_type, $val, false ), esc_html( $label ) );
	}
	echo '</select>';
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'Pick the kind of structured data this page describes, then build it below.', 'loupely-canvas' )
		. '</p>';
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'When the schema carries dates (the Article type does), they come from this page\'s WordPress publish date. A draft has no publish date yet, so publish or schedule the page first, then build the schema. A scheduled page uses its scheduled date. After a notable edit to a published page, build it again to refresh the modified date.', 'loupely-canvas' )
		. '</p>';

	// JSON-LD box and generate button.
	echo '<h4 style="' . esc_attr( $head_css ) . '">' . esc_html__( 'Page schema (JSON-LD)', 'loupely-canvas' ) . '</h4>';
	echo '<p style="margin:0 0 8px;">';
	echo '<button type="button" class="button lc-seo-generate">' . esc_html__( 'Generate SEO page schema', 'loupely-canvas' ) . '</button>';
	echo '</p>';
	echo '<div class="lc-seo-summary" role="status" aria-live="polite" style="display:none;"></div>';
	printf(
		'<textarea name="lc_seo_jsonld" class="lc-html-field lc-seo-jsonld" rows="8" spellcheck="false" style="%s">%s</textarea>',
		esc_attr( $area_css ),
		esc_textarea( $jsonld )
	);
	echo '<p style="' . esc_attr( $help_css ) . '">'
		. esc_html__( 'Printed in the head as structured data. The button fills this from your fields; you can also paste your own. Anything that does not parse as JSON is skipped on the front end.', 'loupely-canvas' )
		. '</p>';

	echo '</div>';
}
add_action( 'lc_page_settings_after', 'lc_seo_render_section' );


// ===========================================================
// SAVE
//
// Hooked to lc_after_page_settings_save, which fires from the shared save after
// the caller has verified its own nonce and capability. So the values are
// stored here from any editor that mounts the shared save.
// ===========================================================

function lc_seo_save( $post_id, $can_html ) {
	$post_id = (int) $post_id;

	if ( isset( $_POST['lc_seo_title'] ) ) {
		$val = sanitize_text_field( wp_unslash( $_POST['lc_seo_title'] ) );
		lc_seo_update_or_clear( $post_id, '_lc_seo_title', $val );
	}

	if ( isset( $_POST['lc_seo_desc'] ) ) {
		$val = sanitize_textarea_field( wp_unslash( $_POST['lc_seo_desc'] ) );
		lc_seo_update_or_clear( $post_id, '_lc_seo_desc', $val );
	}

	if ( isset( $_POST['lc_seo_image'] ) ) {
		$val = esc_url_raw( wp_unslash( $_POST['lc_seo_image'] ) );
		lc_seo_update_or_clear( $post_id, '_lc_seo_image', $val );
	}

	if ( isset( $_POST['lc_seo_type'] ) ) {
		$val   = sanitize_text_field( wp_unslash( $_POST['lc_seo_type'] ) );
		$types = array_keys( lc_seo_schema_types() );
		if ( ! in_array( $val, $types, true ) ) {
			$val = '';
		}
		lc_seo_update_or_clear( $post_id, '_lc_seo_type', $val );
	}

	if ( isset( $_POST['lc_seo_jsonld'] ) ) {
		$val = trim( (string) wp_unslash( $_POST['lc_seo_jsonld'] ) );
		if ( $val !== '' ) {
			// Re-slash so the metadata API's single unslash on write leaves any
			// backslash inside the JSON intact.
			update_post_meta( $post_id, '_lc_seo_jsonld', wp_slash( $val ) );
		} else {
			delete_post_meta( $post_id, '_lc_seo_jsonld' );
		}
	}
}
add_action( 'lc_after_page_settings_save', 'lc_seo_save', 10, 2 );

/**
 * Store a value, or delete the meta when the value is empty, so a cleared field
 * does not leave a stale entry behind.
 */
function lc_seo_update_or_clear( int $post_id, string $key, string $value ): void {
	if ( $value !== '' ) {
		update_post_meta( $post_id, $key, $value );
	} else {
		delete_post_meta( $post_id, $key );
	}
}


// ===========================================================
// EDITOR ASSETS
//
// The media picker and the schema builder. Loaded on the block editor screens
// here; Canvas Pro loads the same script on its editor screen, so the controls
// work wherever the shared block is mounted. The script reads context from the
// section's data attributes and needs nothing from PHP.
// ===========================================================

function lc_seo_admin_assets( $hook ) {
	if ( get_option( 'lc_seo_enabled', '1' ) !== '1' ) {
		return;
	}
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	lc_seo_enqueue_editor_assets();
}
add_action( 'admin_enqueue_scripts', 'lc_seo_admin_assets' );

/**
 * Enqueue the SEO editor script, its styles, and the media library, and hand
 * the script its translated labels. Called from the block editor screens here,
 * and from any other editor that mounts the SEO section, so the controls and
 * the field summary read correctly wherever the section appears.
 */
function lc_seo_enqueue_editor_assets() {
	wp_enqueue_media();

	$js_rel = '/assets/seo.js';
	$js_abs = get_template_directory() . $js_rel;
	$js_ver = file_exists( $js_abs ) ? (string) filemtime( $js_abs ) : LC_VERSION;
	wp_enqueue_script( 'lc-seo', get_template_directory_uri() . $js_rel, [], $js_ver, true );
	wp_localize_script( 'lc-seo', 'lcSeo', lc_seo_l10n() );

	$css_rel = '/assets/seo.css';
	$css_abs = get_template_directory() . $css_rel;
	$css_ver = file_exists( $css_abs ) ? (string) filemtime( $css_abs ) : LC_VERSION;
	wp_enqueue_style( 'lc-seo', get_template_directory_uri() . $css_rel, [], $css_ver );
}

/**
 * The translated labels the SEO editor script shows in the field summary and
 * the character counters. Kept here so every editor that enqueues the script
 * hands it the same set.
 */
function lc_seo_l10n(): array {
	return [
		'included'    => __( 'Included', 'loupely-canvas' ),
		'notIncluded' => __( 'Not included', 'loupely-canvas' ),
		'none'        => __( 'Select a schema type above to build the page schema.', 'loupely-canvas' ),
		'faq'         => __( 'A blank question and answer was added. Fill it in below.', 'loupely-canvas' ),
		'over'        => __( 'over recommended length', 'loupely-canvas' ),
		'chars'       => __( 'characters', 'loupely-canvas' ),
		'labels'      => [
			'title'       => __( 'Title', 'loupely-canvas' ),
			'name'        => __( 'Name', 'loupely-canvas' ),
			'description' => __( 'Description', 'loupely-canvas' ),
			'image'       => __( 'Image', 'loupely-canvas' ),
			'logo'        => __( 'Logo', 'loupely-canvas' ),
			'url'         => __( 'Page URL', 'loupely-canvas' ),
			'siteUrl'     => __( 'Site URL', 'loupely-canvas' ),
			'published'   => __( 'Published date', 'loupely-canvas' ),
			'modified'    => __( 'Modified date', 'loupely-canvas' ),
			'author'      => __( 'Author', 'loupely-canvas' ),
		],
	];
}


// ===========================================================
// FRONT END OUTPUT
// ===========================================================

/**
 * The post whose SEO applies to the current request, reusing the resolver that
 * the rest of the per-page settings use. A singular page or post resolves to
 * itself; the blog index resolves to the assigned Posts page; true archives and
 * search resolve to 0.
 */
function lc_seo_post_id(): int {
	return function_exists( 'lc_settings_post_id' ) ? lc_settings_post_id() : ( is_singular() ? (int) get_queried_object_id() : 0 );
}

/**
 * Override the document title with the per-page SEO title when set.
 */
function lc_seo_document_title( $title ) {
	if ( is_admin() ) {
		return $title;
	}
	$id = lc_seo_post_id();
	if ( $id > 0 ) {
		$t = (string) get_post_meta( $id, '_lc_seo_title', true );
		if ( trim( $t ) !== '' ) {
			return $t;
		}
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'lc_seo_document_title', 20 );

/**
 * The meta description for this request: the per-page value, then the site-wide
 * default supplied through the filter (Canvas Pro feeds it).
 */
function lc_seo_description( int $id ): string {
	$desc = $id > 0 ? (string) get_post_meta( $id, '_lc_seo_desc', true ) : '';
	if ( $desc === '' ) {
		$desc = (string) apply_filters( 'lc_seo_default_description', '' );
	}
	return $desc;
}

/**
 * The share image for this request: the per-page value, then the featured
 * image, then the site-wide default supplied through the filter.
 */
function lc_seo_image( int $id ): string {
	$image = '';
	if ( $id > 0 ) {
		$image = (string) get_post_meta( $id, '_lc_seo_image', true );
		if ( $image === '' && has_post_thumbnail( $id ) ) {
			$image = (string) get_the_post_thumbnail_url( $id, 'large' );
		}
	}
	if ( $image === '' ) {
		$image = (string) apply_filters( 'lc_seo_default_image', '' );
	}
	return $image;
}

/**
 * The flags used when encoding JSON-LD, so a tag, ampersand, or slash in the
 * data cannot break out of the script element.
 */
function lc_seo_jsonld_flags(): int {
	return JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
}

/**
 * Print the meta description, Open Graph and Twitter tags, the page JSON-LD,
 * and any extra schema supplied through the filter. Robots noindex is printed
 * by page-meta.php, so it is not repeated here.
 */
/**
 * Gate all Canvas SEO output on the lc_seo_enabled option. This filter is the
 * single switch the head function and the plugin's SEO both run through, so
 * turning the option off stops every Canvas tag and schema at once, leaving the
 * field to whatever other SEO plugin is in use.
 */
function lc_seo_output_enabled_option( $enabled ) {
	return get_option( 'lc_seo_enabled', '1' ) === '1' ? $enabled : false;
}
add_filter( 'lc_seo_output_enabled', 'lc_seo_output_enabled_option' );

function lc_seo_head(): void {
	if ( is_admin() || ! apply_filters( 'lc_seo_output_enabled', true ) ) {
		return;
	}

	$id          = lc_seo_post_id();
	$is_singular = ( $id > 0 );
	$desc        = lc_seo_description( $id );
	$image       = lc_seo_image( $id );
	$title       = wp_get_document_title();
	$url         = $is_singular ? (string) get_permalink( $id ) : home_url( '/' );
	$type        = ( $is_singular && is_single() ) ? 'article' : 'website';

	if ( $desc !== '' ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}

	// Open Graph.
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc !== '' ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	if ( $image !== '' ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	}

	// Twitter.
	echo '<meta name="twitter:card" content="' . ( $image !== '' ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	$twitter_site = (string) apply_filters( 'lc_seo_twitter_site', '' );
	if ( $twitter_site !== '' ) {
		echo '<meta name="twitter:site" content="' . esc_attr( $twitter_site ) . '">' . "\n";
	}
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc !== '' ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	if ( $image !== '' ) {
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
	}

	// Page JSON-LD, printed only when it parses.
	if ( $is_singular ) {
		$jsonld = (string) get_post_meta( $id, '_lc_seo_jsonld', true );
		if ( trim( $jsonld ) !== '' ) {
			$decoded = json_decode( $jsonld, true );
			if ( is_array( $decoded ) ) {
				echo '<script type="application/ld+json">' . wp_json_encode( $decoded, lc_seo_jsonld_flags() ) . '</script>' . "\n";
			}
		}
	}

	// Extra schema supplied by an extension, as an array of schema arrays.
	$extra = (array) apply_filters( 'lc_seo_extra_schemas', [] );
	foreach ( $extra as $schema ) {
		if ( is_array( $schema ) && $schema ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, lc_seo_jsonld_flags() ) . '</script>' . "\n";
		}
	}
}
add_action( 'wp_head', 'lc_seo_head', 5 );
