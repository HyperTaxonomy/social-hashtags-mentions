<?php
/**
 * Automatic term assignment from inline tokens.
 *
 * On save, the post's hashtag/mention term assignments are reconciled to match
 * the inline tokens present in the body. There is no taxonomy meta box; the
 * content is the single source of truth for which terms a post carries.
 *
 * Tokens are anchors the editor produces, e.g.:
 *   <a class="sh-token sh-hashtag" data-sh-tax="hashtag" data-sh-term="12"
 *      data-sh-networks="*" href="...">#example</a>
 *
 * An anchor may carry data-sh-term (an existing term ID) or, for a freshly
 * created token, data-sh-name (the label) with an empty term ID — in which case
 * the term is created here (get-or-create).
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'save_post', 'sh_sync_post_terms', 20, 2 );
add_filter( 'wp_kses_allowed_html', 'sh_kses_allowed_html', 10, 2 );

/**
 * Whitelists this plugin's data-sh-* token attributes on <a> tags for the
 * 'post' kses context.
 *
 * Without this, wp_insert_post()/wp_update_post() strips every data-sh-*
 * attribute from post_content for any user without the unfiltered_html
 * capability — every role except Administrator on a single-site install, and
 * effectively everyone on multisite — which silently breaks the entire
 * token-to-taxonomy pipeline for most real installs.
 *
 * @since 1.0.0
 * @param array        $tags    Allowed tags/attributes.
 * @param string|array $context Kses context name, or an allowed-tags array.
 * @return array
 */
function sh_kses_allowed_html( $tags, $context ) {
    if ( 'post' !== $context || ! is_array( $tags ) || ! isset( $tags['a'] ) || ! is_array( $tags['a'] ) ) {
        return $tags;
    }
    foreach ( array( 'data-sh-tax', 'data-sh-term', 'data-sh-name', 'data-sh-networks', 'data-sh-user-id' ) as $attr ) {
        $tags['a'][ $attr ] = true;
    }
    return $tags;
}

/**
 * Reconciles a post's hashtag/mention terms with the tokens in its content.
 *
 * @since 1.0.0
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function sh_sync_post_terms( int $post_id, $post ): void {
    if ( ! ( $post instanceof WP_Post ) ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( ! in_array( $post->post_type, sh_token_post_types(), true ) ) {
        return;
    }

    $tokens = sh_extract_tokens( (string) $post->post_content );

    foreach ( sh_taxonomies() as $taxonomy ) {
        $ids = array();
        foreach ( $tokens as $token ) {
            if ( $token['tax'] !== $taxonomy ) {
                continue;
            }
            $term_id = $token['id'];
            if ( $term_id > 0 ) {
                $term = get_term( $term_id, $taxonomy );
                if ( $term instanceof WP_Term ) {
                    $ids[] = $term_id;
                    continue;
                }
            }
            if ( '' !== $token['name'] ) {
                $created = sh_get_or_create_term( $token['name'], $taxonomy, $token['user_id'] );
                if ( $created > 0 ) {
                    $ids[] = $created;
                }
            }
        }
        // Authoritative replace (empty array removes all assignments).
        wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $taxonomy, false );
    }
}

/**
 * Extracts inline tokens from post content.
 *
 * @since 1.0.0
 * @param string $content Post content HTML.
 * @return array[] Each: { tax: string, id: int, name: string, text: string }
 */
