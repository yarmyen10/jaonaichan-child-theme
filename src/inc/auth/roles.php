<?php
/**
 * Custom Role: Developer
 */
function register_developer_role() {

    $caps = array(
        // ── WordPress Core ──────────────────
        'read'                      => true,
        'edit_posts'                => true,
        'edit_pages'                => true,
        'edit_others_posts'         => true,
        'edit_published_posts'      => true,
        'publish_posts'             => true,
        'delete_posts'              => true,
        'manage_options'            => true,
        'edit_theme_options'        => true,
        'install_plugins'           => true,
        'activate_plugins'          => true,
        'edit_plugins'              => true,
        'install_themes'            => true,
        'switch_themes'             => true,
        'edit_files'                => true,
        'upload_files'              => true,
        'unfiltered_html'           => true,
        'export'                    => true,
        'import'                    => true,

        // ── WooCommerce ──────────────────────
        'manage_woocommerce'        => true,
        'view_woocommerce_reports'  => true,
        'edit_shop_orders'          => true,
        'edit_products'             => true,
        'publish_shop_orders'       => true,

        // ── Users ────────────────────────────
        'create_users'              => false,
        'edit_users'                => false,
        'delete_users'              => false,
        'promote_users'             => false,

        // ── Query Monitor ────────────────────
        'view_query_monitor'        => true,
    );

    // ถ้ายังไม่มี role ให้สร้าง
    if ( ! get_role( 'developer' ) ) {
        add_role( 'developer', 'Developer', $caps );
    }

    // ถ้ามีแล้ว ให้อัปเดต capability ทุกครั้ง
    $role = get_role( 'developer' );
    if ( $role ) {
        foreach ( $caps as $cap => $grant ) {
            if ( $grant ) {
                $role->add_cap( $cap );
            } else {
                $role->remove_cap( $cap );
            }
        }
    }
}
add_action( 'init', 'register_developer_role' );

/**
 * อนุญาตให้ role developer ใช้งาน Query Monitor
 */
add_filter( 'qm/user_can_view', function( $user_can, $user ) {
    if ( user_can( $user, 'view_query_monitor' ) ) {
        return true;
    }

    if ( in_array( 'developer', (array) $user->roles, true ) ) {
        return true;
    }

    return $user_can;
}, 10, 2 );

/**
 * ลบ Role เมื่อ Theme ถูก Deactivate
 */
function remove_developer_role() {
    remove_role( 'developer' );
}
add_action( 'switch_theme', 'remove_developer_role' );