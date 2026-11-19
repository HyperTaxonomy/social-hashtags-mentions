<?php
/**
 * Admin settings page: Settings → TermConnect Social.
 *
 * Lets the admin enable/disable networks and drag to reorder them. The stored
 * order is the order networks are offered in the editor and rendered as badges.
 * Also exposes the Blogosphere auto-append toggle and the no-link hashtag mode.
 *
 * @package TermConnectSocial
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'tcsoc_admin_menu' );
add_action( 'admin_init', 'tcsoc_admin_handle_save' );
add_action( 'admin_enqueue_scripts', 'tcsoc_admin_enqueue' );
add_filter( 'plugin_action_links_' . TCSOC_PLUGIN_BASENAME, 'tcsoc_plugin_action_links' );

/**
 * Adds a Settings link to the plugin's row on the Plugins screen.
 *
 * @since 1.0.0
 * @param string[] $links Existing action links.
 * @return string[]
 */
function tcsoc_plugin_action_links( array $links ): array {
    $url      = admin_url( 'options-general.php?page=termconnect-social' );
    $settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'termconnect-social' ) . '</a>';
    array_unshift( $links, $settings );
    return $links;
}

/**
 * Registers the settings page.
 *
 * @since 1.0.0
 */
function tcsoc_admin_menu(): void {
    add_options_page(
        __( 'TermConnect Social', 'termconnect-social' ),
        __( 'TermConnect Social', 'termconnect-social' ),
        'manage_options',
        'termconnect-social',
        'tcsoc_admin_render'
    );
}

/**
 * Enqueues the sortable script on the settings page only.
 *
 * @since 1.0.0
 * @param string $hook Current admin page hook.
 */
function tcsoc_admin_enqueue( string $hook ): void {
    if ( 'settings_page_termconnect-social' !== $hook ) {
        return;
    }
    wp_enqueue_script(
        'tcsoc-settings',
        TCSOC_PLUGIN_URL . 'assets/js/tcsoc-settings.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        TCSOC_VERSION,
        true
    );
    wp_enqueue_style(
        'termconnect-social-admin',
        TCSOC_PLUGIN_URL . 'assets/css/admin.css',
        array( 'dashicons' ),
        TCSOC_VERSION
    );
}

/**
 * Handles the settings form submission.
 *
 * @since 1.0.0
 */
function tcsoc_admin_handle_save(): void {
    if ( ! isset( $_POST['tcsoc_settings_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tcsoc_settings_nonce'] ) ), 'tcsoc_settings_save' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'termconnect-social' ) );
    }

    $order   = isset( $_POST['tcsoc_order'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['tcsoc_order'] ) ) : array();
    $enabled = isset( $_POST['tcsoc_enabled'] ) ? map_deep( (array) wp_unslash( $_POST['tcsoc_enabled'] ), 'sanitize_text_field' ) : array();

    $networks = array();
    foreach ( $order as $slug ) {
        if ( '' === $slug ) {
            continue;
        }
        $networks[ $slug ] = ! empty( $enabled[ $slug ] );
    }

    $auto    = isset( $_POST['tcsoc_blogosphere_auto_append'] );
    $no_link = isset( $_POST['tcsoc_hashtag_no_link'] ) ? sanitize_key( wp_unslash( $_POST['tcsoc_hashtag_no_link'] ) ) : 'archive';

    tcsoc_save_settings( $networks, $auto, $no_link, isset( $_POST['tcsoc_delete_on_uninstall'] ) );

    wp_safe_redirect( add_query_arg(
        array( 'page' => 'termconnect-social', 'tcsoc-saved' => '1' ),
        admin_url( 'options-general.php' )
    ) );
    exit;
}

/**
 * Renders the settings page.
 *
 * @since 1.0.0
 */
