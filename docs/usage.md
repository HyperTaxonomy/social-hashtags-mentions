---
title: Usage
nav_order: 2
---

# Usage

## Enabling and ordering networks

Go to **Settings → Social Hashtags & Mentions**. Each supported network has a row you can:

- **Enable / disable** with its checkbox. Disabling a network removes its links from every post at once.
- **Drag to reorder** (the move handle on the left). The order here is the order networks are offered in the editor and rendered on the front end.

Networks declare which taxonomies they support — most support both hashtags and mentions; a few are mention-only (e.g. GitHub, Reddit) or instance-based (Mastodon, where a handle is `user@instance`).

Two display options live on the same screen:

- **Blogosphere** — auto-append the aggregated peer-post section to hashtag archives (see below).
- **Hashtags with no enabled links** — link them to the local hashtag archive, or render them as plain text.

## Writing hashtags and mentions

In any rich-text field in the block editor, type `#` or `@`. An autocomplete list appears:

- `#` — searches existing **hashtag** terms; choose one, or pick "Create #…" to make a new one.
- `@` — searches existing **mention** terms and your **local users** in one combined list. Picking a local user ties the mention to that person's account (not just their display name), and automatically sets the term's "Local site" profile link the first time — so two different users who happen to share a display name are never merged into the same mention.

Selecting an item inserts a styled token. Terms are created and assigned to the post **automatically when you save** — there is no separate taxonomy box.

### Turning existing text into a token

You can also tokenize text you have already written: **select a word or phrase and click the `#` or `@` button in the block toolbar** — the same way the editor's link button works.

- **`#`** wraps the first word of the selection as a hashtag (a hashtag is a single word). A `#` already sitting in front of the word is reused, not duplicated.
- **`@`** wraps the whole selection as a mention, so multi-word names and forms like `@user@host` or `C#` are kept intact.
- Click **Apply** in the panel to keep the token; press **Escape** or click away to cancel and leave the text untouched.

The button is unavailable when the selection is part of an ordinary link (remove the link first) or already carries the other token type.

### Choosing networks for a token

Place the caret inside a token and a small panel opens (or click the toolbar button):

- **Hashtag** — tick the networks this particular hashtag should link to. (By default a token links to all enabled networks.)
- **Mention** — tick which of this person's **configured profiles** to show for this instance. The panel lists only the networks where the term already has a handle; untick any you want to omit here.

#### Setting a person's handles and profiles

A mention's identifiers are configured once, on the **mention term's edit screen** (Mentions → edit a term → *External links*), and reused everywhere that person is mentioned:

- **Social handles** — for networks with a public profile search (currently **GitHub** and **Bluesky**) the field autocompletes; for the rest, type the handle directly (a pasted profile URL or a leading `@` is cleaned up automatically).
- **Peer sites** — a WordPress-peer link on a mention searches that peer's **authors** (a person is an author there, not a tag) and links to their author profile.
- **Local site** — the built-in "Local site" entry maps the mention to a **local** author profile the same way.

## How tokens render on the front end

The links are computed at display time from the per-token selection intersected with the networks currently enabled in settings.

- **Hashtag with one network** → the token links directly to that network's hashtag page.
- **Hashtag with several networks** → clicking the token opens a small pane listing each network.
- **Hashtag with no enabled networks** → links to the local archive, or plain text (per the setting).
- **Mention with one profile** → the token renders inline as the network icon + handle, linking to that profile (in place of the `@name`).
- **Mention with several profiles** → the `@name` stays and clicking it opens a pane of icon + handle links.
- **Mention with no profiles** → links to the local archive, or plain text (the same setting as hashtags).

## The Blogosphere section

The built-in **Blogosphere** gathers post **summaries** sharing a hashtag from your configured WordPress peer sites (set up in TermConnect) and shows them on the hashtag archive. Each summary links out to the peer's own post — nothing is copied locally. (The aggregated-summaries feature is hashtag-only; for **mentions**, enabling Blogosphere acts as the on/off gate for showing a person's WordPress author profiles across your peer sites.)

Enable the **Blogosphere** network in settings to turn it on. With **auto-append** enabled it appears automatically on hashtag archives. For precise placement — especially in block themes — use the shortcode:

```
[social_blogosphere]
```

On a hashtag archive it defaults to the current hashtag. Elsewhere, name one:

```
[social_blogosphere hashtag="example" per_page="10"]
```

The section paginates independently of the page's own pagination.

## Federation

Because Social Hashtags & Mentions is built on TermConnect, hashtags and mentions work across peer sites automatically. Mentions are matched between sites by their shared network handle (the resulting profile URI), so the same person is recognized even when the term slug or label differs from site to site.
