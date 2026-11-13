---
title: Function Reference
parent: Architecture
nav_order: 2
---

# Function Reference
{: .no_toc }

Social Hashtags & Mentions's functions and public and protected methods, by file, with each one's docblock summary. Private methods aren't listed. This page is generated from the code. For what other plugins and themes should call, see the [Developer Reference](../developer-reference.html); for hook registrations, see the [Hook Reference](hook-reference.html).

## Table of contents
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## Plugin root

### `social-hashtags-mentions.php`

Main plugin file.

- `sh_activate()` — Activation: register taxonomies then flush rewrite rules so archives resolve.
- `sh_deactivate()` — Deactivation: drop the stored rewrite rules so the taxonomy archive routes are removed.
- `sh_termconnect_active()` — Whether TermConnect is active.

### `uninstall.php`

Uninstall cleanup for Social Hashtags & Mentions.

- `sh_uninstall_cleanup()` — Clean up the current site.

## Includes

### `includes/admin.php`

Admin settings page: Settings → Social Hashtags & Mentions.

- `sh_plugin_action_links( array $links )` — Adds a Settings link to the plugin's row on the Plugins screen.
- `sh_admin_menu()` — Registers the settings page.
- `sh_admin_enqueue( string $hook )` — Enqueues the sortable script on the settings page only.
- `sh_admin_handle_save()` — Handles the settings form submission.
- `sh_admin_render()` — Renders the settings page.

### `includes/blogosphere.php`

Blogosphere: aggregated peer-post summaries for a hashtag.

- `sh_blogosphere_enabled()` — Whether the Blogosphere pseudo-network is enabled.
- `sh_blogosphere_auto_append_enabled()` — Whether the Blogosphere section should auto-append to hashtag archives.
- `sh_blogosphere_peers()` — Returns the configured WordPress peers as [ slug, label, origin ] tuples.
- `sh_blogosphere_pool( string $hashtag_slug, int $fetch_size = 30 )` — Fetches and merges the peer-post pool for a hashtag slug.
- `sh_render_blogosphere( array $args = array() )` — Renders the Blogosphere section for a hashtag.
- `sh_blogosphere_shortcode( $atts )` — Shortcode handler for [social_blogosphere].
- `sh_blogosphere_auto_append( $query )` — Auto-appends the Blogosphere section after the main loop on a hashtag archive.

### `includes/editor.php`

Block editor integration: enqueues the inline-token script and localizes the data it needs (REST root + nonce, ordered enabled networks).

- `sh_enqueue_editor_assets()` — Enqueues and localizes the editor script for supported post types.
- `sh_editor_networks()` — Returns the ordered enabled networks for the editor.

### `includes/link-types.php`

TermConnect link-type integration.

- `sh_sanitize_handle( $id, string $slug )` — Basic validation/normalization for free-form social handles.
- `sh_mention_maps_to_peer_authors( $is_author, string $taxonomy )` — Maps the mention taxonomy's WordPress-peer links to peer authors.
- `sh_enable_tc_taxonomies( $taxonomies )` — Opts the hashtag and mention taxonomies into TermConnect.
- `sh_register_link_types( array $types )` — Registers one `social_{slug}` link type per external network.
- `sh_filter_link_uri( ?string $uri, string $slug, string $id, array $type, string $taxonomy )` — Builds the per-network, per-taxonomy URL for `social_{slug}` link types.
- `sh_suggest_config( $config, string $slug, $type = null, string $taxonomy = '' )` — Provides a profile-search suggest endpoint for networks that expose a public, credential-free typeahead API.
- `sh_parse_suggest_response( array $results, string $body, string $parser )` — Parses suggest responses for this plugin's network parsers into the { value, label } shape TermConnect's autocomplete expects. The stored value is the network handle; the label adds the display name when one is available.

### `includes/networks.php`

Social network registry.