function tcsoc_admin_render(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'termconnect-social' ) );
    }

    $states   = tcsoc_get_network_states();
    $auto     = tcsoc_blogosphere_auto_append_setting();
    $no_link  = tcsoc_no_link_mode();
    $tconn_ready = function_exists( 'tconn_get_all_link_types' );
    ?>
    <div class="wrap tcsoc-settings">
        <h1><?php esc_html_e( 'TermConnect Social', 'termconnect-social' ); ?></h1>

        <?php if ( isset( $_GET['tcsoc-saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'termconnect-social' ); ?></p></div>
        <?php endif; ?>

        <?php if ( ! $tconn_ready ) : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'TermConnect is required and does not appear to be active.', 'termconnect-social' ); ?></p></div>
        <?php endif; ?>

        <p class="description">
            <?php esc_html_e( 'Enable the networks you want offered in the editor and shown on your posts, and drag to set their order. Hashtags and mentions are added to posts automatically from the inline tokens in your content.', 'termconnect-social' ); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field( 'tcsoc_settings_save', 'tcsoc_settings_nonce' ); ?>

            <h2><?php esc_html_e( 'Networks', 'termconnect-social' ); ?></h2>
            <ul class="tcsoc-network-list">
                <?php foreach ( $states as $slug => $is_enabled ) : ?>
                    <?php
                    $def = tcsoc_get_network( $slug );
                    if ( ! $def ) {
                        continue;
                    }
                    $supports = array();
                    if ( in_array( 'hashtag', (array) $def['supports'], true ) ) {
                        $supports[] = __( 'Hashtags', 'termconnect-social' );
                    }
                    if ( in_array( 'mention', (array) $def['supports'], true ) ) {
                        $supports[] = __( 'Mentions', 'termconnect-social' );
                    }
                    ?>
                    <li class="tcsoc-network-item">
                        <span class="tcsoc-drag" title="<?php esc_attr_e( 'Drag to reorder', 'termconnect-social' ); ?>" aria-hidden="true">&#8942;</span>
                        <input type="hidden" name="tcsoc_order[]" value="<?php echo esc_attr( $slug ); ?>" />
                        <label class="tcsoc-network-label">
                            <input type="checkbox" name="tcsoc_enabled[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $is_enabled ); ?> />
                            <?php echo wp_kses( tcsoc_network_icon_html( $slug, $def['icon'] ?? 'dashicons-admin-links' ), tcsoc_allowed_html() ); ?>
                            <span class="tcsoc-network-name"><?php echo esc_html( $def['label'] ); ?></span>
                        </label>
                        <span class="tcsoc-network-supports">
                            <?php
                            echo esc_html( implode( ' · ', $supports ) );
                            if ( ! empty( $def['internal'] ) ) {
                                echo ' — ' . esc_html__( 'peer aggregation (no external link)', 'termconnect-social' );
                            }
                            ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2><?php esc_html_e( 'Display', 'termconnect-social' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Blogosphere', 'termconnect-social' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="tcsoc_blogosphere_auto_append" value="1" <?php checked( $auto ); ?> />
                            <?php esc_html_e( 'Automatically append the Blogosphere section to hashtag archives.', 'termconnect-social' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When off, place it manually with the [social_blogosphere] shortcode (recommended for block themes). Requires the Blogosphere network to be enabled above and WordPress peer sites configured in TermConnect.', 'termconnect-social' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tokens with no enabled links', 'termconnect-social' ); ?></th>
                    <td>
                        <label><input type="radio" name="tcsoc_hashtag_no_link" value="archive" <?php checked( 'archive', $no_link ); ?> /> <?php esc_html_e( 'Link to the local archive', 'termconnect-social' ); ?></label><br />
                        <label><input type="radio" name="tcsoc_hashtag_no_link" value="plain" <?php checked( 'plain', $no_link ); ?> /> <?php esc_html_e( 'Render as plain text (no link)', 'termconnect-social' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Applies to both hashtags and mentions whose networks resolve to no links.', 'termconnect-social' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Uninstall', 'termconnect-social' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Delete data on uninstall', 'termconnect-social' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="tcsoc_delete_on_uninstall" value="1" <?php checked( ! empty( tcsoc_get_settings()['delete_on_uninstall'] ) ); ?> />
                            <?php esc_html_e( 'Delete mention handles and their links to local users when the plugin is deleted.', 'termconnect-social' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When off, they are kept if you reinstall. Hashtag and mention terms are kept either way, and other settings are always removed.', 'termconnect-social' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Justify the help text on this plugin's admin pages, such as the paragraphs above tables and
 * forms (including those in a table cell directly above a nested table), without splitting words.
 * Other field descriptions in table cells, and notices, are left as they are.
 *
 * @since 1.0.0
 */
function tcsoc_admin_justify_help_text() {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( in_array( $page, array( 'termconnect-social' ), true ) ) {
        wp_register_style( 'tcsoc-admin-help-text', false, array(), TCSOC_VERSION );
        wp_add_inline_style( 'tcsoc-admin-help-text', '.wrap p:not(td p):not(th p):not(.notice p), .wrap p:has(+ table) { text-align: justify; hyphens: none; }' );
        wp_print_styles( 'tcsoc-admin-help-text' );
    }
}
add_action( 'admin_head', 'tcsoc_admin_justify_help_text' );
