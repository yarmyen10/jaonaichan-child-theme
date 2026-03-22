<?php
/**
 * Redirect Manager
 */
class Theme_Redirect {

    /**
     * กำหนด Rules ทั้งหมดตรงนี้ที่เดียว
     */
    private static array $rules = [
        [
            'match'    => '/step/thank-you',     // URI ที่ต้องการ match
            'target'   => '/thank-you/',        // redirect ไปที่ไหน
            'pass_params' => ['wcf-order'],        // Query params ที่ต้องการส่งต่อ
            'status'   => 301,
        ],
        // เพิ่ม rule ใหม่ได้ตรงนี้
        // [
        //     'match'       => '/step/order',
        //     'target'      => '/order-complete/',
        //     'pass_params' => ['wcf-order'],
        //     'status'      => 302,
        // ],
    ];

    /**
     * เริ่มต้น Redirect Manager
     */
    public static function init(): void {
        add_action( 'template_redirect', [ self::class, 'handle' ] );
    }

    /**
     * จัดการ Redirect ทั้งหมด
     */
    public static function handle(): void {
        $uri = $_SERVER['REQUEST_URI'];

        foreach ( self::$rules as $rule ) {
            if ( self::match( $uri, $rule['match'] ) ) {
                self::redirect( $rule );
                return;
            }
        }
    }

    /**
     * เช็ค URI ตรงกับ Rule ไหม
     */
    private static function match( string $uri, string $pattern ): bool {
        return strpos( $uri, $pattern ) !== false;
    }

    /**
     * ทำการ Redirect
     */
    private static function redirect( array $rule ): void {
        $params = [];

        // เก็บ Query params ที่ต้องการส่งต่อ
        foreach ( $rule['pass_params'] ?? [] as $param ) {
            if ( isset( $_GET[$param] ) ) {
                $params[$param] = sanitize_text_field( $_GET[$param] );
            }
        }

        // สร้าง URL
        $url = home_url( $rule['target'] );
        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        wp_safe_redirect( $url, $rule['status'] ?? 302 );
        exit;
    }
}

// เริ่มต้นใช้งาน
Theme_Redirect::init();