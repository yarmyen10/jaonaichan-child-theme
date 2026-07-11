<?php
/**
 * Dashboard partial — Overview
 * Loaded by page-dashboard.php (?tab=overview)
 *
 * Requires: $user, $base_url from parent template.
 * Fetches GET /bigboss-auth/v1/my-summary on mount.
 */

$rest_nonce   = wp_create_nonce( 'wp_rest' );
$summary_url  = esc_js( rest_url( 'bigboss-auth/v1/my-summary' ) );
$orders_tab   = esc_url( add_query_arg( 'tab', 'orders', $base_url ) );
?>

<div
    x-data="overviewPage('<?= $summary_url ?>', '<?= esc_js( $rest_nonce ) ?>')"
    x-init="load()"
    class="space-y-6"
>
    <!-- Page heading -->
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            <?= __( 'ภาพรวม', 'jaonaichan' ) ?>
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
            <?= __( 'สรุปกิจกรรมและคำสั่งซื้อของคุณ', 'jaonaichan' ) ?>
        </p>
    </div>

    <!-- Loading skeleton -->
    <template x-if="status === 'loading'">
        <div class="animate-pulse space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="h-24 rounded-2xl bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-24 rounded-2xl bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-24 rounded-2xl bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="h-64 rounded-2xl bg-gray-200 dark:bg-gray-700"></div>
        </div>
    </template>

    <!-- Error state -->
    <template x-if="status === 'error'">
        <div class="rounded-2xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-6 text-center">
            <p class="text-red-600 dark:text-red-400 font-medium" x-text="errorMsg"></p>
            <button @click="load()" class="mt-3 text-sm underline text-red-500 hover:text-red-700">
                <?= __( 'ลองใหม่', 'jaonaichan' ) ?>
            </button>
        </div>
    </template>

    <!-- Main content -->
    <template x-if="status === 'ready'">
        <div class="space-y-6">

            <!-- Stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                <!-- Total orders -->
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                <?= __( 'คำสั่งซื้อทั้งหมด', 'jaonaichan' ) ?>
                            </p>
                            <p class="mt-1.5 text-3xl font-extrabold text-gray-800 dark:text-white" x-text="summary.order_count"></p>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#fce7f3;">
                            <svg class="w-5 h-5" style="color:#ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                    </div>
                    <a href="<?= $orders_tab ?>" class="mt-3 inline-flex items-center gap-1 text-xs font-medium hover:underline" style="color:#ec4899;">
                        <?= __( 'ดูทั้งหมด', 'jaonaichan' ) ?>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                <!-- Total spent -->
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                <?= __( 'ยอดซื้อสะสม', 'jaonaichan' ) ?>
                            </p>
                            <p class="mt-1.5 text-2xl font-extrabold text-gray-800 dark:text-white" x-text="fmtMoney(summary.total_spent)"></p>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#fce7f3;">
                            <svg class="w-5 h-5" style="color:#ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                        <?= __( 'เฉพาะออเดอร์ที่ชำระแล้ว', 'jaonaichan' ) ?>
                    </p>
                </div>

                <!-- Last order -->
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                <?= __( 'ซื้อล่าสุด', 'jaonaichan' ) ?>
                            </p>
                            <p class="mt-1.5 text-base font-bold text-gray-800 dark:text-white leading-snug"
                               x-text="summary.last_order_date ? fmtDate(summary.last_order_date) : '<?= esc_js( __( 'ยังไม่มีคำสั่งซื้อ', 'jaonaichan' ) ) ?>'">
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#fce7f3;">
                            <svg class="w-5 h-5" style="color:#ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Recent orders -->
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">

                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">
                        <?= __( 'คำสั่งซื้อล่าสุด', 'jaonaichan' ) ?>
                    </h3>
                    <a href="<?= $orders_tab ?>" class="text-xs font-medium hover:underline" style="color:#ec4899;">
                        <?= __( 'ดูทั้งหมด →', 'jaonaichan' ) ?>
                    </a>
                </div>

                <!-- Empty state -->
                <template x-if="summary.recent_orders.length === 0">
                    <div class="py-12 text-center">
                        <svg class="mx-auto w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="mt-3 text-sm text-gray-400 dark:text-gray-500">
                            <?= __( 'ยังไม่มีคำสั่งซื้อ', 'jaonaichan' ) ?>
                        </p>
                    </div>
                </template>

                <!-- Order rows -->
                <template x-if="summary.recent_orders.length > 0">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <template x-for="order in summary.recent_orders" :key="order.id">
                            <li class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">

                                <!-- Status dot -->
                                <div class="flex-shrink-0 w-2 h-2 rounded-full" :style="statusDot(order.status)"></div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-semibold text-gray-800 dark:text-white" x-text="'#' + order.number"></span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                              :style="statusBadge(order.status)"
                                              x-text="statusLabel(order.status)">
                                        </span>
                                        <template x-if="order.bill2?.status && ['pending-payment-2','wait-verify-2'].includes(order.status)">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                  :style="bill2Badge(order.bill2.status)"
                                                  x-text="bill2Label(order.bill2.status)">
                                            </span>
                                        </template>
                                        <template x-if="order.is_rts">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold" style="background:#d1fae5;color:#065f46;">
                                                ⚡ RTS
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate"
                                       x-text="orderProductLine(order)">
                                    </p>
                                </div>

                                <!-- Amount + date -->
                                <div class="flex-shrink-0 text-right">
                                    <p class="text-sm font-bold text-gray-800 dark:text-white" x-text="fmtMoney(displayAmount(order))"></p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="fmtDate(order.date)"></p>
                                    <a
                                        :href="'/thank-you-slave/?wcf-order=' + order.id"
                                        class="mt-1 inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                        style="color:#ec4899;"
                                    >
                                        <?= __( 'รายละเอียด', 'jaonaichan' ) ?>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>

                            </li>
                        </template>
                    </ul>
                </template>

            </div>

        </div>
    </template>

