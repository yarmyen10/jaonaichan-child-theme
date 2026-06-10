<?php
/**
 * Dashboard partial — Header (bigboss theme)
 *
 * Parent context: $user, $current_page, $base_url
 * Alpine state: sidebarToggle, darkMode
 */

$page_titles = [
    'overview' => __( 'ภาพรวม', 'jaonaichan' ),
    'orders'   => __( 'คำสั่งซื้อ', 'jaonaichan' ),
    'profile'  => __( 'โปรไฟล์', 'jaonaichan' ),
];
$page_title = $page_titles[ $current_page ] ?? '';
?>

<header class="sticky top-0 z-[9999] flex w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
<div class="flex items-center justify-between w-full px-4 h-[60px] lg:px-6">

    <!-- Left -->
    <div class="flex items-center gap-3">

        <!-- Hamburger (mobile) -->
        <button
            @click="sidebarToggle = !sidebarToggle"
            class="flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors lg:hidden"
            aria-label="<?= esc_attr__( 'เปิดเมนู', 'jaonaichan' ) ?>"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <!-- Breadcrumb -->
        <nav class="hidden sm:flex items-center gap-2 text-sm" aria-label="breadcrumb">
            <a href="<?= esc_url( home_url('/') ) ?>" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <?= __( 'หน้าหลัก', 'jaonaichan' ) ?>
            </a>
            <svg class="w-4 h-4 text-gray-300 dark:text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="font-medium text-gray-700 dark:text-gray-200"><?= esc_html( $page_title ) ?></span>
        </nav>

        <!-- Mobile: page title only -->
        <span class="sm:hidden text-sm font-semibold text-gray-800 dark:text-white"><?= esc_html( $page_title ) ?></span>

    </div>

    <!-- Right -->
    <div class="flex items-center gap-2">

        <!-- Dark mode toggle -->
        <button
            @click="darkMode = !darkMode"
            :aria-label="darkMode ? '<?= esc_js( __( 'โหมดสว่าง', 'jaonaichan' ) ) ?>' : '<?= esc_js( __( 'โหมดมืด', 'jaonaichan' ) ) ?>'"
            class="flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors"
        >
            <svg x-show="darkMode" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <svg x-show="!darkMode" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
        </button>

        <!-- User menu -->
        <div class="relative" x-data="{ userMenuOpen: false }">
            <button
                @click="userMenuOpen = !userMenuOpen"
                @keydown.escape.window="userMenuOpen = false"
                class="flex items-center gap-2 pl-1 pr-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors"
            >
                <img
                    src="<?= esc_url( get_avatar_url( $user->ID, [ 'size' => 32 ] ) ) ?>"
                    alt="<?= esc_attr( $user->display_name ) ?>"
                    class="w-8 h-8 rounded-full object-cover"
                >
                <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-300 max-w-[120px] truncate">
                    <?= esc_html( $user->display_name ?: $user->user_login ) ?>
                </span>
                <svg class="hidden sm:block w-4 h-4 text-gray-400 transition-transform duration-150" :class="userMenuOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <!-- Dropdown -->
            <div
                x-show="userMenuOpen"
                @click.outside="userMenuOpen = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 mt-2 w-52 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-[0px_4px_8px_rgba(16,24,40,0.08)] py-1 z-50"
                x-cloak
            >
                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-800">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                        <?= esc_html( $user->display_name ?: $user->user_login ) ?>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                        <?= esc_html( $user->user_email ) ?>
                    </p>
                </div>

                <?php
                $menu_items = [
                    [
                        'href'  => add_query_arg( 'tab', 'profile', $base_url ),
                        'label' => __( 'โปรไฟล์', 'jaonaichan' ),
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                    ],
                ];
                if ( current_user_can( 'manage_options' ) ) {
                    $menu_items[] = [
                        'href'  => get_edit_user_link( $user->ID ),
                        'label' => __( 'ตั้งค่าบัญชี', 'jaonaichan' ),
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                    ];
                }
                foreach ( $menu_items as $item ) : ?>
                <a
                    href="<?= esc_url( $item['href'] ) ?>"
                    class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors"
                >
                    <svg class="w-4 h-4 flex-shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?= $item['icon'] ?>
                    </svg>
                    <?= esc_html( $item['label'] ) ?>
                </a>
                <?php endforeach; ?>

                <div class="my-1 border-t border-gray-100 dark:border-gray-800"></div>

                <a
                    href="<?= esc_url( wp_logout_url( home_url('/') ) ) ?>"
                    class="flex items-center gap-3 px-4 py-2 text-sm text-red-500 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors"
                >
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <?= __( 'ออกจากระบบ', 'jaonaichan' ) ?>
                </a>
            </div>
        </div>

    </div>
</div>
</header>
