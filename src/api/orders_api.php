<?php
/**
 * Orders REST API
 */
class Orders_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {

        // ✅ specific routes ขึ้นก่อน
        // -----------------------------------------------------------------------
        // GET /wp-json/jaonaichan/v1/orders/products
        //   ?status=processing          (required)
        //   &format=grouped|flat        (default: grouped)
        //   &page=1&per_page=10
        // -----------------------------------------------------------------------
        register_rest_route( 'jaonaichan/v1', '/orders/products', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_products_by_order_status' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'status'   => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [ self::class, 'validate_order_status' ],
                ],
                'format'   => [
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'grouped',
                    'enum'     => [ 'grouped', 'flat' ],
                ],
                'page'     => [ 'required' => false, 'type' => 'integer', 'default' => 1 ],
                'per_page' => [ 'required' => false, 'type' => 'integer', 'default' => 10 ],
            ],
        ]);

        // GET  /wp-json/jaonaichan/v1/orders/products/bulk
        //   ?statuses=processing,completed,on-hold   (คั่นด้วย comma)
        //   ?statuses=all                             (ทุก status)
        //   &page=1&per_page=20
        //
        // POST /wp-json/jaonaichan/v1/orders/products/bulk
        //   body: { "order_ids": [101,102,...], "statuses": "processing,completed", "page": 1, "per_page": 20 }
        //   order_ids คือ JSON array ของ integer — ใช้ POST body เพื่อรองรับจำนวนมากโดยไม่ติด URL length limit
        //   statuses ใน POST เป็น optional filter เสริม
        register_rest_route( 'jaonaichan/v1', '/orders/products/bulk', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'get_products_bulk' ],
                'permission_callback' => [ self::class, 'check_permission' ],
                'args'                => [
                    'statuses' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'comma-separated statuses หรือ "all"',
                    ],
                    'page'     => [ 'required' => false, 'type' => 'integer', 'default' => 1 ],
                    'per_page' => [ 'required' => false, 'type' => 'integer', 'default' => 20 ],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'get_products_bulk_by_ids' ],
                'permission_callback' => [ self::class, 'check_permission' ],
                'args'                => [
                    'order_ids' => [
                        'required'    => true,
                        'type'        => 'array',
                        'items'       => [ 'type' => 'integer', 'minimum' => 1 ],
                        'description' => 'JSON array of order IDs',
                    ],
                    'statuses'  => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'comma-separated statuses หรือ "all" (optional filter)',
                    ],
                    'page'     => [ 'required' => false, 'type' => 'integer', 'default' => 1 ],
                    'per_page' => [ 'required' => false, 'type' => 'integer', 'default' => 20 ],
                ],
            ],
        ]);

        // ✅ wildcard routes ลงหลัง
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

        // GET /wp-json/jaonaichan/v1/orders/{id}/products
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)/products', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_order_products' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ]);        

        // PATCH /wp-json/jaonaichan/v1/orders/{id}/status
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)/status', [
            'methods'             => 'PATCH',
            'callback'            => [ self::class, 'update_order_status' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'status' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [ self::class, 'validate_order_status' ],
                ],
            ],
        ]);

        // PATCH /wp-json/jaonaichan/v1/orders/{id}/note
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)/note', [
            'methods'             => 'PATCH',
            'callback'            => [ self::class, 'update_order_note' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'note'             => [ 'required' => true,  'type' => 'string' ],
                'is_customer_note' => [ 'required' => false, 'type' => 'boolean', 'default' => false ],
            ],
        ]);

        // PATCH /wp-json/jaonaichan/v1/orders/{id}/customer
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)/customer', [
            'methods'             => 'PATCH',
            'callback'            => [ self::class, 'update_order_customer' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ]);

        // PATCH /wp-json/jaonaichan/v1/orders/{id}/bill/{bill_number}
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)/bill/(?P<bill_number>[12])', [
            'methods'             => 'PATCH',
            'callback'            => [ self::class, 'update_order_bill' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'status'  => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [ self::class, 'validate_bill_status' ],
                ],
                'amount'  => [ 'required' => false, 'type' => 'number' ],
                'paid_at' => [ 'required' => false, 'type' => 'string' ],
            ],
        ]);
    }

    // =========================================================================
    // GET /orders/products?status=...&format=grouped|flat
    // =========================================================================

    public static function get_products_by_order_status( WP_REST_Request $request ): WP_REST_Response {
        $status   = $request->get_param('status');
        $format   = $request->get_param('format');   // 'grouped' | 'flat'
        $page     = max( 1, (int) $request->get_param('page') );
        $per_page = min( 50, (int) $request->get_param('per_page') );

        $orders = wc_get_orders([
            'status'  => $status,
            'limit'   => $per_page,
            'offset'  => ( $page - 1 ) * $per_page,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        $total_orders = wc_get_orders([
            'status' => $status,
            'limit'  => -1,
            'return' => 'ids',
        ]);

        $data = $format === 'flat'
            ? self::build_flat( $orders )
            : self::build_grouped( $orders );

        return new WP_REST_Response([
            'format'     => $format,
            'status'     => $status,
            'data'       => $data,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => count( $total_orders ),
                'total_pages' => ceil( count( $total_orders ) / $per_page ),
            ],
        ], 200);
    }

    // =========================================================================
    // GET /orders/products/bulk?statuses=processing,completed&page=1
    // =========================================================================

    public static function get_products_bulk( WP_REST_Request $request ): WP_REST_Response {
        $page     = max( 1, (int) $request->get_param('page') );
        $per_page = min( 50, (int) $request->get_param('per_page') );
        $raw      = $request->get_param('statuses');

        if ( $raw === 'all' ) {
            $statuses = 'any';
        } else {
            $statuses = array_filter(
                array_map( 'trim', explode( ',', $raw ) )
            );

            $invalid = array_filter( $statuses, fn( $s ) => ! self::validate_order_status( $s ) );
            if ( ! empty( $invalid ) ) {
                return new WP_REST_Response([
                    'success'        => false,
                    'message'        => 'status ไม่ถูกต้อง: ' . implode( ', ', $invalid ),
                    'valid_statuses' => array_keys( wc_get_order_statuses() ),
                ], 400);
            }
        }

        $orders = wc_get_orders([
            'status'  => $statuses,
            'limit'   => $per_page,
            'offset'  => ( $page - 1 ) * $per_page,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        $total_ids = wc_get_orders([
            'status' => $statuses,
            'limit'  => -1,
            'return' => 'ids',
        ]);

        // build flat list — ใช้ field names เดียวกับ format_order()
        $flat = [];
        foreach ( $orders as $order ) {
            $order_id = $order->get_id();

            foreach ( $order->get_items() as $item ) {
                $formatted = self::format_order_item( $item );
                if ( ! $formatted ) continue;

                $flat[] = array_merge([
                    'id'       => $order_id,
                    'number'   => $order->get_order_number(),
                    'status'   => $order->get_status(),
                    'date'     => $order->get_date_created()?->date('Y-m-d H:i:s'),
                    'total'    => (float) $order->get_total(),
                    'currency' => $order->get_currency(),
                    'customer' => [
                        'id'    => $order->get_customer_id(),
                        'name'  => $order->get_formatted_billing_full_name(),
                        'email' => $order->get_billing_email(),
                        'phone' => $order->get_billing_phone(),
                    ],
                    'bill1' => [
                        'status' => get_post_meta( $order_id, '_bill1_status', true ) ?: 'pending',
                        'amount' => (float) ( get_post_meta( $order_id, '_bill1_amount', true ) ?: 0 ),
                    ],
                    'bill2' => [
                        'status' => get_post_meta( $order_id, '_bill2_status', true ) ?: 'pending',
                        'amount' => (float) ( get_post_meta( $order_id, '_bill2_amount', true ) ?: 0 ),
                    ],
                ], $formatted );
            }
        }

        // สรุปแยกตาม status
        $status_summary = [];
        foreach ( $flat as $row ) {
            $s = $row['status'];
            if ( ! isset( $status_summary[ $s ] ) ) {
                $status_summary[ $s ] = [ 'order_count' => 0, 'item_count' => 0, 'total' => 0 ];
            }
            $status_summary[ $s ]['item_count']++;
            $status_summary[ $s ]['total'] += $row['total'];
        }
        $seen_orders = [];
        foreach ( $flat as $row ) {
            $key = $row['status'] . '_' . $row['id'];
            if ( ! isset( $seen_orders[ $key ] ) ) {
                $seen_orders[ $key ] = true;
                $status_summary[ $row['status'] ]['order_count']++;
            }
        }

        return new WP_REST_Response([
            'statuses'   => $raw,
            'summary'    => $status_summary,
            'data'       => $flat,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => count( $total_ids ),
                'total_pages' => ceil( count( $total_ids ) / $per_page ),
                'total_items' => count( $flat ),
            ],
        ], 200);
    }

    // =========================================================================
    // POST /orders/products/bulk  — body: { order_ids: [101,102,...], statuses?, page?, per_page? }
    // =========================================================================

    public static function get_products_bulk_by_ids( WP_REST_Request $request ): WP_REST_Response {
        $page     = max( 1, (int) $request->get_param('page') );
        $per_page = min( 100, (int) $request->get_param('per_page') );

        $order_ids = array_values( array_filter(
            array_map( 'absint', (array) $request->get_param('order_ids') )
        ));

        if ( empty( $order_ids ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'order_ids ต้องมีค่าอย่างน้อยหนึ่งรายการ',
            ], 400);
        }

        $raw_statuses = $request->get_param('statuses');
        $statuses     = 'any';

        if ( $raw_statuses !== null && $raw_statuses !== '' ) {
            if ( $raw_statuses !== 'all' ) {
                $parsed  = array_filter( array_map( 'trim', explode( ',', $raw_statuses ) ) );
                $invalid = array_filter( $parsed, fn( $s ) => ! self::validate_order_status( $s ) );
                if ( ! empty( $invalid ) ) {
                    return new WP_REST_Response([
                        'success'        => false,
                        'message'        => 'status ไม่ถูกต้อง: ' . implode( ', ', $invalid ),
                        'valid_statuses' => array_keys( wc_get_order_statuses() ),
                    ], 400);
                }
                $statuses = $parsed;
            }
        }

        $base_args = [
            'include' => $order_ids,
            'status'  => $statuses,
            'orderby' => 'date',
            'order'   => 'DESC',
        ];

        $orders    = wc_get_orders( array_merge( $base_args, [
            'limit'  => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
        ]));
        $total_ids = wc_get_orders( array_merge( $base_args, [
            'limit'  => -1,
            'return' => 'ids',
        ]));

        $flat = [];
        foreach ( $orders as $order ) {
            $order_id = $order->get_id();

            foreach ( $order->get_items() as $item ) {
                $formatted = self::format_order_item( $item );
                if ( ! $formatted ) continue;

                $flat[] = array_merge([
                    'id'       => $order_id,
                    'number'   => $order->get_order_number(),
                    'status'   => $order->get_status(),
                    'date'     => $order->get_date_created()?->date('Y-m-d H:i:s'),
                    'total'    => (float) $order->get_total(),
                    'currency' => $order->get_currency(),
                    'customer' => [
                        'id'    => $order->get_customer_id(),
                        'name'  => $order->get_formatted_billing_full_name(),
                        'email' => $order->get_billing_email(),
                        'phone' => $order->get_billing_phone(),
                    ],
                    'bill1' => [
                        'status' => get_post_meta( $order_id, '_bill1_status', true ) ?: 'pending',
                        'amount' => (float) ( get_post_meta( $order_id, '_bill1_amount', true ) ?: 0 ),
                    ],
                    'bill2' => [
                        'status' => get_post_meta( $order_id, '_bill2_status', true ) ?: 'pending',
                        'amount' => (float) ( get_post_meta( $order_id, '_bill2_amount', true ) ?: 0 ),
                    ],
                ], $formatted );
            }
        }

        $status_summary = [];
        $seen_orders    = [];
        foreach ( $flat as $row ) {
            $s = $row['status'];
            if ( ! isset( $status_summary[ $s ] ) ) {
                $status_summary[ $s ] = [ 'order_count' => 0, 'item_count' => 0, 'total' => 0 ];
            }
            $status_summary[ $s ]['item_count']++;
            $status_summary[ $s ]['total'] += $row['total'];
            $key = $s . '_' . $row['id'];
            if ( ! isset( $seen_orders[ $key ] ) ) {
                $seen_orders[ $key ] = true;
                $status_summary[ $s ]['order_count']++;
            }
        }

        return new WP_REST_Response([
            'order_ids'  => $order_ids,
            'statuses'   => $raw_statuses,
            'summary'    => $status_summary,
            'data'       => $flat,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => count( $total_ids ),
                'total_pages' => ceil( count( $total_ids ) / $per_page ),
                'total_items' => count( $flat ),
            ],
        ], 200);
    }

    // -------------------------------------------------------------------------
    // grouped: แต่ละ order มี items ของตัวเอง
    // -------------------------------------------------------------------------
    // Response:
    // [
    //   {
    //     "id": 101, "number": "#101", "status": "processing",
    //     "total": 5000, "bill1": {...}, "bill2": {...},
    //     "customer": { "name": "...", "email": "..." },
    //     "items": [ { item + product }, ... ]
    //   },
    //   ...
    // ]
    // -------------------------------------------------------------------------

    private static function build_grouped( array $orders ): array {
        return array_map( function( WC_Order $order ) {
            $order_id = $order->get_id();
            return [
                'id'       => $order_id,
                'number'   => $order->get_order_number(),
                'status'   => $order->get_status(),
                'total'    => (float) $order->get_total(),
                'currency' => $order->get_currency(),
                'date'     => $order->get_date_created()?->date('Y-m-d H:i:s'),
                'customer' => [
                    'id'    => $order->get_customer_id(),
                    'name'  => $order->get_formatted_billing_full_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                ],
                'bill1' => [
                    'status'  => get_post_meta( $order_id, '_bill1_status', true ) ?: 'pending',
                    'amount'  => (float) get_post_meta( $order_id, '_bill1_amount', true ),
                    'paid_at' => get_post_meta( $order_id, '_bill1_paid_at', true ),
                ],
                'bill2' => [
                    'status'  => get_post_meta( $order_id, '_bill2_status', true ) ?: 'pending',
                    'amount'  => (float) get_post_meta( $order_id, '_bill2_amount', true ),
                    'paid_at' => get_post_meta( $order_id, '_bill2_paid_at', true ),
                ],
                'items' => array_values( array_filter(
                    array_map( fn( $item ) => self::format_order_item( $item ), $order->get_items() )
                )),
            ];
        }, $orders );
    }

    // -------------------------------------------------------------------------
    // flat: รวม products ทุก order เป็น list เดียว พร้อมอ้างอิง id
    // -------------------------------------------------------------------------
    // Response:
    // [
    //   {
    //     "id": 101, "number": "#101", "status": "processing", "date": "...",
    //     "bill1": { "status": "paid" }, "bill2": { "status": "pending" },
    //     "item_id": 55, "name": "สินค้า A", "quantity": 2, "total": 2000,
    //     "product": { ... }
    //   },
    //   ...
    // ]
    // -------------------------------------------------------------------------

    private static function build_flat( array $orders ): array {
        $flat = [];

        foreach ( $orders as $order ) {
            $order_id = $order->get_id();

            foreach ( $order->get_items() as $item ) {
                $formatted = self::format_order_item( $item );
                if ( ! $formatted ) continue;

                $flat[] = array_merge(
                    [
                        'id'     => $order_id,
                        'number' => $order->get_order_number(),
                        'status' => $order->get_status(),
                        'date'   => $order->get_date_created()?->date('Y-m-d H:i:s'),
                        'bill1'  => [
                            'status' => get_post_meta( $order_id, '_bill1_status', true ) ?: 'pending',
                        ],
                        'bill2'  => [
                            'status' => get_post_meta( $order_id, '_bill2_status', true ) ?: 'pending',
                        ],
                    ],
                    $formatted
                );
            }
        }

        return $flat;
    }

    // =========================================================================
    // GET /orders/{id}/products
    // =========================================================================

    public static function get_order_products( WP_REST_Request $request ): WP_REST_Response {
        $order = self::get_order_or_fail( $request['id'] );
        if ( $order instanceof WP_REST_Response ) return $order;

        $order_id     = $order->get_id();
        $bill1_amount = (float) ( get_post_meta( $order_id, '_bill1_amount', true ) ?: 0 );
        $order_total  = (float) $order->get_total();

        $items = array_values( array_filter(
            array_map( fn( $item ) => self::format_order_item( $item ), $order->get_items() )
        ));

        return new WP_REST_Response([
            'id'    => $order_id,
            'total' => $order_total,
            'bill1' => [
                'status'  => get_post_meta( $order_id, '_bill1_status', true ) ?: 'pending',
                'amount'  => $bill1_amount,
                'paid_at' => get_post_meta( $order_id, '_bill1_paid_at', true ),
            ],
            'bill2' => [
                'status'  => get_post_meta( $order_id, '_bill2_status', true ) ?: 'pending',
                'amount'  => round( $order_total - $bill1_amount, 2 ),
                'paid_at' => get_post_meta( $order_id, '_bill2_paid_at', true ),
            ],
            'items_summary' => [
                'count'     => count( $items ),
                'subtotal'  => array_sum( array_column( $items, 'subtotal' ) ),
                'total_qty' => array_sum( array_column( $items, 'quantity' ) ),
            ],
            'items' => $items,
        ], 200);
    }

    // =========================================================================
    // GET /orders & /orders/{id}
    // =========================================================================

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

        $total = wc_get_orders([
            'limit'  => -1,
            'status' => $status,
            'return' => 'ids',
        ]);

        return new WP_REST_Response([
            'data'       => array_map( fn( $o ) => self::format_order( $o ), $orders ),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => count( $total ),
                'total_pages' => ceil( count( $total ) / $per_page ),
            ],
        ], 200);
    }

    public static function get_order_detail( WP_REST_Request $request ): WP_REST_Response {
        $order = self::get_order_or_fail( $request['id'] );
        if ( $order instanceof WP_REST_Response ) return $order;

        return new WP_REST_Response( self::format_order( $order, true ), 200 );
    }

    // =========================================================================
    // PATCH handlers
    // =========================================================================

    public static function update_order_status( WP_REST_Request $request ): WP_REST_Response {
        $order = self::get_order_or_fail( $request['id'] );
        if ( $order instanceof WP_REST_Response ) return $order;

        $new_status = $request->get_param('status');
        $order->update_status( $new_status, '', true );

        return new WP_REST_Response([
            'success' => true,
            'message' => "อัปเดต status เป็น {$new_status} แล้ว",
            'data'    => self::format_order( $order ),
        ], 200);
    }

    public static function update_order_note( WP_REST_Request $request ): WP_REST_Response {
        $order = self::get_order_or_fail( $request['id'] );
        if ( $order instanceof WP_REST_Response ) return $order;

        $note             = sanitize_textarea_field( $request->get_param('note') );
        $is_customer_note = (bool) $request->get_param('is_customer_note');
        $note_id          = $order->add_order_note( $note, $is_customer_note, true );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'เพิ่ม Note แล้ว',
            'note_id' => $note_id,
        ], 200);
    }

    public static function update_order_customer( WP_REST_Request $request ): WP_REST_Response {
        $order = self::get_order_or_fail( $request['id'] );
        if ( $order instanceof WP_REST_Response ) return $order;

        $allowed = [
            'first_name', 'last_name', 'email', 'phone',
            'address_1', 'address_2', 'city', 'state', 'postcode', 'country',
        ];

        $updated = [];
        foreach ( $allowed as $field ) {
            $value = $request->get_param( $field );
            if ( ! is_null( $value ) ) {
                $setter = "set_billing_{$field}";
                if ( method_exists( $order, $setter ) ) {
                    $order->$setter( sanitize_text_field( $value ) );
                    $updated[] = $field;
                }
            }
        }

        if ( empty( $updated ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่มี field ที่ส่งมา' ], 400);
        }

        $order->save();

        return new WP_REST_Response([
            'success' => true,
            'message' => 'อัปเดตข้อมูลลูกค้าแล้ว',
            'updated' => $updated,
            'data'    => self::format_order( $order ),
        ], 200);
    }

    public static function update_order_bill( WP_REST_Request $request ): WP_REST_Response {
        $order = self::get_order_or_fail( $request['id'] );
        if ( $order instanceof WP_REST_Response ) return $order;

        $bill_number = $request['bill_number'];
        $order_id    = $order->get_id();
        $updated     = [];

        $field_map = [
            'status'  => "_bill{$bill_number}_status",
            'amount'  => "_bill{$bill_number}_amount",
            'paid_at' => "_bill{$bill_number}_paid_at",
        ];

        foreach ( $field_map as $param => $meta_key ) {
            $value = $request->get_param( $param );
            if ( ! is_null( $value ) ) {
                update_post_meta( $order_id, $meta_key, sanitize_text_field( $value ) );
                $updated[ $param ] = $value;
            }
        }

        if ( empty( $updated ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่มี field ที่ส่งมา' ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "อัปเดต Bill {$bill_number} แล้ว",
            'updated' => $updated,
        ], 200);
    }

    // =========================================================================
    // Format helpers
    // =========================================================================

    private static function format_order_item( WC_Order_Item $item ): ?array {
        /** @var WC_Order_Item_Product $item */
        $product = $item->get_product();
        if ( ! $product ) return null;

        $image_id   = $product->get_image_id();
        $qty        = $item->get_quantity();
        $subtotal   = (float) $item->get_subtotal();
        $total      = (float) $item->get_total();
        $unit_price = $qty > 0 ? round( $total / $qty, 4 ) : 0;

        $variation_data = [];
        if ( $item instanceof WC_Order_Item_Product ) {
            $variation_data = array_map( fn( $meta ) => [
                'key'   => $meta->display_key,
                'value' => $meta->display_value,
            ], $item->get_formatted_meta_data('') );
        }

        return [
            'item_id'    => $item->get_id(),
            'name'       => $item->get_name(),
            'quantity'   => $qty,
            'unit_price' => $unit_price,
            'subtotal'   => $subtotal,
            'total'      => $total,
            'discount'   => round( $subtotal - $total, 2 ),
            'variation'  => array_values( $variation_data ),
            'product'    => [
                'id'            => $product->get_id(),
                'type'          => $product->get_type(),
                'name'          => $product->get_name(),
                'sku'           => $product->get_sku(),
                'price'         => (float) $product->get_price(),
                'regular_price' => (float) $product->get_regular_price(),
                'sale_price'    => (float) $product->get_sale_price(),
                'stock'         => $product->get_stock_quantity(),
                'stock_status'  => $product->get_stock_status(),
                'categories'    => wp_get_post_terms( $product->get_id(), 'product_cat', ['fields' => 'names'] ),
                'tags'          => wp_get_post_terms( $product->get_id(), 'product_tag', ['fields' => 'names'] ),
                'attributes'    => self::get_product_attributes( $product ),
                'permalink'     => get_permalink( $product->get_id() ),
                'image'         => [
                    'thumbnail' => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
                    'medium'    => wp_get_attachment_image_url( $image_id, 'medium' ),
                    'full'      => wp_get_attachment_image_url( $image_id, 'full' ),
                ],
            ],
        ];
    }

    private static function format_order( WC_Order $order, bool $with_items = false ): array {
        $order_id = $order->get_id();

        $data = [
            'id'             => $order_id,
            'number'         => $order->get_order_number(),
            'status'         => $order->get_status(),
            'total'          => (float) $order->get_total(),
            'currency'       => $order->get_currency(),
            'date'           => $order->get_date_created()?->date('Y-m-d H:i:s'),
            'payment_method' => $order->get_payment_method(),
            'customer'       => [
                'id'    => $order->get_customer_id(),
                'name'  => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'billing' => [
                'address' => $order->get_formatted_billing_address(),
            ],
            'bill1' => [
                'status'  => get_post_meta( $order_id, '_bill1_status', true ) ?: 'pending',
                'amount'  => (float) get_post_meta( $order_id, '_bill1_amount', true ),
                'paid_at' => get_post_meta( $order_id, '_bill1_paid_at', true ),
            ],
            'bill2' => [
                'status'  => get_post_meta( $order_id, '_bill2_status', true ) ?: 'pending',
                'amount'  => (float) get_post_meta( $order_id, '_bill2_amount', true ),
                'paid_at' => get_post_meta( $order_id, '_bill2_paid_at', true ),
            ],
        ];

        if ( $with_items ) {
            $data['items'] = array_values( array_filter(
                array_map( fn( $item ) => self::format_order_item( $item ), $order->get_items() )
            ));
        }

        return $data;
    }

    private static function get_product_attributes( WC_Product $product ): array {
        $attributes = [];

        foreach ( $product->get_attributes() as $key => $attribute ) {

            // ✅ ข้ามถ้าไม่ใช่ object (บาง product เก็บ attribute เป็น string)
            if ( ! is_object( $attribute ) ) {
                continue;
            }

            $options = $attribute->get_options();

            // ✅ get_options() คืน array of term_ids (taxonomy) หรือ array of strings (custom)
            // ถ้าเป็น taxonomy ต้องแปลงจาก ID → name
            if ( $attribute->is_taxonomy() ) {
                $terms  = array_map( fn( $id ) => get_term( $id )?->name ?? $id, $options );
                $values = array_filter( $terms );
            } else {
                $values = is_array( $options ) ? $options : [ $options ];
            }

            $attributes[] = [
                'name'   => wc_attribute_label( $key, $product ),
                'values' => array_values( $values ),
            ];
        }

        return $attributes;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function get_order_or_fail( $id ): WC_Order|WP_REST_Response {
        $order = wc_get_order( absint( $id ) );
        if ( ! $order ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่พบ Order' ], 404);
        }
        return $order;
    }

    public static function validate_order_status( string $status ): bool {
        $valid = array_keys( wc_get_order_statuses() );
        return in_array( $status, $valid, true )
            || in_array( 'wc-' . $status, $valid, true );
    }

    public static function validate_bill_status( string $status ): bool {
        return in_array( $status, [ 'pending', 'paid', 'cancelled' ], true );
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }
}

Orders_API::init();