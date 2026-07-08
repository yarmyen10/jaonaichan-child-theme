<?php
/**
 * กัน CDN / page-cache plugin (WP Rocket, W3TC, LiteSpeed ฯลฯ)
 * จาก cache หน้าที่ render ต่างกันตาม login state
 *
 * - nocache_headers() ส่ง Cache-Control: no-cache, must-revalidate ให้ browser/CDN
 * - DONOTCACHE* คือ flag ที่ plugin ส่วนใหญ่อ่านเพื่อข้าม object/page/db cache
 *
 * ทำงานบน send_headers (ก่อน body ออก) ครอบทุก path prefix ที่ระบุไว้
 * shop-login template มี nocache_headers() อยู่แล้ว — เรียกซ้ำไม่มีผลข้างเคียง
 */
class Theme_No_Cache {

    private static array $paths = [
        '/shop',
        '/shop-login',
        '/cart',
        '/checkout',
        '/my-account',
        '/dashboard',
    ];

    public static function init(): void {
        add_action( 'send_headers', [ self::class, 'maybe_send' ] );
    }

    public static function maybe_send(): void {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) return;

        $uri = strtok( $_SERVER['REQUEST_URI'], '?' );
        $uri = '/' . trim( $uri, '/' );

        foreach ( self::$paths as $path ) {
            $needle = rtrim( $path, '/' );
            if ( $uri === $needle || strpos( $uri, $needle . '/' ) === 0 ) {
                nocache_headers();
                if ( ! defined( 'DONOTCACHEPAGE' ) )   define( 'DONOTCACHEPAGE',   true );
                if ( ! defined( 'DONOTCACHEOBJECT' ) ) define( 'DONOTCACHEOBJECT', true );
                if ( ! defined( 'DONOTCACHEDB' ) )     define( 'DONOTCACHEDB',     true );
                return;
            }
        }
    }
}

Theme_No_Cache::init();
