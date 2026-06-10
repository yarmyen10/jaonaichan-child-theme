<?php
/**
 * Template Name: Profile
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$user         = wp_get_current_user();
$base_url     = get_permalink();
$current_page = 'profile';
?>
<!DOCTYPE html>
<html lang="<?php language_attributes(); ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= esc_html__( 'โปรไฟล์', 'jaonaichan' ) ?> — <?php bloginfo('name'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= get_stylesheet_directory_uri() ?>/assets/css/tailwind.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body
    style="font-family: 'Outfit', sans-serif;"
    x-data="{
        darkMode: false,
        stickyMenu: false,
        sidebarToggle: false
    }"
    x-init="
        darkMode = JSON.parse(localStorage.getItem('darkMode'));
        $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))
    "
    :class="darkMode ? 'dark bg-gray-900' : 'bg-gray-50'"
>

<div class="flex h-screen overflow-hidden">

    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <div class="relative flex flex-col flex-1 overflow-x-hidden overflow-y-auto">

        <?php include __DIR__ . '/partials/header.php'; ?>

        <main>
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                <?php include __DIR__ . '/partials/profile.php'; ?>
            </div>
        </main>

    </div>
</div>

</body>
</html>
