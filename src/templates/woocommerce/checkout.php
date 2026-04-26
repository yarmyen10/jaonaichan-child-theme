<?php
/**
 * Template Name: Jaonaichan Checkout
 */

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
.jn-checkout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}
@media (min-width: 922px) {
  .jn-checkout-grid {
    grid-template-columns: 2fr 1fr;
  }
}

@media (max-width: 921px) {
  .jn-checkout-heading { font-size: 1rem; }
  .jn-confirm-btn { font-size: 0.95rem; padding: 0.75rem; }
}
@media (min-width: 922px) {
  .jn-checkout-heading { font-size: 1.125rem; }
  .jn-confirm-btn { font-size: 1rem; padding: 0.875rem; }
}
</style>

<main
    x-data="jaoCheckout()"
    class="w-full mx-auto px-12 py-12 my-12 rounded-xl bg-[#ffffff]"
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
      <div>

        <!-- Order Summary -->
        <div class="jn-checkout-card" style="background:#fff; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,0.08); padding:1.25rem; margin-bottom:1rem;">
          <h3 class="jn-checkout-heading font-semibold text-gray-800" style="margin-bottom:1rem;">
            <?= __( 'สรุปคำสั่งซื้อ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h3>

          <!-- scrollable items container -->
          <div style="max-height:320px; overflow-y:auto; margin:0 -0.25rem; padding:0 0.25rem;">
            <?php foreach ( $cart->get_cart() as $cart_item ) :
              $product   = $cart_item['data'];
              $qty       = $cart_item['quantity'];
              $image_id  = $product->get_image_id();
              $image_url = $image_id
                ? wp_get_attachment_image_url( $image_id, 'custom-100' )
                : wc_placeholder_img_src( 'custom-100' );
            ?>
              <div style="display:flex; align-items:center; gap:0.75rem; padding:0.625rem 0; border-bottom:1px solid #f3f4f6;">
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
        <div class="jn-checkout-card" style="background:#fff; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,0.08); padding:1.25rem; margin-bottom:1rem;">
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

      <!-- ===== Right Column (sticky) ===== -->
      <div class="sticky top-6 self-start">

        <!-- Payment -->
        <div class="jn-checkout-card" style="background:#fff; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,0.08); padding:1.25rem; margin-bottom:1rem;">
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
        <div class="jn-checkout-card" style="background:#fff; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,0.08); padding:1.25rem; margin-bottom:1rem;">
          <div class="flex gap-2">
            <input
              type="text"
              x-model="couponCode"
              @keydown.enter.prevent="applyCoupon"
              placeholder="<?= esc_attr( __( 'Coupon Code', $_ENV['TEXTDOMAIN_NAME'] ) ) ?>"
              class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary"
            >
            <button
              type="button"
              @click="applyCoupon"
              :disabled="!couponCode || loading"
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
        <div class="jn-checkout-card" style="background:#fff; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,0.08); padding:1.25rem; margin-bottom:1rem;">
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

<script>
function jaoCheckout() {
    return {
        loading: false,
        selectedMethod: '<?= esc_js( $first_gw ) ?>',
        couponCode: '',
        couponError: '',
        errorHtml: '',

        async placeOrder() {
            this.loading   = true;
            this.errorHtml = '';

            const form = document.getElementById('jao-checkout-form');
            const data = new URLSearchParams(new FormData(form));
            data.set('payment_method', this.selectedMethod);

            try {
                const res    = await fetch('/?wc-ajax=checkout', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body:    data.toString(),
                });
                const result = await res.json();

                if (result.result === 'success') {
                    window.location.href = result.redirect;
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
                const res  = await fetch('/?wc-ajax=apply_coupon', {
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
