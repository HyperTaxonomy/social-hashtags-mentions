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

add_action( 'admin_menu', 'tcs_admin_menu' );
add_action( 'admin_init', 'tcs_admin_handle_save' );
add_action( 'admin_enqueue_scripts', 'tcs_admin_enqueue' );
add_filter( 'plugin_action_links_' . TCS_PLUGIN_BASENAME, 'tcs_plugin_action_links' );

/**
 * Adds a Settings link to the plugin's row on the Plugins screen.
 *
 * @since 1.0.0
 * @param string[] $links Existing action links.
 * @return string[]
 */
function tcs_plugin_action_links( array $links ): array {
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
function tcs_admin_menu(): void {
    add_options_page(
        __( 'TermConnect Social', 'termconnect-social' ),
        __( 'TermConnect Social', 'termconnect-social' ),
        'manage_options',
        'termconnect-social',
        'tcs_admin_render'
    );
}

/**
 * Enqueues the sortable script on the settings page only.
 *
 * @since 1.0.0
 * @param string $hook Current admin page hook.
 */
function tcs_admin_enqueue( string $hook ): void {
    if ( 'settings_page_termconnect-social' !== $hook ) {
        return;
    }
    wp_enqueue_script(
        'tcs-settings',
        TCS_PLUGIN_URL . 'assets/js/tcs-settings.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        TCS_VERSION,
        true
    );
    wp_enqueue_style(
        'termconnect-social-admin',
        TCS_PLUGIN_URL . 'assets/css/admin.css',
        array( 'dashicons' ),
        TCS_VERSION
    );
}

/**
 * Handles the settings form submission.
 *
 * @since 1.0.0
 */
function tcs_admin_handle_save(): void {
    if ( ! isset( $_POST['tcs_settings_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tcs_settings_nonce'] ) ), 'tcs_settings_save' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'termconnect-social' ) );
    }

    $order   = isset( $_POST['tcs_order'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['tcs_order'] ) ) : array();
    $enabled = isset( $_POST['tcs_enabled'] ) ? map_deep( (array) wp_unslash( $_POST['tcs_enabled'] ), 'sanitize_text_field' ) : array();

    $networks = array();
    foreach ( $order as $slug ) {
        if ( '' === $slug ) {
            continue;
        }
        $networks[ $slug ] = ! empty( $enabled[ $slug ] );
    }

    $auto    = isset( $_POST['tcs_blogosphere_auto_append'] );
    $no_link = isset( $_POST['tcs_hashtag_no_link'] ) ? sanitize_key( wp_unslash( $_POST['tcs_hashtag_no_link'] ) ) : 'archive';

    tcs_save_settings( $networks, $auto, $no_link, isset( $_POST['tcs_delete_on_uninstall'] ) );

    wp_safe_redirect( add_query_arg(
        array( 'page' => 'termconnect-social', 'tcs-saved' => '1' ),
        admin_url( 'options-general.php' )
    ) );
    exit;
}

/**
 * Renders the settings page.
 *
 * @since 1.0.0
 */
function tcs_admin_render(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'termconnect-social' ) );
    }

    $states   = tcs_get_network_states();
    $auto     = tcs_blogosphere_auto_append_setting();
    $no_link  = tcs_no_link_mode();
    $tc_ready = function_exists( 'tc_get_all_link_types' );
    ?>
    <div class="wrap tcs-settings">
        <h1><?php esc_html_e( 'TermConnect Social', 'termconnect-social' ); ?></h1>

        <?php if ( isset( $_GET['tcs-saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'termconnect-social' ); ?></p></div>
        <?php endif; ?>

        <?php if ( ! $tc_ready ) : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'TermConnect is required and does not appear to be active.', 'termconnect-social' ); ?></p></div>
        <?php endif; ?>

        <p class="description">
            <?php esc_html_e( 'Enable the networks you want offered in the editor and shown on your posts, and drag to set their order. Hashtags and mentions are added to posts automatically from the inline tokens in your content.', 'termconnect-social' ); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field( 'tcs_settings_save', 'tcs_settings_nonce' ); ?>

            <h2><?php esc_html_e( 'Networks', 'termconnect-social' ); ?></h2>
            <ul class="tcs-network-list">
                <?php foreach ( $states as $slug => $is_enabled ) : ?>
                    <?php
                    $def = tcs_get_network( $slug );
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
                    <li class="tcs-network-item">
                        <span class="tcs-drag" title="<?php esc_attr_e( 'Drag to reorder', 'termconnect-social' ); ?>" aria-hidden="true">&#8942;</span>
                        <input type="hidden" name="tcs_order[]" value="<?php echo esc_attr( $slug ); ?>" />
                        <label class="tcs-network-label">
                            <input type="checkbox" name="tcs_enabled[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $is_enabled ); ?> />
                            <?php echo wp_kses( tcs_network_icon_html( $slug, $def['icon'] ?? 'dashicons-admin-links' ), tcs_allowed_html() ); ?>
                            <span class="tcs-network-name"><?php echo esc_html( $def['label'] ); ?></span>
                        </label>
                        <span class="tcs-network-supports">
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
                            <input type="checkbox" name="tcs_blogosphere_auto_append" value="1" <?php checked( $auto ); ?> />
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
                        <label><input type="radio" name="tcs_hashtag_no_link" value="archive" <?php checked( 'archive', $no_link ); ?> /> <?php esc_html_e( 'Link to the local archive', 'termconnect-social' ); ?></label><br />
                        <label><input type="radio" name="tcs_hashtag_no_link" value="plain" <?php checked( 'plain', $no_link ); ?> /> <?php esc_html_e( 'Render as plain text (no link)', 'termconnect-social' ); ?></label>
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
                            <input type="checkbox" name="tcs_delete_on_uninstall" value="1" <?php checked( ! empty( tcs_get_settings()['delete_on_uninstall'] ) ); ?> />
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
function tcs_admin_justify_help_text() {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( in_array( $page, array( 'termconnect-social' ), true ) ) {
        echo '<style>.wrap p:not(td p):not(th p):not(.notice p), .wrap p:has(+ table) { text-align: justify; hyphens: none; }</style>';
    }
}
add_action( 'admin_head', 'tcs_admin_justify_help_text' );
