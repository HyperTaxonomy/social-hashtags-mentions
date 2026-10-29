<?php
/**
 * Social Hashtags & Mentions bootstrap — loads all plugin files.
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Core (loaded unconditionally).
require_once SH_PLUGIN_DIR . 'includes/networks.php';
require_once SH_PLUGIN_DIR . 'includes/settings.php';
require_once SH_PLUGIN_DIR . 'includes/taxonomies.php';
require_once SH_PLUGIN_DIR . 'includes/link-types.php';
require_once SH_PLUGIN_DIR . 'includes/term-sync.php';
require_once SH_PLUGIN_DIR . 'includes/render.php';
require_once SH_PLUGIN_DIR . 'includes/blogosphere.php';
require_once SH_PLUGIN_DIR . 'includes/rest.php';

// Register taxonomies on init.
add_action( 'init', 'sh_register_taxonomies' );

// Admin + editor.
if ( is_admin() ) {
    require_once SH_PLUGIN_DIR . 'includes/admin.php';
    require_once SH_PLUGIN_DIR . 'includes/term-columns.php';
}
require_once SH_PLUGIN_DIR . 'includes/editor.php';
