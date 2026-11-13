---
title: Function Reference
parent: Architecture
nav_order: 2
---

# Function Reference

Functions and methods of Social Hashtags & Mentions by file, with each one's docblock summary, chosen by one rule used across the suite's plugins. Listed: the public API (documented in the [Developer Reference](../developer-reference.html)) and anything called from another file, which is what contributors reuse. Not listed: hook callbacks, which the [Hook Reference](hook-reference.html) names next to their hooks; screen wiring (rendering, enqueueing, menu and column registration, constructors); overrides of a base class's method; and anything used only inside its own file or class, which is best read in the code. This page is generated from the code.

## Includes

### Network registry (`includes/networks.php`)

Social network registry.

- `sh_get_network_registry()` — Returns the built-in network definitions, keyed by slug.
- `sh_get_network( string $slug )` — Returns a single network definition, or null.
- `sh_network_supports( string $slug, string $taxonomy )` — Whether a network supports a given taxonomy.
- `sh_taxonomies()` — The taxonomies this plugin manages.
- `sh_network_icon_html( string $slug, string $dashicon, string $color = '', string $extra_class = '' )` — Returns the icon markup for a network.
- `sh_network_svg( string $slug )` — Returns the raw bundled SVG markup for a network, or '' if none.
- `sh_network_color( string $slug )` — The official brand color for a network, used to tint its icon in the editor and the front-end panel (admin screens stay monochrome). Returns '' when none.
- `sh_build_network_url( string $slug, string $id, string $taxonomy )` — Builds the external URL for a network + identifier on a given taxonomy.

### Token rendering (`includes/render.php`)

Frontend rendering of inline tokens.

- `sh_mention_universe( WP_Term $term )` — The ordered selectable items for a mention token: enabled social networks plus, when the Blogosphere network is enabled, the term's WordPress author profiles (the "Local site" and any peers). Blogosphere itself is the on/off gate, not a selectable item.
- `sh_link_name( int $term_id, string $slug, string $handle )` — The human display name captured for a term's link, or '' when none is available.
- `sh_author_site_name( string $slug, string $fallback )` — The site name shown for a WordPress author profile. The non-configurable "Local site" link type carries a generic label (fine in the term editor), so on the front end and in the post editor we substitute this site's actual title; peers already use their admin-given name.

### Settings storage (`includes/settings.php`)

Settings storage.

- `sh_get_settings()` — Returns the full settings array, with the network map normalized against the current registry (back-filling new networks at the end, dropping unknown ones).
- `sh_no_link_mode()` — How to render a token (hashtag or mention) that has no enabled network links.
- `sh_blogosphere_auto_append_setting()` — Whether the Blogosphere section should auto-append to hashtag archives.
- `sh_get_network_states()` — Returns the ordered network map ( slug => enabled ).
- `sh_get_enabled_networks( string $taxonomy = '' )` — Returns the ordered list of enabled network slugs.
- `sh_is_network_enabled( string $slug )` — Whether a network is enabled.
- `sh_save_settings( array $networks, bool $auto_append, string $no_link, bool $delete_on_uninstall )` — Persists the full settings: ordered network map plus display options.

### Taxonomies (`includes/taxonomies.php`)

Taxonomy registration: hashtag (primary) and mention (secondary).

- `sh_token_post_types()` — Post types that may contain inline hashtags/mentions.
- `sh_register_taxonomies()` — Registers the hashtag and mention taxonomies.

### Automatic term assignment (`includes/term-sync.php`)

Automatic term assignment from inline tokens.

- `sh_kses_allowed_html( $tags, $context )` — Whitelists this plugin's data-sh-* token attributes on <a> tags for the 'post' kses context.
- `sh_token_attr( string $attrs, string $name )` — Reads a single attribute value from an anchor's attribute string.
- `sh_get_or_create_term( string $name, string $taxonomy, int $user_id = 0 )` — Returns an existing term ID by name, creating the term if necessary.
- `sh_user_matches_mention_name( int $user_id, string $name )` — Whether a mention token's text names the given user.
