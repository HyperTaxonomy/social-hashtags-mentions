<?php
/**
 * TermConnect link-type integration.
 *
 * Each external (non-internal) network is registered with TermConnect as a
 * `social_{slug}` link type via the tc_link_types filter. The `provider` flag
 * keeps these out of TC's Peer Sites admin (they are managed in this plugin's
 * settings) while staying active for the term-edit panel, REST, and peer
 * matching. The enabled flag is derived from this plugin's settings so the
 * admin toggles are the single source of truth — nothing is persisted in TC's
 * tc_link_types option.
 *
 * URLs are produced through the tc_link_uri filter, which branches per taxonomy
 * (hashtag URL on the hashtag taxonomy, profile URL on the mention taxonomy)
 * and handles instance-based transforms (Mastodon).
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'tc_link_types', 'sh_register_link_types' );
add_filter( 'tc_link_uri', 'sh_filter_link_uri', 10, 5 );
add_filter( 'tc_enabled_taxonomies', 'sh_enable_tc_taxonomies' );
add_filter( 'tc_suggest_config', 'sh_suggest_config', 10, 4 );
add_filter( 'tc_parse_suggest_response', 'sh_parse_suggest_response', 10, 3 );
add_filter( 'tc_taxonomy_peer_authors', 'sh_mention_maps_to_peer_authors', 10, 2 );
add_filter( 'tc_link_value', 'sh_sanitize_handle', 10, 2 );

/**
 * Basic validation/normalization for free-form social handles.
 *
 * Applies only to this plugin's social_{network} link types. Trims the value,
 * reduces a pasted profile URL to its last path segment (for instance-based
 * networks such as Mastodon, a pasted https://{instance}/@{user} URL is instead
 * rewritten to user@instance, its handle shape), strips a leading "@", and
 * rejects anything that still contains whitespace (which is never a handle).
 *
 * @since 1.0.0
 * @param string $id   Raw identifier.
 * @param string $slug Link type slug.
 * @return string Normalized handle, or '' to reject (removes the link).
 */
function sh_sanitize_handle( $id, string $slug ): string {
    if ( 0 !== strpos( $slug, 'social_' ) ) {
        return (string) $id;
    }

    $id  = trim( (string) $id );
    $def = sh_get_network( substr( $slug, 7 ) );

    if ( false !== strpos( $id, '://' ) ) {
        if ( ! empty( $def['instance'] ) ) {
            // A pasted https://{instance}/@{user} profile URL → user@instance.
            $host = (string) wp_parse_url( $id, PHP_URL_HOST );
            $path = (string) wp_parse_url( $id, PHP_URL_PATH );
            $user = ltrim( trim( $path, '/' ), '@' );
            $id   = ( '' !== $host && '' !== $user && false === strpos( $user, '/' ) )
                ? $user . '@' . $host
                : '';
        } else {
            // A pasted profile URL → keep its last path segment.
            $path  = (string) wp_parse_url( $id, PHP_URL_PATH );
            $parts = array_values( array_filter( explode( '/', $path ) ) );
            $id    = $parts ? (string) end( $parts ) : '';
        }
    }

    $id = ltrim( $id, '@' );

    if ( '' === $id || preg_match( '/\s/', $id ) ) {
        return '';
    }
    return $id;
}

/**
 * Maps the mention taxonomy's WordPress-peer links to peer authors.
 *
 * A mention is a person, who on a peer WordPress site is an author (a WP user),
 * not a taxonomy term — so the peer link on a mention term should search the peer's
 * authors and resolve to their author archive. (Peer mention *terms*, when a peer
 * also runs this plugin, are matched automatically by shared handle URI.)
 *
 * @since 1.0.0
 * @param bool   $is_author Current value.
 * @param string $taxonomy  Taxonomy slug.
 * @return bool
 */
function sh_mention_maps_to_peer_authors( $is_author, string $taxonomy ): bool {
    return 'mention' === $taxonomy ? true : (bool) $is_author;
}

/**
 * Opts the hashtag and mention taxonomies into TermConnect.
 *
 * This makes TermConnect expose the tc_links REST field and the htax_term_uri
 * matching parameter for these taxonomies, and include their terms in peer
 * resolution by shared identifier. The practical effect for mentions: two peer
 * sites that store the same network handle for a person (e.g. X: @jdoe) match by
 * the resulting handle URI even when the term slugs and labels differ — the same
 * identifier-overlap mechanism TermConnect already uses for shared locations.
 *
 * @since 1.0.0
 * @param string[] $taxonomies Enabled taxonomy slugs.
 * @return string[]
 */
function sh_enable_tc_taxonomies( $taxonomies ): array {
    return array_merge( (array) $taxonomies, sh_taxonomies() );
}

/**
 * Registers one `social_{slug}` link type per external network.
 *
 * @since 1.0.0
 * @param array[] $types Existing link types keyed by slug.
 * @return array[]
 */
