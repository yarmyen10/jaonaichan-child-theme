<?php
/**
 * Social Login Block: บล็อก user ใหม่ที่ไม่มีในระบบ
 *
 * jaonaichan-social-login plugin จัดการ block logic เองภายใน (return WP_Error แทน wp_insert_user)
 * ไฟล์นี้คุม fallback กรณี plugin อื่นสร้าง user นอก wp-admin โดยไม่ได้ตั้งใจ
 *
 * บน site นี้ checkout ต้องล็อกอิน + ไม่มี open registration
 * ดังนั้น user_register นอก wp-admin = ไม่ได้รับอนุญาต → ลบทิ้ง
 */

function jaonaichan_block_social_registration( int $user_id ): void {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $user_id );
    wp_clear_auth_cookie();
    wp_redirect( add_query_arg( 'social_error', 'not_registered', home_url( '/shop-login/' ) ) );
    exit;
}

function jaonaichan_block_new_user_register( int $user_id ): void {
    if ( is_admin() ) return;
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
    jaonaichan_block_social_registration( $user_id );
}
add_action( 'user_register', 'jaonaichan_block_new_user_register', 1 );
