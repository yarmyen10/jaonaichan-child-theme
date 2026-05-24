<?php
/**
 * Dashboard REST API
 * GET /wp-json/jaonaichan/v1/dashboard?year=YYYY
 */
class Dashboard_API {

    private static array $ACTIVE_STATUSES = [
        'pending', 'processing', 'on-hold', 'completed',
        'waiting-transfer', 'pending-payment-1', 'pending-payment-2',
        'wait-verify-1', 'wait-verify-2', 'paid-1', 'paid-2',
    ];

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'jaonaichan/v1', '/dashboard', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_dashboard' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'year' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => (int) date( 'Y' ),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public static function check_permission(): bool {
        return is_user_logged_in();
    }

    public static function get_dashboard( WP_REST_Request $request ): WP_REST_Response {
        $year = (int) $request->get_param( 'year' );

        return new WP_REST_Response([
            'metrics'          => self::build_metrics(),
            'monthly_revenue'  => self::build_monthly_revenue( $year ),
            'recent_orders'    => self::build_recent_orders(),
            'status_breakdown' => self::build_status_breakdown(),
        ]);
    }

    // =========================================================================
    // Metrics
    // =========================================================================

    private static function build_metrics(): array {
        $total_orders = count( wc_get_orders([
            'status' => 'any',
            'type'   => 'shop_order',
            'limit'  => -1,
            'return' => 'ids',
        ]));

        $cq = new WP_User_Query([
            'role__in'    => [ 'customer', 'subscriber' ],
            'fields'      => 'ID',
            'number'      => 1,
            'count_total' => true,
        ]);
        $total_customers = (int) $cq->get_total();

        $tz  = new DateTimeZone( wp_timezone_string() );
        $now = new DateTime( 'now', $tz );

        $this_month_data  = self::get_period_data(
            $now->format( 'Y-m-01 00:00:00' ),
            $now->format( 'Y-m-t 23:59:59' )
        );

        $last_m = clone $now;
        $last_m->modify( 'first day of last month' );
        $last_month_data = self::get_period_data(
            $last_m->format( 'Y-m-01 00:00:00' ),
            $last_m->format( 'Y-m-t 23:59:59' )
        );

        $today_data = self::get_period_data(
            $now->format( 'Y-m-d 00:00:00' ),
            $now->format( 'Y-m-d 23:59:59' )
        );

        return [
            'total_orders'       => $total_orders,
            'total_customers'    => $total_customers,
            'this_month_revenue' => $this_month_data['revenue'],
            'last_month_revenue' => $last_month_data['revenue'],
            'today_revenue'      => $today_data['revenue'],
            'this_month_orders'  => $this_month_data['orders'],
            'last_month_orders'  => $last_month_data['orders'],
        ];
    }

    private static function get_period_data( string $after, string $before ): array {
        $orders = wc_get_orders([
            'status'     => self::$ACTIVE_STATUSES,
            'type'       => 'shop_order',
            'date_query' => [[ 'after' => $after, 'before' => $before, 'inclusive' => true ]],
            'limit'      => -1,
        ]);

        $revenue = 0.0;
        foreach ( $orders as $o ) {
            $revenue += (float) $o->get_total();
        }

        return [ 'orders' => count( $orders ), 'revenue' => round( $revenue, 2 ) ];
    }

    // =========================================================================
    // Monthly revenue — 12 rows for the given year
    // =========================================================================

    private static function build_monthly_revenue( int $year ): array {
        $labels = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];

        $orders = wc_get_orders([
            'status'     => self::$ACTIVE_STATUSES,
            'type'       => 'shop_order',
            'date_query' => [[
                'after'     => "{$year}-01-01 00:00:00",
                'before'    => "{$year}-12-31 23:59:59",
                'inclusive' => true,
            ]],
            'limit'      => -1,
        ]);

        $monthly = array_fill( 1, 12, [ 'revenue' => 0.0, 'orders' => 0 ] );
        foreach ( $orders as $o ) {
            $m = (int) $o->get_date_created()->format( 'n' );
            $monthly[ $m ]['revenue'] += (float) $o->get_total();
            $monthly[ $m ]['orders']++;
        }

        $result = [];
        for ( $m = 1; $m <= 12; $m++ ) {
            $result[] = [
                'month'   => $m,
                'label'   => $labels[ $m - 1 ],
                'revenue' => round( $monthly[ $m ]['revenue'], 2 ),
                'orders'  => $monthly[ $m ]['orders'],
            ];
        }
        return $result;
    }

    // =========================================================================
    // Recent orders — last 10
    // =========================================================================

    private static function build_recent_orders(): array {
        $orders = wc_get_orders([
            'status'  => 'any',
            'type'    => 'shop_order',
            'limit'   => 10,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        return array_map( fn( $o ) => Orders_API::format_order( $o ), $orders );
    }

    // =========================================================================
    // Status breakdown — count per status (non-zero only)
    // =========================================================================

    private static function build_status_breakdown(): array {
        global $wpdb;

        // Single query — works for HPOS (wc_orders) which is enabled on this site
        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt
             FROM {$wpdb->prefix}wc_orders
             WHERE type = 'shop_order'
             GROUP BY status
             ORDER BY cnt DESC"
        );

        $breakdown = [];
        foreach ( $rows as $row ) {
            $status = str_replace( 'wc-', '', $row->status );
            $breakdown[ $status ] = (int) $row->cnt;
        }

        return $breakdown;
    }
}

Dashboard_API::init();