function sh_extract_tokens( string $content ): array {
    $tokens = array();
    if ( false === strpos( $content, 'data-sh-tax' ) ) {
        return $tokens;
    }

    // Attribute portion is quote-aware so a literal '>' inside a quoted attribute
    // value (e.g. title="a > b") doesn't truncate the match early.
    if ( ! preg_match_all( '/<a\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
        return $tokens;
    }

    foreach ( $matches as $match ) {
        $attrs = $match[1];
        $tax   = sh_token_attr( $attrs, 'data-sh-tax' );
        if ( ! in_array( $tax, sh_taxonomies(), true ) ) {
            continue;
        }
        $text = trim( wp_strip_all_tags( $match[2] ) );
        $name = sh_token_attr( $attrs, 'data-sh-name' );
        if ( '' === $name ) {
            // Fall back to the visible label, minus the leading # or @.
            $name = ltrim( $text, '#@' );
        }
        $tokens[] = array(
            'tax'     => $tax,
            'id'      => (int) sh_token_attr( $attrs, 'data-sh-term' ),
            'name'    => $name,
            'text'    => $text,
            'user_id' => (int) sh_token_attr( $attrs, 'data-sh-user-id' ),
        );
    }

    return $tokens;
}

/**
 * Reads a single attribute value from an anchor's attribute string.
 *
 * @since 1.0.0
 * @param string $attrs Attribute portion of the tag.
 * @param string $name  Attribute name.
 * @return string Unescaped value, or '' if absent.
 */
function sh_token_attr( string $attrs, string $name ): string {
    // (?<=^|\s): the name must start right after whitespace or the start of the
    // attribute list, not just any word-boundary — so "data-evil-data-sh-tax" can't
    // be misread as "data-sh-tax" the way a generic \b boundary would allow.
    $pattern = '/(?<=^|\s)' . preg_quote( $name, '/' ) . '=("|\')(.*?)\1/i';
    if ( preg_match( $pattern, $attrs, $m ) ) {
        return html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }
    return '';
}

/**
 * Returns an existing term ID by name, creating the term if necessary.
 *
 * When $user_id is given (a mention picked from the @ autocomplete's local-user
 * results), the term is resolved/claimed by that user's identity first — via the
 * `_sh_user_id` term meta set by sh_set_term_user_link() — rather than by name text
 * alone, so two different users who happen to share a display name are never
 * silently merged into one mention term.
 *
 * @since 1.0.0
 * @param string $name     Term name/label.
 * @param string $taxonomy Taxonomy slug.
 * @param int    $user_id  Optional WordPress user ID this mention resolved to.
 * @return int Term ID, or 0 on failure.
 */
function sh_get_or_create_term( string $name, string $taxonomy, int $user_id = 0 ): int {
    $name = trim( $name );
    if ( '' === $name ) {
        return 0;
    }

    // data-sh-user-id comes from post content, which any author can hand-edit. Only
    // trust it when it names a real user whose name matches the mention text (always
    // true for a pick from the @ autocomplete); otherwise resolve by name alone, so a
    // token can't claim another person's mention term or point it at a different user.
    if ( $user_id > 0 && ! sh_user_matches_mention_name( $user_id, $name ) ) {
        $user_id = 0;
    }

    if ( $user_id > 0 && 'mention' === $taxonomy ) {
        $linked_term_id = sh_get_term_by_user_id( $user_id );
        if ( $linked_term_id > 0 ) {
            return $linked_term_id;
        }
    }

    $existing = term_exists( $name, $taxonomy );
    if ( $existing ) {
        $term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );

        if ( 0 === $user_id || 'mention' !== $taxonomy ) {
            return $term_id;
        }

        $linked_user = (int) get_term_meta( $term_id, '_sh_user_id', true );
        if ( 0 === $linked_user ) {
            // An untagged term that happens to share this user's display name —
            // claim it for them.
            sh_set_term_user_link( $term_id, $user_id );
            return $term_id;
        }
        if ( $linked_user === $user_id ) {
            return $term_id;
        }

        // The name collides with a different user's already-linked term. WordPress
        // itself forbids two terms with the same name in a flat taxonomy, so create
        // a distinctly-named term instead of silently reassigning their link.
        $name = sh_disambiguate_term_name( $name, $user_id );
        $existing = term_exists( $name, $taxonomy );
        if ( $existing ) {
            $term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );

            // Same ownership check as above, mirrored: never silently reassign a term
            // already linked to a different user, even at the disambiguated name.
            $linked_user = (int) get_term_meta( $term_id, '_sh_user_id', true );
            if ( 0 === $linked_user || $linked_user === $user_id ) {
                sh_set_term_user_link( $term_id, $user_id );
                return $term_id;
            }
            // Linked to a different user: use the term by name, but never relink it.
            // (Returning here matters: falling through, wp_insert_term() would fail on
            // the existing name and the retry below would link it anyway.) Not reachable
            // today, since nicenames are unique, so two users can't share this name.
            return $term_id;
        }
    }

    $result = wp_insert_term( $name, $taxonomy );
    if ( is_wp_error( $result ) ) {
        // A race or slug collision may have created it; try once more.
        $existing = term_exists( $name, $taxonomy );
        $term_id  = $existing ? (int) ( is_array( $existing ) ? $existing['term_id'] : $existing ) : 0;
    } else {
        $term_id = (int) $result['term_id'];
    }

    if ( $term_id > 0 && $user_id > 0 && 'mention' === $taxonomy ) {
        sh_set_term_user_link( $term_id, $user_id );
    }

    return $term_id;
}

