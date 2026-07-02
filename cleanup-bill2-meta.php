<?php
/**
 * One-time cleanup: clear stale _bill2_* meta from orders that are
 * at a pre-bill2 status but still have bill2 data from a previous rollback.
 *
 * Run via WP-CLI:
 *   wp eval-file cleanup-bill2-meta.php --path=/path/to/wordpress
 *
 * Or via PHP:
 *   define('ABSPATH', '/path/to/wordpress/');
 *   require ABSPATH . 'wp-load.php';
 *   // then paste the code below
 */

$pre_bill2 = [ 'wc-paid-1', 'wc-wait-verify-1', 'wc-waiting-transfer', 'wc-pending-payment-1', 'wc-pending', 'wc-checkout-draft' ];

$bill2_keys = [
    '_bill2_status', '_bill2_amount', '_bill2_paid_at',
    '_bill2_unit_prices', '_bill2_unit_prices_id',
    '_bill2_china_shipping', '_bill2_import_fee', '_bill2_local_shipping',
];

$orders = wc_get_orders([
    'status' => $pre_bill2,
    'limit'  => -1,
    'meta_query' => [[
        'key'     => '_bill2_status',
        'value'   => '',
        'compare' => '!=',
    ]],
]);

echo sprintf( "Found %d orders to clean.\n", count( $orders ) );

$cleaned = 0;
foreach ( $orders as $order ) {
    $had_data = false;
    foreach ( $bill2_keys as $key ) {
        if ( $order->get_meta( $key, true ) !== '' ) {
            $order->delete_meta_data( $key );
            $had_data = true;
        }
    }
    if ( $had_data ) {
        $order->save();
        $cleaned++;
        echo sprintf( "  Cleaned order #%s (status: %s)\n", $order->get_id(), $order->get_status() );
    }
}

echo sprintf( "Done. Cleaned %d orders.\n", $cleaned );
