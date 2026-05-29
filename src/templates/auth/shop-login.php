<?php
/**
 * Template Name: Shop Login
 */

// Dequeue all external stylesheets — this page uses its own inline CSS.
// wp_enqueue_scripts fires inside wp_head() so this hook runs in time.
add_action( 'wp_enqueue_scripts', function () {
    global $wp_styles;
    foreach ( array_keys( $wp_styles->registered ) as $handle ) {
        wp_dequeue_style( $handle );
    }
}, PHP_INT_MAX );

$shop_login_image = [
    'src'    => get_stylesheet_directory_uri() . '/assets/imgs/login-maow.png',
    'width'  => '112%',
    'height' => '110%',
    'alt'    => '',
];

// Normalize action — only the four we handle
$action = sanitize_key( $_GET['action'] ?? 'login' );

// Logout ต้องผ่าน wp-login.php เพื่อ nonce + cookie clear
if ( $action === 'logout' ) {
    wp_safe_redirect( is_user_logged_in() ? wp_logout_url() : home_url( JN_SHOP_LOGIN_PATH ) );
    exit;
}

if ( ! in_array( $action, [ 'login', 'lostpassword', 'rp', 'resetpass' ], true ) ) {
    $action = 'login';
}

$default_redirect = home_url( '/shop/' );

// ── LOGIN ───────────────────────────────────────────────────────────────────
$login_error = '';
$redirect_to = '';
$jn_notice   = '';

if ( $action === 'login' ) {

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

    // Final guard: ถ้า redirect_to ลงเอยที่ตัว login page เอง → fallback ไปที่ '/' (path ดิบๆ ไม่ผ่าน home_url)
    // เพื่อกัน redirect loop กรณี home_url() ถูก config ผิด
    $rt_path   = parse_url( $redirect_to, PHP_URL_PATH ) ?: '';
    $self_norm = rtrim( $self_path, '/' );
    $rt_norm   = rtrim( $rt_path, '/' );
    if ( $rt_norm === $self_norm ) {
        $redirect_to = '/';
    }

    if ( is_user_logged_in() && ! is_preview() ) {
        wp_redirect( $redirect_to );
        exit;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['shop_login_nonce'] ) ) {
        if ( wp_verify_nonce( $_POST['shop_login_nonce'], 'shop_login' ) ) {
            $user = wp_signon([
                'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
                'user_password' => $_POST['pwd'] ?? '',
                'remember'      => true,
            ], is_ssl());
            if ( ! is_wp_error( $user ) ) {
                $target = $redirect_to;
                if ( ! headers_sent( $hs_file, $hs_line ) ) {
                    wp_redirect( $target );
                    exit;
                }
                // Fallback: ถ้า headers ส่งไปแล้ว (มี plugin echo ก่อน) → ใช้ JS redirect + แสดง source
                echo '<!-- DEBUG headers_sent_at: ' . esc_html( $hs_file . ':' . $hs_line ) . ' -->';
                echo '<script>window.location.replace(' . wp_json_encode( $target ) . ');</script>';
                exit;
            }
            $login_error = __( 'username หรือ password ไม่ถูกต้อง', $_ENV['TEXTDOMAIN_NAME'] );
        } else {
            $login_error = __( 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่', $_ENV['TEXTDOMAIN_NAME'] );
        }
    }

    // Success/info notices from redirect (password_sent, password_reset)
    if ( isset( $_GET['jn_notice'] ) ) {
        $n = sanitize_key( $_GET['jn_notice'] );
        if ( $n === 'password_sent' )  $jn_notice = __( 'ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว', $_ENV['TEXTDOMAIN_NAME'] );
        if ( $n === 'password_reset' ) $jn_notice = __( 'รีเซ็ตรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบ', $_ENV['TEXTDOMAIN_NAME'] );
    }

    // Social login blocked: miniOrange (?social_login_blocked=1) หรือ LINE (?line_error=not_registered)
    if ( isset( $_GET['social_login_blocked'] ) ||
         ( isset( $_GET['line_error'] ) && sanitize_key( $_GET['line_error'] ) === 'not_registered' ) ) {
        $login_error = __( 'ไม่พบบัญชีของคุณในระบบ กรุณาติดต่อผู้ดูแล', $_ENV['TEXTDOMAIN_NAME'] );
    }
}

// ── LOST PASSWORD ───────────────────────────────────────────────────────────
$lp_error   = '';
$lp_success = false;

