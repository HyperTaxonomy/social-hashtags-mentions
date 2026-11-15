---
title: Developer Reference
nav_order: 3
---

# Developer Reference

For developers who read or extend TermConnect Social: its taxonomies, token markup, filters, REST endpoints and TermConnect integration.

## Taxonomies

Two flat, public, REST-enabled taxonomies are registered for the post types returned by the `tcs_token_post_types` filter (default: `post`):

- `hashtag` — primary.
- `mention` — secondary.

Both register with `meta_box_cb => false`: assignments are managed from inline tokens, not a meta box.

## Token markup

The editor inserts each token as an anchor the server understands:

```html
<a class="tcs-hashtag" data-tcs-tax="hashtag" data-tcs-term="12"
   data-tcs-networks="*" href="…">#example</a>
```

| Attribute | Meaning |
|-----------|---------|
| `class` | `tcs-hashtag` or `tcs-mention` |
| `data-tcs-tax` | `hashtag` or `mention` |
| `data-tcs-term` | term ID, or empty for a not-yet-created term |
| `data-tcs-name` | label for a not-yet-created term (used to get-or-create on save) |
| `data-tcs-networks` | `*` (all enabled) or a comma-separated subset |
| `data-tcs-user-id` | mention only: the WordPress user ID, when the token was created by picking a local user from the `@` autocomplete (empty otherwise) |

On `save_post`, the plugin reconciles each post's `hashtag`/`mention` terms to exactly the tokens present (creating terms by name when needed). On `the_content`, it rewrites tokens into the final links and selection panes.

The plugin registers a `wp_kses_allowed_html` filter (`tcs_kses_allowed_html()` in `includes/term-sync.php`) to allow the `data-tcs-*` attributes on `<a>` tags for the `'post'` kses context. Without it, `wp_insert_post()`/`wp_update_post()` strips these attributes from `post_content` for any user without the `unfiltered_html` capability — i.e. every role except Administrator on a single-site install, and effectively everyone on multisite.

### Mention identity

A mention created by picking a **local user** from the `@` autocomplete (rather than an existing term or free text) carries that user's ID in `data-tcs-user-id`. Because post content can be hand-edited, the ID is honored only when it belongs to an existing user whose display name, nicename, or login matches the mention text (`tcs_user_matches_mention_name()`); otherwise the mention is resolved by name only. On save, `tcs_get_or_create_term()` resolves/creates the term by that identity:

- If a mention term already linked to that user exists (tracked via the `_tcs_user_id` term meta), it's reused.
- Otherwise, an unlinked term that happens to share the user's display name is claimed for them; a term already linked to a *different* user is left alone and a distinctly-named term is created instead (WordPress itself disallows two terms with the same name in a flat taxonomy), so two different people with the same display name are never silently merged.
- Claiming or creating a term this way also sets its TermConnect "Local site" link to that user's profile automatically (via [`tc_set_term_link()`](https://hypertaxonomy.org/termconnect/developer-reference/term-links-api.html#per-term)), unless one is already set.

A mention picked from the existing-terms list, or typed as free text with no matching local user, is resolved/created by name only.

## Filters

| Filter | Purpose |
|--------|---------|
| `tcs_network_registry` | Add or modify networks. Receives the keyed registry array (`label`, `hashtag_url`, `profile_url`, `supports`, `icon`, `instance`, `internal`). Icon colors are a fixed list for the built-in networks, so a custom network uses the default color. |
| `tcs_token_post_types` | Post types that support inline tokens (default `['post']`). |
| `tcs_blogosphere_auto_append` | Whether to auto-append the [Blogosphere section](user-guide.html#the-blogosphere-section) to hashtag archives. |
| `tcs_blogosphere_cache_ttl` | TTL (seconds) for the cached Blogosphere post pool. |
| `tcs_blogosphere_fetch_size` | Posts requested per peer for the Blogosphere pool. |

## REST endpoints

Namespace `termconnect-social/v1` (all require the `edit_posts` capability):

| Route | Method | Purpose |
|-------|--------|---------|
| `/terms?tax=&q=` | GET | Search hashtag/mention terms. |
| `/users?q=` | GET | Search local users for `@` autocomplete. |
| `/handles?term_id=` | GET | Read a mention term's per-network handles (drives the editor's per-instance profile checkboxes). |

Mention handles are *set* on the term's edit screen through TermConnect's [External Links panel](https://hypertaxonomy.org/termconnect/user-guide/concepts.html#term-equivalence-and-external-identifiers), not via REST — so there is no write endpoint here. Terms are created on post save by the token sync, so there is no create endpoint either.

## Uninstall

The **Delete data on uninstall** setting (`delete_on_uninstall` in the `tcs_settings` option; see [Data Storage](architecture/data-storage.html#option)) decides whether uninstalling also removes `_tc_link_social_*`, `_tc_link_local_site` and `_tcs_user_id` term meta. Hashtag and mention terms are always kept.

## TermConnect integration

TermConnect Social is a TermConnect companion and relies on these TermConnect extension points (see the [TermConnect developer reference](https://hypertaxonomy.org/termconnect/developer-reference/)):

- **[`tc_link_types`](https://hypertaxonomy.org/termconnect/developer-reference/actions-filters.html#register-link-types)** — registers one `social_{network}` link type per network, flagged `provider => 'termconnect-social'` so they stay out of TermConnect's Peer Sites admin (managed here instead) while remaining active for REST and matching.
- **[`tc_link_uri`](https://hypertaxonomy.org/termconnect/developer-reference/actions-filters.html#link-url)** — builds each network's URL per taxonomy (hashtag page vs. profile), including the Mastodon `user@instance` → `https://instance/@user` transform.
- **[`tc_enabled_taxonomies`](https://hypertaxonomy.org/termconnect/developer-reference/actions-filters.html#enabled-taxonomies)** — opts `hashtag` and `mention` into TermConnect so they get the `tc_links` REST field and `htax_term_uri` cross-site matching. This is what gives mention terms identity matching by shared handle URI across peers.
- **[`tc_suggest_config` / `tc_parse_suggest_response`](https://hypertaxonomy.org/termconnect/developer-reference/actions-filters.html#identifier-autocomplete)** — for networks with a public, credential-free typeahead API (currently GitHub and Bluesky), registers a profile-search suggest endpoint so the term-edit identifier field offers autocomplete. The configs are flagged `free_text` (suggestions are advisory — a known handle that does not surface in search is still accepted) and gated to the `mention` taxonomy.
- **[`tc_taxonomy_peer_authors`](https://hypertaxonomy.org/termconnect/developer-reference/actions-filters.html#people-taxonomies)** — declares `mention` a "people" taxonomy, so a WordPress-peer link on a mention term autocompletes the peer's **authors** (not its terms) and resolves to the peer's `/author/{nicename}/` archive. A built-in, non-configurable **"Local site"** peer (a `local_site` link type pointing at this site's own origin) maps a mention to a **local** author the same way.
- **[`tc_link_value`](https://hypertaxonomy.org/termconnect/developer-reference/actions-filters.html#normalize-identifiers-before-saving)** — basic validation for free-form handles on `social_{network}` types: trims, reduces a pasted profile URL to its last segment (except instance handles like Mastodon), strips a leading `@`, and rejects values containing whitespace.

Mention handles are stored as TermConnect identifiers (`_tc_link_social_{network}` term meta) via `tc_set_term_link()`, so they flow into `_tc_uris` and the `tc_links` REST field, and move with the term when HyperTaxonomy [merges terms](https://hypertaxonomy.org/hypertaxonomy/user-guide/merge-convert.html#merge-terms).
