<?php
$spinner_color = $color ?? '#4f46e5'; // indigo-600
?>

<div
    x-show="loading"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center bg-white/80"
>
    <div
        class="w-8 h-8 rounded-full border-4 border-gray-200 animate-spin"
        style="border-top-color: <?= $spinner_color ?>;"
    ></div>
</div>