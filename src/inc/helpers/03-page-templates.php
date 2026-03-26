<?php
/**
 * Register Page Templates จาก /src/templates/
 */
class Theme_Page_Templates {

    private array $templates_paths;
    private array $templates = [];

    public function __construct() {
        // ✅ กำหนดได้หลาย Path
        $this->templates_paths = [
            get_stylesheet_directory() . '/src/templates',
            // get_stylesheet_directory() . '/src/pages',
            // get_stylesheet_directory() . '/src/views',
        ];

        add_filter( 'theme_page_templates', [ $this, 'register' ] );
        add_filter( 'template_include',     [ $this, 'load' ] );
    }

    public function register( array $templates ): array {
        foreach ( $this->templates_paths as $path ) {
            if ( ! is_dir( $path ) ) continue;

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS )
            );

            foreach ( $files as $file ) {
                if ( $file->getExtension() !== 'php' ) continue;

                $headers = get_file_data( $file->getPathname(), [ 'Template Name' => 'Template Name' ] );

                if ( ! empty( $headers['Template Name'] ) ) {
                    $relative = str_replace( get_stylesheet_directory() . '/', '', $file->getPathname() );
                    $this->templates[ $relative ] = $headers['Template Name'];
                    $templates[ $relative ]       = $headers['Template Name'];
                }
            }
        }

        return $templates;
    }

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