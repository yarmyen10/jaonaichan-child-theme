<?php
/**
 * Template Name: Jaonaichan Contact
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

.jn-contact-card {
    transition: all 0.3s ease;
}
.jn-contact-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(236,72,153,0.15);
}
</style>

<div class="jn-page-wrap w-full min-h-[calc(100vh-80px)] font-sans relative z-10 breakout-desktop">
  <!-- Full Width Background Container -->
  <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[100vw] -z-10 overflow-hidden bg-gradient-to-br from-pink-50 via-white to-purple-50">
    <!-- Decorative background blobs -->
    <div class="absolute top-0 left-0 w-96 h-96 bg-[#FB5FAB] opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl transform -translate-x-1/2 -translate-y-1/2 animate-pulse"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-400 opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl transform translate-x-1/2 translate-y-1/2 animate-pulse" style="animation-delay: 2s;"></div>
  </div>

  <section class="relative overflow-hidden pt-[180px] pb-16 md:pt-[220px] md:pb-20">
    <div class="mx-auto max-w-5xl px-6 relative" style="z-index:1;">
      <div class="text-center mb-16">
        <span class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-1.5 rounded-full mb-5" style="background:#fce7f3; color:#db2777;">
          ติดต่อเรา 💌
        </span>
        <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-4" style="color:#1f2937;">
          พร้อมให้บริการ<br>
          <span style="color:#ec4899;">ทาสแมวทุกคน</span>
        </h1>
        <p class="text-lg max-w-md mx-auto" style="color:#6b7280; text-align: center !important;">
          มีคำถาม ข้อสงสัย หรือต้องการคำแนะนำในการเลือกซื้อสินค้า? ทักหาเราได้เลย!
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
        
        <!-- Line OA -->
        <a href="https://line.me/R/ti/p/@jaonaichan" target="_blank" rel="noopener" class="jn-contact-card bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 text-center border border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)] block relative overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-[#00B900]/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
          <div class="w-16 h-16 mx-auto mb-5 bg-[#00B900] text-white rounded-full flex items-center justify-center text-3xl shadow-lg relative z-10">
            📱
          </div>
          <h3 class="text-xl font-bold mb-2 relative z-10" style="color:#1f2937;">LINE Official</h3>
          <p class="mb-5 text-sm relative z-10" style="color:#6b7280;">ทักแชทสอบถาม ปรึกษา สั่งซื้อได้ตลอด</p>
          <span class="inline-block px-5 py-2 rounded-full font-semibold text-sm relative z-10" style="background:#e0f7e0; color:#00B900;">@jaonaichan</span>
        </a>

        <!-- Facebook -->
        <a href="https://facebook.com/jaonaichan" target="_blank" rel="noopener" class="jn-contact-card bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 text-center border border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)] block relative overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-[#1877F2]/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
          <div class="w-16 h-16 mx-auto mb-5 bg-[#1877F2] text-white rounded-full flex items-center justify-center text-3xl shadow-lg relative z-10">
            💬
          </div>
          <h3 class="text-xl font-bold mb-2 relative z-10" style="color:#1f2937;">Facebook</h3>
          <p class="mb-5 text-sm relative z-10" style="color:#6b7280;">ติดตามโปรโมชั่นและอัพเดทใหม่ๆ</p>
          <span class="inline-block px-5 py-2 rounded-full font-semibold text-sm relative z-10" style="background:#e0edff; color:#1877F2;">Jaonaichan</span>
        </a>

        <!-- Phone / Email -->
        <div class="jn-contact-card bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 text-center border border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)] block md:col-span-2 lg:col-span-1 relative overflow-hidden">
          <div class="w-16 h-16 mx-auto mb-5 bg-pink-500 text-white rounded-full flex items-center justify-center text-3xl shadow-lg relative z-10">
            📞
          </div>
          <h3 class="text-xl font-bold mb-2 relative z-10" style="color:#1f2937;">เบอร์ติดต่อ</h3>
          <p class="mb-5 text-sm relative z-10" style="color:#6b7280;">จันทร์ - เสาร์ (09:00 - 18:00)</p>
          <span class="inline-block px-5 py-2 rounded-full font-semibold text-sm mb-2 relative z-10" style="background:#fce7f3; color:#ec4899;">080-XXX-XXXX</span>
        </div>

      </div>
      
      <!-- WordPress Editor Content (if any) -->
      <!-- <div class="mt-12"> -->
        <!-- <?php the_content(); ?> -->
      <!-- </div> -->

      <!-- FAQ / Note -->
      <div class="mt-16 text-center">
        <p class="text-sm px-4 py-3 inline-block rounded-2xl bg-white/50 backdrop-blur-sm" style="color:#9ca3af; text-align: center !important;">
          * หากตอบกลับล่าช้า แอดมินอาจกำลังให้อาหารเจ้านายอยู่ ต้องขออภัยด้วยนะคะ 🙏
        </p>
      </div>

    </div>
  </section>
</div>

<?php get_footer(); ?>
