<?php
/**
 * Term list-table columns for the hashtag and mention taxonomies.
 *
 *  - mention → "Profiles": the network icons for which a handle is stored on the
 *    term (each links to that profile). Not sortable.
 *  - hashtag → "Networks": click-through icons to each enabled network's hashtag
 *    page for the term's slug. Not sortable — the network set is identical for
 *    every hashtag (URLs are slug-derived), so there is nothing to order by.
 *
 * Column placement mirrors TermConnect and As the Crow Flies: when HyperTaxonomy
 * is active, its htax_insert_column_after() helper positions the column after the
 * TermConnect / HyperTaxonomy columns; otherwise it is inserted before the posts
 * count column.
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register the column filters per taxonomy at load time — NOT on current_screen.
// The inline "Add New Term" flow renders the new row via the add-tag AJAX request
// (wp_ajax_add_tag), where the current screen is not the edit-tags screen; a
// current_screen-gated registration would miss it and the new row's cell would be
// blank until a full page reload. The taxonomy is bound per filter, so the
// callbacks don't depend on get_current_screen().
foreach ( sh_taxonomies() as $sh_tax ) {
    add_filter(
        "manage_edit-{$sh_tax}_columns",
        static function ( $columns ) use ( $sh_tax ) {
            return sh_add_term_column( (array) $columns, $sh_tax );
        },
        100
    );
    add_filter( "manage_{$sh_tax}_custom_column", 'sh_render_term_column', 100, 3 );
}
add_action( 'admin_head', 'sh_term_column_css' );

/**
 * Inserts a column the same way TermConnect / As the Crow Flies do.
 *
 * @since 1.0.0
 * @param string[] $columns Existing columns.
 * @param string   $key     Column key.
 * @param string   $label   Column label.
 * @return string[]
 */
function sh_position_term_column( array $columns, string $key, string $label ): array {
    if ( function_exists( 'htax_insert_column_after' ) ) {
        $after = array_key_exists( 'tc_ext_links', $columns )
            ? 'tc_ext_links'
            : ( array_key_exists( 'htax_hidden', $columns ) ? 'htax_hidden' : '' );
        if ( '' !== $after ) {
            return htax_insert_column_after( $columns, $after, $key, $label );
        }
    }

    // Fallback: place it just before the posts (count) column, else append.
    $out = array();
    foreach ( $columns as $k => $v ) {
        if ( 'posts' === $k ) {
            $out[ $key ] = $label;
        }
        $out[ $k ] = $v;
    }
    if ( ! isset( $out[ $key ] ) ) {
        $out[ $key ] = $label;
    }
    return $out;
}

/**
 * Adds the per-taxonomy column.
 *
 * @since 1.0.0
 * @param string[] $columns  Columns.
 * @param string   $taxonomy Taxonomy slug (bound per filter at registration).
 * @return string[]
 */
function sh_add_term_column( array $columns, string $taxonomy ): array {
    if ( 'mention' === $taxonomy ) {
        return sh_position_term_column( $columns, 'sh_profiles', __( 'Profiles', 'social-hashtags-mentions' ) );
    }
    if ( 'hashtag' === $taxonomy ) {
        return sh_position_term_column( $columns, 'sh_networks', __( 'Networks', 'social-hashtags-mentions' ) );
    }
    return $columns;
}

/**
 * Renders a column cell.
 *
 * @since 1.0.0
 * @param string $output  Existing output.
 * @param string $column  Column key.
 * @param int    $term_id Term ID.
 * @return string
 */
function sh_render_term_column( $output, $column, $term_id ) {
    if ( 'sh_profiles' === $column ) {
        return sh_render_profiles_cell( (int) $term_id );
    }
    if ( 'sh_networks' === $column ) {
        return sh_render_networks_cell( (int) $term_id );
    }
    return $output;
}

/**
 * Profiles cell: icons for networks with a stored handle, linking to the profile.
 *
 * @since 1.0.0
 * @param int $term_id Term ID.
 * @return string
 */