function sh_register_link_types( array $types ): array {
    foreach ( sh_get_network_registry() as $slug => $def ) {
        if ( ! empty( $def['internal'] ) ) {
            continue; // Blogosphere has no external link type.
        }

        $type_slug = 'social_' . $slug;

        $types[ $type_slug ] = array(
            'slug'             => $type_slug,
            'label'            => (string) $def['label'],
            // Real URLs come from the tc_link_uri filter; this is only a fallback prefix.
            'uri_prefix'       => '',
            'description'      => __( 'Social network', 'social-hashtags-mentions' ),
            'platform'         => 'other',
            'enabled'          => sh_is_network_enabled( $slug ),
            'show_on_archives' => true,
            'taxonomies'       => (array) $def['supports'],
            'provider'         => 'social-hashtags-mentions',
            'icon'             => (string) ( $def['icon'] ?? '' ),
        );
    }

    // A non-configurable "Local site" pseudo-peer: maps a mention to a local author.
    // It is a WordPress link type pointing at this site's own origin, so it rides the
    // same author autocomplete + /author/{nicename}/ resolution as remote peers (the
    // mention taxonomy is a "people" taxonomy via the tc_taxonomy_peer_authors filter).
    $types['local_site'] = array(
        'slug'             => 'local_site',
        'label'            => __( 'Local site', 'social-hashtags-mentions' ),
        'uri_prefix'       => home_url( '/' ),
        'description'      => __( 'A local author profile', 'social-hashtags-mentions' ),
        'platform'         => 'wordpress',
        'enabled'          => true,
        'show_on_archives' => true,
        'taxonomies'       => array( 'mention' ),
        'provider'         => 'social-hashtags-mentions',
        'remote_taxonomy'  => '',
        'icon'             => 'dashicons-admin-home',
        'sort_last'        => true,
    );

    return $types;
}

/**
 * Builds the per-network, per-taxonomy URL for `social_{slug}` link types.
 *
 * @since 1.0.0
 * @param string|null $uri      Default URI from TermConnect.
 * @param string      $slug     Link type slug.
 * @param string      $id       Identifier (hashtag slug or profile handle).
 * @param array       $type     Link type definition.
 * @param string      $taxonomy Local taxonomy slug.
 * @return string|null
 */
function sh_filter_link_uri( ?string $uri, string $slug, string $id, array $type, string $taxonomy ): ?string {
    // "Local site" resolves to the local author archive for the stored nicename.
    if ( 'local_site' === $slug ) {
        $user = get_user_by( 'slug', $id );
        return $user ? get_author_posts_url( $user->ID ) : $uri;
    }
    if ( 0 !== strpos( $slug, 'social_' ) ) {
        return $uri;
    }
    $network = substr( $slug, 7 );
    return sh_build_network_url( $network, $id, $taxonomy );
}

/**
 * Provides a profile-search suggest endpoint for networks that expose a public,
 * credential-free typeahead API.
 *
 * Only networks whose author/profile search can be queried server-side without
 * authentication are listed; every other network keeps a plain identifier field
 * (no autocomplete) — the same way TermConnect treats identifier types that have
 * no public suggest API. Suggestions here are advisory (`free_text`): the editor
 * may also know a handle that does not surface in search, so typed input is kept.
 *
 * @since 1.0.0
 * @param array|null $config   Existing config (null if none).
 * @param string     $slug     Link type slug.
 * @param array|null $type     Link type definition, if known.
 * @param string     $taxonomy Taxonomy of the term being edited, if known.
 * @return array|null
 */
function sh_suggest_config( $config, string $slug, $type = null, string $taxonomy = '' ) {
    if ( null !== $config || 0 !== strpos( $slug, 'social_' ) ) {
        return $config;
    }

    // These endpoints search people/profiles, so they only apply to the mention
    // (profile) taxonomy. When the taxonomy is unknown, allow it (the AJAX proxy
    // would have been wired only for a taxonomy where it was offered).
    if ( '' !== $taxonomy && 'mention' !== $taxonomy ) {
        return $config;
    }

    switch ( substr( $slug, 7 ) ) {
        case 'github':
            return array(
                'url'       => 'https://api.github.com/search/users?q={query}&per_page=8',
                'parser'    => 'sh_github',
                'free_text' => true,
            );
        case 'bluesky':
            return array(
                'url'       => 'https://public.api.bsky.app/xrpc/app.bsky.actor.searchActorsTypeahead?q={query}&limit=8',
                'parser'    => 'sh_bluesky',
                'free_text' => true,
            );
    }

    return $config;
}

/**
 * Parses suggest responses for this plugin's network parsers into the
 * { value, label } shape TermConnect's autocomplete expects. The stored value is
 * the network handle; the label adds the display name when one is available.
 *
 * @since 1.0.0
 * @param array[] $results Existing results.
 * @param string  $body    Raw response body.
 * @param string  $parser  Parser id.
 * @return array[]
 */
function sh_parse_suggest_response( array $results, string $body, string $parser ): array {
    if ( 'sh_github' !== $parser && 'sh_bluesky' !== $parser ) {
        return $results;
    }

    $data = json_decode( $body, true );
    if ( ! is_array( $data ) ) {
        return $results;
    }

    $out = array();

    if ( 'sh_github' === $parser ) {
        foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
            $login = (string) ( $item['login'] ?? '' );
            if ( '' !== $login ) {
                $out[] = array( 'value' => $login, 'label' => '@' . $login );
            }
        }
        return $out;
    }

    // sh_bluesky
    foreach ( (array) ( $data['actors'] ?? array() ) as $actor ) {
        $handle = (string) ( $actor['handle'] ?? '' );
        if ( '' === $handle ) {
            continue;
        }
        $name      = (string) ( $actor['displayName'] ?? '' );
        $out[]     = array(
            'value' => $handle,
            'label' => '' !== $name ? $name . ' (@' . $handle . ')' : '@' . $handle,
        );
    }

    return $out;
}
