<?php
defined( 'ABSPATH' ) || exit;

class Barcode_Import_API {

    private static function import_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'barcode_import_items';
    }

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
        add_action( 'init', [ self::class, 'maybe_create_table' ] );
    }

    public static function maybe_create_table(): void {
        if ( get_option( 'barcode_import_items_table_v1' ) ) return;

        global $wpdb;
        $t       = self::import_table();
        $collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$t} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id    BIGINT UNSIGNED NOT NULL,
            barcode_code  VARCHAR(255)    NOT NULL,
            received_qty  INT UNSIGNED    NOT NULL DEFAULT 1,
            last_scan_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_barcode (product_id, barcode_code),
            KEY product_id (product_id)
        ) {$collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( 'barcode_import_items_table_v1', true );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/barcode-import', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'handle' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ] );
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }

    public static function handle( WP_REST_Request $request ) {
        $body   = $request->get_json_params();
        $action = $body['action'] ?? '';

        switch ( $action ) {
            case 'get_import_products': return self::get_import_products();
            case 'set_order_qty':       return self::set_order_qty( $body );
            case 'update_barcode_qty':  return self::update_barcode_qty( $body );
            case 'remove_barcode':      return self::remove_barcode( $body );
            case 'save_barcode':        return self::save_barcode( $body );
            // legacy — kept for BarcodeManagement page
            case 'search_products':     return self::search_products( $body );
            case 'get_variations':      return self::get_variations( $body );
            case 'get_barcodes':        return self::get_barcodes( $body );
            case 'delete_barcode':      return self::delete_barcode( $body );
            default:
                return new WP_Error( 'invalid_action', 'Invalid action.', [ 'status' => 400 ] );
        }
    }

    // =========================================================================
    // v2 — Import product list UI
    // =========================================================================

    private static function get_import_products() {
        global $wpdb;

        // All published products
        $product_ids = array_map( 'intval', (array) $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish'
             ORDER BY post_title ASC"
        ) );

        if ( empty( $product_ids ) ) {
            return new WP_REST_Response( [ 'products' => [] ], 200 );
        }

        // Collect all target IDs (product_id for simple, variation_id for variable)
        // and build a product→type / product→children map in one pass.
        // WooCommerce 3.0+ stores product type in the product_type taxonomy, not postmeta
        $type_rows = $wpdb->get_results(
            "SELECT tr.object_id, t.slug
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.taxonomy = 'product_type'
               AND tr.object_id IN (" . implode( ',', $product_ids ) . ")",
            ARRAY_A
        );
        $type_by_id = [];
        foreach ( $type_rows as $row ) {
            $type_by_id[ (int) $row['object_id'] ] = $row['slug'];
        }

        // Get all variation IDs for variable products in one query
        $variable_ids = array_filter( $product_ids, fn( $id ) => ( $type_by_id[ $id ] ?? '' ) === 'variable' );
        $children_by_parent = [];
        if ( $variable_ids ) {
            $var_rows = $wpdb->get_results(
                "SELECT ID, post_parent FROM {$wpdb->posts}
                 WHERE post_type = 'product_variation' AND post_status = 'publish'
                   AND post_parent IN (" . implode( ',', $variable_ids ) . ")
                 ORDER BY menu_order ASC",
                ARRAY_A
            );
            foreach ( $var_rows as $row ) {
                $children_by_parent[ (int) $row['post_parent'] ][] = (int) $row['ID'];
            }
        }

        // All target IDs for batch queries
        $simple_ids = array_filter( $product_ids, fn( $id ) => ( $type_by_id[ $id ] ?? 'simple' ) !== 'variable' );
        $all_variation_ids = array_merge( ...array_values( $children_by_parent ) ?: [[]] );
        $all_target_ids = array_merge( array_values( $simple_ids ), $all_variation_ids );

        // Batch: barcodes from import table
        $t_import           = self::import_table();
        $barcodes_by_target = [];
        if ( $all_target_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $all_target_ids ), '%d' ) );
            $rows         = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT product_id, barcode_code AS code, received_qty, last_scan_at
                     FROM {$t_import} WHERE product_id IN ($placeholders) ORDER BY last_scan_at DESC",
                    ...$all_target_ids
                ),
                ARRAY_A
            );
            foreach ( $rows as $row ) {
                $barcodes_by_target[ (int) $row['product_id'] ][] = [
                    'code'         => $row['code'],
                    'received_qty' => (int) $row['received_qty'],
                    'last_scan_at' => $row['last_scan_at'],
                ];
            }
        }

        // Batch: order_qty from postmeta
        $order_qty_by_id = [];
        if ( $all_target_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $all_target_ids ), '%d' ) );
            $meta_rows    = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                     WHERE meta_key = '_barcode_import_order_qty' AND post_id IN ($placeholders)",
                    ...$all_target_ids
                ),
                ARRAY_A
            );
            foreach ( $meta_rows as $row ) {
                $order_qty_by_id[ (int) $row['post_id'] ] = (int) $row['meta_value'];
            }
        }

        // Batch: product images
        $thumbnail_ids = array_map( 'intval', (array) $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_thumbnail_id' AND post_id IN (" . implode( ',', $product_ids ) . ")"
        ) );
        // We'll call wp_get_attachment_image_url per product (WP caches attachment meta)

        // Batch: first category per product (term taxonomy)
        $cat_by_product = [];
        $term_rows      = $wpdb->get_results(
            "SELECT tr.object_id, t.name
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.taxonomy = 'product_cat'
               AND tr.object_id IN (" . implode( ',', $product_ids ) . ")
             ORDER BY tr.object_id, tt.term_order",
            ARRAY_A
        );
        foreach ( $term_rows as $row ) {
            if ( ! isset( $cat_by_product[ (int) $row['object_id'] ] ) ) {
                $cat_by_product[ (int) $row['object_id'] ] = $row['name'];
            }
        }

        // Batch: variation attribute labels
        $attr_by_var = [];
        if ( $all_variation_ids ) {
            $attr_rows = $wpdb->get_results(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key LIKE 'attribute_%'
                   AND post_id IN (" . implode( ',', $all_variation_ids ) . ")",
                ARRAY_A
            );
            foreach ( $attr_rows as $row ) {
                $attr_by_var[ (int) $row['post_id'] ][ $row['meta_key'] ] = $row['meta_value'];
            }
        }

        // Build response
        $products = [];
        foreach ( $product_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) continue;

            $image_url = (string) wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
            $category  = $cat_by_product[ $pid ] ?? '';
            $type      = $type_by_id[ $pid ] ?? 'simple';

            if ( $type === 'variable' ) {
                $variants    = [];
                $var_ids     = $children_by_parent[ $pid ] ?? [];
                $vi          = 0;
                foreach ( $var_ids as $var_id ) {
                    $vi++;
                    $var = wc_get_product( $var_id );
                    if ( ! $var ) continue;

                    // Build label from stored attribute slugs
                    $label = self::variation_label_from_attrs(
                        $attr_by_var[ $var_id ] ?? [],
                        $vi
                    );

                    $variants[] = [
                        'variant_id'  => $var_id,
                        'name'        => $label,
                        'sku'         => $var->get_sku(),
                        'order_qty'   => $order_qty_by_id[ $var_id ] ?? 0,
                        'barcodes'    => $barcodes_by_target[ $var_id ] ?? [],
                    ];
                }

                $products[] = [
                    'product_id' => $pid,
                    'name'       => $product->get_name(),
                    'sku'        => $product->get_sku(),
                    'image_url'  => $image_url,
                    'category'   => $category,
                    'type'       => 'variable',
                    'order_qty'  => 0,
                    'barcodes'   => [],
                    'variants'   => $variants,
                ];
            } else {
                $products[] = [
                    'product_id' => $pid,
                    'name'       => $product->get_name(),
                    'sku'        => $product->get_sku(),
                    'image_url'  => $image_url,
                    'category'   => $category,
                    'type'       => 'simple',
                    'order_qty'  => $order_qty_by_id[ $pid ] ?? 0,
                    'barcodes'   => $barcodes_by_target[ $pid ] ?? [],
                    'variants'   => [],
                ];
            }
        }

        return new WP_REST_Response( [ 'products' => $products ], 200 );
    }

    private static function variation_label_from_attrs( array $attr_meta, int $index ): string {
        $parts = [];
        foreach ( $attr_meta as $key => $val ) {
            if ( strpos( $key, 'attribute_' ) !== 0 || $val === '' ) continue;
            $taxonomy = substr( $key, strlen( 'attribute_' ) );
            if ( taxonomy_exists( $taxonomy ) ) {
                $term    = get_term_by( 'slug', $val, $taxonomy );
                $parts[] = $term ? $term->name : $val;
            } else {
                $parts[] = $val;
            }
        }
        $label = implode( ' / ', array_filter( $parts ) );
        return $label !== '' ? $label : "ตัวเลือก $index";
    }

    private static function set_order_qty( array $body ) {
        $target_id = intval( $body['target_id'] ?? 0 );
        $qty       = max( 0, intval( $body['qty'] ?? 0 ) );

        if ( ! $target_id ) {
            return new WP_Error( 'missing_params', 'target_id is required.', [ 'status' => 400 ] );
        }

        update_post_meta( $target_id, '_barcode_import_order_qty', $qty );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    private static function update_barcode_qty( array $body ) {
        global $wpdb;

        $target_id = intval( $body['target_id'] ?? 0 );
        $code      = sanitize_text_field( $body['code'] ?? '' );
        $qty       = intval( $body['qty'] ?? 0 );

        if ( ! $target_id || $code === '' ) {
            return new WP_Error( 'missing_params', 'target_id and code are required.', [ 'status' => 400 ] );
        }

        $t = self::import_table();

        if ( $qty < 1 ) {
            $wpdb->delete( $t, [ 'product_id' => $target_id, 'barcode_code' => $code ], [ '%d', '%s' ] );
            return new WP_REST_Response( [ 'success' => true, 'received_qty' => 0 ], 200 );
        }

        $wpdb->update(
            $t,
            [ 'received_qty' => $qty, 'last_scan_at' => current_time( 'mysql' ) ],
            [ 'product_id' => $target_id, 'barcode_code' => $code ],
            [ '%d', '%s' ],
            [ '%d', '%s' ]
        );

        return new WP_REST_Response( [ 'success' => true, 'received_qty' => $qty ], 200 );
    }

    private static function remove_barcode( array $body ) {
        global $wpdb;

        $target_id = intval( $body['target_id'] ?? 0 );
        $code      = sanitize_text_field( $body['code'] ?? '' );

        if ( ! $target_id || $code === '' ) {
            return new WP_Error( 'missing_params', 'target_id and code are required.', [ 'status' => 400 ] );
        }

        $t = self::import_table();
        $wpdb->delete( $t, [ 'product_id' => $target_id, 'barcode_code' => $code ], [ '%d', '%s' ] );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    // =========================================================================
    // save_barcode — upserts into barcode_import_items (v2 UI)
    // + tries insert into product_barcodes for packing compatibility
    // =========================================================================

    private static function save_barcode( array $body ) {
        global $wpdb;

        $product_id = intval( $body['product_id'] ?? 0 );
        $barcode    = sanitize_text_field( $body['barcode'] ?? '' );
        $image      = isset( $body['image'] ) ? $body['image'] : null;

        if ( ! $product_id || $barcode === '' ) {
            return new WP_Error( 'missing_params', 'product_id and barcode are required.', [ 'status' => 400 ] );
        }

        // Upsert into import tracking table (increment received_qty if duplicate)
        $t_import = self::import_table();
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$t_import} (product_id, barcode_code, received_qty, last_scan_at)
             VALUES (%d, %s, 1, %s)
             ON DUPLICATE KEY UPDATE received_qty = received_qty + 1, last_scan_at = VALUES(last_scan_at)",
            $product_id,
            $barcode,
            current_time( 'mysql' )
        ) );

        // Also try inserting into product_barcodes for packing workflow.
        // Silently skip if barcode already exists globally (UNIQUE constraint).
        if ( class_exists( 'Barcode_Pack_DB' ) ) {
            Barcode_Pack_DB::insert_barcodes( [ $barcode ], $product_id, $image );
        }

        return new WP_REST_Response( [ 'success' => true, 'message' => 'บันทึก Barcode สำเร็จ' ], 200 );
    }

    // =========================================================================
    // Legacy actions — kept for BarcodeManagement and other pages
    // =========================================================================

    private static function search_products( array $body ) {
        global $wpdb;

        $query = sanitize_text_field( $body['query'] ?? '' );
        if ( $query === '' ) {
            return new WP_Error( 'missing_query', 'query is required.', [ 'status' => 400 ] );
        }

        $like = '%' . $wpdb->esc_like( $query ) . '%';

        $name_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish' AND post_title LIKE %s LIMIT 20",
            $like
        ) );

        $sku_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
               AND pm.meta_key = '_sku' AND pm.meta_value LIKE %s LIMIT 20",
            $like
        ) );

        $product_ids = array_slice(
            array_unique( array_merge(
                array_map( 'intval', (array) $name_ids ),
                array_map( 'intval', (array) $sku_ids )
            ) ),
            0, 20
        );

        $t        = $wpdb->prefix . 'product_barcodes';
        $products = [];

        foreach ( $product_ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) continue;
            $count      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE product_id = %d", $id ) );
            $products[] = [
                'product_id'    => $id,
                'name'          => $product->get_name(),
                'sku'           => $product->get_sku(),
                'barcode_count' => $count,
                'type'          => $product->get_type(),
            ];
        }

        return new WP_REST_Response( [ 'products' => $products ], 200 );
    }

    private static function get_variations( array $body ) {
        global $wpdb;

        $product_id = intval( $body['product_id'] ?? 0 );
        if ( ! $product_id ) {
            return new WP_Error( 'missing_product_id', 'product_id is required.', [ 'status' => 400 ] );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return new WP_Error( 'invalid_product', 'Product not found or not variable.', [ 'status' => 404 ] );
        }

        $t          = $wpdb->prefix . 'product_barcodes';
        $variations = [];

        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) ) continue;

            $attrs = $variation->get_attributes();
            $parts = [];
            foreach ( $attrs as $tax => $val ) {
                if ( taxonomy_exists( $tax ) ) {
                    $term    = get_term_by( 'slug', $val, $tax );
                    $parts[] = $term ? $term->name : $val;
                } else {
                    $parts[] = $val;
                }
            }
            $label = implode( ' / ', array_filter( $parts ) ) ?: $variation->get_name();

            $count        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE product_id = %d", $variation_id ) );
            $variations[] = [
                'variation_id'  => $variation_id,
                'name'          => $label,
                'sku'           => $variation->get_sku(),
                'barcode_count' => $count,
            ];
        }

        return new WP_REST_Response( [ 'variations' => $variations ], 200 );
    }

    private static function get_barcodes( array $body ) {
        global $wpdb;

        $page       = max( 1, intval( $body['page'] ?? 1 ) );
        $per_page   = max( 1, intval( $body['per_page'] ?? 20 ) );
        $search     = sanitize_text_field( $body['search'] ?? '' );
        $product_id = intval( $body['product_id'] ?? 0 );

        $t     = $wpdb->prefix . 'product_barcodes';
        $where = '1=1';
        $args  = [];

        if ( $search !== '' ) {
            $where  .= ' AND barcode LIKE %s';
            $args[]  = '%' . $wpdb->esc_like( $search ) . '%';
        }
        if ( $product_id > 0 ) {
            $where  .= ' AND product_id = %d';
            $args[]  = $product_id;
        }

        $query   = "SELECT SQL_CALC_FOUND_ROWS * FROM {$t} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $args[]  = $per_page;
        $args[]  = ( $page - 1 ) * $per_page;
        $results = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );
        $total   = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

        $barcodes = [];
        foreach ( $results as $row ) {
            $pid      = (int) $row['product_id'];
            $product  = wc_get_product( $pid );
            $barcodes[] = [
                'id'           => (int) $row['id'],
                'barcode'      => $row['barcode'],
                'product_id'   => $pid,
                'product_name' => $product ? $product->get_name() : 'ไม่พบสินค้า (ID: ' . $pid . ')',
                'status'       => $row['status'],
                'created_at'   => $row['created_at'],
                'image_base64' => $row['image_base64'] ?? null,
            ];
        }

        return new WP_REST_Response( [
            'barcodes'    => $barcodes,
            'total'       => $total,
            'total_pages' => (int) ceil( $total / $per_page ),
        ], 200 );
    }

    private static function delete_barcode( array $body ) {
        global $wpdb;

        $id = intval( $body['id'] ?? 0 );
        if ( ! $id ) {
            return new WP_Error( 'missing_id', 'Barcode ID is required.', [ 'status' => 400 ] );
        }

        $t   = $wpdb->prefix . 'product_barcodes';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
        if ( ! $row ) {
            return new WP_Error( 'not_found', 'Barcode not found.', [ 'status' => 404 ] );
        }
        if ( $row->status === 'packed' ) {
            return new WP_Error( 'cannot_delete', 'Cannot delete a packed barcode.', [ 'status' => 403 ] );
        }

        $deleted = $wpdb->delete( $t, [ 'id' => $id ], [ '%d' ] );
        if ( $deleted ) {
            return new WP_REST_Response( [ 'success' => true, 'message' => 'ลบ Barcode สำเร็จ' ], 200 );
        }

        return new WP_REST_Response( [ 'success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบ' ], 500 );
    }
}

Barcode_Import_API::init();
