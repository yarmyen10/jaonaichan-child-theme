<?php
/**
 * Dashboard partial — Profile
 * Loaded by page-dashboard.php (?page=profile) or page-profile.php
 *
 * Requires: $user, $base_url from parent template.
 * Alpine component fetches GET /bigboss-auth/v1/profile on mount,
 * then PATCHes on save. WP REST nonce is injected server-side.
 */

$rest_nonce  = wp_create_nonce( 'wp_rest' );
$profile_url = esc_js( rest_url( 'bigboss-auth/v1/profile' ) );
?>

<div
    x-data="profilePage('<?= $profile_url ?>', '<?= esc_js( $rest_nonce ) ?>')"
    x-init="load()"
    class="space-y-6"
>
    <!-- Page heading -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                <?= __( 'โปรไฟล์', 'jaonaichan' ) ?>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                <?= __( 'ข้อมูลส่วนตัวและการตั้งค่าบัญชีของคุณ', 'jaonaichan' ) ?>
            </p>
        </div>
    </div>

    <!-- ── Loading skeleton ── -->
    <template x-if="status === 'loading'">
        <div class="animate-pulse space-y-4">
            <div class="h-32 rounded-2xl bg-gray-200 dark:bg-gray-700"></div>
            <div class="h-64 rounded-2xl bg-gray-200 dark:bg-gray-700"></div>
        </div>
    </template>

    <!-- ── Error state ── -->
    <template x-if="status === 'error'">
        <div class="rounded-2xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-6 text-center">
            <p class="text-red-600 dark:text-red-400 font-medium" x-text="errorMsg"></p>
            <button @click="load()" class="mt-3 text-sm underline text-red-500 hover:text-red-700">
                <?= __( 'ลองใหม่', 'jaonaichan' ) ?>
            </button>
        </div>
    </template>

    <!-- ── Main content ── -->
    <template x-if="status === 'ready' || status === 'saving'">
        <div class="space-y-6">

            <!-- Profile card (read-only info) -->
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">

                <!-- Pink header strip -->
                <div class="h-24 w-full" style="background: linear-gradient(90deg,#fce7f3 0%,#fdf4ff 100%);"></div>

                <div class="px-6 pb-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-end gap-4 -mt-12">

                        <!-- Avatar -->
                        <div class="relative flex-shrink-0">
                            <img
                                :src="profile.avatar_url"
                                :alt="profile.display_name"
                                class="w-24 h-24 rounded-full border-4 border-white dark:border-gray-800 object-cover"
                                style="box-shadow: 0 4px 16px rgba(236,72,153,.25);"
                            >
                            <span class="absolute bottom-1 right-1 w-4 h-4 rounded-full bg-green-400 border-2 border-white dark:border-gray-800"></span>
                        </div>

                        <!-- Name + meta -->
                        <div class="flex-1 min-w-0 mt-2 sm:mt-0">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white truncate" x-text="profile.display_name || profile.username"></h3>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-sm text-gray-500 dark:text-gray-400">
                                <span x-text="'@' + profile.username"></span>
                                <span class="hidden sm:inline">·</span>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                    style="background:#fce7f3; color:#db2777;"
                                    x-text="roleLabelTh(profile.role)"
                                ></span>
                            </div>
                        </div>
                    </div>

                    <!-- Info chips -->
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                            <svg class="w-4 h-4 flex-shrink-0" style="color:#ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span x-text="profile.email" class="truncate"></span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                            <svg class="w-4 h-4 flex-shrink-0" style="color:#ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span><?= __( 'สมาชิกตั้งแต่', 'jaonaichan' ) ?> <span x-text="fmtDate(profile.registered_at)"></span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit form -->
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">

                <h4 class="text-base font-semibold text-gray-800 dark:text-white mb-5">
                    <?= __( 'แก้ไขข้อมูลส่วนตัว', 'jaonaichan' ) ?>
                </h4>

                <!-- Toast -->
                <div
                    x-show="toast.visible"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    :class="toast.type === 'success'
                        ? 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/20 dark:border-green-700 dark:text-green-300'
                        : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300'"
                    class="rounded-xl border px-4 py-3 text-sm font-medium mb-5 flex items-center gap-2"
                >
                    <span x-text="toast.type === 'success' ? '✓' : '✕'" class="font-bold text-base"></span>
                    <span x-text="toast.msg"></span>
                </div>

                <form @submit.prevent="save()" class="space-y-4">

                    <!-- first_name + last_name -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                                <?= __( 'ชื่อ', 'jaonaichan' ) ?>
                            </label>
                            <input
                                type="text"
                                x-model="form.first_name"
                                :disabled="status === 'saving'"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-transparent disabled:opacity-60"
                                style="--tw-ring-color: #ec4899;"
                                placeholder="<?= esc_attr__( 'ชื่อจริง', 'jaonaichan' ) ?>"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                                <?= __( 'นามสกุล', 'jaonaichan' ) ?>
                            </label>
                            <input
                                type="text"
                                x-model="form.last_name"
                                :disabled="status === 'saving'"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-transparent disabled:opacity-60"
                                style="--tw-ring-color: #ec4899;"
                                placeholder="<?= esc_attr__( 'นามสกุล', 'jaonaichan' ) ?>"
                            >
                        </div>
                    </div>

                    <!-- display_name -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                            <?= __( 'ชื่อที่แสดง', 'jaonaichan' ) ?>
                        </label>
                        <input
                            type="text"
                            x-model="form.display_name"
                            :disabled="status === 'saving'"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-transparent disabled:opacity-60"
                            style="--tw-ring-color: #ec4899;"
                            placeholder="<?= esc_attr__( 'ชื่อที่ปรากฏในระบบ', 'jaonaichan' ) ?>"
                        >
                    </div>

                    <!-- nickname -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                            <?= __( 'ชื่อเล่น', 'jaonaichan' ) ?>
                        </label>
                        <input
                            type="text"
                            x-model="form.nickname"
                            :disabled="status === 'saving'"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-transparent disabled:opacity-60"
                            style="--tw-ring-color: #ec4899;"
                            placeholder="<?= esc_attr__( 'ชื่อเล่น', 'jaonaichan' ) ?>"
                        >
                    </div>

                    <!-- description / bio -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                            <?= __( 'เกี่ยวกับฉัน', 'jaonaichan' ) ?>
                        </label>
                        <textarea
                            x-model="form.description"
                            :disabled="status === 'saving'"
                            rows="3"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-transparent disabled:opacity-60 resize-none"
                            style="--tw-ring-color: #ec4899;"
                            placeholder="<?= esc_attr__( 'เขียนอะไรสักเล็กน้อยเกี่ยวกับตัวคุณ...', 'jaonaichan' ) ?>"
                        ></textarea>
                    </div>

                    <!-- read-only fields (info only) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                                <?= __( 'Username', 'jaonaichan' ) ?>
                            </label>
                            <input
                                type="text"
                                :value="profile.username"
                                readonly
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-750 text-gray-400 dark:text-gray-500 px-4 py-2.5 text-sm cursor-not-allowed"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">
                                <?= __( 'อีเมล', 'jaonaichan' ) ?>
                            </label>
                            <input
                                type="email"
                                :value="profile.email"
                                readonly
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-750 text-gray-400 dark:text-gray-500 px-4 py-2.5 text-sm cursor-not-allowed"
                            >
                        </div>
                    </div>

                    <!-- Save button -->
                    <div class="flex items-center justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="status === 'saving'"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all duration-150 disabled:opacity-70 disabled:cursor-wait"
                            style="background:#ec4899; box-shadow: 0 4px 14px rgba(236,72,153,.35);"
                            onmouseover="if(!this.disabled) this.style.background='#db2777'"
                            onmouseout="this.style.background='#ec4899'"
                        >
                            <!-- Spinner -->
                            <svg x-show="status === 'saving'" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"></circle>
                                <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" style="opacity:.75"></path>
                            </svg>
                            <span x-text="status === 'saving' ? '<?= esc_js( __( 'กำลังบันทึก...', 'jaonaichan' ) ) ?>' : '<?= esc_js( __( 'บันทึกการเปลี่ยนแปลง', 'jaonaichan' ) ) ?>'"></span>
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </template>

