<?php
/**
 * Initialize order status + two-bill meta on order creation.
 */
add_action( 'woocommerce_new_order', function ( $order_id, $order ) {
    error_log( sprintf(
        '[bill-meta] woocommerce_new_order fired for order #%d, total=%s',
        $order_id,
        $order->get_total()
    ) );

    $order->set_status( 'pending-payment-1' );
    $order->update_meta_data( '_bill1_status', 'pending' );
    $order->update_meta_data( '_bill1_amount', (float) $order->get_total() );
    $order->update_meta_data( '_bill1_paid_at', '' );
    $order->save();

    $fresh = wc_get_order( $order_id );
    error_log( sprintf(
        '[bill-meta] after save: _bill1_status=%s, _bill1_amount=%s, _bill1_paid_at=%s',
        var_export( $fresh->get_meta( '_bill1_status' ), true ),
        var_export( $fresh->get_meta( '_bill1_amount' ), true ),
        var_export( $fresh->get_meta( '_bill1_paid_at' ), true )
    ) );
}, 10, 2 );

/**
 * Clean up all _bill* meta when order is permanently deleted.
 */
add_action( 'woocommerce_before_delete_order', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    foreach ( $order->get_meta_data() as $meta ) {
        if ( str_starts_with( $meta->key, '_bill' ) ) {
            $order->delete_meta_data_by_mid( $meta->id );
        }
    }
    $order->save();
} );