if ( $action === 'lostpassword' ) {

    if ( is_user_logged_in() && ! is_preview() ) {
        wp_safe_redirect( home_url( '/shop/' ) );
        exit;
    }

    // Expired-key error forwarded from the rp block below
    if ( $_SERVER['REQUEST_METHOD'] === 'GET'
        && isset( $_GET['error'] )
        && sanitize_key( $_GET['error'] ) === 'expiredkey'
    ) {
        $lp_error = __( 'ลิงก์รีเซ็ตหมดอายุหรือไม่ถูกต้อง กรุณาส่งคำขอใหม่', $_ENV['TEXTDOMAIN_NAME'] );
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['jn_lostpass_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['jn_lostpass_nonce'], 'jn_lostpassword' ) ) {
            $lp_error = __( 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่', $_ENV['TEXTDOMAIN_NAME'] );
        } else {
            $user_input = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );
            if ( empty( $user_input ) ) {
                $lp_error = __( 'กรุณากรอก Username หรือ Email', $_ENV['TEXTDOMAIN_NAME'] );
            } else {
                $user = strpos( $user_input, '@' )
                    ? get_user_by( 'email', $user_input )
                    : get_user_by( 'login', $user_input );

                if ( ! $user instanceof WP_User ) {
                    $lp_error = __( 'ไม่พบบัญชีที่ใช้ Username หรือ Email นี้', $_ENV['TEXTDOMAIN_NAME'] );
                } else {
                    $key = get_password_reset_key( $user );
                    if ( is_wp_error( $key ) ) {
                        $lp_error = wp_strip_all_tags( $key->get_error_message() );
                    } else {
                        $reset_url = add_query_arg(
                            [
                                'action' => 'rp',
                                'key'    => $key,
                                'login'  => rawurlencode( $user->user_login ),
                            ],
                            home_url( JN_SHOP_LOGIN_PATH )
                        );
                        $blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
                        $message   = __( 'Someone has requested a password reset for the following account:', $_ENV['TEXTDOMAIN_NAME'] ) . "\r\n\r\n";
                        $message  .= sprintf( __( 'Site Name: %s', $_ENV['TEXTDOMAIN_NAME'] ), $blog_name ) . "\r\n\r\n";
                        $message  .= sprintf( __( 'Username: %s', $_ENV['TEXTDOMAIN_NAME'] ), $user->user_login ) . "\r\n\r\n";
                        $message  .= __( 'If this was a mistake, ignore this email and nothing will happen.', $_ENV['TEXTDOMAIN_NAME'] ) . "\r\n\r\n";
                        $message  .= __( 'To reset your password, visit the following address:', $_ENV['TEXTDOMAIN_NAME'] ) . "\r\n\r\n";
                        $message  .= "<{$reset_url}>\r\n";
                        $message   = apply_filters( 'retrieve_password_message', $message, $key, $user->user_login, $user );
                        $subject   = apply_filters( 'retrieve_password_title', sprintf( '[%s] Password Reset', $blog_name ), $user->user_login, $user );
                        wp_mail( $user->user_email, $subject, $message );
                        $lp_success = true;
                    }
                }
            }
        }
    }
}

// ── RESET PASSWORD ──────────────────────────────────────────────────────────
// Key/login come from GET params (the email link); form posts back to the same URL,
// so $_GET still carries them on POST.
$rp_error = '';
$rp_key   = sanitize_text_field( wp_unslash( $_GET['key']   ?? '' ) );
$rp_login = sanitize_user(        wp_unslash( $_GET['login'] ?? '' ) );
$rp_user  = null;

