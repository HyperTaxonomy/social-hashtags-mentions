<?php
/**
 * Uninstall cleanup for Social Hashtags & Mentions.
 *
 * Removes the plugin's settings option and its cached blogosphere pools (sh_bg_pool_*
 * transients). When "Delete data on uninstall" is on, also removes mention handles
 * (_tc_link_social_*), local author links (_tc_link_local_site) and user links
 * (_sh_user_id). On multisite every site is cleaned. The hashtag/mention terms
 * themselves are always left in place — they are content, and removing the plugin
 * should not delete a site's taxonomy data. The filter-registered TermConnect link types disappear on their
 * own once this plugin is inactive (they are never persisted).
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up the current site.
 */
function sh_uninstall_cleanup(): void {
    global $wpdb;

    $settings = get_option( 'sh_settings', array() );

    delete_option( 'sh_settings' );

    // Cached "around the blogosphere" pools.
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\_transient\_sh\_bg\_%'
            OR option_name LIKE '\_transient\_timeout\_sh\_bg\_%'"
    );

    // Handles and user links are kept unless the admin opted in.
    if ( empty( $settings['delete_on_uninstall'] ) ) {
        return;
    }

    // Remove handle and local author meta written for mention terms.
    $rows = $wpdb->get_col(
        "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key LIKE '\_tc\_link\_social\_%' OR meta_key = '_tc_link_local_site'"
    );

    $wpdb->query(
        "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\_tc\_link\_social\_%' OR meta_key IN ( '_tc_link_local_site', '_sh_user_id' )"
    );

    // Refresh TermConnect's URI index/cache for affected terms when TC is present.
    if ( function_exists( 'tc_flush_term_link_cache' ) && function_exists( 'tc_rebuild_uri_index' ) ) {
        foreach ( array_unique( array_map( 'intval', (array) $rows ) ) as $term_id ) {
            tc_flush_term_link_cache( $term_id );
            tc_rebuild_uri_index( $term_id );
        }
    }
}

if ( is_multisite() ) {
    foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $site_id ) {
        switch_to_blog( $site_id );
        sh_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    sh_uninstall_cleanup();
}
