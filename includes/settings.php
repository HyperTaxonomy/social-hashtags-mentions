<?php
/**
 * Settings storage.
 *
 * Networks are stored as an ordered map ( slug => enabled ) in the sh_settings
 * option. The key order is the display order, controlled by drag-and-drop in the
 * admin screen (admin.php). New networks added to the registry in a later version
 * appear at the end, disabled, until the admin enables them.
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const SH_OPTION = 'sh_settings';

/**
 * Returns the full settings array, with the network map normalized against the
 * current registry (back-filling new networks at the end, dropping unknown ones).
 *
 * @since 1.0.0
 * @return array{networks: array<string,bool>}
 */
function sh_get_settings(): array {
    // Keyed by blog ID, same reasoning as sh_get_network_registry(): a multisite
    // caller that switch_to_blog()s between sites within one request must never be
    // served a different site's cached settings.
    static $cache = array();
    $blog_id = get_current_blog_id();
    if ( isset( $cache[ $blog_id ] ) ) {
        return $cache[ $blog_id ];
    }

    $stored   = get_option( SH_OPTION, array() );
    $stored   = is_array( $stored ) ? $stored : array();
    $networks = isset( $stored['networks'] ) && is_array( $stored['networks'] ) ? $stored['networks'] : array();

    $registry = sh_get_network_registry();

    // Keep stored order for known networks, then append any new registry networks.
    $normalized = array();
    foreach ( $networks as $slug => $enabled ) {
        if ( isset( $registry[ $slug ] ) ) {
            $normalized[ $slug ] = (bool) $enabled;
        }
    }
    foreach ( $registry as $slug => $def ) {
        if ( ! array_key_exists( $slug, $normalized ) ) {
            $normalized[ $slug ] = false;
        }
    }

    $no_link = isset( $stored['hashtag_no_link'] ) && 'plain' === $stored['hashtag_no_link'] ? 'plain' : 'archive';

    $cache[ $blog_id ] = array(
        'networks'                => $normalized,
        'blogosphere_auto_append' => ! isset( $stored['blogosphere_auto_append'] ) || ! empty( $stored['blogosphere_auto_append'] ),
        'hashtag_no_link'         => $no_link,
    );
    return $cache[ $blog_id ];
}

/**
 * How to render a token (hashtag or mention) that has no enabled network links.
 *
 * @since 1.0.0
 * @return string 'archive' (link to the local term archive) or 'plain' (no link).
 */
function sh_no_link_mode(): string {
    $settings = sh_get_settings();
    return $settings['hashtag_no_link'];
}

/**
 * Whether the Blogosphere section should auto-append to hashtag archives.
 *
 * @since 1.0.0
 * @return bool
 */
function sh_blogosphere_auto_append_setting(): bool {
    $settings = sh_get_settings();
    return (bool) $settings['blogosphere_auto_append'];
}

/**
 * Returns the ordered network map ( slug => enabled ).
 *
 * @since 1.0.0
 * @return array<string,bool>
 */
function sh_get_network_states(): array {
    $settings = sh_get_settings();
    return $settings['networks'];
}

/**
 * Returns the ordered list of enabled network slugs.
 *
 * @since 1.0.0
 * @param string $taxonomy Optional taxonomy to filter by network support.
 * @return string[]
 */
function sh_get_enabled_networks( string $taxonomy = '' ): array {
    $out = array();
    foreach ( sh_get_network_states() as $slug => $enabled ) {
        if ( ! $enabled ) {
            continue;
        }
        if ( '' !== $taxonomy && ! sh_network_supports( $slug, $taxonomy ) ) {
            continue;
        }
        $out[] = $slug;
    }
    return $out;
}

/**
 * Whether a network is enabled.
 *
 * @since 1.0.0
 * @param string $slug Network slug.
 * @return bool
 */
function sh_is_network_enabled( string $slug ): bool {
    $states = sh_get_network_states();
    return ! empty( $states[ $slug ] );
}

/**
 * Persists the full settings: ordered network map plus display options.
 *
 * @since 1.0.0
 * @param array<string,bool> $networks    Ordered map ( slug => enabled ).
 * @param bool               $auto_append Whether to auto-append the Blogosphere section.
 * @param string             $no_link     'archive' or 'plain'.
 * @return bool
 */
function sh_save_settings( array $networks, bool $auto_append, string $no_link ): bool {
    $registry = sh_get_network_registry();
    $clean    = array();
    foreach ( $networks as $slug => $enabled ) {
        if ( isset( $registry[ $slug ] ) ) {
            $clean[ $slug ] = (bool) $enabled;
        }
    }
    return (bool) update_option(
        SH_OPTION,
        array(
            'networks'                => $clean,
            'blogosphere_auto_append' => $auto_append,
            'hashtag_no_link'         => 'plain' === $no_link ? 'plain' : 'archive',
        )
    );
}
