<?php
/**
 * Loupely Canvas - Lite migration.
 *
 * Offers to bring a site over from Loupely Canvas Lite. Lite saves its global
 * header and footer as Customizer theme mods on the Lite theme, and its per
 * page choices as post meta. The full theme keeps its header and footer in its
 * own options, so right after a switch the full theme cannot see what Lite
 * saved and the boxes read empty. When the full theme finds a header or footer
 * that Lite saved, it shows a notice on the Themes screen and the Loupely
 * Canvas settings screen with an Import button. Importing fills only boxes that
 * are still empty, so nothing already set in the full theme is overwritten, and
 * it carries the per page settings Lite stores: hide title, full width (which
 * the full theme calls unwrap), and body class.
 *
 * The offer reads Lite's saved data while the Lite theme is still installed, so
 * import before you remove Lite.
 *
 * @package Loupely_Canvas
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The theme mods Loupely Canvas Lite saved, read straight from its own theme
 * mod store so they are reachable while the full theme is active. Empty when
 * Lite is not installed.
 */
function lc_lite_get_mods(): array {
	foreach ( wp_get_themes() as $stylesheet => $theme ) {
		if ( $theme->get( 'Name' ) === 'Loupely Canvas Lite' || $theme->get( 'TextDomain' ) === 'loupely-canvas-lite' ) {
			$mods = get_option( 'theme_mods_' . $stylesheet );
			return is_array( $mods ) ? $mods : [];
		}
	}
	return [];
}

/**
 * Whether Lite has a saved header or footer to import, and the offer has not
 * already been taken or dismissed.
 */
function lc_lite_import_available(): bool {
	if ( get_option( 'lc_lite_import_done' ) || get_option( 'lc_lite_import_dismissed' ) ) {
		return false;
	}
	$mods   = lc_lite_get_mods();
	$header = (string) ( $mods['lclite_header_html'] ?? '' );
	$footer = (string) ( $mods['lclite_footer_html'] ?? '' );
	return $header !== '' || $footer !== '';
}

/**
 * Show the import offer on the Themes screen and the Loupely Canvas settings
 * screen. The screen check runs first, so the theme lookup only happens there.
 */
function lc_lite_import_notice(): void {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'themes', 'appearance_page_lc-header-footer-html' ], true ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_theme_options' ) || ! lc_lite_import_available() ) {
		return;
	}
	$import = wp_nonce_url( admin_url( 'admin-post.php?action=lc_lite_import' ), 'lc_lite_import' );
	$skip   = wp_nonce_url( admin_url( 'admin-post.php?action=lc_lite_dismiss' ), 'lc_lite_dismiss' );
	?>
	<div class="notice lc-lite-notice" style="border-left-color:#5C7F68;background:#5C7F68;color:#FFFFFF;padding:14px 16px;">
		<p style="color:#FFFFFF;font-size:14px;margin:0 0 12px;">We noticed you have settings from Loupely Canvas Lite. Would you like to bring your header, footer, and per page settings into the full theme?</p>
		<p style="margin:0;">
			<a href="<?php echo esc_url( $import ); ?>" class="button" style="background:#FFFFFF;border-color:#FFFFFF;color:#1A2420;">Import my Lite settings</a>
			<a href="<?php echo esc_url( $skip ); ?>" style="color:#FFFFFF;text-decoration:underline;margin-left:12px;">No thanks</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'lc_lite_import_notice' );

/**
 * Show a one time confirmation right after an import runs.
 */
function lc_lite_imported_notice(): void {
	if ( empty( $_GET['lc_lite_imported'] ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php echo esc_html__( 'Your Loupely Canvas Lite header, footer, and per page settings are now in the full theme.', 'loupely-canvas' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'lc_lite_imported_notice' );

/**
 * Copy Lite's global header and footer (theme mods) and per page settings (post
 * meta) into the full theme's keys, filling only destinations still empty.
 */
function lc_lite_run_import(): void {
	$mods    = lc_lite_get_mods();
	$globals = [
		'lclite_header_html' => 'lc_header_html',
		'lclite_footer_html' => 'lc_footer_html',
	];
	foreach ( $globals as $from => $to ) {
		$value = (string) ( $mods[ $from ] ?? '' );
		if ( $value !== '' && (string) get_option( $to, '' ) === '' ) {
			update_option( $to, $value );
		}
	}

	$meta_map = [
		'_lclite_hide_title' => '_lc_hide_title',
		'_lclite_full_width' => '_lc_unwrap',
		'_lclite_body_class' => '_lc_body_class',
	];
	$post_ids = get_posts( [
		'post_type'   => 'any',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [
			'relation' => 'OR',
			[ 'key' => '_lclite_hide_title', 'compare' => 'EXISTS' ],
			[ 'key' => '_lclite_full_width', 'compare' => 'EXISTS' ],
			[ 'key' => '_lclite_body_class', 'compare' => 'EXISTS' ],
		],
	] );
	foreach ( $post_ids as $post_id ) {
		foreach ( $meta_map as $from => $to ) {
			$value = (string) get_post_meta( $post_id, $from, true );
			if ( $value !== '' && (string) get_post_meta( $post_id, $to, true ) === '' ) {
				update_post_meta( $post_id, $to, $value );
			}
		}
	}

	update_option( 'lc_lite_import_done', '1' );
}

/** Handle the Import button. */
function lc_lite_handle_import(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
	}
	check_admin_referer( 'lc_lite_import' );
	lc_lite_run_import();
	wp_safe_redirect( add_query_arg( 'lc_lite_imported', '1', wp_get_referer() ?: admin_url( 'themes.php' ) ) );
	exit;
}
add_action( 'admin_post_lc_lite_import', 'lc_lite_handle_import' );

/** Handle the No thanks button. The offer goes away for good. */
function lc_lite_handle_dismiss(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'loupely-canvas' ) );
	}
	check_admin_referer( 'lc_lite_dismiss' );
	update_option( 'lc_lite_import_dismissed', '1' );
	wp_safe_redirect( wp_get_referer() ?: admin_url( 'themes.php' ) );
	exit;
}
add_action( 'admin_post_lc_lite_dismiss', 'lc_lite_handle_dismiss' );
