<?php
/**
 * Blogosphere: aggregated peer-post summaries for a hashtag.
 *
 * The Blogosphere is a built-in, hashtag-only pseudo-network. It has no external
 * URL; its "target" is the local hashtag archive. When enabled, the plugin
 * injects a summary list of posts that share the hashtag across all configured
 * WordPress peers (via TermConnect's peer-fetch API) into the hashtag archive.
 *
 * Nothing is rendered as a local post: we show only the summary TermConnect
 * already returns (title, excerpt, thumbnail) and each card links out to the
 * peer's own post URL. Remote posts never enter the archive WP_Query, so the
 * native loop and its pagination are untouched. This section paginates with its
 * own query var (sh_bg_page) over a cached, capped pool.
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'social_blogosphere', 'sh_blogosphere_shortcode' );
add_action( 'loop_end', 'sh_blogosphere_auto_append' );

/**
 * Whether the Blogosphere pseudo-network is enabled.
 *
 * @since 1.0.0
 * @return bool
 */
function sh_blogosphere_enabled(): bool {
    return sh_is_network_enabled( 'blogosphere' );
}

/**
 * Whether the Blogosphere section should auto-append to hashtag archives.
 *
 * @since 1.0.0
 * @return bool
 */
function sh_blogosphere_auto_append_enabled(): bool {
    /**
     * Filters whether the Blogosphere section is auto-appended to hashtag archives.
     *
     * Disable this to place the section manually with the [social_blogosphere]
     * shortcode (recommended for block themes, where loop_end may not fire as expected).
     *
     * @since 1.0.0
     * @param bool $enabled Default: true when the Blogosphere network is enabled and the setting is on.
     */
    return (bool) apply_filters( 'sh_blogosphere_auto_append', sh_blogosphere_enabled() && sh_blogosphere_auto_append_setting() );
}

/**
 * Returns the configured WordPress peers as [ slug, label, origin ] tuples.
 *
 * Only reachable, non-blacklisted origins are returned (SSRF-safe).
 *
 * @since 1.0.0
 * @return array[]
 */
function sh_blogosphere_peers(): array {
    if ( ! function_exists( 'tc_get_platform_link_types' ) || ! function_exists( 'tc_external_get_site_origin' ) ) {
        return array();
    }

    $peers = array();
    foreach ( tc_get_platform_link_types( 'wordpress' ) as $slug => $type ) {
        // Skip types explicitly scoped away from hashtag (e.g. this plugin's own
        // local_site type, which declares taxonomies => ['mention'] only, since it
        // maps mentions to local author profiles rather than being a real peer).
        // An empty/absent taxonomies field (TC's own Peer Sites entries) is unrestricted.
        $taxonomies = (array) ( $type['taxonomies'] ?? array() );
        if ( ! empty( $taxonomies ) && ! in_array( 'hashtag', $taxonomies, true ) ) {
            continue;
        }
        $origin = tc_external_get_site_origin( (string) ( $type['uri_prefix'] ?? '' ) );
        if ( '' === $origin ) {
            continue;
        }
        if ( function_exists( 'tc_external_is_blacklisted' ) && tc_external_is_blacklisted( $origin ) ) {
            continue;
        }
        if ( function_exists( 'tc_external_validate_origin' ) && ! tc_external_validate_origin( $origin ) ) {
            continue;
        }
        $peers[] = array(
            'slug'   => $slug,
            'label'  => (string) ( $type['label'] ?? $slug ),
            'origin' => $origin,
        );
    }
    return $peers;
}

/**
 * Fetches and merges the peer-post pool for a hashtag slug.
 *
 * Resolves the hashtag slug on each WordPress peer, fetches recent posts, then
 * merges, de-dupes by URL, and sorts by date descending. The merged pool is
 * cached briefly so pagination does not re-fetch.
 *
 * @since 1.0.0
 * @param string $hashtag_slug Hashtag term slug.
 * @param int    $fetch_size   Max posts to request per peer.
 * @return array[] Normalized peer posts.
 */
