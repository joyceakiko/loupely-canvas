<?php
/**
 * Loupely Canvas - Lite migration.
 *
 * Carries a site over from Loupely Canvas Lite when the full theme is
 * activated. Lite stores its settings under its own lclite_ keys, which the
 * full theme does not read, so without this step a Lite user's header, footer,
 * and per page choices would read blank after switching. The carry-over runs
 * once, on activation, and only fills a destination that is still empty, so it
 * never overwrites a setting already made in the full theme.
 *
 * Lite has no head or body code, sets, or injections, so only the global header
 * and footer and three per page options carry over: hide title, full width
 * (which the full theme calls unwrap), and body class.
 *
 * @package Loupely_Canvas
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Copy Loupely Canvas Lite settings into the full theme's keys, once.
 */
function lc_migrate_from_lite(): void {
	if ( get_option( 'lc_lite_migrated' ) ) {
		return;
	}
	// Mark done first, so a half finished run never repeats on the next load.
	update_option( 'lc_lite_migrated', '1' );

	// Global header and footer: Lite option into the full theme option, only
	// where the full theme's own option is still empty.
	$globals = [
		'lclite_header_html' => 'lc_header_html',
		'lclite_footer_html' => 'lc_footer_html',
	];
	foreach ( $globals as $from => $to ) {
		$value = (string) get_option( $from, '' );
		if ( $value !== '' && (string) get_option( $to, '' ) === '' ) {
			update_option( $to, $value );
		}
	}

	// Per page settings, only on pages that carry a Lite key.
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
}
add_action( 'after_switch_theme', 'lc_migrate_from_lite' );
