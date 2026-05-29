<?php
/**
 * Social Login Block: บล็อก Facebook / Google / LINE login สำหรับ user ที่ยังไม่มีใน DB
 *
 * - User ที่มีอยู่แล้ว → login ผ่าน social ได้ตามปกติ
 * - User ใหม่ที่ไม่เคยลงทะเบียน → ลบ user ที่ plugin สร้างให้ และ redirect กลับ /shop-login/
 *
 * Hook หลัก : mo_social_login_update_user_profile (miniOrange-specific)
 * Hook สำรอง: user_register — บน site นี้ checkout ต้องล็อกอิน + ไม่มี open registration
 *             ดังนั้น user_register นอก wp-admin = social login เสมอ
 *             LINE login ไม่ถูกกระทบ: find_or_create_user() return WP_Error ก่อนถึง wp_insert_user()
 */


/**
 * ลบ user + เคลียร์ cookie + redirect กลับ /shop-login/?social_login_blocked=1
 */
function jaonaichan_block_social_registration( int $user_id ): void {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $user_id );
    wp_clear_auth_cookie();
    wp_redirect( add_query_arg( 'social_login_blocked', '1', wp_login_url() ) );
    exit;
}


/**
 * Hook หลัก: miniOrange fires action นี้หลังสร้าง / อัปเดต user profile จาก social provider
 * $is_new_user = true หมายถึง user ถูกสร้างใหม่ในรอบนี้ → บล็อก
 */
function jaonaichan_mo_social_block_new_user( $user_id, $customer_data, $is_new_user ): void {
    if ( ! $is_new_user ) {
        return;
    }
    jaonaichan_block_social_registration( (int) $user_id );
}
add_action( 'mo_social_login_update_user_profile', 'jaonaichan_mo_social_block_new_user', 1, 3 );


/**
 * Hook สำรอง: ดักทุก user_register นอก wp-admin
 * LINE login ไม่ถูกกระทบ — return WP_Error ก่อนถึง wp_insert_user() แล้ว
 */
function jaonaichan_mo_oauth_block_new_user( int $user_id ): void {
    if ( is_admin() ) return;
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
    jaonaichan_block_social_registration( $user_id );
}
add_action( 'user_register', 'jaonaichan_mo_oauth_block_new_user', 1 );
