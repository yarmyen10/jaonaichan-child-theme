<?php
/**
 * Spinner Component
 * @param bool   $show      แสดง/ซ่อน (default: false)
 * @param string $size      ขนาด dot เช่น 'size-2', 'size-3', 'size-4'
 * @param string $color     สี เช่น 'bg-indigo-600', 'bg-gray-400'
 * @param string $class     class เพิ่มเติม
 */
$show  = $show  ?? false;
$color = $color ?? '!bg-indigo-600 dark:!bg-indigo-300';
// $size  = $size  ?? '!size-3';
// $class = $class ?? '';
?>

<div
    x-show="loading"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center bg-white/80"
>
    <div class="w-8 h-8 rounded-full border-4 border-gray-200 border-t-<?= $color ?? 'indigo-600' ?> animate-spin"></div>
</div>
