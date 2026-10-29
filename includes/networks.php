<?php
/**
 * Social network registry.
 *
 * Each network defines how to build a hashtag URL and/or a profile URL, which
 * taxonomies it supports, and a dashicon for badges. Networks are exposed to
 * TermConnect as `social_{slug}` link types (see link-types.php) and offered in
 * the block editor in the admin-defined order (see settings.php).
 *
 * The special `blogosphere` pseudo-network is hashtag-only and has no external
 * URL — it aggregates peer-site posts via TermConnect (see blogosphere.php).
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the built-in network definitions, keyed by slug.
 *
 * Definition keys:
 *  - label        : Display name.
 *  - hashtag_url  : sprintf pattern with one %s for the hashtag slug, or '' if unsupported.
 *  - profile_url  : sprintf pattern with one %s for the profile handle, or '' if unsupported.
 *  - supports     : array of taxonomy slugs ('hashtag', 'mention').
 *  - icon         : dashicons class for badges.
 *  - instance     : (optional) true for handle-with-instance networks (e.g. Mastodon),
 *                   where the mention handle is 'user@host' and the URL is host/@user.
 *  - internal     : (optional) true for the Blogosphere pseudo-network (local target).
 *
 * Third parties may add or modify networks via the `sh_network_registry` filter.
 *
 * @since 1.0.0
 * @return array[] [ slug => definition ]
 */
function sh_get_network_registry(): array {
    // Keyed by blog ID so any multisite caller that switch_to_blog()s between
    // sites within one request (the plugin's own uninstall.php iterates sites
    // this way, and third-party tooling may do the same) never gets served a
    // different site's cached/filtered registry.
    static $cache = array();
    $blog_id = get_current_blog_id();
    if ( isset( $cache[ $blog_id ] ) ) {
        return $cache[ $blog_id ];
    }

    // Ordered roughly by global popularity, with the built-in Blogosphere pinned first.
    $registry = array(
        'blogosphere' => array(
            // The local + peer WordPress network: peer post summaries on hashtag
            // archives, and the person's local + peer author profiles on mentions.
            'label'       => __( 'Blogosphere', 'social-hashtags-mentions' ),
            'hashtag_url' => '',
            'profile_url' => '',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-admin-site-alt3',
            'internal'    => true,
        ),
        'facebook'    => array(
            'label'       => 'Facebook',
            'hashtag_url' => 'https://www.facebook.com/hashtag/%s',
            'profile_url' => 'https://www.facebook.com/%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-facebook',
        ),
        'whatsapp'    => array(
            'label'       => 'WhatsApp',
            'hashtag_url' => '',
            'profile_url' => 'https://wa.me/%s',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-whatsapp',
        ),
        'youtube'     => array(
            'label'       => 'YouTube',
            'hashtag_url' => 'https://www.youtube.com/hashtag/%s',
            'profile_url' => 'https://www.youtube.com/@%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-youtube',
        ),
        'instagram'   => array(
            'label'       => 'Instagram',
            'hashtag_url' => 'https://www.instagram.com/explore/tags/%s/',
            'profile_url' => 'https://www.instagram.com/%s/',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-instagram',
        ),
        'tiktok'      => array(
            'label'       => 'TikTok',
            'hashtag_url' => 'https://www.tiktok.com/tag/%s',
            'profile_url' => 'https://www.tiktok.com/@%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-video-alt3',
        ),
        'telegram'    => array(
            'label'       => 'Telegram',
            'hashtag_url' => '',
            'profile_url' => 'https://t.me/%s',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-admin-comments',
        ),
        'snapchat'    => array(
            'label'       => 'Snapchat',
            'hashtag_url' => '',
            'profile_url' => 'https://www.snapchat.com/add/%s',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-camera',
        ),
        'x'           => array(
            'label'       => 'X',
            'hashtag_url' => 'https://x.com/hashtag/%s',
            'profile_url' => 'https://x.com/%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-twitter',
        ),
        'pinterest'   => array(
            'label'       => 'Pinterest',
            'hashtag_url' => 'https://www.pinterest.com/search/pins/?q=%%23%s',
            'profile_url' => 'https://www.pinterest.com/%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-pinterest',
        ),
        'reddit'      => array(
            'label'       => 'Reddit',
            'hashtag_url' => '',
            'profile_url' => 'https://www.reddit.com/user/%s',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-reddit',
        ),
        'linkedin'    => array(
            'label'       => 'LinkedIn',
            'hashtag_url' => 'https://www.linkedin.com/feed/hashtag/?keywords=%s',
            'profile_url' => 'https://www.linkedin.com/in/%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-linkedin',
        ),
        'threads'     => array(
            'label'       => 'Threads',
            'hashtag_url' => 'https://www.threads.net/search?q=%s&serp_type=tags',
            'profile_url' => 'https://www.threads.net/@%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-format-chat',
        ),
        'twitch'      => array(
            'label'       => 'Twitch',
            'hashtag_url' => '',
            'profile_url' => 'https://www.twitch.tv/%s',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-video-alt2',
        ),
        'tumblr'      => array(
            'label'       => 'Tumblr',
            'hashtag_url' => 'https://www.tumblr.com/tagged/%s',
            'profile_url' => 'https://%s.tumblr.com/',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-welcome-write-blog',
        ),
        'github'      => array(
            'label'       => 'GitHub',
            'hashtag_url' => '',
            'profile_url' => 'https://github.com/%s',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-editor-code',
        ),
        'flickr'      => array(
            'label'       => 'Flickr',
            'hashtag_url' => 'https://www.flickr.com/photos/tags/%s/',
            'profile_url' => 'https://www.flickr.com/people/%s/',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-format-gallery',
        ),
        'bluesky'     => array(
            'label'       => 'Bluesky',
            'hashtag_url' => 'https://bsky.app/hashtag/%s',
            'profile_url' => 'https://bsky.app/profile/%s',
            'supports'    => array( 'hashtag', 'mention' ),
            'icon'        => 'dashicons-cloud',
        ),
        'mastodon'    => array(
            'label'       => 'Mastodon',
            'hashtag_url' => '',
            'profile_url' => '',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-networking',
            'instance'    => true,
        ),
        'substack'    => array(
            'label'       => 'Substack',
            'hashtag_url' => '',
            'profile_url' => 'https://substack.com/@%s',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-email',
        ),
        'livejournal' => array(
            'label'       => 'LiveJournal',
            'hashtag_url' => '',
            'profile_url' => 'https://%s.livejournal.com/',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-admin-users',
        ),
        'dreamwidth'  => array(
            'label'       => 'Dreamwidth',
            'hashtag_url' => '',
            'profile_url' => 'https://%s.dreamwidth.org/',
            'supports'    => array( 'mention' ),
            'icon'        => 'dashicons-id',
        ),
    );

    /**
     * Filters the social network registry.
     *
     * @since 1.0.0
     * @param array[] $registry [ slug => definition ]
     */
    $registry = apply_filters( 'sh_network_registry', $registry );

    // Hashtags are the primary feature, so group networks that support hashtags
    // above mention-only ones. The split is stable (PHP 7.4-safe), so the
    // popularity order is preserved within each group, and Blogosphere stays first.
    $with_hashtag = array();
    $mention_only = array();
    foreach ( $registry as $slug => $def ) {
        if ( in_array( 'hashtag', (array) ( $def['supports'] ?? array() ), true ) ) {
            $with_hashtag[ $slug ] = $def;
        } else {
            $mention_only[ $slug ] = $def;
        }
    }
    $registry = $with_hashtag + $mention_only;

    $cache[ $blog_id ] = $registry;

    return $registry;
}

