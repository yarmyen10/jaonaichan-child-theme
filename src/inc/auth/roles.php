<?php
/**
 * Custom Role: Developer
 */
function register_developer_role() {

    // ถ้ามี Role นี้แล้ว ไม่ต้องสร้างใหม่
    if ( get_role( 'developer' ) ) return;

    add_role(
        'developer',        // slug
        'Developer',        // ชื่อที่แสดง
        array(
            // ── WordPress Core ──────────────────
            'read'                   => true,
            'edit_posts'             => true,
            'edit_pages'             => true,
            'edit_others_posts'      => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'manage_options'         => true,   // เข้า Settings ได้
            'edit_theme_options'     => true,   // แก้ Theme ได้
            'install_plugins'        => true,
            'activate_plugins'       => true,
            'edit_plugins'           => true,
            'install_themes'         => true,
            'switch_themes'          => true,
            'edit_files'             => true,   // แก้ไฟล์ใน Editor ได้
            'upload_files'           => true,
            'unfiltered_html'        => true,   // เขียน HTML ได้เต็ม
            'export'                 => true,
            'import'                 => true,

            // ── WooCommerce ──────────────────────
            'manage_woocommerce'     => true,
            'view_woocommerce_reports' => true,
            'edit_shop_orders'       => true,
            'edit_products'          => true,
            'publish_shop_orders'    => true,

            // ── ไม่ให้ทำ ─────────────────────────
            'create_users'           => false,  // ห้ามสร้าง User
            'edit_users'             => false,  // ห้ามแก้ User
            'delete_users'           => false,  // ห้ามลบ User
            'promote_users'          => false,  // ห้ามเปลี่ยน Role
        )
    );
}

add_action( 'init', 'register_developer_role' );


/**
 * ลบ Role เมื่อ Theme ถูก Deactivate
 * (เพื่อความสะอาด)
 */
function remove_developer_role() {
    remove_role( 'developer' );
}
add_action( 'switch_theme', 'remove_developer_role' );