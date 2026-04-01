<?php
/**
 * Auth REST API — Sign In
 */
class Auth_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'bigboss-auth/v1', '/signin', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'signin' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * POST /wp-json/bigboss-auth/v1/signin
     * Body: { "username": "...", "password": "..." }
     */
    public static function signin( WP_REST_Request $request ): WP_REST_Response {

        // Rate Limit — กัน Brute Force
        $ip       = $_SERVER['REMOTE_ADDR'];
        $attempts = (int) get_transient( 'login_attempts_' . $ip );

        if ( $attempts >= 5 ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ลองใหม่ในอีก 15 นาที',
            ], 429);
        }

        $username = sanitize_text_field( $request->get_param('username') );
        $password = $request->get_param('password');

        if ( empty($username) || empty($password) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'กรุณากรอก username และ password',
            ], 400);
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error($user) ) {
            // นับจำนวนครั้งที่ Login ผิด
            set_transient( 'login_attempts_' . $ip, $attempts + 1, 15 * MINUTE_IN_SECONDS );

            return new WP_REST_Response([
                'success' => false,
                'message' => 'username หรือ password ไม่ถูกต้อง',
            ], 401);
        }

        // Login สำเร็จ → Reset attempts
        delete_transient( 'login_attempts_' . $ip );

        $token = wp_generate_auth_cookie( $user->ID, time() + DAY_IN_SECONDS, 'auth' );

        return new WP_REST_Response([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'roles'        => $user->roles,
            ],
        ], 200);
    }
}

Auth_API::init();