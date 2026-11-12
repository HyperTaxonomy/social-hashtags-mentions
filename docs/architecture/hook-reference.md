---
title: Hook Reference
parent: Architecture
nav_order: 3
---

# Hook Reference

Where Social Hashtags & Mentions registers its callbacks, and the hooks it fires for other code. This page is generated from the code. Hook names that are built at runtime are shown as the PHP expression that builds them. For how to use the hooks Social Hashtags & Mentions fires, see the [Developer Reference](../developer-reference.html).

## Callbacks registered

### Plugin root

**`social-hashtags-mentions.php`**

| Hook | Callback | Priority |
|---|---|---|
| `init` | `*(anonymous function)*` | 10 |
| `plugins_loaded` | `*(anonymous function)*` | 20 |
| `admin_notices` | `*(anonymous function)*` | 10 |

### Includes

**`includes/admin.php`**

| Hook | Callback | Priority |
|---|---|---|
| `admin_menu` | `sh_admin_menu` | 10 |
| `admin_init` | `sh_admin_handle_save` | 10 |
| `admin_enqueue_scripts` | `sh_admin_enqueue` | 10 |
| `'plugin_action_links_' . SH_PLUGIN_BASENAME` | `sh_plugin_action_links` | 10 |

**`includes/blogosphere.php`**

| Hook | Callback | Priority |
|---|---|---|
| `loop_end` | `sh_blogosphere_auto_append` | 10 |

**`includes/bootstrap.php`**

| Hook | Callback | Priority |
|---|---|---|
| `init` | `sh_register_taxonomies` | 10 |

**`includes/editor.php`**

| Hook | Callback | Priority |
|---|---|---|
| `enqueue_block_editor_assets` | `sh_enqueue_editor_assets` | 10 |

**`includes/link-types.php`**

| Hook | Callback | Priority |
|---|---|---|
| `tc_link_types` | `sh_register_link_types` | 10 |
| `tc_link_uri` | `sh_filter_link_uri` | 10 |
| `tc_enabled_taxonomies` | `sh_enable_tc_taxonomies` | 10 |
| `tc_suggest_config` | `sh_suggest_config` | 10 |
| `tc_parse_suggest_response` | `sh_parse_suggest_response` | 10 |
| `tc_taxonomy_peer_authors` | `sh_mention_maps_to_peer_authors` | 10 |
| `tc_link_value` | `sh_sanitize_handle` | 10 |

**`includes/render.php`**

| Hook | Callback | Priority |
|---|---|---|
| `the_content` | `sh_render_tokens` | 20 |
| `wp_enqueue_scripts` | `sh_enqueue_frontend_assets` | 10 |

**`includes/rest.php`**

| Hook | Callback | Priority |
|---|---|---|
| `rest_api_init` | `sh_register_rest_routes` | 10 |

**`includes/term-columns.php`**

| Hook | Callback | Priority |
|---|---|---|
| `"manage_edit-{$sh_tax}_columns"` | `*(anonymous function)*` | 100 |
| `"manage_{$sh_tax}_custom_column"` | `sh_render_term_column` | 100 |
| `admin_head` | `sh_term_column_css` | 10 |

**`includes/term-sync.php`**

| Hook | Callback | Priority |
|---|---|---|
| `save_post` | `sh_sync_post_terms` | 20 |
| `wp_kses_allowed_html` | `sh_kses_allowed_html` | 10 |

## Hooks fired

Actions and filters Social Hashtags & Mentions fires for other code to use.

| Hook | Type | File | Description |
|---|---|---|---|
| `sh_blogosphere_auto_append` | filter | `includes/blogosphere.php` | Filters whether the Blogosphere section is auto-appended to hashtag archives. |
| `sh_blogosphere_cache_ttl` | filter | `includes/blogosphere.php` | Filters the cache TTL (seconds) for the Blogosphere post pool. |
| `sh_blogosphere_fetch_size` | filter | `includes/blogosphere.php` | — |
| `sh_network_registry` | filter | `includes/networks.php` | Filters the social network registry. |
| `sh_token_post_types` | filter | `includes/taxonomies.php` | Filters the post types that support inline hashtags and mentions. |
