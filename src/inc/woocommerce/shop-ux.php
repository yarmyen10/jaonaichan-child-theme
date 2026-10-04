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
        $heading = Banners_API::text( 'heading', $b['heading'] ?? '' );
        $sub     = Banners_API::text( 'subheading', $b['subheading'] ?? '' );
        $link    = esc_url_raw( (string) ( $b['link'] ?? '' ) );
        $out[] = [
            'url'        => $url,
            'title'      => sanitize_text_field( (string) ( $b['title'] ?? '' ) ),
            // text over the picture is real text on the page, so the picture is decoration; otherwise the description is what the picture says
            'alt'        => ( $heading !== '' || $sub !== '' ) ? '' : ( sanitize_text_field( (string) ( $b['description'] ?? '' ) ) ?: sanitize_text_field( (string) ( $b['title'] ?? '' ) ) ),
            'link'       => $link,
            'heading'    => $heading,
            'subheading' => $sub,
            'cta'        => $link !== '' ? Banners_API::text( 'cta', $b['ctaLabel'] ?? '' ) : '',   // a button with nowhere to go is not shown
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
    $has_text = (bool) array_filter( $banners, fn( $b ) => $b['heading'] !== '' || $b['subheading'] !== '' || $b['cta'] !== '' );   // text over a picture needs more height than 1000:340 gives on a phone
    wp_enqueue_script( 'jn-shop-banner', get_stylesheet_directory_uri() . '/assets/js/shop-banner.js', [], filemtime( get_stylesheet_directory() . '/assets/js/shop-banner.js' ), true );
    ?>
    <section class="jn-banner<?= $has_text ? ' jn-banner--text' : '' ?>" data-jn-banner data-interval="<?= (int) Banners_API::interval() ?>" aria-roledescription="carousel" aria-label="<?= esc_attr__( 'โปรโมชั่น', 'jaonaichan' ) ?>">
        <div class="jn-banner__track">
            <?php foreach ( $banners as $i => $b ) :
                $img = sprintf(
                    '<img src="%s" alt="%s" %s>',
                    esc_url( $b['url'] ), esc_attr( $b['alt'] ),
                    $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'
                );
                $external = $b['link'] !== '' && wp_parse_url( $b['link'], PHP_URL_HOST ) && wp_parse_url( $b['link'], PHP_URL_HOST ) !== $home;
                $target   = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
                // the mock's .banner-content: heading / sub-heading / button over the picture. With a button, the button is the link (as in the mock); without one the whole slide is.
                $text = '';
                if ( $b['heading'] !== '' ) $text .= '<div class="jn-banner__title">' . nl2br( esc_html( $b['heading'] ) ) . '</div>';
                if ( $b['subheading'] !== '' ) $text .= '<div class="jn-banner__subtitle">' . esc_html( $b['subheading'] ) . '</div>';
                if ( $b['cta'] !== '' ) $text .= '<a class="jn-banner__cta" href="' . esc_url( $b['link'] ) . '"' . $target . '>' . esc_html( $b['cta'] ) . '</a>';
                $inner = $img . ( $text !== '' ? '<div class="jn-banner__content"><div class="jn-banner__copy">' . $text . '</div></div>' : '' ); ?>
                <div class="jn-banner__slide" role="group" aria-roledescription="slide" aria-label="<?= esc_attr( ( $i + 1 ) . ' / ' . $n ) ?>">
                    <?php if ( $b['link'] !== '' && $b['cta'] === '' ) : ?>
                        <a href="<?= esc_url( $b['link'] ) ?>"<?= $target ?>><?= $inner ?></a>
                    <?php else : echo $inner; endif; ?>
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
    // two-tone pastel icons in the designer's style (40×40, same pink / lilac / amber / green as the mock's category chips)
    $icons   = [
        'all'      => '<rect x="4" y="4" width="14" height="14" rx="3" fill="#FFE7EF" stroke="#F26593" stroke-width="1.6"/><rect x="22" y="4" width="14" height="14" rx="3" fill="#F3E8FF" stroke="#A379E0" stroke-width="1.6"/><rect x="4" y="22" width="14" height="14" rx="3" fill="#FFF3C9" stroke="#E9A94C" stroke-width="1.6"/><rect x="22" y="22" width="14" height="14" rx="3" fill="#D6F5E0" stroke="#5EBB7C" stroke-width="1.6"/>',
        'preorder' => '<rect x="6" y="9" width="28" height="25" rx="5" fill="#F3E8FF" stroke="#A379E0" stroke-width="1.4"/><path d="M6 14a5 5 0 0 1 5-5h18a5 5 0 0 1 5 5v3H6z" fill="#E4D0FF" stroke="#A379E0" stroke-width="1.4"/><path d="M13 6v6M27 6v6" stroke="#A379E0" stroke-width="1.6" stroke-linecap="round"/><circle cx="20" cy="26" r="6" fill="#fff" stroke="#F26593" stroke-width="1.4"/><path d="M20 22.8v3.4l2.4 1.5" fill="none" stroke="#F26593" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>',
        'ready'    => '<path d="M4 13h20v16H4z" fill="#FFF3C9" stroke="#E9A94C" stroke-width="1.4" stroke-linejoin="round"/><path d="M24 18h6.5l4.5 5.5V29H24z" fill="#FFE7EF" stroke="#F26593" stroke-width="1.4" stroke-linejoin="round"/><path d="M9 18h10" stroke="#E9A94C" stroke-width="1.4" stroke-linecap="round"/><circle cx="12" cy="30" r="3.4" fill="#fff" stroke="#E9A94C" stroke-width="1.4"/><circle cx="29" cy="30" r="3.4" fill="#fff" stroke="#F26593" stroke-width="1.4"/>',
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
                            <svg viewBox="0 0 40 40" aria-hidden="true"><?= $icons[ $key ] ?></svg>
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
