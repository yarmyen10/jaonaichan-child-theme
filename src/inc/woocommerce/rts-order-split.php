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

// ถ้าไม่มี product tag rts เลยในตะกร้า → ไม่คำนวณ shipping เลย
// เพิ่ม jn_rts_cost เข้า package เพื่อให้ WC hash เปลี่ยนเมื่อ cost เปลี่ยน → recalculate โดยไม่ต้อง clear cart
add_filter( 'woocommerce_cart_shipping_packages', function ( array $packages ): array {
    $has_rts = false;
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( jn_product_is_rts( (int) $item['product_id'] ) ) {
            $has_rts = true;
            break;
        }
    }
    if ( ! $has_rts ) return [];

    $cost = get_option( 'jn_rts_shipping_cost', '0' );
    foreach ( $packages as &$package ) {
        // ตั้ง country เพื่อให้ WC zone matching ทำงานแม้ยังไม่ได้กรอก address
        if ( empty( $package['destination']['country'] ) ) {
            $package['destination']['country'] = 'TH';
        }
        $package['jn_rts_cost'] = $cost;
    }
    return $packages;
} );

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
    // _bill1_amount was set to 0 by bill-meta.php (woocommerce_new_order fires before items exist)
    // At this hook WC has already called calculate_totals(), so get_total() includes shipping
    if ( empty( $normal_items ) && ! empty( $rts_items ) ) {
        $order->update_meta_data( '_is_rts_order', '1' );
        $order->update_meta_data( '_bill1_amount', (float) $order->get_total() );
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

    // Look up shipping cost from WC zone named 'rts' — WC admin is source of truth
    $rts_ship_cost  = 0.0;
    $rts_ship_title = 'RTS Shipping';
    $rts_ship_id    = 'flat_rate';
    foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
        if ( strtolower( $zone['zone_name'] ) === 'rts' ) {
            foreach ( $zone['shipping_methods'] as $method ) {
                if ( $method->is_enabled() ) {
                    $rts_ship_cost  = (float) $method->cost;
                    $rts_ship_title = $method->get_title();
                    $rts_ship_id    = $method->id . ':' . $method->instance_id;
                    break;
                }
            }
            break;
        }
    }

    // Remove shipping from main order (bill 2 handles its own shipping later)
    foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
        $order->remove_item( $shipping_item->get_id() );
    }

    // Add RTS shipping to RTS order
    if ( $rts_ship_cost > 0 ) {
        $new_shipping = new WC_Order_Item_Shipping();
        $new_shipping->set_method_title( $rts_ship_title );
        $new_shipping->set_method_id( $rts_ship_id );
        $new_shipping->set_total( $rts_ship_cost );
        $rts_order->add_item( $new_shipping );
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