function sh_render_profiles_cell( int $term_id ): string {
    $links = function_exists( 'tc_get_term_links' ) ? tc_get_term_links( $term_id ) : array();

    $items = array();
    foreach ( sh_get_enabled_networks( 'mention' ) as $slug ) {
        $handle = (string) ( $links[ 'social_' . $slug ] ?? '' );
        if ( '' === $handle ) {
            continue;
        }
        $def  = sh_get_network( $slug );
        if ( ! $def ) {
            continue;
        }
        $icon  = sh_network_icon_html( $slug, $def['icon'] ?? 'dashicons-admin-links' );
        $title = $def['label'] . ': ' . $handle;
        $url   = sh_build_network_url( $slug, $handle, 'mention' );
        if ( null !== $url && '' !== $url ) {
            $items[] = '<a class="sh-col-icon" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow" title="' . esc_attr( $title ) . '">' . $icon . '</a>';
        } else {
            $items[] = '<span class="sh-col-icon" title="' . esc_attr( $title ) . '">' . $icon . '</span>';
        }
    }

    if ( empty( $items ) ) {
        $edit = get_edit_term_link( $term_id );
        return '<a class="sh-col-add" href="' . esc_url( $edit ) . '#tc-external-links" aria-label="' . esc_attr__( 'Add profiles', 'social-hashtags-mentions' ) . '">+</a>';
    }

    return '<span class="sh-col-icons">' . implode( '', $items ) . '</span>';
}

/**
 * Networks cell: click-through icons to each enabled network's hashtag page.
 *
 * @since 1.0.0
 * @param int $term_id Term ID.
 * @return string
 */
function sh_render_networks_cell( int $term_id ): string {
    $term = get_term( $term_id );
    if ( ! ( $term instanceof WP_Term ) ) {
        return '';
    }

    $items = array();
    foreach ( sh_get_enabled_networks( 'hashtag' ) as $slug ) {
        $def = sh_get_network( $slug );
        if ( ! $def ) {
            continue;
        }
        if ( ! empty( $def['internal'] ) ) {
            $url      = get_term_link( $term );
            $url      = is_wp_error( $url ) ? '' : $url;
            $external = false;
        } else {
            $url      = (string) sh_build_network_url( $slug, $term->slug, 'hashtag' );
            $external = true;
        }
        if ( '' === $url ) {
            continue;
        }
        $icon = sh_network_icon_html( $slug, $def['icon'] ?? 'dashicons-admin-links' );
        $rel  = $external ? ' target="_blank" rel="noopener nofollow"' : '';
        $items[] = '<a class="sh-col-icon" href="' . esc_url( $url ) . '"' . $rel . ' title="' . esc_attr( $def['label'] ) . '">' . $icon . '</a>';
    }

    if ( empty( $items ) ) {
        return '<span class="sh-col-empty">&mdash;</span>';
    }

    return '<span class="sh-col-icons">' . implode( '', $items ) . '</span>';
}

/**
 * Inline styles for the columns (the front-end stylesheet isn't loaded here).
 *
 * @since 1.0.0
 */
function sh_term_column_css(): void {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! ( $screen instanceof WP_Screen ) || 'edit-tags' !== $screen->base
        || ! in_array( (string) $screen->taxonomy, sh_taxonomies(), true ) ) {
        return;
    }
    ?>
    <style>
    .column-sh_profiles, .column-sh_networks { width: 140px; }
    .sh-col-icons { display: inline-flex; flex-wrap: wrap; gap: 4px; align-items: center; }
    .sh-col-icon { color: #50575e; text-decoration: none; line-height: 1; display: inline-flex; }
    .sh-col-icon:hover, .sh-col-icon:focus { color: #2271b1; }
    .sh-col-icon .sh-icon { display: inline-flex; width: 18px; height: 18px; }
    .sh-col-icon .sh-icon svg { width: 100%; height: 100%; fill: currentColor; }
    .sh-col-icon .dashicons { font-size: 18px; width: 18px; height: 18px; }
    .sh-col-add { color: #a7aaad; text-decoration: none; font-size: 18px; line-height: 1; }
    .sh-col-add:hover { color: #2271b1; }
    .sh-col-empty { color: #a7aaad; }
    </style>
    <?php
}
