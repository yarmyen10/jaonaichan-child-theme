<?php

/**
 * RTS (ready-to-ship) order split.
 *
 * Products tagged rts* or ready_to_ship* are in-stock items that only need
 * Bill 1.  When a checkout contains both RTS and normal items, this hook
 * splits them into two separate WC orders so each follows its own billing
 * flow.
 */

function jn_product_is_rts( int $product_id ): bool {
    // Tags live on the parent; for variations get_id() returns the variation ID
    $product = wc_get_product( $product_id );
    $lookup_id = ( $product && $product->is_type( 'variation' ) )
        ? $product->get_parent_id()
        : $product_id;

    $terms = wp_get_post_terms( $lookup_id, 'product_tag', [ 'fields' => 'slugs' ] );
    if ( is_wp_error( $terms ) ) return false;
    foreach ( $terms as $slug ) {
        if ( str_starts_with( $slug, 'rts' ) || str_starts_with( $slug, 'ready_to_ship' ) ) {
            return true;
        }
    }
    return false;
}

add_action( 'woocommerce_checkout_order_created', function ( WC_Order $order ) {
    // Guard: skip orders we created programmatically to avoid re-entry
    if ( $order->get_meta( '_is_rts_order' ) !== '' || $order->get_meta( '_parent_order_id' ) !== '' ) {
        return;
    }

    $rts_items    = [];
    $normal_items = [];

    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        if ( ! $product ) continue;
        if ( jn_product_is_rts( $product->get_id() ) ) {
            $rts_items[ $item_id ] = $item;
        } else {
            $normal_items[ $item_id ] = $item;
        }
    }

    // All-RTS: flag order, no split needed
    if ( empty( $normal_items ) && ! empty( $rts_items ) ) {
        $order->update_meta_data( '_is_rts_order', '1' );
        $order->save();
        return;
    }

    // All-normal: nothing to do
    if ( empty( $rts_items ) ) {
        return;
    }

    // Mixed: create a separate RTS order for the in-stock items
    $rts_order = wc_create_order( [
        'customer_id' => $order->get_customer_id(),
        'status'      => 'pending-payment-1',
    ] );

    $rts_order->set_address( $order->get_address( 'billing' ), 'billing' );
    $rts_order->set_payment_method( $order->get_payment_method() );
    $rts_order->set_payment_method_title( $order->get_payment_method_title() );
    $rts_order->set_currency( $order->get_currency() );
    $rts_order->set_customer_note( $order->get_customer_note() );

    foreach ( $rts_items as $item ) {
        $new_item = new WC_Order_Item_Product();
        $new_item->set_product( $item->get_product() );
        $new_item->set_quantity( $item->get_quantity() );
        $new_item->set_subtotal( $item->get_subtotal() );
        $new_item->set_total( $item->get_total() );
        $rts_order->add_item( $new_item );

        // remove_item() purges from in-memory cache + marks for DB delete on save()
        // wc_delete_order_item() only hits DB, leaving the cache stale for calculate_totals()
        $order->remove_item( $item->get_id() );
    }

    $rts_order->calculate_totals();
    $order->calculate_totals();

    // woocommerce_new_order set _bill1_amount before items existed — fix both
    $rts_order->update_meta_data( '_bill1_amount', (float) $rts_order->get_total() );
    $order->update_meta_data( '_bill1_amount', (float) $order->get_total() );

    $rts_order->update_meta_data( '_is_rts_order',    '1' );
    $rts_order->update_meta_data( '_parent_order_id', $order->get_id() );
    $order->update_meta_data( '_linked_rts_order_id', $rts_order->get_id() );

    $rts_order->save();
    $order->save();
} );
