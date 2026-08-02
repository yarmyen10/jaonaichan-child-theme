<?php
/**
 * Template Name: Jaonaichan Cart
 */

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/shop-login' ) );
    exit;
}

// Force Astra full-width / no-sidebar layout for this template
add_filter( 'astra_get_option', function ( $val, $option ) {
    if ( in_array( $option, [ 'site-sidebar-layout', 'single-page-sidebar-layout' ], true ) ) {
        return 'no-sidebar';
    }
    return $val;
}, 10, 2 );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'jn-kawaii-fonts',
		'https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap',
		[],
		null
	);
} );

get_header();

if ( ! WC()->cart ) {
    wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
    exit;
}

$cart = WC()->cart;

// Shipping behaviour (ดู src/inc/woocommerce/rts-order-split.php):
// ตะกร้าไม่มี RTS เลย → ไม่มี shipping line — เหมือน checkout.php
$has_rts = $has_normal = false;
foreach ( $cart->get_cart() as $ci ) {
    jn_product_is_rts( $ci['data']->get_id() ) ? ( $has_rts = true ) : ( $has_normal = true );
}
$is_mixed_cart = $has_rts && $has_normal;

// Lookup RTS shipping once — ใช้ทั้ง mixed และ all-RTS notice (เหมือน checkout.php)
$rts_ship_cost  = 0.0;
$rts_ship_label = '';
if ( $has_rts ) {
    foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
        if ( strtolower( $zone['zone_name'] ) === 'rts' ) {
            foreach ( $zone['shipping_methods'] as $method ) {
                if ( $method->is_enabled() ) {
                    $rts_ship_cost  = (float) $method->cost;
                    $rts_ship_label = $method->get_title();
                    break;
                }
            }
            break;
        }
    }
}

// Seed data for the Alpine cart component — same shape the ajax endpoint (cart-ajax.php) returns
$items_payload = [];
foreach ( $cart->get_cart() as $key => $cart_item ) {
    $product   = $cart_item['data'];
    $image_id  = $product->get_image_id();
    $image_url = $image_id
        ? wp_get_attachment_image_url( $image_id, 'custom-100' )
        : wc_placeholder_img_src( 'custom-100' );

    // เหมือน get_formatted_meta_data() ของ order item แต่สำหรับ cart item — ดู thank-you.php
    $meta_flat  = wc_get_formatted_cart_item_data( $cart_item, true );
    $meta_lines = $meta_flat ? array_values( array_filter( array_map( 'trim', explode( "\n", $meta_flat ) ) ) ) : [];
    $qty        = $cart_item['quantity'];

    $items_payload[] = [
        'key'        => $key,
        'sku'        => $product->get_sku(),
        'name'       => $product->get_name(),
        'image'      => $image_url,
        'quantity'   => $qty,
        'line_total' => wc_price( $cart_item['line_total'] ),
        'unit_price' => wc_price( $qty > 0 ? $cart_item['line_total'] / $qty : 0 ),
        'meta_lines' => array_map( 'wp_strip_all_tags', $meta_lines ),
    ];
}

$cart_seed = [
    'items'       => $items_payload,
    'totals'      => [
        'subtotal' => wc_price( $cart->get_subtotal() ),
        'shipping' => $cart->get_shipping_total() > 0 ? wc_price( $cart->get_shipping_total() ) : null,
        'discount' => $cart->get_discount_total() > 0 ? wc_price( $cart->get_discount_total() ) : null,
        'total'    => wc_price( (float) $cart->get_total( 'edit' ) ),
    ],
    'hasRts'      => $has_rts,
    'hasNormal'   => $has_normal,
    'isMixedCart' => $is_mixed_cart,
];
?>

<style>
/* ── Typography / buttons ── */
.jn-checkout-heading { font-size: 1rem; }
.jn-confirm-btn { font-size: 0.95rem; padding: 0.75rem; }
@media (min-width: 922px) {
  .jn-checkout-heading { font-size: 1.125rem; }
  .jn-confirm-btn { font-size: 1rem; padding: 0.875rem; }
}

.jn-checkout-wrap { font-family: 'Prompt', sans-serif; }

/* Divider style — no box, just a hairline between sections (was a bordered/shadowed card) */
.jn-checkout-card {
  padding-bottom: 1.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid #f3f4f6;
}

/* ── Mobile card tweaks ── */
@media (max-width: 639px) {
  .jn-checkout-card { padding-bottom: 1rem; margin-bottom: 1rem; }
}

