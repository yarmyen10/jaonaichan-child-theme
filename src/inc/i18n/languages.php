<?php
/**
 * Language & i18n Setup
 */

/**
 * โหลดไฟล์ภาษาของ Theme
 * ไฟล์ .mo จะอยู่ที่ /src/inc/i18n/languages/
 */
function theme_load_textdomain() {
    $mo_file = get_stylesheet_directory() . '/src/inc/i18n/languages/' 
               . $_ENV['TEXTDOMAIN_NAME'] . '-' . get_locale() . '.mo';

    $result = load_textdomain( $_ENV['TEXTDOMAIN_NAME'], $mo_file );

    do_action('qm/info', 'mo_file: ' . $mo_file);
    do_action('qm/info', 'load_textdomain result: ' . ( $result ? 'YES' : 'NO' ));

    // เช็ค global $l10n ว่า textdomain ถูกลงทะเบียนไหม
    global $l10n;
    do_action('qm/info', 'l10n has domain: ' . ( isset($l10n[$_ENV['TEXTDOMAIN_NAME']]) ? 'YES' : 'NO' ));
}
add_action( 'init', 'theme_load_textdomain', 1 );


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
