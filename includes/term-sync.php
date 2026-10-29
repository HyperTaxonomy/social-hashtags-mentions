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
                $created = sh_get_or_create_term( $token['name'], $taxonomy );
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

    if ( ! preg_match_all( '/<a\b([^>]*)>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
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
            'tax'  => $tax,
            'id'   => (int) sh_token_attr( $attrs, 'data-sh-term' ),
            'name' => $name,
            'text' => $text,
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
    $pattern = '/\b' . preg_quote( $name, '/' ) . '=("|\')(.*?)\1/i';
    if ( preg_match( $pattern, $attrs, $m ) ) {
        return html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }
    return '';
}

/**
 * Returns an existing term ID by name, creating the term if necessary.
 *
 * @since 1.0.0
 * @param string $name     Term name/label.
 * @param string $taxonomy Taxonomy slug.
 * @return int Term ID, or 0 on failure.
 */
function sh_get_or_create_term( string $name, string $taxonomy ): int {
    $name = trim( $name );
    if ( '' === $name ) {
        return 0;
    }

    $existing = term_exists( $name, $taxonomy );
    if ( $existing ) {
        return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
    }

    $result = wp_insert_term( $name, $taxonomy );
    if ( is_wp_error( $result ) ) {
        // A race or slug collision may have created it; try once more.
        $existing = term_exists( $name, $taxonomy );
        return $existing ? (int) ( is_array( $existing ) ? $existing['term_id'] : $existing ) : 0;
    }
    return (int) $result['term_id'];
}
