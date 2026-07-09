<?php
/**
 * Auth REST API — Sign In / Sign Out
 */
class Auth_API {

    public static function init(): void {
        // Inject bb_jwt cookie as HTTP_AUTHORIZATION.
        // Two-layer approach:
        // 1) File-load time — for servers where determine_current_user fires after theme loads.
        // 2) determine_current_user priority 9 — fires right before the JWT plugin (priority 10),
        //    handles servers where WP bootstraps the user BEFORE loading the theme.
        if ( ! empty( $_COOKIE['bb_jwt'] ) && empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . sanitize_text_field( wp_unslash( $_COOKIE['bb_jwt'] ) );
        }

        add_filter( 'determine_current_user', function ( $user_id ) {
            if ( $user_id ) return $user_id;
            if ( ! empty( $_COOKIE['bb_jwt'] ) && empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
                $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . sanitize_text_field( wp_unslash( $_COOKIE['bb_jwt'] ) );
            }
            return $user_id;
        }, 9 );

        add_action( 'rest_api_init', [ self::class, 'register_routes' ], 10 );

        add_filter('jwt_auth_token_before_dispatch', function ($data, $user) {
            $data['roles']      = $user->roles;
            $data['role']       = $user->roles[0] ?? null;
            $data['avatar_url'] = get_avatar_url( $user->ID, [ 'size' => 96 ] );

            return $data;
        }, 10, 2);

        add_filter( 'rest_authentication_errors', function ($result) {
            $route = $_GET['rest_route'] ?? '';
            $uri   = $_SERVER['REQUEST_URI'] ?? '';

            $public_routes = [
                '/bigboss-auth/v1/ping',
                '/bigboss-auth/v1/signin',
                '/bigboss-auth/v1/signout',
                '/jwt-auth/v1/token',
                '/jwt-auth/v1/token/validate',
            ];

            foreach ($public_routes as $public_route) {
                if (
                    str_contains($route, $public_route) ||
                    str_contains($uri, '/wp-json' . $public_route)
                ) {
                    return null;
                }
            }

            return $result;
        }, 9999 );

    }

    public static function register_routes(): void {
        register_rest_route('bigboss-auth/v1', '/ping', [
            'methods'             => 'GET',
            'callback'            => function () { return ['ok' => true]; },
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'bigboss-auth/v1', '/signin', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'signin' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'bigboss-auth/v1', '/signout', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'signout' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * POST /wp-json/bigboss-auth/v1/signin
     * Body: { "username": "...", "password": "..." }
     * Sets bb_jwt httpOnly cookie; does NOT return the token in the body.
     */
    public static function signin( WP_REST_Request $request ): WP_REST_Response {

        // Rate limit — brute-force protection
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
            set_transient( 'login_attempts_' . $ip, $attempts + 1, 15 * MINUTE_IN_SECONDS );
            return new WP_REST_Response([
                'success' => false,
                'message' => 'username หรือ password ไม่ถูกต้อง',
            ], 401);
        }

        if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ไม่มีสิทธิ์เข้าใช้งาน',
            ], 403);
        }

        delete_transient( 'login_attempts_' . $ip );

        // Generate JWT via the plugin endpoint (enriched by jwt_auth_token_before_dispatch)
        $jwt_request = new WP_REST_Request( 'POST', '/jwt-auth/v1/token' );
        $jwt_request->set_body_params([ 'username' => $username, 'password' => $password ]);
        $jwt_response = rest_do_request( $jwt_request );
        $jwt_data     = $jwt_response->get_data();

        if ( $jwt_response->get_status() !== 200 || empty( $jwt_data['token'] ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ไม่สามารถออก Token ได้',
            ], 500);
        }

        // httpOnly cookie — JS อ่านไม่ได้
        // SameSite=None เพื่อรองรับ cross-site (localhost dev → jaonaichan.com)
        // Secure=true บังคับให้ส่งผ่าน HTTPS เท่านั้น — ชดเชย SameSite=None
        setcookie('bb_jwt', $jwt_data['token'], [
            'expires'  => time() + 7 * DAY_IN_SECONDS,
            'path'     => '/wp-json/',
            'domain'   => '.jaonaichan.com',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);

        return new WP_REST_Response([
            'success' => true,
            'user'    => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'roles'        => $user->roles,
                'role'         => $user->roles[0] ?? null,
                'avatar_url'   => get_avatar_url( $user->ID, [ 'size' => 96 ] ),
            ],
        ], 200);
    }

    /**
     * POST /wp-json/bigboss-auth/v1/signout
     * Expires the bb_jwt cookie.
     */
    public static function signout(): WP_REST_Response {
        setcookie('bb_jwt', '', [
            'expires'  => time() - DAY_IN_SECONDS,
            'path'     => '/wp-json/',
            'domain'   => '.jaonaichan.com',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);

        return new WP_REST_Response(['success' => true], 200);
    }
}

Auth_API::init();
