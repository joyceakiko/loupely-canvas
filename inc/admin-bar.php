<?php
/**
 * Loupely Canvas - admin bar
 *
 * Adds a "New Canvas page" link to the WordPress toolbar, sitting just left of
 * the edit link a logged-in editor sees on a page. In the free theme it opens
 * the standard new-page editor. Canvas Pro reroutes it to the Canvas editor
 * through the lc_new_canvas_page_url filter, so the same toolbar link opens the
 * page in whichever editor is in charge.
 *
 * The node is added at a priority that runs before the core edit link (80) and
 * the Pro edit link (81), so it renders to the left of either one. It shows for
 * anyone who can create pages, on the front end and in wp-admin alike, since
 * starting a new page is useful from either place.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * The destination for the New Canvas page link. The standard new-page editor by
 * default; Canvas Pro filters this to its own new-page route.
 */
function lc_new_canvas_page_url(): string {
	$url = admin_url( 'post-new.php?post_type=page' );
	return (string) apply_filters( 'lc_new_canvas_page_url', $url );
}

/**
 * Add the New Canvas page node to the toolbar.
 */
function lc_admin_bar_new_canvas_page( $wp_admin_bar ) {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$wp_admin_bar->add_node( [
		'id'     => 'lc-new-canvas-page',
		'title'  => __( 'New Canvas page', 'loupely-canvas' ),
		'href'   => esc_url( lc_new_canvas_page_url() ),
		'parent' => false,
		'meta'   => [ 'title' => __( 'Create a new Loupely Canvas page', 'loupely-canvas' ) ],
	] );
}
add_action( 'admin_bar_menu', 'lc_admin_bar_new_canvas_page', 79 );
