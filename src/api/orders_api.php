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
                    'per_page' => [ 'required' => false, 'type' => 'integer' ],
                ],
            ],
        ]);

        // ✅ wildcard routes ลงหลัง
        // GET /wp-json/jaonaichan/v1/orders?page=1&per_page=10&status=...
        //   &create_date=dd/mm/yyyy   (exact day)
        //   &create_date_m=mm         (month only)
        //   &create_date_y=yyyy       (year only — combinable with create_date_m)
        register_rest_route( 'jaonaichan/v1', '/orders', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_orders' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'page'          => [ 'required' => false, 'type' => 'integer', 'default' => 1 ],
                'per_page'      => [ 'required' => false, 'type' => 'integer', 'default' => 10 ],
                'status'        => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'create_date'   => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn( $v ) => (bool) preg_match( '/^\d{1,2}\/\d{1,2}\/\d{4}$/', $v ),
                    'description'       => 'dd/mm/yyyy — กรองตามวันที่สร้าง (เฉพาะวัน)',
                ],
                'create_date_m' => [
                    'required'    => false,
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 12,
                    'description' => 'เดือน (1-12) — ใช้ร่วมกับ create_date_y ได้',
                ],
                'create_date_y' => [
                    'required'    => false,
                    'type'        => 'integer',
                    'minimum'     => 2000,
                    'description' => 'ปี (yyyy) — ใช้ร่วมกับ create_date_m ได้',
                ],
                'unit_prices_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
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
            'permission_callback' => [ self::class, 'check_bill_permission' ],
            'args'                => [
                'status'  => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [ self::class, 'validate_bill_status' ],
                ],
                'amount'      => [ 'required' => false, 'type' => 'number' ],
                'paid_at'     => [ 'required' => false, 'type' => 'string' ],
                'unit_prices'    => [ 'required' => false, 'type' => 'object' ],
                'unit_prices_id' => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ]);

        // PATCH /wp-json/jaonaichan/v1/orders/{id}/invoice-items
        register_rest_route( 'jaonaichan/v1', '/orders/(?P<id>\d+)/invoice-items', [
            'methods'             => 'PATCH',
            'callback'            => [ self::class, 'update_order_invoice_items' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'items' => [
                    'required'    => true,
                    'type'        => 'array',
                    'description' => 'Array of custom invoice items',
                ],
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
                        'name'  => self::get_billing_name( $order ),
                        'email' => $order->get_billing_email(),
                        'phone' => $order->get_billing_phone(),
                    ],
                    'bill1' => [
                        'status' => $order->get_meta( '_bill1_status' ) ?: 'pending',
                        'amount' => (float) ( $order->get_meta( '_bill1_amount' ) ?: 0 ),
                    ],
                    'bill2' => [
                        'status'         => $order->get_meta( '_bill2_status' ) ?: 'pending',
                        'amount'         => (float) ( $order->get_meta( '_bill2_amount' ) ?: 0 ),
                        'unit_prices'    => self::get_bill2_unit_prices( $order ),
                        'unit_prices_id' => $order->get_meta( '_bill2_unit_prices_id' ) ?: null,
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
        // Read JSON body directly — get_param() can miss body values when WP REST
        // resolves defaults before the body is fully merged into the param stack.
        $body = $request->get_json_params() ?: [];

        $page         = max( 1, (int) ( $body['page']     ?? $request->get_param('page')     ?? 1 ) );
        $per_page_raw =          $body['per_page'] ?? $request->get_param('per_page');
        $per_page     = ( $per_page_raw === null ) ? -1 : max( 1, (int) $per_page_raw ); // -1 = all

        $raw_order_ids = $body['order_ids'] ?? $request->get_param('order_ids') ?? [];
        $order_ids = array_values( array_filter(
            array_map( 'absint', (array) $raw_order_ids )
        ));

        if ( empty( $order_ids ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'order_ids ต้องมีค่าอย่างน้อยหนึ่งรายการ',
            ], 400);
        }

        $raw_statuses  = $body['statuses'] ?? $request->get_param('statuses');
        $status_filter = null; // null = no filter

        if ( $raw_statuses !== null && $raw_statuses !== '' && $raw_statuses !== 'all' ) {
            $parsed  = array_filter( array_map( 'trim', explode( ',', $raw_statuses ) ) );
            $invalid = array_filter( $parsed, fn( $s ) => ! self::validate_order_status( $s ) );
            if ( ! empty( $invalid ) ) {
                return new WP_REST_Response([
                    'success'        => false,
                    'message'        => 'status ไม่ถูกต้อง: ' . implode( ', ', $invalid ),
                    'valid_statuses' => array_keys( wc_get_order_statuses() ),
                ], 400);
            }
            // Normalize: strip wc- prefix so it matches get_status() output
            $status_filter = array_map( fn( $s ) => str_replace( 'wc-', '', $s ), $parsed );
        }

        // Fetch each order directly by primary key — works on both CPT and HPOS,
        // and guarantees the result set matches exactly the requested IDs.
        $all_orders = array_values( array_filter(
            array_map( 'wc_get_order', $order_ids ),
            fn( $o ) => $o instanceof WC_Order
        ));

        if ( $status_filter !== null ) {
            $all_orders = array_values( array_filter(
                $all_orders,
                fn( $o ) => in_array( $o->get_status(), $status_filter, true )
            ));
        }

        $total_order_count = count( $all_orders );

        // Paginate at the order level (consistent with get_products_bulk)
        $paged_orders = $per_page === -1
            ? $all_orders
            : array_slice( $all_orders, ( $page - 1 ) * $per_page, $per_page );

        $flat = [];
        foreach ( $paged_orders as $order ) {
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
                        'name'  => self::get_billing_name( $order ),
                        'email' => $order->get_billing_email(),
                        'phone' => $order->get_billing_phone(),
                    ],
                    'bill1' => [
                        'status' => $order->get_meta( '_bill1_status' ) ?: 'pending',
                        'amount' => (float) ( $order->get_meta( '_bill1_amount' ) ?: 0 ),
                    ],
                    'bill2' => [
                        'status'         => $order->get_meta( '_bill2_status' ) ?: 'pending',
                        'amount'         => (float) ( $order->get_meta( '_bill2_amount' ) ?: 0 ),
                        'unit_prices'    => self::get_bill2_unit_prices( $order ),
                        'unit_prices_id' => $order->get_meta( '_bill2_unit_prices_id' ) ?: null,
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
                'total'       => $total_order_count,
                'total_pages' => $per_page === -1 ? 1 : (int) ceil( $total_order_count / $per_page ),
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
                    'name'  => self::get_billing_name( $order ),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                ],
                'bill1' => [
                    'status'  => $order->get_meta( '_bill1_status' ) ?: 'pending',
                    'amount'  => (float) $order->get_meta( '_bill1_amount' ),
                    'paid_at' => $order->get_meta( '_bill1_paid_at' ),
                ],
                'bill2' => [
                    'status'         => $order->get_meta( '_bill2_status' ) ?: 'pending',
                    'amount'         => (float) $order->get_meta( '_bill2_amount' ),
                    'paid_at'        => $order->get_meta( '_bill2_paid_at' ),
                    'unit_prices'    => self::get_bill2_unit_prices( $order ),
                    'unit_prices_id' => $order->get_meta( '_bill2_unit_prices_id' ) ?: null,
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
                            'status' => $order->get_meta( '_bill1_status' ) ?: 'pending',
                        ],
                        'bill2'  => [
                            'status' => $order->get_meta( '_bill2_status' ) ?: 'pending',
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
        $bill1_amount = (float) ( $order->get_meta( '_bill1_amount' ) ?: 0 );
        $order_total  = (float) $order->get_total();

        $items = array_values( array_filter(
            array_map( fn( $item ) => self::format_order_item( $item ), $order->get_items() )
        ));

        return new WP_REST_Response([
            'id'    => $order_id,
            'total' => $order_total,
            'bill1' => [
                'status'  => $order->get_meta( '_bill1_status' ) ?: 'pending',
                'amount'  => $bill1_amount,
                'paid_at' => $order->get_meta( '_bill1_paid_at' ),
            ],
            'bill2' => [
                'status'         => $order->get_meta( '_bill2_status' ) ?: 'pending',
                'amount'         => round( $order_total - $bill1_amount, 2 ),
                'paid_at'        => $order->get_meta( '_bill2_paid_at' ),
                'unit_prices'    => self::get_bill2_unit_prices( $order ),
                'unit_prices_id' => $order->get_meta( '_bill2_unit_prices_id' ) ?: null,
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
        $status_raw = sanitize_text_field( $request->get_param('status') ?: 'any' );

        // Support comma-separated statuses e.g. "paid-1,paid-2,completed"
        if ( str_contains( $status_raw, ',' ) ) {
            $parts  = array_filter( array_map( 'trim', explode( ',', $status_raw ) ) );
            $status = array_map( fn( $s ) => str_replace( 'wc-', '', $s ), $parts );
        } else {
            $status = $status_raw === 'any' ? 'any' : str_replace( 'wc-', '', $status_raw );
        }

        $base_args = [
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => $status,
        ];

        $unit_prices_id = sanitize_text_field( $request->get_param('unit_prices_id') );
        if ( ! empty( $unit_prices_id ) ) {
            $base_args['meta_key']   = '_bill2_unit_prices_id';
            $base_args['meta_value'] = $unit_prices_id;
        }

        $date_query = self::build_date_query( $request );
        if ( $date_query ) {
            $base_args['date_query'] = $date_query;
        }

        $orders = wc_get_orders( array_merge( $base_args, [
            'limit'  => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
        ]));

        $total = wc_get_orders( array_merge( $base_args, [
            'limit'  => -1,
            'return' => 'ids',
        ]));

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

    private static function build_date_query( WP_REST_Request $request ): array {
        $create_date = $request->get_param('create_date');

        if ( $create_date ) {
            [ $d, $m, $y ] = explode( '/', $create_date );
            return [ [ 'year' => (int) $y, 'month' => (int) $m, 'day' => (int) $d ] ];
        }

        $clause = [];
        $m = $request->get_param('create_date_m');
        $y = $request->get_param('create_date_y');
        if ( $m ) $clause['month'] = (int) $m;
        if ( $y ) $clause['year']  = (int) $y;

        return $clause ? [ $clause ] : [];
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
                $order->update_meta_data( $meta_key, sanitize_text_field( $value ) );
                $updated[ $param ] = $value;
            }
        }

        if ( $bill_number === '2' ) {
            $raw_prices = $request->get_param('unit_prices');
            if ( is_array( $raw_prices ) ) {
                $clean = [];
                foreach ( $raw_prices as $pid => $price ) {
                    $clean[ absint( $pid ) ] = (float) $price;
                }
                $order->update_meta_data( '_bill2_unit_prices', wp_json_encode( $clean ) );
                $updated['unit_prices'] = $clean;
            }

            $prices_id = $request->get_param('unit_prices_id');
            if ( ! is_null( $prices_id ) ) {
                $order->update_meta_data( '_bill2_unit_prices_id', sanitize_text_field( $prices_id ) );
                $updated['unit_prices_id'] = $prices_id;
            }
        }

        if ( empty( $updated ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่มี field ที่ส่งมา' ], 400);
        }

        $order->save();

        // sync WC order status ตาม bill status ที่เปลี่ยน
        if ( isset( $updated['status'] ) ) {
            $b1 = (string) $order->get_meta( '_bill1_status', true );
            $b2 = (string) $order->get_meta( '_bill2_status', true );

            if ( $b2 === 'paid' ) {
                $order->update_status( 'paid-2', 'ชำระครบทั้ง 2 บิลแล้ว' );
            } elseif ( $b1 === 'paid' ) {
                $order->update_status( 'paid-1', 'ชำระบิลแรกแล้ว' );
            } elseif ( $b2 === 'submitted' ) {
                $order->update_status( 'wait-verify-2', 'รอตรวจสลิปบิลที่ 2' );
            } elseif ( $b1 === 'submitted' ) {
                $order->update_status( 'wait-verify-1', 'รอตรวจสลิปบิลแรก' );
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "อัปเดต Bill {$bill_number} แล้ว",
            'updated' => $updated,
        ], 200);
    }

    public static function update_order_invoice_items( WP_REST_Request $request ): WP_REST_Response {
        $order = self::get_order_or_fail( $request['id'] );
        if ( $order instanceof WP_REST_Response ) return $order;

        $items = $request->get_param('items');
        if ( ! is_array( $items ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'Items must be an array' ], 400);
        }

        $order->update_meta_data( '_custom_invoice_items', wp_json_encode( $items ) );
        $order->save();

        return new WP_REST_Response([
            'success' => true,
            'message' => 'อัปเดตรายการ Invoice แล้ว',
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

    private static function get_billing_name( WC_Order $order ): string {
        $name = trim( $order->get_formatted_billing_full_name() );
        if ( $name !== '' ) {
            return $name;
        }
        $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        if ( $name !== '' ) {
            return $name;
        }
        $user_id = $order->get_customer_id();
        if ( $user_id ) {
            $name = trim( get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'last_name', true ) );
        }
        return $name;
    }

    public static function format_order( WC_Order $order, bool $with_items = false ): array {
        $order_id = $order->get_id();

        $data = [
            'id'             => $order_id,
            'number'         => $order->get_order_number(),
            'status'         => $order->get_status(),
            'total'          => (float) $order->get_total(),
            'currency'       => $order->get_currency(),
            'date'           => $order->get_date_created()?->date('Y-m-d H:i:s'),
            'date_modified'  => $order->get_date_modified()?->date('Y-m-d H:i:s'),
            'payment_method' => $order->get_payment_method(),
            'customer'       => [
                'id'    => $order->get_customer_id(),
                'name'  => self::get_billing_name( $order ),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'billing' => [
                'address' => $order->get_formatted_billing_address(),
            ],
            'shipping' => [
                'name'    => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
                'phone'   => (string) $order->get_meta( '_shipping_phone', true ),
                'address' => $order->get_shipping_address_1(),
            ],
            'bill1' => [
                'status'  => $order->get_meta( '_bill1_status' ) ?: 'pending',
                'amount'  => (float) $order->get_meta( '_bill1_amount' ),
                'paid_at' => $order->get_meta( '_bill1_paid_at' ),
            ],
            'bill2' => [
                'status'         => $order->get_meta( '_bill2_status' ) ?: 'pending',
                'amount'         => (float) $order->get_meta( '_bill2_amount' ),
                'paid_at'        => $order->get_meta( '_bill2_paid_at' ),
                'unit_prices'    => self::get_bill2_unit_prices( $order ),
                'unit_prices_id' => $order->get_meta( '_bill2_unit_prices_id' ) ?: null,
            ],
            'invoice_items' => self::get_custom_invoice_items( $order ),
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

    private static function get_bill2_unit_prices( WC_Order $order ): array {
        $raw = $order->get_meta( '_bill2_unit_prices' );
        if ( ! $raw ) return [];
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    private static function get_custom_invoice_items( WC_Order $order ): array {
        $raw = $order->get_meta( '_custom_invoice_items' );
        if ( ! $raw ) return [];
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

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
        return in_array( $status, [ 'pending', 'submitted', 'paid', 'cancelled' ], true );
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }

    /** PATCH bill — admin หรือเจ้าของ order เท่านั้น */
    public static function check_bill_permission( WP_REST_Request $req ): bool {
        if ( ! is_user_logged_in() ) return false;
        if ( current_user_can( 'manage_options' ) ) return true;

        $order = wc_get_order( (int) $req['id'] );
        return $order && (int) $order->get_customer_id() === get_current_user_id();
    }
}

Orders_API::init();