<?php
/**
 * Override the post-checkout return URL to point at our custom thank-you page.
 */
add_filter( 'woocommerce_get_return_url', function ( $return_url, $order ) {
    return home_url( '/thank-you-slave/?wcf-order=' . $order->get_id() );
}, 10, 2 );

/**
 * Catch gateways that build their own redirect URL inside process_payment()
 * without routing through get_return_url() — rewrite at the AJAX response layer.
 */
add_filter( 'woocommerce_payment_successful_result', function ( $result, $order_id ) {
    if ( isset( $result['result'] ) && $result['result'] === 'success' ) {
        $result['redirect'] = home_url( '/thank-you-slave/?wcf-order=' . $order_id );
    }
    return $result;
}, 10, 2 );
