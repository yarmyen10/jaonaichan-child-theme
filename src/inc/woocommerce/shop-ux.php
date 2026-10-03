<?php
/**
 * Shop page UX (Trello "Shop > UX/UI"): on top of the product loop show
 *   1. a banner slider   — banners come from the option `jn_shop_banners` (the "Banner Management" admin writes it; model below)
 *   2. a big search box  — replaces the sidebar search widget (hidden by CSS on these pages)
 *   3. group tabs        — ทั้งหมด · สินค้าพรีออเดอร์ (every round) · สินค้าพร้อมส่ง, filtered with ?jn_group=
 *
 * Banner model, one array per banner (same as the designer's Banner Management package):
 *   [ 'id' => '..', 'title' => '..', 'description' => '..', 'isActive' => true,
 *     'image' => [ 'url' => 'https://…', 'name' => '..' ], 'link' => 'https://…' (optional), 'updatedAt' => 'ISO date' ]
 * Only active banners that have an image are shown; with none the whole banner block is left out (no empty gap).
 */

/** The listing pages that get the panel: shop, product category/tag archives and product search. */
function jn_shop_is_listing(): bool {
    if ( ! function_exists( 'is_shop' ) ) return false;
    return is_shop() || is_product_taxonomy() || ( is_search() && get_query_var( 'post_type' ) === 'product' );
}

function jn_shop_groups(): array {
    return [
        'all'      => __( 'ทั้งหมด', 'jaonaichan' ),
        'preorder' => __( 'สินค้าพรีออเดอร์', 'jaonaichan' ),
        'ready'    => __( 'สินค้าพร้อมส่ง', 'jaonaichan' ),
    ];
}

/**
 * Category ids of a group. Pre-order rounds = categories whose NAME says พรีออเดอร์ / pre-order (a new "…รอบ 22" joins by itself);
 * ready = the "Ready to ship" category. Names/slugs, not ids, because the ids differ between Local and production.
 */
function jn_shop_group_term_ids( string $group ): array {
    static $cache = [];
    if ( isset( $cache[ $group ] ) ) return $cache[ $group ];
    $terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
    if ( is_wp_error( $terms ) ) return $cache[ $group ] = [];
    $ids = [];
    foreach ( $terms as $t ) {
        $is_pre   = (bool) preg_match( '/พรีออเดอร์|pre-?order/iu', $t->name );
        $is_ready = $t->slug === 'ready-to-ship' || stripos( $t->name, 'ready to ship' ) !== false;
        if ( ( $group === 'preorder' && $is_pre ) || ( $group === 'ready' && $is_ready ) ) $ids[] = (int) $t->term_id;
    }
    return $cache[ $group ] = $ids;
}

/** ?jn_group=preorder|ready — anything else means "all". */
function jn_shop_requested_group(): string {
    $g = isset( $_GET['jn_group'] ) ? sanitize_key( wp_unslash( $_GET['jn_group'] ) ) : '';
    return in_array( $g, [ 'preorder', 'ready' ], true ) ? $g : '';
}

/** Which tab is highlighted: the requested one; on a category archive the group that category belongs to (none if it belongs to neither). */
function jn_shop_current_group(): string {
    $g = jn_shop_requested_group();
    if ( $g ) return $g;
    if ( is_product_category() ) {
        $term = get_queried_object();
        if ( $term instanceof WP_Term ) {
            foreach ( [ 'preorder', 'ready' ] as $k ) {
                if ( in_array( (int) $term->term_id, jn_shop_group_term_ids( $k ), true ) ) return $k;
            }
        }
        return '';
    }
    return 'all';
}

add_action( 'woocommerce_product_query', function ( WP_Query $q ) {
    $g = jn_shop_requested_group();
    if ( ! $g ) return;
    $ids = jn_shop_group_term_ids( $g );
    if ( ! $ids ) { $q->set( 'post__in', [ 0 ] ); return; }   // the group has no category yet → no products, not "everything"
    $tax   = (array) $q->get( 'tax_query' );
    $tax[] = [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $ids, 'operator' => 'IN' ];
    $q->set( 'tax_query', $tax );
} );

/** Active banners that have an image, in the stored order. */
function jn_shop_banners(): array {
    $raw = get_option( 'jn_shop_banners', [] );
    if ( is_string( $raw ) ) $raw = json_decode( $raw, true );
    if ( ! is_array( $raw ) ) return [];
    $out = [];
    foreach ( $raw as $b ) {
        if ( ! is_array( $b ) || empty( $b['isActive'] ) ) continue;
        $url = esc_url_raw( (string) ( $b['image']['url'] ?? '' ) );
        if ( $url === '' ) continue;
        $out[] = [
            'url'   => $url,
            'title' => sanitize_text_field( (string) ( $b['title'] ?? '' ) ),
            'link'  => esc_url_raw( (string) ( $b['link'] ?? '' ) ),
        ];
    }
    return $out;
}

add_filter( 'body_class', function ( array $classes ): array {
    if ( jn_shop_is_listing() ) $classes[] = 'jn-shop-ux';
    return $classes;
} );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! jn_shop_is_listing() ) return;
    $dir = get_stylesheet_directory();
    wp_enqueue_style( 'jn-shop-ux', get_stylesheet_directory_uri() . '/assets/css/shop-ux.css', [ 'astra-theme-css' ], filemtime( $dir . '/assets/css/shop-ux.css' ) );
}, 20 );