/**
 * Returns a single network definition, or null.
 *
 * @since 1.0.0
 * @param string $slug Network slug (without the social_ prefix).
 * @return array|null
 */
function sh_get_network( string $slug ): ?array {
    $registry = sh_get_network_registry();
    return $registry[ $slug ] ?? null;
}

/**
 * Whether a network supports a given taxonomy.
 *
 * @since 1.0.0
 * @param string $slug     Network slug.
 * @param string $taxonomy Taxonomy slug.
 * @return bool
 */
function sh_network_supports( string $slug, string $taxonomy ): bool {
    $def = sh_get_network( $slug );
    return $def && in_array( $taxonomy, (array) $def['supports'], true );
}

/**
 * The taxonomies this plugin manages.
 *
 * @since 1.0.0
 * @return string[]
 */
function sh_taxonomies(): array {
    return array( 'hashtag', 'mention' );
}

/**
 * Returns the icon markup for a network.
 *
 * Prefers a bundled SVG at assets/icons/{slug}.svg (so brandless networks can use
 * a real mark in the front-end multilink panel and badges); falls back to the
 * network's Dashicon when no SVG is present. Bundled SVGs should be monochrome
 * (fill="currentColor") with a square viewBox so they match Dashicons inline —
 * see assets/icons/README.md.
 *
 * @since 1.0.0
 * @param string $slug        Network slug.
 * @param string $dashicon    Dashicons class to fall back to.
 * @param string $extra_class Optional extra class on the wrapper.
 * @return string
 */
