<?php
/**
 * REST endpoints used by the block editor.
 *
 * Namespace: social-hashtags/v1
 *   GET  /terms?tax=&q=        Search hashtag/mention terms.
 *   GET  /users?q=             Search local users (for @ mention autocomplete).
 *   GET  /handles?term_id=     Read a mention term's per-network handles
 *                              (drives the per-instance network checkboxes).
 *
 * All routes require the edit_posts capability (cookie nonce via apiFetch).
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'sh_register_rest_routes' );

/**
 * Permission callback: any user who can edit posts.
 *
 * @since 1.0.0
 * @return bool
 */
function sh_rest_can_edit(): bool {
    return current_user_can( 'edit_posts' );
}

/**
 * Registers the REST routes.
 *
 * @since 1.0.0
 */
function sh_register_rest_routes(): void {
    $ns = 'social-hashtags/v1';

    register_rest_route( $ns, '/terms', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'sh_rest_search_terms',
        'permission_callback' => 'sh_rest_can_edit',
        'args'                => array(
            'tax' => array( 'required' => true, 'type' => 'string' ),
            'q'   => array( 'required' => false, 'type' => 'string' ),
        ),
    ) );

    register_rest_route( $ns, '/users', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'sh_rest_search_users',
        'permission_callback' => 'sh_rest_can_edit',
        'args'                => array(
            'q' => array( 'required' => false, 'type' => 'string' ),
        ),
    ) );

    register_rest_route( $ns, '/handles', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'sh_rest_get_handles',
        'permission_callback' => 'sh_rest_can_edit',
        'args'                => array(
            'term_id' => array( 'required' => true, 'type' => 'integer' ),
        ),
    ) );
}

/**
 * Validates a taxonomy param against the plugin's taxonomies.
 *
 * @since 1.0.0
 * @param string $tax Raw taxonomy slug.
 * @return string|null
 */
function sh_rest_validate_tax( string $tax ): ?string {
    $tax = sanitize_key( $tax );
    return in_array( $tax, sh_taxonomies(), true ) ? $tax : null;
}

/**
 * Formats a term for a REST response.
 *
 * @since 1.0.0
 * @param WP_Term $term Term.
 * @return array
 */
function sh_rest_term_payload( WP_Term $term ): array {
    $link = get_term_link( $term );
    return array(
        'id'   => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'url'  => is_wp_error( $link ) ? '' : $link,
    );
}

/**
 * GET /terms — search terms.
 *
 * @since 1.0.0
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function sh_rest_search_terms( WP_REST_Request $request ) {
    $tax = sh_rest_validate_tax( (string) $request->get_param( 'tax' ) );
    if ( null === $tax ) {
        return new WP_Error( 'sh_bad_tax', __( 'Invalid taxonomy.', 'social-hashtags-mentions' ), array( 'status' => 400 ) );
    }
    $q = sanitize_text_field( (string) $request->get_param( 'q' ) );

    $terms = get_terms( array(
        'taxonomy'   => $tax,
        'search'     => $q,
        'hide_empty' => false,
        'number'     => 10,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ) );
    if ( is_wp_error( $terms ) ) {
        return $terms;
    }

    $out = array();
    foreach ( $terms as $term ) {
        $out[] = sh_rest_term_payload( $term );
    }
    return rest_ensure_response( $out );
}

/**
 * GET /users — search local users for @ autocomplete.
 *
 * @since 1.0.0
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function sh_rest_search_users( WP_REST_Request $request ) {
    $q     = sanitize_text_field( (string) $request->get_param( 'q' ) );
    $users = get_users( array(
        'search'         => '' !== $q ? '*' . $q . '*' : '',
        'search_columns' => array( 'display_name', 'user_nicename', 'user_login' ),
        'number'         => 8,
        'orderby'        => 'display_name',
        'order'          => 'ASC',
    ) );

    $out = array();
    foreach ( $users as $user ) {
        $out[] = array(
            'id'   => $user->ID,
            'name' => $user->display_name,
            'slug' => $user->user_nicename,
            'url'  => get_author_posts_url( $user->ID ),
        );
    }
    return rest_ensure_response( $out );
}

/**
 * Returns the selectable items for a mention's editor popover: each enabled social
 * network (with the term's stored handle, if any) and — when the Blogosphere
 * network is enabled — each WordPress author profile on the term (the "Local site"
 * and any peers), with the author nicename as its handle.
 *
 * @since 1.0.0
 * @param int $term_id Term ID.
 * @return array[] Each: { network, label, handle, icon }
 */
function sh_collect_handles( int $term_id ): array {
    $term = get_term( $term_id, 'mention' );
    if ( ! ( $term instanceof WP_Term ) || ! function_exists( 'sh_mention_universe' ) ) {
        return array();
    }
    $links = function_exists( 'tc_get_term_links' ) ? tc_get_term_links( $term_id ) : array();
    $out   = array();

    // Same order as the front end: settings order, with the Blogosphere subrange
    // (Local site + peers, in Peer Sites order) expanded in place.
    foreach ( sh_mention_universe( $term ) as $slug ) {
        $def = sh_get_network( $slug );
        if ( $def && empty( $def['internal'] ) ) {
            // Show the handle (or "handle (Name)") — the icon identifies the network.
            $handle = (string) ( $links[ 'social_' . $slug ] ?? '' );
            $name   = ( '' !== $handle && function_exists( 'sh_link_name' ) )
                ? sh_link_name( $term_id, 'social_' . $slug, $handle )
                : '';
            $out[] = array(
                'network' => $slug,
                'label'   => ( '' !== $handle && '' !== $name ) ? $handle . ' (' . $name . ')' : $handle,
                'handle'  => $handle,
                'icon'    => (string) ( $def['icon'] ?? 'dashicons-admin-links' ),
            );
            continue;
        }
        $type = function_exists( 'tc_get_link_type' ) ? tc_get_link_type( $slug ) : null;
        if ( $type ) {
            // WordPress author profile: show "Name @ Site".
            $handle = (string) ( $links[ $slug ] ?? '' );
            $name   = function_exists( 'sh_link_name' ) ? sh_link_name( $term_id, $slug, $handle ) : '';
            $site   = function_exists( 'sh_author_site_name' )
                ? sh_author_site_name( $slug, (string) ( $type['label'] ?? $slug ) )
                : (string) ( $type['label'] ?? $slug );
            $out[]  = array(
                'network' => $slug,
                'label'   => ( '' !== $name ? $name : $handle ) . ' @ ' . $site,
                'handle'  => $handle,
                'icon'    => (string) ( $type['icon'] ?? 'dashicons-admin-users' ),
            );
        }
    }

    return $out;
}

/**
 * GET /handles — read a mention term's per-network handles.
 *
 * @since 1.0.0
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function sh_rest_get_handles( WP_REST_Request $request ) {
    $term_id = (int) $request->get_param( 'term_id' );
    $term    = get_term( $term_id, 'mention' );
    if ( ! ( $term instanceof WP_Term ) ) {
        return new WP_Error( 'sh_bad_term', __( 'Invalid mention term.', 'social-hashtags-mentions' ), array( 'status' => 404 ) );
    }
    return rest_ensure_response( sh_collect_handles( $term_id ) );
}
