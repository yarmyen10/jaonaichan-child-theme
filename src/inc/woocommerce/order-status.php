<?php
/**
 * Custom Order Status
 */
function register_custom_order_status() {
    // 🧾 กลุ่มสถานะ “การชำระเงิน”
    // รอโอนเงิน (สำหรับลูกค้าที่เลือกชำระเงินแบบโอนเงิน แต่ยังไม่โอนเงินเข้ามา)
    register_post_status( 'wc-waiting-transfer', array(
        'label'                     => __( 'รอโอนเงิน', $_ENV['TEXTDOMAIN_NAME'] ),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));

    // รอชำระบิลที่ 1 (สำหรับลูกค้าที่เลือกชำระเงินแบบโอนเงิน แต่ยังไม่โอนเงินเข้ามา)
    register_post_status( 'wc-pending-payment-1', array(
        'label'                     => __( 'รอชำระบิลที่ 1', $_ENV['TEXTDOMAIN_NAME'] ),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));

    // รอชำระบิลที่ 2 (สำหรับลูกค้าที่เลือกชำระเงินแบบโอนเงิน แต่ยังไม่โอนเงินเข้ามา)
    register_post_status( 'wc-pending-payment-2', array(
        'label'                     => __( 'รอชำระบิลที่ 2', $_ENV['TEXTDOMAIN_NAME'] ),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));

    // รอตรวจสอบการชำระ (สำหรับลูกค้าที่เลือกชำระเงินแบบโอนเงิน และโอนเงินเข้ามาแล้ว แต่ยังไม่ตรวจสอบ)
    register_post_status( 'wc-waiting-verification-1', array(
        'label'                     => __( 'รอตรวจสอบการชำระ (ครั้งที่ 1)', $_ENV['TEXTDOMAIN_NAME'] ),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));

    // รอตรวจสอบการชำระ (สำหรับลูกค้าที่เลือกชำระเงินแบบโอนเงิน และโอนเงินเข้ามาแล้ว แต่ยังไม่ตรวจสอบ)
    register_post_status( 'wc-waiting-verification-2', array(
        'label'                     => __( 'รอตรวจสอบการชำระ (ครั้งที่ 2)', $_ENV['TEXTDOMAIN_NAME'] ),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));

    // ชำระแล้ว (สำหรับลูกค้าที่เลือกชำระเงินแบบโอนเงิน และโอนเงินเข้ามาแล้ว และตรวจสอบแล้ว)
    register_post_status( 'wc-paid-1', array(
        'label'                     => __( 'ชำระแล้ว (ครั้งที่ 1)', $_ENV['TEXTDOMAIN_NAME'] ),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));

    // ชำระแล้ว (สำหรับลูกค้าที่เลือกชำระเงินแบบโอนเงิน และโอนเงินเข้ามาแล้ว และตรวจสอบแล้ว)
    register_post_status( 'wc-paid-2', array(
        'label'                     => __( 'ชำระแล้ว (ครั้งที่ 2)', $_ENV['TEXTDOMAIN_NAME'] ),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));

    // 📦 กลุ่มสถานะ “แพ็คสินค้า”
}
// ✅ เปลี่ยน priority เป็น 5 → รันหลัง textdomain โหลด (priority 1)
add_action( 'init', 'register_custom_order_status', 5 );

function add_custom_status_to_dropdown( $order_statuses ) {
    // 🧾 กลุ่มสถานะ “การชำระเงิน”
    $order_statuses['wc-waiting-transfer'] = __( 'รอโอนเงิน', $_ENV['TEXTDOMAIN_NAME'] );
    $order_statuses['wc-pending-payment-1'] = __( 'รอชำระบิลที่ 1', $_ENV['TEXTDOMAIN_NAME'] );
    $order_statuses['wc-pending-payment-2'] = __( 'รอชำระบิลที่ 2', $_ENV['TEXTDOMAIN_NAME'] );
    $order_statuses['wc-waiting-verification-1'] = __( 'รอตรวจสอบการชำระ (ครั้งที่ 1)', $_ENV['TEXTDOMAIN_NAME'] );
    $order_statuses['wc-waiting-verification-2'] = __( 'รอตรวจสอบการชำระ (ครั้งที่ 2)', $_ENV['TEXTDOMAIN_NAME'] );
    $order_statuses['wc-paid-1'] = __( 'ชำระแล้ว (ครั้งที่ 1)', $_ENV['TEXTDOMAIN_NAME'] );
    $order_statuses['wc-paid-2'] = __( 'ชำระแล้ว (ครั้งที่ 2)', $_ENV['TEXTDOMAIN_NAME'] );

    // 📦 กลุ่มสถานะ “แพ็คสินค้า”


    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'add_custom_status_to_dropdown' );


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
        .order-status.status-waiting-verification-1,
        mark.status-waiting-verification-1 { background: #d4e7ff; color: #1c4a86; }
        .order-status.status-waiting-verification-2,
        mark.status-waiting-verification-2 { background: #a9cdff; color: #1c4a86; }

        /* ชำระแล้ว ครั้งที่ 1 / 2 */
        .order-status.status-paid-1,
        mark.status-paid-1 { background: #c8e6c9; color: #2e7d32; }
        .order-status.status-paid-2,
        mark.status-paid-2 { background: #81c784; color: #1b5e20; }
    </style>
    <?php
}