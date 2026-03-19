<?php
/**
 * Language & i18n Setup
 */
function theme_load_textdomain() {
    load_textdomain(
        $_ENV['TEXTDOMAIN_NAME'],
        get_stylesheet_directory() . '/src/inc/i18n/languages/'
            . $_ENV['TEXTDOMAIN_NAME'] . '-' . get_locale() . '.mo'
    );
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
