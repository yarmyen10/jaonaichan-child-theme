<?php
defined( 'ABSPATH' ) || exit;

class Lots_API {

    private static string $db_version = '1.0';

    public static function init(): void {
        add_action( 'init',          [ self::class, 'maybe_create_table' ] );
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function maybe_create_table(): void {
        if ( get_option( 'jaonaichan_lots_db_version' ) === self::$db_version ) return;

        global $wpdb;
        $table           = $wpdb->prefix . 'jaonaichan_lots';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL DEFAULT 'open',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'jaonaichan_lots_db_version', self::$db_version );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/lots', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'get_lots' ],
                'permission_callback' => [ self::class, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'create_lot' ],
                'permission_callback' => [ self::class, 'check_permission' ],
            ],
        ] );

        register_rest_route( 'jaonaichan/v1', '/lots/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'update_lot' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'id' => [ 'required' => true, 'type' => 'integer' ],
            ],
        ] );
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }

    public static function get_lots() {
        global $wpdb;
        $table = $wpdb->prefix . 'jaonaichan_lots';
        $rows  = $wpdb->get_results( "SELECT id, status, created_at FROM {$table} ORDER BY id DESC" );

        return new WP_REST_Response( array_map( fn( $r ) => [
            'id'         => (int) $r->id,
            'status'     => $r->status,
            'created_at' => $r->created_at,
        ], $rows ?: [] ), 200 );
    }

    public static function create_lot() {
        global $wpdb;
        $table = $wpdb->prefix . 'jaonaichan_lots';

        $wpdb->insert( $table, [ 'status' => 'open' ] );
        $id = $wpdb->insert_id;

        if ( ! $id ) {
            return new WP_Error( 'db_error', 'Failed to create lot.', [ 'status' => 500 ] );
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, created_at FROM {$table} WHERE id = %d", $id ) );

        return new WP_REST_Response( [
            'id'         => (int) $row->id,
            'status'     => $row->status,
            'created_at' => $row->created_at,
        ], 201 );
    }

    public static function update_lot( WP_REST_Request $request ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'jaonaichan_lots';
        $id     = (int) $request['id'];
        $body   = $request->get_json_params();
        $status = sanitize_text_field( $body['status'] ?? '' );

        $valid = [ 'open', 'packed', 'shipped' ];
        if ( ! in_array( $status, $valid, true ) ) {
            return new WP_Error( 'invalid_status', 'status must be one of: ' . implode( ', ', $valid ), [ 'status' => 400 ] );
        }

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) );
        if ( ! $exists ) {
            return new WP_Error( 'not_found', 'Lot not found.', [ 'status' => 404 ] );
        }

        $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ] );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }
}

Lots_API::init();
