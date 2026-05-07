<?php
/**
 * Custom Order Status
 */

// 🧾 กลุ่มสถานะ “การชำระเงิน” / 📦 กลุ่มสถานะ “แพ็คสินค้า”
function jaonaichan_get_custom_order_statuses() {
    return array(
        'wc-waiting-transfer'       => __( 'รอโอนเงิน', $_ENV['TEXTDOMAIN_NAME'] ),
        'wc-pending-payment-1'      => __( 'รอชำระบิลที่ 1', $_ENV['TEXTDOMAIN_NAME'] ),
        'wc-pending-payment-2'      => __( 'รอชำระบิลที่ 2', $_ENV['TEXTDOMAIN_NAME'] ),
        'wc-wait-verify-1' => __( 'รอตรวจสอบการชำระ (ครั้งที่ 1)', $_ENV['TEXTDOMAIN_NAME'] ),
        'wc-wait-verify-2' => __( 'รอตรวจสอบการชำระ (ครั้งที่ 2)', $_ENV['TEXTDOMAIN_NAME'] ),
        'wc-paid-1'                 => __( 'ชำระแล้ว (ครั้งที่ 1)', $_ENV['TEXTDOMAIN_NAME'] ),
        'wc-paid-2'                 => __( 'ชำระแล้ว (ครั้งที่ 2)', $_ENV['TEXTDOMAIN_NAME'] ),
    );
}

function jaonaichan_get_custom_order_status_args( $label ) {
    $count_template = $label . ' <span class="count">(%s)</span>';
    return array(
        'label'                     => $label,
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( $count_template, $count_template ),
    );
}

// ✅ เปลี่ยน priority เป็น 5 → รันหลัง textdomain โหลด (priority 1)
function register_custom_order_status() {
    foreach ( jaonaichan_get_custom_order_statuses() as $slug => $label ) {
        register_post_status( $slug, jaonaichan_get_custom_order_status_args( $label ) );
    }
}
add_action( 'init', 'register_custom_order_status', 5 );

function add_custom_status_to_dropdown( $order_statuses ) {
    return array_merge( $order_statuses, jaonaichan_get_custom_order_statuses() );
}

// HPOS: WooCommerce registers shop_order post statuses through this filter
// when High-Performance Order Storage is enabled, bypassing register_post_status().
function jaonaichan_register_hpos_order_statuses( $statuses ) {
    foreach ( jaonaichan_get_custom_order_statuses() as $slug => $label ) {
        $statuses[ $slug ] = jaonaichan_get_custom_order_status_args( $label );
    }
    return $statuses;
}

add_filter( 'wc_order_statuses', 'add_custom_status_to_dropdown' );
add_filter( 'woocommerce_register_shop_order_post_statuses', 'jaonaichan_register_hpos_order_statuses' );


// ใส่สีพื้นหลังให้ badge ของสถานะที่กำหนดเองในหน้า admin
add_action( 'admin_head', 'jaonaichan_custom_status_badge_colors' );
function jaonaichan_custom_status_badge_colors() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && ! in_array( $screen->id, array( 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders--shop_order' ), true ) ) {
        return;
    }
    ?>
    <style>
        /* รอโอนเงิน */
        .order-status.status-waiting-transfer,
        mark.status-waiting-transfer { background: #f8dda7; color: #94660c; }

        /* รอชำระบิลที่ 1 / 2 */
        .order-status.status-pending-payment-1,
        mark.status-pending-payment-1 { background: #fdf1d8; color: #94660c; }
        .order-status.status-pending-payment-2,
        mark.status-pending-payment-2 { background: #f8dda7; color: #94660c; }

        /* รอตรวจสอบการชำระ ครั้งที่ 1 / 2 */
        .order-status.status-wait-verify-1,
        mark.status-wait-verify-1 { background: #d4e7ff; color: #1c4a86; }
        .order-status.status-wait-verify-2,
        mark.status-wait-verify-2 { background: #a9cdff; color: #1c4a86; }

        /* ชำระแล้ว ครั้งที่ 1 / 2 */
        .order-status.status-paid-1,
        mark.status-paid-1 { background: #c8e6c9; color: #2e7d32; }
        .order-status.status-paid-2,
        mark.status-paid-2 { background: #81c784; color: #1b5e20; }
    </style>
    <?php
}