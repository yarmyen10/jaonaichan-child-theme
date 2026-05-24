<?php
/**
 * Customers REST API
 *
 * GET /wp-json/jaonaichan/v1/customers
 * GET /wp-json/jaonaichan/v1/customers/{id}/orders
 */
class Customers_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {

        // GET /wp-json/jaonaichan/v1/customers
        //   ?page=1&per_page=20&search=...
        register_rest_route( 'jaonaichan/v1', '/customers', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_customers' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'page'     => [ 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
                'per_page' => [ 'required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
                'search'   => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /wp-json/jaonaichan/v1/customers/{id}/orders
        //   ?page=1&per_page=20
        register_rest_route( 'jaonaichan/v1', '/customers/(?P<id>\d+)/orders', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_customer_orders' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'page'     => [ 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
                'per_page' => [ 'required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
            ],
        ]);
    }

    // =========================================================================
    // GET /customers
    // =========================================================================

    public static function get_customers( WP_REST_Request $request ): WP_REST_Response {
        $page     = max( 1, (int) $request->get_param('page') );
        $per_page = min( 100, max( 1, (int) $request->get_param('per_page') ) );
        $search   = $request->get_param('search') ?? '';

        $query_args = [
            'role__in'    => [ 'customer', 'subscriber' ],
            'number'  => $per_page,
            'offset'  => ( $page - 1 ) * $per_page,
            'orderby' => 'registered',
            'order'   => 'DESC',
            'count_total' => true,
        ];

        if ( $search !== '' ) {
            $query_args['search']         = '*' . $search . '*';
            $query_args['search_columns'] = [ 'user_email', 'display_name', 'user_login' ];

            // also search billing_phone via meta
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'billing_phone',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                // placeholder so WP_User_Query OR-joins correctly with the text search
            ];
        }

        $user_query = new WP_User_Query( $query_args );
        $users      = $user_query->get_results();
        $total      = $user_query->get_total();

        $data = array_map( [ self::class, 'format_customer' ], $users );

        return new WP_REST_Response([
            'data'       => array_values( $data ),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => (int) $total,
                'total_pages' => (int) ceil( $total / $per_page ),
            ],
        ], 200);
    }

    // =========================================================================
    // GET /customers/{id}/orders
    // =========================================================================

    public static function get_customer_orders( WP_REST_Request $request ): WP_REST_Response {
        $customer_id = absint( $request->get_param('id') );
        $page        = max( 1, (int) $request->get_param('page') );
        $per_page    = min( 100, max( 1, (int) $request->get_param('per_page') ) );

        $user = get_userdata( $customer_id );
        if ( ! $user ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่พบลูกค้า' ], 404);
        }

        $orders = wc_get_orders([
            'customer' => $customer_id,
            'limit'    => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ]);

        $total_ids = wc_get_orders([
            'customer' => $customer_id,
            'limit'    => -1,
            'return'   => 'ids',
        ]);

        $data = array_map( fn( $o ) => Orders_API::format_order( $o ), $orders );

        return new WP_REST_Response([
            'data'       => array_values( $data ),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => count( $total_ids ),
                'total_pages' => (int) ceil( count( $total_ids ) / $per_page ),
            ],
        ], 200);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function format_customer( WP_User $user ): array {
        $user_id = $user->ID;

        $first = get_user_meta( $user_id, 'billing_first_name', true );
        $last  = get_user_meta( $user_id, 'billing_last_name', true );
        $name  = trim( "$first $last" );
        if ( $name === '' ) {
            $name = $user->display_name;
        }

        $email = get_user_meta( $user_id, 'billing_email', true );
        if ( $email === '' ) {
            $email = $user->user_email;
        }

        $phone = get_user_meta( $user_id, 'billing_phone', true );

        // wc_get_customer_order_count / wc_get_customer_total_spent are
        // WooCommerce built-ins with transient caching
        $order_count = (int) wc_get_customer_order_count( $user_id );
        $total_spend = (float) wc_get_customer_total_spent( $user_id );

        $last_order_date = null;
        if ( $order_count > 0 ) {
            $last_ids = wc_get_orders([
                'customer' => $user_id,
                'limit'    => 1,
                'orderby'  => 'date',
                'order'    => 'DESC',
                'return'   => 'ids',
            ]);
            if ( ! empty( $last_ids ) ) {
                $last_order = wc_get_order( $last_ids[0] );
                $last_order_date = $last_order?->get_date_created()?->date('Y-m-d H:i:s');
            }
        }

        $roles = $user->roles;
        $role  = ! empty( $roles ) ? reset( $roles ) : '';

        return [
            'id'              => $user_id,
            'name'            => $name,
            'email'           => $email,
            'phone'           => $phone ?: '',
            'role'            => $role,
            'order_count'     => $order_count,
            'total_spend'     => $total_spend,
            'last_order_date' => $last_order_date,
        ];
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }
}

Customers_API::init();
