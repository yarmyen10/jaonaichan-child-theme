<?php
/**
 * Full-page navigation loading indicator (storefront only)
 *
 * Storefront pages use full page reloads with no visual feedback — this shows
 * the same "cat" loading overlay bigboss uses. Blocklist (not allowlist) so any
 * new storefront page (page-*.php) gets it automatically; dashboard/auth pages
 * already have their own Alpine loading state and are excluded by template path.
 */

function jn_page_loading_should_show(): bool {
    $template = is_page() ? get_page_template_slug() : '';
    return ! ( $template && (
        str_starts_with( $template, 'src/templates/dashboard/' ) ||
        str_starts_with( $template, 'src/templates/auth/' )
    ) );
}

add_action( 'wp_enqueue_scripts', function () {
    if ( ! jn_page_loading_should_show() ) return;

    wp_enqueue_script(
        'jn-page-loading',
        get_stylesheet_directory_uri() . '/assets/js/page-loading.js',
        [],
        filemtime( get_stylesheet_directory() . '/assets/js/page-loading.js' ),
        true
    );
}, 20 );

add_action( 'wp_footer', function () {
    if ( ! jn_page_loading_should_show() ) return;
    ?>
    <div id="jn-page-loading" style="display:none; position:fixed; inset:0; z-index:99999; align-items:center; justify-content:center; background:rgba(0,0,0,0.8);">
        <img
            src="<?= esc_url( get_stylesheet_directory_uri() . '/assets/imgs/meow-loading.gif' ) ?>"
            alt="<?= esc_attr__( 'กำลังโหลด...', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
            style="width:clamp(96px, 28vw, 220px); height:auto;"
        />
    </div>
    <?php
} );
