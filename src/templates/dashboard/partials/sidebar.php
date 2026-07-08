<?php
/**
 * Dashboard partial — Sidebar (bigboss theme)
 *
 * Parent context: $user, $base_url, $current_page
 * Alpine state: sidebarToggle, darkMode
 */

$nav_items = [
    'overview' => [
        'label' => __( 'ภาพรวม', 'jaonaichan' ),
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
    ],
    'orders' => [
        'label' => __( 'คำสั่งซื้อ', 'jaonaichan' ),
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>',
    ],
    'profile' => [
        'label' => __( 'โปรไฟล์', 'jaonaichan' ),
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
    ],
];
?>

<!-- Mobile overlay -->
<div
    x-show="sidebarToggle"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-end="opacity-0"
    @click="sidebarToggle = false"
    class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
    x-cloak
></div>

<!-- Sidebar -->
<aside
    :class="sidebarToggle ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    style="height:100vh;height:100dvh"
    class="fixed top-0 left-0 z-50 flex w-[290px] flex-col bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 transition-transform duration-300 ease-in-out lg:static lg:z-auto"
>

    <!-- Logo -->
    <div class="flex items-center gap-3 px-5 py-[18px] border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-center w-8 h-8 rounded-lg text-white text-base flex-shrink-0" style="background:#ec4899;">
            🐾
        </div>
        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">
            <?php bloginfo('name'); ?>
        </span>
        <button
            @click="sidebarToggle = false"
            class="ml-auto flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 lg:hidden"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Nav -->
    <div class="flex-1 overflow-y-auto no-scrollbar px-5 py-5">

        <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-600">
            <?= __( 'เมนูหลัก', 'jaonaichan' ) ?>
        </h3>

        <ul class="space-y-1">
            <?php foreach ( $nav_items as $key => $item ) :
                $is_active = ( $current_page === $key );
                $href      = esc_url( add_query_arg( 'tab', $key, $base_url ) );
            ?>
            <li>
                <a
                    href="<?= $href ?>"
                    class="relative flex items-center w-full gap-3 px-3 py-2 font-medium rounded-lg text-sm <?=
                        $is_active
                            ? 'bg-[#fdf2f8] text-[#ec4899] dark:bg-[#ec4899]/[.12] dark:text-[#f9a8d4]'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5'
                    ?>"
                >
                    <svg
                        class="w-6 h-6 flex-shrink-0 <?= $is_active ? 'text-[#ec4899] dark:text-[#f9a8d4]' : 'text-gray-500 dark:text-gray-400' ?>"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <?= $item['icon'] ?>
                    </svg>
                    <?= esc_html( $item['label'] ) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="my-5 border-t border-gray-200 dark:border-gray-800"></div>

        <a
            href="<?= esc_url( wc_get_page_permalink('shop') ?: home_url('/shop/') ) ?>"
            class="relative flex items-center w-full gap-3 px-3 py-2 font-medium rounded-lg text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
        >
            <svg class="w-6 h-6 flex-shrink-0 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <?= __( 'กลับไปร้านค้า', 'jaonaichan' ) ?>
        </a>

    </div>

    <!-- User (bottom) -->
    <div class="border-t border-gray-200 dark:border-gray-800 px-5 py-4">
        <a href="<?= esc_url( add_query_arg( 'tab', 'profile', $base_url ) ) ?>" class="flex items-center gap-3 group">
            <img
                src="<?= esc_url( get_avatar_url( $user->ID, [ 'size' => 40 ] ) ) ?>"
                alt="<?= esc_attr( $user->display_name ) ?>"
                class="w-9 h-9 rounded-full object-cover flex-shrink-0"
            >
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate group-hover:text-[#ec4899] dark:group-hover:text-[#f9a8d4] transition-colors">
                    <?= esc_html( $user->display_name ?: $user->user_login ) ?>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    <?= esc_html( $user->user_email ) ?>
                </p>
            </div>
        </a>
    </div>

</aside>
