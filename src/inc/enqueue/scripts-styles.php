<?php
/**
 * Enqueue styles
 */
function child_enqueue_styles() {
    wp_enqueue_style('jao-nai-chan-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), filemtime( get_stylesheet_directory() . '/style.css' ), 'all');
    wp_enqueue_style(
        'tailwind',
        get_stylesheet_directory_uri() . '/assets/css/tailwind.css',
        ['astra-theme-css'],
        filemtime( get_stylesheet_directory() . '/assets/css/tailwind.css' )
    );
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

/**
 * โหลด Scripts & Styles
 */
// function theme_enqueue_assets() {
//     // CSS
//     wp_enqueue_style(
//         'theme-style',
//         get_template_directory_uri() . '/assets/css/main.css',
//         [],
//         '1.0.0'
//     );

//     // JS + ส่งค่าไป Alpine.js
//     wp_enqueue_script(
//         'theme-script',
//         get_template_directory_uri() . '/assets/js/main.js',
//         [],
//         '1.0.0',
//         true
//     );

//     wp_localize_script( 'theme-script', 'myData', [
//         'ajaxUrl' => admin_url( 'admin-ajax.php' ),
//         'nonce'   => wp_create_nonce( 'my-nonce' ),
//     ]);
// }
// add_action( 'wp_enqueue_scripts', 'theme_enqueue_assets' );

