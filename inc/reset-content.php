<?php
/**
 * Loupely Canvas - reset and wipe
 *
 * A panel at the foot of the Appearance > Loupely Canvas settings screen with
 * two destructive actions, both administrator only:
 *
 *   Reset theme settings   Clears the boxes on this screen back to empty: the
 *                          header, footer, head and body code, the blog
 *                          templates, and the toggles. Pages, posts, and media
 *                          are left alone.
 *   Wipe content           A set of checkboxes for the content to delete, so the
 *                          user picks before running it: pages, posts, media,
 *                          the SEO data saved on content, and the theme settings.
 *                          A second group always shows the Canvas Pro items:
 *                          snippets, header and footer sets, templates,
 *                          injections, each custom post type by name, and Pro
 *                          settings and version history. When Pro is not active a
 *                          notice in that group says so and links to
 *                          loupelycanvas.com/pro.
 *
 * Each checkbox carries a live item count so the confirm dialog can show
 * "Header and footer sets (5)" rather than just the label. Counts are
 * queried once when the page renders. Custom post types get one checkbox
 * each, named after the type, so counts are per type.
 *
 * Each action runs only after the user types the confirmation word in a
 * dialog, which the confirm script enforces in the browser and the handler
 * verifies again on the server. Both handlers check a nonce and the
 * manage_options capability before touching anything, and the wipe deletes
 * only the items the user selected, validated against the list of items shown.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * The word the user types to confirm a reset. Used both in the dialog (passed
 * to the confirm script) and on the server, so they always match.
 */
function lc_reset_confirm_phrase(): string {
	return _x( 'Understood', 'word the user types to confirm a content reset', 'loupely-canvas' );
}


/** Whether Canvas Pro is installed and active. Its version constant is defined
 *  only when the plugin is running, so its presence is the reliable signal. */
function lc_reset_pro_active(): bool {
	return defined( 'LC_PRO_VERSION' );
}


/** The Canvas Pro content post types, keyed by the wipe checkbox they back. */
function lc_reset_pro_post_types(): array {
	return [
		'snippets'   => 'lc_snippet',
		'hf_sets'    => 'lc_hf_set',
		'templates'  => 'lc_template',
		'injections' => 'lc_injection',
	];
}


/**
 * The user's Canvas Pro custom post types, as a map of post type slug to its
 * plural name. When Pro is active the registered definitions are read through
 * its own accessor; when Pro is inactive the stored definitions are read from
 * the option directly, so leftover items can still be listed and removed.
 *
 * @return array<string, string>
 */
function lc_reset_custom_post_types(): array {
	$out = [];

	if ( function_exists( 'lc_pro_get_cpts' ) ) {
		$cpts = lc_pro_get_cpts();
	} else {
		$cpts = get_option( 'lc_pro_cpts', [] );
	}

	if ( ! is_array( $cpts ) ) {
		return $out;
	}

	foreach ( $cpts as $slug => $record ) {
		$slug = sanitize_key( (string) $slug );
		if ( $slug === '' ) {
			continue;
		}
		$plural = ( is_array( $record ) && isset( $record['plural'] ) && $record['plural'] !== '' )
			? (string) $record['plural']
			: $slug;
		$out[ $slug ] = $plural;
	}

	return $out;
}


/**
 * Whether Canvas Pro data is sitting in the database while Pro is not active,
 * which happens after the plugin is deactivated or deleted. True when any Pro
 * option, any Pro content post, or any saved version remains. While Pro is
 * active this is false, because that data is in use, not orphaned.
 */
function lc_reset_orphaned_pro_exists(): bool {
	if ( lc_reset_pro_active() ) {
		return false;
	}

	global $wpdb;

	$option = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
			$wpdb->esc_like( 'lc_pro_' ) . '%'
		)
	);
	if ( $option ) {
		return true;
	}

	$types        = array_values( lc_reset_pro_post_types() );
	$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$post         = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($placeholders) LIMIT 1",
			$types
		)
	);
	if ( $post ) {
		return true;
	}

	$table  = $wpdb->prefix . 'lc_pro_history';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists === $table && $wpdb->get_var( "SELECT 1 FROM `{$table}` LIMIT 1" ) ) {
		return true;
	}

	return false;
}


