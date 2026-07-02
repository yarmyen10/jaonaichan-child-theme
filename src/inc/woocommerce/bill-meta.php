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
 * When order status rolls back past the bill2 creation point, wipe all bill2 meta so
 * bill2HasMeta becomes false on the thank-you page.
 * Covers WP-admin status changes that bypass the REST API cleanup logic.
 * Safe to fire alongside the API: if status stays paid-1→paid-1 (no change), WC never fires this hook.
 */
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
    // Statuses where bill2 batch does not yet exist
    $pre_bill2 = [ 'waiting-transfer', 'pending-payment-1', 'wait-verify-1', 'paid-1', 'pending', 'checkout-draft' ];
    // Statuses where bill2 batch has been created
    $post_bill2 = [ 'pending-payment-2', 'wait-verify-2', 'paid-2' ];

    // Only act when rolling BACK from post-bill2 into pre-bill2
    if ( ! in_array( $to, $pre_bill2, true ) || ! in_array( $from, $post_bill2, true ) ) return;
    if ( ! $order->get_meta( '_bill2_status', true ) ) return; // nothing to clean

    foreach ( [
        '_bill2_status', '_bill2_amount', '_bill2_paid_at',
        '_bill2_unit_prices', '_bill2_unit_prices_id',
        '_bill2_china_shipping', '_bill2_import_fee', '_bill2_local_shipping',
    ] as $key ) {
        $order->delete_meta_data( $key );
    }
    $order->save();
}, 10, 4 );

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
