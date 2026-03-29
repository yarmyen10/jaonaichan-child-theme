<?php
$spinner_color = $color ?? '#FB5FAB';
?>

<div
    x-show="loading"
    x-transition.opacity
    class="fixed inset-0 z-9999 flex items-center justify-center bg-white/80"
>
    <div class="inline-flex items-center gap-3">
        <svg class="size-6 animate-spin" style="color: <?= $spinner_color ?>;"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10"
                    stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="font-medium !text-gray-700 !dark:text-gray-500 !mb-0">Loading...</p>
    </div>
</div>