- `sh_get_network_registry()` — Returns the built-in network definitions, keyed by slug.
- `sh_get_network( string $slug )` — Returns a single network definition, or null.
- `sh_network_supports( string $slug, string $taxonomy )` — Whether a network supports a given taxonomy.
- `sh_taxonomies()` — The taxonomies this plugin manages.
- `sh_network_icon_html( string $slug, string $dashicon, string $color = '', string $extra_class = '' )` — Returns the icon markup for a network.
- `sh_network_svg( string $slug )` — Returns the raw bundled SVG markup for a network, or '' if none.
- `sh_network_color( string $slug )` — The official brand color for a network, used to tint its icon in the editor and the front-end panel (admin screens stay monochrome). Returns '' when none.
- `sh_build_network_url( string $slug, string $id, string $taxonomy )` — Builds the external URL for a network + identifier on a given taxonomy.

### `includes/render.php`

Frontend rendering of inline tokens.

- `sh_enqueue_frontend_assets()` — Enqueues the frontend stylesheet for token badges and the Blogosphere section.
- `sh_render_tokens( string $content )` — Augments inline tokens in post content with network badges.
- `sh_render_token_callback( array $match )` — preg_replace_callback handler for a single anchor.
- `sh_render_hashtag_token( string $attrs, string $inner, WP_Term $term, array $selected )` — Renders a hashtag token: direct link (one target), pane (many), or archive/plain (none), per the per-instance ∩ enabled network targets.
- `sh_hashtag_targets( WP_Term $term, array $selected, string $archive )` — Builds the ordered link targets for a hashtag token.
- `sh_resolve_token_term( string $attrs, string $taxonomy )` — Resolves the term referenced by a token anchor (by ID, falling back to name).
- `sh_token_selected_networks( string $attrs, array $universe )` — Returns the ordered list of item slugs to render for a token, intersecting the per-instance selection (data-sh-networks) with the given universe of selectable items. A selection of '' or '*' means "all items in the universe".
- `sh_mention_universe( WP_Term $term )` — The ordered selectable items for a mention token: enabled social networks plus, when the Blogosphere network is enabled, the term's WordPress author profiles (the "Local site" and any peers). Blogosphere itself is the on/off gate, not a selectable item.
- `sh_term_wp_author_slugs( array $links )` — The WordPress author profile link-type slugs set on a term — the "Local site" first, then peer sites — from a tc_get_term_links() map.
- `sh_link_name( int $term_id, string $slug, string $handle )` — The human display name captured for a term's link, or '' when none is available.
- `sh_render_mention_token( string $attrs, string $inner, WP_Term $term, array $selected )` — Renders a mention token, mirroring the hashtag shape: a single enabled profile renders inline as the network icon + handle (replacing the @name); several open a pane of icon + handle rows; none links to the local archive or renders plain (per the setting). A link to the local profile keeps the @name text.
- `sh_mention_targets( WP_Term $term, array $selected )` — Builds the ordered profile targets for a mention token from the selected items.
- `sh_author_site_name( string $slug, string $fallback )` — The site name shown for a WordPress author profile. The non-configurable "Local site" link type carries a generic label (fine in the term editor), so on the front end and in the post editor we substitute this site's actual title; peers already use their admin-given name.
- `sh_mention_label_html( array $t )` — The visible text for a mention target: "Name @ Site" for a WordPress author profile (local or peer), or "handle (Name)" for a social network.
- `sh_set_attr( string $attrs, string $name, string $value )` — Sets or replaces an attribute within an anchor attribute string.
- `sh_set_text_attr( string $attrs, string $name, string $value )` — Sets or replaces a plain (non-URL) attribute within an attribute string.
- `sh_add_class( string $attrs, string $class )` — Adds a class to the class attribute within an attribute string.

### `includes/rest.php`

REST endpoints used by the block editor.

