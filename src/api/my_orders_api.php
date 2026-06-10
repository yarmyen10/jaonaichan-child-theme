<?php
/**
 * My Orders REST API — customer-scoped endpoints
 *
 * GET /wp-json/bigboss-auth/v1/my-summary
 * GET /wp-json/bigboss-auth/v1/my-orders?page=1&per_page=10&status=any
 */
class My_Orders_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'bigboss-auth/v1', '/my-summary', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_summary' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ]);

        register_rest_route( 'bigboss-auth/v1', '/my-orders', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_orders' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'page'     => [ 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
                'per_page' => [ 'required' => false, 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 50 ],
                'status'   => [ 'required' => false, 'type' => 'string', 'default' => 'any', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ]);
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }

    // =========================================================================
    // GET /my-summary
    // =========================================================================

    public static function get_summary(): WP_REST_Response {
        $user_id = get_current_user_id();

        $order_count = count( wc_get_orders([
            'customer_id' => $user_id,
            'status'      => 'any',
            'type'        => 'shop_order',
            'limit'       => -1,
            'return'      => 'ids',
        ]));

        $total_spent = (float) wc_get_customer_total_spent( $user_id );

        $recent = wc_get_orders([
            'customer_id' => $user_id,
            'status'      => 'any',
            'type'        => 'shop_order',
            'limit'       => 5,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);

        $last_order_date = ! empty( $recent )
            ? $recent[0]->get_date_created()?->date( 'Y-m-d H:i:s' )
            : null;

        return new WP_REST_Response([
            'order_count'     => $order_count,
            'total_spent'     => round( $total_spent, 2 ),
            'last_order_date' => $last_order_date,
            'recent_orders'   => array_values( array_map(
                [ self::class, 'format_order_brief' ],
                $recent
            )),
        ], 200);
    }

    // =========================================================================
    // GET /my-orders
    // =========================================================================

    public static function get_orders( WP_REST_Request $request ): WP_REST_Response {
        $user_id  = get_current_user_id();
        $page     = (int) $request->get_param( 'page' );
        $per_page = (int) $request->get_param( 'per_page' );
        $status   = $request->get_param( 'status' );

        $base_args = [
            'customer_id' => $user_id,
            'status'      => $status,
            'type'        => 'shop_order',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ];

        $orders = wc_get_orders( array_merge( $base_args, [
            'limit'  => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
        ]));

        $total = count( wc_get_orders( array_merge( $base_args, [
            'limit'  => -1,
            'return' => 'ids',
        ])));

        return new WP_REST_Response([
            'data'       => array_values( array_map(
                [ self::class, 'format_order_brief' ],
                $orders
            )),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil( $total / $per_page ),
            ],
        ], 200);
    }

    // =========================================================================
    // Format helper — customer-facing (no other customers' data)
    // =========================================================================

    private static function format_order_brief( WC_Order $order ): array {
        $items = $order->get_items();
        $names = array_values( array_map( fn( $i ) => $i->get_name(), $items ) );
        $more  = max( 0, count( $names ) - 3 );

        return [
            'id'             => $order->get_id(),
            'number'         => $order->get_order_number(),
            'status'         => $order->get_status(),
            'total'          => (float) $order->get_total(),
            'currency'       => $order->get_currency(),
            'date'           => $order->get_date_created()?->date( 'Y-m-d H:i:s' ),
            'item_count'     => count( $items ),
            'product_names'  => array_slice( $names, 0, 3 ),
            'more_items'     => $more,
            'payment_method' => $order->get_payment_method_title(),
            'view_url'       => $order->get_view_order_url(),
            'bill1'          => [
                'amount' => (float) ( $order->get_meta( '_bill1_amount' ) ?: 0 ),
            ],
            'bill2'          => [
                'amount' => (float) ( $order->get_meta( '_bill2_amount' ) ?: 0 ),
            ],
        ];
    }
}

My_Orders_API::init();
