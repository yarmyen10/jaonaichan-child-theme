<?php
/**
 * Register Page Templates จาก /src/templates/
 */
class Utils {
    public static function init(): void {
        // ลงทะเบียน Custom Size
        add_action( 'after_setup_theme', function() {
            add_image_size( 'custom-100', 100, 100, true ); // true = crop
        });
    }
}

// เริ่มต้นใช้งาน
Utils::init();