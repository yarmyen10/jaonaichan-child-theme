<?php
/**
 * Language & i18n Setup
 */

/**
 * โหลดไฟล์ภาษาของ Theme
 * ไฟล์ .mo จะอยู่ที่ /inc/i18n/languages/
 */
function theme_load_textdomain() {
    load_child_theme_textdomain(
        $_ENV['TEXTDOMAIN_NAME'],                                          // textdomain
        get_stylesheet_directory() . '/inc/i18n/languages'     // path ไฟล์ภาษา
    );
}
add_action( 'after_setup_theme', 'theme_load_textdomain' );


/**
 * ตั้งค่า Locale ตาม Site Language
 */
function theme_set_locale( $locale ) {
    // ถ้า Admin เลือกภาษาไทย → ใช้ th
    if ( get_option('WPLANG') === 'th' ) {
        return 'th';
    }
    return $locale;
}
add_filter( 'locale', 'theme_set_locale' );
