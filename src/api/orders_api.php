<?php
/**
 * Orders REST API
 */
class Orders_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {

        // GET /wp-json/jaonaichan/v1/orders?page=1&per_page=10
        register_rest_route( 'jaonaichan/v1', '/orders', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_orders' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ]);

        // GET /wp-json/jaonaichan/v1/orders/{id}
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_order_detail' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ]);
    }

    /**
     * GET Orders — พร้อม Paging
     */
    public static function get_orders( WP_REST_Request $request ): WP_REST_Response {
        $page     = max( 1, (int) $request->get_param('page')     ?: 1 );
        $per_page = min( 50, (int) $request->get_param('per_page') ?: 10 );
        $status   = sanitize_text_field( $request->get_param('status') ?: 'any' );

        $orders = wc_get_orders([
            'limit'   => $per_page,
            'offset'  => ( $page - 1 ) * $per_page,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => $status,
        ]);

        // นับจำนวนทั้งหมด
        $total = wc_get_orders([
            'limit'  => -1,
            'status' => $status,
            'return' => 'ids',
        ]);

        $data = array_map( fn($order) => self::format_order( $order ), $orders );

        return new WP_REST_Response([
            'data'       => $data,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => count($total),
                'total_pages' => ceil( count($total) / $per_page ),
            ],
        ], 200);
    }

    /**
     * GET Order Detail — พร้อมรูปสินค้า
     */
    public static function get_order_detail( WP_REST_Request $request ): WP_REST_Response {
        $order_id = absint( $request['id'] );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ไม่พบ Order',
            ], 404);
        }

        return new WP_REST_Response(
            self::format_order( $order, true ), // true = include items
            200
        );
    }

    /**
     * Format Order Data
     */
    private static function format_order( WC_Order $order, bool $with_items = false ): array {
        $data = [
            'id'         => $order->get_id(),
            'number'     => $order->get_order_number(),
            'status'     => $order->get_status(),
            'total'      => (float) $order->get_total(),
            'currency'   => $order->get_currency(),
            'date'       => $order->get_date_created()?->date('Y-m-d H:i:s'),
            'customer'   => [
                'id'    => $order->get_customer_id(),
                'name'  => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'billing' => [
                'address' => $order->get_formatted_billing_address(),
            ],
            'bill1' => [
                'status'   => get_post_meta( $order->get_id(), '_bill1_status', true ) ?: 'pending',
                'amount'   => get_post_meta( $order->get_id(), '_bill1_amount', true ),
                'paid_at'  => get_post_meta( $order->get_id(), '_bill1_paid_at', true ),
            ],
            'bill2' => [
                'status'   => get_post_meta( $order->get_id(), '_bill2_status', true ) ?: 'pending',
                'amount'   => get_post_meta( $order->get_id(), '_bill2_amount', true ),
                'paid_at'  => get_post_meta( $order->get_id(), '_bill2_paid_at', true ),
            ],
        ];

        // เพิ่ม Items เฉพาะ Detail Page
        if ( $with_items ) {
            $data['items'] = array_map( function( $item ) {
                $product  = $item->get_product();
                $image_id = $product?->get_image_id();

                return [
                    'id'       => $item->get_id(),
                    'name'     => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total'    => (float) $item->get_total(),
                    'product'  => [
                        'id'    => $product?->get_id(),
                        'sku'   => $product?->get_sku(),
                        'stock' => $product?->get_stock_quantity(),
                        'image' => [
                            'thumbnail' => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
                            'medium'    => wp_get_attachment_image_url( $image_id, 'medium' ),
                            'full'      => wp_get_attachment_image_url( $image_id, 'full' ),
                        ],
                    ],
                ];
            }, array_values( $order->get_items() ) );
        }

        return $data;
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }
}

Orders_API::init();