/* Qty stepper */
.jn-qty-btn {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #fce7f3;
  border-radius: 8px;
  background: #fff;
  color: #FB5FAB;
  font-size: 1rem;
  line-height: 1;
  cursor: pointer;
  transition: background-color .15s, border-color .15s;
}
.jn-qty-btn:hover:not(:disabled) { background: #fdf2f8; border-color: #FB5FAB; }
.jn-qty-btn:disabled { opacity: .4; cursor: not-allowed; }

/* Blob float animations */
@keyframes jn-co-blob-drift-1 {
  0%, 100% { transform: translate(-50%, -50%) scale(1); }
  50%      { transform: translate(calc(-50% + 16px), calc(-50% - 12px)) scale(1.08); }
}
@keyframes jn-co-blob-drift-2 {
  0%, 100% { transform: translate(50%, 50%) scale(1); }
  50%      { transform: translate(calc(50% - 14px), calc(50% + 10px)) scale(1.1); }
}
.jn-co-blob-1 { animation: jn-co-blob-drift-1 7s ease-in-out infinite; will-change: transform; }
.jn-co-blob-2 { animation: jn-co-blob-drift-2 9s ease-in-out infinite; animation-delay: 2s; will-change: transform; }

/* Entrance fade-in — main card only */
@keyframes jn-rise-in {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}
.jn-rise-in { animation: jn-rise-in .5s ease-out both; }

@media (prefers-reduced-motion: reduce) {
  .jn-co-blob-1,
  .jn-co-blob-2,
  .jn-rise-in {
    animation: none;
  }
}

/* Force no-sidebar: hide sidebar + make content area fill 100% */
#secondary,
aside.widget-area { display: none !important; }

#primary,
#primary.content-area {
  float: none !important;
  width: 100% !important;
  max-width: 100% !important;
  padding-right: 0 !important;
  padding-left: 0 !important;
}

.site-content .ast-container,
.site-content > .ast-container {
  display: block !important;
}

/* ModernCart floating button doesn't listen for wc_fragment_refresh — stays stale
   after our ajax quantity updates. This page already shows live totals, hide it. */
#moderncart-floating-cart { display: none !important; }
</style>

