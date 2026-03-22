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

$dotenv = Dotenv\Dotenv::createImmutable( get_stylesheet_directory() );
$dotenv->load();

// Autoload ทุกไฟล์ใน /src/inc/ ตามลำดับ
$inc_folders = [
    '/src/inc/i18n',
    '/src/inc/enqueue',
    '/src/inc/auth',
    '/src/inc/woocommerce',
    // '/src/inc/helpers',
];

foreach ( $inc_folders as $folder ) {
    $path = get_stylesheet_directory() . $folder;

    if ( ! is_dir( $path ) ) continue;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS )
    );

    foreach ( $files as $file ) {
        if ( $file->isFile() && $file->getExtension() === 'php' ) {
            require_once $file->getPathname();
        }
    }
}

add_action('init', function() {
    do_action('qm/info', '__DIR__: ' . __DIR__);
    do_action('qm/info', '__DIR__ <2: ' . dirname(__DIR__, 2));
    do_action('qm/info', 'get_stylesheet_directory_uri: ' . get_stylesheet_directory_uri());
    do_action('qm/info', '__FILE__: ' . __FILE__);
    do_action('qm/info', 'plugin_dir_url: ' . plugin_dir_url(dirname( __FILE__ )));
    do_action('qm/info', 'locale: ' . get_locale());
    do_action('qm/info', 'WPLANG: ' . get_option('WPLANG'));
    do_action('qm/info', 'translated: ' . __('รอโอนเงิน', $_ENV['TEXTDOMAIN_NAME']));
    
}, 1);

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
