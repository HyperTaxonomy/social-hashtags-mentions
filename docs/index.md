---
title: Home
nav_order: 1
---

# Social Hashtags & Mentions

*Hashtags unleashed*
{: .fs-6 .fw-300 }

Write `#hashtags` and `@mentions` inline in the block editor and let them become real taxonomy terms — assigned to your posts automatically from the tokens in your content, with no taxonomy box to maintain. Each token links out to the social networks you choose: hashtags to a network's hashtag page, mentions to a person's profile. Enable and order networks once; the choice applies everywhere, and the front end renders the right links at display time.

Part of the [HyperTaxonomy Suite](https://hypertaxonomy.org) of interoperable WordPress taxonomy plugins. Built on [TermConnect](https://hypertaxonomy.org/termconnect/), which it requires: TermConnect provides the link-type registry, identifier storage and cross-site matching, and the peer-fetch API this plugin builds on.

## How it works

- **Inline tokens become terms** — type `#` or `@` in the editor; on save, each post's `hashtag` and `mention` terms are updated to match its tokens.
- **Links chosen per token** — each token links to the networks you enable, in the order you set, and the front end renders the links at display time.
- **Blogosphere** — hashtag archives can show matching posts from your WordPress peer sites, through TermConnect.
- **Cross-site matching** — mentions match across peer sites running this plugin by the person's profile URI.

[Usage](usage.html) covers each of these in full.

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

## Getting started

1. Install and activate **[TermConnect](https://hypertaxonomy.org/termconnect/)**.
2. Install from the [WordPress.org plugin directory](https://wordpress.org/plugins/social-hashtags-mentions/), or download the zip from [GitHub Releases](https://github.com/HyperTaxonomy/social-hashtags-mentions/releases) and upload via **Plugins → Add New → Upload Plugin**.
3. Activate, then go to **Settings → Social Hashtags & Mentions** to enable and order the networks you want.
4. In a post, type `#` followed by a word and pick or create the hashtag. Save, and the post gets that hashtag term; view the post to see the token's links.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
