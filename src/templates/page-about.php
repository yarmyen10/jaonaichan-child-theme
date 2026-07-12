<?php
/**
 * Template Name: Jaonaichan About
 */

// Force Astra: no sidebar, no page title
add_filter( 'astra_get_option', function ( $val, $option ) {
	if ( in_array( $option, [ 'site-sidebar-layout', 'single-page-sidebar-layout' ], true ) ) {
		return 'no-sidebar';
	}
	return $val;
}, 10, 2 );

add_filter( 'astra_the_title_enabled', '__return_false' );
add_filter( 'astra_breadcrumb_enabled', '__return_false' );

get_header();

// Set up global $post so content helpers work
if ( have_posts() ) {
	the_post();
}
?>

<style>
/* Break out of Astra's content container for true full-width sections */
.jn-page-wrap {
	margin-left:  calc(50% - 50vw);
	margin-right: calc(50% - 50vw);
	width: 100vw;
	max-width: 100vw;
	overflow-x: hidden;
}
/* Remove default page padding Astra adds */
.ast-article-single, .entry-content { padding: 0 !important; margin: 0 !important; }
</style>

<div class="jn-page-wrap w-full min-h-[calc(100vh-80px)] font-sans relative z-10 breakout-desktop">
  <!-- Full Width Background Container -->
  <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[100vw] -z-10 overflow-hidden bg-gradient-to-br from-pink-50 via-white to-purple-50">
    <!-- Decorative background blobs -->
    <div class="absolute top-0 left-0 w-96 h-96 bg-[#FB5FAB] opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl transform -translate-x-1/2 -translate-y-1/2 animate-pulse"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-400 opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl transform translate-x-1/2 translate-y-1/2 animate-pulse" style="animation-delay: 2s;"></div>
  </div>

  <section class="relative overflow-hidden pt-[120px] pb-16 md:pt-[160px] md:pb-20 lg:pt-[220px]">
    <div class="mx-auto max-w-4xl px-6 relative text-center" style="z-index:1;">
      <span class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-1.5 rounded-full mb-5" style="background:#fce7f3; color:#db2777;">
        เกี่ยวกับเรา 🐈
      </span>
      <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-8" style="color:#1f2937;">
        เรื่องราวของ <span style="color:#ec4899;">Jaonaichan</span>
      </h1>
      
      <div class="bg-white/70 backdrop-blur-xl rounded-[2rem] p-8 md:p-12 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)] text-left md:text-center relative">
        <div class="absolute -top-10 -right-4 text-6xl opacity-20 transform rotate-12">🐾</div>
        <div class="absolute -bottom-6 -left-4 text-6xl opacity-20 transform -rotate-12">🐟</div>
        
        <p class="text-lg leading-relaxed mb-6" style="color:#4b5563;">
          จุดเริ่มต้นของเรามาจากความรักที่มีต่อน้องแมว เราเชื่อว่า <strong>"เจ้านาย"</strong> ทุกตัวสมควรได้รับสิ่งที่ดีที่สุด ไม่ว่าจะเป็นอาหารที่คัดสรรมาอย่างดี ของเล่นที่ปลอดภัย หรือของใช้ที่ตอบโจทย์พฤติกรรมตามธรรมชาติของพวกเขา
        </p>
        <p class="text-lg leading-relaxed mb-8" style="color:#4b5563;">
          <strong>Jaonaichan (เจ้านายฉัน)</strong> ถือกำเนิดขึ้นด้วยความตั้งใจที่จะเป็นแหล่งรวมสินค้าคุณภาพที่ทาสแมวสามารถไว้วางใจได้ เราทดลองและเลือกสรรสินค้าทุกชิ้นด้วยตัวเอง เพื่อให้แน่ใจว่าสินค้าทุกชิ้นจะสร้างความสุขและสุขภาพที่ดีให้กับเจ้านายของคุณ
        </p>
        
        <div class="mt-10 p-6 md:p-8 rounded-3xl" style="background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);">
          <h3 class="text-xl font-bold mb-5" style="color:#1f2937;">คำมั่นสัญญาของเรา ❤️</h3>
          <ul class="text-left max-w-2xl mx-auto space-y-4" style="color:#6b7280;">
            <li class="flex items-start gap-4">
              <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-white text-xs font-bold" style="background:#ec4899;">✓</span> 
              <span class="pt-0.5">คัดสรรเฉพาะสินค้าคุณภาพ ปลอดภัยต่อสุขภาพแมว 100%</span>
            </li>
            <li class="flex items-start gap-4">
              <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-white text-xs font-bold" style="background:#ec4899;">✓</span> 
              <span class="pt-0.5">บริการด้วยใจ ใส่ใจทุกรายละเอียดของลูกค้าเหมือนเป็นครอบครัวเดียวกัน</span>
            </li>
            <li class="flex items-start gap-4">
              <span class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-white text-xs font-bold" style="background:#ec4899;">✓</span> 
              <span class="pt-0.5">จัดส่งรวดเร็ว ทันใจ เพื่อให้เจ้านายของคุณไม่ต้องรอนาน</span>
            </li>
          </ul>
        </div>
      </div>
      
      <!-- WordPress Editor Content (if any) -->
      <!-- <div class="mt-10 text-left"> -->
        <!-- <?php the_content(); ?> -->
      <!-- </div> -->

    </div>
  </section>
</div>

<?php get_footer(); ?>
