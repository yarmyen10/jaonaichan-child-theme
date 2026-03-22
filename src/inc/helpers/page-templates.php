<?php
/**
 * Register Page Templates จาก /src/templates/
 */
class Theme_Page_Templates {

    private string $templates_path;
    private array  $templates = [];

    public function __construct() {
        $this->templates_path = get_stylesheet_directory() . '/src/templates';

        add_filter( 'theme_page_templates',  [ $this, 'register' ] );
        add_filter( 'template_include',      [ $this, 'load' ] );
    }

    /**
     * สแกนหาไฟล์ PHP ใน /src/templates/ แล้ว Register อัตโนมัติ
     */
    public function register( array $templates ): array {
        if ( ! is_dir( $this->templates_path ) ) return $templates;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->templates_path, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $files as $file ) {
            if ( $file->getExtension() !== 'php' ) continue;

            // อ่าน Template Name จาก comment
            $headers = get_file_data( $file->getPathname(), [ 'Template Name' => 'Template Name' ] );

            if ( ! empty( $headers['Template Name'] ) ) {
                // เก็บ relative path
                $relative = str_replace( get_stylesheet_directory() . '/', '', $file->getPathname() );
                $this->templates[ $relative ] = $headers['Template Name'];
                $templates[ $relative ]       = $headers['Template Name'];
            }
        }

        return $templates;
    }

    /**
     * โหลด Template ที่ถูกเลือก
     */
    public function load( string $template ): string {
        if ( ! is_page() ) return $template;

        $selected = get_post_meta( get_the_ID(), '_wp_page_template', true );
        $path     = get_stylesheet_directory() . '/' . $selected;

        if ( $selected && file_exists( $path ) ) {
            return $path;
        }

        return $template;
    }
}

new Theme_Page_Templates();