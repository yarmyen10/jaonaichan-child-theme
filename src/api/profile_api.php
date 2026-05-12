<?php
/**
 * User Profile REST API
 *
 * GET   /wp-json/bigboss-auth/v1/profile  — ดึง profile ของ user ที่ login อยู่
 * PATCH /wp-json/bigboss-auth/v1/profile  — อัปเดต profile fields
 */
class Profile_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'bigboss-auth/v1', '/profile', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'get_profile' ],
                'permission_callback' => [ self::class, 'check_permission' ],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [ self::class, 'update_profile' ],
                'permission_callback' => [ self::class, 'check_permission' ],
                'args'                => [
                    'display_name' => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'first_name'   => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'last_name'    => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'nickname'     => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'description'  => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
                ],
            ],
        ]);
    }

    // =========================================================================
    // GET /bigboss-auth/v1/profile
    // =========================================================================

    public static function get_profile(): WP_REST_Response {
        $user = wp_get_current_user();
        return new WP_REST_Response( self::format_profile( $user ), 200 );
    }

    // =========================================================================
    // PATCH /bigboss-auth/v1/profile
    // =========================================================================

    public static function update_profile( WP_REST_Request $request ): WP_REST_Response {
        $user    = wp_get_current_user();
        $updated = [];

        // display_name, first_name, last_name ใช้ wp_update_user()
        // nickname, description เก็บเป็น user_meta
        $core_fields = [ 'display_name', 'first_name', 'last_name', 'nickname', 'description' ];

        $user_data = [ 'ID' => $user->ID ];

        foreach ( $core_fields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $user_data[ $field ] = $value;
                $updated[]           = $field;
            }
        }

        if ( empty( $updated ) ) {
            return new WP_REST_Response([ 'success' => false, 'message' => 'ไม่มี field ที่ส่งมา' ], 400);
        }

        $result = wp_update_user( $user_data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'อัปเดต Profile แล้ว',
            'updated' => $updated,
            'data'    => self::format_profile( get_userdata( $user->ID ) ),
        ], 200);
    }

    // =========================================================================
    // Format helper
    // =========================================================================

    private static function format_profile( WP_User $user ): array {
        return [
            'id'            => $user->ID,
            'username'      => $user->user_login,
            'email'         => $user->user_email,
            'display_name'  => $user->display_name,
            'first_name'    => (string) get_user_meta( $user->ID, 'first_name',  true ),
            'last_name'     => (string) get_user_meta( $user->ID, 'last_name',   true ),
            'nickname'      => (string) get_user_meta( $user->ID, 'nickname',    true ),
            'description'   => (string) get_user_meta( $user->ID, 'description', true ),
            'registered_at' => $user->user_registered,
            'roles'         => array_values( $user->roles ),
            'role'          => $user->roles[0] ?? null,
            'avatar_url'    => get_avatar_url( $user->ID, [ 'size' => 96 ] ),
        ];
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }
}

Profile_API::init();
