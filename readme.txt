=== Social Hashtags & Mentions ===
Contributors: eugenevv
Author: Eugene Weinstein
Tags: hashtags, mentions, social, taxonomy, fediverse
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inline #hashtags and @mentions as taxonomy terms, with per-instance links to the social networks you enable. Built on TermConnect.

== Description ==

Social Hashtags & Mentions lets you write `#hashtags` and `@mentions` inline in the block editor — or select text you've already written and click the `#`/`@` toolbar button to turn it into a token. They become terms in two flat taxonomies (`hashtag` and `mention`), assigned to your posts automatically from the tokens in your content — there is no separate taxonomy box to maintain.

Each token can link out to the social networks you choose:

* **Hashtags** link to a network's hashtag page. With one enabled network the token links straight to it; with several, clicking the token opens a small pane of choices; with none it links to the local hashtag archive (or renders as plain text — your choice).
* **Mentions** link to a person's profile on each network. The per-network handle is stored on the mention term and reused everywhere that person is mentioned.

Enable and **drag to order** the networks you want in Settings → Social Hashtags & Mentions. The order is how they are offered in the editor and shown on your posts. Disabling a network removes its links everywhere at once.

= Blogosphere =

The built-in **Blogosphere** is a hashtag-only option that aggregates post summaries sharing a hashtag from your configured WordPress peer sites (via TermConnect) and shows them on the hashtag archive. Each summary links out to the peer's own post — nothing is copied locally.

= Federation =

Since this plugin is built on TermConnect, hashtags and mentions federate across peer sites for free. Mentions are matched across sites by their shared network handle (the resulting profile URI), so the same person is recognized even when term slugs and labels differ between sites.

= Requirements =

* **TermConnect** (required) — link-type registry, identifier storage and matching, peer fetching.
* **HyperTaxonomy** (optional) — relevance-ranked search and richer relationships.
* **WP-SKOS** (optional) — exposes the taxonomies as SKOS concepts.

== Installation ==

1. Install and activate **TermConnect**.
2. Upload the `social-hashtags-mentions` folder to `/wp-content/plugins/`, or install through Plugins → Add New.
3. Activate the plugin.
4. Visit **Settings → Social Hashtags & Mentions** to enable and order the networks you want.

== Frequently Asked Questions ==

= How are hashtag and mention terms added to a post? =

Automatically. On save, the plugin reconciles each post's `hashtag` and `mention` terms to match the inline tokens in its content.

= Where are mention handles entered? =

On the mention term's edit screen (Mentions → edit a term → External Links), where each network's handle (and any peer/local author profile) is configured once and reused everywhere that person is mentioned. In the post editor, the mention's toolbar panel only selects which of those configured profiles to show for that instance.

= How do I place the Blogosphere list? =

It auto-appends to hashtag archives when enabled. For precise placement (especially in block themes) use the `[social_blogosphere]` shortcode.

== Screenshots ==

1. Settings → Social Hashtags & Mentions — enabling and drag-ordering networks, with the Blogosphere and display options
2. Inserting a hashtag in the block editor — the # autocomplete listing existing terms
3. Front end — a hashtag opening its multi-network pane, and a mention rendered as an icon + handle link
4. A mention term's edit screen — the External Links panel where each network's handle is configured once and reused everywhere that person is mentioned
5. The mention popover — choosing which of a person's configured profiles to show, anchored to the token
6. The Blogosphere section on a hashtag archive — aggregated post summaries from peer sites

== Changelog ==

= 1.0.0 =
* Initial release.
