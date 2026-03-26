<?php
/**
 * Utils
 */
class Utils {
    public static function init(): void {
        // เรียกตรงๆ ได้เลย ไม่ต้องใช้ Hook
        add_image_size( 'custom-100', 100, 100, true );
    }
}

Utils::init();