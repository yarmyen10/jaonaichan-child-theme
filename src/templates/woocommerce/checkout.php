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

<main
    x-data="jaoCheckout()"
    class="w-full mx-auto px-12 py-12 my-12 bg-[#ffffff]"
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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <!-- ===== Left Column (col-span-2) ===== -->
      <div class="md:col-span-2 space-y-6">

        <!-- Contact -->
        <div class="bg-white rounded-2xl shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Contact</h2>
          <?php if ( is_user_logged_in() ) : ?>
            <p class="text-sm text-gray-500">
              <?= sprintf(
                __( 'Welcome Back %s (%s)', $_ENV['TEXTDOMAIN_NAME'] ),
                esc_html( $user->display_name ),
                esc_html( $user->user_email )
              ) ?>
            </p>
          <?php endif; ?>
        </div>

        <!-- Additional Info -->
        <div class="bg-white rounded-2xl shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <?= __( 'ข้อมูลเพิ่มเติม', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h2>
          <textarea
            name="order_comments"
            rows="4"
            placeholder="<?= esc_attr( __( 'หมายเหตุต่างๆ เช่น รายละเอียดการจัดส่ง', $_ENV['TEXTDOMAIN_NAME'] ) ) ?>"
            class="w-full rounded-xl border border-gray-200 p-3 text-sm text-gray-600 resize-none focus:outline-none focus:ring-2 focus:ring-primary"
          ><?= esc_textarea( $checkout->get_value( 'order_comments' ) ?? '' ) ?></textarea>
        </div>

        <!-- Payment Methods -->
        <div class="bg-white rounded-2xl shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Payment</h2>

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
                    <span class="text-sm font-medium text-gray-800">
                      <?= esc_html( $gateway->get_title() ) ?>
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

        <!-- Validation / WC error messages -->
        <div
          x-show="errorHtml"
          x-html="errorHtml"
          class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-2xl px-4 py-3"
        ></div>

        <!-- Privacy notice + Submit -->
        <div class="bg-white rounded-2xl shadow-sm p-6">
          <p class="text-xs text-gray-400 mb-4">
            <?= sprintf(
              __( 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our %s.', $_ENV['TEXTDOMAIN_NAME'] ),
              '<a href="' . esc_url( wc_get_page_permalink( 'privacy' ) ) . '" class="text-primary underline">' . __( 'นโยบายความเป็นส่วนตัว', $_ENV['TEXTDOMAIN_NAME'] ) . '</a>'
            ) ?>
          </p>

          <button
            type="submit"
            :disabled="loading || <?= empty( $gateways ) ? 'true' : 'false' ?>"
            class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-[#5e9a28] text-white py-3 rounded-xl text-sm font-medium transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
            <?= __( 'Place Order', $_ENV['TEXTDOMAIN_NAME'] ) ?>
            ฿<?= number_format( $cart_total_raw, 2 ) ?>
          </button>
        </div>

      </div><!-- /left col -->

      <!-- ===== Right Column (sticky) ===== -->
      <div class="space-y-6 md:sticky md:top-8 self-start">

        <!-- Cart items -->
        <div class="bg-white rounded-2xl shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">สรุปคำสั่งซื้อ</h2>
          <div class="w-full text-sm divide-y divide-gray-100">
            <?php foreach ( $cart->get_cart() as $cart_item ) :
              $product = $cart_item['data'];
              $qty     = $cart_item['quantity'];
              $img_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' )
                      ?: wc_placeholder_img_src( 'woocommerce_thumbnail' );
            ?>
              <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                <div class="relative shrink-0">
                  <img
                    src="<?= esc_url( $img_url ) ?>"
                    alt="<?= esc_attr( $product->get_name() ) ?>"
                    class="w-14 h-14 object-cover rounded-lg border border-gray-100"
                  >
                  <span class="absolute -top-1.5 -left-1.5 w-5 h-5 flex items-center justify-center bg-primary text-white text-[10px] font-bold rounded-full">
                    <?= $qty ?>
                  </span>
                </div>
                <p class="flex-1 text-gray-800 line-clamp-2">
                  <?= esc_html( $product->get_name() ) ?>
                </p>
                <p class="font-medium text-gray-900 shrink-0">
                  ฿<?= number_format( (float) $cart_item['line_total'], 2 ) ?>
                </p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Coupon -->
        <div class="bg-white rounded-2xl shadow-sm p-6">
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

        <!-- Totals -->
        <div class="bg-white rounded-2xl shadow-sm p-6">
          <div class="w-full text-sm divide-y divide-gray-100">
            <div class="flex justify-between pb-2">
              <span class="text-gray-600"><?= __( 'Subtotal', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
              <span class="text-primary">฿<?= number_format( (float) $cart->get_subtotal(), 2 ) ?></span>
            </div>

            <?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
              <div class="flex justify-between py-2">
                <span class="text-gray-500"><?= __( 'Coupon', $_ENV['TEXTDOMAIN_NAME'] ) ?>: <?= esc_html( $code ) ?></span>
                <span class="text-green-600">- ฿<?= number_format( (float) $cart->get_coupon_discount_amount( $code ), 2 ) ?></span>
              </div>
            <?php endforeach; ?>

            <?php if ( wc_tax_enabled() && $cart->get_taxes_total() > 0 ) : ?>
              <div class="flex justify-between py-2">
                <span class="text-gray-500"><?= __( 'Tax', $_ENV['TEXTDOMAIN_NAME'] ) ?></span>
                <span class="text-gray-700">฿<?= number_format( (float) $cart->get_taxes_total(), 2 ) ?></span>
              </div>
            <?php endif; ?>

            <div class="flex justify-between pt-3">
              <span class="font-semibold text-gray-900">Total</span>
              <span class="font-semibold text-gray-900">฿<?= number_format( $cart_total_raw, 2 ) ?></span>
            </div>
          </div>
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
