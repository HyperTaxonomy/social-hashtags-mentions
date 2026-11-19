<?php
/**
 * Uninstall cleanup for TermConnect Social.
 *
 * Removes the plugin's settings option and its cached blogosphere pools (tcsoc_bg_pool_*
 * transients). When "Delete data on uninstall" is on, also removes mention handles
 * (_tconn_link_social_*), local author links (_tconn_link_local_site) and user links
 * (_tcsoc_user_id). On multisite every site is cleaned. The hashtag/mention terms
 * themselves are always left in place — they are content, and removing the plugin
 * should not delete a site's taxonomy data. The filter-registered TermConnect link types disappear on their
 * own once this plugin is inactive (they are never persisted).
 *
 * @package TermConnectSocial
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up the current site.
 */
function tcsoc_uninstall_cleanup(): void {
    global $wpdb;

    $settings = get_option( 'tcsoc_settings', array() );

    delete_option( 'tcsoc_settings' );

    // Cached "around the blogosphere" pools.
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\_transient\_tcsoc\_bg\_%'
            OR option_name LIKE '\_transient\_timeout\_tcsoc\_bg\_%'"
    );

    // Handles and user links are kept unless the admin opted in.
    if ( empty( $settings['delete_on_uninstall'] ) ) {
        return;
    }

    // Remove handle and local author meta written for mention terms.
    $rows = $wpdb->get_col(
        "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key LIKE '\_tconn\_link\_social\_%' OR meta_key = '_tconn_link_local_site'"
    );

    $wpdb->query(
        "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\_tconn\_link\_social\_%' OR meta_key IN ( '_tconn_link_local_site', '_tcsoc_user_id' )"
    );

    // Refresh TermConnect's URI index/cache for affected terms when TC is present.
    if ( function_exists( 'tconn_flush_term_link_cache' ) && function_exists( 'tconn_rebuild_uri_index' ) ) {
        foreach ( array_unique( array_map( 'intval', (array) $rows ) ) as $term_id ) {
            tconn_flush_term_link_cache( $term_id );
            tconn_rebuild_uri_index( $term_id );
        }
    }
}

if ( is_multisite() ) {
    foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $tcsoc_site_id ) {
        switch_to_blog( $tcsoc_site_id );
        tcsoc_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    tcsoc_uninstall_cleanup();
}
