<?php
/**
 * Taxonomy registration: hashtag (primary) and mention (secondary).
 *
 * Both are flat (non-hierarchical), public, REST-enabled, and attached to the
 * post types returned by tcsoc_token_post_types(). Term assignments are managed
 * automatically from inline tokens (see term-sync.php), so the default meta box
 * is suppressed.
 *
 * @package TermConnectSocial
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Post types that may contain inline hashtags/mentions.
 *
 * @since 1.0.0
 * @return string[]
 */
function tcsoc_token_post_types(): array {
    /**
     * Filters the post types that support inline hashtags and mentions.
     *
     * @since 1.0.0
     * @param string[] $post_types Default: post.
     */
    return apply_filters( 'tcsoc_token_post_types', array( 'post' ) );
}

/**
 * Registers the hashtag and mention taxonomies.
 *
 * @since 1.0.0
 */
function tcsoc_register_taxonomies(): void {
    $post_types = tcsoc_token_post_types();

    register_taxonomy(
        'hashtag',
        $post_types,
        array(
            'labels'            => array(
                'name'          => __( 'Hashtags', 'termconnect-social' ),
                'singular_name' => __( 'Hashtag', 'termconnect-social' ),
                'menu_name'     => __( 'Hashtags', 'termconnect-social' ),
                'search_items'  => __( 'Search Hashtags', 'termconnect-social' ),
                'all_items'     => __( 'All Hashtags', 'termconnect-social' ),
                'edit_item'     => __( 'Edit Hashtag', 'termconnect-social' ),
                'view_item'     => __( 'View Hashtag', 'termconnect-social' ),
                'not_found'     => __( 'No hashtags found.', 'termconnect-social' ),
            ),
            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            // Assignments come from inline tokens; hide the classic/Gutenberg picker.
            'meta_box_cb'       => false,
            'rewrite'           => array( 'slug' => 'hashtag' ),
        )
    );

    register_taxonomy(
        'mention',
        $post_types,
        array(
            'labels'            => array(
                'name'          => __( 'Mentions', 'termconnect-social' ),
                'singular_name' => __( 'Mention', 'termconnect-social' ),
                'menu_name'     => __( 'Mentions', 'termconnect-social' ),
                'search_items'  => __( 'Search Mentions', 'termconnect-social' ),
                'all_items'     => __( 'All Mentions', 'termconnect-social' ),
                'edit_item'     => __( 'Edit Mention', 'termconnect-social' ),
                'view_item'     => __( 'View Mention', 'termconnect-social' ),
                'not_found'     => __( 'No mentions found.', 'termconnect-social' ),
            ),
            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'meta_box_cb'       => false,
            'rewrite'           => array( 'slug' => 'mention' ),
        )
    );
}
