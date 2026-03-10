<?php
/**
 * Template Name: Custom Thank You Page
 * This is a custom template to display a thank you page.
 */

get_header(); // เรียกใช้ header ของ WordPress
?>

<!-- <div x-data="{ open: false }" class="border rounded p-4 max-w-md">
  
  <button 
    @click="open = !open" 
    class="flex justify-between w-full font-bold"
  >
    คลิกเพื่อเปิด/ปิด
    <span x-text="open ? '▲' : '▼'"></span>
  </button>

  <div x-show="open" class="mt-4 text-gray-600">
    เนื้อหาที่ซ่อนอยู่ข้างในครับ 🎉
  </div>

</div>


<div class="bg-red-500 text-white p-4">
  ทดสอบ Tailwind
</div> -->

<div class="w-full px-4">
    <section>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-center md:gap-8">
            <div>
                <div class="max-w-prose md:max-w-none">
                <h2 class="text-2xl font-semibold text-gray-900 sm:text-3xl">
                    Lorem ipsum dolor sit amet consectetur adipisicing elit.
                </h2>

                <p class="mt-4 text-pretty text-gray-700">
                    Lorem ipsum dolor sit amet consectetur adipisicing elit. Tenetur doloremque saepe
                    architecto maiores repudiandae amet perferendis repellendus, reprehenderit voluptas
                    sequi.
                </p>
                </div>
            </div>

            <div>
                <img src="https://images.unsplash.com/photo-1731690415686-e68f78e2b5bd?auto=format&amp;fit=crop&amp;q=80&amp;w=1160" class="rounded" alt="">
            </div>
            </div>
        </div>
    </section>
</div>