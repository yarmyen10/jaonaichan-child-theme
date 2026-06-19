<?php
defined( 'ABSPATH' ) || exit;

class Barcode_Import_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
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
            case 'search_products':
                return self::search_products( $body );
            case 'get_variations':
                return self::get_variations( $body );
            case 'save_barcode':
                return self::save_barcode( $body );
            case 'get_barcodes':
                return self::get_barcodes( $body );
            case 'delete_barcode':
                return self::delete_barcode( $body );
            default:
                return new WP_Error( 'invalid_action', 'Invalid action.', [ 'status' => 400 ] );
        }
    }

    private static function search_products( array $body ) {
        global $wpdb;

        $query = sanitize_text_field( $body['query'] ?? '' );
        if ( $query === '' ) {
            return new WP_Error( 'missing_query', 'query is required.', [ 'status' => 400 ] );
        }

        $like = '%' . $wpdb->esc_like( $query ) . '%';

        $name_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'product'
               AND post_status = 'publish'
               AND post_title LIKE %s
             LIMIT 20",
            $like
        ) );

        $sku_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'
               AND pm.meta_key = '_sku'
               AND pm.meta_value LIKE %s
             LIMIT 20",
            $like
        ) );

        $product_ids = array_slice(
            array_unique( array_merge(
                array_map( 'intval', (array) $name_ids ),
                array_map( 'intval', (array) $sku_ids )
            ) ),
            0,
            20
        );

        $t        = $wpdb->prefix . 'product_barcodes';
        $products = [];

        foreach ( $product_ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) continue;

            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE product_id = %d",
                $id
            ) );

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

            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE product_id = %d",
                $variation_id
            ) );

            $variations[] = [
                'variation_id'  => $variation_id,
                'name'          => $label,
                'sku'           => $variation->get_sku(),
                'barcode_count' => $count,
            ];
        }

        return new WP_REST_Response( [ 'variations' => $variations ], 200 );
    }

    private static function save_barcode( array $body ) {
        $product_id = intval( $body['product_id'] ?? 0 );
        $barcode    = sanitize_text_field( $body['barcode'] ?? '' );

        if ( ! $product_id || $barcode === '' ) {
            return new WP_Error( 'missing_params', 'product_id and barcode are required.', [ 'status' => 400 ] );
        }

        if ( ! wc_get_product( $product_id ) ) {
            return new WP_Error( 'invalid_product', 'Product not found.', [ 'status' => 404 ] );
        }

        if ( ! class_exists( 'Barcode_Pack_DB' ) ) {
            return new WP_Error( 'plugin_missing', 'Barcode Pack plugin is not active.', [ 'status' => 503 ] );
        }

        $result = Barcode_Pack_DB::insert_barcodes( [ $barcode ], $product_id );

        if ( $result['inserted'] > 0 ) {
            return new WP_REST_Response( [ 'success' => true, 'message' => 'บันทึก Barcode สำเร็จ' ], 200 );
        }

        if ( $result['skipped'] > 0 ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Barcode นี้มีอยู่แล้วในระบบ' ], 200 );
        }

        return new WP_REST_Response( [ 'success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก' ], 200 );
    }

    private static function get_barcodes( array $body ) {
        global $wpdb;

        $page     = max( 1, intval( $body['page'] ?? 1 ) );
        $per_page = max( 1, intval( $body['per_page'] ?? 20 ) );
        $search   = sanitize_text_field( $body['search'] ?? '' );
        
        $t = $wpdb->prefix . 'product_barcodes';
        
        $where = "1=1";
        $args  = [];
        if ( $search !== '' ) {
            $where .= " AND barcode LIKE %s";
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $query = "SELECT SQL_CALC_FOUND_ROWS * FROM {$t} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $args[] = $per_page;
        $args[] = ( $page - 1 ) * $per_page;

        $results = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );
        $total   = (int) $wpdb->get_var( "SELECT FOUND_ROWS()" );

        $barcodes = [];
        foreach ( $results as $row ) {
            $product_id = (int) $row['product_id'];
            $product    = wc_get_product( $product_id );

            $barcodes[] = [
                'id'           => (int) $row['id'],
                'barcode'      => $row['barcode'],
                'product_id'   => $product_id,
                'product_name' => $product ? $product->get_name() : 'ไม่พบสินค้า (ID: ' . $product_id . ')',
                'status'       => $row['status'],
                'created_at'   => $row['created_at'],
            ];
        }

        return new WP_REST_Response( [
            'barcodes'    => $barcodes,
            'total'       => $total,
            'total_pages' => ceil( $total / $per_page ),
        ], 200 );
    }

    private static function delete_barcode( array $body ) {
        global $wpdb;

        $id = intval( $body['id'] ?? 0 );
        if ( ! $id ) {
            return new WP_Error( 'missing_id', 'Barcode ID is required.', [ 'status' => 400 ] );
        }

        $t = $wpdb->prefix . 'product_barcodes';
        
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
        if ( ! $row ) {
            return new WP_Error( 'not_found', 'Barcode not found.', [ 'status' => 404 ] );
        }

        if ( $row->status === 'packed' ) {
            return new WP_Error( 'cannot_delete', 'Cannot delete a barcode that is already packed.', [ 'status' => 403 ] );
        }

        $deleted = $wpdb->delete( $t, [ 'id' => $id ], [ '%d' ] );

        if ( $deleted ) {
            return new WP_REST_Response( [ 'success' => true, 'message' => 'ลบ Barcode สำเร็จ' ], 200 );
        }

        return new WP_REST_Response( [ 'success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบ' ], 500 );
    }
}

Barcode_Import_API::init();
