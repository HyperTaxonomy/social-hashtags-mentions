<?php
/**
 * Plugin Name: Social Hashtags & Mentions
 * Plugin URI:  https://github.com/HyperTaxonomy/social-hashtags-mentions
 * Description: Inline #hashtags and @mentions as taxonomy terms, with per-instance links to the social networks you enable. Built on TermConnect.
 * Version:     1.0.0
 * Author:      Eugene Weinstein
 * Author URI:  https://hypertaxonomy.org
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Requires Plugins: termconnect
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-hashtags-mentions
 * Domain Path: /languages
 *
 * @package SocialHashtagsMentions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SH_PLUGIN_FILE',     __FILE__ );
define( 'SH_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'SH_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'SH_VERSION',         '1.0.0' );
define( 'SH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

register_activation_hook( __FILE__, 'sh_activate' );
register_deactivation_hook( __FILE__, 'sh_deactivate' );

// Load translations regardless of whether TermConnect is active, so the
// requirement notice is translatable too. Hooked on init per WP 6.7+ guidance.
add_action( 'init', static function () {
    load_plugin_textdomain( 'social-hashtags-mentions', false, dirname( SH_PLUGIN_BASENAME ) . '/languages' );
} );

/**
 * Activation: register taxonomies then flush rewrite rules so archives resolve.
 *
 * @since 1.0.0
 */
function sh_activate(): void {
    require_once SH_PLUGIN_DIR . 'includes/taxonomies.php';
    sh_register_taxonomies();
    flush_rewrite_rules();
}

/**
 * Deactivation: drop the stored rewrite rules so the taxonomy archive routes are removed.
 *
 * @since 1.0.0
 */
function sh_deactivate(): void {
    // Not flush_rewrite_rules(): the plugin's rules are still registered during this request, so flush_rewrite_rules() would rebuild them; dropping the stored rules makes WordPress rebuild them without this plugin on the next request.
    delete_option( 'rewrite_rules' );
}

/**
 * Whether TermConnect is active.
 *
 * Checks the active-plugins option as well as the function, so the result does
 * not depend on plugin load order — TermConnect registers its own loader on the
 * same `plugins_loaded` hook, and may run before or after this plugin.
 *
 * TermConnect provides the link-type registry, URL filter, peer-fetch API, and
 * the cross-site identifier storage this plugin depends on.
 *
 * @since 1.0.0
 * @return bool True when TermConnect is available.
 */
function sh_termconnect_active(): bool {
    if ( function_exists( 'tc_get_all_link_types' ) ) {
        return true;
    }
    $plugin = 'termconnect/termconnect.php';
    if ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) {
        return true;
    }
    if ( is_multisite() ) {
        $network = (array) get_site_option( 'active_sitewide_plugins', array() );
        if ( isset( $network[ $plugin ] ) ) {
            return true;
        }
    }
    return false;
}

// Load when TermConnect is present; otherwise show a notice. The bootstrap only
// registers hooks (it does not call TermConnect functions at load time), so it is
// safe to load even when TermConnect's own loader runs later in the request.
add_action( 'plugins_loaded', static function () {
    if ( sh_termconnect_active() ) {
        require_once SH_PLUGIN_DIR . 'includes/bootstrap.php';
        return;
    }
    add_action( 'admin_notices', static function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'Social Hashtags & Mentions requires the TermConnect plugin to be installed and active.', 'social-hashtags-mentions' );
        echo '</p></div>';
    } );
}, 20 );