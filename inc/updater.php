<?php
/**
 * Loupely Canvas - GitHub update checker (full version only)
 *
 * The full theme is distributed through GitHub and loupelycanvas.com, not the
 * WordPress.org directory, so WordPress will not offer updates for it on its
 * own. This file fills that gap: it asks the GitHub Releases API for the
 * latest release, compares it to the installed version, and hands WordPress a
 * download package so the normal one click update works in wp-admin.
 *
 * SETUP: change the two constants below to your GitHub account and repository.
 * Then publish each new version as a GitHub release whose tag is the version
 * number (for example 2.2.0 or v2.2.0). Attach the built zip (the one whose
 * inner folder is named loupely-canvas) as a release asset. The updater will
 * prefer that asset; if none is attached it falls back to the source zip and
 * renames the folder for you.
 *
 * The Lite edition on WordPress.org uses a different folder slug, so the two
 * never compete for updates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// CHANGE THESE TWO LINES to your GitHub owner and repository name.
if ( ! defined( 'LC_GH_OWNER' ) ) define( 'LC_GH_OWNER', 'joyceakiko' );
if ( ! defined( 'LC_GH_REPO' ) )  define( 'LC_GH_REPO', 'loupely-canvas' );


/**
 * Fetch the latest release from GitHub, cached for 12 hours so we stay well
 * under the API rate limit and do not slow down wp-admin.
 */
function lc_github_latest_release() {
    $cache_key = 'lc_gh_latest_release';
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        return is_array( $cached ) ? $cached : [];
    }

    $url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', LC_GH_OWNER, LC_GH_REPO );
    $res = wp_remote_get( $url, [
        'timeout' => 10,
        'headers' => [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'Loupely-Canvas-Theme-Updater',
        ],
    ] );

    if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
        // Cache the miss briefly so a flaky network does not hammer the API.
        set_transient( $cache_key, [], HOUR_IN_SECONDS );
        return [];
    }

    $data = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( ! is_array( $data ) ) {
        $data = [];
    }
    set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
    return $data;
}


function lc_github_release_version( array $release ): string {
    return empty( $release['tag_name'] ) ? '' : ltrim( (string) $release['tag_name'], 'vV' );
}


/**
 * Prefer a .zip release asset (its inner folder is named correctly), and fall
 * back to the GitHub source zipball if no asset is attached.
 */
function lc_github_package_url( array $release ): string {
    if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
        foreach ( $release['assets'] as $asset ) {
            if ( ! empty( $asset['browser_download_url'] ) && substr( (string) $asset['name'], -4 ) === '.zip' ) {
                return $asset['browser_download_url'];
            }
        }
    }
    return ! empty( $release['zipball_url'] ) ? $release['zipball_url'] : '';
}


/**
 * Tell WordPress an update is available when GitHub has a newer version.
 */
function lc_check_theme_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }

    $slug      = get_template();
    $installed = wp_get_theme( $slug )->get( 'Version' );
    $release   = lc_github_latest_release();
    $remote    = lc_github_release_version( $release );

    if ( $remote === '' ) {
        return $transient;
    }

    if ( version_compare( $remote, $installed, '>' ) ) {
        $package = lc_github_package_url( $release );
        if ( $package === '' ) {
            return $transient;
        }
        $transient->response[ $slug ] = [
            'theme'       => $slug,
            'new_version' => $remote,
            'url'         => ! empty( $release['html_url'] ) ? $release['html_url'] : '',
            'package'     => $package,
        ];
    } else {
        unset( $transient->response[ $slug ] );
        $transient->no_update[ $slug ] = [
            'theme'       => $slug,
            'new_version' => $installed,
            'url'         => '',
            'package'     => '',
        ];
    }

    return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'lc_check_theme_update' );


/**
 * When the package is the GitHub source zipball, its top folder is named
 * owner-repo-hash. Rename it to the theme slug so WordPress installs it into
 * the right directory. Skipped automatically when a correctly named zip asset
 * is used, and only ever touches this theme's own update.
 */
function lc_fix_update_source( $source, $remote_source, $upgrader, $args = [] ) {
    if ( empty( $args['theme'] ) || $args['theme'] !== get_template() ) {
        return $source;
    }

    global $wp_filesystem;
    $slug    = get_template();
    $desired = trailingslashit( $remote_source ) . $slug;

    if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
        return $source; // already correct
    }
    if ( $wp_filesystem && $wp_filesystem->move( $source, $desired ) ) {
        return trailingslashit( $desired );
    }
    return $source;
}
add_filter( 'upgrader_source_selection', 'lc_fix_update_source', 10, 4 );
