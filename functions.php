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

require_once get_stylesheet_directory() . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(get_stylesheet_directory());
$dotenv->load();

$inc_folders = [
    '/src/inc/i18n/',
    '/src/inc/enqueue/',
    '/src/inc/auth/',
    '/src/inc/woocommerce/',
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

function register_custom_order_status() {
    $label = __( 'รอโอนเงิน', $_ENV['TEXTDOMAIN_NAME'] );
    
    // Debug ตรงนี้เลย
    do_action('qm/info', 'order status label: ' . $label);
    do_action('qm/info', 'order status textdomain: ' . $_ENV['TEXTDOMAIN_NAME']);
    
    register_post_status( 'wc-waiting-transfer', array(
        'label' => $label,
        // ...
    ));
}
add_action( 'init', 'register_custom_order_status', 5 );

// add_action('init', function() {
//     do_action('qm/info', '=== i18n Debug ===');
//     do_action('qm/info', 'textdomain : ' . $_ENV['TEXTDOMAIN_NAME']);
//     do_action('qm/info', 'site lang  : ' . get_option('WPLANG'));
//     do_action('qm/info', 'locale     : ' . get_locale());

//     // เช็คไฟล์ .mo มีจริงไหม
//     $mo_file = get_stylesheet_directory() . '/src/inc/i18n/languages/' 
//                . $_ENV['TEXTDOMAIN_NAME'] . '-' . get_locale() . '.mo';
    
//     do_action('qm/info', 'mo file   : ' . $mo_file);
//     do_action('qm/info', 'mo exists : ' . ( file_exists($mo_file) ? 'YES' : 'NO' ));
//     do_action('qm/info', 'translated: ' . __('รอโอนเงิน', $_ENV['TEXTDOMAIN_NAME']));
// });
