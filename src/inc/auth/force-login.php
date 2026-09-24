<?php
/**
 * Force login: ทุกหน้าต้อง login ก่อน
 * ยกเว้น shop-login, AJAX, REST, Cron, CLI
 */
function jaonaichan_force_login(): void {
    if ( is_user_logged_in() )                       return;
    if ( wp_doing_ajax() )                           return;
    if ( wp_doing_cron() )                           return;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
    if ( defined( 'WP_CLI' )       && WP_CLI )       return;
    if ( is_page( 'shop-login' ) )                   return;

    $current_url = home_url( $_SERVER['REQUEST_URI'] );

    // wp_login_url() ผ่าน filter ใน shop-login-redirect.php → ชี้ /shop-login/ พร้อม redirect_to อัตโนมัติ
    wp_safe_redirect( wp_login_url( $current_url ), 302 );
    exit;
}
add_action( 'template_redirect', 'jaonaichan_force_login', 1 );