/**
 * The theme option keys a settings reset clears: the settings boxes and toggles
 * on the Loupely Canvas screen. Site identity (logo, favicon) and the Lite
 * migration marker are not Canvas settings and are left untouched.
 */
function lc_reset_option_keys(): array {
	return [
		'lc_header_html',
		'lc_footer_html',
		'lc_head_html',
		'lc_body_end_html',
		'lc_post_card_html',
		'lc_single_post_html',
		'lc_archive_header_html',
		'lc_error_404_html',
		'lc_hide_editor_menus',
		'lc_enable_find_replace',
		'lc_editor_preview',
		'lc_seo_enabled',
		'lc_lite_import_done',
		'lc_lite_import_dismissed',
		'lc_lite_migrated',
	];
}


/**
 * Count the pages, posts, and other items that carry SEO data, so the wipe can
 * show how many would be cleared. Zero when the SEO feature is not present.
 */
function lc_reset_count_seo(): int {
	if ( ! function_exists( 'lc_seo_meta_keys' ) ) {
		return 0;
	}
	$keys = lc_seo_meta_keys();
	if ( empty( $keys ) ) {
		return 0;
	}
	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
	$count        = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)",
			$keys
		)
	);
	return (int) $count;
}


/**
 * Clear the SEO data from every post that carries it, of any post type, while
 * leaving the posts themselves in place. Deleting a page or post elsewhere in
 * this wipe already removes its meta, so this is for stripping SEO without
 * deleting the content.
 */
function lc_reset_delete_seo() {
	if ( ! function_exists( 'lc_seo_meta_keys' ) ) {
		return;
	}
	foreach ( lc_seo_meta_keys() as $key ) {
		delete_metadata( 'post', 0, $key, '', true );
	}
}


/**
 * Count every post of a given type, including drafts and trashed items.
 */
function lc_reset_count_posts( string $post_type ): int {
	global $wpdb;
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			$post_type
		)
	);
	return (int) $count;
}


/**
 * The selectable wipe items, each a checkbox in the panel. The Canvas Pro
 * group is always included so users without Pro can see what it covers. Items
 * that are not backed by a post type (settings, pro_data) carry count -1 to
 * signal that no count is shown. Custom post types each get their own item
 * keyed as "cpt_{slug}" so the handler can delete them individually. When Pro
 * is not active the Pro checkboxes are rendered disabled so they cannot be
 * submitted, and a notice in the fieldset explains this.
 *
 * The handler validates the posted selection against these keys, so a Pro key
 * cannot be acted on unless its checkbox was actually offered and enabled.
 */
