<?php
/**
 * Spinner Component
 * @param bool   $show      แสดง/ซ่อน (default: false)
 * @param string $size      ขนาด dot เช่น 'size-2', 'size-3', 'size-4'
 * @param string $color     สี เช่น 'bg-indigo-600', 'bg-gray-400'
 * @param string $class     class เพิ่มเติม
 */
$show  = $show  ?? false;
$size  = $size  ?? '!size-3';
$color = $color ?? '!bg-indigo-600 dark:!bg-indigo-300';
$class = $class ?? '';
?>

<!-- <div
    x-show="<?= esc_attr($show) ?>"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm <?= esc_attr($class) ?>"
>
    <span class="<?= esc_attr($size) ?> !animate-ping !rounded-full <?= esc_attr($color) ?>"></span>
    <span class="<?= esc_attr($size) ?> !animate-ping !rounded-full <?= esc_attr($color) ?> ![animation-delay:0.2s]"></span>
    <span class="<?= esc_attr($size) ?> !animate-ping !rounded-full <?= esc_attr($color) ?> ![animation-delay:0.4s]"></span>
</div> -->

<div x-show="<?= esc_attr($show) ?>" class="inline-flex items-center gap-3">
    <svg class="size-6 animate-spin text-indigo-600 dark:text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" data-darkreader-inline-stroke="" style="--darkreader-inline-stroke: currentColor;"></circle>

        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    <p class="font-medium text-gray-700 dark:text-gray-200">Loading...</p>
</div>