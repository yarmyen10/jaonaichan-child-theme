<?php
defined( 'ABSPATH' ) || exit;

class Barcode_Pack_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/barcode-pack', [
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
            case 'get_order_items':
                return self::get_order_items( $body );
            case 'validate_barcode':
                return self::validate_barcode( $body );
            case 'confirm_pack':
                return self::confirm_pack( $body );
            case 'save_tracking':
                return self::save_tracking( $body );
            default:
                return new WP_Error( 'invalid_action', 'Invalid action.', [ 'status' => 400 ] );
        }
    }

    private static function get_order_items( array $body ) {
        $order_id = intval( $body['order_id'] ?? 0 );
        if ( ! $order_id ) {
            return new WP_Error( 'missing_order_id', 'order_id is required.', [ 'status' => 400 ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_not_found', 'Order not found.', [ 'status' => 404 ] );
        }

        $items = [];
        foreach ( $order->get_items() as $item_id => $item ) {
            /** @var WC_Order_Item_Product $item */
            $items[] = [
                'product_id'    => $item->get_variation_id() ?: $item->get_product_id(),
                'order_item_id' => $item_id,
                'name'          => $item->get_name(),
                'qty'           => $item->get_quantity(),
            ];
        }

        return new WP_REST_Response( [ 'items' => $items ], 200 );
    }

    private static function validate_barcode( array $body ) {
        $barcode = sanitize_text_field( $body['barcode'] ?? '' );
        if ( $barcode === '' ) {
            return new WP_Error( 'missing_barcode', 'barcode is required.', [ 'status' => 400 ] );
        }

        if ( ! class_exists( 'Barcode_Pack_DB' ) ) {
            return new WP_Error( 'plugin_missing', 'Barcode Pack plugin is not active.', [ 'status' => 503 ] );
        }

        $row = Barcode_Pack_DB::get_available( $barcode );
        if ( ! $row ) {
            return new WP_Error( 'barcode_not_found', 'Barcode not found or already packed.', [ 'status' => 404 ] );
        }

        $product = wc_get_product( $row->product_id );

        return new WP_REST_Response( [
            'product_id'   => (int) $row->product_id,
            'product_name' => $product ? $product->get_name() : '(Unknown product)',
        ], 200 );
    }

    private static function confirm_pack( array $body ) {
        $order_id = intval( $body['order_id'] ?? 0 );
        $lot_id   = intval( $body['lot_id'] ?? 0 );
        $scanned  = $body['scanned'] ?? [];

        if ( ! $order_id || ! is_array( $scanned ) ) {
            return new WP_Error( 'missing_params', 'order_id and scanned are required.', [ 'status' => 400 ] );
        }

        if ( ! class_exists( 'Barcode_Pack_DB' ) ) {
            return new WP_Error( 'plugin_missing', 'Barcode Pack plugin is not active.', [ 'status' => 503 ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_not_found', 'Order not found.', [ 'status' => 404 ] );
        }

        // Build product_id → order_item_id map — variation_id ?: parent_id, same as get_order_items
        $product_to_item = [];
        foreach ( $order->get_items() as $item_id => $item ) {
            /** @var WC_Order_Item_Product $item */
            $product_to_item[ $item->get_variation_id() ?: $item->get_product_id() ] = $item_id;
        }

        $user_id     = get_current_user_id();
        $order_items = $order->get_items();

        foreach ( $scanned as $product_id => $barcodes ) {
            $product_id    = (int) $product_id;
            $order_item_id = $product_to_item[ $product_id ] ?? null;

            if ( ! $order_item_id || ! isset( $order_items[ $order_item_id ] ) ) {
                continue;
            }

            $item   = $order_items[ $order_item_id ];
            $packed = $item->get_meta( '_packed_barcodes' ) ?: [];

            foreach ( (array) $barcodes as $barcode ) {
                $barcode = sanitize_text_field( $barcode );
                if ( $barcode === '' ) continue;

                if ( Barcode_Pack_DB::pack( $barcode, $order_id, $order_item_id, $user_id ) ) {
                    $packed[] = $barcode;
                }
            }

            $item->update_meta_data( '_packed_barcodes', $packed );
            $item->save();
        }

        // Check if all items are fully packed and complete the order if so
        $order      = wc_get_order( $order_id );
        $all_packed = true;
        foreach ( $order->get_items() as $item ) {
            /** @var WC_Order_Item_Product $item */
            if ( count( $item->get_meta( '_packed_barcodes' ) ?: [] ) < $item->get_quantity() ) {
                $all_packed = false;
                break;
            }
        }

        if ( $all_packed ) {
            $order->update_status( 'packed', 'All items packed via Barcode Pack.' );
        }

        if ( $lot_id ) {
            $order->update_meta_data( '_lot_id', $lot_id );
            $order->save();
        }

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    private static function save_tracking( array $body ) {
        $order_id = intval( $body['order_id'] ?? 0 );
        $parcels  = $body['parcels'] ?? [];

        if ( ! $order_id || ! is_array( $parcels ) || empty( $parcels ) ) {
            return new WP_Error( 'missing_params', 'order_id and parcels required.', [ 'status' => 400 ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_not_found', 'Order not found.', [ 'status' => 404 ] );
        }

        $clean = [];
        foreach ( $parcels as $p ) {
            $carrier = sanitize_text_field( $p['carrier'] ?? '' );
            $number  = sanitize_text_field( $p['number']  ?? '' );
            if ( $carrier && $number ) {
                $clean[] = [ 'carrier' => $carrier, 'number' => $number ];
            }
        }

        if ( empty( $clean ) ) {
            return new WP_Error( 'no_valid_parcels', 'No valid parcels provided.', [ 'status' => 400 ] );
        }

        $order->update_meta_data( '_tracking_parcels', $clean );
        $order->update_status( 'shipped', 'Tracking added via Barcode Pack.' );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }
}

Barcode_Pack_API::init();