if ( $action === 'rp' || $action === 'resetpass' ) {

    $rp_user = check_password_reset_key( $rp_key, $rp_login );

    if ( is_wp_error( $rp_user ) ) {
        wp_safe_redirect( add_query_arg(
            [ 'action' => 'lostpassword', 'error' => 'expiredkey' ],
            home_url( JN_SHOP_LOGIN_PATH )
        ) );
        exit;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['jn_reset_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['jn_reset_nonce'], 'jn_reset_password' ) ) {
            $rp_error = __( 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่', $_ENV['TEXTDOMAIN_NAME'] );
        } else {
            $pass1 = $_POST['pass1'] ?? '';
            $pass2 = $_POST['pass2'] ?? '';
            if ( empty( $pass1 ) ) {
                $rp_error = __( 'กรุณากรอกรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] );
            } elseif ( $pass1 !== $pass2 ) {
                $rp_error = __( 'รหัสผ่านไม่ตรงกัน กรุณากรอกอีกครั้ง', $_ENV['TEXTDOMAIN_NAME'] );
            } else {
                reset_password( $rp_user, $pass1 );
                // after_password_reset hook (password-reset.php) fires here → redirect + exit
            }
        }
    }
}

// Page title for <head>
$page_titles = [
    'login'     => __( 'Shop Login',        $_ENV['TEXTDOMAIN_NAME'] ),
    'lostpassword' => __( 'ลืมรหัสผ่าน',  $_ENV['TEXTDOMAIN_NAME'] ),
    'rp'        => __( 'ตั้งรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ),
    'resetpass' => __( 'ตั้งรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ),
];
$page_title = $page_titles[ $action ] ?? $page_titles['login'];

nocache_headers();
?>
<!-- JN-DEBUG action=<?= esc_html( $_GET['action'] ?? 'NONE' ) ?> all_get=<?= esc_html( implode( ',', array_keys( $_GET ) ) ) ?> -->
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc_html( $page_title ) ?> — <?php bloginfo( 'name' ); ?></title>
  <?php wp_head(); ?>
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
    /* Hide anything plugins inject into <body> outside our card */
    body > *:not(.jn-login-card) { display: none !important; }
    /* MiniOrange social login — left-align, compact icon row */
    [id*="mo_social_login_"],
    [class*="mo_social_login"],
    .mo-openid-app-icons {
      text-align: left !important;
      background: transparent !important;
      padding: 0 !important;
      margin: 0 0 0 !important;
    }
    .mo_login_button {
      width: auto !important;
      height: auto !important;
      padding: 6px !important;
      border-radius: 50% !important;
      margin: 0 8px 0 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
    }
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
      margin: 0 0 clamp(0.25rem, 2vw, 0rem);
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
    @keyframes jn-blob-a {
      0%, 100% { transform: translate(0, 0) scale(1); }
      35%       { transform: translate(8px, -10px) scale(1.03); }
      68%       { transform: translate(-5px, 6px) scale(0.97); }
    }
    @keyframes jn-blob-b {
      0%, 100% { transform: translate(0, 0) scale(1); }
      40%       { transform: translate(-9px, 7px) scale(1.04); }
      72%       { transform: translate(6px, -5px) scale(0.96); }
    }
    .jn-blob { position: absolute; border-radius: 9999px; pointer-events: none; will-change: transform; }
    .jn-blob-1 { top: -40px; left: -40px; width: 160px; height: 160px; background: var(--jn-blob-1); opacity: .55; animation: jn-blob-a 13s ease-in-out infinite; }
    .jn-blob-2 { bottom: -50px; left: -30px; width: 180px; height: 180px; background: var(--jn-blob-2); opacity: .45; animation: jn-blob-b 16s ease-in-out infinite; animation-delay: -5s; }
    .jn-blob-3 { top: 30%; left: 45%; width: 120px; height: 120px; background: var(--jn-blob-3); opacity: .35; animation: jn-blob-a 19s ease-in-out infinite; animation-delay: -9s; }
    .jn-blob-4 { top: -60px; right: 0px; width: 150px; height: 150px; background: var(--jn-blob-4); opacity: .55; animation: jn-blob-b 11s ease-in-out infinite; animation-delay: -2s; }
    @media (prefers-reduced-motion: reduce) {
      .jn-blob { animation: none; }
    }
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

        <?php if ( $action === 'login' ) : ?>

          <h1 class="jn-login-title">
            <?= esc_html__( 'jao nai chan', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h1>
          <h2 class="jn-login-sub">
            <?= esc_html__( 'ยินดีต้อนรับกลับ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h2>
          <p class="jn-login-body-text">
            <?= esc_html__( 'เข้าสู่ระบบเพื่อดำเนินการสั่งซื้อสินค้าและติดตามคำสั่งซื้อของคุณ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </p>

          <?php if ( $jn_notice ) : ?>
            <div class="jn-login-error"><?= esc_html( $jn_notice ) ?></div>
          <?php endif; ?>

          <?php if ( $login_error ) : ?>
            <div class="jn-login-error"><?= esc_html( $login_error ) ?></div>
          <?php endif; ?>

          <?php echo apply_shortcodes('[miniorange_social_login shape="round" theme="default" space="4" size="35"]') ?>

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
              :readonly="loading"
              required
            >
            <input
              type="password"
              name="pwd"
              class="jn-login-input"
              placeholder="<?= esc_attr__( 'Password', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
              :readonly="loading"
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
            <a href="<?= esc_url( wp_lostpassword_url() ) ?>">
              <?= esc_html__( 'Forgot your password', $_ENV['TEXTDOMAIN_NAME'] ) ?>?
            </a>
          </p>

        <?php elseif ( $action === 'lostpassword' ) : ?>

          <h1 class="jn-login-title">
            <?= esc_html__( 'jao nai chan', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h1>
          <h2 class="jn-login-sub">
            <?= esc_html__( 'ลืมรหัสผ่าน?', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h2>

          <?php if ( $lp_success ) : ?>

            <p class="jn-login-body-text">
              <?= esc_html__( 'ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว กรุณาตรวจสอบกล่องขาเข้า', $_ENV['TEXTDOMAIN_NAME'] ) ?>
            </p>
            <p class="jn-login-foot">
              <a href="<?= esc_url( home_url( JN_SHOP_LOGIN_PATH ) ) ?>">
                &larr; <?= esc_html__( 'กลับหน้าเข้าสู่ระบบ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
              </a>
            </p>

          <?php else : ?>

            <p class="jn-login-body-text">
              <?= esc_html__( 'กรอก Username หรือ Email เพื่อรับลิงก์รีเซ็ตรหัสผ่าน', $_ENV['TEXTDOMAIN_NAME'] ) ?>
            </p>

            <?php if ( $lp_error ) : ?>
              <div class="jn-login-error"><?= esc_html( $lp_error ) ?></div>
            <?php endif; ?>

            <form
              method="post"
              action=""
              x-data="{ loading: false }"
              @submit="loading = true"
              @pageshow.window="if ($event.persisted) loading = false"
            >
              <?php wp_nonce_field( 'jn_lostpassword', 'jn_lostpass_nonce' ); ?>
              <input
                type="text"
                name="user_login"
                class="jn-login-input"
                placeholder="<?= esc_attr__( 'Username or Email', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
                autocomplete="username email"
                :readonly="loading"
                required
              >
              <button type="submit" class="jn-login-btn" :class="{ 'is-loading': loading }" :disabled="loading">
                <svg class="jn-login-btn-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"></circle>
                  <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity:.75"></path>
                </svg>
                <?= esc_html__( 'ส่งลิงก์รีเซ็ต', $_ENV['TEXTDOMAIN_NAME'] ) ?>
              </button>
            </form>

            <p class="jn-login-foot">
              <a href="<?= esc_url( home_url( JN_SHOP_LOGIN_PATH ) ) ?>">
                &larr; <?= esc_html__( 'กลับหน้าเข้าสู่ระบบ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
              </a>
            </p>

          <?php endif; ?>

        <?php elseif ( $action === 'rp' || $action === 'resetpass' ) : ?>

          <h1 class="jn-login-title">
            <?= esc_html__( 'jao nai chan', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h1>
          <h2 class="jn-login-sub">
            <?= esc_html__( 'ตั้งรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </h2>
          <p class="jn-login-body-text">
            <?= esc_html__( 'กรอกรหัสผ่านใหม่สำหรับบัญชีของคุณ', $_ENV['TEXTDOMAIN_NAME'] ) ?>
          </p>

          <?php if ( $rp_error ) : ?>
            <div class="jn-login-error"><?= esc_html( $rp_error ) ?></div>
          <?php endif; ?>

          <form
            method="post"
            action=""
            x-data="{ loading: false }"
            @submit="loading = true"
            @pageshow.window="if ($event.persisted) loading = false"
          >
            <?php wp_nonce_field( 'jn_reset_password', 'jn_reset_nonce' ); ?>
            <input
              type="password"
              name="pass1"
              class="jn-login-input"
              placeholder="<?= esc_attr__( 'รหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
              autocomplete="new-password"
              :readonly="loading"
              required
            >
            <input
              type="password"
              name="pass2"
              class="jn-login-input"
              placeholder="<?= esc_attr__( 'ยืนยันรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ) ?>"
              autocomplete="new-password"
              :readonly="loading"
              required
            >
            <button type="submit" class="jn-login-btn" :class="{ 'is-loading': loading }" :disabled="loading">
              <svg class="jn-login-btn-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"></circle>
                <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity:.75"></path>
              </svg>
              <?= esc_html__( 'ยืนยันรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ) ?>
            </button>
          </form>

        <?php endif; ?>

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
  <?php wp_footer(); ?>
</body>
</html>
