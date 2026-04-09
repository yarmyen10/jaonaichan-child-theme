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