/**
 * Finds the mention term already linked to a given local user, by stored meta.
 *
 * @since 1.0.0
 * @param int $user_id WordPress user ID.
 * @return int Term ID, or 0 if none.
 */
function sh_get_term_by_user_id( int $user_id ): int {
    $terms = get_terms( array(
        'taxonomy'   => 'mention',
        'hide_empty' => false,
        'number'     => 1,
        'fields'     => 'ids',
        'meta_query' => array(
            array(
                'key'   => '_sh_user_id',
                'value' => $user_id,
            ),
        ),
    ) );
    return ( is_array( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;
}

/**
 * Builds a disambiguated term name for a mention that collides, by display name,
 * with a different local user's already-linked term.
 *
 * @since 1.0.0
 * @param string $name    Original (colliding) display name.
 * @param int    $user_id WordPress user ID the new term is for.
 * @return string
 */
function sh_disambiguate_term_name( string $name, int $user_id ): string {
    $user = get_user_by( 'id', $user_id );
    $tag  = $user ? $user->user_nicename : (string) $user_id;
    return $name . ' (' . $tag . ')';
}

/**
 * Whether a mention token's text names the given user.
 *
 * True when the user exists and the text matches their display name, nicename, or
 * login (case-insensitive). The @ autocomplete always inserts the display name.
 *
 * @since 1.0.0
 * @param int    $user_id WordPress user ID from the token.
 * @param string $name    Mention text from the token.
 * @return bool
 */
function sh_user_matches_mention_name( int $user_id, string $name ): bool {
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        return false;
    }
    $lower = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
    $name  = $lower( trim( $name ) );
    foreach ( array( $user->display_name, $user->user_nicename, $user->user_login ) as $candidate ) {
        if ( '' !== (string) $candidate && $lower( trim( (string) $candidate ) ) === $name ) {
            return true;
        }
    }
    return false;
}

/**
 * Records a mention term's linked local user and, when no "Local site" profile
 * link is already set, wires one up from that user's profile — so a mention
 * created from the @ autocomplete's local-user results is unambiguously tied to
 * that person rather than resolved by display-name text alone.
 *
 * @since 1.0.0
 * @param int $term_id Term ID.
 * @param int $user_id WordPress user ID.
 */
function sh_set_term_user_link( int $term_id, int $user_id ): void {
    update_term_meta( $term_id, '_sh_user_id', $user_id );

    if ( ! function_exists( 'tc_set_term_link' ) ) {
        return;
    }
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        return;
    }
    $existing = function_exists( 'tc_get_term_links' ) ? tc_get_term_links( $term_id ) : array();
    if ( '' !== (string) ( $existing['local_site'] ?? '' ) ) {
        return; // Already linked (e.g. set manually on the term edit screen) — don't override.
    }
    tc_set_term_link( $term_id, 'local_site', $user->user_nicename, $user->display_name );
}
