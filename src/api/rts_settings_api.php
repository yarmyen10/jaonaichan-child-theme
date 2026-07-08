<?php
/**
 * RTS Shipping Settings REST API
 *
 * GET  /wp-json/jaonaichan/v1/settings/rts-shipping  — อ่าน shipping cost จาก WC zone "rts"
 * POST /wp-json/jaonaichan/v1/settings/rts-shipping  — อัปเดต cost { cost: float }
 */
class RTS_Shipping_Settings_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/settings/rts-shipping', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'get_settings' ],
                'permission_callback' => [ self::class, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'update_settings' ],
                'permission_callback' => [ self::class, 'check_permission' ],
                'args'                => [
                    'cost' => [ 'required' => true, 'type' => 'number' ],
                ],
            ],
        ] );
    }

    public static function get_settings(): array {
        foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
            if ( strtolower( $zone['zone_name'] ) === 'rts' ) {
                foreach ( $zone['shipping_methods'] as $method ) {
                    if ( $method->is_enabled() ) {
                        return [
                            'zone_name'    => $zone['zone_name'],
                            'method_title' => $method->get_title(),
                            'cost'         => (float) $method->cost,
                            'instance_id'  => $method->instance_id,
                        ];
                    }
                }
            }
        }
        return [ 'zone_name' => 'rts', 'method_title' => '', 'cost' => 0.0, 'instance_id' => null ];
    }

    public static function update_settings( WP_REST_Request $req ): array|WP_Error {
        $cost = (float) $req->get_param( 'cost' );
        foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
            if ( strtolower( $zone['zone_name'] ) === 'rts' ) {
                foreach ( $zone['shipping_methods'] as $method ) {
                    if ( $method->is_enabled() ) {
                        $key              = 'woocommerce_' . $method->id . '_' . $method->instance_id . '_settings';
                        $settings         = get_option( $key, [] );
                        $settings['cost'] = (string) $cost;
                        update_option( $key, $settings );
                        update_option( 'jn_rts_shipping_cost', (string) $cost );
                        WC_Cache_Helper::get_transient_version( 'shipping', true );
                        return [ 'success' => true, 'cost' => $cost ];
                    }
                }
            }
        }
        return new WP_Error( 'rts_zone_not_found', 'RTS shipping zone not found', [ 'status' => 404 ] );
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }
}

RTS_Shipping_Settings_API::init();
