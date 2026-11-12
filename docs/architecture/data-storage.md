---
title: Data Storage
parent: Architecture
nav_order: 4
---

# Data Storage

Social Hashtags & Mentions has no tables. It stores its terms in two taxonomies, mention links through TermConnect, and its settings and caches in an option and transients. For what uninstalling removes, see [User Guide → Uninstalling](../user-guide.html#uninstalling).

## Taxonomies

| Taxonomy | Holds |
|---|---|
| `hashtag` | Hashtags, assigned to posts from their `#` tokens. |
| `mention` | Mentioned people, assigned to posts from their `@` tokens. |

## Term meta

| Key | Value |
|---|---|
| `_tc_link_social_{network}` | A mention's handle on a network. Stored through TermConnect, as link type `social_{network}`. |
| `_tc_link_local_site` | The local author a mention represents, as TermConnect link type `local_site`. |
| `_sh_user_id` | The local user a mention belongs to. |

## Option

`sh_settings` holds:

| Key | Value |
|---|---|
| `networks` | Each network and whether it's enabled, in display order. |
| `blogosphere_auto_append` | Whether the Blogosphere section is added to hashtag archives automatically. |
| `hashtag_no_link` | How a token with no enabled network renders: `archive` (links to the local archive) or `plain`. |
| `delete_on_uninstall` | Whether uninstalling also removes mention handles and user links. |

## Transient

`sh_bg_pool_{md5}` holds the posts fetched from peer sites for a hashtag's Blogosphere section.
