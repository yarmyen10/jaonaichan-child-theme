<?php
/**
 * Plugin Name: BB Cookie Auth
 * Description: Maps bb_jwt cookie to HTTP_AUTHORIZATION before JWT plugin runs.
 * Version: 1.0.0
 */

// Runs at mu-plugin load time — before all regular plugins (including JWT plugin).
// Ensures $determine_current_user sees the Authorization header from the cookie.
if ( ! empty( $_COOKIE['bb_jwt'] ) && empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . sanitize_text_field( wp_unslash( $_COOKIE['bb_jwt'] ) );
}
