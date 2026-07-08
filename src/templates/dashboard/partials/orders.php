<?php
/**
 * Dashboard partial — Orders
 * Loaded by page-dashboard.php (?tab=orders)
 *
 * Requires: $user, $base_url from parent template.
 * Fetches GET /bigboss-auth/v1/my-orders on mount and on filter change.
 */

$rest_nonce = wp_create_nonce( 'wp_rest' );
$orders_url = esc_js( rest_url( 'bigboss-auth/v1/my-orders' ) );
?>

<div
    x-data="ordersPage('<?= $orders_url ?>', '<?= esc_js( $rest_nonce ) ?>')"
    x-init="load()"
    class="space-y-5"
>
    <!-- Page heading + filter -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                <?= __( 'คำสั่งซื้อ', 'jaonaichan' ) ?>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                <span x-text="pagination.total"></span>
                <?= __( 'รายการ', 'jaonaichan' ) ?>
            </p>
        </div>

        <!-- Status filter -->
        <select
            x-model="filterStatus"
            @change="load(1)"
            class="w-full sm:w-48 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm px-3 py-2 focus:outline-none focus:ring-2"
            style="--tw-ring-color:#ec4899;"
        >
            <option value="any"><?= __( 'ทั้งหมด', 'jaonaichan' ) ?></option>
            <option value="pending"><?= __( 'รอดำเนินการ', 'jaonaichan' ) ?></option>
            <option value="processing"><?= __( 'กำลังดำเนินการ', 'jaonaichan' ) ?></option>
            <option value="waiting-transfer"><?= __( 'รอโอนเงิน', 'jaonaichan' ) ?></option>
            <option value="pending-payment-1"><?= __( 'รอชำระบิล 1', 'jaonaichan' ) ?></option>
            <option value="pending-payment-2"><?= __( 'รอชำระบิล 2', 'jaonaichan' ) ?></option>
            <option value="wait-verify-1"><?= __( 'รอตรวจสอบ (1)', 'jaonaichan' ) ?></option>
            <option value="wait-verify-2"><?= __( 'รอตรวจสอบ (2)', 'jaonaichan' ) ?></option>
            <option value="paid-1"><?= __( 'ชำระแล้ว (บิล 1)', 'jaonaichan' ) ?></option>
            <option value="paid-2"><?= __( 'ชำระแล้ว (บิล 2)', 'jaonaichan' ) ?></option>
            <option value="on-hold"><?= __( 'ระงับไว้', 'jaonaichan' ) ?></option>
            <option value="completed"><?= __( 'สำเร็จแล้ว', 'jaonaichan' ) ?></option>
            <option value="cancelled"><?= __( 'ยกเลิก', 'jaonaichan' ) ?></option>
            <option value="refunded"><?= __( 'คืนเงิน', 'jaonaichan' ) ?></option>
        </select>
    </div>

    <!-- Loading skeleton -->
    <template x-if="status === 'loading'">
        <div class="animate-pulse space-y-3">
            <template x-for="i in [1,2,3,4,5]" :key="i">
                <div class="h-20 rounded-2xl bg-gray-200 dark:bg-gray-700"></div>
            </template>
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

    <!-- Order list -->
    <template x-if="status === 'ready'">
        <div class="space-y-3">

            <!-- Empty state -->
            <template x-if="orders.length === 0">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-16 text-center">
                    <svg class="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                        <?= __( 'ไม่พบคำสั่งซื้อ', 'jaonaichan' ) ?>
                    </p>
                </div>
            </template>

            <!-- Order cards -->
            <template x-for="order in orders" :key="order.id">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 sm:p-5">

                    <div class="flex items-start justify-between gap-3">

                        <!-- Left: order info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-bold text-gray-800 dark:text-white" x-text="'คำสั่งซื้อ #' + order.number"></span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                      :style="statusBadge(order.status)"
                                      x-text="statusLabel(order.status)">
                                </span>
                            </div>

                            <!-- Date + payment -->
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                <span x-text="fmtDate(order.date)"></span>
                                <template x-if="order.payment_method">
                                    <span> · <span x-html="order.payment_method"></span></span>
                                </template>
                            </p>

                            <!-- Products -->
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-2 leading-snug" x-text="orderProductLine(order)"></p>
                        </div>

                        <!-- Right: total + link -->
                        <div class="flex-shrink-0 text-right">
                            <p class="text-base font-extrabold text-gray-900 dark:text-white" x-text="fmtMoney(displayAmount(order))"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="order.item_count + ' รายการ'"></p>
                            <a
                                :href="'/thank-you-slave/?wcf-order=' + order.id"
                                class="mt-2 inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                style="color:#ec4899;"
                            >
                                <?= __( 'รายละเอียด', 'jaonaichan' ) ?>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Pagination -->
            <template x-if="pagination.total_pages > 1">
                <div class="flex items-center justify-between pt-2">
                    <button
                        @click="load(pagination.page - 1)"
                        :disabled="pagination.page <= 1"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-600 dark:text-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        <?= __( 'ก่อนหน้า', 'jaonaichan' ) ?>
                    </button>

                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        <?= __( 'หน้า', 'jaonaichan' ) ?>
                        <span x-text="pagination.page" class="font-semibold text-gray-800 dark:text-white"></span>
                        /
                        <span x-text="pagination.total_pages"></span>
                    </span>

                    <button
                        @click="load(pagination.page + 1)"
                        :disabled="pagination.page >= pagination.total_pages"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-600 dark:text-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <?= __( 'ถัดไป', 'jaonaichan' ) ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </template>

        </div>
    </template>

</div>

<script>
function ordersPage(apiUrl, nonce) {
    return {
        status:       'loading',
        orders:       [],
        pagination:   { page: 1, per_page: 10, total: 0, total_pages: 1 },
        filterStatus: 'any',
        errorMsg:     '',

        async load(page = 1) {
            this.status = 'loading';
            try {
                const params = new URLSearchParams({
                    page,
                    per_page: 10,
                    status:   this.filterStatus,
                });
                const res  = await fetch(`${apiUrl}?${params}`, { headers: { 'X-WP-Nonce': nonce }, cache: 'no-store' });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'โหลดไม่สำเร็จ');
                this.orders     = data.data;
                this.pagination = data.pagination;
                this.status     = 'ready';
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
            };
            return m[s] || 'background:#f3f4f6;color:#374151';
        },

        orderProductLine(order) {
            const names = order.product_names.join(', ');
            return order.more_items > 0 ? `${names} +${order.more_items} รายการ` : names;
        },

        displayAmount(order) {
            const bill1 = ['pending-payment-1', 'wait-verify-1', 'paid-1'];
            const bill2 = ['pending-payment-2', 'wait-verify-2', 'paid-2'];
            if (bill1.includes(order.status)) return order.bill1?.amount || order.total;
            if (bill2.includes(order.status)) return order.bill2?.amount || order.total;
            return order.total;
        },
    };
}
</script>
