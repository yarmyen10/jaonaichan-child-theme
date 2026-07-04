<?php
/**
 * Template Name: Jaonaichan Home
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

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'jn-home-fonts',
		'https://fonts.googleapis.com/css2?family=Mali:wght@600;700&family=Prompt:wght@400;500;600;700&display=swap',
		[],
		null
	);
} );

get_header();

// Set up global $post so thumbnail / content helpers work
if ( have_posts() ) {
	the_post();
}

// Featured products — prefer WC "featured" flag, fallback to latest
$featured = [];
if ( function_exists( 'wc_get_products' ) ) {
	$featured = wc_get_products( [
		'status'   => 'publish',
		'limit'    => 8,
		'featured' => true,
		'orderby'  => 'date',
		'order'    => 'DESC',
	] );
	if ( empty( $featured ) ) {
		$featured = wc_get_products( [
			'status'  => 'publish',
			'limit'   => 8,
			'orderby' => 'date',
			'order'   => 'DESC',
		] );
	}
}

// Top-level product categories
$categories = get_terms( [
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => 0,
	'exclude'    => [ get_option( 'default_product_cat' ) ],
	'number'     => 6,
	'orderby'    => 'count',
	'order'      => 'DESC',
] );
if ( is_wp_error( $categories ) ) {
	$categories = [];
}

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );
?>

<style>
/* Break out of Astra's content container for true full-width sections */
.jn-home-wrap {
	margin-left:  calc(50% - 50vw);
	margin-right: calc(50% - 50vw);
	width: 100vw;
	max-width: 100vw;
	overflow-x: hidden;
}
/* Remove default page padding Astra adds */
.ast-article-single, .entry-content { padding: 0 !important; margin: 0 !important; }
/* WooCommerce price colour override inside our cards */
.jn-product-card .price { color: #ec4899 !important; font-weight: 700; }
.jn-product-card ins { text-decoration: none; }
/* Blob float animations */
@keyframes jn-home-blob-drift-1 {
  0%, 100% { transform: translate(-50%, -50%) scale(1); }
  50%      { transform: translate(calc(-50% + 16px), calc(-50% - 12px)) scale(1.08); }
}
@keyframes jn-home-blob-drift-2 {
  0%, 100% { transform: translate(50%, 50%) scale(1); }
  50%      { transform: translate(calc(50% - 14px), calc(50% + 10px)) scale(1.1); }
}
.jn-home-blob-1 { animation: jn-home-blob-drift-1 7s ease-in-out infinite; will-change: transform; }
.jn-home-blob-2 { animation: jn-home-blob-drift-2 9s ease-in-out infinite; animation-delay: 2s; will-change: transform; }
/* Cat wiggle animation */
@keyframes jn-wiggle {
  0%, 100% { transform: rotate(0deg); }
  25%       { transform: rotate(-12deg); }
  75%       { transform: rotate(12deg); }
}
.jn-cat-wiggle {
  display: inline-block;
  transform-origin: bottom center;
  animation: jn-wiggle 2.8s ease-in-out infinite;
}
/* Badge text slider — 3 messages + duplicate of first for seamless loop */
@keyframes jn-badge-slide {
  0%,  23% { transform: translateY(0); }
  25%, 48% { transform: translateY(-25%); }
  50%, 73% { transform: translateY(-50%); }
  75%, 98% { transform: translateY(-75%); }
  100%     { transform: translateY(0); }
}
.jn-badge-track {
  display: flex;
  flex-direction: column;
  animation: jn-badge-slide 9s ease-in-out infinite;
}
.jn-badge-item {
  height: 1.35rem;
  line-height: 1.35rem;
  white-space: nowrap;
}
.jn-home-wrap { font-family: 'Prompt', sans-serif; }
.jn-home-wrap h1,
.jn-home-wrap h2 { font-family: 'Mali', sans-serif; }
/* Claymorphism buttons */
.jn-btn-clay {
  background: #ec4899;
  color: #fff;
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.35),
    0 10px 20px -6px rgba(236,72,153,.45),
    0 3px 8px rgba(236,72,153,.25);
  transition: transform .2s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease-out, background-color .2s ease;
}
.jn-btn-clay:hover {
  background: #db2777;
  transform: translateY(-2px);
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.35),
    0 14px 24px -6px rgba(219,39,119,.45),
    0 4px 10px rgba(219,39,119,.3);
}
.jn-btn-clay:active {
  transform: translateY(1px);
  box-shadow: inset 0 2px 6px rgba(219,39,119,.35);
}
.jn-btn-outline {
  background: #fff;
  color: #ec4899;
  border: 2px solid #fbcfe8;
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.6),
    0 6px 14px -4px rgba(236,72,153,.18),
    0 2px 6px rgba(236,72,153,.12);
  transition: transform .2s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease-out, background-color .2s ease, color .2s ease, border-color .2s ease;
}
.jn-btn-outline:hover {
  background: #ec4899;
  color: #fff;
  border-color: #ec4899;
  transform: translateY(-2px);
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.25),
    0 10px 20px -6px rgba(236,72,153,.35),
    0 3px 8px rgba(236,72,153,.22);
}
.jn-btn-outline:active {
  transform: translateY(1px);
  box-shadow: inset 0 2px 6px rgba(219,39,119,.35);
}
/* Hero mascot glow backdrop */
.jn-hero-art-wrap::before {
  content: "";
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 140%;
  aspect-ratio: 1;
  border-radius: 9999px;
  background: radial-gradient(circle, #fce7f3 0%, #fbcfe8 35%, transparent 70%);
  opacity: .6;
  z-index: -1;
  pointer-events: none;
}
/* Entrance stagger */
@keyframes jn-rise-in {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}
.jn-rise-in { animation: jn-rise-in .5s ease-out both; }
.jn-delay-1 { animation-delay: .05s; }
.jn-delay-2 { animation-delay: .1s;  }
.jn-delay-3 { animation-delay: .15s; }
.jn-delay-4 { animation-delay: .2s;  }
.jn-delay-5 { animation-delay: .25s; }
.jn-delay-6 { animation-delay: .3s;  }
.jn-delay-7 { animation-delay: .35s; }
@media (prefers-reduced-motion: reduce) {
  .jn-home-blob-1,
  .jn-home-blob-2,
  .jn-cat-wiggle,
  .jn-rise-in,
  .jn-badge-track {
    animation: none;
  }
}
</style>

<div class="jn-home-wrap w-full min-h-[calc(100vh-80px)] font-sans relative z-10 breakout-desktop">

  <!-- Full Width Background Container -->
  <div class="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[100vw] -z-10 overflow-hidden bg-gradient-to-br from-pink-50 via-white to-purple-50">
    <!-- Decorative background blobs -->
    <div class="jn-home-blob-1 absolute top-0 left-0 w-96 h-96 bg-[#FB5FAB] opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
    <div class="jn-home-blob-2 absolute bottom-0 right-0 w-96 h-96 bg-purple-400 opacity-[0.08] rounded-full mix-blend-multiply filter blur-3xl"></div>
  </div>

	<!-- ═══════════════════════════════════════════
	     1. HERO
	══════════════════════════════════════════════ -->
	<section class="relative overflow-hidden">

		<div class="mx-auto max-w-6xl px-6 pt-[240px] pb-16 md:pt-[280px] md:pb-28 relative" style="z-index:1;">
			<div class="flex flex-col md:flex-row items-center gap-10 md:gap-16">

				<!-- ── Text ── -->
				<div class="flex-1 text-center md:text-left">
					<span class="jn-rise-in jn-delay-1 inline-flex items-center gap-2 text-sm font-semibold px-4 py-1.5 rounded-full mb-5"
					      style="background:#fce7f3; color:#db2777;">
						🐾 ร้านอาหารแมวแสนรัก
					</span>

					<h1 class="jn-rise-in jn-delay-2 text-4xl md:text-6xl font-bold leading-tight mb-5" style="color:#1f2937;">
						ของดีสำหรับ<br>
						<span style="color:#ec4899;">เจ้านายขนฟู</span>
					</h1>

					<p class="jn-rise-in jn-delay-3 text-lg mb-8 max-w-md mx-auto md:mx-0" style="color:#9ca3af;">
						อาหารและขนมคุณภาพดี คัดสรรมาเพื่อแมวที่คุณรัก&nbsp;ส่งตรงถึงบ้าน
					</p>

					<div class="jn-rise-in jn-delay-4 flex flex-wrap gap-3 justify-center md:justify-start">
						<a href="<?php echo esc_url( $shop_url ); ?>"
						   class="jn-btn-clay inline-flex items-center gap-2 font-semibold px-7 py-3 rounded-full">
							ช้อปเลย 🛒
						</a>
						<a href="#jn-categories"
						   class="jn-btn-outline inline-flex items-center gap-2 font-semibold px-7 py-3 rounded-full">
							ดูหมวดหมู่
						</a>
					</div>

					<!-- Stats -->
					<div class="jn-rise-in jn-delay-5 flex gap-6 mt-10 justify-center md:justify-start" style="color:#6b7280;">
						<div class="text-center">
							<div class="text-2xl font-bold" style="color:#1f2937;">100%</div>
							<div class="text-xs mt-0.5">คุณภาพดี</div>
						</div>
						<div class="w-px" style="background:#e5e7eb;"></div>
						<div class="text-center">
							<div class="text-2xl">🚚</div>
							<div class="text-xs mt-0.5">ส่งทั่วไทย</div>
						</div>
						<div class="w-px" style="background:#e5e7eb;"></div>
						<div class="text-center">
							<div class="text-2xl">❤️</div>
							<div class="text-xs mt-0.5">แมวปลื้ม</div>
						</div>
					</div>
				</div>

				<!-- ── Hero image ── -->
				<div class="flex-1 flex justify-center">
					<div class="relative jn-hero-art-wrap jn-rise-in jn-delay-3">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="w-64 h-64 md:w-80 md:h-80 rounded-full overflow-hidden"
							     style="box-shadow: 0 25px 60px rgba(236,72,153,.28);">
								<?php the_post_thumbnail( 'large', [ 'class' => 'w-full h-full object-cover', 'alt' => get_the_title() ] ); ?>
							</div>
						<?php else : ?>
							<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/imgs/cat-hero.gif' ); ?>"
							     alt="แมวน้อย"
							     class="w-52 md:w-64 drop-shadow-2xl jn-cat-wiggle">
						<?php endif; ?>

						<!-- Floating badges -->
						<div class="jn-rise-in jn-delay-6 absolute -top-5 -left-12 flex items-center bg-white rounded-2xl px-4 py-3"
						     style="box-shadow:0 4px 20px rgba(0,0,0,.12);">
							<div style="overflow:hidden; height:1.35rem;">
								<div class="jn-badge-track">
									<span class="text-sm font-semibold jn-badge-item" style="color:#374151; height:1.35rem; line-height:1.35rem;">⭐ คัดสรรคุณภาพ</span>
									<span class="text-sm font-semibold jn-badge-item" style="color:#374151; height:1.35rem; line-height:1.35rem;">🐾 ปลอดภัย 100%</span>
									<span class="text-sm font-semibold jn-badge-item" style="color:#374151; height:1.35rem; line-height:1.35rem;">🚀 ส่งเร็ว 1-2 วัน</span>
									<span class="text-sm font-semibold jn-badge-item" style="color:#374151; height:1.35rem; line-height:1.35rem;">⭐ คัดสรรคุณภาพ</span>
								</div>
							</div>
						</div>
						<div class="jn-rise-in jn-delay-7 absolute -bottom-5 -right-12 rounded-2xl px-4 py-3"
						     style="background:#ec4899; box-shadow:0 4px 20px rgba(236,72,153,.45);">
							<span class="text-sm font-semibold text-white">🚚 ส่งฟรีทั่วไทย</span>
						</div>
					</div>
				</div>

			</div>
		</div>
	</section>

	<!-- ═══════════════════════════════════════════
	     2. FEATURED PRODUCTS
	══════════════════════════════════════════════ -->
	<?php if ( ! empty( $featured ) ) : ?>
	<section class="py-16">
		<div class="mx-auto max-w-6xl px-6">

			<div class="jn-rise-in jn-delay-1 text-center mb-10">
				<span class="text-sm font-semibold tracking-widest uppercase" style="color:#ec4899;">สินค้าแนะนำ</span>
				<h2 class="text-3xl font-bold mt-2" style="color:#1f2937;">เมนูโปรดของเจ้านาย 🐟</h2>
				<p class="mt-2" style="color:#9ca3af;">คัดมาเฉพาะของที่แมวชอบและดีต่อสุขภาพ</p>
			</div>

			<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">
				<?php foreach ( $featured as $i => $product ) :
					$img_id  = $product->get_image_id();
					$img_src = $img_id
						? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' )
						: wc_placeholder_img_src( 'woocommerce_thumbnail' );
					$on_sale = $product->is_on_sale();
					$rise_delay_class = 'jn-delay-' . min( $i + 1, 7 );
				?>
				<article class="jn-rise-in <?php echo esc_attr( $rise_delay_class ); ?> jn-product-card group rounded-[1.5rem] overflow-hidden border transition-all duration-300 bg-white/70 backdrop-blur-xl border-white/60 shadow-[0_8px_32px_0_rgba(31,38,135,0.05)]"
				         onmouseover="this.style.boxShadow='0 8px 32px rgba(236,72,153,.2)';"
				         onmouseout="this.style.boxShadow='';">
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="block">

						<!-- Image -->
						<div class="relative overflow-hidden aspect-square" style="background:#fdf2f8;">
							<img src="<?php echo esc_url( $img_src ); ?>"
							     alt="<?php echo esc_attr( $product->get_name() ); ?>"
							     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
							     loading="lazy">
							<?php if ( $on_sale ) : ?>
							<span class="absolute top-2 left-2 text-white text-xs font-bold px-2 py-0.5 rounded-lg"
							      style="background:#ec4899;">SALE</span>
							<?php endif; ?>
						</div>

						<!-- Info -->
						<div class="p-4">
							<h3 class="text-sm font-semibold leading-snug line-clamp-2 mb-2 transition-colors duration-200"
							    style="color:#374151;"
							    onmouseover="this.style.color='#ec4899'" onmouseout="this.style.color='#374151'">
								<?php echo esc_html( $product->get_name() ); ?>
							</h3>
							<div class="flex items-center justify-between gap-2">
								<div class="text-sm">
									<?php echo $product->get_price_html(); ?>
								</div>
								<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
								   data-quantity="1"
								   data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
								   data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>"
								   class="add_to_cart_button ajax_add_to_cart flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold text-lg transition-all duration-200"
								   style="background:#fce7f3; color:#ec4899;"
								   onmouseover="this.style.background='#ec4899'; this.style.color='#fff';"
								   onmouseout="this.style.background='#fce7f3'; this.style.color='#ec4899';"
								   aria-label="<?php echo esc_attr( $product->add_to_cart_text() ); ?>"
								   rel="nofollow">
									🐱
								</a>
							</div>
						</div>
					</a>
				</article>
				<?php endforeach; ?>
			</div>

			<div class="text-center mt-10">
				<a href="<?php echo esc_url( $shop_url ); ?>"
				   class="jn-btn-outline inline-flex items-center gap-2 font-semibold px-8 py-3 rounded-full">
					ดูสินค้าทั้งหมด →
				</a>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<!-- ═══════════════════════════════════════════
	     3. CATEGORY GRID
	══════════════════════════════════════════════ -->
	<?php if ( ! empty( $categories ) ) : ?>
	<section id="jn-categories" class="py-16">
		<div class="mx-auto max-w-6xl px-6">

			<div class="jn-rise-in jn-delay-1 text-center mb-10">
				<span class="text-sm font-semibold tracking-widest uppercase" style="color:#ec4899;">หมวดหมู่สินค้า</span>
				<h2 class="text-3xl font-bold mt-2" style="color:#1f2937;">เลือกช้อปตามใจแมว 🐾</h2>
			</div>

			<div class="grid grid-cols-2 md:grid-cols-3 gap-4">
				<?php foreach ( $categories as $cat ) :
					$thumb_id  = get_term_meta( $cat->term_id, 'thumbnail_id', true );
					$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';
					$cat_url   = get_term_link( $cat );

					// Pastel fallback colours cycling through categories
					$fallbacks = [
						'linear-gradient(135deg,#fce7f3,#fbcfe8)',
						'linear-gradient(135deg,#fef3c7,#fde68a)',
						'linear-gradient(135deg,#e0f2fe,#bae6fd)',
						'linear-gradient(135deg,#dcfce7,#bbf7d0)',
						'linear-gradient(135deg,#ede9fe,#ddd6fe)',
						'linear-gradient(135deg,#fff1f2,#fecdd3)',
					];
					static $cat_idx = 0;
					$fallback_bg = $fallbacks[ $cat_idx % count( $fallbacks ) ];
					$cat_delay_class = 'jn-delay-' . min( $cat_idx + 1, 7 );
					$cat_idx++;
				?>
				<a href="<?php echo esc_url( $cat_url ); ?>"
				   class="jn-rise-in <?php echo esc_attr( $cat_delay_class ); ?> group relative overflow-hidden rounded-2xl flex items-end p-5 transition-all duration-300"
				   style="aspect-ratio:4/3; background:<?php echo $thumb_url ? "url('" . esc_url( $thumb_url ) . "') center/cover" : $fallback_bg; ?>; box-shadow:0 2px 8px rgba(0,0,0,.07);"
				   onmouseover="this.style.boxShadow='0 12px 32px rgba(236,72,153,.2)'; this.style.transform='translateY(-2px)';"
				   onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,.07)'; this.style.transform='none';">

					<!-- Overlay -->
					<div class="absolute inset-0 rounded-2xl transition-all duration-300"
					     style="background: linear-gradient(to top, rgba(131,24,67,.65) 0%, transparent 55%);"
					     data-overlay></div>

					<!-- Emoji placeholder when no image -->
					<?php if ( ! $thumb_url ) : ?>
					<div class="absolute inset-0 flex items-center justify-center pointer-events-none"
					     style="font-size:4rem; opacity:.25;">🐱</div>
					<?php endif; ?>

					<div class="relative" style="z-index:1;">
						<h3 class="text-white font-bold text-base md:text-lg leading-tight">
							<?php echo esc_html( $cat->name ); ?>
						</h3>
						<p class="text-xs mt-0.5" style="color:#fbcfe8;">
							<?php echo number_format( $cat->count ); ?> สินค้า
						</p>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	

</div><!-- .jn-home-wrap -->

<?php get_footer(); ?>
