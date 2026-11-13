---
title: Hook Reference
parent: Architecture
nav_order: 3
---

# Hook Reference

The hook registrations in Social Hashtags & Mentions that you can't guess from a callback's name: load order, priorities chosen to run after other code, and hooks registered per taxonomy. Routine registrations (scripts, the settings screen, REST routes, notices) are left out; search the code for `add_action( 'hook_name'` to find any of them.

The filters Social Hashtags & Mentions applies for other code, and the TermConnect filters it answers, are documented in the [Developer Reference](../developer-reference.html#filters) (see also its [TermConnect integration](../developer-reference.html#termconnect-integration) section).

## Loading

The main file loads `includes/bootstrap.php` on `plugins_loaded` at priority 20, only when TermConnect is active (checked by its functions or, before they load, by the active plugins list); otherwise it shows an admin notice. TermConnect loads its own code on `plugins_loaded` at priority 20 as well, often after this, which is safe because the bootstrap only registers hooks and doesn't call TermConnect functions while loading.

## Priorities

| Hook | Callback | Priority | Why |
|---|---|---|---|
| `the_content` | `sh_render_tokens` | 20 | Runs on the finished HTML, after `wpautop` (10) and shortcodes (11) |
| `save_post` | `sh_sync_post_terms` | 20 | After callbacks at the default priority |
| `manage_edit-{$taxonomy}_columns`, `manage_{$taxonomy}_custom_column` | **Networks** column (hashtags) and **Profiles** column (mentions) | 100 | Runs after TermConnect adds its External Links column (99), so the new column can be placed right after it |

The term list columns are registered for each of the two taxonomies when the plugin loads, not on `current_screen`: the **Add New Term** form renders the new row in an AJAX request where the current screen isn't the term list, and a screen check there would leave the new row's cell blank until the page is reloaded.
