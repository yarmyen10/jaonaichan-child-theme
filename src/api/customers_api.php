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

        // POST /wp-json/jaonaichan/v1/customers
        register_rest_route( 'jaonaichan/v1', '/customers', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'create_customer' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'email'      => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
                'first_name' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'last_name'  => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'phone'      => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ]);

        // POST /wp-json/jaonaichan/v1/customers/{id}
        register_rest_route( 'jaonaichan/v1', '/customers/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'update_customer' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'email'      => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
                'first_name' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'last_name'  => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'phone'      => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
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
    // POST /customers
    // =========================================================================

    public static function create_customer( WP_REST_Request $request ): WP_REST_Response {
        $email      = $request->get_param('email');
        $first_name = $request->get_param('first_name');
        $last_name  = $request->get_param('last_name');
        $phone      = $request->get_param('phone');

        if ( empty( $phone ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'กรุณากรอกเบอร์โทรศัพท์' ], 400);
        }

        // Generate username JNC{YY}9999
        $year = date('y');
        $prefix = "JNC{$year}";
        
        global $wpdb;
        $latest_username = $wpdb->get_var( $wpdb->prepare( "
            SELECT user_login FROM {$wpdb->users} 
            WHERE user_login LIKE %s 
            ORDER BY user_login DESC LIMIT 1
        ", $prefix . '%' ) );
        
        if ( $latest_username ) {
            $num = (int) str_replace( $prefix, '', $latest_username );
            $next_num = $num + 1;
        } else {
            $next_num = 1;
        }
        $username = $prefix . str_pad( $next_num, 4, '0', STR_PAD_LEFT );

        $password = $phone;

        if ( empty( $email ) ) {
            $email = strtolower($username) . '@jaonaichan.local';
        } else {
            if ( ! is_email( $email ) ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง' ], 400);
            }
            if ( email_exists( $email ) ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'อีเมลนี้มีอยู่ในระบบแล้ว' ], 400);
            }
        }

        // wc_create_new_customer creates the user and triggers new customer email if WC is configured to do so
        $customer_id = wc_create_new_customer( $email, $username, $password );

        if ( is_wp_error( $customer_id ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => $customer_id->get_error_message() ], 400);
        }

        // Update additional info
        update_user_meta( $customer_id, 'billing_first_name', $first_name );
        update_user_meta( $customer_id, 'billing_last_name', $last_name );
        update_user_meta( $customer_id, 'billing_phone', $phone );

        // Also update standard WP name fields
        wp_update_user([
            'ID'           => $customer_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( "$first_name $last_name" )
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'สร้างลูกค้าใหม่สำเร็จ',
            'data'    => [
                'id'    => $customer_id,
                'email' => $email,
                'name'  => trim( "$first_name $last_name" )
            ]
        ], 201);
    }

    // =========================================================================
    // POST /customers/{id} (Update)
    // =========================================================================

    public static function update_customer( WP_REST_Request $request ): WP_REST_Response {
        $customer_id = absint( $request->get_param('id') );
        $email       = $request->get_param('email');
        $first_name  = $request->get_param('first_name');
        $last_name   = $request->get_param('last_name');
        $phone       = $request->get_param('phone');

        if ( empty( $phone ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'กรุณากรอกเบอร์โทรศัพท์' ], 400);
        }

        $user = get_userdata( $customer_id );
        if ( ! $user ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่พบลูกค้า' ], 404);
        }

        if ( ! empty( $email ) ) {
            if ( ! is_email( $email ) ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง' ], 400);
            }
            $existing_user = get_user_by( 'email', $email );
            if ( $existing_user && $existing_user->ID !== $customer_id ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'อีเมลนี้ถูกใช้งานโดยผู้ใช้อื่นแล้ว' ], 400);
            }
        }

        // Update main user fields
        $update_args = [
            'ID'           => $customer_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( "$first_name $last_name" )
        ];

        if ( ! empty( $email ) ) {
            $update_args['user_email'] = $email;
        }
        
        $updated_id = wp_update_user( $update_args );
        if ( is_wp_error( $updated_id ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => $updated_id->get_error_message() ], 400);
        }

        // Update meta fields
        update_user_meta( $customer_id, 'billing_first_name', $first_name );
        update_user_meta( $customer_id, 'billing_last_name', $last_name );
        if ( ! empty( $email ) ) {
            update_user_meta( $customer_id, 'billing_email', $email );
        }
        update_user_meta( $customer_id, 'billing_phone', $phone );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'อัปเดตข้อมูลลูกค้าสำเร็จ',
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