function sh_blogosphere_pool( string $hashtag_slug, int $fetch_size = 30 ): array {
    if ( '' === $hashtag_slug || ! function_exists( 'tc_external_fetch_posts' ) || ! function_exists( 'tc_external_resolve_term_id' ) ) {
        return array();
    }

    // Peer origins are folded into the cache key (sorted, so key order doesn't
    // matter) so adding or removing a peer naturally produces a fresh key instead
    // of serving a pool cached under the old peer set for up to $ttl.
    $peers        = sh_blogosphere_peers();
    $peer_origins = wp_list_pluck( $peers, 'origin' );
    sort( $peer_origins );
    $cache_key = 'sh_bg_pool_' . md5( $hashtag_slug . '|' . $fetch_size . '|' . implode( ',', $peer_origins ) );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return (array) $cached;
    }

    /**
     * Filters the cache TTL (seconds) for the Blogosphere post pool.
     *
     * @since 1.0.0
     * @param int $ttl Default: 1 hour.
     */
    $ttl = (int) apply_filters( 'sh_blogosphere_cache_ttl', HOUR_IN_SECONDS );

    $merged = array();
    foreach ( $peers as $peer ) {
        $remote_id = tc_external_resolve_term_id( $peer['origin'], 'hashtag', $hashtag_slug );
        if ( ! $remote_id ) {
            continue;
        }
        $posts = tc_external_fetch_posts(
            $peer['origin'],
            'hashtag',
            array( $remote_id ),
            true,
            $fetch_size,
            $ttl,
            $peer['slug']
        );
        foreach ( $posts as $post ) {
            $url = (string) ( $post['url'] ?? '' );
            if ( '' === $url ) {
                continue;
            }
            $post['peer_label']                  = $peer['label'];
            $merged[ untrailingslashit( $url ) ] = $post; // de-dupe by normalized URL
        }
    }

    $merged = array_values( $merged );
    usort(
        $merged,
        static function ( $a, $b ) {
            return strcmp( (string) ( $b['date_gmt'] ?? '' ), (string) ( $a['date_gmt'] ?? '' ) );
        }
    );

    // Avoid caching an empty pool: a transient peer outage would otherwise
    // suppress the section for the full TTL. Only cache a non-empty result.
    if ( ! empty( $merged ) ) {
        set_transient( $cache_key, $merged, $ttl );
    }
    return $merged;
}

/**
 * Renders the Blogosphere section for a hashtag.
 *
 * @since 1.0.0
 * @param array $args {
 *     @type string $hashtag  Hashtag slug. Defaults to the current hashtag archive term.
 *     @type int    $per_page Items per page. Default 10.
 * }
 * @return string HTML, or '' when there is nothing to show.
 */