function jn_shop_render_banner( array $banners ): void {
    $n    = count( $banners );
    $home = wp_parse_url( home_url(), PHP_URL_HOST );
    wp_enqueue_script( 'jn-shop-banner', get_stylesheet_directory_uri() . '/assets/js/shop-banner.js', [], filemtime( get_stylesheet_directory() . '/assets/js/shop-banner.js' ), true );
    ?>
    <section class="jn-banner" data-jn-banner aria-roledescription="carousel" aria-label="<?= esc_attr__( 'โปรโมชั่น', 'jaonaichan' ) ?>">
        <div class="jn-banner__track">
            <?php foreach ( $banners as $i => $b ) :
                $img = sprintf(
                    '<img src="%s" alt="%s" %s>',
                    esc_url( $b['url'] ), esc_attr( $b['title'] ),
                    $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'
                );
                $external = $b['link'] !== '' && wp_parse_url( $b['link'], PHP_URL_HOST ) && wp_parse_url( $b['link'], PHP_URL_HOST ) !== $home; ?>
                <div class="jn-banner__slide" role="group" aria-roledescription="slide" aria-label="<?= esc_attr( ( $i + 1 ) . ' / ' . $n ) ?>">
                    <?php if ( $b['link'] !== '' ) : ?>
                        <a href="<?= esc_url( $b['link'] ) ?>"<?= $external ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= $img ?></a>
                    <?php else : echo $img; endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ( $n > 1 ) : ?>
            <button type="button" class="jn-banner__nav jn-banner__nav--prev" aria-label="<?= esc_attr__( 'ภาพก่อนหน้า', 'jaonaichan' ) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <button type="button" class="jn-banner__nav jn-banner__nav--next" aria-label="<?= esc_attr__( 'ภาพถัดไป', 'jaonaichan' ) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <div class="jn-banner__dots">
                <?php for ( $i = 0; $i < $n; $i++ ) : ?>
                    <button type="button" class="jn-banner__dot" aria-label="<?= esc_attr( sprintf( __( 'ไปภาพที่ %d', 'jaonaichan' ), $i + 1 ) ) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"></button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function jn_shop_render_panel(): void {
    $current = jn_shop_current_group();
    $query   = is_search() ? get_search_query( false ) : '';
    $icons   = [
        'all'      => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
        'preorder' => '<path d="M7 3v3M17 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM12 12v3l2 1.5"/>',
        'ready'    => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7M7.5 19.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>',
    ];
    ?>
    <div class="jn-shop-panel">
        <form class="jn-shop-search" role="search" method="get" action="<?= esc_url( home_url( '/' ) ) ?>">
            <label class="screen-reader-text" for="jn-shop-q"><?= esc_html__( 'ค้นหาสินค้า', 'jaonaichan' ) ?></label>
            <svg class="jn-shop-search__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-4.3-4.3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input id="jn-shop-q" type="search" name="s" value="<?= esc_attr( $query ) ?>" placeholder="<?= esc_attr__( 'ค้นหาสินค้าที่ต้องการ…', 'jaonaichan' ) ?>" autocomplete="off">
            <input type="hidden" name="post_type" value="product">
            <?php if ( jn_shop_requested_group() ) : ?><input type="hidden" name="jn_group" value="<?= esc_attr( jn_shop_requested_group() ) ?>"><?php endif; ?>
            <button type="submit" aria-label="<?= esc_attr__( 'ค้นหา', 'jaonaichan' ) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-4.3-4.3" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></button>
        </form>
        <nav class="jn-shop-tabs" aria-label="<?= esc_attr__( 'หมวดสินค้า', 'jaonaichan' ) ?>">
            <ul>
                <?php foreach ( jn_shop_groups() as $key => $label ) :
                    // tabs always go to the shop root (a category archive ∩ another group would be empty); a running search is kept
                    $args = $query !== '' ? [ 's' => $query, 'post_type' => 'product' ] : [];
                    if ( $key !== 'all' ) $args['jn_group'] = $key;
                    $url = add_query_arg( rawurlencode_deep( $args ), $query !== '' ? home_url( '/' ) : wc_get_page_permalink( 'shop' ) );
                    // the browser has no Thai dictionary entry for พรีออเดอร์ and cuts it mid-word on a phone: allow one break after สินค้า and keep the rest whole
                    $label_html = str_starts_with( $label, 'สินค้า' )
                        ? 'สินค้า<wbr><span class="jn-nowrap">' . esc_html( mb_substr( $label, mb_strlen( 'สินค้า' ) ) ) . '</span>'
                        : esc_html( $label ); ?>
                    <li>
                        <a class="jn-shop-tab" href="<?= esc_url( $url ) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>>
                            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icons[ $key ] ?></svg>
                            <span><?= $label_html ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
    <?php
}

/** Runs once per request — on the loop, or (no results) instead of it, so an empty search still shows the search box. */
function jn_shop_render_top(): void {
    static $done = false;
    if ( $done || ! jn_shop_is_listing() ) return;
    $done = true;
    echo '<div class="jn-shop-top">';
    $banners = ( is_shop() && ! is_search() && ! is_paged() ) ? jn_shop_banners() : [];
    if ( $banners ) jn_shop_render_banner( $banners );
    jn_shop_render_panel();
    echo '</div>';
}
add_action( 'woocommerce_before_shop_loop', 'jn_shop_render_top', 5 );
add_action( 'woocommerce_no_products_found', 'jn_shop_render_top', 5 );
