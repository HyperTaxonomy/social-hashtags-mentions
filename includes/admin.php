<?php
/**
 * Admin settings page: Settings → Social Hashtags & Mentions.
 *
 * Lets the admin enable/disable networks and drag to reorder them. The stored
 * order is the order networks are offered in the editor and rendered as badges.
 * Also exposes the Blogosphere auto-append toggle and the no-link hashtag mode.
 *
 * @package SocialHashtagsMentions
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'sh_admin_menu' );
add_action( 'admin_init', 'sh_admin_handle_save' );
add_action( 'admin_enqueue_scripts', 'sh_admin_enqueue' );
add_filter( 'plugin_action_links_' . SH_PLUGIN_BASENAME, 'sh_plugin_action_links' );

/**
 * Adds a Settings link to the plugin's row on the Plugins screen.
 *
 * @since 1.0.0
 * @param string[] $links Existing action links.
 * @return string[]
 */
function sh_plugin_action_links( array $links ): array {
    $url      = admin_url( 'options-general.php?page=social-hashtags-mentions' );
    $settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'social-hashtags-mentions' ) . '</a>';
    array_unshift( $links, $settings );
    return $links;
}

/**
 * Registers the settings page.
 *
 * @since 1.0.0
 */
function sh_admin_menu(): void {
    add_options_page(
        __( 'Social Hashtags & Mentions', 'social-hashtags-mentions' ),
        __( 'Social Hashtags & Mentions', 'social-hashtags-mentions' ),
        'manage_options',
        'social-hashtags-mentions',
        'sh_admin_render'
    );
}

/**
 * Enqueues the sortable script on the settings page only.
 *
 * @since 1.0.0
 * @param string $hook Current admin page hook.
 */
function sh_admin_enqueue( string $hook ): void {
    if ( 'settings_page_social-hashtags-mentions' !== $hook ) {
        return;
    }
    wp_enqueue_script(
        'sh-settings',
        SH_PLUGIN_URL . 'assets/js/sh-settings.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        SH_VERSION,
        true
    );
    wp_enqueue_style(
        'social-hashtags-mentions-admin',
        SH_PLUGIN_URL . 'assets/css/admin.css',
        array( 'dashicons' ),
        SH_VERSION
    );
}

/**
 * Handles the settings form submission.
 *
 * @since 1.0.0
 */
function sh_admin_handle_save(): void {
    if ( ! isset( $_POST['sh_settings_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sh_settings_nonce'] ) ), 'sh_settings_save' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'social-hashtags-mentions' ) );
    }

    $order   = isset( $_POST['sh_order'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sh_order'] ) ) : array();
    $enabled = isset( $_POST['sh_enabled'] ) ? (array) wp_unslash( $_POST['sh_enabled'] ) : array();

    $networks = array();
    foreach ( $order as $slug ) {
        if ( '' === $slug ) {
            continue;
        }
        $networks[ $slug ] = ! empty( $enabled[ $slug ] );
    }

    $auto    = isset( $_POST['sh_blogosphere_auto_append'] );
    $no_link = isset( $_POST['sh_hashtag_no_link'] ) ? sanitize_key( wp_unslash( $_POST['sh_hashtag_no_link'] ) ) : 'archive';

    sh_save_settings( $networks, $auto, $no_link );

    wp_safe_redirect( add_query_arg(
        array( 'page' => 'social-hashtags-mentions', 'sh-saved' => '1' ),
        admin_url( 'options-general.php' )
    ) );
    exit;
}

/**
 * Renders the settings page.
 *
 * @since 1.0.0
 */
function sh_admin_render(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'social-hashtags-mentions' ) );
    }

    $states   = sh_get_network_states();
    $auto     = sh_blogosphere_auto_append_setting();
    $no_link  = sh_no_link_mode();
    $tc_ready = function_exists( 'tc_get_all_link_types' );
    ?>
    <div class="wrap sh-settings">
        <h1><?php esc_html_e( 'Social Hashtags & Mentions', 'social-hashtags-mentions' ); ?></h1>

        <?php if ( isset( $_GET['sh-saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'social-hashtags-mentions' ); ?></p></div>
        <?php endif; ?>

        <?php if ( ! $tc_ready ) : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'TermConnect is required and does not appear to be active.', 'social-hashtags-mentions' ); ?></p></div>
        <?php endif; ?>

        <p class="description">
            <?php esc_html_e( 'Enable the networks you want offered in the editor and shown on your posts, and drag to set their order. Hashtags and mentions are added to posts automatically from the inline tokens in your content.', 'social-hashtags-mentions' ); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field( 'sh_settings_save', 'sh_settings_nonce' ); ?>

            <h2><?php esc_html_e( 'Networks', 'social-hashtags-mentions' ); ?></h2>
            <ul class="sh-network-list">
                <?php foreach ( $states as $slug => $is_enabled ) : ?>
                    <?php
                    $def = sh_get_network( $slug );
                    if ( ! $def ) {
                        continue;
                    }
                    $supports = array();
                    if ( in_array( 'hashtag', (array) $def['supports'], true ) ) {
                        $supports[] = __( 'Hashtags', 'social-hashtags-mentions' );
                    }
                    if ( in_array( 'mention', (array) $def['supports'], true ) ) {
                        $supports[] = __( 'Mentions', 'social-hashtags-mentions' );
                    }
                    ?>
                    <li class="sh-network-item">
                        <span class="sh-drag" title="<?php esc_attr_e( 'Drag to reorder', 'social-hashtags-mentions' ); ?>" aria-hidden="true">&#8942;</span>
                        <input type="hidden" name="sh_order[]" value="<?php echo esc_attr( $slug ); ?>" />
                        <label class="sh-network-label">
                            <input type="checkbox" name="sh_enabled[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $is_enabled ); ?> />
                            <?php echo sh_network_icon_html( $slug, $def['icon'] ?? 'dashicons-admin-links' ); ?>
                            <span class="sh-network-name"><?php echo esc_html( $def['label'] ); ?></span>
                        </label>
                        <span class="sh-network-supports">
                            <?php
                            echo esc_html( implode( ' · ', $supports ) );
                            if ( ! empty( $def['internal'] ) ) {
                                echo ' — ' . esc_html__( 'peer aggregation (no external link)', 'social-hashtags-mentions' );
                            }
                            ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2><?php esc_html_e( 'Display', 'social-hashtags-mentions' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Blogosphere', 'social-hashtags-mentions' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sh_blogosphere_auto_append" value="1" <?php checked( $auto ); ?> />
                            <?php esc_html_e( 'Automatically append the Blogosphere section to hashtag archives.', 'social-hashtags-mentions' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When off, place it manually with the [social_blogosphere] shortcode (recommended for block themes). Requires the Blogosphere network to be enabled above and WordPress peer sites configured in TermConnect.', 'social-hashtags-mentions' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tokens with no enabled links', 'social-hashtags-mentions' ); ?></th>
                    <td>
                        <label><input type="radio" name="sh_hashtag_no_link" value="archive" <?php checked( 'archive', $no_link ); ?> /> <?php esc_html_e( 'Link to the local archive', 'social-hashtags-mentions' ); ?></label><br />
                        <label><input type="radio" name="sh_hashtag_no_link" value="plain" <?php checked( 'plain', $no_link ); ?> /> <?php esc_html_e( 'Render as plain text (no link)', 'social-hashtags-mentions' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Applies to both hashtags and mentions whose networks resolve to no links.', 'social-hashtags-mentions' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
