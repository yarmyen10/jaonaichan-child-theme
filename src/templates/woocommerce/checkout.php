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
?>

<style>
/* ── Layout ── */
.jn-checkout-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
@media (min-width: 922px) {
  .jn-checkout-grid { flex-direction: row; gap: 1.5rem; }
  .jn-left-col  { flex: 2 1 0; min-width: 0; }
  .jn-right-col { flex: 1 1 0; min-width: 0; }
}

/* ── Right col sticky (same breakpoint as 2-col) ── */
@media (min-width: 922px) {
  .jn-right-col { position: sticky; top: 1.5rem; align-self: flex-start; }
}

/* ── Typography / buttons ── */
.jn-checkout-heading { font-size: 1rem; }
.jn-confirm-btn { font-size: 0.95rem; padding: 0.75rem; }
@media (min-width: 922px) {
  .jn-checkout-heading { font-size: 1.125rem; }
  .jn-confirm-btn { font-size: 1rem; padding: 0.875rem; }
}

/* ── Mobile card tweaks ── */
@media (max-width: 639px) {
  .jn-checkout-card { padding: 1rem !important; }
  .jn-items-scroll  { max-height: 200px !important; }
}

.jn-checkout-wrap { font-family: 'Prompt', sans-serif; }

.jn-checkout-card {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08), 0 8px 20px -8px rgba(107,63,160,.10);
  padding: 1.25rem;
  margin-bottom: 1rem;
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

