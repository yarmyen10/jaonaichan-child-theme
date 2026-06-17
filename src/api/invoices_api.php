<?php
class Invoices_API {

    private static string $db_version = '1.0';

    public static function init(): void {
        add_action( 'init',         [ self::class, 'maybe_create_table' ] );
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function maybe_create_table(): void {
        if ( get_option( 'jaonaichan_invoices_db_version' ) === self::$db_version ) return;

        global $wpdb;
        $table           = $wpdb->prefix . 'jaonaichan_invoices';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            invoice_date varchar(100) NOT NULL,
            customer_ids longtext NOT NULL,
            items longtext NOT NULL,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            notes text,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'jaonaichan_invoices_db_version', self::$db_version );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/invoices', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'get_invoices' ],
                'permission_callback' => [ self::class, 'check_permission' ],
                'args'                => [
                    'page'     => [ 'default' => 1,  'type' => 'integer', 'minimum' => 1 ],
                    'per_page' => [ 'default' => 20, 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ],
                    'search'   => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'create_invoice' ],
                'permission_callback' => [ self::class, 'check_permission' ],
                'args'                => [
                    'invoice_number' => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'invoice_date'   => [ 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'customer_ids'   => [ 'required' => false, 'type' => 'array',  'default' => [] ],
                    'items'          => [ 'required' => true,  'type' => 'array' ],
                    'total'          => [ 'required' => true,  'type' => 'number' ],
                    'notes'          => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ],
                    'status'         => [ 'required' => false, 'type' => 'string', 'default' => 'draft', 'enum' => [ 'draft', 'sent', 'paid' ] ],
                ],
            ],
        ] );

        register_rest_route( 'jaonaichan/v1', '/invoices/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'get_invoice' ],
                'permission_callback' => [ self::class, 'check_permission' ],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [ self::class, 'patch_invoice' ],
                'permission_callback' => [ self::class, 'check_permission' ],
                'args'                => [
                    'status' => [ 'type' => 'string', 'enum' => [ 'draft', 'sent', 'paid' ] ],
                    'notes'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
                ],
            ],
        ] );
    }

    public static function get_invoices( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $table    = $wpdb->prefix . 'jaonaichan_invoices';
        $page     = intval( $request->get_param( 'page' ) );
        $per_page = intval( $request->get_param( 'per_page' ) );
        $search   = sanitize_text_field( $request->get_param( 'search' ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where = "1=1";
        $args = [];

        if ( ! empty( $search ) ) {
            $where .= " AND invoice_number LIKE %s";
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        if ( empty( $args ) ) {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
            $rows  = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
        } else {
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $args ) );
            $args[] = $per_page;
            $args[] = $offset;
            $rows  = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    ...$args
                ),
                ARRAY_A
            );
        }

        return new WP_REST_Response( [
            'data'        => array_map( [ self::class, 'format_invoice' ], $rows ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ], 200 );
    }

    public static function get_invoice( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;
        $table = $wpdb->prefix . 'jaonaichan_invoices';
        $id    = intval( $request->get_param( 'id' ) );
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return new WP_Error( 'not_found', 'Invoice not found', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( [
            'success' => true,
            'data'    => self::format_invoice( $row ),
        ], 200 );
    }

    public static function create_invoice( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;
        $table          = $wpdb->prefix . 'jaonaichan_invoices';
        $invoice_number = $request->get_param( 'invoice_number' );

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE invoice_number = %s", $invoice_number )
        );
        if ( $exists ) {
            return new WP_Error( 'duplicate_invoice', 'Invoice number already exists', [ 'status' => 409 ] );
        }

        $result = $wpdb->insert(
            $table,
            [
                'invoice_number' => $invoice_number,
                'invoice_date'   => $request->get_param( 'invoice_date' ),
                'customer_ids'   => wp_json_encode( $request->get_param( 'customer_ids' ) ?? [] ),
                'items'          => wp_json_encode( $request->get_param( 'items' ) ),
                'total'          => floatval( $request->get_param( 'total' ) ),
                'notes'          => $request->get_param( 'notes' ) ?? '',
                'status'         => $request->get_param( 'status' ) ?? 'draft',
            ],
            [ '%s', '%s', '%s', '%s', '%f', '%s', '%s' ]
        );

        if ( $result === false ) {
            return new WP_Error( 'db_error', 'Failed to save invoice', [ 'status' => 500 ] );
        }

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ),
            ARRAY_A
        );

        return new WP_REST_Response( [
            'success' => true,
            'data'    => self::format_invoice( $row ),
        ], 201 );
    }

    public static function patch_invoice( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;
        $table = $wpdb->prefix . 'jaonaichan_invoices';
        $id    = intval( $request->get_param( 'id' ) );

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id )
        );
        if ( ! $exists ) {
            return new WP_Error( 'not_found', 'Invoice not found', [ 'status' => 404 ] );
        }

        $data   = [];
        $format = [];

        if ( $request->has_param( 'status' ) ) {
            $data['status'] = sanitize_text_field( $request->get_param( 'status' ) );
            $format[]       = '%s';
        }
        if ( $request->has_param( 'notes' ) ) {
            $data['notes'] = sanitize_textarea_field( $request->get_param( 'notes' ) );
            $format[]      = '%s';
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_fields', 'No fields to update', [ 'status' => 400 ] );
        }

        $wpdb->update( $table, $data, [ 'id' => $id ], $format, [ '%d' ] );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        return new WP_REST_Response( [
            'success' => true,
            'data'    => self::format_invoice( $row ),
        ], 200 );
    }

    private static function format_invoice( array $row ): array {
        return [
            'id'             => (int) $row['id'],
            'invoice_number' => $row['invoice_number'],
            'invoice_date'   => $row['invoice_date'],
            'customer_ids'   => json_decode( $row['customer_ids'], true ) ?? [],
            'items'          => json_decode( $row['items'], true ) ?? [],
            'total'          => (float) $row['total'],
            'notes'          => $row['notes'],
            'status'         => $row['status'],
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at'],
        ];
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }
}

Invoices_API::init();
