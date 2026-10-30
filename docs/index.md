---
title: Home
nav_order: 1
---

# Social Hashtags & Mentions

Write `#hashtags` and `@mentions` inline in the block editor and let them become real taxonomy terms — assigned to your posts automatically from the tokens in your content, with no taxonomy box to maintain. Each token links out to the social networks you choose: hashtags to a network's hashtag page, mentions to a person's profile. Enable and order networks once; the choice applies everywhere, and the front end renders the right links at display time.

## Part of the HyperTaxonomy suite

One of five interoperable WordPress plugins for richer, connected taxonomies. Each works on its own; together they share a common term model. Learn more at [hypertaxonomy.org](https://hypertaxonomy.org).

- **[HyperTaxonomy](https://hypertaxonomy.org/hypertaxonomy/)** — the core: term groups, inclusions, synonyms, redirects, icons, relevance-scored search, and enhanced archives.
- **[TermConnect](https://hypertaxonomy.org/termconnect/)** — link terms to knowledge-base identifiers (Wikidata, GeoNames, LCSH…) and peer sites; delegated identity, shared vocabularies, and cross-site related content.
- **Social Hashtags & Mentions** (this plugin) — inline #hashtags and @mentions as taxonomy terms, linked to the social networks you enable.
- **[As the Crow Flies](https://hypertaxonomy.org/as-the-crow-flies/)** — attach physical locations to terms and filter content by distance/proximity.
- **[WP-SKOS](https://hypertaxonomy.org/wp-skos/)** — expose your taxonomies as SKOS linked data via a Skosmos-compatible REST API.

Social Hashtags & Mentions is **built on TermConnect**, which it requires: TermConnect provides the link-type registry, identifier storage and cross-site matching, and the peer-fetch API this plugin builds on.

## How it works

- **Two flat taxonomies** — `hashtag` (primary) and `mention` (secondary). A post's terms are reconciled from its inline tokens on every save; there is no taxonomy meta box.
- **Inline tokens** — type `#` or `@` in any rich-text field to autocomplete existing terms and local users. A small panel on each token picks which networks it links to (hashtags) or which of the person's configured profiles to show (mentions); mention handles are set once on the term's edit screen.
- **Smart links at render time** — one enabled network links straight to it; several open a small pane of choices; none links to the local archive (or renders as plain text). A mention with a single profile renders inline as the network icon + handle.
- **Network ordering** — enable and drag-to-order networks in *Settings → Social Hashtags & Mentions*. The order drives the editor and the front end; disabling a network removes its links everywhere at once.
- **Blogosphere** — a hashtag-only, built-in option that aggregates post summaries sharing a hashtag across your WordPress peer sites (via TermConnect) onto the hashtag archive, each linking out to the peer.
- **Federation for free** — because handles are stored as TermConnect identifiers, mentions match across peer sites by their shared profile URI even when term slugs and labels differ.

## Documentation map

- **[Usage](usage.html)** — the user-facing guide: settings and network ordering, the inline editor tokens, hashtag vs. mention rendering, mention handles, and the Blogosphere section and `[social_blogosphere]` shortcode.
- **[Developer Reference](developer-reference.html)** — the network registry filter, REST endpoints, the TermConnect integration (link types, `tc_link_uri`, `tc_enabled_taxonomies`, peer author search), and how token markup maps to taxonomy terms.

## Requirements

| Item | Version |
|------|---------|
| WordPress | 6.6+ |
| PHP | 7.4+ |
| TermConnect | required |
| HyperTaxonomy | optional (relevance search, relationships) |
| WP-SKOS | optional (SKOS concept output) |

## Installation

1. Install and activate **[TermConnect](https://hypertaxonomy.org/termconnect/)**.
2. Install from the [WordPress.org plugin directory](https://wordpress.org/plugins/social-hashtags-mentions/), or download the zip from [GitHub Releases](https://github.com/HyperTaxonomy/social-hashtags-mentions/releases) and upload via **Plugins → Add New → Upload Plugin**.
3. Activate, then go to **Settings → Social Hashtags & Mentions** to enable and order the networks you want.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
