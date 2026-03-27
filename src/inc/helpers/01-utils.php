<?php
/**
 * Utils
 */
class Utils {
    public static function init(): void {
        add_image_size( 'custom-100', 100, 100, true );
        
        // do_action('qm/info', 'Utils::init() called');
        // do_action('qm/info', 'custom-100 registered: ' . 
        //     ( in_array('custom-100', get_intermediate_image_sizes()) ? 'YES' : 'NO' )
        // );
    }
}

Utils::init();