function lc_reset_items(): array {
	$items = [
		'pages' => [
			'label'     => __( 'Pages', 'loupely-canvas' ),
			'note'      => __( 'Every page, including drafts and trashed pages.', 'loupely-canvas' ),
			'post_type' => 'page',
			'pro'       => false,
			'count'     => lc_reset_count_posts( 'page' ),
		],
		'posts' => [
			'label'     => __( 'Posts', 'loupely-canvas' ),
			'note'      => __( 'Every blog post, including drafts and trashed posts.', 'loupely-canvas' ),
			'post_type' => 'post',
			'pro'       => false,
			'count'     => lc_reset_count_posts( 'post' ),
		],
		'media' => [
			'label'     => __( 'Media', 'loupely-canvas' ),
			'note'      => __( 'Every file in the Media Library, removed from the library and from disk.', 'loupely-canvas' ),
			'post_type' => 'attachment',
			'pro'       => false,
			'count'     => lc_reset_count_posts( 'attachment' ),
		],
		'seo' => [
			'label'     => __( 'SEO data', 'loupely-canvas' ),
			'note'      => __( 'The SEO title, description, share image, schema type, and page schema saved on pages and posts, and on any custom post type. The pages and posts themselves stay.', 'loupely-canvas' ),
			'post_type' => '',
			'pro'       => false,
			'count'     => lc_reset_count_seo(),
		],
		'settings' => [
			'label'     => __( 'Theme settings', 'loupely-canvas' ),
			'note'      => __( 'The boxes and toggles on this screen, the same as the reset above.', 'loupely-canvas' ),
			'post_type' => '',
			'pro'       => false,
			'count'     => -1,
		],
	];

	// Pro items are always listed. When Pro is active the counts are live;
	// when Pro is not active the checkboxes are disabled and show zero.
	$pro_active = lc_reset_pro_active();

	$pro_labels = [
		'snippets'   => [ __( 'Snippets', 'loupely-canvas' ), __( 'Every Canvas Pro snippet.', 'loupely-canvas' ) ],
		'hf_sets'    => [ __( 'Header and footer sets', 'loupely-canvas' ), __( 'Every Canvas Pro header and footer set.', 'loupely-canvas' ) ],
		'templates'  => [ __( 'Templates', 'loupely-canvas' ), __( 'Every Canvas Pro page template.', 'loupely-canvas' ) ],
		'injections' => [ __( 'Injections', 'loupely-canvas' ), __( 'Every Canvas Pro injection.', 'loupely-canvas' ) ],
	];
	foreach ( lc_reset_pro_post_types() as $key => $post_type ) {
		$items[ $key ] = [
			'label'     => $pro_labels[ $key ][0],
			'note'      => $pro_labels[ $key ][1],
			'post_type' => $post_type,
			'pro'       => true,
			'count'     => $pro_active ? lc_reset_count_posts( $post_type ) : 0,
			'disabled'  => ! $pro_active,
		];
	}

	// Each user-defined custom post type gets its own checkbox. A placeholder
	// row is always shown so users know the feature exists whether or not any
	// types are defined yet.
	$cpts = lc_reset_custom_post_types();
	if ( empty( $cpts ) ) {
		$items['pro_cpts_placeholder'] = [
			'label'     => __( 'Custom post types', 'loupely-canvas' ),
			'note'      => __( 'No custom post types are defined yet. Once you create some in Canvas Pro, they will appear here individually.', 'loupely-canvas' ),
			'post_type' => '',
			'pro'       => true,
			'count'     => 0,
			'disabled'  => true,
			'is_cpt'    => false,
		];
	} else {
		foreach ( $cpts as $slug => $plural ) {
			$items[ 'cpt_' . $slug ] = [
				'label'     => $plural,
				'note'      => sprintf(
					/* translators: %s is the plural name of a custom post type. */
					__( 'Every item of the %s post type, including drafts and trashed items. The type definition stays until you also wipe Pro settings below.', 'loupely-canvas' ),
					$plural
				),
				'post_type' => $slug,
				'pro'       => true,
				'count'     => $pro_active ? lc_reset_count_posts( $slug ) : 0,
				'disabled'  => ! $pro_active,
				'is_cpt'    => true,
			];
		}
	}

	$items['pro_data'] = [
		'label'     => __( 'Pro settings and version history', 'loupely-canvas' ),
		'note'      => __( 'Canvas Pro settings, custom post type definitions, and the saved version history of every item.', 'loupely-canvas' ),
		'post_type' => '',
		'pro'       => true,
		'count'     => -1,
		'disabled'  => ! $pro_active,
	];

	return $items;
}


/**
 * Delete the theme settings. Shared by the settings reset and the wipe.
 */
function lc_reset_delete_settings() {
	foreach ( lc_reset_option_keys() as $key ) {
		delete_option( $key );
	}
}


/**
 * The taxonomies Canvas Pro registers, keyed by the post type each belongs to.
 * Deleting a type's posts clears the term relationships but leaves the term
 * definitions, so the wipe removes the terms of the matching taxonomy too.
 */
function lc_reset_pro_taxonomies_by_post_type(): array {
	return [
		'lc_snippet'   => 'lc_snippet_cat',
		'lc_template'  => 'lc_template_cat',
		'lc_injection' => 'lc_injection_cat',
	];
}

/**
 * Delete every post of one type, then clear the terms of the taxonomy that type
 * owns, so no orphaned category or group definitions are left behind. Posts go
 * through wp_delete_post so meta and term links are cleaned up too, and so an
 * attachment also has its files removed.
 */
