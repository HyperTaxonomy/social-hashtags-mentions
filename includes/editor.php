<?php
/**
 * Block editor integration: enqueues the inline-token script and localizes the
 * data it needs (REST root + nonce, ordered enabled networks).
 *
 * The script is hand-authored against the wp.* globals (no build step), the same
 * approach HyperTaxonomy uses for its editor script.
 *
 * @package TermConnectSocial
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'enqueue_block_editor_assets', 'tcs_enqueue_editor_assets' );

/**
 * Enqueues and localizes the editor script for supported post types.
 *
 * @since 1.0.0
 */
function tcs_enqueue_editor_assets(): void {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && $screen->post_type && ! in_array( $screen->post_type, tcs_token_post_types(), true ) ) {
        return;
    }

    wp_enqueue_script(
        'tcs-editor',
        TCS_PLUGIN_URL . 'assets/js/tcs-editor.js',
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
        TCS_VERSION,
        true
    );

    wp_enqueue_style(
        'termconnect-social',
        TCS_PLUGIN_URL . 'assets/css/termconnect-social.css',
        array( 'dashicons' ),
        TCS_VERSION
    );

    wp_localize_script( 'tcs-editor', 'TCSEditor', array(
        'rest'     => esc_url_raw( rest_url( 'termconnect-social/v1/' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'networks' => tcs_editor_networks(),
    ) );

    // Load the editor script's translations from the plugin's bundled languages/
    // directory. The script is not a block, so its bundled
    // <domain>-<locale>-tcs-editor.json file must be pointed at explicitly.
    wp_set_script_translations( 'tcs-editor', 'termconnect-social', TCS_PLUGIN_DIR . 'languages' );
}

/**
 * Returns the ordered enabled networks for the editor.
 *
 * @since 1.0.0
 * @return array[]
 */
function tcs_editor_networks(): array {
    $out = array();
    foreach ( tcs_get_enabled_networks() as $slug ) {
        $def = tcs_get_network( $slug );
        if ( ! $def ) {
            continue;
        }
        $out[] = array(
            'slug'     => $slug,
            'label'    => (string) $def['label'],
            'icon'     => (string) ( $def['icon'] ?? 'dashicons-admin-links' ),
            // Brand SVG + color so the editor shows the same tinted mark as the front end.
            'svg'      => tcs_network_svg( $slug ),
            'color'    => tcs_network_color( $slug ),
            'supports' => array_values( (array) $def['supports'] ),
            'internal' => ! empty( $def['internal'] ),
            'instance' => ! empty( $def['instance'] ),
        );
    }
    return $out;
}
