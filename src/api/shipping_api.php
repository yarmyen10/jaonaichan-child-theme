<?php
/**
 * Order Shipping REST API
 *
 * PATCH /wp-json/jaonaichan/v1/orders/(?P<id>\d+)/shipping
 */
class Shipping_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)/shipping', [
            [
                'methods'             => 'PATCH',
                'callback'            => [ self::class, 'update_shipping' ],
                'permission_callback' => '__return_true', // Open for customer checkout page (or add specific nonce/permission check if needed)
                'args'                => [
                    'shipping_name'    => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'shipping_phone'   => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'shipping_address' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
                ],
            ],
        ]);
    }

    public static function update_shipping( WP_REST_Request $request ): WP_REST_Response {
        $order_id = $request->get_param('id');
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่พบคำสั่งซื้อ' ], 404);
        }

        $name    = $request->get_param('shipping_name');
        $phone   = $request->get_param('shipping_phone');
        $address = $request->get_param('shipping_address');

        if ( empty($name) || empty($phone) || empty($address) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน' ], 400);
        }

        $parts      = explode(' ', $name, 2);
        $first_name = $parts[0];
        $last_name  = $parts[1] ?? '';

        $order->set_shipping_first_name( $first_name );
        $order->set_shipping_last_name( $last_name );
        
        $order->set_shipping_address_1( $address );
        $order->set_shipping_address_2( '' );
        $order->set_shipping_city( '' );
        $order->set_shipping_state( '' );
        $order->set_shipping_postcode( '' );
        $order->set_shipping_country( 'TH' );

        $order->update_meta_data( '_shipping_phone', $phone );
        if ( empty( $order->get_billing_phone() ) ) {
            $order->set_billing_phone( $phone );
        }

        $order->save();

        return new WP_REST_Response([
            'success' => true,
            'message' => 'อัปเดตข้อมูลจัดส่งเรียบร้อยแล้ว'
        ], 200);
    }
}

Shipping_API::init();