function sh_network_icon_html( string $slug, string $dashicon, string $color = '', string $extra_class = '' ): string {
    $svg   = sh_network_svg( $slug );
    // The SVG fills with currentColor and Dashicons are font glyphs, so a wrapper
    // text color tints either. Front-end/editor pass the brand color; admin omits it.
    $style = '' !== $color ? ' style="color:' . esc_attr( $color ) . '"' : '';

    if ( '' !== $svg ) {
        $class = trim( 'sh-icon ' . $extra_class );
        // First-party bundled asset; output inline so it inherits currentColor.
        return '<span class="' . esc_attr( $class ) . '"' . $style . ' aria-hidden="true">' . $svg . '</span>';
    }

    $dashicon = '' !== $dashicon ? $dashicon : 'dashicons-admin-links';
    $class    = trim( 'dashicons ' . $dashicon . ' ' . $extra_class );
    return '<span class="' . esc_attr( $class ) . '"' . $style . ' aria-hidden="true"></span>';
}

/**
 * Returns the raw bundled SVG markup for a network, or '' if none.
 *
 * @since 1.0.0
 * @param string $slug Network slug.
 * @return string
 */
function sh_network_svg( string $slug ): string {
    static $cache = array();
    if ( ! array_key_exists( $slug, $cache ) ) {
        $file           = SH_PLUGIN_DIR . 'assets/icons/' . $slug . '.svg';
        $cache[ $slug ] = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : '';
    }
    return $cache[ $slug ];
}

/**
 * The official brand color for a network, used to tint its icon in the editor and
 * the front-end panel (admin screens stay monochrome). Returns '' when none.
 *
 * Hex values are the brands' official colors (per Simple Icons).
 *
 * @since 1.0.0
 * @param string $slug Network slug.
 * @return string
 */
function sh_network_color( string $slug ): string {
    static $colors = array(
        'facebook'    => '#1877F2',
        'whatsapp'    => '#25D366',
        'youtube'     => '#FF0000',
        'instagram'   => '#E4405F',
        'tiktok'      => '#000000',
        'telegram'    => '#26A5E4',
        'snapchat'    => '#FFFC00',
        'x'           => '#000000',
        'pinterest'   => '#BD081C',
        'reddit'      => '#FF4500',
        'linkedin'    => '#0A66C2',
        'threads'     => '#000000',
        'twitch'      => '#9146FF',
        'tumblr'      => '#36465D',
        'github'      => '#181717',
        'flickr'      => '#0063DC',
        'bluesky'     => '#0285FF',
        'mastodon'    => '#6364FF',
        'substack'    => '#FF6719',
        'livejournal' => '#00B0EA',
        // Dreamwidth has no Simple Icons entry (see assets/icons/README.md), so
        // this is an approximation of its teal site accent, not a sourced brand
        // value like the others above — verify against Dreamwidth's own brand
        // guide before treating it as authoritative.
        'dreamwidth'  => '#00707A',
    );
    return $colors[ $slug ] ?? '';
}

/**
 * Builds the external URL for a network + identifier on a given taxonomy.
 *
 * Hashtag taxonomy uses the slug as the identifier; mention taxonomy uses the
 * stored handle. Returns null when the network cannot produce a URL for the
 * taxonomy (e.g. a mention-only network on a hashtag, or the internal
 * Blogosphere network).
 *
 * @since 1.0.0
 * @param string $slug     Network slug.
 * @param string $id       Identifier (hashtag slug or profile handle).
 * @param string $taxonomy Taxonomy slug.
 * @return string|null
 */
function sh_build_network_url( string $slug, string $id, string $taxonomy ): ?string {
    $def = sh_get_network( $slug );
    if ( ! $def || '' === $id || ! empty( $def['internal'] ) ) {
        return null;
    }

    if ( 'hashtag' === $taxonomy ) {
        if ( empty( $def['hashtag_url'] ) ) {
            return null;
        }
        return sprintf( $def['hashtag_url'], rawurlencode( $id ) );
    }

    if ( 'mention' === $taxonomy ) {
        // Instance-based networks (Mastodon): handle 'user@host' → 'https://host/@user'.
        if ( ! empty( $def['instance'] ) ) {
            $handle = ltrim( $id, '@' );
            if ( false === strpos( $handle, '@' ) ) {
                return null; // Need both user and host.
            }
            list( $user, $host ) = explode( '@', $handle, 2 );
            if ( '' === $user || '' === $host ) {
                return null;
            }
            return 'https://' . $host . '/@' . rawurlencode( $user );
        }
        if ( empty( $def['profile_url'] ) ) {
            return null;
        }
        return sprintf( $def['profile_url'], rawurlencode( ltrim( $id, '@' ) ) );
    }

    return null;
}
