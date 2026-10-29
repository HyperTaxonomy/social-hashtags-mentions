# Network icons

Drop a brand SVG here named `{network-slug}.svg` (e.g. `mastodon.svg`, `bluesky.svg`,
`livejournal.svg`) and it is used automatically wherever that network's icon renders —
the front-end multilink pane, mention badges, and the admin settings list — via
`sh_network_icon_html()` in `includes/networks.php`. When no SVG is present for a slug,
the network's Dashicon (set in the registry) is used instead.

Slugs match the registry keys in `includes/networks.php`: `x`, `mastodon`, `bluesky`,
`threads`, `instagram`, `tiktok`, `youtube`, `facebook`, `linkedin`, `pinterest`,
`reddit`, `tumblr`, `github`, `livejournal`, `dreamwidth`. (Networks that already have a
real Dashicons brand glyph — Instagram, YouTube, Facebook, LinkedIn, Pinterest, Reddit —
don't need an SVG, but one here will override the Dashicon if you prefer consistency.)

## Style requirements (so SVGs sit cleanly next to Dashicons)

The SVG is inlined and inherits the surrounding text color, sized to `1em`:

- **Square `viewBox`** — `0 0 24 24` (or `0 0 20 20`). No `width`/`height` attributes on the `<svg>`.
- **Monochrome** — use `fill="currentColor"` (no hard-coded colors), so the icon takes the link/badge color and hover state.
- **Single, simple shape** — a flat silhouette in the Dashicons weight; avoid gradients, multiple colors, or fine detail that won't read at ~16–20px.
- Keep the markup minimal (`<svg>` + `<path>`), no XML prolog, no comments.

Example skeleton:

```svg
<svg viewBox="0 0 24 24" fill="currentColor" role="img" aria-hidden="true"><path d="…"/></svg>
```

## What ships here

Bundled from [Simple Icons](https://simpleicons.org/) (CC0): `x`, `tiktok`, `threads`,
`bluesky`, `mastodon`, `tumblr`, `github`, `livejournal` — i.e. the networks WordPress's
Dashicons don't already cover. `dreamwidth` has no Simple Icons entry, so it falls back to
its Dashicon; drop a `dreamwidth.svg` here (per the rules above) to add it.

## Sourcing

These are brand marks, so this folder ships empty by default. A good source of
consistently-styled, CC0-licensed brand glyphs is [Simple Icons](https://simpleicons.org/)
(the SVGs are public domain; the trademarks remain each brand's property — using a mark to
link to that network is standard nominative use). Normalize any sourced SVG to the rules
above before adding it here.
