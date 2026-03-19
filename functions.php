<?php
/**
 * JAO NAI CHAN Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package JAO NAI CHAN
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_JAO_NAI_CHAN_VERSION', '1.0.0' );

// require_once get_stylesheet_directory() . '/vendor/autoload.php';

// $dotenv = Dotenv\Dotenv::createImmutable(get_stylesheet_directory());
// $dotenv->load();

$inc_folders = [
    '/src/inc/enqueue/',
    '/src/inc/auth/',
    // '/src/inc/woocommerce/',
    // '/src/inc/i18n/',
    // '/src/inc/helpers/',
];

foreach ( $inc_folders as $folder ) {
    $files = glob( get_stylesheet_directory() . $folder . '*.php' );

    if ( ! $files ) {
        // Debug — บอกว่าหาไฟล์ไม่เจอตรงไหน
        error_log( 'No files found in: ' . get_stylesheet_directory() . $folder );
        continue;
    }

    foreach ( $files as $file ) {
        require_once $file;
    }
}