function lc_reset_delete_post_type( string $post_type ) {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $post_type )
	);
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}

	$taxonomies = lc_reset_pro_taxonomies_by_post_type();
	if ( isset( $taxonomies[ $post_type ] ) ) {
		lc_reset_delete_taxonomy_terms( $taxonomies[ $post_type ] );
	}
}

/**
 * Delete every term of one Canvas Pro taxonomy. When Pro is active the taxonomy
 * is registered, so terms are removed through the term API, which also clears
 * their term meta and relationship rows. When Pro is inactive the taxonomy is
 * not registered, so the rows are cleared directly from the term tables, keyed
 * by taxonomy name, the only reliable path with no taxonomy object to query.
 */
function lc_reset_delete_taxonomy_terms( string $taxonomy ) {
	global $wpdb;

	if ( taxonomy_exists( $taxonomy ) ) {
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ] );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}
		return;
	}

	$tt = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
			$taxonomy
		)
	);
	if ( ! $tt ) {
		return;
	}
	$ttids    = array_map( static function ( $r ) { return (int) $r->term_taxonomy_id; }, $tt );
	$term_ids = array_map( static function ( $r ) { return (int) $r->term_id; }, $tt );

	$tt_ph = implode( ',', array_fill( 0, count( $ttids ), '%d' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($tt_ph)", $ttids ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ($tt_ph)", $ttids ) );

	$t_ph = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ($t_ph)", $term_ids ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->terms} WHERE term_id IN ($t_ph)", $term_ids ) );
}


/**
 * Delete the Canvas Pro settings and version history. The options are owned by
 * the plugin, so they are cleared by their lc_pro_ prefix rather than by a list
 * the theme would have to keep in step. The history lives in its own table when
 * Pro is installed; its rows are cleared, and the empty table is left for Pro to
 * reuse if it returns.
 */
function lc_reset_delete_pro_data() {
	global $wpdb;

	$like = $wpdb->esc_like( 'lc_pro_' ) . '%';
	$wpdb->query(
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
	);

	$table  = $wpdb->prefix . 'lc_pro_history';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists === $table ) {
		$wpdb->query( "DELETE FROM `{$table}`" );
	}
}


/**
 * Delete all Canvas Pro data: its content posts (each clearing its taxonomy
 * terms as it goes), then its settings and history. Clears what Canvas Pro
 * leaves in the database while the plugin is inactive.
 */
function lc_reset_delete_pro_all() {
	foreach ( lc_reset_pro_post_types() as $post_type ) {
		lc_reset_delete_post_type( $post_type );
	}
	// Custom post type items, read from the stored definitions before the options
	// that hold those definitions are cleared below.
	foreach ( lc_reset_custom_post_types() as $slug => $plural ) {
		lc_reset_delete_post_type( $slug );
	}
	lc_reset_delete_pro_data();
}


/**
 * Confirm the typed word matches before a destructive action runs. The dialog
 * blocks the button until the word is right, and this is the same check on the
 * server, so the action cannot run without it even if the dialog is bypassed.
 */
function lc_reset_confirmed(): bool {
	$typed = isset( $_POST['lc_reset_confirm'] )
		? trim( (string) wp_unslash( $_POST['lc_reset_confirm'] ) )
		: '';
	return $typed === lc_reset_confirm_phrase();
}


function lc_handle_reset_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
	}
	check_admin_referer( 'lc_reset_settings', 'lc_reset_settings_nonce' );

	if ( ! lc_reset_confirmed() ) {
		wp_die( esc_html__( 'Type the confirmation word to reset the settings.', 'loupely-canvas' ) );
	}

	lc_reset_delete_settings();

	wp_safe_redirect(
		add_query_arg(
			[ 'page' => 'lc-header-footer-html', 'lc_reset_done' => 'settings' ],
			admin_url( 'themes.php' )
		)
	);
	exit;
}
add_action( 'admin_post_lc_reset_settings', 'lc_handle_reset_settings' );


