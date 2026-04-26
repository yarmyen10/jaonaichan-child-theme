<?php
/**
 * Initialize order status + two-bill meta on order creation.
 */
add_action( 'woocommerce_checkout_order_created', function ( $order ) {
    error_log( sprintf(
        '[bill-meta] hook fired for order #%d, total=%s',
        $order->get_id(),
        $order->get_total()
    ) );

    $order->set_status( 'pending-payment-1' );
    $order->update_meta_data( '_bill1_status', 'pending' );
    $order->update_meta_data( '_bill1_amount', (float) $order->get_total() );
    $order->update_meta_data( '_bill1_paid_at', '' );
    $order->save();
}, 10, 1 );
