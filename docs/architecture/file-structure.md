---
title: File Structure & Loading
parent: Architecture
nav_order: 1
---

# File Structure & Loading

## File Structure

```
termconnect-social/
├── .distignore                                 # Files left out of the WordPress.org deploy
├── .gitignore                                  # Files left out of the repository
├── LICENSE                                     # GPLv2
├── README.md                                   # Overview and documentation links
├── readme.txt                                  # WordPress.org readme
├── termconnect-social.php                      # Main plugin file: header, constants and bootstrap
├── uninstall.php                               # Uninstall handler
├── .wordpress-org/                             # WordPress.org listing assets: icons, banners, screenshots (not in the zip)
├── assets/                                     
│   ├── css/                                    
│   │   ├── admin.css                           # TermConnect Social — admin settings styles.
│   │   └── termconnect-social.css              # TermConnect Social — frontend styles.
│   ├── icons/                                  
│   │   ├── (images)                            
│   │   └── README.md                           # Network icons
│   └── js/                                     
│       ├── tcs-editor.js                       # TermConnect Social — block editor integration.
│       ├── tcs-frontend.js                     # TermConnect Social — frontend pane behavior.
│       └── tcs-settings.js                     # TermConnect Social — admin settings sortable.
├── docs/                                       # This documentation (Just-the-Docs)
├── includes/                                   
│   ├── admin.php                               # Admin settings page: Settings → TermConnect Social.
│   ├── blogosphere.php                         # Blogosphere: aggregated peer-post summaries for a hashtag.
│   ├── bootstrap.php                           # TermConnect Social bootstrap — loads all plugin files.
│   ├── editor.php                              # Block editor integration: enqueues the inline-token script and localizes the data it needs (REST root + nonce,
│   ├── link-types.php                          # TermConnect link-type integration.
│   ├── networks.php                            # Social network registry.
│   ├── render.php                              # Frontend rendering of inline tokens.
│   ├── rest.php                                # REST endpoints used by the block editor.
│   ├── settings.php                            # Settings storage.
│   ├── taxonomies.php                          # Taxonomy registration: hashtag (primary) and mention (secondary).
│   ├── term-columns.php                        # Term list-table columns for the hashtag and mention taxonomies.
│   └── term-sync.php                           # Automatic term assignment from inline tokens.
├── languages/                                  # Translation template and translations; .mo and .json are build output
└── utils/                                      # Packaging, translation and local deploy scripts (not in the zip)
```

## Loading Sequence

### 1. Main file (`termconnect-social.php`)

1. Define `TCS_PLUGIN_FILE`, `TCS_PLUGIN_DIR`, `TCS_PLUGIN_URL`, `TCS_VERSION` and `TCS_PLUGIN_BASENAME`.
2. Register the activation and deactivation hooks.
3. Load translations on `init`.
4. On `plugins_loaded` at priority 20, require `includes/bootstrap.php` if TermConnect is active. Otherwise, show an admin notice and load nothing else.

### 2. Bootstrap (`includes/bootstrap.php`)

1. **Always:** `networks.php`, `settings.php`, `taxonomies.php`, `link-types.php`, `term-sync.php`, `render.php`, `blogosphere.php` and `rest.php`, then hook `tcs_register_taxonomies()` on `init`.
2. **In admin requests:** `admin.php` and `term-columns.php`.
3. **Always:** `editor.php`, which loads the block editor script.

### Activation and deactivation

- **Activation** (`tcs_activate()`) registers the taxonomies and flushes the rewrite rules, so hashtag and mention archives work immediately.
- **Deactivation** (`tcs_deactivate()`) deletes the stored rewrite rules, so WordPress rebuilds them without the plugin's archives.