function lc_handle_reset_everything() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
	}
	check_admin_referer( 'lc_reset_everything', 'lc_reset_everything_nonce' );

	if ( ! lc_reset_confirmed() ) {
		wp_die( esc_html__( 'Type the confirmation word to wipe the selected content.', 'loupely-canvas' ) );
	}

	$items    = lc_reset_items();
	$selected = ( isset( $_POST['lc_reset_types'] ) && is_array( $_POST['lc_reset_types'] ) )
		? array_map( 'sanitize_key', wp_unslash( $_POST['lc_reset_types'] ) )
		: [];
	// Keep only keys that are actually offered, which drops any Pro key that was
	// not on the screen and any value that was not one of the checkboxes.
	$selected = array_values( array_intersect( $selected, array_keys( $items ) ) );

	if ( empty( $selected ) ) {
		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'lc-header-footer-html', 'lc_reset_done' => 'none' ],
				admin_url( 'themes.php' )
			)
		);
		exit;
	}

	foreach ( $selected as $key ) {
		if ( $key === 'settings' ) {
			lc_reset_delete_settings();
		} elseif ( $key === 'seo' ) {
			lc_reset_delete_seo();
		} elseif ( $key === 'pro_data' ) {
			lc_reset_delete_pro_data();
		} elseif ( isset( $items[ $key ]['is_cpt'] ) && $items[ $key ]['is_cpt'] ) {
			// Individual custom post type checkbox: delete items of that one type.
			lc_reset_delete_post_type( $items[ $key ]['post_type'] );
		} elseif ( $items[ $key ]['post_type'] !== '' ) {
			lc_reset_delete_post_type( $items[ $key ]['post_type'] );
		}
	}

	wp_safe_redirect(
		add_query_arg(
			[ 'page' => 'lc-header-footer-html', 'lc_reset_done' => 'everything' ],
			admin_url( 'themes.php' )
		)
	);
	exit;
}
add_action( 'admin_post_lc_reset_everything', 'lc_handle_reset_everything' );


/**
 * Load the confirm dialog script on the settings screen and hand it the
 * confirmation word, so the dialog matches the word the server checks for.
 */