- `sh_rest_can_edit()` — Permission callback: any user who can edit posts.
- `sh_register_rest_routes()` — Registers the REST routes.
- `sh_rest_validate_tax( string $tax )` — Validates a taxonomy param against the plugin's taxonomies.
- `sh_rest_term_payload( WP_Term $term )` — Formats a term for a REST response.
- `sh_rest_search_terms( WP_REST_Request $request )` — GET /terms — search terms.
- `sh_rest_search_users( WP_REST_Request $request )` — GET /users — search local users for @ autocomplete.
- `sh_collect_handles( int $term_id )` — Returns the selectable items for a mention's editor popover: each enabled social network (with the term's stored handle, if any) and — when the Blogosphere network is enabled — each WordPress author profile on the term (the "Local site" and any peers), with the author nicename as its handle.
- `sh_rest_get_handles( WP_REST_Request $request )` — GET /handles — read a mention term's per-network handles.

### `includes/settings.php`

Settings storage.

- `sh_get_settings()` — Returns the full settings array, with the network map normalized against the current registry (back-filling new networks at the end, dropping unknown ones).
- `sh_no_link_mode()` — How to render a token (hashtag or mention) that has no enabled network links.
- `sh_blogosphere_auto_append_setting()` — Whether the Blogosphere section should auto-append to hashtag archives.
- `sh_get_network_states()` — Returns the ordered network map ( slug => enabled ).
- `sh_get_enabled_networks( string $taxonomy = '' )` — Returns the ordered list of enabled network slugs.
- `sh_is_network_enabled( string $slug )` — Whether a network is enabled.
- `sh_save_settings( array $networks, bool $auto_append, string $no_link, bool $delete_on_uninstall )` — Persists the full settings: ordered network map plus display options.

### `includes/taxonomies.php`

Taxonomy registration: hashtag (primary) and mention (secondary).

- `sh_token_post_types()` — Post types that may contain inline hashtags/mentions.
- `sh_register_taxonomies()` — Registers the hashtag and mention taxonomies.

### `includes/term-columns.php`

Term list-table columns for the hashtag and mention taxonomies.

- `sh_position_term_column( array $columns, string $key, string $label )` — Inserts a column the same way TermConnect / As the Crow Flies do.
- `sh_add_term_column( array $columns, string $taxonomy )` — Adds the per-taxonomy column.
- `sh_render_term_column( $output, $column, $term_id )` — Renders a column cell.
- `sh_render_profiles_cell( int $term_id )` — Profiles cell: icons for networks with a stored handle, linking to the profile.
- `sh_render_networks_cell( int $term_id )` — Networks cell: click-through icons to each enabled network's hashtag page.
- `sh_term_column_css()` — Inline styles for the columns (the front-end stylesheet isn't loaded here).

### `includes/term-sync.php`

Automatic term assignment from inline tokens.

- `sh_kses_allowed_html( $tags, $context )` — Whitelists this plugin's data-sh-* token attributes on <a> tags for the 'post' kses context.
- `sh_sync_post_terms( int $post_id, $post )` — Reconciles a post's hashtag/mention terms with the tokens in its content.
- `sh_extract_tokens( string $content )` — Extracts inline tokens from post content.
- `sh_token_attr( string $attrs, string $name )` — Reads a single attribute value from an anchor's attribute string.
- `sh_get_or_create_term( string $name, string $taxonomy, int $user_id = 0 )` — Returns an existing term ID by name, creating the term if necessary.
- `sh_get_term_by_user_id( int $user_id )` — Finds the mention term already linked to a given local user, by stored meta.
- `sh_disambiguate_term_name( string $name, int $user_id )` — Builds a disambiguated term name for a mention that collides, by display name, with a different local user's already-linked term.
- `sh_user_matches_mention_name( int $user_id, string $name )` — Whether a mention token's text names the given user.
- `sh_set_term_user_link( int $term_id, int $user_id )` — Records a mention term's linked local user and, when no "Local site" profile link is already set, wires one up from that user's profile — so a mention created from the @ autocomplete's local-user results is unambiguously tied to that person rather than resolved by display-name text alone.
