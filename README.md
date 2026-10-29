# Social Hashtags & Mentions

Inline `#hashtags` and `@mentions` as taxonomy terms, with per-instance links to the social networks you enable. Part of the [HyperTaxonomy](https://hypertaxonomy.org) suite; built on [TermConnect](https://hypertaxonomy.org/termconnect/).

## What it does

- **Two flat taxonomies** — `hashtag` (primary) and `mention` (secondary). Terms are assigned to posts **automatically** from the inline tokens in your content; there is no taxonomy meta box.
- **Inline tokens in the editor** — type `#` or `@` to autocomplete existing terms and local users, or select text and click the `#`/`@` toolbar button to tokenize it. Pick which networks each token links to.
- **Smart hashtag links** — one enabled network → the token links straight to it; several → the token opens a pane of choices; none → links to the local archive (or plain text, your choice).
- **Mention profiles** — per-network handles are stored on the mention term and reused; a mention renders as a direct icon + handle link (one profile) or a pane of profiles (several).
- **Drag-to-order networks** in *Settings → Social Hashtags & Mentions*; the order drives the editor and the front end. Toggling a network updates every post at once.
- **Blogosphere** — aggregates hashtag post summaries from your WordPress peers (via TermConnect) onto the hashtag archive, each linking out to the peer.
- **Federation for free** — mentions match across peer sites by shared network handle (profile URI), even when slugs and labels differ.

## Requirements

| Plugin | Role |
|--------|------|
| **TermConnect** | Required — link types, identifier storage + matching, peer fetching |
| **HyperTaxonomy** | Optional — relevance search and relationships |
| **WP-SKOS** | Optional — SKOS concept output |

## Quick start

1. Activate **TermConnect**, then **Social Hashtags & Mentions**.
2. In *Settings → Social Hashtags & Mentions*, enable and order the networks you want.
3. In a post, type `#` or `@` and pick a term; the token's panel chooses which networks (hashtags) or configured profiles (mentions) to show. Mention handles themselves are set on the term's edit screen (External Links).

## Documentation

Full documentation is published at [hypertaxonomy.org/social-hashtags-mentions](https://hypertaxonomy.org/social-hashtags-mentions) (source in [`docs/`](docs/)):

- **[Usage](docs/usage.md)** — settings and network ordering, the inline editor tokens, hashtag vs. mention rendering, mention handles, and the Blogosphere section and `[social_blogosphere]` shortcode.
- **[Developer Reference](docs/developer-reference.md)** — the network registry filter, REST endpoints, the TermConnect integration (link types, `tc_link_uri`, `tc_enabled_taxonomies`, peer author search), and how token markup maps to taxonomy terms.

## Translations

Social Hashtags & Mentions is translation-ready and ships with a complete translation template: `languages/social-hashtags-mentions.pot`.

To contribute a translation: copy the template to `languages/social-hashtags-mentions-<locale>.po`, translate it, and open a pull request — the build compiles it (and, for block-editor strings, generates the required JSON) automatically. See `utils/make-pot.sh` and `utils/make-json.py`.

## License

GPLv2 or later.
