<?php
/**
 * Custom Order Status
 */
function register_custom_order_status() {
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
}
// ✅ เปลี่ยน priority เป็น 5 → รันหลัง textdomain โหลด (priority 1)
add_action( 'init', 'register_custom_order_status', 5 );

function add_custom_status_to_dropdown( $order_statuses ) {
    $order_statuses['wc-waiting-transfer'] = __( 'รอโอนเงิน', $_ENV['TEXTDOMAIN_NAME'] );
    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'add_custom_status_to_dropdown' );