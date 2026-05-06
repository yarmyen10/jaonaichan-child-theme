<?php
/**
 * Hook สำหรับ login flow ที่ไม่ผ่าน /shop-login/ template เอง
 * เช่น My Account form, plugin ที่เรียก wp_signon() แล้วยิง login_redirect filter
 *
 * ลำดับ fallback: requested_redirect_to → HTTP_REFERER → /shop/
 * ตัด /wp-admin และตัว login page ออกเสมอ
 *
 * shop-login-redirect.php กับ shop-login.php คุม wp-login.php / form submit อยู่แล้ว
 * ไฟล์นี้คุมเฉพาะ surface ที่เหลือ — ไม่ duplicate ตรรกะเดิม
 */

const JN_LOGIN_FALLBACK_PATH = '/shop/';

function jaonaichan_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    if ( is_wp_error( $user ) ) return $redirect_to;

    $candidate = $requested_redirect_to;

    // ข้าม default ของ WP ที่ส่ง admin role ไป /wp-admin
    if ( ! $candidate || strpos( $candidate, '/wp-admin' ) !== false ) {
        $candidate = ! empty( $_SERVER['HTTP_REFERER'] )
            ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
            : '';
    }

    // กัน loop กลับไปหน้า login
    foreach ( [ JN_SHOP_LOGIN_PATH, '/wp-login.php' ] as $blocked ) {
        if ( $candidate && strpos( $candidate, $blocked ) !== false ) {
            $candidate = '';
            break;
        }
    }

    return wp_validate_redirect( $candidate, home_url( JN_LOGIN_FALLBACK_PATH ) );
}
add_filter( 'login_redirect', 'jaonaichan_login_redirect', 10, 3 );
