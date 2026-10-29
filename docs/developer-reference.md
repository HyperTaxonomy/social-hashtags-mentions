---
title: Developer Reference
nav_order: 3
---

# Developer Reference

## Taxonomies

Two flat, public, REST-enabled taxonomies are registered for the post types returned by the `sh_token_post_types` filter (default: `post`):

- `hashtag` — primary.
- `mention` — secondary.

Both register with `meta_box_cb => false`: assignments are managed from inline tokens, not a meta box.

## Token markup

The editor inserts each token as an anchor the server understands:

```html
<a class="sh-hashtag" data-sh-tax="hashtag" data-sh-term="12"
   data-sh-networks="*" href="…">#example</a>
```

| Attribute | Meaning |
|-----------|---------|
| `class` | `sh-hashtag` or `sh-mention` |
| `data-sh-tax` | `hashtag` or `mention` |
| `data-sh-term` | term ID, or empty for a not-yet-created term |
| `data-sh-name` | label for a not-yet-created term (used to get-or-create on save) |
| `data-sh-networks` | `*` (all enabled) or a comma-separated subset |

On `save_post`, the plugin reconciles each post's `hashtag`/`mention` terms to exactly the tokens present (creating terms by name when needed). On `the_content`, it rewrites tokens into the final links and selection panes.

## Filters

| Filter | Purpose |
|--------|---------|
| `sh_network_registry` | Add or modify networks. Receives the keyed registry array (`label`, `hashtag_url`, `profile_url`, `supports`, `icon`, `instance`, `internal`). |
| `sh_token_post_types` | Post types that support inline tokens (default `['post']`). |
| `sh_blogosphere_auto_append` | Whether to auto-append the Blogosphere section to hashtag archives. |
| `sh_blogosphere_cache_ttl` | TTL (seconds) for the cached Blogosphere post pool. |
| `sh_blogosphere_fetch_size` | Posts requested per peer for the Blogosphere pool. |

## REST endpoints

Namespace `social-hashtags/v1` (all require the `edit_posts` capability):

| Route | Method | Purpose |
|-------|--------|---------|
| `/terms?tax=&q=` | GET | Search hashtag/mention terms. |
| `/users?q=` | GET | Search local users for `@` autocomplete. |
| `/handles?term_id=` | GET | Read a mention term's per-network handles (drives the editor's per-instance profile checkboxes). |

Mention handles are *set* on the term's edit screen through TermConnect's External Links panel, not via REST — so there is no write endpoint here. Terms are created on post save by the token sync, so there is no create endpoint either.

## Shortcode

`[social_blogosphere hashtag="" per_page="10"]` — renders the aggregated peer-post summaries for a hashtag (defaults to the current hashtag archive term).

## TermConnect integration

Social Hashtags & Mentions is a TermConnect companion and relies on these TermConnect extension points (see the [TermConnect developer reference](https://hypertaxonomy.org/termconnect/developer-reference/)):

- **`tc_link_types`** — registers one `social_{network}` link type per network, flagged `provider => 'social-hashtags-mentions'` so they stay out of TermConnect's Peer Sites admin (managed here instead) while remaining active for REST and matching.
- **`tc_link_uri`** — builds each network's URL per taxonomy (hashtag page vs. profile), including the Mastodon `user@instance` → `https://instance/@user` transform.
- **`tc_enabled_taxonomies`** — opts `hashtag` and `mention` into TermConnect so they get the `tc_links` REST field and `htax_term_uri` cross-site matching. This is what gives mention terms identity matching by shared handle URI across peers.
- **`tc_suggest_config` / `tc_parse_suggest_response`** — for networks with a public, credential-free typeahead API (currently GitHub and Bluesky), registers a profile-search suggest endpoint so the term-edit identifier field offers autocomplete. The configs are flagged `free_text` (suggestions are advisory — a known handle that does not surface in search is still accepted) and gated to the `mention` taxonomy.
- **`tc_taxonomy_peer_authors`** — declares `mention` a "people" taxonomy, so a WordPress-peer link on a mention term autocompletes the peer's **authors** (not its terms) and resolves to the peer's `/author/{nicename}/` archive. A built-in, non-configurable **"Local site"** peer (a `local_site` link type pointing at this site's own origin) maps a mention to a **local** author the same way.
- **`tc_link_value`** — basic validation for free-form handles on `social_{network}` types: trims, reduces a pasted profile URL to its last segment (except instance handles like Mastodon), strips a leading `@`, and rejects values containing whitespace.

Mention handles are stored as TermConnect identifiers (`_tc_link_social_{network}` term meta) via `tc_set_term_link()`, so they flow into `_tc_uris`, the `tc_links` REST field, and TermConnect's term-merge migration for free.
