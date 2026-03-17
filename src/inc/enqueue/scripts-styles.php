<?php
/**
 * โหลด Scripts & Styles
 */
function theme_enqueue_assets() {
    // CSS
    wp_enqueue_style(
        'theme-style',
        get_template_directory_uri() . '/assets/css/main.css',
        [],
        '1.0.0'
    );

    // JS + ส่งค่าไป Alpine.js
    wp_enqueue_script(
        'theme-script',
        get_template_directory_uri() . '/assets/js/main.js',
        [],
        '1.0.0',
        true
    );

    wp_localize_script( 'theme-script', 'myData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'my-nonce' ),
    ]);
}
add_action( 'wp_enqueue_scripts', 'theme_enqueue_assets' );