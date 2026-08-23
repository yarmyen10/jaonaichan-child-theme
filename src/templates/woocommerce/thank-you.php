<?php
/**
 * Template Name: Thank You
 */

// $show  = 'loading';
// $color = 'bg-indigo-600 dark:bg-indigo-300';
// include get_stylesheet_directory() . '/src/templates/spinner.php';

$order_id = isset( $_GET['wcf-order'] ) ? intval( $_GET['wcf-order'] ) : 0;
$order    = $order_id ? wc_get_order( $order_id ) : null;

// Ownership check — wcf-order is a raw numeric ID with no secret key, so without this
// ANY logged-in visitor could view ANY other customer's order (shipping address, phone,
// payment status/amount, product list) just by changing the number in the URL.
// Must run before get_header() — redirecting after HTML output has started risks
// a "headers already sent" failure.
if ( $order && ! current_user_can( 'manage_options' ) && ( ! is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id() ) ) {
    wp_safe_redirect( home_url( '/dashboard' ) );
    exit;
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'jn-kawaii-fonts',
		'https://fonts.googleapis.com/css2?family=Mali:wght@600;700&family=Prompt:wght@400;500;600;700&display=swap',
		[],
		null
	);
} );

get_header();
?>
<style>
  body { overflow-x: hidden !important; }
  .product-scroll::-webkit-scrollbar { width: 4px; }
  .product-scroll::-webkit-scrollbar-track { background: transparent; }
  .product-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 9999px; }
  .product-scroll::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
  @media (min-width: 1280px) {
    .breakout-desktop {
      width: 100vw !important;
      max-width: 100vw !important;
      margin-left: calc(50% - 50vw) !important;
      margin-right: calc(50% - 50vw) !important;
    }
  }
  .jn-thankyou-wrap { font-family: 'Prompt', sans-serif; }
  .jn-thankyou-wrap h2 { font-family: 'Mali', sans-serif; }
  /* Blob float animations */
  @keyframes jn-ty-blob-drift-1 {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50%      { transform: translate(calc(-50% + 16px), calc(-50% - 12px)) scale(1.08); }
  }
  @keyframes jn-ty-blob-drift-2 {
    0%, 100% { transform: translate(50%, 50%) scale(1); }
    50%      { transform: translate(calc(50% - 14px), calc(50% + 10px)) scale(1.1); }
  }
  .jn-ty-blob-1 { animation: jn-ty-blob-drift-1 7s ease-in-out infinite; will-change: transform; }
  .jn-ty-blob-2 { animation: jn-ty-blob-drift-2 9s ease-in-out infinite; animation-delay: 2s; will-change: transform; }
  /* Entrance stagger — static header content only, Alpine-driven panels keep their own x-transition */
  @keyframes jn-rise-in {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .jn-rise-in { animation: jn-rise-in .5s ease-out both; }
  .jn-delay-1 { animation-delay: .05s; }
  .jn-delay-2 { animation-delay: .1s;  }
  .jn-delay-3 { animation-delay: .15s; }
  .jn-delay-4 { animation-delay: .2s;  }
  @media (prefers-reduced-motion: reduce) {
    .jn-ty-blob-1,
    .jn-ty-blob-2,
    .jn-rise-in {
      animation: none;
    }
  }
  /* Bill 2 — 7-col table */
  .jn-bill2-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .jn-bill2-table { width: 100%; border-collapse: collapse; min-width: 720px; border: none; }
  .jn-bill2-table thead, .jn-bill2-table tbody, .jn-bill2-table tfoot,
  .jn-bill2-table tr, .jn-bill2-table th, .jn-bill2-table td { border: none; }
  .jn-bill2-table thead th {
    background: linear-gradient(180deg,#FFF5F5 0%,#FFFBFB 100%);
    font-size: 13px; font-weight: 700; color: #27272A;
    padding: 12px 10px; text-align: right;
    border-bottom: 2px solid #FFD1D6 !important;
    white-space: nowrap;
  }
  .jn-bill2-table thead th:first-child { text-align: left; min-width: 180px; }
  .jn-bill2-table thead th.jn-center { text-align: center; }
  .jn-bill2-table tbody td {
    padding: 14px 10px; font-size: 13px; text-align: right;
    border-bottom: 1px solid #F4F4F5 !important;
    vertical-align: middle; color: #3F3F46;
  }
  .jn-bill2-table tbody td:first-child { text-align: left; }
  .jn-bill2-table tbody td.jn-center { text-align: center; }
  .jn-bill2-table tbody tr:last-child td { border-bottom: none !important; }
  .jn-bill2-table tbody tr:hover td { background: #FAFAFA; }
  .jn-money-zero { color: #A1A1AA; }
  /* Summary rows + total bar */
  .jn-summary-row { display: flex; justify-content: space-between; padding: 9px 0; font-size: 13px; color: #52525B; border-bottom: 1px dashed #E4E4E7; }
  .jn-summary-row .jn-val { font-weight: 500; color: #27272A; font-variant-numeric: tabular-nums; }
  .jn-total-bar { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; margin-top: 6px; background: linear-gradient(90deg,#FFE5E5 0%,#FFF5F5 100%); border-top: 2px solid #FFD1D6; border-radius: 0.5rem; }
  .jn-total-bar .jn-label { font-size: 15px; font-weight: 600; color: #C43D55; }
  .jn-total-bar .jn-val { font-size: 20px; font-weight: 700; color: #C43D55; font-variant-numeric: tabular-nums; }
</style>
<div class="jn-thankyou-wrap w-full min-h-[calc(100vh-80px)] pt-[150px] pb-8 md:pt-[220px] lg:pt-[280px] px-4 sm:px-6 lg:px-8 font-sans relative z-10 breakout-desktop">
  
  <!-- Full Width Background Container -->
  <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[100vw] -z-10 overflow-hidden bg-gradient-to-br from-pink-50 via-white to-purple-50">
    <!-- Decorative background blobs -->
    <div class="jn-ty-blob-1 absolute top-0 left-0 w-96 h-96 bg-[#FB5FAB] opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
    <div class="jn-ty-blob-2 absolute bottom-0 right-0 w-96 h-96 bg-purple-400 opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
  </div>

  <main x-data="billTabs()" class="jn-rise-in relative z-10 w-full max-w-[1200px] mx-auto px-4 py-8 md:px-12 md:py-12 rounded-[2rem] bg-white/70 backdrop-blur-xl border border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)]">

  <?php
    $color = '#FB5FAB';
    include get_stylesheet_directory() . '/src/templates/spinner.php';
  ?>

  <div class="text-center pt-2 mb-10">
    <h2 class="jn-rise-in jn-delay-1 text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-[#FB5FAB] to-purple-600 tracking-tight">ขอบคุณสำหรับคำสั่งซื้อ</h2>
    <p class="jn-rise-in jn-delay-2 text-base text-gray-500 mt-3 font-medium">กรุณาชำระเงินเพื่อยืนยันคำสั่งซื้อของคุณ</p>
    <?php
      // $order_id / $order already resolved + ownership-checked at the top of the file
      $order_status = $order ? $order->get_status() : '';
      $bill1_status   = $order ? ( $order->get_meta('_bill1_status', true) ?: 'pending' ) : 'pending';
      $bill2_status   = $order ? ( $order->get_meta('_bill2_status', true) ?: 'pending' ) : 'pending';
      $bill2_has_meta = $order && !in_array( $order->get_meta('_bill2_status', true), [ '', 'draft' ], true );

      $bill2_unit_prices_raw = $order ? $order->get_meta('_bill2_unit_prices', true) : '';
      $bill2_unit_prices     = $bill2_unit_prices_raw ? (array) json_decode($bill2_unit_prices_raw, true) : [];
      $bill2_total           = 0.0;
      if ( $order ) {
          foreach ( $order->get_items() as $item ) {
              if ( ! $item->get_product() ) continue;
              $iid  = (string) $item->get_id();
              $unit = isset( $bill2_unit_prices[$iid] ) ? (float) $bill2_unit_prices[$iid] : 0.0;
              $bill2_total += $unit * $item->get_quantity();
          }
      }
      $bill2_china_shipping = $order ? (array) json_decode($order->get_meta('_bill2_china_shipping', true), true) : [];
      $bill2_import_fee     = $order ? (array) json_decode($order->get_meta('_bill2_import_fee', true), true) : [];

      $bill2_local_shipping = $order ? (float) $order->get_meta('_bill2_local_shipping', true) : 0.0;

      $bill2_extra_shipping       = $order ? (array) json_decode($order->get_meta('_bill2_extra_shipping', true), true) : [];
      $bill2_extra_shipping_total = $bill2_extra_shipping ? array_sum($bill2_extra_shipping) : 0.0;

      $wc_original_total = 0.0;
      if ( $order ) {
          foreach ( $order->get_items() as $_i ) {
              $wc_original_total += (float) $_i->get_total();
          }
      }

      $bill2_meta_amount = $order ? (float) $order->get_meta('_bill2_amount', true) : 0.0;
      $bill2_amount = $bill2_meta_amount ?: ( $bill2_unit_prices ? $bill2_total : ( $order ? (float) $order->get_total() : 0.0 ) );

      if ( current_user_can('manage_options') && isset($_GET['mock_result']) ) {
        $mock = sanitize_key($_GET['mock_result']);
        // pending → wait_verify_1 → bill1_paid → wait_verify_2 → both_paid
        // pending → wait_verify_1 → bill1_paid → wait_verify_2 → both_paid
        // bill2_has_meta เป็น true เฉพาะ state ที่ควรสร้าง bill2 แล้ว
        if ( $mock === 'pending' )          { $bill1_status = 'pending'; $bill2_status = 'pending'; $order_status = 'waiting-transfer'; $bill2_has_meta = false; }
        elseif ( $mock === 'wait_verify_1') { $bill1_status = 'submitted'; $bill2_status = 'pending';    $order_status = 'wait-verify-1'; $bill2_has_meta = false; }
        elseif ( $mock === 'bill1_paid' )   { $bill1_status = 'paid';    $bill2_status = 'pending';    $order_status = 'paid-1';        $bill2_has_meta = false; }
        elseif ( $mock === 'wait_verify_2') { $bill1_status = 'paid';    $bill2_status = 'submitted';  $order_status = 'wait-verify-2'; $bill2_has_meta = true;  }
        elseif ( $mock === 'both_paid' )    { $bill1_status = 'paid';    $bill2_status = 'paid';    $order_status = 'paid-2';           $bill2_has_meta = true;  }
      }

      if ( current_user_can('manage_options') && isset($_GET['mock_bill2']) && $order ) {
        $bill2_has_meta       = true;
        $bill2_status         = 'pending';
        $bill2_unit_prices    = [];
        $bill2_china_shipping = [];
        $bill2_import_fee     = [];
        $bill2_total          = 0.0;
        foreach ( $order->get_items() as $item ) {
          $p = $item->get_product();
          if ( ! $p ) continue;
          $iid  = (string) $item->get_id();
          $qty  = $item->get_quantity();
          $unit = (float) $p->get_price() ?: 199.0;
          $bill2_unit_prices[$iid]    = $unit;
          $bill2_china_shipping[$iid] = round( $unit * 0.12 );
          $bill2_import_fee[$iid]     = round( $unit * 0.08 );
          $bill2_extra_shipping[$iid] = round( $unit * 0.05 );
          $bill2_total += $unit * $qty;
        }
        $bill2_local_shipping        = 50.0;
        $bill2_extra_shipping_total  = array_sum($bill2_extra_shipping);
        $bill2_amount = $bill2_total
          + array_sum($bill2_china_shipping)
          + array_sum($bill2_import_fee)
          + array_sum($bill2_extra_shipping)
          + $bill2_local_shipping;
      }

      $bill1_paid      = $bill1_status === 'paid';
      $bill2_paid      = $bill2_status === 'paid';
      $bill1_submitted = $bill1_status === 'submitted';
      $bill2_submitted = $bill2_status === 'submitted';

      // Shipping Data
      $shipping_locked = $order && jn_is_past_packed( $order );
      $cid = $order ? (int) $order->get_customer_id() : 0;

      if ($shipping_locked) {
          // Locked: อ่านจาก order meta ตรงๆ ไม่ fallback — แก้ไขไม่ได้แล้ว
          $fn = $order->get_shipping_first_name();
          $ln = $order->get_shipping_last_name();
          $shipping_name    = trim("$fn $ln");
          $shipping_phone   = (string) ($order->get_shipping_phone() ?: $order->get_billing_phone());
          $shipping_address = trim(implode(' ', array_filter([
              $order->get_shipping_address_1(),
              $order->get_shipping_address_2(),
              $order->get_shipping_city(),
              $order->get_shipping_state(),
              $order->get_shipping_postcode(),
          ])));
      } else {
          // order fields → billing fields → user meta → lazy migration จาก order เก่า
          $fn = $order ? $order->get_shipping_first_name() : '';
          $ln = $order ? $order->get_shipping_last_name() : '';
          $shipping_name = trim("$fn $ln");
          if (empty($shipping_name)) {
              $shipping_name = $order ? trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : '';
          }

          $shipping_phone = $order ? ((string) ($order->get_shipping_phone() ?: $order->get_billing_phone())) : '';

          $addr1 = $order ? $order->get_shipping_address_1() : '';
          $shipping_address = trim(implode(' ', array_filter([
              $addr1,
              $order ? $order->get_shipping_address_2() : '',
              $order ? $order->get_shipping_city() : '',
              $order ? $order->get_shipping_state() : '',
              $order ? $order->get_shipping_postcode() : '',
          ])));
          if (empty($shipping_address)) {
              $shipping_address = trim(implode(' ', array_filter([
                  $order ? $order->get_billing_address_1() : '',
                  $order ? $order->get_billing_address_2() : '',
                  $order ? $order->get_billing_city() : '',
                  $order ? $order->get_billing_state() : '',
                  $order ? $order->get_billing_postcode() : '',
              ])));
          }

          if ($cid) {
              if (empty($shipping_name)) {
                  $shipping_name = trim(get_user_meta($cid, 'shipping_first_name', true) . ' ' . get_user_meta($cid, 'shipping_last_name', true));
              }
              if (empty($shipping_phone)) {
                  $shipping_phone = (string) get_user_meta($cid, 'billing_phone', true);
              }
              if (empty($shipping_address)) {
                  $shipping_address = (string) get_user_meta($cid, 'shipping_address_1', true);
              }

              // Lazy migration: user meta ว่าง → ดึงจาก order เก่า + เขียน user meta
              if (empty($shipping_address)) {
                  $past_orders = wc_get_orders([
                      'customer_id' => $cid,
                      'limit'       => 5,
                      'orderby'     => 'date',
                      'order'       => 'DESC',
                      'status'      => 'any',
                  ]);
                  foreach ($past_orders as $po) {
                      if ($po->get_id() === ($order ? $order->get_id() : 0)) continue;
                      $pa = $po->get_shipping_address_1();
                      if (empty($pa)) continue;
                      $pfn = $po->get_shipping_first_name();
                      $pln = $po->get_shipping_last_name();
                      $pph = $po->get_shipping_phone() ?: $po->get_billing_phone();
                      if (empty($shipping_name))  $shipping_name  = trim("$pfn $pln");
                      if (empty($shipping_phone)) $shipping_phone = $pph;
                      $shipping_address = $pa;
                      update_user_meta($cid, 'shipping_first_name', $pfn);
                      update_user_meta($cid, 'shipping_last_name',  $pln);
                      update_user_meta($cid, 'shipping_address_1',  $pa);
                      update_user_meta($cid, 'shipping_country',    'TH');
                      if (!empty($pph)) update_user_meta($cid, 'billing_phone', $pph);
                      break;
                  }
              }
          }
      }

      $is_rts_order        = $order && $order->get_meta( '_is_rts_order', true ) === '1';
      $linked_rts_order_id = $order ? (int) $order->get_meta( '_linked_rts_order_id', true ) : 0;
      $linked_rts_order    = $linked_rts_order_id ? wc_get_order( $linked_rts_order_id ) : null;
    ?>
    <span class="jn-rise-in jn-delay-3 inline-block mt-3 px-4 py-1.5 text-sm text-gray-500 bg-gray-100 rounded-lg">
      Order #<?= $order ? $order->get_order_number() : $order_id ?>
    </span>
    <?php if ( current_user_can('manage_options') && isset($mock) && $mock ) : ?>
      <div class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 text-xs font-mono text-amber-700 bg-amber-50 border border-amber-200 rounded-lg">
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400"></span>
        mock: <?= esc_html($mock) ?>
      </div>
    <?php endif; ?>
    <?php if ( current_user_can('manage_options') && isset($_GET['mock_bill2']) ) : ?>
      <div class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 text-xs font-mono text-purple-700 bg-purple-50 border border-purple-200 rounded-lg">
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-400"></span>
        mock_bill2: unit=250 · ส่งจีน=30 · import=20 · ส่งไทย=50
      </div>
    <?php endif; ?>
  </div>

  <div>

    <?php if ( $linked_rts_order ) : ?>
    <div class="jn-rise-in jn-delay-1 flex items-center justify-between gap-3 px-4 py-3 mb-6 bg-emerald-50 border border-emerald-200 rounded-2xl text-sm">
      <div class="flex items-center gap-2 text-emerald-800">
        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
        <span>มีสินค้าพร้อมส่ง (RTS) แยกออกเป็นอีก order หนึ่ง</span>
      </div>
      <a href="<?= esc_url( home_url( '/thank-you-slave/?wcf-order=' . $linked_rts_order_id ) ) ?>" class="shrink-0 px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors">
        ชำระสินค้า RTS →
      </a>
    </div>
    <?php endif; ?>

    <!-- {{-- Tabs --}} -->
    <div class="jn-rise-in jn-delay-4 flex p-1.5 bg-gray-100/60 backdrop-blur-md rounded-[1.25rem] mb-8 shadow-inner border border-gray-200/50">
      <div
        @click="switchTab(1)"
        :class="activeTab === 1 ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'"
        class="flex-1 flex flex-col items-center justify-center gap-1 py-3 px-2 text-center transition-all duration-300 rounded-xl cursor-pointer"
      >
        <span :class="bill1Paid ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : (bill1Submitted ? 'bg-blue-400' : 'bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.4)]')" class="inline-block w-2 h-2 rounded-full transition-colors duration-300"></span>
        <?php if ( $is_rts_order ) : ?>
          <span class="text-xs font-semibold leading-tight">ชำระเงิน</span>
          <span class="text-[10px] opacity-60 leading-tight">พร้อมส่ง</span>
        <?php else : ?>
          <span class="text-xs font-semibold leading-tight">Chinees invoice</span>
          <span class="text-[10px] opacity-60 leading-tight">🇨🇳 บิลจีน</span>
        <?php endif; ?>
      </div>

      <?php if ( ! $is_rts_order ) : ?>
      <div
        @click="switchTab(2)"
        :class="[
          activeTab === 2 ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50',
          (!bill1Paid || !bill2HasMeta) ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'cursor-pointer'
        ]"
        class="flex-1 flex flex-col items-center justify-center gap-1 py-3 px-2 text-center transition-all duration-300 rounded-xl"
      >
        <span :class="bill2Paid ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : (bill1Paid ? (bill2Submitted ? 'bg-blue-400' : 'bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.4)]') : 'bg-gray-300')" class="inline-block w-2 h-2 rounded-full transition-colors duration-300"></span>
        <span class="text-xs font-semibold leading-tight flex items-center gap-1">
          Thai invoice
          <svg x-show="!bill1Paid || !bill2HasMeta" class="w-3 h-3 opacity-60 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </span>
        <span class="text-[10px] opacity-60 leading-tight">🇹🇭 บิลไทย</span>
      </div>
      <?php endif; ?>
    </div>

    <!-- {{-- Bill 1 --}} -->
    <div 
      x-show="activeTab === 1"
      x-transition:enter="transition ease-out duration-500" 
      x-transition:enter-start="opacity-0 translate-y-4 scale-95" 
      x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    >

      <div x-show="!bill1Paid && !bill1Submitted" class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 text-amber-800 text-sm rounded-lg mb-4">
        <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
        รอการชำระเงิน (รบกวนชำระบิลภายใน 24 ชั่วโมง)
      </div>
      <div x-show="bill1Submitted" class="flex items-center gap-2 px-4 py-2.5 bg-blue-50 text-blue-800 text-sm rounded-lg mb-4">
        <span class="inline-block w-2 h-2 rounded-full bg-blue-400"></span>
        รอเจ้าหน้าที่ตรวจสอบ
      </div>
      <div x-show="bill1Paid" class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-800 text-sm rounded-lg mb-4">
        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
        ชำระเงินแล้ว
      </div>

      <div class="pb-6 md:pb-8 mb-6 md:mb-8 border-b border-gray-100">
        <?php if ( $is_rts_order ) : ?>
        <p class="text-base font-medium text-gray-900">ยอดชำระ</p>
        <p class="text-sm text-gray-400 mt-1 mb-6">สินค้าพร้อมส่ง ชำระครั้งเดียว</p>
        <?php endif; ?>

        <div class="flex flex-col gap-8">

          <?php if ( $is_rts_order ) : ?>
          <!-- Shipping address — RTS orders need delivery address before payment -->
          <div>
            <div class="flex items-center justify-between mb-4">
              <div>
                <p class="text-sm font-medium text-gray-700">ข้อมูลการจัดส่ง</p>
                <p class="text-xs text-gray-400 mt-0.5">กรุณาระบุที่อยู่สำหรับจัดส่งสินค้าพร้อมส่ง</p>
              </div>
              <button x-show="canEditShipping" @click="openShippingModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                <span x-text="(shippingName && shippingPhone && shippingAddress) ? 'แก้ไขข้อมูล' : 'กรอกข้อมูล'"></span>
              </button>
            </div>
            <template x-if="!shippingName || !shippingPhone || !shippingAddress">
              <div class="flex items-center gap-2 text-amber-600 text-sm">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>กรุณาระบุที่อยู่ก่อนชำระเงิน</span>
              </div>
            </template>
            <template x-if="shippingName && shippingPhone && shippingAddress">
              <div class="space-y-1 text-sm text-gray-700">
                <p><span class="font-medium text-gray-900" x-text="shippingName"></span> <span class="text-gray-300 mx-2">|</span> <span x-text="shippingPhone"></span></p>
                <p class="whitespace-pre-line" x-text="shippingAddress"></p>
              </div>
            </template>
          </div>
          <?php endif; ?>

          <!-- {{-- รายการสินค้า --}} -->
          <div>
            <p class="text-sm font-medium text-gray-700 mb-3">รายการสินค้า</p>
            <?php if ( $order ) : ?>
              <div class="jn-bill2-table-wrap">
                <table class="jn-bill2-table" style="min-width:400px">
                  <thead>
                    <tr>
                      <th>สินค้า</th>
                      <th class="jn-center">จำนวน</th>
                      <th>ราคา/ชิ้น</th>
                      <th>รวม</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ( $order->get_items() as $item ) :
                    $product = $item->get_product();
                    if ( ! $product ) continue;
                    $img_url = wp_get_attachment_image_url( $product->get_image_id(), 'custom-100' );
                    if ( ! $img_url ) {
                        $img_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                    }
                    $qty1        = $item->get_quantity();
                    $line_total1 = (float) $item->get_total();
                    $unit1       = $qty1 > 0 ? $line_total1 / $qty1 : 0.0;
                  ?>
                    <tr>
                      <td>
                        <div class="flex items-center gap-2">
                          <?php if ( $img_url ) : ?>
                            <img src="<?= esc_url($img_url) ?>" class="w-14 h-14 shrink-0 object-cover rounded-lg border border-gray-200" />
                          <?php endif; ?>
                          <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 !mb-0 leading-snug"><?= esc_html( $item->get_name() ) ?></p>
                            <?php foreach ( $item->get_formatted_meta_data() as $meta ) : ?>
                            <p class="text-xs text-gray-400 !mb-0"><?= esc_html($meta->display_key) ?>: <?= wp_strip_all_tags($meta->display_value) ?></p>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      </td>
                      <td class="jn-center">×<?= $qty1 ?></td>
                      <td>฿<?= number_format($unit1, 2) ?></td>
                      <td>฿<?= number_format($line_total1, 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <?php $order_shipping = $order ? (float) $order->get_shipping_total() : 0.0; ?>
              <div class="border-t border-gray-200 mt-3 pt-3 space-y-1.5">
                <?php if ( $order_shipping > 0 ) : ?>
                <div class="flex justify-between text-xs text-gray-400">
                  <span>ค่าจัดส่ง</span>
                  <span>฿<?= number_format( $order_shipping, 2 ) ?></span>
                </div>
                <?php endif; ?>
                <div class="jn-total-bar mt-1">
                  <span class="jn-label">รวมทั้งหมด</span>
                  <span class="jn-val">฿<?= number_format( $order->get_total(), 2 ) ?></span>
                </div>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- {{-- QR --}} -->
          <div class="flex flex-col gap-4">
            <!-- {{-- QR Code --}} -->
            <div class="flex flex-col items-center gap-3">
              <div class="relative w-full max-w-[240px]">
                <?php
                    $gateway      = WC()->payment_gateways->payment_gateways()['promptpay_qr'] ?? null;
                    $amount       = $order ? $order->get_total() : 0;
                    $qr_mode_snap = $order ? (string) $order->get_meta('_qr_mode',   true) : '';
                    $qr_target    = $order ? (string) $order->get_meta('_qr_target', true) : '';
                    if ( ! $qr_mode_snap ) $qr_mode_snap = get_option('promptpay_qr_mode', 'phone');
                    if ( ! $qr_target ) {
                        $qr_target = $qr_mode_snap === 'biller'
                            ? get_option('promptpay_biller_id', '')
                            : ( $gateway ? $gateway->phone : get_option('promptpay_phone') );
                        if ( ! $qr_target ) {
                            $qr_target    = $gateway ? $gateway->phone : get_option('promptpay_phone');
                            $qr_mode_snap = 'phone';
                        }
                    }
                    $qr_url = $qr_mode_snap === 'biller'
                        ? KShop_QR_Generator::generate( $qr_target, $amount, (string) ( $order ? $order->get_id() : '' ) )
                        : PromptPay_QR_Generator::generate( $qr_target, $amount );
                ?>
                <div class="rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-white">
                    <!-- Thai QR logo header -->
                    <div class="flex items-center justify-center px-4 py-3" style="background-color:#0d3b6e">
                        <img src="<?= get_stylesheet_directory_uri() . '/assets/imgs/thai-qr-logo-white.svg' ?>"
                             alt="Thai QR Payment" class="h-9 w-auto object-contain" />
                    </div>
                    <!-- QR code -->
                    <div class="p-2" style="position:relative">
                        <img src="<?= esc_url($qr_url) ?>" alt="QR" class="w-full object-contain" />
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
                            <div style="background:#fff;border-radius:6px;padding:4px;box-shadow:0 1px 3px rgba(0,0,0,.15)">
                                <img src="<?= get_stylesheet_directory_uri() . '/assets/imgs/kbank-logo.svg' ?>" alt="KBank" style="width:20px;height:auto;display:block" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Watermark ชำระแล้ว (Pinned Note) -->
                <div
                    x-show="bill1Paid"
                    class="absolute inset-0 flex items-center justify-center z-10 bg-white/40 backdrop-blur-[3px] rounded-xl"
                >
                    <div class="relative rotate-[-6deg] bg-gradient-to-br from-yellow-50 to-amber-100 px-6 py-4 shadow-[2px_4px_16px_rgba(0,0,0,0.15)] border border-amber-200 transform hover:scale-105 transition-transform duration-300">
                        <!-- Red Push Pin -->
                        <div class="absolute -top-2 left-1/2 -translate-x-1/2 w-4 h-4 rounded-full bg-red-500 shadow-[inset_-2px_-2px_4px_rgba(0,0,0,0.3),_1px_2px_4px_rgba(0,0,0,0.4)] z-20">
                            <div class="absolute top-[2px] left-[3px] w-1.5 h-1.5 rounded-full bg-white/60"></div>
                        </div>
                        
                        <!-- Text Content -->
                        <div class="text-center mt-1 border-2 border-dashed border-emerald-500/30 p-2 rounded">
                            <p class="text-emerald-600 font-extrabold text-2xl tracking-widest drop-shadow-sm mb-0">ชำระแล้ว</p>
                            <p class="text-emerald-600/80 font-bold text-sm tracking-[0.3em] mb-0">PAID</p>
                        </div>
                    </div>
                </div>
              </div>
              <span class="text-lg font-medium text-gray-900">
                  ฿<?= number_format($amount, 2) ?>
              </span>
              <span class="text-xs text-gray-400">
                  <?= $qr_mode_snap === 'biller' ? 'K-Shop' : 'PromptPay' ?> : <?= esc_html($qr_target) ?>
              </span>
            </div>

            <!-- {{-- Upload Bill 1 --}} -->
            <div class="flex flex-col gap-3">

              <input type="file" class="hidden" accept="image/*" x-ref="file1" @change="handleFile($event, 1)">

              <div
                @click="(preview1 || viewBill1) ? openSlip(preview1 || viewBill1) : $refs.file1.click()"
                class="relative border-[2.5px] border-dashed border-gray-300 rounded-lg overflow-hidden cursor-pointer hover:bg-gray-50 transition-colors group"
                style="height: 140px;"
              >
                <template x-if="!preview1 && !viewBill1">
                  <div class="flex flex-col items-center justify-center h-full gap-2 opacity-70 group-hover:opacity-100 transition-opacity">
                    <svg class="w-6 h-6 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                    </svg>
                    <p class="text-sm text-gray-500 font-medium">แนบสลิปโอนเงิน</p>
                  </div>
                </template>

                <template x-if="preview1 || viewBill1">
                  <div class="relative w-full h-full">
                    <img :src="preview1 || viewBill1" class="w-full h-full object-cover" />
                    
                    <template x-if="!preview1 && viewBill1">
                      <div>
                        <!-- Badge: รอตรวจสอบ (submitted for manual review) -->
                        <div x-show="bill1Submitted && slip1Verify === false" class="absolute top-2 right-2 bg-blue-400 text-white text-xs px-2 py-1 rounded-full shadow-sm">
                          🕐 รอตรวจ
                        </div>
                        <!-- Badge: ตรวจสอบ (SlipOK rejected) -->
                        <div x-show="!bill1Submitted && slip1Verify === false" class="absolute top-2 right-2 bg-amber-400 text-white text-xs px-2 py-1 rounded-full shadow-sm">
                          ⚠︎ ตรวจสอบ
                        </div>
                        <!-- Badge: ชำระแล้ว -->
                        <div x-show="slip1Verify !== false" class="absolute top-2 right-2 bg-emerald-500 text-white text-xs px-2 py-1 rounded-full shadow-sm">
                          ✓ ชำระแล้ว
                        </div>
                      </div>
                    </template>

                    <!-- คลิกเพื่อขยาย (Hover Overlay) -->
                    <div class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/30 transition-colors duration-300">
                      <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 drop-shadow-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                      </svg>
                    </div>
                  </div>
                </template>
              </div>

              <!-- ปุ่ม -->
              <template x-if="!bill1Paid && !bill1Submitted">
                  <div class="flex flex-col gap-2">
                      <button @click="$refs.file1.click()"
                              class="w-full py-2.5 text-sm bg-white hover:bg-gray-50 border border-gray-200 rounded-xl text-gray-700 font-medium transition-colors shadow-sm">
                          <span x-text="preview1 ? 'เปลี่ยนรูป' : 'เลือกไฟล์'"></span>
                      </button>
                      <button
                          @click="payBill1()"
                          :disabled="!preview1"
                          :class="preview1 ? '!bg-gradient-to-r !from-[#FB5FAB] !to-purple-500 !text-white shadow-[0_4px_14px_0_rgba(251,95,171,0.39)] hover:shadow-[0_6px_20px_rgba(251,95,171,0.23)] hover:-translate-y-0.5' : '!bg-gray-200 !text-gray-400 cursor-not-allowed'"
                          class="w-full py-3 text-sm font-bold rounded-xl transition-all duration-300"
                      >
                          ยืนยันการชำระเงิน
                      </button>
                  </div>
              </template>

              <!-- รอตรวจสอบ — ดูสลิปได้ -->
              <template x-if="bill1Submitted && !bill1Paid">
                  <button @click="viewBill1 && openSlip(viewBill1)"
                          class="w-full py-2 text-sm bg-blue-50 border border-blue-200 rounded-lg text-blue-700">
                      🕐 รอตรวจสอบ — ดูสลิปที่แนบ
                  </button>
              </template>

              <!-- ชำระแล้ว — ดูสลิปได้ -->
              <template x-if="bill1Paid">
                  <button @click="viewBill1 && openSlip(viewBill1)"
                          class="w-full py-2 text-sm bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700">
                      🧾 ดูสลิปที่แนบ
                  </button>
              </template>

            </div>

          </div>

        </div>
      </div>
    </div>

    <?php if ( ! $is_rts_order ) : ?>
    <!-- {{-- Bill 2 --}} -->
    <div
      x-show="activeTab === 2"
      x-transition:enter="transition ease-out duration-500" 
      x-transition:enter-start="opacity-0 translate-y-4 scale-95" 
      x-transition:enter-end="opacity-100 translate-y-0 scale-100"
      style="display: none;"
    >

      <div x-show="!bill1Paid" class="flex flex-col items-center justify-center py-16 text-gray-400">
        <svg class="w-8 h-8 mb-3 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2"/>
          <path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
        <p class="text-sm">ชำระบิลแรกก่อนเพื่อปลดล็อกบิลที่สอง</p>
      </div>

      <div x-show="bill1Paid">

        <div x-show="!bill2HasMeta" class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 text-amber-800 text-sm rounded-lg mb-4">
          <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
          กำลังเตรียมบิลที่สอง ยอดนี้อาจมีการเปลี่ยนแปลง
        </div>
        <div x-show="bill2HasMeta && !bill2Paid && !bill2Submitted" class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 text-amber-800 text-sm rounded-lg mb-4">
          <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
          รอการชำระเงิน (รบกวนชำระบิลภายใน 24 ชั่วโมง)
        </div>
        <div x-show="bill2Submitted" class="flex items-center gap-2 px-4 py-2.5 bg-blue-50 text-blue-800 text-sm rounded-lg mb-4">
          <span class="inline-block w-2 h-2 rounded-full bg-blue-400"></span>
          รอเจ้าหน้าที่ตรวจสอบ
        </div>
        <div x-show="bill2Paid" class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-800 text-sm rounded-lg mb-4">
          <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
          ชำระเงินแล้ว
        </div>

        <!-- {{-- Shipping Info (ข้อมูลจัดส่ง) --}} -->
        <div class="pb-6 md:pb-8 mb-6 md:mb-8 border-b border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-base font-medium text-gray-900">ข้อมูลการจัดส่ง</p>
              <p class="text-sm text-gray-400 mt-1">กรุณาตรวจสอบและระบุข้อมูลสำหรับจัดส่งสินค้า</p>
            </div>
            <button x-show="canEditShipping" @click="openShippingModal()" class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
              <span x-text="(shippingName && shippingPhone && shippingAddress) ? 'แก้ไขข้อมูล' : 'กรอกข้อมูล'"></span>
            </button>
          </div>
          
          <div>
            <template x-if="!shippingName || !shippingPhone || !shippingAddress">
              <div class="flex items-center gap-2 text-amber-600 text-sm">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>กรุณาระบุที่อยู่สำหรับจัดส่งสินค้า เพื่อดำเนินการชำระบิลที่สอง</span>
              </div>
            </template>
            <template x-if="shippingName && shippingPhone && shippingAddress">
              <div class="space-y-1 text-sm text-gray-700">
                <p><span class="font-medium text-gray-900" x-text="shippingName"></span> <span class="text-gray-300 mx-2">|</span> <span x-text="shippingPhone"></span></p>
                <p class="whitespace-pre-line" x-text="shippingAddress"></p>
              </div>
            </template>
          </div>
        </div>

        <div class="pb-6 md:pb-8">

          <div class="flex flex-col gap-8">

            <!-- {{-- รายการสินค้า --}} -->
            <div>
              <p class="text-sm font-medium text-gray-700 mb-3">รายการสินค้า</p>
              <?php if ( $order ) : ?>
                <div class="jn-bill2-table-wrap">
                  <table class="jn-bill2-table">
                    <thead>
                      <tr>
                        <th>สินค้า</th>
                        <th class="jn-center">จำนวน</th>
                        <th>ราคาสินค้า</th>
                        <th>Extra Items</th>
                        <th>Extra Shipping Fee</th>
                        <th>ค่าส่งจีน</th>
                        <th>ค่านำเข้า</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $order->get_items() as $item ) :
                      $product      = $item->get_product();
                      $img_url      = wp_get_attachment_image_url( $product->get_image_id(), 'custom-100' );
                      if ( ! $img_url ) {
                          $img_url  = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                      }
                      $iid2         = (string) $item->get_id();
                      $wc_total2    = (float) $item->get_total();
                      $unit2        = isset( $bill2_unit_prices[$iid2] )    ? (float) $bill2_unit_prices[$iid2]    : null;
                      $extra_ship2  = isset( $bill2_extra_shipping[$iid2] ) ? (float) $bill2_extra_shipping[$iid2] : null;
                      $china_ship2  = isset( $bill2_china_shipping[$iid2] ) ? (float) $bill2_china_shipping[$iid2] : null;
                      $import_fee2  = isset( $bill2_import_fee[$iid2] )     ? (float) $bill2_import_fee[$iid2]     : null;
                      $extra_items2 = $unit2 !== null ? $unit2 * $item->get_quantity() : null;
                    ?>
                      <tr>
                        <td>
                          <div class="flex items-center gap-2">
                            <?php if ( $img_url ) : ?>
                              <img src="<?= esc_url($img_url) ?>" class="w-14 h-14 shrink-0 object-cover rounded-lg border border-gray-200" />
                            <?php endif; ?>
                            <div class="min-w-0">
                              <p class="text-sm font-medium text-gray-900 !mb-0 leading-snug"><?= esc_html( $item->get_name() ) ?></p>
                              <?php foreach ( $item->get_formatted_meta_data() as $meta ) : ?>
                              <p class="text-xs text-gray-400 !mb-0"><?= esc_html($meta->display_key) ?>: <?= wp_strip_all_tags($meta->display_value) ?></p>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        </td>
                        <td class="jn-center">×<?= $item->get_quantity() ?></td>
                        <td class="<?= $wc_total2 == 0 ? 'jn-money-zero' : '' ?>">฿<?= number_format($wc_total2, 2) ?></td>
                        <td class="<?= ($extra_items2 ?? 0) == 0 ? 'jn-money-zero' : '' ?>">฿<?= number_format($extra_items2 ?? 0, 2) ?></td>
                        <td class="<?= ($extra_ship2 ?? 0) == 0 ? 'jn-money-zero' : '' ?>">฿<?= number_format($extra_ship2 ?? 0, 2) ?></td>
                        <td class="<?= ($china_ship2 ?? 0) == 0 ? 'jn-money-zero' : '' ?>">฿<?= number_format($china_ship2 ?? 0, 2) ?></td>
                        <td class="<?= ($import_fee2 ?? 0) == 0 ? 'jn-money-zero' : '' ?>">฿<?= number_format($import_fee2 ?? 0, 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <?php
                  $bill2_china_total  = $bill2_china_shipping ? array_sum($bill2_china_shipping) : 0.0;
                  $bill2_import_total = $bill2_import_fee    ? array_sum($bill2_import_fee)    : 0.0;
                  $has_breakdown      = $bill2_china_shipping || $bill2_import_fee || $bill2_local_shipping || $bill2_extra_shipping;
                ?>
                <div class="border-t border-gray-200 mt-4 pt-3">
                  <?php if ( $has_breakdown ) : ?>
                    <?php if ( $wc_original_total > 0 ) : ?>
                    <div class="jn-summary-row">
                      <span>ราคาสินค้า</span>
                      <span class="jn-val">฿<?= number_format($wc_original_total, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $bill2_total > 0 ) : ?>
                    <div class="jn-summary-row">
                      <span>Extra Items</span>
                      <span class="jn-val">฿<?= number_format($bill2_total, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $bill2_extra_shipping_total > 0 ) : ?>
                    <div class="jn-summary-row">
                      <span>Extra Shipping</span>
                      <span class="jn-val">฿<?= number_format($bill2_extra_shipping_total, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $bill2_china_total > 0 ) : ?>
                    <div class="jn-summary-row">
                      <span>รวมค่าส่งจีน</span>
                      <span class="jn-val">฿<?= number_format($bill2_china_total, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $bill2_import_total > 0 ) : ?>
                    <div class="jn-summary-row">
                      <span>รวมค่านำเข้า</span>
                      <span class="jn-val">฿<?= number_format($bill2_import_total, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $bill2_local_shipping > 0 ) : ?>
                    <div class="jn-summary-row">
                      <span>ค่าส่งไทย</span>
                      <span class="jn-val">฿<?= number_format($bill2_local_shipping, 2) ?></span>
                    </div>
                    <?php endif; ?>
                  <?php endif; ?>
                  <div class="jn-total-bar">
                    <span class="jn-label">รวมทั้งหมด</span>
                    <span class="jn-val">฿<?= number_format($bill2_amount, 2) ?></span>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <!-- {{-- QR + Upload — เฉพาะบิลที่เผยแพร่แล้ว ไม่สร้าง QR/แสดงปุ่มอัปโหลดขณะยังเป็นร่าง --}} -->
            <?php if ( $bill2_has_meta ) : ?>
            <div class="flex flex-col gap-4">
              <!-- {{-- QR Code --}} -->
              <div class="flex flex-col items-center gap-3">
                <div class="relative w-full max-w-[240px]">
                  <?php
                      $gateway      = WC()->payment_gateways->payment_gateways()['promptpay_qr'] ?? null;
                      $amount       = $bill2_amount;
                      $qr_mode_snap = $order ? (string) $order->get_meta('_qr_mode',   true) : '';
                      $qr_target    = $order ? (string) $order->get_meta('_qr_target', true) : '';
                      if ( ! $qr_mode_snap ) $qr_mode_snap = get_option('promptpay_qr_mode', 'phone');
                      if ( ! $qr_target ) {
                          $qr_target = $qr_mode_snap === 'biller'
                              ? get_option('promptpay_biller_id', '')
                              : ( $gateway ? $gateway->phone : get_option('promptpay_phone') );
                          if ( ! $qr_target ) {
                              $qr_target    = $gateway ? $gateway->phone : get_option('promptpay_phone');
                              $qr_mode_snap = 'phone';
                          }
                      }
                      $qr_url = $qr_mode_snap === 'biller'
                          ? KShop_QR_Generator::generate( $qr_target, $amount, (string) ( $order ? $order->get_id() : '' ) )
                          : PromptPay_QR_Generator::generate( $qr_target, $amount );
                  ?>
                  <div class="rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-white">
                      <!-- Thai QR logo header -->
                      <div class="flex items-center justify-center px-4 py-3" style="background-color:#0d3b6e">
                          <img src="<?= get_stylesheet_directory_uri() . '/assets/imgs/thai-qr-logo-white.svg' ?>"
                               alt="Thai QR Payment" class="h-9 w-auto object-contain" />
                      </div>
                      <!-- QR code -->
                      <div class="p-2" style="position:relative">
                          <img src="<?= esc_url($qr_url) ?>" alt="QR" class="w-full object-contain" />
                          <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
                              <div style="background:#fff;border-radius:6px;padding:4px;box-shadow:0 1px 3px rgba(0,0,0,.15)">
                                  <img src="<?= get_stylesheet_directory_uri() . '/assets/imgs/kbank-logo.svg' ?>" alt="KBank" style="width:20px;height:auto;display:block" />
                              </div>
                          </div>
                      </div>
                  </div>

                  <!-- Watermark ชำระแล้ว (Pinned Note) -->
                  <div
                      x-show="bill2Paid"
                      class="absolute inset-0 flex items-center justify-center z-10 bg-white/40 backdrop-blur-[3px] rounded-xl"
                  >
                      <div class="relative rotate-[4deg] bg-gradient-to-br from-yellow-50 to-amber-100 px-6 py-4 shadow-[2px_4px_16px_rgba(0,0,0,0.15)] border border-amber-200 transform hover:scale-105 transition-transform duration-300">
                          <!-- Red Push Pin -->
                          <div class="absolute -top-2 left-1/2 -translate-x-1/2 w-4 h-4 rounded-full bg-red-500 shadow-[inset_-2px_-2px_4px_rgba(0,0,0,0.3),_1px_2px_4px_rgba(0,0,0,0.4)] z-20">
                              <div class="absolute top-[2px] left-[3px] w-1.5 h-1.5 rounded-full bg-white/60"></div>
                          </div>
                          
                          <!-- Text Content -->
                          <div class="text-center mt-1 border-2 border-dashed border-emerald-500/30 p-2 rounded">
                              <p class="text-emerald-600 font-extrabold text-2xl tracking-widest drop-shadow-sm mb-0">ชำระแล้ว</p>
                              <p class="text-emerald-600/80 font-bold text-sm tracking-[0.3em] mb-0">PAID</p>
                          </div>
                      </div>
                  </div>
                </div>
                <span class="text-lg font-medium text-gray-900">
                    ฿<?= number_format($amount, 2) ?>
                </span>
                <span class="text-xs text-gray-400">
                    <?= esc_html($qr_target) ?>
                </span>
              </div>

              <!-- {{-- Upload Bill 2 --}} -->
              <div class="flex flex-col gap-3">

                <input type="file" class="hidden" accept="image/*" x-ref="file2" @change="handleFile($event, 2)">

                <div
                  @click="(preview2 || viewBill2) ? openSlip(preview2 || viewBill2) : $refs.file2.click()"
                  class="relative border-[2.5px] border-dashed border-gray-300 rounded-lg overflow-hidden cursor-pointer hover:bg-gray-50 transition-colors group"
                  style="height: 140px;"
                >
                  <template x-if="!preview2 && !viewBill2">
                    <div class="flex flex-col items-center justify-center h-full gap-2 opacity-70 group-hover:opacity-100 transition-opacity">
                      <svg class="w-6 h-6 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                      </svg>
                      <p class="text-sm text-gray-500 font-medium">แนบสลิปโอนเงิน</p>
                    </div>
                  </template>

                  <template x-if="preview2 || viewBill2">
                    <div class="relative w-full h-full">
                      <img :src="preview2 || viewBill2" class="w-full h-full object-cover" />
                      
                      <template x-if="!preview2 && viewBill2">
                        <div>
                          <!-- Badge: รอตรวจสอบ (submitted for manual review) -->
                          <div x-show="bill2Submitted && slip2Verify === false" class="absolute top-2 right-2 bg-blue-400 text-white text-xs px-2 py-1 rounded-full shadow-sm">
                            🕐 รอตรวจ
                          </div>
                          <!-- Badge: ตรวจสอบ (SlipOK rejected) -->
                          <div x-show="!bill2Submitted && slip2Verify === false" class="absolute top-2 right-2 bg-amber-400 text-white text-xs px-2 py-1 rounded-full shadow-sm">
                            ⚠︎ ตรวจสอบ
                          </div>
                          <!-- Badge: ชำระแล้ว -->
                          <div x-show="slip2Verify !== false" class="absolute top-2 right-2 bg-emerald-500 text-white text-xs px-2 py-1 rounded-full shadow-sm">
                            ✓ ชำระแล้ว
                          </div>
                        </div>
                      </template>

                      <!-- คลิกเพื่อขยาย (Hover Overlay) -->
                      <div class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/30 transition-colors duration-300">
                        <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 drop-shadow-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                        </svg>
                      </div>
                    </div>
                  </template>
                </div>

                <!-- ปุ่ม -->
                <template x-if="!bill2Paid && !bill2Submitted">
                    <div class="flex flex-col gap-2">
                        <button @click="$refs.file2.click()"
                                class="w-full py-2.5 text-sm bg-white hover:bg-gray-50 border border-gray-200 rounded-xl text-gray-700 font-medium transition-colors shadow-sm">
                            <span x-text="preview2 ? 'เปลี่ยนรูป' : 'เลือกไฟล์'"></span>
                        </button>
                        <button
                            @click="payBill2()"
                            :disabled="!preview2"
                            :class="preview2 ? '!bg-gradient-to-r !from-[#FB5FAB] !to-purple-500 !text-white shadow-[0_4px_14px_0_rgba(251,95,171,0.39)] hover:shadow-[0_6px_20px_rgba(251,95,171,0.23)] hover:-translate-y-0.5' : '!bg-gray-200 !text-gray-400 cursor-not-allowed'"
                            class="w-full py-3 text-sm font-bold rounded-xl transition-all duration-300"
                        >
                            ยืนยันการชำระเงิน
                        </button>
                    </div>
                </template>

                <!-- รอตรวจสอบ — ดูสลิปได้ -->
                <template x-if="bill2Submitted && !bill2Paid">
                    <button @click="viewBill2 && openSlip(viewBill2)"
                            class="w-full py-2 text-sm bg-blue-50 border border-blue-200 rounded-lg text-blue-700">
                        🕐 รอตรวจสอบ — ดูสลิปที่แนบ
                    </button>
                </template>

                <!-- ชำระแล้ว — ดูสลิปได้ -->
                <template x-if="bill2Paid">
                    <button @click="viewBill2 && openSlip(viewBill2)"
                            class="w-full py-2 text-sm bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700">
                        🧾 ดูสลิปที่แนบ
                    </button>
                </template>

              </div>

            </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Modal ดูสลิป -->
  <template x-teleport="body">
    <div
        x-show="slipModal"
        x-transition
        @click="slipModal = false"
        style="position:fixed; top:0; left:0; right:0; bottom:0; z-index:100000; background:rgba(0,0,0,0.85); cursor:zoom-out;"
    >
      <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;">
        <img :src="slipModalUrl" style="max-width:80vw; max-height:85vh; object-fit:contain;" @click.stop>
      </div>
    </div>
  </template>

  <!-- Modal แก้ไขข้อมูลจัดส่ง — x-teleport ย้ายไป body เพื่อหลีก backdrop-filter stacking context ของ <main> -->
  <template x-teleport="body">
  <div x-show="shippingModal" style="display: none;" x-transition.opacity x-effect="document.body.style.overflow = shippingModal ? 'hidden' : ''" @keydown.escape.window="shippingModal && $event.preventDefault()" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-xl" x-transition.scale.95>
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-medium text-gray-900">แก้ไขข้อมูลจัดส่ง</h3>
        <button @click="!savingShipping && (shippingModal = false)" class="text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-gray-600">ชื่อ-นามสกุล ผู้รับ <span class="text-red-500">*</span></label>
          <input type="text" x-model="shippingNameInput" placeholder="ระบุชื่อผู้รับ" class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-gray-400 focus:ring-2 focus:ring-gray-100 transition-all">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-gray-600">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
          <input type="tel" x-model="shippingPhoneInput" placeholder="08X-XXX-XXXX" class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-gray-400 focus:ring-2 focus:ring-gray-100 transition-all">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-gray-600">ที่อยู่จัดส่งแบบครบถ้วน <span class="text-red-500">*</span></label>
          <textarea x-model="shippingAddressInput" rows="3" placeholder="บ้านเลขที่, หมู่, ซอย, ถนน, ตำบล/แขวง, อำเภอ/เขต, จังหวัด, รหัสไปรษณีย์" class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-gray-400 focus:ring-2 focus:ring-gray-100 transition-all"></textarea>
        </div>
      </div>
      <div class="px-6 py-4 bg-gray-50 flex justify-end gap-2 border-t border-gray-100">
        <button @click="shippingModal = false" :disabled="savingShipping" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50">
          ยกเลิก
        </button>
        <button @click="saveShipping()" :disabled="savingShipping" class="px-4 py-2 text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 rounded-lg transition-colors disabled:opacity-50">
          <span x-show="!savingShipping">บันทึกข้อมูล</span>
          <span x-show="savingShipping">กำลังบันทึก...</span>
        </button>
      </div>
    </div>
  </div>
  </template>

</main>
</div>
<script>
function billTabs() {
  return {
    loading: false,
    activeTab: 1,
    // status ล่าสุดของ order (WC status เช่น wait-verify-1, paid-1, ...)
    orderStatus: '<?= esc_js( $order_status ) ?>',
    mockResult:  '<?= isset($mock) ? esc_js($mock) : '' ?>',
    // ค่าเริ่มต้นมาจาก _bill{N}_status ใน order meta — bill2 จะเปิดให้ก็ต่อเมื่อ bill1Paid
    bill1Paid:      <?= $bill1_paid      ? 'true' : 'false' ?>,
    bill2Paid:      <?= $bill2_paid      ? 'true' : 'false' ?>,
    bill1Submitted: <?= $bill1_submitted ? 'true' : 'false' ?>,
    bill2Submitted: <?= $bill2_submitted ? 'true' : 'false' ?>,
    bill2HasMeta: <?= $bill2_has_meta ? 'true' : 'false' ?>,
    bill2Amount:  <?= (float) $bill2_amount ?>,
    isRtsOrder:   <?= $is_rts_order ? 'true' : 'false' ?>,
    preview1: null,
    preview2: null,
    viewBill1: null,
    viewBill2: null,
    slip1Verify: null,
    slip2Verify: null,

    slipModal: false,
    slipModalUrl: null,

    shippingModal: false,
    shippingNameInput: '',
    shippingPhoneInput: '',
    shippingAddressInput: '',

    shippingName: '<?= esc_js($shipping_name) ?>',
    shippingPhone: '<?= esc_js($shipping_phone) ?>',
    shippingAddress: '<?= esc_js($shipping_address) ?>',
    canEditShipping: <?= $shipping_locked ? 'false' : 'true' ?>,
    savingShipping: false,

    async init() {
      this.$watch('slipModal', val => { document.body.style.overflow = val ? 'hidden' : ''; });
      try {
        this.loading = true;

        // เด้งไป tab 2 เมื่อ bill1 จ่ายแล้ว และมีข้อมูล _bill2_* ใน order meta แล้วเท่านั้น
        console.log('billTabs init', { bill1Paid: this.bill1Paid, bill2HasMeta: this.bill2HasMeta });
        if (this.bill1Paid && this.bill2HasMeta) {
          this.activeTab = 2;
        }

        // โหลดสลิปที่อัปโหลดไว้แล้วเพื่อแสดง preview (ไม่เกี่ยวกับสถานะ paid)
        this.viewBill1 = await this.loadSlip(1);
        if ((this.viewBill1 || this.mockResult) && (this.bill1Submitted || this.orderStatus.startsWith('wait-verify-1'))) {
          this.slip1Verify = false;
        }

        if (this.bill1Paid || this.bill2Submitted) {
          this.viewBill2 = await this.loadSlip(2);
          if ((this.viewBill2 || this.mockResult) && (this.bill2Submitted || this.orderStatus.startsWith('wait-verify-2'))) {
            this.slip2Verify = false;
          }
        }
      } catch (error) {
        console.error('billTabs init error', error);
      } finally {
        this.loading = false;
      }
    },

    loadSlip(bill) {
        return fetch(`/wp-json/promptpay/v1/slip/<?= $order_id ?>/${bill}`, {
            headers: { 'X-WP-Nonce': '<?= wp_create_nonce("wp_rest") ?>' }
        })
        .then(r => {
            if (!r.ok) return null; // ถ้าไม่มีไฟล์ → null
            return r.blob();
        })
        .then(blob => blob ? URL.createObjectURL(blob) : null);
    },

    switchTab(n) {
      if (n === 2 && (!this.bill1Paid || !this.bill2HasMeta)) return;
      this.activeTab = n;
    },
    handleFile(e, bill) {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        if (bill === 1) this.preview1 = ev.target.result;
        if (bill === 2) this.preview2 = ev.target.result;
      };
      reader.readAsDataURL(file);
    },
    openShippingModal() {
        this.shippingNameInput = this.shippingName;
        this.shippingPhoneInput = this.shippingPhone;
        this.shippingAddressInput = this.shippingAddress;
        this.shippingModal = true;
    },
    async saveShipping() {
        if (!this.shippingNameInput || !this.shippingPhoneInput || !this.shippingAddressInput) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณากรอกข้อมูลให้ครบถ้วน',
                text: 'ชื่อ เบอร์โทรศัพท์ และที่อยู่จัดส่งห้ามเว้นว่าง',
                confirmButtonColor: '#111827',
            });
            return;
        }

        this.savingShipping = true;
        
        try {
            const res = await fetch(`/wp-json/jaonaichan/v1/orders/<?= $order_id ?>/shipping`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': '<?= wp_create_nonce("wp_rest") ?>'
                },
                body: JSON.stringify({
                    shipping_name: this.shippingNameInput,
                    shipping_phone: this.shippingPhoneInput,
                    shipping_address: this.shippingAddressInput
                })
            });
            
            const json = await res.json();
            
            if (res.ok && json.success) {
                this.shippingName = this.shippingNameInput;
                this.shippingPhone = this.shippingPhoneInput;
                this.shippingAddress = this.shippingAddressInput;
                this.shippingModal = false;
                
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกข้อมูลสำเร็จ',
                    text: 'บันทึกข้อมูลจัดส่งลงในคำสั่งซื้อเรียบร้อยแล้ว',
                    confirmButtonColor: '#111827',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                throw new Error(json.message || 'Update failed');
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: err.message,
                confirmButtonColor: '#111827',
            });
        } finally {
            this.savingShipping = false;
        }
    },
    async payBill1() {
      if (!this.preview1) return;
      if (this.isRtsOrder && (!this.shippingName || !this.shippingPhone || !this.shippingAddress)) {
          Swal.fire({
              icon: 'warning',
              title: 'ข้อมูลจัดส่งไม่ครบถ้วน',
              text: 'กรุณาระบุชื่อ เบอร์โทรศัพท์ และที่อยู่จัดส่งก่อนชำระเงิน',
              confirmButtonColor: '#111827',
          });
          return;
      }

      if (this.isRtsOrder) {
          const confirmAddr = await Swal.fire({
              title: 'ยืนยันที่อยู่จัดส่ง',
              html: `<div style="text-align:left;font-size:0.875rem;line-height:1.5">
                  <p style="margin:0"><strong>${this.shippingName}</strong> &nbsp;·&nbsp; ${this.shippingPhone}</p>
                  <p style="margin:0.5rem 0 0;color:#6b7280">${this.shippingAddress}</p>
              </div>`,
              showCancelButton: true,
              confirmButtonText: 'ถูกต้อง ยืนยัน',
              cancelButtonText: 'แก้ไขที่อยู่',
              confirmButtonColor: '#111827',
              cancelButtonColor: '#6b7280',
          });
          if (!confirmAddr.isConfirmed) {
              if (confirmAddr.dismiss === Swal.DismissReason.cancel) this.openShippingModal();
              return;
          }
      }

      try {
        this.loading = true;
        let json;
        if (this.mockResult) {
          json = { success: true, data: { verify: true, message: '[mock]' } };
        } else {
          const formData = new FormData();
          formData.append('action',   'promptpay_verify_slip');
          formData.append('bill',     '1');
          formData.append('nonce',    '<?= wp_create_nonce("promptpay_upload_slip") ?>');
          formData.append('order_id', '<?= $order_id ?>');
          formData.append('slip',     this.$refs.file1.files[0]);

          const res = await fetch('<?= admin_url("admin-ajax.php") ?>', { method: 'POST', body: formData });
          json = await res.json();
        }

        console.log('🚀 payBill1.result', json);

        this.slip1Verify = json.data.verify ?? false;
        // clear preview so the viewBill1 badge template becomes active
        this.preview1 = null;
        this.viewBill1 = await this.loadSlip(1);
        if (json.success && json.data.verify) {
          const patch1 = await fetch(`/wp-json/jaonaichan/v1/orders/<?= $order_id ?>/bill/1`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?= wp_create_nonce("wp_rest") ?>' },
            body: JSON.stringify({ status: 'paid', paid_at: new Date().toISOString() }),
          });
          this.loading = false;
          if (!patch1.ok) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถอัปเดตสถานะได้ กรุณาติดต่อแอดมิน', confirmButtonColor: '#111827' });
            return;
          }
          await Swal.fire({
            icon: 'success',
            title: 'ชำระเงินสำเร็จ',
            text: 'ระบบได้รับสลิปของคุณแล้ว',
            confirmButtonColor: '#111827',
          });
          window.location.href = '/shop';
        } else if (json.success && !json.data.verify) {
          await fetch(`/wp-json/jaonaichan/v1/orders/<?= $order_id ?>/bill/1`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?= wp_create_nonce("wp_rest") ?>' },
            body: JSON.stringify({ status: 'submitted' }),
          });
          this.bill1Submitted = true;
          this.loading = false;
          Swal.fire({
            icon: 'info',
            title: 'รับสลิปแล้ว',
            text: 'รอเจ้าหน้าที่ตรวจสอบ',
            confirmButtonColor: '#111827',
          });
        } else {
          this.loading = false;
          Swal.fire({
            icon: 'warning',
            title: 'ตรวจสอบไม่ผ่าน',
            text: json.data?.message ?? 'กรุณาลองอีกครั้ง',
            confirmButtonColor: '#111827',
          });
        }
      } catch (error) {
        console.error('Error occurred while paying bill 1:', error);
      } finally {
        this.loading = false;
      } 
    },
    async payBill2() {
      if (!this.preview2) return;
      if (!this.shippingName || !this.shippingPhone || !this.shippingAddress) {
          Swal.fire({
              icon: 'warning',
              title: 'ข้อมูลจัดส่งไม่ครบถ้วน',
              text: 'กรุณาระบุข้อมูลสำหรับจัดส่งสินค้าให้เรียบร้อยก่อนยืนยันการชำระเงิน',
              confirmButtonColor: '#111827',
          });
          return;
      }

      const confirmAddr = await Swal.fire({
          title: 'ยืนยันที่อยู่จัดส่ง',
          html: `<div style="text-align:left;font-size:0.875rem;line-height:1.5">
              <p style="margin:0"><strong>${this.shippingName}</strong> &nbsp;·&nbsp; ${this.shippingPhone}</p>
              <p style="margin:0.5rem 0 0;color:#6b7280">${this.shippingAddress}</p>
          </div>`,
          showCancelButton: true,
          confirmButtonText: 'ถูกต้อง ยืนยัน',
          cancelButtonText: 'แก้ไขที่อยู่',
          confirmButtonColor: '#111827',
          cancelButtonColor: '#6b7280',
      });
      if (!confirmAddr.isConfirmed) {
          if (confirmAddr.dismiss === Swal.DismissReason.cancel) this.openShippingModal();
          return;
      }

      try {
        this.loading = true;
        let json;
        if (this.mockResult) {
          json = { success: true, data: { verify: true, message: '[mock]' } };
        } else {
          const formData = new FormData();
          formData.append('action',   'promptpay_verify_slip');
          formData.append('bill',     '2');
          formData.append('nonce',    '<?= wp_create_nonce("promptpay_upload_slip") ?>');
          formData.append('order_id', '<?= $order_id ?>');
          formData.append('amount',   this.bill2Amount);
          formData.append('slip',     this.$refs.file2.files[0]);

          const res = await fetch('<?= admin_url("admin-ajax.php") ?>', { method: 'POST', body: formData });
          json = await res.json();
        }

        console.log('🚀 payBill2.result', json);

        this.slip2Verify = json.data.verify ?? false;
        this.preview2 = null;
        this.viewBill2 = await this.loadSlip(2);
        if (json.success && json.data.verify) {
            const patch2 = await fetch(`/wp-json/jaonaichan/v1/orders/<?= $order_id ?>/bill/2`, {
              method: 'PATCH',
              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?= wp_create_nonce("wp_rest") ?>' },
              body: JSON.stringify({ status: 'paid', amount: this.bill2Amount, paid_at: new Date().toISOString() }),
            });
            this.loading = false;
            if (!patch2.ok) {
              Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถอัปเดตสถานะได้ กรุณาติดต่อแอดมิน', confirmButtonColor: '#111827' });
              return;
            }
            await Swal.fire({
              icon: 'success',
              title: 'ชำระเงินสำเร็จ',
              text: 'ระบบได้รับสลิปของคุณแล้ว',
              confirmButtonColor: '#111827',
            });
            window.location.href = '/shop';
        } else if (json.success && !json.data.verify) {
            await fetch(`/wp-json/jaonaichan/v1/orders/<?= $order_id ?>/bill/2`, {
              method: 'PATCH',
              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?= wp_create_nonce("wp_rest") ?>' },
              body: JSON.stringify({ status: 'submitted' }),
            });
            this.bill2Submitted = true;
            this.loading = false;
            Swal.fire({
              icon: 'info',
              title: 'รับสลิปแล้ว',
              text: 'รอเจ้าหน้าที่ตรวจสอบ',
              confirmButtonColor: '#111827',
            });
        } else {
            this.loading = false;
            Swal.fire({
              icon: 'warning',
              title: 'ตรวจสอบไม่ผ่าน',
              text: json.data?.message ?? 'กรุณาลองอีกครั้ง',
              confirmButtonColor: '#111827',
            });
        }
      } catch (error) {
        console.error('Error occurred while paying bill 2:', error);
      } finally {
        this.loading = false;
      }
    },

    openSlip(url) {
      this.slipModalUrl = url;
      this.slipModal    = true;
    },
  }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" defer></script>

<?php get_footer(); ?>