<?php
/**
 * Template Name: Jaonaichan Checkout
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

if ( ! WC()->cart || WC()->cart->is_empty() ) {
    wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
    exit;
}

$checkout       = WC()->checkout();
$cart           = WC()->cart;
$gateways       = WC()->payment_gateways->get_available_payment_gateways();
$user           = wp_get_current_user();
$first_gw       = ! empty( $gateways ) ? array_key_first( $gateways ) : '';
$cart_total_raw = (float) $cart->get_total( 'edit' );

// Custom checkout ไม่มี address form — ดึงจาก WC user meta (บันทึกจาก order ก่อนหน้า)
// billing_country บังคับ TH ไว้เสมอเพื่อให้ WC zone matching ทำงานได้และ validate ผ่าน
$billing = [
    'first_name' => get_user_meta( $user->ID, 'billing_first_name', true ) ?: $user->first_name,
    'last_name'  => get_user_meta( $user->ID, 'billing_last_name',  true ) ?: $user->last_name,
    'phone'      => get_user_meta( $user->ID, 'billing_phone',      true ) ?: '',
    'address_1'  => get_user_meta( $user->ID, 'billing_address_1',  true ) ?: '',
    'city'       => get_user_meta( $user->ID, 'billing_city',       true ) ?: '',
    'postcode'   => get_user_meta( $user->ID, 'billing_postcode',   true ) ?: '',
    'country'    => 'TH',
];

// ทำให้ address fields ไม่ required เพราะ checkout นี้ไม่เก็บ address จากผู้ใช้โดยตรง
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
    $optional = [ 'billing_address_1', 'billing_city', 'billing_postcode', 'billing_phone', 'billing_state' ];
    foreach ( $optional as $key ) {
        if ( isset( $fields['billing'][ $key ] ) ) {
            $fields['billing'][ $key ]['required'] = false;
        }
    }
    return $fields;
} );

// Shipping behaviour (ดู src/inc/woocommerce/rts-order-split.php):
// - ตะกร้ามี RTS อย่างน้อย 1 ชิ้น → WC คำนวณ shipping ตามปกติ (flat_rate จาก zone "rts")
// - ตะกร้าไม่มี RTS เลย → woocommerce_cart_shipping_packages คืน [] → ไม่มี shipping line
// - Mixed cart → ที่ checkout WC เห็น shipping แต่ order split hook จะ remove ออกจาก main order
//   และย้าย shipping ไปใส่ RTS sub-order แทน
$has_rts = $has_normal = false;
foreach ( $cart->get_cart() as $ci ) {
    jn_product_is_rts( $ci['data']->get_id() ) ? ( $has_rts = true ) : ( $has_normal = true );
}
$is_mixed_cart = $has_rts && $has_normal;
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

/* Entrance fade-in — main card only, kept light on a task-focused checkout flow */
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
</style>

