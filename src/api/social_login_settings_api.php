<?php
/**
 * Social Login Settings REST API
 *
 * Exposes credentials stored by the jaonaichan-social-login plugin
 * so that bigboss.jaonaichan.com can read and update them.
 *
 * GET  /wp-json/bigboss-auth/v1/social-login-settings  — Read all provider keys (admin only)
 * POST /wp-json/bigboss-auth/v1/social-login-settings  — Update keys (admin only)
 *
 * Expected POST body (JSON, all fields optional):
 * {
 *   "line":     { "channel_id": "...", "channel_secret": "..." },
 *   "google":   { "client_id": "...",  "client_secret": "..." },
 *   "facebook": { "app_id": "...",     "app_secret": "..." }
 * }
 */
class Social_Login_Settings_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'bigboss-auth/v1', '/social-login-settings', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'get_settings' ],
                'permission_callback' => [ self::class, 'check_admin' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'update_settings' ],
                'permission_callback' => [ self::class, 'check_admin' ],
            ],
        ] );
    }

    public static function get_settings(): WP_REST_Response {
        if ( ! class_exists( 'JSL_Settings' ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'jaonaichan-social-login plugin ไม่ได้ติดตั้ง',
            ], 503 );
        }
        return new WP_REST_Response([
            'success' => true,
            'data'    => JSL_Settings::get(),
        ], 200 );
    }

    public static function update_settings( WP_REST_Request $request ): WP_REST_Response {
        if ( ! class_exists( 'JSL_Settings' ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'jaonaichan-social-login plugin ไม่ได้ติดตั้ง',
            ], 503 );
        }

        $body = $request->get_json_params();
        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ส่งข้อมูลไม่ถูกต้อง (JSON body required)',
            ], 400 );
        }

        JSL_Settings::update( $body );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'อัปเดต Social Login keys แล้ว',
            'data'    => JSL_Settings::get(),
        ], 200 );
    }

    public static function check_admin(): bool {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }
}

Social_Login_Settings_API::init();