</div>

<!-- ── Social account linking (server-side, always visible) ── -->
<?php if ( is_user_logged_in() ) :
    $current_user_id = get_current_user_id();

    $providers = [
        'facebook' => [
            'label'     => 'Facebook',
            'meta_keys' => [ 'mo_social_login_facebook_id', 'mo_social_login_customer_key_facebook' ],
            'color'     => '#1877F2',
            'bg'        => '#e7f0fd',
            'icon'      => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        ],
        'google' => [
            'label'     => 'Google',
            'meta_keys' => [ 'mo_social_login_google_id', 'mo_social_login_customer_key_google' ],
            'color'     => '#EA4335',
            'bg'        => '#fce8e6',
            'icon'      => '<svg viewBox="0 0 24 24" class="w-5 h-5"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>',
        ],
        'line' => [
            'label'     => 'LINE',
            'meta_keys' => [ '_line_user_id', 'mo_social_login_line_id', 'mo_social_login_customer_key_line' ],
            'color'     => '#06C755',
            'bg'        => '#e0f7ea',
            'icon'      => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.630 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>',
        ],
    ];

    $profile_url_raw = add_query_arg( 'tab', 'profile', $base_url );

    foreach ( $providers as $key => &$p ) {
        $p['linked']      = false;
        $p['connect_url'] = '';
        foreach ( $p['meta_keys'] as $mk ) {
            if ( ! empty( get_user_meta( $current_user_id, $mk, true ) ) ) {
                $p['linked'] = true;
                break;
            }
        }
        if ( ! $p['linked'] && $key === 'line' && class_exists( 'Line_Login' ) ) {
            $p['connect_url'] = Line_Login::make_auth_url( $profile_url_raw );
        }
    }
    unset( $p );
