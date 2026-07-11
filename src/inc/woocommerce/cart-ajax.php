<?php
/**
 * Ajax endpoint for the custom cart page (src/templates/woocommerce/cart.php).
 *
 * Classic WooCommerce only ships a full-page-reload "update_cart" form handler —
 * no ajax action for changing a line item's quantity. This adds one, matching
 * the wc-ajax=checkout / wc-ajax=apply_coupon pattern checkout.php already
 * relies on. quantity=0 removes the line (WC_Cart::set_quantity does this
 * natively), so one endpoint covers both update and remove.
 */

add_action( 'wc_ajax_nopriv_jn_update_cart_item', function () {
    wp_send_json_error( [ 'message' => 'login_required' ], 401 );
} );

add_action( 'wc_ajax_jn_update_cart_item', 'jn_update_cart_item' );

function jn_update_cart_item(): void {
    check_ajax_referer( 'jn-cart-update', 'security' );

    $cart = WC()->cart;
    $key  = isset( $_POST['cart_item_key'] ) ? wc_clean( wp_unslash( $_POST['cart_item_key'] ) ) : '';
    $qty  = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;

    $cart_contents = $cart->get_cart();
    if ( ! $key || ! isset( $cart_contents[ $key ] ) ) {
        wp_send_json_error( [ 'message' => 'item_not_found' ], 404 );
    }

    // Never trust the client-supplied quantity outright — clamp to available stock.
    if ( $qty > 0 ) {
        $product = $cart_contents[ $key ]['data'];
        if ( $product->managing_stock() && ! $product->backorders_allowed() ) {
            $stock = $product->get_stock_quantity();
            if ( null !== $stock ) {
                $qty = min( $qty, max( 0, $stock ) );
            }
        }
    }

    $cart->set_quantity( $key, $qty, true );

    $updated  = $cart->get_cart();
    $removed  = ! isset( $updated[ $key ] );
    $item_out = null;
    if ( ! $removed ) {
        $item_out = [
            'key'        => $key,
            'quantity'   => $updated[ $key ]['quantity'],
            'line_total' => wc_price( $updated[ $key ]['line_total'] ),
        ];
    }

    $has_rts = $has_normal = false;
    foreach ( $updated as $item ) {
        jn_product_is_rts( $item['data']->get_id() ) ? ( $has_rts = true ) : ( $has_normal = true );
    }

    wp_send_json_success( [
        'removed'       => $removed,
        'item'          => $item_out,
        'has_rts'       => $has_rts,
        'has_normal'    => $has_normal,
        'is_mixed_cart' => $has_rts && $has_normal,
        'totals'        => [
            'subtotal' => wc_price( $cart->get_subtotal() ),
            'shipping' => $cart->get_shipping_total() > 0 ? wc_price( $cart->get_shipping_total() ) : null,
            'discount' => $cart->get_discount_total() > 0 ? wc_price( $cart->get_discount_total() ) : null,
            'total'    => wc_price( (float) $cart->get_total( 'edit' ) ),
        ],
    ] );
}