<div class="jn-checkout-wrap w-full min-h-[calc(100vh-80px)] pt-[150px] pb-8 md:pt-[220px] lg:pt-[280px] px-0 sm:px-6 lg:px-8 font-sans relative z-10 breakout-desktop">

  <!-- Full Width Background Container -->
  <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[100vw] -z-10 overflow-hidden bg-gradient-to-br from-pink-50 via-white to-purple-50">
    <!-- Decorative background blobs -->
    <div class="jn-co-blob-1 absolute top-0 left-0 w-96 h-96 bg-[#FB5FAB] opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
    <div class="jn-co-blob-2 absolute bottom-0 right-0 w-96 h-96 bg-purple-400 opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
  </div>

  <main
      x-data="jaoCheckout()"
      class="jn-rise-in relative z-10 w-full max-w-6xl mx-auto px-2 py-8 md:px-12 md:py-12 rounded-[2rem] bg-white/70 backdrop-blur-xl border border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)]"
  >
  <?php $color = '#FB5FAB'; include get_stylesheet_directory() . '/src/templates/spinner.php'; ?>

  <form
    id="jao-checkout-form"
    method="post"
    @submit.prevent="placeOrder"
    novalidate
  >
    <?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
    <input type="hidden" name="ship_to_different_address" value="0">
    <input type="hidden" name="payment_method" :value="selectedMethod">
    <input type="hidden" name="billing_email"      value="<?= esc_attr( $user->user_email ) ?>">
    <input type="hidden" name="billing_first_name" value="<?= esc_attr( $billing['first_name'] ) ?>">
    <input type="hidden" name="billing_last_name"  value="<?= esc_attr( $billing['last_name'] ) ?>">
    <input type="hidden" name="billing_phone"      value="<?= esc_attr( $billing['phone'] ) ?>">
    <input type="hidden" name="billing_address_1"  value="<?= esc_attr( $billing['address_1'] ) ?>">
    <input type="hidden" name="billing_city"       value="<?= esc_attr( $billing['city'] ) ?>">
    <input type="hidden" name="billing_postcode"   value="<?= esc_attr( $billing['postcode'] ) ?>">
    <input type="hidden" name="billing_country"    value="TH">

      <!-- Order Summary (full width) -->
      <div class="jn-checkout-card">
        <h3 class="jn-checkout-heading font-semibold text-gray-800" style="margin-bottom:1rem;">
          <?= __( 'สรุปคำสั่งซื้อ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
        </h3>

        <?php
          // คำนวณข้อมูลแต่ละ item ครั้งเดียว ใช้ทั้ง card list (มือถือ/iPad) และตาราง (desktop)
          $items_view = [];
          foreach ( $cart->get_cart() as $cart_item ) {
              $product   = $cart_item['data'];
              $image_id  = $product->get_image_id();
              $image_url = $image_id
                  ? wp_get_attachment_image_url( $image_id, 'custom-100' )
                  : wc_placeholder_img_src( 'custom-100' );
              // เหมือน get_formatted_meta_data() ของ order item แต่สำหรับ cart item — ดู thank-you.php
              $meta_flat  = wc_get_formatted_cart_item_data( $cart_item, true );
              $meta_lines = $meta_flat ? array_filter( array_map( 'trim', explode( "\n", $meta_flat ) ) ) : [];
              $qty        = $cart_item['quantity'];

              $items_view[] = [
                  'name'       => $product->get_name(),
                  'sku'        => $product->get_sku(),
                  'image'      => $image_url,
                  'qty'        => $qty,
                  'meta_lines' => $meta_lines,
                  'unit_price' => $qty > 0 ? $cart_item['line_total'] / $qty : 0,
                  'line_total' => $cart_item['line_total'],
              ];
          }
          $last_index = count( $items_view ) - 1;
        ?>

        <!-- Card list — iPad / iPhone -->
        <div class="lg:hidden" style="margin:0 -0.25rem; padding:0 0.25rem;">
          <?php foreach ( $items_view as $i => $it ) : ?>
            <div style="display:flex; align-items:center; gap:0.75rem; padding:0.625rem 0; border-bottom: <?= $i === $last_index ? 'none' : '1px solid #f3f4f6' ?>;">
              <img
                src="<?= esc_url( $it['image'] ) ?>"
                alt="<?= esc_attr( $it['name'] ) ?>"
                style="width:80px; height:80px; object-fit:cover; border-radius:8px; flex-shrink:0;"
              />
              <div style="flex:1; min-width:0;">
                <p style="font-size:0.875rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin:0;">
                  <?php if ( $it['sku'] ) : ?>
                    <span style="color:#9ca3af; font-weight:400;"><?= esc_html( $it['sku'] ) ?></span> ·
                  <?php endif; ?>
                  <?= esc_html( $it['name'] ) ?>
                </p>
                <?php if ( $it['meta_lines'] ) : ?>
                  <?php foreach ( $it['meta_lines'] as $meta_line ) : ?>
                    <p style="font-size:0.85rem; color:#9ca3af; margin:0;"><?= wp_strip_all_tags( $meta_line ) ?></p>
                  <?php endforeach; ?>
                <?php else : ?>
                  <p style="font-size:0.85rem; color:#9ca3af; margin:0;">&nbsp;</p>
                <?php endif; ?>
                <p style="font-size:0.85rem; color:#6b7280; margin:0;">
                  x<?= $it['qty'] ?> · <?= wc_price( $it['unit_price'] ) ?>/ชิ้น
                </p>
              </div>
              <span style="font-size:0.875rem; font-weight:600; white-space:nowrap;">
                <?= wc_price( $it['line_total'] ) ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Table — Desktop (grid style matching thank-you.php's bill2 item list) -->
        <div class="hidden lg:block">
          <div class="grid grid-cols-[1fr_3rem_6rem_6rem] gap-2 pb-1.5 border-b border-gray-100 text-[10px] text-gray-400 uppercase tracking-wide">
            <span><?= __( 'สินค้า', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
            <span class="text-center"><?= __( 'จำนวน', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
            <span class="text-right"><?= __( 'ราคา/ชิ้น', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
            <span class="text-right"><?= __( 'รวม', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
          </div>
          <?php foreach ( $items_view as $it ) : ?>
            <div class="grid grid-cols-[1fr_3rem_6rem_6rem] items-center gap-x-3 py-2 border-b border-gray-50 last:border-0">
              <div class="flex items-center gap-2">
                <img
                  src="<?= esc_url( $it['image'] ) ?>"
                  alt="<?= esc_attr( $it['name'] ) ?>"
                  class="w-20 h-20 shrink-0 object-cover rounded-lg border border-gray-200"
                />
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-900 !mb-0 leading-snug">
                    <?php if ( $it['sku'] ) : ?>
                      <span class="text-gray-400 font-normal"><?= esc_html( $it['sku'] ) ?></span> ·
                    <?php endif; ?>
                    <?= esc_html( $it['name'] ) ?>
                  </p>
                  <?php foreach ( $it['meta_lines'] as $meta_line ) : ?>
                    <p class="text-xs text-gray-400 !mb-0"><?= wp_strip_all_tags( $meta_line ) ?></p>
                  <?php endforeach; ?>
                </div>
              </div>
              <p class="text-xs text-gray-500 text-center !mb-0">x<?= $it['qty'] ?></p>
              <p class="text-xs text-gray-500 text-right !mb-0"><?= wc_price( $it['unit_price'] ) ?></p>
              <p class="text-sm font-semibold text-gray-900 text-right !mb-0"><?= wc_price( $it['line_total'] ) ?></p>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="border-top:1px solid #f3f4f6; margin-top:0.5rem; padding-top:0.75rem; display:flex; flex-direction:column; gap:0.375rem;">

          <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:#6b7280;">
            <span><?= __( 'ยอดรวมสินค้า', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
            <span><?= wc_price( $cart->get_subtotal() ) ?></span>
          </div>

          <?php if ( $cart->get_shipping_total() > 0 ) : ?>
          <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:#6b7280;">
            <span><?= __( 'ค่าจัดส่ง', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
            <span><?= wc_price( $cart->get_shipping_total() ) ?></span>
          </div>
          <?php endif; ?>

          <?php if ( $cart->get_discount_total() > 0 ) : ?>
          <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:#e53e3e;">
            <span><?= __( 'ส่วนลด', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
            <span>-<?= wc_price( $cart->get_discount_total() ) ?></span>
          </div>
          <?php endif; ?>

          <div class="flex justify-between items-center px-3 py-2 mt-1 rounded-lg bg-pink-50 border border-pink-100">
            <span class="text-base font-semibold text-gray-700"><?= __( 'ยอดรวมทั้งหมด', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
            <span class="text-base font-bold text-[#FB5FAB]"><?= wc_price( $cart_total_raw ) ?></span>
          </div>

        </div>
      </div>

      <?php
      // Lookup RTS shipping once — ใช้ทั้ง mixed และ all-RTS notice
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
      ?>

      <?php if ( $has_rts && ! $has_normal ) : ?>
      <div class="jn-checkout-card" style="background:#f0fdf4; border-left:3px solid #34d399; padding:0.875rem 1rem;">
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
      <?php endif; ?>

      <?php if ( $is_mixed_cart ) : ?>
      <div class="jn-checkout-card" style="background:#f0fdf4; border-left:3px solid #34d399; padding:0.875rem 1rem;">
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
      <?php endif; ?>

    <!-- Validation / WC error messages -->
    <div
      x-show="errorHtml"
      x-html="errorHtml"
      class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-2xl px-4 py-3"
      style="margin-bottom:1rem;"
    ></div>

    <!-- Submit + Privacy -->
    <div class="jn-checkout-card">
      <p class="text-xs text-gray-400 mb-4">
        <?= sprintf(
          __( 'เมื่อกดยืนยันออเดอร์ จะไม่สามารถแก้ไขรายการสินค้า/จำนวนสินค้าได้ รบกวนตรวจสอบรายการสั่งซื้อก่อนกดยืนยัน', $_ENV['TEXTDOMAIN_NAME'] ),
          '<a href="' . esc_url( wc_get_page_permalink( 'privacy' ) ) . '" class="text-primary underline">' . __( 'นโยบายความเป็นส่วนตัว', $_ENV['TEXTDOMAIN_NAME'] ) . '</a>'
        ) ?>
      </p>

      <button
        type="submit"
        :disabled="loading || <?= empty( $gateways ) ? 'true' : 'false' ?>"
        class="jn-confirm-btn w-full flex items-center justify-center gap-2 bg-primary hover:bg-[#5e9a28] text-white rounded-xl font-medium transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
      >
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2"/>
          <path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
        <?= __( 'ยืนยันออเดอร์', $_ENV['TEXTDOMAIN_NAME'] ) ?>
        <?= wc_price( $cart_total_raw ) ?>
      </button>
    </div>
  </form>

  </main>
</div>

<script>
function jaoCheckout() {
    return {
        loading: false,
        selectedMethod: '<?= esc_js( $first_gw ) ?>',
        errorHtml: '',

        async init() {
          try {
            this.loading = true;
            console.log('🚧 jaoCheckout init');
          } catch (error) {
            
          } finally {
            this.loading = false;
          }
          
        },

        async placeOrder() {
            this.loading   = true;
            this.errorHtml = '';

            const form = document.getElementById('jao-checkout-form');
            const data = new URLSearchParams(new FormData(form));
            data.set('payment_method', this.selectedMethod);
            data.set('status', 'wc-pending-payment-1'); // ตั้งสถานะเริ่มต้นเป็น "รอชำระบิลที่ 1"

            try {
                const res = await fetch('/?wc-ajax=checkout', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body:    data.toString(),
                });

                const result = await res.json();
                if (result.result === 'success') {
                    const m = result.redirect.match(/order-received\/(\d+)/i);
                    window.location.href = m
                        ? '<?= esc_js( home_url( '/thank-you-slave/' ) ) ?>?wcf-order=' + m[1]
                        : result.redirect;
                } else {
                    this.errorHtml = result.messages
                        ?? '<?= esc_js( __( 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง', $_ENV['TEXTDOMAIN_NAME'] ) ) ?>';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            } catch (_) {
                this.errorHtml = '<?= esc_js( __( 'เกิดข้อผิดพลาดในการเชื่อมต่อ', $_ENV['TEXTDOMAIN_NAME'] ) ) ?>';
            } finally {
                this.loading = false;
            }
        },

    };
}
</script>

<?php get_footer(); ?>