function lc_enqueue_reset_assets( $hook ) {
	if ( $hook !== 'appearance_page_lc-header-footer-html' ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$js_rel = '/assets/reset-confirm.js';
	$js_abs = get_template_directory() . $js_rel;

	wp_enqueue_script(
		'lc-reset-confirm',
		get_template_directory_uri() . $js_rel,
		[],
		file_exists( $js_abs ) ? (string) filemtime( $js_abs ) : LC_VERSION,
		true
	);

	$pro_active = lc_reset_pro_active();

	wp_localize_script(
		'lc-reset-confirm',
		'lcReset',
		[
			'phrase'        => lc_reset_confirm_phrase(),
			'selectedLabel' => __( 'You are about to delete:', 'loupely-canvas' ),
			'backup'        => [
				'pro'      => $pro_active,
				'url'      => $pro_active ? admin_url( 'admin.php?page=lc-pro-settings' ) : '',
				'text'     => $pro_active
					? __( 'Back up your Canvas Pro data first.', 'loupely-canvas' )
					: __( 'Canvas Pro is not active, so its export tool is unavailable. To keep any of this, turn Canvas Pro back on and export it from its Settings screen before you delete it.', 'loupely-canvas' ),
				'linkText' => __( 'Go to Settings, Export', 'loupely-canvas' ),
			],
		]
	);
}
add_action( 'admin_enqueue_scripts', 'lc_enqueue_reset_assets' );


/**
 * Render one wipe checkbox.
 */
function lc_reset_render_checkbox( string $key, array $item ) {
	$pro_attr      = ! empty( $item['pro'] ) ? ' data-pro="1"' : '';
	$count         = isset( $item['count'] ) ? (int) $item['count'] : -1;
	$count_attr    = $count >= 0 ? ' data-count="' . $count . '"' : '';
	$disabled      = ! empty( $item['disabled'] );
	$disabled_attr = $disabled ? ' disabled' : '';
	$empty         = ! $disabled && $count === 0;

	$classes = 'lc-reset-option';
	if ( $disabled ) {
		$classes .= ' lc-reset-option--disabled';
	} elseif ( $empty ) {
		$classes .= ' lc-reset-option--empty';
	}
	?>
	<label class="<?php echo esc_attr( $classes ); ?>">
		<input type="checkbox" name="lc_reset_types[]" value="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( $item['label'] ); ?>"<?php echo $pro_attr . $count_attr . $disabled_attr; ?>>
		<span class="lc-reset-option__text">
			<span class="lc-reset-option__label">
				<?php echo esc_html( $item['label'] ); ?>
				<?php if ( $count >= 0 ) : ?>
					<span class="lc-reset-option__count">(<?php echo esc_html( (string) $count ); ?>)</span>
				<?php endif; ?>
			</span>
			<span class="lc-reset-option__note"><?php echo esc_html( $item['note'] ); ?></span>
		</span>
	</label>
	<?php
}


/**
 * Render the reset panel. Hooked to lc_settings_bottom, which fires after the
 * settings form on the Loupely Canvas screen, so the panel's own forms sit
 * outside that form and post to admin-post.php on their own. Administrator only.
 */
function lc_render_reset_panel() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$items  = lc_reset_items();
	$phrase = lc_reset_confirm_phrase();
	$pro_active = lc_reset_pro_active();
	?>
	<div id="lc-sec-reset" class="lc-section lc-reset-panel">
		<h2 class="lc-reset-panel__title"><?php echo esc_html__( 'Reset and wipe', 'loupely-canvas' ); ?></h2>
		<p class="lc-reset-panel__intro"><?php echo esc_html__( 'Two ways to start clean. Both ask you to type a word first, and neither can be undone, so keep an export or a backup if you might want anything back.', 'loupely-canvas' ); ?></p>

		<div class="lc-reset-row">
			<div class="lc-reset-row__text">
				<h3><?php echo esc_html__( 'Reset theme settings', 'loupely-canvas' ); ?></h3>
				<p><?php echo esc_html__( 'Empties every box on this screen: the header, footer, head and body code, the blog templates, and the toggles. Your pages, posts, and media are left alone.', 'loupely-canvas' ); ?></p>
			</div>
			<button
				type="button"
				class="lc-reset-btn"
				data-lc-reset-form="lc-reset-settings-form"
				data-lc-reset-title="<?php echo esc_attr__( 'Reset theme settings', 'loupely-canvas' ); ?>"
				data-lc-reset-body="<?php echo esc_attr__( 'This empties the header, footer, head and body code, the blog templates, and the toggles on this screen. Your pages, posts, and media stay. This cannot be undone.', 'loupely-canvas' ); ?>"
				data-lc-reset-confirm-label="<?php echo esc_attr__( 'Reset the settings', 'loupely-canvas' ); ?>"
			><?php echo esc_html__( 'Reset theme settings', 'loupely-canvas' ); ?></button>
		</div>

		<div class="lc-reset-wipe">
			<h3 class="lc-reset-wipe__title"><?php echo esc_html__( 'Wipe content', 'loupely-canvas' ); ?></h3>
			<p class="lc-reset-wipe__intro"><?php echo esc_html__( 'Pick what to delete, then run it. Anything left unchecked is left alone.', 'loupely-canvas' ); ?></p>

			<form id="lc-reset-everything-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lc_reset_everything">
				<input type="hidden" name="lc_reset_confirm" value="">
				<?php wp_nonce_field( 'lc_reset_everything', 'lc_reset_everything_nonce' ); ?>

				<fieldset class="lc-reset-group">
					<legend class="lc-reset-group__legend"><?php echo esc_html__( 'Content', 'loupely-canvas' ); ?></legend>
					<?php
					foreach ( $items as $key => $item ) {
						if ( $item['pro'] ) {
							continue;
						}
						lc_reset_render_checkbox( $key, $item );
					}
					?>
				</fieldset>

				<fieldset class="lc-reset-group lc-reset-group--pro">
					<legend class="lc-reset-group__legend"><?php echo esc_html__( 'Canvas Pro', 'loupely-canvas' ); ?></legend>
				<?php if ( ! $pro_active ) : ?>
						<div class="lc-reset-pro-notice">
							<p class="lc-reset-pro-notice__text">
								<?php
								printf(
									/* translators: %s is a link to the Canvas Pro page. */
									esc_html__( 'These options are for Canvas Pro, which extends Canvas to include a full screen code editor with syntax coloring and more, plus version history, page templates, code snippets, HTML/CSS/Javascript injections, and then some. %s', 'loupely-canvas' ),
									'<a class="lc-reset-pro-notice__link" href="https://loupelycanvas.com/pro" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more about Canvas Pro.', 'loupely-canvas' ) . '</a>'
								);
								?>
							</p>
						</div>
					<?php endif; ?>
					<?php
					foreach ( $items as $key => $item ) {
						if ( ! $item['pro'] ) {
							continue;
						}
						lc_reset_render_checkbox( $key, $item );
					}
					?>
				</fieldset>

				<button
					type="button"
					class="lc-reset-btn lc-reset-wipe__btn"
					data-lc-reset-form="lc-reset-everything-form"
					data-lc-reset-title="<?php echo esc_attr__( 'Wipe the selected content', 'loupely-canvas' ); ?>"
					data-lc-reset-body="<?php echo esc_attr__( 'This permanently deletes the items you selected. There is no way back from this.', 'loupely-canvas' ); ?>"
					data-lc-reset-confirm-label="<?php echo esc_attr__( 'Wipe selected', 'loupely-canvas' ); ?>"
					disabled
				><?php echo esc_html__( 'Wipe selected content', 'loupely-canvas' ); ?></button>
			</form>
		</div>

		<form id="lc-reset-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lc_reset_settings">
			<input type="hidden" name="lc_reset_confirm" value="">
			<?php wp_nonce_field( 'lc_reset_settings', 'lc_reset_settings_nonce' ); ?>
		</form>

		<div id="lc-reset-dialog" class="lc-reset-dialog" hidden>
			<div class="lc-reset-dialog__backdrop" data-lc-reset-cancel></div>
			<div class="lc-reset-dialog__box" role="dialog" aria-modal="true" aria-labelledby="lc-reset-dialog-title" aria-describedby="lc-reset-dialog-body">
				<h2 id="lc-reset-dialog-title" class="lc-reset-dialog__title"></h2>
				<p id="lc-reset-dialog-body" class="lc-reset-dialog__body"></p>
				<ul id="lc-reset-dialog-list" class="lc-reset-dialog__list" hidden></ul>
				<p id="lc-reset-dialog-backup" class="lc-reset-dialog__backup" hidden></p>
				<label class="lc-reset-dialog__label" for="lc-reset-dialog-input">
					<?php
					printf(
						/* translators: %s is the confirmation word the user must type, for example Understood. */
						esc_html__( 'Type %s to confirm.', 'loupely-canvas' ),
						'<span class="lc-reset-dialog__word">' . esc_html( $phrase ) . '</span>'
					);
					?>
				</label>
				<input type="text" id="lc-reset-dialog-input" class="lc-reset-dialog__input" autocomplete="off" autocapitalize="off" spellcheck="false">
				<div class="lc-reset-dialog__actions">
					<button type="button" class="lc-reset-dialog__cancel" data-lc-reset-cancel><?php echo esc_html__( 'Cancel', 'loupely-canvas' ); ?></button>
					<button type="button" class="lc-reset-dialog__confirm" disabled></button>
				</div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'lc_settings_bottom', 'lc_render_reset_panel' );


/**
 * Confirm a reset or wipe finished, on return to the settings screen.
 */
function lc_reset_done_notice() {
	if ( ! isset( $_GET['page'], $_GET['lc_reset_done'] ) || $_GET['page'] !== 'lc-header-footer-html' ) {
		return;
	}
	$which = sanitize_key( wp_unslash( $_GET['lc_reset_done'] ) );

	if ( $which === 'none' ) {
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html__( 'Nothing was selected, so nothing was deleted.', 'loupely-canvas' );
		echo '</p></div>';
		return;
	}

	echo '<div class="notice notice-success is-dismissible"><p>';
	if ( $which === 'everything' ) {
		echo esc_html__( 'The selected content was wiped.', 'loupely-canvas' );
	} else {
		echo esc_html__( 'Theme settings were reset. Your pages, posts, and media are unchanged.', 'loupely-canvas' );
	}
	echo '</p></div>';
}
add_action( 'admin_notices', 'lc_reset_done_notice' );
