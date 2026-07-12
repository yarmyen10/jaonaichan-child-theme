<?php
/**
 * Customers REST API
 *
 * GET    /wp-json/jaonaichan/v1/customers
 * POST   /wp-json/jaonaichan/v1/customers
 * POST   /wp-json/jaonaichan/v1/customers/{id}
 * PATCH  /wp-json/jaonaichan/v1/customers/{id}/status
 * POST   /wp-json/jaonaichan/v1/customers/{id}/reset-password
 * GET    /wp-json/jaonaichan/v1/customers/{id}/orders
 * GET    /wp-json/jaonaichan/v1/customers/{id}/cart
 */
class Customers_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
        // Block login for inactive accounts
        add_filter( 'authenticate', [ self::class, 'block_inactive_login' ], 30, 1 );
    }

    public static function register_routes(): void {

        register_rest_route( 'jaonaichan/v1', '/customers', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_customers' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'page'     => [ 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
                'per_page' => [ 'required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
                'search'   => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ]);

        register_rest_route( 'jaonaichan/v1', '/customers', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'create_customer' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'username'      => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_user' ],
                'customer_name' => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'phone'         => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'email'         => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
                'status'        => [ 'required' => false, 'type' => 'string', 'enum' => [ 'active', 'inactive' ], 'default' => 'active' ],
            ],
        ]);

        register_rest_route( 'jaonaichan/v1', '/customers/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'update_customer' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'username'      => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_user' ],
                'customer_name' => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'phone'         => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'email'         => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
            ],
        ]);

        register_rest_route( 'jaonaichan/v1', '/customers/(?P<id>\d+)/status', [
            'methods'             => 'PATCH',
            'callback'            => [ self::class, 'set_status' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'status' => [ 'required' => true, 'type' => 'string', 'enum' => [ 'active', 'inactive' ] ],
            ],
        ]);

        register_rest_route( 'jaonaichan/v1', '/customers/(?P<id>\d+)/reset-password', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'reset_password' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'mode'     => [ 'required' => true,  'type' => 'string', 'enum' => [ 'phone', 'manual' ] ],
                'password' => [ 'required' => false, 'type' => 'string' ],
            ],
        ]);

        register_rest_route( 'jaonaichan/v1', '/customers/(?P<id>\d+)/cart', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_customer_cart' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ]);

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
            'number'      => $per_page,
            'offset'      => ( $page - 1 ) * $per_page,
            'orderby'     => 'registered',
            'order'       => 'DESC',
            'count_total' => true,
        ];

        if ( $search !== '' ) {
            $query_args['search']         = '*' . $search . '*';
            $query_args['search_columns'] = [ 'user_email', 'display_name', 'user_login' ];
            $query_args['meta_query']     = [
                'relation' => 'OR',
                [ 'key' => 'billing_phone', 'value' => $search, 'compare' => 'LIKE' ],
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
        $username      = $request->get_param('username');
        $customer_name = $request->get_param('customer_name');
        $phone         = $request->get_param('phone');
        $email         = $request->get_param('email');
        $status        = $request->get_param('status') ?: 'active';

        if ( username_exists( $username ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'Username นี้มีอยู่ในระบบแล้ว' ], 400);
        }

        // Duplicate phone check (active or inactive)
        $phone_clean = preg_replace( '/\D/', '', $phone );
        $existing_phone = get_users([
            'meta_key'   => 'billing_phone',
            'meta_value' => $phone_clean,
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        if ( ! empty( $existing_phone ) ) {
            $existing_status = get_user_meta( $existing_phone[0], 'jnc_account_status', true ) ?: 'active';
            $label = $existing_status === 'inactive' ? ' (ระงับการใช้งาน)' : '';
            return new WP_REST_Response([ 'success' => false, 'message' => "เบอร์โทรนี้มีอยู่ในระบบแล้ว{$label}" ], 400);
        }

        if ( empty( $email ) ) {
            $email = strtolower( $username ) . '@jaonaichan.local';
        } else {
            if ( ! is_email( $email ) ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง' ], 400);
            }
            if ( email_exists( $email ) ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'อีเมลนี้มีอยู่ในระบบแล้ว' ], 400);
            }
        }

        $password    = $phone_clean;
        $customer_id = wc_create_new_customer( $email, $username, $password );

        if ( is_wp_error( $customer_id ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => $customer_id->get_error_message() ], 400);
        }

        wp_update_user([
            'ID'           => $customer_id,
            'display_name' => $customer_name,
        ]);
        update_user_meta( $customer_id, 'billing_phone', $phone_clean );
        update_user_meta( $customer_id, 'jnc_account_status', $status );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'สร้างลูกค้าใหม่สำเร็จ',
            'data'    => [ 'id' => $customer_id, 'username' => $username, 'name' => $customer_name ],
        ], 201);
    }

    // =========================================================================
    // POST /customers/{id}
    // =========================================================================

    public static function update_customer( WP_REST_Request $request ): WP_REST_Response {
        $customer_id   = absint( $request->get_param('id') );
        $username      = $request->get_param('username');
        $customer_name = $request->get_param('customer_name');
        $phone         = $request->get_param('phone');
        $email         = $request->get_param('email');

        $user = get_userdata( $customer_id );
        if ( ! $user ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่พบลูกค้า' ], 404);
        }

        // Username uniqueness check (skip if unchanged)
        if ( $username !== $user->user_login && username_exists( $username ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'Username นี้มีอยู่ในระบบแล้ว' ], 400);
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

        $update_args = [
            'ID'           => $customer_id,
            'display_name' => $customer_name,
        ];
        if ( ! empty( $email ) ) {
            $update_args['user_email'] = $email;
        }

        $updated = wp_update_user( $update_args );
        if ( is_wp_error( $updated ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => $updated->get_error_message() ], 400);
        }

        // Change user_login if different (wp_update_user doesn't support this)
        if ( $username !== $user->user_login ) {
            global $wpdb;
            $wpdb->update( $wpdb->users, [ 'user_login' => $username ], [ 'ID' => $customer_id ] );
            clean_user_cache( $customer_id );
        }

        $phone_clean = preg_replace( '/\D/', '', $phone );
        update_user_meta( $customer_id, 'billing_phone', $phone_clean );
        if ( ! empty( $email ) ) {
            update_user_meta( $customer_id, 'billing_email', $email );
        }

        return new WP_REST_Response([ 'success' => true, 'message' => 'อัปเดตข้อมูลลูกค้าสำเร็จ' ], 200);
    }

    // =========================================================================
    // PATCH /customers/{id}/status
    // =========================================================================

    public static function set_status( WP_REST_Request $request ): WP_REST_Response {
        $customer_id = absint( $request->get_param('id') );
        $status      = $request->get_param('status');

        if ( ! get_userdata( $customer_id ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่พบลูกค้า' ], 404);
        }

        update_user_meta( $customer_id, 'jnc_account_status', $status );

        return new WP_REST_Response([
            'success' => true,
            'message' => $status === 'active' ? 'เปิดใช้งานบัญชีสำเร็จ' : 'ระงับบัญชีสำเร็จ',
        ], 200);
    }

    // =========================================================================
    // POST /customers/{id}/reset-password
    // =========================================================================

    public static function reset_password( WP_REST_Request $request ): WP_REST_Response {
        $customer_id = absint( $request->get_param('id') );
        $mode        = $request->get_param('mode');

        $user = get_userdata( $customer_id );
        if ( ! $user ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่พบลูกค้า' ], 404);
        }

        if ( $mode === 'phone' ) {
            $phone = get_user_meta( $customer_id, 'billing_phone', true );
            if ( empty( $phone ) ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'ลูกค้าไม่มีเบอร์โทรศัพท์' ], 400);
            }
            $new_password = preg_replace( '/\D/', '', $phone );
        } else {
            $new_password = $request->get_param('password');
            if ( empty( $new_password ) || mb_strlen( $new_password ) < 6 ) {
                return new WP_REST_Response([ 'success' => false, 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร' ], 400);
            }
        }

        wp_set_password( $new_password, $customer_id );

        return new WP_REST_Response([ 'success' => true, 'message' => 'รีเซ็ตรหัสผ่านสำเร็จ' ], 200);
    }

    // =========================================================================
    // GET /customers/{id}/cart
    // =========================================================================

    public static function get_customer_cart( WP_REST_Request $request ): WP_REST_Response {
        $user_id = absint( $request->get_param('id') );

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s",
            (string) $user_id
        ) );

        $empty = [ 'items' => [], 'subtotal' => 0.0, 'total' => 0.0 ];

        if ( ! $row ) {
            return new WP_REST_Response([ 'data' => $empty ], 200);
        }

        $session  = maybe_unserialize( $row->session_value );
        $raw_cart = $session['cart'] ?? [];
        $totals   = $session['cart_totals'] ?? [];

        $items = [];
        foreach ( $raw_cart as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product ) continue;
            $items[] = [
                'product_id' => $item['product_id'],
                'name'       => $product->get_name(),
                'quantity'   => (int) $item['quantity'],
                'price'      => (float) $product->get_price(),
                'line_total' => (float) ( $item['line_total'] ?? 0 ),
            ];
        }

        return new WP_REST_Response([
            'data' => [
                'items'    => $items,
                'subtotal' => (float) ( $totals['subtotal'] ?? array_sum( array_column( $items, 'line_total' ) ) ),
                'total'    => (float) ( $totals['total'] ?? array_sum( array_column( $items, 'line_total' ) ) ),
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

        $name = $user->display_name ?: $user->user_login;

        $email = get_user_meta( $user_id, 'billing_email', true ) ?: $user->user_email;
        $phone = get_user_meta( $user_id, 'billing_phone', true ) ?: '';

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
                $last_order      = wc_get_order( $last_ids[0] );
                $last_order_date = $last_order?->get_date_created()?->date('Y-m-d H:i:s');
            }
        }

        $roles = $user->roles;
        $role  = ! empty( $roles ) ? reset( $roles ) : '';

        $status = get_user_meta( $user_id, 'jnc_account_status', true ) ?: 'active';

        return [
            'id'              => $user_id,
            'username'        => $user->user_login,
            'name'            => $name,
            'email'           => $email,
            'phone'           => $phone,
            'role'            => $role,
            'status'          => $status,
            'order_count'     => $order_count,
            'total_spend'     => $total_spend,
            'member_date'     => $user->user_registered,
            'last_order_date' => $last_order_date,
        ];
    }

    public static function block_inactive_login( $user ) {
        if ( ! ( $user instanceof WP_User ) ) {
            return $user;
        }
        $status = get_user_meta( $user->ID, 'jnc_account_status', true ) ?: 'active';
        if ( $status === 'inactive' ) {
            return new WP_Error( 'account_inactive', 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อเจ้าหน้าที่' );
        }
        return $user;
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }
}

Customers_API::init();