<div class="jn-checkout-wrap w-full min-h-[calc(100vh-80px)] pt-[150px] pb-8 md:pt-[220px] lg:pt-[280px] px-0 sm:px-6 lg:px-8 font-sans relative z-10 breakout-desktop">

  <!-- Full Width Background Container -->
  <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[100vw] -z-10 overflow-hidden bg-gradient-to-br from-pink-50 via-white to-purple-50">
    <!-- Decorative background blobs -->
    <div class="jn-co-blob-1 absolute top-0 left-0 w-96 h-96 bg-[#FB5FAB] opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
    <div class="jn-co-blob-2 absolute bottom-0 right-0 w-96 h-96 bg-purple-400 opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
  </div>

  <main
      x-data="jaoCart(<?= esc_attr( wp_json_encode( $cart_seed ) ) ?>)"
      class="jn-rise-in relative z-10 w-full max-w-6xl mx-auto px-2 py-8 md:px-12 md:py-12 rounded-[2rem] bg-white/70 backdrop-blur-xl border border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)]"
  >

  <!-- Items -->
  <div class="jn-checkout-card" x-show="items.length > 0">
    <h3 class="jn-checkout-heading font-semibold text-gray-800" style="margin-bottom:1rem;">
      <?= __( 'ตะกร้าสินค้า', $_ENV['TEXTDOMAIN_NAME'] ) ?>
    </h3>

    <!-- Card list — iPad / iPhone -->
    <div class="lg:hidden">
      <template x-for="item in items" :key="item.key">
        <div style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 0; border-bottom:1px solid #f3f4f6;">
          <img :src="item.image" :alt="item.name" style="width:56px; height:56px; object-fit:cover; border-radius:8px; flex-shrink:0;">

          <div style="flex:1; min-width:0;">
            <p style="font-size:0.875rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin:0;" x-text="item.name"></p>

            <template x-for="line in item.meta_lines" :key="line">
              <p style="font-size:0.75rem; color:#9ca3af; margin:0;" x-text="line"></p>
            </template>

            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.375rem;">
              <button type="button" class="jn-qty-btn" :disabled="updatingKey !== null" @click="changeQty(item.key, -1)">−</button>
              <span style="min-width:1.5rem; text-align:center; font-size:0.875rem;" x-text="item.quantity"></span>
              <button type="button" class="jn-qty-btn" :disabled="updatingKey !== null" @click="changeQty(item.key, 1)">+</button>

              <button
                type="button"
                title="<?= esc_attr__( 'ลบสินค้า', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
                :disabled="updatingKey !== null"
                @click="removeItem(item.key)"
                style="margin-left:0.5rem; color:#d1d5db; background:none; border:none; cursor:pointer; padding:4px; transition:color .15s;" onmouseover="this.style.color='#FB5FAB'" onmouseout="this.style.color='#d1d5db'"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/>
                </svg>
              </button>
            </div>
          </div>

          <span style="font-size:0.875rem; font-weight:600; white-space:nowrap;" x-html="item.line_total"></span>
        </div>
      </template>
    </div>

    <!-- Table — Desktop (grid style matching thank-you.php's bill2 item list) -->
    <div class="hidden lg:block">
      <div class="grid grid-cols-[1fr_8rem_6rem_6rem] gap-2 pb-1.5 border-b border-gray-100 text-[10px] text-gray-400 uppercase tracking-wide">
        <span><?= __( 'สินค้า', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
        <span class="text-center"><?= __( 'จำนวน', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
        <span class="text-right"><?= __( 'ราคา/ชิ้น', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
        <span class="text-right"><?= __( 'รวม', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
      </div>

      <template x-for="item in items" :key="item.key">
        <div class="grid grid-cols-[1fr_8rem_6rem_6rem] items-center gap-x-3 py-2 border-b border-gray-50 last:border-0">
          <div class="flex items-center gap-2">
            <img :src="item.image" :alt="item.name" class="w-20 h-20 shrink-0 object-cover rounded-lg border border-gray-200">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 !mb-0 leading-snug">
                <template x-if="item.sku"><span class="text-gray-400 font-normal" x-text="item.sku + ' · '"></span></template><span x-text="item.name"></span>
              </p>
              <template x-for="line in item.meta_lines" :key="line">
                <p class="text-xs text-gray-400 !mb-0" x-text="line"></p>
              </template>
            </div>
          </div>

          <div class="flex items-center justify-center gap-1.5">
            <button type="button" class="jn-qty-btn" :disabled="updatingKey !== null" @click="changeQty(item.key, -1)">−</button>
            <span style="min-width:1.25rem; text-align:center; font-size:0.8125rem;" x-text="item.quantity"></span>
            <button type="button" class="jn-qty-btn" :disabled="updatingKey !== null" @click="changeQty(item.key, 1)">+</button>
          </div>

          <p class="text-xs text-gray-500 text-right !mb-0" x-html="item.unit_price"></p>
          <p class="text-sm font-semibold text-gray-900 text-right !mb-0" x-html="item.line_total"></p>
        </div>
      </template>
    </div>

    <!-- totals -->
    <div style="border-top:1px solid #f3f4f6; margin-top:0.5rem; padding-top:0.75rem; display:flex; flex-direction:column; gap:0.375rem;">

      <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:#6b7280;">
        <span><?= __( 'ยอดรวมสินค้า', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
        <span x-html="totals.subtotal"></span>
      </div>

      <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:#6b7280;" x-show="totals.shipping">
        <span><?= __( 'ค่าจัดส่ง', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
        <span x-html="totals.shipping"></span>
      </div>

      <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:#e53e3e;" x-show="totals.discount">
        <span><?= __( 'ส่วนลด', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
        <span x-html="totals.discount"></span>
      </div>

      <div class="flex justify-between items-center px-3 py-2 mt-1 rounded-lg bg-pink-50 border border-pink-100">
        <span class="text-base font-semibold text-gray-700"><?= __( 'ยอดรวมทั้งหมด', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
        <span class="text-base font-bold text-[#FB5FAB]" x-html="totals.total"></span>
      </div>

    </div>
  </div>

  <?php /*
  <!-- RTS-only notice -->
  <div class="jn-checkout-card" style="background:#f0fdf4; border-left:3px solid #34d399; padding:0.875rem 1rem;" x-show="hasRts && !hasNormal">
    <div style="display:flex; gap:0.625rem; align-items:flex-start;">
      <span style="font-size:1.1rem; line-height:1.4; flex-shrink:0;">⚡</span>
      <div style="font-size:0.8125rem; color:#065f46; line-height:1.55;">
        <p style="font-weight:600; margin:0 0 0.25rem;">สินค้าพร้อมส่ง (RTS)</p>
        <p style="margin:0; color:#047857;">จ่ายแค่บิลเดียว — ไม่ต้องรอของเข้า</p>
        <?php if ( $rts_ship_cost > 0 ) : ?>
        <p style="margin:0.375rem 0 0; color:#065f46;">
          ค่าจัดส่ง<?= $rts_ship_label ? ' (' . esc_html( $rts_ship_label ) . ')' : '' ?>:
          <strong><?= wc_price( $rts_ship_cost ) ?></strong>
        </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  */ ?>

  <?php /*
  <!-- Mixed cart notice -->
  <div class="jn-checkout-card" style="background:#f0fdf4; border-left:3px solid #34d399; padding:0.875rem 1rem;" x-show="isMixedCart">
    <div style="display:flex; gap:0.625rem; align-items:flex-start;">
      <span style="font-size:1.1rem; line-height:1.4; flex-shrink:0;">⚡</span>
      <div style="font-size:0.8125rem; color:#065f46; line-height:1.55;">
        <p style="font-weight:600; margin:0 0 0.25rem;">ตะกร้ามีสินค้า 2 ประเภท</p>
        <p style="margin:0; color:#047857;">
          สินค้า <strong>พร้อมส่ง (RTS)</strong> จะถูกแยกเป็นอีก order หนึ่งโดยอัตโนมัติ
          และจ่ายแค่บิลเดียว — สินค้าที่เหลือใช้ระบบ 2 บิลตามปกติ
        </p>
        <?php if ( $rts_ship_cost > 0 ) : ?>
        <p style="margin:0.375rem 0 0; color:#065f46;">
          ค่าจัดส่ง RTS<?= $rts_ship_label ? ' (' . esc_html( $rts_ship_label ) . ')' : '' ?>:
          <strong><?= wc_price( $rts_ship_cost ) ?></strong>
        </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  */ ?>

  <!-- Empty state -->
  <div class="jn-checkout-card" style="text-align:center; padding:3rem 1.5rem;" x-show="items.length === 0">
    <p style="font-size:0.9375rem; color:#6b7280; margin:0 0 1rem;">
      <?= __( 'ตะกร้าของคุณว่างเปล่า', $_ENV['TEXTDOMAIN_NAME'] ) ?>
    </p>
    <a
      href="<?= esc_url( wc_get_page_permalink( 'shop' ) ) ?>"
      class="jn-confirm-btn inline-flex items-center justify-center bg-primary hover:bg-[#5e9a28] text-white rounded-xl font-medium transition-colors"
      style="padding-left:1.5rem; padding-right:1.5rem;"
    >
      <?= __( 'เลือกซื้อสินค้าต่อ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
    </a>
  </div>

  <!-- Proceed to checkout -->
  <div class="jn-checkout-card" x-show="items.length > 0">
    <a
      href="<?= esc_url( home_url( '/checkout-slave/' ) ) ?>"
      class="jn-confirm-btn w-full flex items-center justify-center gap-2 bg-primary hover:bg-[#5e9a28] text-white rounded-xl font-medium transition-colors"
    >
      <?= __( 'ไปหน้าชำระเงิน', $_ENV['TEXTDOMAIN_NAME'] ) ?>
    </a>
    <a
      href="<?= esc_url( wc_get_page_permalink( 'shop' ) ) ?>"
      class="jn-confirm-btn w-full flex items-center justify-center gap-2 border border-gray-200 text-gray-600 hover:bg-gray-50 rounded-xl font-medium transition-colors"
      style="margin-top:0.625rem;"
    >
      <?= __( 'กลับไปร้านค้า', $_ENV['TEXTDOMAIN_NAME'] ) ?>
    </a>
  </div>

  </main>
</div>

<script>
function jaoCart(seed) {
    return {
        items:       seed.items,
        totals:      seed.totals,
        hasRts:      seed.hasRts,
        hasNormal:   seed.hasNormal,
        isMixedCart: seed.isMixedCart,
        updatingKey: null,

        async setQty(key, newQty) {
            if (newQty < 0 || this.updatingKey !== null) return;
            this.updatingKey = key;

            const data = new URLSearchParams({
                cart_item_key: key,
                quantity:      newQty,
                security:      '<?= wp_create_nonce( 'jn-cart-update' ) ?>',
            });

            try {
                const res = await fetch('/?wc-ajax=jn_update_cart_item', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body:    data.toString(),
                });
                const json = await res.json();
                if (!json.success) return;

                const d = json.data;
                if (d.removed) {
                    this.items = this.items.filter(i => i.key !== key);
                } else {
                    const item = this.items.find(i => i.key === key);
                    if (item) {
                        item.quantity   = d.item.quantity;
                        item.line_total = d.item.line_total;
                        item.unit_price = d.item.unit_price;
                    }
                }
                this.totals      = d.totals;
                this.hasRts      = d.has_rts;
                this.hasNormal   = d.has_normal;
                this.isMixedCart = d.is_mixed_cart;

                // Header mini-cart / floating cart widgets (Astra, plugins) listen for this
                // to re-fetch their own WC fragments — they're outside this template entirely.
                if (window.jQuery) {
                    window.jQuery(document.body).trigger('wc_fragment_refresh');
                }
            } catch (_) {
                // network error — leave state as-is, user can retry
            } finally {
                this.updatingKey = null;
            }
        },

        changeQty(key, delta) {
            const item = this.items.find(i => i.key === key);
            if (!item) return;
            this.setQty(key, item.quantity + delta);
        },

        removeItem(key) {
            this.setQty(key, 0);
        },
    };
}
</script>

<?php get_footer(); ?>
