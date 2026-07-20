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
        return self::verified_customer_id() > 0;
    }

    /**
     * Re-derive identity from the visitor's own native WordPress login cookie only —
     * bypasses get_current_user_id()/is_user_logged_in(), which resolve through the
     * determine_current_user filter chain and can be overridden by any
     * Authorization-header-based auth mechanism (e.g. a stale admin JWT cookie on a
     * shared browser winning over the customer's own session). These customer-only
     * endpoints must never trust anything but the cookie WordPress itself issued to
     * whoever is actually sitting at this browser right now.
     */
    private static function verified_customer_id(): int {
        return (int) wp_validate_auth_cookie( '', 'logged_in' );
    }

    // =========================================================================
    // GET /my-summary
    // =========================================================================

    public static function get_summary(): WP_REST_Response {
        $user_id = self::verified_customer_id();

        $order_count = count( wc_get_orders([
            'customer' => $user_id,
            'status'   => 'any',
            'type'     => 'shop_order',
            'limit'    => -1,
            'return'   => 'ids',
        ]));

        // Count from bill-1 paid onwards; bill-1-only statuses use bill1_amount, rest use order total.
        $bill1_only_statuses = [ 'paid-1', 'pending-payment-2', 'wait-verify-2' ];
        $paid_statuses = array_merge( $bill1_only_statuses, [
            'paid-2', 'packed', 'wait-tracking', 'tracked', 'wait-shipping', 'shipped',
            'processing', 'completed',
        ]);
        $paid_orders = wc_get_orders([
            'customer' => $user_id,
            'status'   => $paid_statuses,
            'type'     => 'shop_order',
            'limit'    => -1,
        ]);
        $total_spent = 0.0;
        foreach ( $paid_orders as $o ) {
            if ( in_array( $o->get_status(), $bill1_only_statuses, true ) ) {
                $b1 = (float) ( $o->get_meta( '_bill1_amount' ) ?: 0 );
                $total_spent += $b1 > 0 ? $b1 : (float) $o->get_total();
            } else {
                $total_spent += (float) $o->get_total();
            }
        }

        $recent = wc_get_orders([
            'customer' => $user_id,
            'status'   => 'any',
            'type'     => 'shop_order',
            'limit'    => 5,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ]);

        $last_order_date = ! empty( $recent )
            ? $recent[0]->get_date_created()?->date( 'Y-m-d H:i:s' )
            : null;

        $response = new WP_REST_Response([
            'order_count'     => $order_count,
            'total_spent'     => round( $total_spent, 2 ),
            'last_order_date' => $last_order_date,
            'recent_orders'   => array_values( array_map(
                [ self::class, 'format_order_brief' ],
                $recent
            )),
        ], 200);

        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
        $response->header( 'Pragma', 'no-cache' );

        return $response;
    }

    // =========================================================================
    // GET /my-orders
    // =========================================================================

    public static function get_orders( WP_REST_Request $request ): WP_REST_Response {
        $user_id  = self::verified_customer_id();
        $page     = (int) $request->get_param( 'page' );
        $per_page = (int) $request->get_param( 'per_page' );
        $status   = $request->get_param( 'status' );

        $base_args = [
            'customer' => $user_id,
            'status'   => $status,
            'type'     => 'shop_order',
            'orderby'  => 'date',
            'order'    => 'DESC',
        ];

        $orders = wc_get_orders( array_merge( $base_args, [
            'limit'  => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
        ]));

        // Hard filter — กัน order ของคนอื่นไม่ว่า WC query จะ behave ยังไง
        $orders = array_values( array_filter( $orders, fn( $o ) => (int) $o->get_customer_id() === $user_id ) );

        $total = count( wc_get_orders( array_merge( $base_args, [
            'limit'  => -1,
            'return' => 'ids',
        ])));

        $response = new WP_REST_Response([
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

        // ห้าม caching plugin เก็บ response นี้ — ข้อมูล user-specific
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
        $response->header( 'Pragma', 'no-cache' );

        return $response;
    }

    // =========================================================================
    // Format helper — customer-facing (no other customers' data)
    // =========================================================================

    private static function format_order_brief( WC_Order $order ): array {
        $items = $order->get_items();
        $names = array_values( array_map( fn( $i ) => $i->get_name(), $items ) );
        $more  = max( 0, count( $names ) - 3 );

        // Bill 2 still a draft → pretend it hasn't started yet, same fiction thank-you.php
        // already tells the customer, so the "My Orders" list can't leak an unpublished price.
        $is_bill2_draft = $order->get_meta( '_bill2_status', true ) === 'draft';
        $status = $order->get_status();

        return [
            'id'             => $order->get_id(),
            'number'         => $order->get_order_number(),
            'status'         => $status,
            'total'          => (float) $order->get_total(),
            'currency'       => $order->get_currency(),
            'date'           => $order->get_date_created()?->date( 'Y-m-d H:i:s' ),
            'item_count'     => count( $items ),
            'product_names'  => array_slice( $names, 0, 3 ),
            'more_items'     => $more,
            'payment_method' => $order->get_payment_method_title(),
            'view_url'       => $order->get_view_order_url(),
            'is_rts'         => $order->get_meta( '_is_rts_order', true ) === '1',
            'bill1'          => [
                'amount' => (float) ( $order->get_meta( '_bill1_amount' ) ?: 0 ),
            ],
            'bill2'          => [
                'amount' => $is_bill2_draft ? 0.0 : (float) ( $order->get_meta( '_bill2_amount' ) ?: 0 ),
                'status' => $order->get_meta( '_bill2_status', true ) ?: null,
            ],
        ];
    }
}

My_Orders_API::init();
