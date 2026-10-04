<?php
/**
 * Shop banners REST API (Trello "Banner Management") — what the bigboss "Banner Management" page talks to.
 * The Shop page (src/inc/woocommerce/shop-ux.php) reads the same option directly.
 *
 *   GET  /wp-json/jaonaichan/v1/banners         → { success, data: Banner[] }          every banner, active or not, in display order
 *   PUT  /wp-json/jaonaichan/v1/banners         ← { banners: Banner[] }                replaces the whole list (order = display order); ids "new-…" get a real id
 *   POST /wp-json/jaonaichan/v1/banners/upload  ← multipart field `file` (png/jpeg/webp, ≤ 2MB) → { success, url, name, id }   stored in the media library
 *
 *   Banner = { id, title, description, isActive, image: { url, name } | null, link, updatedAt }
 *
 * Rules (also enforced here, not only in the UI): an active banner needs an image · image.url must be one of our own uploads · link is empty, an
 * http(s) URL or a site path ("/shop") — never javascript: · at most 20 banners · updatedAt moves only for banners that actually changed.
 * Deleting a banner does not delete its picture from the media library (it may still be used elsewhere).
 */
defined( 'ABSPATH' ) || exit;

class Banners_API {

    const OPTION    = 'jn_shop_banners';
    const MAX       = 20;
    const MAX_BYTES = 2 * 1024 * 1024;

    /** Seconds between two slides on the Shop page; 0 = the slider does not move by itself. Saved with the list, read by shop-ux.php. */
    const SETTINGS_OPTION = 'jn_shop_banner_settings';
    const INTERVAL_DEFAULT = 5;
    const INTERVAL_MIN     = 2;
    const INTERVAL_MAX     = 60;

