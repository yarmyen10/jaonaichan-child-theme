<?php
/**
 * Spinner Component
 * @param bool   $show      แสดง/ซ่อน (default: false)
 * @param string $size      ขนาด dot เช่น 'size-2', 'size-3', 'size-4'
 * @param string $color     สี เช่น 'bg-indigo-600', 'bg-gray-400'
 * @param string $class     class เพิ่มเติม
 */
$show  = $show  ?? false;
$size  = $size  ?? 'size-3';
$color = $color ?? 'bg-indigo-600 dark:bg-indigo-300';
$class = $class ?? '';
?>

<div
    x-show="<?= esc_attr($show) ?>"
    class="flex gap-2 <?= esc_attr($class) ?>"
>
    <span class="<?= esc_attr($size) ?> animate-ping rounded-full <?= esc_attr($color) ?>"></span>
    <span class="<?= esc_attr($size) ?> animate-ping rounded-full <?= esc_attr($color) ?> [animation-delay:0.2s]"></span>
    <span class="<?= esc_attr($size) ?> animate-ping rounded-full <?= esc_attr($color) ?> [animation-delay:0.4s]"></span>
</div>