</div>

<script>
function overviewPage(apiUrl, nonce) {
    return {
        status:   'loading',
        summary:  null,
        errorMsg: '',

        async load() {
            this.status = 'loading';
            try {
                const res  = await fetch(apiUrl, { headers: { 'X-WP-Nonce': nonce }, cache: 'no-store' });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'โหลดไม่สำเร็จ');
                this.summary = data;
                this.status  = 'ready';
            } catch (e) {
                this.errorMsg = e.message;
                this.status   = 'error';
            }
        },

        fmtMoney(amount) {
            return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB', minimumFractionDigits: 0 }).format(amount);
        },

        fmtDate(str) {
            if (!str) return '-';
            return new Date(str).toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });
        },

        statusLabel(s) {
            const m = {
                pending: 'รอดำเนินการ', processing: 'กำลังดำเนินการ', 'on-hold': 'ระงับไว้',
                completed: 'สำเร็จแล้ว', cancelled: 'ยกเลิก', refunded: 'คืนเงิน', failed: 'ล้มเหลว',
                'waiting-transfer': 'รอโอนเงิน',
                'pending-payment-1': 'รอชำระบิล 1', 'pending-payment-2': 'รอชำระบิล 2',
                'wait-verify-1': 'รอตรวจสอบ (1)', 'wait-verify-2': 'รอตรวจสอบ (2)',
                'paid-1': 'ชำระแล้ว (1)', 'paid-2': 'ชำระแล้ว (2)',
                'packed': 'แพ็คแล้ว', 'wait-tracking': 'รอการติดตาม', 'tracked': 'ติดตามแล้ว',
                'wait-shipping': 'รอการจัดส่ง', 'shipped': 'จัดส่งแล้ว',
            };
            return m[s] || s;
        },

        statusBadge(s) {
            const m = {
                pending: 'background:#fef9c3;color:#854d0e',
                processing: 'background:#dbeafe;color:#1e40af',
                'on-hold': 'background:#f3f4f6;color:#374151',
                completed: 'background:#dcfce7;color:#166534',
                cancelled: 'background:#fee2e2;color:#991b1b',
                refunded: 'background:#f3f4f6;color:#374151',
                failed: 'background:#fee2e2;color:#991b1b',
                'waiting-transfer': 'background:#fef3c7;color:#92400e',
                'pending-payment-1': 'background:#fef3c7;color:#92400e',
                'pending-payment-2': 'background:#fed7aa;color:#9a3412',
                'wait-verify-1': 'background:#fef3c7;color:#92400e',
                'wait-verify-2': 'background:#fed7aa;color:#9a3412',
                'paid-1': 'background:#dbeafe;color:#1e40af',
                'paid-2': 'background:#bfdbfe;color:#1d4ed8',
                'packed': 'background:#d1fae5;color:#065f46',
                'wait-tracking': 'background:#fef9c3;color:#854d0e',
                'tracked': 'background:#bfdbfe;color:#1e40af',
                'wait-shipping': 'background:#ddd6fe;color:#5b21b6',
                'shipped': 'background:#6ee7b7;color:#064e3b',
            };
            return m[s] || 'background:#f3f4f6;color:#374151';
        },

        statusDot(s) {
            const m = {
                pending: '#f59e0b', processing: '#3b82f6', 'on-hold': '#9ca3af',
                completed: '#22c55e', cancelled: '#ef4444', refunded: '#9ca3af', failed: '#ef4444',
                'waiting-transfer': '#f59e0b',
                'pending-payment-1': '#f59e0b', 'pending-payment-2': '#f97316',
                'wait-verify-1': '#f59e0b', 'wait-verify-2': '#f97316',
                'paid-1': '#3b82f6', 'paid-2': '#6366f1',
                'packed': '#22c55e', 'wait-tracking': '#f59e0b', 'tracked': '#3b82f6',
                'wait-shipping': '#8b5cf6', 'shipped': '#10b981',
            };
            return `background:${m[s] || '#9ca3af'}`;
        },

        orderProductLine(order) {
            const names = order.product_names.join(', ');
            return order.more_items > 0 ? `${names} +${order.more_items} รายการ` : names;
        },

        bill2Label(s) {
            const m = { draft: 'Draft', pending: 'เปิดแล้ว', submitted: 'ส่งสลิปแล้ว', paid: 'ชำระแล้ว' };
            return m[s] || s;
        },

        bill2Badge(s) {
            const m = {
                draft:     'background:#f3f4f6;color:#6b7280',
                pending:   'background:#fef9c3;color:#854d0e',
                submitted: 'background:#fed7aa;color:#9a3412',
                paid:      'background:#dcfce7;color:#166534',
            };
            return m[s] || 'background:#f3f4f6;color:#6b7280';
        },

        displayAmount(order) {
            const bill1 = ['pending-payment-1', 'wait-verify-1', 'paid-1'];
            const bill2 = ['pending-payment-2', 'wait-verify-2', 'paid-2'];
            if (bill1.includes(order.status)) return order.bill1?.amount > 0 ? order.bill1.amount : order.total;
            if (bill2.includes(order.status)) return order.bill2?.amount > 0 ? order.bill2.amount : order.total;
            return order.total;
        },
    };
}
</script>