    /** The text drawn over the picture on the Shop page (the mock's .banner-content): heading (up to 2 lines), sub-heading and a button that goes to `link`. */
    const HEADING_MAX    = 60;
    const SUBHEADING_MAX = 120;
    const CTA_MAX        = 24;

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/banners', [
            [ 'methods' => 'GET', 'callback' => [ self::class, 'get_banners' ],  'permission_callback' => [ self::class, 'check_permission' ] ],
            [ 'methods' => 'PUT', 'callback' => [ self::class, 'save_banners' ], 'permission_callback' => [ self::class, 'check_permission' ] ],
        ] );
        register_rest_route( 'jaonaichan/v1', '/banners/upload', [
            'methods' => 'POST', 'callback' => [ self::class, 'upload_image' ], 'permission_callback' => [ self::class, 'check_permission' ],
        ] );
    }

    public static function check_permission(): bool {
        return Auth_API::is_admin();
    }

    /** Banner text: tags stripped; a heading keeps 2 lines at most (blank lines dropped), the sub-heading and the button label are one line. */
    public static function text( string $kind, $v ): string {
        $v = (string) $v;
        if ( $kind === 'heading' ) {
            $lines = array_values( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $v ) ) ), 'strlen' ) );
            return mb_substr( implode( "\n", array_slice( $lines, 0, 2 ) ), 0, self::HEADING_MAX );
        }
        return mb_substr( sanitize_text_field( $v ), 0, $kind === 'subheading' ? self::SUBHEADING_MAX : self::CTA_MAX );
    }

    /** The stored list, cleaned (a hand-edited or old option cannot break the page). */
    public static function stored(): array {
        $raw = get_option( self::OPTION, [] );
        if ( is_string( $raw ) ) $raw = json_decode( $raw, true );
        if ( ! is_array( $raw ) ) return [];
        $out = [];
        foreach ( $raw as $b ) {
            if ( ! is_array( $b ) ) continue;
            $out[] = [
                'id'          => (string) ( $b['id'] ?? '' ),
                'title'       => (string) ( $b['title'] ?? '' ),
                'description' => (string) ( $b['description'] ?? '' ),
                'isActive'    => ! empty( $b['isActive'] ),
                'image'       => ! empty( $b['image']['url'] ) ? [ 'url' => (string) $b['image']['url'], 'name' => (string) ( $b['image']['name'] ?? '' ) ] : null,
                'link'        => (string) ( $b['link'] ?? '' ),
                'heading'     => self::text( 'heading', $b['heading'] ?? '' ),
                'subheading'  => self::text( 'subheading', $b['subheading'] ?? '' ),
                'ctaLabel'    => self::text( 'cta', $b['ctaLabel'] ?? '' ),
                'updatedAt'   => (string) ( $b['updatedAt'] ?? '' ),
            ];
        }
        return $out;
    }

    /** A JSON integer: 0, or INTERVAL_MIN–INTERVAL_MAX (no "5", no 2.5, no true). */
    private static function interval_ok( $v ): bool {
        return is_int( $v ) && ( $v === 0 || ( $v >= self::INTERVAL_MIN && $v <= self::INTERVAL_MAX ) );
    }

    /** What the Shop uses: the saved value, or the default when nothing valid is saved (a hand-edited option cannot break the slider). */
    public static function interval(): int {
        $s = get_option( self::SETTINGS_OPTION, [] );
        $v = is_array( $s ) ? ( $s['intervalSeconds'] ?? null ) : null;
        return self::interval_ok( $v ) ? $v : self::INTERVAL_DEFAULT;
    }

    public static function get_banners(): array {
        return [ 'success' => true, 'data' => self::stored(), 'settings' => [ 'intervalSeconds' => self::interval() ] ];
    }

    private static function fail( string $code, string $message, int $index = -1 ): WP_Error {
        return new WP_Error( $code, $message, [ 'status' => 422, 'index' => $index ] );
    }

    /** "https://x/y" and "//x/y" compare equal here: only the part after the scheme matters. */
    private static function no_scheme( string $url ): string {
        return preg_replace( '#^https?:#i', '', $url );
    }

    public static function save_banners( WP_REST_Request $request ): array|WP_Error {
        $params = $request->get_json_params();
        $in     = $params['banners'] ?? null;
        if ( ! is_array( $in ) ) return self::fail( 'banners_missing', 'ไม่พบรายการ banners' );
        if ( count( $in ) > self::MAX ) return self::fail( 'banners_too_many', 'มีแบนเนอร์ได้ไม่เกิน ' . self::MAX . ' อัน' );

        $old     = [];
        foreach ( self::stored() as $b ) $old[ $b['id'] ] = $b;
        $uploads = self::no_scheme( wp_get_upload_dir()['baseurl'] ) . '/';
        $now     = gmdate( 'c' );
        $out     = [];
        $seen    = [];

        foreach ( array_values( $in ) as $i => $b ) {
            if ( ! is_array( $b ) ) return self::fail( 'banner_invalid', 'ข้อมูลแบนเนอร์ไม่ถูกต้อง', $i );
            $n = $i + 1;

            $id = (string) ( $b['id'] ?? '' );
            if ( ! preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $id ) || str_starts_with( $id, 'new-' ) || isset( $seen[ $id ] ) ) $id = 'bn_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 );
            $seen[ $id ] = true;

            $title = mb_substr( sanitize_text_field( (string) ( $b['title'] ?? '' ) ), 0, 120 );
            $desc  = mb_substr( sanitize_text_field( (string) ( $b['description'] ?? '' ) ), 0, 300 );

            $image = null;
            if ( ! empty( $b['image']['url'] ) ) {
                $url = esc_url_raw( (string) $b['image']['url'] );
                if ( $url === '' || ! str_starts_with( self::no_scheme( $url ), $uploads ) ) return self::fail( 'banner_image_foreign', "แบนเนอร์ที่ {$n}: รูปต้องอัปโหลดผ่านหน้านี้", $i );
                $image = [ 'url' => $url, 'name' => mb_substr( sanitize_text_field( (string) ( $b['image']['name'] ?? '' ) ), 0, 200 ) ];
            }

            $link = trim( (string) ( $b['link'] ?? '' ) );
            if ( $link !== '' ) {
                $ok = ( str_starts_with( $link, '/' ) && ! str_starts_with( $link, '//' ) && ! preg_match( '/[\s<>"]/', $link ) )
                   || ( preg_match( '#^https?://#i', $link ) && filter_var( $link, FILTER_VALIDATE_URL ) );
                if ( ! $ok ) return self::fail( 'banner_link_invalid', "แบนเนอร์ที่ {$n}: ลิงก์ต้องขึ้นต้นด้วย http(s):// หรือ /", $i );
                $link = esc_url_raw( $link );
            }

            $active = ! empty( $b['isActive'] );
            if ( $active && ! $image ) return self::fail( 'banner_active_needs_image', "แบนเนอร์ที่ {$n}: เปิดใช้งานได้เมื่อมีรูปเท่านั้น", $i );

            $cta = self::text( 'cta', $b['ctaLabel'] ?? '' );
            if ( $cta !== '' && $link === '' ) return self::fail( 'banner_cta_needs_link', "แบนเนอร์ที่ {$n}: ข้อความปุ่มต้องมี Banner Link (ปุ่มพาไปลิงก์นั้น)", $i );

            $row = [ 'id' => $id, 'title' => $title !== '' ? $title : 'Banner ' . $n, 'description' => $desc, 'isActive' => $active, 'image' => $image, 'link' => $link,
                     'heading' => self::text( 'heading', $b['heading'] ?? '' ), 'subheading' => self::text( 'subheading', $b['subheading'] ?? '' ), 'ctaLabel' => $cta ];
            $prev = $old[ $id ] ?? null;
            $same = $prev && array_diff_key( $prev, [ 'updatedAt' => 1 ] ) === $row;
            $row['updatedAt'] = $same ? $prev['updatedAt'] : $now;
            $out[] = $row;
        }

        // `settings` is optional: left out = unchanged. Checked before anything is written, like the list.
        $interval = self::interval();
        if ( isset( $params['settings'] ) ) {
            $set = $params['settings'];
            if ( ! is_array( $set ) ) return self::fail( 'settings_invalid', 'ข้อมูลการตั้งค่าไม่ถูกต้อง' );
            if ( array_key_exists( 'intervalSeconds', $set ) ) {
                if ( ! self::interval_ok( $set['intervalSeconds'] ) ) return self::fail( 'banner_interval_invalid', 'เวลาเปลี่ยนสไลด์ต้องเป็น 0 (ไม่เลื่อนเอง) หรือจำนวนเต็ม ' . self::INTERVAL_MIN . '–' . self::INTERVAL_MAX . ' วินาที' );
                $interval = $set['intervalSeconds'];
            }
            update_option( self::SETTINGS_OPTION, [ 'intervalSeconds' => $interval ], false );
        }

        update_option( self::OPTION, $out, false );
        return [ 'success' => true, 'data' => $out, 'settings' => [ 'intervalSeconds' => $interval ] ];
    }

    public static function upload_image( WP_REST_Request $request ): array|WP_Error {
        $file = $request->get_file_params()['file'] ?? null;
        if ( ! is_array( $file ) || ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) return self::fail( 'upload_missing', 'ไม่พบไฟล์ที่อัปโหลด' );
        if ( (int) $file['size'] > self::MAX_BYTES ) return self::fail( 'upload_too_big', 'ขนาดไฟล์เกิน 2MB' );

        $mimes = [ 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ];
        $info  = @getimagesize( $file['tmp_name'] );   // the real content, not the name the browser sent
        if ( ! $info || ! in_array( $info[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ], true ) ) return self::fail( 'upload_type', 'รองรับเฉพาะไฟล์ JPG / PNG / WEBP' );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $id = media_handle_sideload( [ 'name' => sanitize_file_name( (string) $file['name'] ), 'tmp_name' => $file['tmp_name'] ], 0, null, [ 'test_form' => false, 'mimes' => $mimes ] );
        if ( is_wp_error( $id ) ) return self::fail( 'upload_failed', 'อัปโหลดไม่สำเร็จ: ' . $id->get_error_message() );

        update_post_meta( $id, '_jn_banner', 1 );
        $url = wp_get_attachment_url( $id );
        return [ 'success' => true, 'id' => (int) $id, 'url' => $url, 'name' => basename( (string) get_attached_file( $id ) ) ];
    }
}

Banners_API::init();