?>
<div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden" style="margin-top:24px;">

    <!-- Card header -->
    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700/70 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#fce7f3;">
            <svg class="w-5 h-5" style="color:#ec4899;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
        </div>
        <div>
            <h4 class="text-base font-semibold text-gray-800 dark:text-white leading-tight">
                <?= __( 'เชื่อมบัญชี Social', 'jaonaichan' ) ?>
            </h4>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                <?= __( 'เข้าสู่ระบบด้วย Facebook, Google หรือ LINE ได้สะดวกขึ้น', 'jaonaichan' ) ?>
            </p>
        </div>
    </div>

    <!-- Provider rows -->
    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
        <?php foreach ( $providers as $p ) : ?>
        <div class="flex items-center gap-4 px-6 py-4">

            <!-- Icon bubble -->
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background:<?= esc_attr( $p['bg'] ) ?>; color:<?= esc_attr( $p['color'] ) ?>">
                <?= $p['icon'] ?>
            </div>

            <!-- Label -->
            <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-200">
                <?= esc_html( $p['label'] ) ?>
            </span>

            <!-- Status / action -->
            <?php if ( $p['linked'] ) : ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full"
                      style="background:#dcfce7; color:#15803d;">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <?= __( 'เชื่อมต่อแล้ว', 'jaonaichan' ) ?>
                </span>
            <?php elseif ( $p['connect_url'] ) : ?>
                <a
                    href="<?= esc_url( $p['connect_url'] ) ?>"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full text-white transition-opacity hover:opacity-80"
                    style="background:<?= esc_attr( $p['color'] ) ?>;"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <?= __( 'เชื่อมต่อ', 'jaonaichan' ) ?>
                </a>
            <?php else : ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full border"
                      style="border-color:#e5e7eb; color:#9ca3af;">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <?= __( 'ยังไม่ได้เชื่อม', 'jaonaichan' ) ?>
                </span>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>


</div>
<?php endif; ?>

<script>
function profilePage(apiUrl, nonce) {
    return {
        status:   'loading',  // loading | ready | saving | error
        profile:  {},
        form:     { display_name: '', first_name: '', last_name: '', nickname: '', description: '' },
        toast:    { visible: false, type: 'success', msg: '' },
        errorMsg: '',

        async load() {
            this.status = 'loading';
            try {
                const res  = await fetch(apiUrl, { headers: { 'X-WP-Nonce': nonce } });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'โหลดข้อมูลไม่สำเร็จ');
                this.profile = data;
                this.form = {
                    display_name: data.display_name || '',
                    first_name:   data.first_name   || '',
                    last_name:    data.last_name    || '',
                    nickname:     data.nickname     || '',
                    description:  data.description  || '',
                };
                this.status = 'ready';
            } catch (e) {
                this.errorMsg = e.message;
                this.status   = 'error';
            }
        },

        async save() {
            this.status = 'saving';
            this.hideToast();
            try {
                const res  = await fetch(apiUrl, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body:    JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'บันทึกไม่สำเร็จ');
                this.profile = data.data;
                this.showToast('success', data.message || 'บันทึกสำเร็จ');
            } catch (e) {
                this.showToast('error', e.message);
            } finally {
                this.status = 'ready';
            }
        },

        showToast(type, msg) {
            this.toast = { visible: true, type, msg };
            setTimeout(() => this.hideToast(), 4000);
        },

        hideToast() {
            this.toast.visible = false;
        },

        roleLabelTh(role) {
            const map = {
                administrator: 'ผู้ดูแลระบบ',
                editor:        'บรรณาธิการ',
                author:        'นักเขียน',
                contributor:   'ผู้มีส่วนร่วม',
                subscriber:    'สมาชิก',
                customer:      'ลูกค้า',
                shop_manager:  'ผู้จัดการร้าน',
            };
            return map[role] || role || '';
        },

        fmtDate(str) {
            if (!str) return '';
            const d = new Date(str);
            return d.toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
        },
    };
}
</script>
