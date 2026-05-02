<?php
/**
 * Template Name: Shop Login
 */

$shop_login_image = [
    'src'    => get_stylesheet_directory_uri() . '/assets/imgs/login-maow.png',
    'width'  => '112%',
    'height' => '110%',
    'alt'    => '',
];

$default_redirect = home_url();

$self_path   = strtok( $_SERVER['REQUEST_URI'], '?' );
$redirect_to = '';
if ( ! empty( $_REQUEST['redirect_to'] ) ) {
    $candidate = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
    if ( parse_url( $candidate, PHP_URL_PATH ) !== $self_path ) {
        $redirect_to = $candidate;
    }
} elseif ( $_SERVER['REQUEST_METHOD'] === 'GET' && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
    $referer  = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
    $self_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . strtok( $_SERVER['REQUEST_URI'], '?' );
    if ( strpos( $referer, $self_url ) !== 0 ) {
        $redirect_to = $referer;
    }
}
$redirect_to = wp_validate_redirect( $redirect_to, $default_redirect );

if ( is_user_logged_in() && ! is_preview() ) {
    wp_safe_redirect( $redirect_to );
    exit;
}

$login_error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['shop_login_nonce'] ) ) {
    if ( wp_verify_nonce( $_POST['shop_login_nonce'], 'shop_login' ) ) {
        $user = wp_signon([
            'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
            'user_password' => $_POST['pwd'] ?? '',
            'remember'      => true,
        ], is_ssl());
        if ( ! is_wp_error( $user ) ) {
            wp_safe_redirect( $redirect_to );
            exit;
        }
        $login_error = __( 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', $_ENV['TEXTDOMAIN_NAME'] );
    } else {
        $login_error = __( 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่', $_ENV['TEXTDOMAIN_NAME'] );
    }
}

nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc_html__( 'Shop Login', $_ENV['TEXTDOMAIN_NAME'] ) ?> — <?php bloginfo( 'name' ); ?></title>
  <style>
    :root {
      --jn-bg: #FAE3D1; /* #F5C254 */
      --jn-card: #F9D2CD;/* #FBC8B5 */
      --jn-panel: #B7E0EB;
      --jn-text: #8865B3;/* #6B3FA0 */
      --jn-accent: #E5298E;
      --jn-accent-rgb: 229, 41, 142;
      --jn-accent-strong: #C8217A;
      --jn-blob-1: #F8E08E;
      --jn-blob-2: #F4A6C0;
      --jn-blob-3: #FFFBE6;
      --jn-blob-4: #8EF8E0;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      min-height: 100dvh;
      background: var(--jn-bg);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Sarabun", "Noto Sans Thai", Roboto, sans-serif;
      color: var(--jn-text);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: clamp(0.5rem, 1.5vw, 2rem);
    }
    .jn-login-card {
      position: relative;
      width: 100%;
      max-width: none;
      min-height: calc(100dvh - clamp(1rem, 3vw, 4rem));
      background: var(--jn-card);
      border-radius: clamp(16px, 2vw, 32px);
      box-shadow: 0 18px 40px rgba(0,0,0,0.12);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    /* iPad portrait & small tablets — comfortable centered card, content stays left-aligned */
    @media (min-width: 600px) and (max-width: 921px) {
      .jn-login-card { width: min(92%, 640px); min-height: 0; }
    }
    /* iPad landscape & desktop — two-column with safe min/max */
    @media (min-width: 922px) {
      .jn-login-card {
        width: min(70vw, 1200px);
        height: min(60vh, 720px);
        min-height: 480px;
      }
    }
    .jn-login-grid {
      display: grid;
      grid-template-columns: 1fr;
      width: 100%;
    }
    @media (min-width: 922px) {
      .jn-login-grid { grid-template-columns: 1.1fr 1fr; }
    }
    .jn-login-left {
      padding: clamp(1.5rem, 2vw, 5rem);
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .jn-login-right {
      position: relative;
      min-height: clamp(220px, 30vh, 360px);
      background: var(--jn-panel);
      /* border-top-left-radius: 50% 35%; */
      /* border-bottom-left-radius: 50% 35%; */
      border-radius: 55% 45% 38% 62% / 48% 67% 33% 52%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      overflow: hidden;
    }
    .jn-login-art {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      object-fit: cover;
      display: block;
    }
    @media (max-width: 921px) {
      .jn-login-right { display: none; }
    }
    .jn-login-title {
      font-size: clamp(2.25rem, 6vw, 5.5rem);
      font-weight: 800;
      color: var(--jn-accent);
      line-height: 1.05;
      text-transform: uppercase;
      margin: 0 0 clamp(0.75rem, 1.5vw, 1.5rem);
    }
    .jn-login-sub {
      font-weight: 700;
      color: var(--jn-text);
      font-size: clamp(1rem, 1.4vw, 1.25rem);
      margin: 0 0 0.5rem;
    }
    .jn-login-body-text {
      color: var(--jn-text);
      font-size: clamp(0.9rem, 1.1vw, 1.05rem);
      line-height: 1.6;
      margin: 0 0 clamp(1.25rem, 2vw, 2rem);
      max-width: 60ch;
    }
    .jn-login-input {
      display: block;
      width: 100%;
      max-width: 540px;
      background: transparent;
      border: 2px solid var(--jn-accent);
      color: var(--jn-text);
      font-size: clamp(0.9rem, 1.1vw, .05rem);
      font-weight: 600;
      letter-spacing: 0.05em;
      padding: clamp(0.75rem, 1.4vw, 1.1rem) clamp(1rem, 1.8vw, 1.5rem);
      border-radius: clamp(16px, 2vw, 32px);;
      outline: none;
      transition: box-shadow .15s ease;
      margin-bottom: 0.875rem;
      font-family: inherit;
    }
    .jn-login-input::placeholder {
      color: var(--jn-accent);
      opacity: .7;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .jn-login-input:focus { box-shadow: 0 0 0 3px rgba(var(--jn-accent-rgb), 0.18); }
    .jn-login-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--jn-accent);
      color: #fff;
      font-weight: 700;
      font-size: clamp(0.9rem, 1.1vw, 1.05rem);
      letter-spacing: 0.12em;
      padding: clamp(0.75rem, 1.4vw, 1.1rem) clamp(2rem, 3vw, 3.25rem);
      border-radius: clamp(16px, 2vw, 32px);;
      border: none;
      cursor: pointer;
      margin-top: 0.75rem;
      transition: background .15s ease, transform .05s ease;
      font-family: inherit;
    }
    .jn-login-btn:hover { background: var(--jn-accent-strong); }
    .jn-login-btn:active { transform: translateY(1px); }
    .jn-login-btn[disabled] { opacity: .8; cursor: wait; }
    .jn-login-btn-spin {
      display: none;
      width: 1em;
      height: 1em;
      margin-right: 0.6em;
      animation: jn-spin .8s linear infinite;
    }
    .jn-login-btn.is-loading .jn-login-btn-spin { display: inline-block; }
    @keyframes jn-spin { to { transform: rotate(360deg); } }
    .jn-login-error {
      background: #fff;
      border: 1px solid var(--jn-accent);
      color: var(--jn-accent-strong);
      border-radius: 12px;
      padding: 0.6rem 0.9rem;
      font-size: 0.85rem;
      margin-bottom: 1rem;
    }
    .jn-login-foot {
      margin-top: 1.25rem;
      font-size: 0.85rem;
      color: var(--jn-text);
    }
    .jn-login-foot a { color: var(--jn-accent); font-weight: 600; text-decoration: underline; }
    .jn-blob { position: absolute; border-radius: 9999px; pointer-events: none; }
    .jn-blob-1 { top: -40px; left: -40px; width: 160px; height: 160px; background: var(--jn-blob-1); opacity: .55; }
    .jn-blob-2 { bottom: -50px; left: -30px; width: 180px; height: 180px; background: var(--jn-blob-2); opacity: .45; }
    .jn-blob-3 { top: 30%; left: 45%; width: 120px; height: 120px; background: var(--jn-blob-3); opacity: .35; }
    .jn-blob-4 { top: -60px; right: 0px; width: 150px; height: 150px; background: var(--jn-blob-4); opacity: .55; }
    [x-cloak] { display: none !important; }
  </style>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body>
  <div class="jn-login-card">
    <span class="jn-blob jn-blob-1"></span>
    <span class="jn-blob jn-blob-2"></span>
    <span class="jn-blob jn-blob-3"></span>
    <span class="jn-blob jn-blob-4"></span>

    <div class="jn-login-grid">
      <div class="jn-login-left">
        <h1 class="jn-login-title">
          <?= esc_html__( 'jao nai chan', $_ENV['TEXTDOMAIN_NAME'] ) ?>
        </h1>

        <h2 class="jn-login-sub">
          <?= esc_html__( 'ยินดีต้อนรับกลับ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
        </h2>
        <p class="jn-login-body-text">
          <?= esc_html__( 'เข้าสู่ระบบเพื่อดำเนินการสั่งซื้อสินค้าและติดตามคำสั่งซื้อของคุณ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
        </p>

        <?php if ( $login_error ) : ?>
          <div class="jn-login-error"><?= esc_html( $login_error ) ?></div>
        <?php endif; ?>

        <form
          method="post"
          action=""
          x-data="{ loading: false }"
          @submit="loading = true"
          @pageshow.window="if ($event.persisted) loading = false"
        >
          <?php wp_nonce_field( 'shop_login', 'shop_login_nonce' ); ?>
          <input
            type="text"
            name="log"
            class="jn-login-input"
            placeholder="<?= esc_attr__( 'Username or Email', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
            autocomplete="username"
            :disabled="loading"
            required
          >
          <input
            type="password"
            name="pwd"
            class="jn-login-input"
            placeholder="<?= esc_attr__( 'Password', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
            :disabled="loading"
            autocomplete="current-password"
            required
          >
          <input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_to ) ?>">

          <button type="submit" class="jn-login-btn" :class="{ 'is-loading': loading }" :disabled="loading">
            <svg class="jn-login-btn-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"></circle>
              <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity:.75"></path>
            </svg>
            <?= esc_html__( 'CONFIRM', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </button>
        </form>

        <p class="jn-login-foot">
          <a href="<?= esc_url( wp_lostpassword_url( $redirect_to ) ) ?>">
            <?= esc_html__( 'Forgot your password', $_ENV['TEXTDOMAIN_NAME'] ) ?>?
          </a>
        </p>
      </div>

      <div class="jn-login-right" aria-hidden="<?= empty( $shop_login_image['src'] ) ? 'true' : 'false' ?>">
        <?php if ( ! empty( $shop_login_image['src'] ) ) : ?>
          <img
            class="jn-login-art"
            src="<?= esc_url( $shop_login_image['src'] ) ?>"
            alt="<?= esc_attr( $shop_login_image['alt'] ) ?>"
            style="width: <?= esc_attr( $shop_login_image['width'] ) ?>; height: <?= esc_attr( $shop_login_image['height'] ) ?>;"
          >
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
