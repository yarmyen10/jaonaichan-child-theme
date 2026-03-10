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
