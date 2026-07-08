<?php
/**
 * Menu Filters
 */

// ซ่อนเมนู Shop Login เมื่อ user ล็อกอินแล้ว
add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
    if ( is_user_logged_in() ) {
        foreach ( $items as $key => $item ) {
            // ซ่อนเมนูที่มี URL ชี้ไปที่ /shop-login/
            if ( strpos( $item->url, '/shop-login/' ) !== false ) {
                unset( $items[ $key ] );
            }
        }
    }
    return $items;
}, 10, 2 );

// My Account → แสดงเฉพาะ hamburger menu (ซ่อนใน desktop nav bar)
add_filter( 'nav_menu_css_class', function( $classes, $item ) {
    if ( strpos( $item->url, '/dashboard/' ) !== false || strpos( $item->url, '/my-account/' ) !== false ) {
        $classes[] = 'jn-hide-desktop';
    }
    return $classes;
}, 10, 2 );
