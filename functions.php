<?php
/**
 * JAO NAI CHAN Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package JAO NAI CHAN
 * @since 1.0.0
 */

// Auth / Login
// require_once get_stylesheet_directory() . '/src/inc/auth/*.php';


$inc_folders = [
    '/inc/auth/',
    // '/inc/woocommerce/',
    // '/inc/i18n/',
    // '/inc/enqueue/',
    // '/inc/helpers/',
];

foreach ( $inc_folders as $folder ) {
    foreach ( glob( get_stylesheet_directory() . $folder . '*.php' ) as $file ) {
        require_once $file;
    }
}

/**
 * Define Constants
 */
define( 'CHILD_THEME_JAO_NAI_CHAN_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {
    wp_enqueue_style('jao-nai-chan-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_JAO_NAI_CHAN_VERSION, 'all');
    wp_enqueue_style('tailwind', get_stylesheet_directory_uri() . '/assets/css/tailwind.css');
    wp_enqueue_script('alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js', [], '3.14.1', false);
}
add_action('wp_enqueue_scripts', 'child_enqueue_styles', 15);

function add_defer_to_alpine($tag, $handle) {
    if ($handle === 'alpinejs') {
        return str_replace('<script', '<script defer', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_defer_to_alpine', 10, 2);



// Step 1: Register the status
function register_custom_order_status() {
    register_post_status( 'wc-waiting-transfer', array(
        'label'                     => 'รอโอนเงิน',
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'รอโอนเงิน <span class="count">(%s)</span>',
            'รอโอนเงิน <span class="count">(%s)</span>'
        ),
    ));
}
add_action( 'init', 'register_custom_order_status' );

// Step 2: Add to the dropdown list
function add_custom_status_to_dropdown( $order_statuses ) {
    $order_statuses['wc-waiting-transfer'] = 'รอโอนเงิน';
    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'add_custom_status_to_dropdown' );