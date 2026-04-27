<?php
/**
 * Empty the cart after a frontend checkout creates an order.
 *
 * The PromptPay gateway leaves payment async (slip upload), so it doesn't
 * call empty_cart() the way a synchronous gateway would. Hook here to make
 * sure the cart is clear by the time the customer lands on thank-you.
 */
add_action( 'woocommerce_checkout_order_processed', function ( $order_id, $posted_data, $order ) {
    if ( WC()->cart instanceof WC_Cart ) {
        WC()->cart->empty_cart();
    }
}, 10, 3 );
