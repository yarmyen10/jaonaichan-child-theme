<?php
/**
 * Template Name: Dashboard
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$user         = wp_get_current_user();
$base_url     = get_permalink();
$current_page = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
$assets_url   = get_stylesheet_directory_uri() . '/src/templates/dashboard/assets';
$allowed      = ['overview', 'orders', 'profile'];
?>
<!DOCTYPE html>
<html lang="<?php language_attributes(); ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard — <?php bloginfo('name'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= get_stylesheet_directory_uri() ?>/assets/css/tailwind.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body
    style="font-family: 'Outfit', sans-serif;"
    x-data="{
        page: '<?= esc_js($current_page) ?>',
        loaded: true,
        darkMode: false,
        stickyMenu: false,
        sidebarToggle: false,
        scrollTop: false
    }"
    x-init="
        darkMode = JSON.parse(localStorage.getItem('darkMode'));
        $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))
    "
    :class="darkMode ? 'dark bg-gray-900' : 'bg-gray-50'"
>

<div class="flex overflow-hidden" style="height:100vh;height:100dvh">

    <!-- Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <div class="relative flex flex-col flex-1 overflow-x-hidden overflow-y-auto">

        <!-- Header -->
        <?php include __DIR__ . '/partials/header.php'; ?>

        <!-- Content -->
        <main>
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                <?php
                if ( in_array($current_page, $allowed) ) {
                    include __DIR__ . '/partials/' . $current_page . '.php';
                }
                ?>
            </div>
        </main>

    </div>
</div>

</body>
</html>