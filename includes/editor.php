<?php
/**
 * Block editor integration: enqueues the inline-token script and localizes the
 * data it needs (REST root + nonce, ordered enabled networks).
 *
 * The script is hand-authored against the wp.* globals (no build step), the same
 * approach HyperTaxonomy uses for its editor script.
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'enqueue_block_editor_assets', 'sh_enqueue_editor_assets' );

/**
 * Enqueues and localizes the editor script for supported post types.
 *
 * @since 1.0.0
 */
function sh_enqueue_editor_assets(): void {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && $screen->post_type && ! in_array( $screen->post_type, sh_token_post_types(), true ) ) {
        return;
    }

    wp_enqueue_script(
        'sh-editor',
        SH_PLUGIN_URL . 'assets/js/sh-editor.js',
        array(
            'wp-rich-text',
            'wp-element',
            'wp-components',
            'wp-block-editor',
            'wp-data',
            'wp-i18n',
            'wp-api-fetch',
            'wp-hooks',
            'wp-url',
        ),
        SH_VERSION,
        true
    );

    wp_enqueue_style(
        'social-hashtags-mentions',
        SH_PLUGIN_URL . 'assets/css/social-hashtags-mentions.css',
        array( 'dashicons' ),
        SH_VERSION
    );

    wp_localize_script( 'sh-editor', 'SHEditor', array(
        'rest'     => esc_url_raw( rest_url( 'social-hashtags/v1/' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'networks' => sh_editor_networks(),
    ) );

    // Load the editor script's translations from the plugin's bundled languages/
    // directory. The script is not a block, so its bundled
    // <domain>-<locale>-sh-editor.json file must be pointed at explicitly.
    wp_set_script_translations( 'sh-editor', 'social-hashtags-mentions', SH_PLUGIN_DIR . 'languages' );
}

/**
 * Returns the ordered enabled networks for the editor.
 *
 * @since 1.0.0
 * @return array[]
 */
function sh_editor_networks(): array {
    $out = array();
    foreach ( sh_get_enabled_networks() as $slug ) {
        $def = sh_get_network( $slug );
        if ( ! $def ) {
            continue;
        }
        $out[] = array(
            'slug'     => $slug,
            'label'    => (string) $def['label'],
            'icon'     => (string) ( $def['icon'] ?? 'dashicons-admin-links' ),
            // Brand SVG + color so the editor shows the same tinted mark as the front end.
            'svg'      => sh_network_svg( $slug ),
            'color'    => sh_network_color( $slug ),
            'supports' => array_values( (array) $def['supports'] ),
            'internal' => ! empty( $def['internal'] ),
            'instance' => ! empty( $def['instance'] ),
        );
    }
    return $out;
}
