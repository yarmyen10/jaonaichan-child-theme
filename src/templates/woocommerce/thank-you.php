<?php
/**
 * Template Name: Thank You
 */
get_header();
?>

<div class="w-full mx-auto max-w-8/10 px-12 py-12 my-12 bg-[#ffffff]">

  <div class="text-center mb-8">
    <h2 class="text-2xl font-medium text-gray-900">ขอบคุณสำหรับคำสั่งซื้อ</h2>
    <p class="text-sm text-gray-500 mt-1">กรุณาชำระเงินเพื่อยืนยันคำสั่งซื้อของคุณ</p>
    <?php
      $order_id = isset($_GET['wcf-order']) ? intval($_GET['wcf-order']) : 0;
      $order    = $order_id ? wc_get_order($order_id) : null;
    ?>
    <span class="inline-block mt-3 px-4 py-1.5 text-sm text-gray-500 bg-gray-100 rounded-lg">
      Order #<?= $order ? $order->get_order_number() : $order_id ?>
    </span>
  </div>

  <div x-data="billTabs()">
    {{-- Tabs --}}
    <div class="flex border-b border-gray-200 mb-6">
      <div
        @click="switchTab(1)"
        :class="activeTab === 1 ? 'border-b-2 border-gray-900 text-gray-900 font-medium' : 'text-gray-400'"
        class="flex-1 flex items-center justify-center gap-2 pb-3 text-sm transition-colors cursor-pointer"
      >
        <span :class="bill1Paid ? 'bg-emerald-500' : 'bg-amber-400'" class="inline-block w-2 h-2 rounded-full"></span>
        บิลแรก
      </div>

      <div
        @click="switchTab(2)"
        :class="[
          activeTab === 2 ? 'border-b-2 border-gray-900 text-gray-900 font-medium' : 'text-gray-400',
          !bill1Paid ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'cursor-pointer'
        ]"
        class="flex-1 flex items-center justify-center gap-2 pb-3 text-sm transition-colors"
      >
        <span :class="bill2Paid ? 'bg-emerald-500' : (bill1Paid ? 'bg-amber-400' : 'bg-gray-300')" class="inline-block w-2 h-2 rounded-full"></span>
        บิลที่สอง
        <svg x-show="!bill1Paid" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2"/>
          <path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
      </div>
    </div>

    {{-- Bill 1 --}}
    <div x-show="activeTab === 1">

      <div x-show="!bill1Paid" class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 text-amber-800 text-sm rounded-lg mb-4">
        <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
        รอการชำระเงิน
      </div>
      <div x-show="bill1Paid" class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-800 text-sm rounded-lg mb-4">
        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
        ชำระเงินแล้ว
      </div>

      <div class="border border-gray-100 rounded-xl p-6">
        <p class="text-base font-medium text-gray-900">ค่ามัดจำ</p>
        <p class="text-sm text-gray-400 mt-1 mb-6">ชำระครึ่งหนึ่งของยอดรวม</p>

        <div class="grid grid-cols-2 gap-6">

          {{-- QR --}}
          <div class="flex flex-col items-center gap-3 bg-gray-50 rounded-lg p-4">
            <?php
              $gateway = WC()->payment_gateways->payment_gateways()['promptpay_qr'] ?? null;
              $phone   = $gateway ? $gateway->phone : get_option('promptpay_phone');
              $amount = $order ? $order->get_total() : 0; // หรือดึงจาก order
              $qr_url = PromptPay_QR_Generator::generate($phone, $amount);
            ?>
            <pre><?php echo get_option('promptpay_phone'); ?></pre>
            <div class="w-32 h-32 bg-white border border-gray-200 rounded-lg flex items-center justify-center">
              <img src="<?= esc_url($qr_url) ?>" alt="QR" class="w-full h-full object-contain" />
              <!-- <svg class="w-16 h-16 text-gray-300" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 3h7v7H3V3zm2 2v3h3V5H5zm7-2h7v7h-7V3zm2 2v3h3V5h-3zM3 12h7v7H3v-7zm2 2v3h3v-3H5zm10 0h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4-4h2v2h-2v-2zm-4-2h2v2h-2v-2z"/>
              </svg> -->
            </div>
            <span class="text-lg font-medium text-gray-900">฿<?= number_format($amount, 2) ?></span>
            <span class="text-xs text-gray-400">PromptPay QR : <?= $phone ?></span>
          </div>

          {{-- Upload Bill 1 --}}
          <div class="flex flex-col gap-3">

            <input type="file" class="hidden" accept="image/*" x-ref="file1" @change="handleFile($event, 1)">

            <div
              @click="$refs.file1.click()"
              class="relative border border-dashed border-gray-300 rounded-lg overflow-hidden cursor-pointer hover:bg-gray-50 transition-colors"
              style="height: 140px;"
            >
              <template x-if="!preview1">
                <div class="flex flex-col items-center justify-center h-full gap-2">
                  <svg class="w-6 h-6 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                  </svg>
                  <p class="text-sm text-gray-400">แนบสลิปโอนเงิน</p>
                </div>
              </template>
              <template x-if="preview1">
                <img :src="preview1" class="w-full h-full object-cover">
              </template>
            </div>

            <button
              @click="$refs.file1.click()"
              class="w-full py-2 text-sm bg-gray-100 border border-gray-200 rounded-lg text-gray-700"
            >
              <span x-text="preview1 ? 'เปลี่ยนรูป' : 'เลือกไฟล์'"></span>
            </button>

            <button
              x-show="!bill1Paid"
              @click="payBill1()"
              :disabled="!preview1"
              :class="preview1 ? '!bg-gray-900 !text-white' : '!bg-gray-200 !text-gray-400 cursor-not-allowed'"
              class="w-full py-2.5 text-sm font-medium rounded-lg transition-colors"
            >
              ยืนยันการชำระเงิน
            </button>

          </div>
        </div>
      </div>
    </div>

    {{-- Bill 2 --}}
    <div x-show="activeTab === 2">

      <div x-show="!bill1Paid" class="flex flex-col items-center justify-center py-16 text-gray-400">
        <svg class="w-8 h-8 mb-3 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2"/>
          <path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
        <p class="text-sm">ชำระบิลแรกก่อนเพื่อปลดล็อกบิลที่สอง</p>
      </div>

      <div x-show="bill1Paid">

        <div x-show="!bill2Paid" class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 text-amber-800 text-sm rounded-lg mb-4">
          <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span>
          รอการชำระเงิน
        </div>
        <div x-show="bill2Paid" class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-800 text-sm rounded-lg mb-4">
          <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
          ชำระเงินแล้ว
        </div>

        <div class="border border-gray-100 rounded-xl p-6">
          <p class="text-base font-medium text-gray-900">ค่าส่วนที่เหลือ</p>
          <p class="text-sm text-gray-400 mt-1 mb-6">ยอดคงเหลือทั้งหมด</p>

          <div class="grid grid-cols-2 gap-6">

            {{-- QR --}}
            <div class="flex flex-col items-center gap-3 bg-gray-50 rounded-lg p-4">
              <div class="w-32 h-32 bg-white border border-gray-200 rounded-lg flex items-center justify-center">
                <svg class="w-16 h-16 text-gray-300" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M3 3h7v7H3V3zm2 2v3h3V5H5zm7-2h7v7h-7V3zm2 2v3h3V5h-3zM3 12h7v7H3v-7zm2 2v3h3v-3H5zm10 0h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4-4h2v2h-2v-2zm-4-2h2v2h-2v-2z"/>
                </svg>
              </div>
              <span class="text-lg font-medium text-gray-900">฿1,500</span>
              <span class="text-xs text-gray-400">PromptPay QR</span>
            </div>

            {{-- Upload Bill 2 --}}
            <div class="flex flex-col gap-3">

              <input type="file" class="hidden" accept="image/*" x-ref="file2" @change="handleFile($event, 2)">

              <div
                @click="$refs.file2.click()"
                class="relative border border-dashed border-gray-300 rounded-lg overflow-hidden cursor-pointer hover:bg-gray-50 transition-colors"
                style="height: 140px;"
              >
                <template x-if="!preview2">
                  <div class="flex flex-col items-center justify-center h-full gap-2">
                    <svg class="w-6 h-6 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                    </svg>
                    <p class="text-sm text-gray-400">แนบสลิปโอนเงิน</p>
                  </div>
                </template>
                <template x-if="preview2">
                  <img :src="preview2" class="w-full h-full object-cover">
                </template>
              </div>

              <button
                @click="$refs.file2.click()"
                class="w-full py-2 text-sm bg-gray-100 border border-gray-200 rounded-lg text-gray-700"
              >
                <span x-text="preview2 ? 'เปลี่ยนรูป' : 'เลือกไฟล์'"></span>
              </button>

              <button
                x-show="!bill2Paid"
                @click="payBill2()"
                :disabled="!preview2"
                :class="preview2 ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                class="w-full py-2.5 text-sm font-medium rounded-lg transition-colors"
              >
                ยืนยันการชำระเงิน
              </button>

            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function billTabs() {
  return {
    activeTab: 1,
    bill1Paid: false,
    bill2Paid: false,
    preview1: null,
    preview2: null,
    switchTab(n) {
      if (n === 2 && !this.bill1Paid) return;
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
    payBill1() {
      if (!this.preview1) return;
      this.bill1Paid = true;
      this.activeTab = 2;
    },
    payBill2() {
      if (!this.preview2) return;
      this.bill2Paid = true;
    }
  }
}
</script>

<?php get_footer(); ?>