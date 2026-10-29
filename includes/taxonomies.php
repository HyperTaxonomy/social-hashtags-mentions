<?php
/**
 * Taxonomy registration: hashtag (primary) and mention (secondary).
 *
 * Both are flat (non-hierarchical), public, REST-enabled, and attached to the
 * post types returned by sh_token_post_types(). Term assignments are managed
 * automatically from inline tokens (see term-sync.php), so the default meta box
 * is suppressed.
 *
 * @package SocialHashtagsMentions
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
function sh_token_post_types(): array {
    /**
     * Filters the post types that support inline hashtags and mentions.
     *
     * @since 1.0.0
     * @param string[] $post_types Default: post.
     */
    return apply_filters( 'sh_token_post_types', array( 'post' ) );
}

/**
 * Registers the hashtag and mention taxonomies.
 *
 * @since 1.0.0
 */
function sh_register_taxonomies(): void {
    $post_types = sh_token_post_types();

    register_taxonomy(
        'hashtag',
        $post_types,
        array(
            'labels'            => array(
                'name'          => __( 'Hashtags', 'social-hashtags-mentions' ),
                'singular_name' => __( 'Hashtag', 'social-hashtags-mentions' ),
                'menu_name'     => __( 'Hashtags', 'social-hashtags-mentions' ),
                'search_items'  => __( 'Search Hashtags', 'social-hashtags-mentions' ),
                'all_items'     => __( 'All Hashtags', 'social-hashtags-mentions' ),
                'edit_item'     => __( 'Edit Hashtag', 'social-hashtags-mentions' ),
                'view_item'     => __( 'View Hashtag', 'social-hashtags-mentions' ),
                'not_found'     => __( 'No hashtags found.', 'social-hashtags-mentions' ),
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
                'name'          => __( 'Mentions', 'social-hashtags-mentions' ),
                'singular_name' => __( 'Mention', 'social-hashtags-mentions' ),
                'menu_name'     => __( 'Mentions', 'social-hashtags-mentions' ),
                'search_items'  => __( 'Search Mentions', 'social-hashtags-mentions' ),
                'all_items'     => __( 'All Mentions', 'social-hashtags-mentions' ),
                'edit_item'     => __( 'Edit Mention', 'social-hashtags-mentions' ),
                'view_item'     => __( 'View Mention', 'social-hashtags-mentions' ),
                'not_found'     => __( 'No mentions found.', 'social-hashtags-mentions' ),
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