<div class="jn-checkout-wrap w-full min-h-[calc(100vh-80px)] pt-[240px] pb-8 md:pt-[280px] px-4 sm:px-6 lg:px-8 font-sans relative z-10 breakout-desktop">

  <!-- Full Width Background Container -->
  <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[100vw] -z-10 overflow-hidden bg-gradient-to-br from-pink-50 via-white to-purple-50">
    <!-- Decorative background blobs -->
    <div class="jn-co-blob-1 absolute top-0 left-0 w-96 h-96 bg-[#FB5FAB] opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
    <div class="jn-co-blob-2 absolute bottom-0 right-0 w-96 h-96 bg-purple-400 opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
  </div>

  <main
      x-data="jaoCheckout()"
      class="jn-rise-in relative z-10 w-full max-w-6xl mx-auto px-4 py-8 md:px-12 md:py-12 rounded-[2rem] bg-white/70 backdrop-blur-xl border border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)]"
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
    <?php if ( is_user_logged_in() ) : ?>
      <input type="hidden" name="billing_email" value="<?= esc_attr( $user->user_email ) ?>">
    <?php endif; ?>

    <div class="jn-checkout-grid">

      <!-- ===== Left Column ===== -->
      <div class="jn-left-col">

        <!-- Order Summary -->
        <div class="jn-checkout-card">
          <h3 class="jn-checkout-heading font-semibold text-gray-800" style="margin-bottom:1rem;">
            <?= __( 'สรุปคำสั่งซื้อ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h3>

          <!-- scrollable items container -->
          <div class="jn-items-scroll" style="max-height:260px; overflow-y:auto; margin:0 -0.25rem; padding:0 0.25rem;">
            <?php
              $cart_items = $cart->get_cart();
              $last_item  = end( $cart_items );
              foreach ( $cart_items as $cart_item ) :
              $product   = $cart_item['data'];
              $qty       = $cart_item['quantity'];
              $image_id  = $product->get_image_id();
              $image_url = $image_id
                ? wp_get_attachment_image_url( $image_id, 'custom-100' )
                : wc_placeholder_img_src( 'custom-100' );
            ?>
              <div style="display:flex; align-items:center; gap:0.75rem; padding:0.625rem 0; border-bottom: <?= $cart_item === $last_item ? 'none' : '1px solid #f3f4f6' ?>;">
                <img
                  src="<?= esc_url( $image_url ) ?>"
                  alt="<?= esc_attr( $product->get_name() ) ?>"
                  style="width:56px; height:56px; object-fit:cover; border-radius:8px; flex-shrink:0;"
                />
                <div style="flex:1; min-width:0;">
                  <p style="font-size:0.875rem; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin:0;">
                    <?= esc_html( $product->get_name() ) ?>
                  </p>
                  <p style="font-size:0.75rem; color:#6b7280; margin:0;">x<?= $qty ?></p>
                </div>
                <span style="font-size:0.875rem; font-weight:600; white-space:nowrap;">
                  <?= wc_price( $cart_item['line_total'] ) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- subtotal + totals outside scroll -->
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

            <div style="display:flex; justify-content:space-between; font-size:1rem; font-weight:700; padding-top:0.5rem; border-top:1px solid #e5e7eb;">
              <span><?= __( 'ยอดรวมทั้งหมด', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
              <span><?= wc_price( $cart_total_raw ) ?></span>
            </div>

          </div>
        </div>

        <!-- Additional Info -->
        <div class="jn-checkout-card">
          <h3 class="jn-checkout-heading font-semibold text-gray-800" style="margin-bottom:1rem;">
            <?= __( 'ข้อมูลเพิ่มเติม', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h3>
          <textarea
            name="order_comments"
            placeholder="<?= esc_attr( __( 'หมายเหตุต่างๆ เช่น รายละเอียดการจัดส่ง', $_ENV['TEXTDOMAIN_NAME'] ) ) ?>"
            class="w-full rounded-xl border border-gray-200 p-3 text-sm text-gray-600 resize-none focus:outline-none focus:ring-2 focus:ring-primary min-h-[100px] md:min-h-[120px]"
          ><?= esc_textarea( $checkout->get_value( 'order_comments' ) ?? '' ) ?></textarea>
        </div>

      </div><!-- /left col -->

      <!-- ===== Right Column ===== -->
      <div class="jn-right-col">

        <!-- Payment -->
        <div class="jn-checkout-card">
          <h3 class="jn-checkout-heading font-semibold text-gray-800" style="margin-bottom:1rem;">
            <?= __( 'Payment', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h3>

          <?php if ( ! empty( $gateways ) ) : ?>
            <div class="flex flex-col gap-3">
              <?php foreach ( $gateways as $gw_id => $gateway ) :
                $gw_id_js = esc_js( $gw_id );
              ?>
                <label
                  class="flex flex-col gap-2 border rounded-xl px-4 py-3 cursor-pointer transition-colors"
                  :class="selectedMethod === '<?= $gw_id_js ?>' ? 'border-primary bg-green-50' : 'border-gray-200 hover:border-gray-300'"
                >
                  <div class="flex items-center gap-3">
                    <input
                      type="radio"
                      name="payment_method_radio"
                      value="<?= esc_attr( $gw_id ) ?>"
                      x-model="selectedMethod"
                      class="hidden"
                    >
                    <?php if ( $icon = $gateway->get_icon() ) : ?>
                      <span class="shrink-0 flex items-center"><?= $icon ?></span>
                    <?php endif; ?>
                    <span class="text-sm font-medium text-gray-800 [&_img]:inline-block [&_img]:!h-5 [&_img]:!w-auto [&_img]:!mr-2 [&_img]:!align-middle">
                      <?= wp_kses_post( $gateway->get_title() ) ?>
                    </span>
                  </div>

                  <div
                    x-show="selectedMethod === '<?= $gw_id_js ?>'"
                    x-transition.opacity
                    class="text-xs text-gray-500 border-t border-gray-100 pt-2"
                  >
                    <?= wp_kses_post( $gateway->get_description() ) ?>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          <?php else : ?>
            <p class="text-sm text-amber-700 bg-amber-50 rounded-lg px-4 py-3">
              <?= __( 'ไม่มีช่องทางชำระเงินที่พร้อมใช้งาน', $_ENV['TEXTDOMAIN_NAME'] ) ?>
            </p>
          <?php endif; ?>
        </div>

        <!-- Coupon -->
        <div class="jn-checkout-card" style="overflow:hidden;">
          <div style="display:flex; gap:0.5rem; min-width:0;">
            <input
              type="text"
              x-model="couponCode"
              @keydown.enter.prevent="applyCoupon"
              placeholder="<?= esc_attr( __( 'Coupon Code', $_ENV['TEXTDOMAIN_NAME'] ) ) ?>"
              style="flex:1 1 0%; min-width:0;"
              class="rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary"
            >
            <button
              type="button"
              @click="applyCoupon"
              :disabled="!couponCode || loading"
              style="flex-shrink:0; white-space:nowrap;"
              class="px-4 py-2 bg-primary hover:bg-[#5e9a28] text-white text-sm font-medium rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <?= __( 'Apply', $_ENV['TEXTDOMAIN_NAME'] ) ?>
            </button>
          </div>
          <p x-show="couponError" x-text="couponError" class="text-xs text-red-500 mt-1.5"></p>
        </div>

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
              __( 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.', $_ENV['TEXTDOMAIN_NAME'] ),
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
            <?= __( 'Place Order', $_ENV['TEXTDOMAIN_NAME'] ) ?>
            <?= wc_price( $cart_total_raw ) ?>
          </button>
        </div>

      </div><!-- /right col -->

    </div>
  </form>

  </main>
</div>

<script>
function jaoCheckout() {
    return {
        loading: false,
        selectedMethod: '<?= esc_js( $first_gw ) ?>',
        couponCode: '',
        couponError: '',
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

        async applyCoupon() {
            if (!this.couponCode) return;
            this.loading     = true;
            this.couponError = '';

            const data = new URLSearchParams({
                coupon_code: this.couponCode,
                security:    '<?= wp_create_nonce( 'apply-coupon' ) ?>',
            });

            try {
                const res = await fetch('/?wc-ajax=apply_coupon', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body:    data.toString(),
                });
                const html = await res.text();

                if (html.includes('woocommerce-error')) {
                    const tmp     = document.createElement('div');
                    tmp.innerHTML = html;
                    this.couponError = tmp.textContent.trim();
                } else {
                    window.location.reload();
                }
            } catch (_) {
                this.couponError = '<?= esc_js( __( 'ไม่สามารถใช้คูปองได้', $_ENV['TEXTDOMAIN_NAME'] ) ) ?>';
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>

<?php get_footer(); ?>