function sh_render_blogosphere( array $args = array() ): string {
    if ( ! sh_blogosphere_enabled() ) {
        return '';
    }

    $hashtag  = (string) ( $args['hashtag'] ?? '' );
    $per_page = max( 1, (int) ( $args['per_page'] ?? 10 ) );

    if ( '' === $hashtag ) {
        $obj = get_queried_object();
        if ( $obj instanceof WP_Term && 'hashtag' === $obj->taxonomy ) {
            $hashtag = $obj->slug;
        }
    }
    if ( '' === $hashtag ) {
        return '';
    }

    $fetch_size = (int) apply_filters( 'sh_blogosphere_fetch_size', max( 30, $per_page * 3 ) );
    $pool       = sh_blogosphere_pool( $hashtag, $fetch_size );
    if ( empty( $pool ) ) {
        return '';
    }

    $total = count( $pool );
    $page     = isset( $_GET['sh_bg_page'] ) ? max( 1, absint( wp_unslash( $_GET['sh_bg_page'] ) ) ) : 1;
    $pages    = (int) ceil( $total / $per_page );
    $page     = min( $page, $pages );
    $offset   = ( $page - 1 ) * $per_page;
    $items    = array_slice( $pool, $offset, $per_page );

    ob_start();
    ?>
    <section class="sh-blogosphere" id="sh-blogosphere" aria-label="<?php esc_attr_e( 'From around the blogosphere', 'social-hashtags-mentions' ); ?>">
        <h2 class="sh-blogosphere__title"><?php esc_html_e( 'From around the blogosphere', 'social-hashtags-mentions' ); ?></h2>
        <ul class="sh-blogosphere__list">
            <?php foreach ( $items as $post ) : ?>
                <?php
                $url     = (string) ( $post['url'] ?? '' );
                $title   = (string) ( $post['title'] ?? $url );
                $excerpt = (string) ( $post['excerpt'] ?? '' );
                $thumb   = (string) ( $post['thumbnail_url'] ?? '' );
                $source  = (string) ( $post['peer_label'] ?? wp_parse_url( $url, PHP_URL_HOST ) );
                ?>
                <li class="sh-blogosphere__item">
                    <a class="sh-blogosphere__link" href="<?php echo esc_url( $url ); ?>" rel="nofollow external noopener" target="_blank">
                        <?php if ( '' !== $thumb ) : ?>
                            <img class="sh-blogosphere__thumb" src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" />
                        <?php endif; ?>
                        <span class="sh-blogosphere__title-text"><?php echo esc_html( $title ); ?></span>
                    </a>
                    <span class="sh-blogosphere__source"><?php echo esc_html( $source ); ?></span>
                    <?php if ( '' !== $excerpt ) : ?>
                        <p class="sh-blogosphere__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 30 ) ); ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ( $pages > 1 ) : ?>
            <nav class="sh-blogosphere__pagination" aria-label="<?php esc_attr_e( 'Blogosphere navigation', 'social-hashtags-mentions' ); ?>">
                <?php if ( $page > 1 ) : ?>
                    <a class="sh-blogosphere__prev" href="<?php echo esc_url( add_query_arg( 'sh_bg_page', $page - 1 ) . '#sh-blogosphere' ); ?>">
                        <?php esc_html_e( '&larr; Newer', 'social-hashtags-mentions' ); ?>
                    </a>
                <?php endif; ?>
                <span class="sh-blogosphere__page-of">
                    <?php
                    /* translators: 1: current page, 2: total pages. */
                    echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'social-hashtags-mentions' ), $page, $pages ) );
                    ?>
                </span>
                <?php if ( $page < $pages ) : ?>
                    <a class="sh-blogosphere__next" href="<?php echo esc_url( add_query_arg( 'sh_bg_page', $page + 1 ) . '#sh-blogosphere' ); ?>">
                        <?php esc_html_e( 'Older &rarr;', 'social-hashtags-mentions' ); ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
}

/**
 * Shortcode handler for [social_blogosphere].
 *
 * @since 1.0.0
 * @param array $atts Shortcode attributes: hashtag, per_page.
 * @return string
 */
function sh_blogosphere_shortcode( $atts ): string {
    $atts = shortcode_atts(
        array(
            'hashtag'  => '',
            'per_page' => 10,
        ),
        is_array( $atts ) ? $atts : array(),
        'social_blogosphere'
    );
    return sh_render_blogosphere(
        array(
            'hashtag'  => sanitize_title( (string) $atts['hashtag'] ),
            'per_page' => (int) $atts['per_page'],
        )
    );
}

/**
 * Auto-appends the Blogosphere section after the main loop on a hashtag archive.
 *
 * @since 1.0.0
 * @param WP_Query $query The query that just finished looping.
 */
function sh_blogosphere_auto_append( $query ): void {
    static $done = false;
    if ( $done ) {
        return;
    }
    if ( ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
        return;
    }
    if ( ! is_tax( 'hashtag' ) ) {
        return;
    }
    if ( ! sh_blogosphere_auto_append_enabled() ) {
        return;
    }
    $done = true;
    echo wp_kses_post( sh_render_blogosphere() );
}
