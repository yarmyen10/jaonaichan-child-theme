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

    // Social login errors from jaonaichan-social-login plugin
    if ( isset( $_GET['social_error'] ) ) {
        $social_err_code = sanitize_key( $_GET['social_error'] );
        $login_error = match( $social_err_code ) {
            'not_registered'                                    => __( 'ไม่พบบัญชีของคุณในระบบ กรุณาติดต่อผู้ดูแล', $_ENV['TEXTDOMAIN_NAME'] ),
            'line_denied', 'google_denied', 'facebook_denied'  => __( 'คุณยกเลิกการเข้าสู่ระบบ', $_ENV['TEXTDOMAIN_NAME'] ),
            'invalid_state'                                     => __( 'Session หมดอายุ กรุณาลองใหม่', $_ENV['TEXTDOMAIN_NAME'] ),
            default                                             => __( 'เข้าสู่ระบบด้วย Social ล้มเหลว กรุณาลองใหม่อีกครั้ง', $_ENV['TEXTDOMAIN_NAME'] ),
        };
    }
    // Legacy params (miniOrange / old LINE plugin) — ลบออกได้หลัง deactivate plugins เก่า
    if ( ! $login_error && isset( $_GET['social_login_blocked'] ) ) {
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      /* Original Base Colors */
      --jn-bg-main: #FAE3D1;
      --jn-card-base: #F9D2CD;
      --jn-panel: #B7E0EB;
      
      /* Text */
      --jn-text: #6B3FA0;
      --jn-text-muted: #8865B3;
      
      /* Accents */
      --jn-accent-1: #E5298E;
      --jn-accent-2: #F5C254; /* warm yellow-orange for gradient mix */
      
      /* Glassmorphism */
      --jn-glass-bg: rgba(255, 255, 255, 0.45);
      --jn-glass-border: rgba(255, 255, 255, 0.7);
      --jn-glass-shadow: rgba(107, 63, 160, 0.1);
      
      /* Blobs */
      --jn-blob-1: #F8E08E;
      --jn-blob-2: #F4A6C0;
      --jn-blob-3: #FFFBE6;
      --jn-blob-4: #8EF8E0;
      
      --jn-error: #E5298E;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body > *:not(.jn-login-wrapper) { display: none !important; }
    
    body {
      min-height: 100vh;
      min-height: 100dvh;
      background: var(--jn-bg-main);
      background-attachment: fixed;
      font-family: 'Inter', 'Prompt', sans-serif;
      color: var(--jn-text);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: clamp(1rem, 3vw, 2rem);
      overflow-x: hidden;
    }

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
    .jn-blob { position: absolute; border-radius: 9999px; pointer-events: none; will-change: transform; z-index: 0; }
    .jn-blob-1 { top: -40px; left: -40px; width: 160px; height: 160px; background: var(--jn-blob-1); opacity: .55; animation: jn-blob-a 13s ease-in-out infinite; }
    .jn-blob-2 { bottom: -50px; left: -30px; width: 180px; height: 180px; background: var(--jn-blob-2); opacity: .45; animation: jn-blob-b 16s ease-in-out infinite; animation-delay: -5s; }
    .jn-blob-3 { top: 30%; left: 45%; width: 120px; height: 120px; background: var(--jn-blob-3); opacity: .35; animation: jn-blob-a 19s ease-in-out infinite; animation-delay: -9s; }
    .jn-blob-4 { top: -60px; right: 0px; width: 150px; height: 150px; background: var(--jn-blob-4); opacity: .55; animation: jn-blob-b 11s ease-in-out infinite; animation-delay: -2s; }
    @media (prefers-reduced-motion: reduce) {
      .jn-blob { animation: none; }
    }

    .jn-login-wrapper {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 1100px;
      min-height: 600px;
      display: flex;
      align-items: stretch;
      background: var(--jn-glass-bg);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--jn-glass-border);
      border-radius: 32px;
      box-shadow: 0 20px 40px var(--jn-glass-shadow);
      overflow: hidden;
    }

    .jn-login-left {
      flex: 1;
      position: relative;
      z-index: 2;
      padding: clamp(2rem, 5vw, 4rem);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .jn-login-right {
      flex: 1;
      display: none;
      position: relative;
      overflow: hidden;
      background: var(--jn-panel);
    }

    @media (min-width: 900px) {
      .jn-login-right { display: block; }
      .jn-login-wrapper { flex-direction: row; }
    }

    .jn-login-art {
      width: 100%;
      height: 100%;
      object-fit: cover;
      position: absolute;
      top: 0; left: 0;
      mix-blend-mode: multiply; /* Helps blend the image with the pastel background */
    }
    .jn-login-art-overlay {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: linear-gradient(to right, rgba(250, 227, 209, 0.4) 0%, rgba(250, 227, 209, 0) 100%);
    }

    .jn-login-title {
      font-size: clamp(2.5rem, 4.5vw, 3.5rem);
      font-weight: 800;
      color: var(--jn-accent-1);
      margin: 0 0 0.2rem;
      letter-spacing: -0.02em;
    }

    .jn-login-sub {
      font-weight: 600;
      color: var(--jn-text);
      font-size: clamp(1rem, 1.2vw, 1.1rem);
      margin: 0 0 2rem;
    }

    .jn-login-body-text {
      color: var(--jn-text-muted);
      font-size: 0.95rem;
      line-height: 1.6;
      margin: 0 0 1.5rem;
    }

    .jn-login-error {
      background: rgba(229, 41, 142, 0.1);
      border-left: 4px solid var(--jn-error);
      color: var(--jn-error);
      padding: 0.8rem 1rem;
      border-radius: 8px;
      font-size: 0.9rem;
      margin-bottom: 1.5rem;
      font-weight: 500;
    }

    .jn-social-row {
      display: flex;
      gap: 12px;
      margin-bottom: 2rem;
    }
    
    .jn-social-btn {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 48px;
      border-radius: 12px;
      text-decoration: none !important;
      background: rgba(255, 255, 255, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.8);
      transition: all 0.2s ease;
    }
    .jn-social-btn:hover {
      background: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(107, 63, 160, 0.08);
    }
    .jn-social-btn svg { width: 24px; height: 24px; }
    
    .jn-divider {
      display: flex;
      align-items: center;
      text-align: center;
      color: var(--jn-text-muted);
      font-size: 0.85rem;
      margin-bottom: 2rem;
      font-weight: 500;
    }
    .jn-divider::before, .jn-divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid rgba(107, 63, 160, 0.15);
    }
    .jn-divider:not(:empty)::before { margin-right: .5em; }
    .jn-divider:not(:empty)::after { margin-left: .5em; }

    .jn-login-input-group {
      margin-bottom: 1.2rem;
      position: relative;
    }

    .jn-login-input {
      display: block;
      width: 100%;
      background: rgba(255, 255, 255, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.8);
      color: var(--jn-text);
      font-size: 1rem;
      font-weight: 500;
      padding: 1rem 1.2rem;
      border-radius: 16px;
      outline: none;
      transition: all 0.2s ease;
      font-family: inherit;
    }
    .jn-login-input::placeholder { color: var(--jn-text-muted); opacity: 0.6; font-weight: 400; }
    .jn-login-input:focus {
      background: #fff;
      border-color: var(--jn-accent-1);
      box-shadow: 0 0 0 4px rgba(229, 41, 142, 0.15);
    }

    .jn-login-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      background: linear-gradient(135deg, var(--jn-accent-1), var(--jn-accent-2));
      color: #fff;
      font-weight: 600;
      font-size: 1.05rem;
      letter-spacing: 0.05em;
      padding: 1rem;
      border-radius: 16px;
      border: none;
      cursor: pointer;
      margin-top: 0.5rem;
      transition: all 0.2s ease;
      font-family: inherit;
      position: relative;
      overflow: hidden;
    }
    .jn-login-btn::before {
      content: '';
      position: absolute;
      top: 0; left: -100%; width: 100%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.5s ease;
    }
    .jn-login-btn:hover::before { left: 100%; }
    .jn-login-btn:hover {
      box-shadow: 0 10px 20px -5px rgba(229, 41, 142, 0.4);
      transform: translateY(-2px);
    }
    .jn-login-btn:active { transform: translateY(1px); }
    .jn-login-btn[disabled] { opacity: .7; cursor: wait; filter: grayscale(50%); }
    
    .jn-login-btn-spin {
      display: none;
      width: 1.2em; height: 1.2em;
      margin-right: 0.5em;
      animation: jn-spin 0.8s linear infinite;
    }
    .jn-login-btn.is-loading .jn-login-btn-spin { display: inline-block; }
    @keyframes jn-spin { to { transform: rotate(360deg); } }

    .jn-login-foot {
      margin-top: 1.5rem;
      font-size: 0.95rem;
      color: var(--jn-text-muted);
      text-align: center;
      font-weight: 500;
    }
    .jn-login-foot a {
      color: var(--jn-text);
      text-decoration: none;
      transition: color 0.2s;
    }
    .jn-login-foot a:hover { color: var(--jn-accent-1); text-decoration: underline; }

    [x-cloak] { display: none !important; }
  </style>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body>
  <div class="jn-login-wrapper">
    <span class="jn-blob jn-blob-1"></span>
    <span class="jn-blob jn-blob-2"></span>
    <span class="jn-blob jn-blob-3"></span>
    <span class="jn-blob jn-blob-4"></span>

    <div class="jn-login-left">
      <?php if ( $action === 'login' ) : ?>
        <h1 class="jn-login-title"><?= esc_html__( 'jao nai chan', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></h1>
        <h2 class="jn-login-sub"><?= esc_html__( 'ยินดีต้อนรับสู่ประสบการณ์พรีเมียม', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></h2>

        <?php if ( $jn_notice ) : ?>
          <div class="jn-login-error" style="border-left-color: #48bb78; color: #9ae6b4; background: rgba(72,187,120,0.1);"><?= esc_html( $jn_notice ) ?></div>
        <?php endif; ?>
        <?php if ( $login_error ) : ?>
          <div class="jn-login-error"><?= esc_html( $login_error ) ?></div>
        <?php endif; ?>

        <?php
        $jsl_line_url     = class_exists( 'JSL_Provider_Line' )     ? JSL_Provider_Line::auth_url()     : '';
        $jsl_google_url   = class_exists( 'JSL_Provider_Google' )   ? JSL_Provider_Google::auth_url()   : '';
        $jsl_facebook_url = class_exists( 'JSL_Provider_Facebook' ) ? JSL_Provider_Facebook::auth_url() : '';
        if ( $jsl_line_url || $jsl_google_url || $jsl_facebook_url ) : ?>
          <div class="jn-social-row">
            <?php if ( $jsl_line_url ) : ?>
            <a href="<?= esc_url( $jsl_line_url ) ?>" class="jn-social-btn" title="เข้าสู่ระบบด้วย LINE">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#06C755"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63h2.386c.349 0 .63.285.63.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
            </a>
            <?php endif; ?>
            <?php if ( $jsl_google_url ) : ?>
            <a href="<?= esc_url( $jsl_google_url ) ?>" class="jn-social-btn" title="เข้าสู่ระบบด้วย Google">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#DB4437"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            </a>
            <?php endif; ?>
            <?php if ( $jsl_facebook_url ) : ?>
            <a href="<?= esc_url( $jsl_facebook_url ) ?>" class="jn-social-btn" title="เข้าสู่ระบบด้วย Facebook">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <?php endif; ?>
          </div>
          <div class="jn-divider">หรือเข้าสู่ระบบด้วยอีเมล</div>
        <?php endif; ?>

        <form method="post" action="" x-data="{ loading: false }" @submit="loading = true" @pageshow.window="if ($event.persisted) loading = false">
          <?php wp_nonce_field( 'shop_login', 'shop_login_nonce' ); ?>
          
          <div class="jn-login-input-group">
            <input type="text" name="log" class="jn-login-input" placeholder="<?= esc_attr__( 'Username or Email', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>" autocomplete="username" :readonly="loading" required>
          </div>
          <div class="jn-login-input-group">
            <input type="password" name="pwd" class="jn-login-input" placeholder="<?= esc_attr__( 'Password', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>" :readonly="loading" autocomplete="current-password" required>
          </div>
          <input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_to ) ?>">

          <button type="submit" class="jn-login-btn" :class="{ 'is-loading': loading }" :disabled="loading">
            <svg class="jn-login-btn-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity:.75"></path></svg>
            <?= esc_html__( 'ลงชื่อเข้าใช้', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>
          </button>
        </form>

        <p class="jn-login-foot">
          <a href="<?= esc_url( wp_lostpassword_url() ) ?>"><?= esc_html__( 'ลืมรหัสผ่านใช่หรือไม่?', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></a>
        </p>

      <?php elseif ( $action === 'lostpassword' ) : ?>
        <h1 class="jn-login-title"><?= esc_html__( 'jao nai chan', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></h1>
        <h2 class="jn-login-sub"><?= esc_html__( 'ลืมรหัสผ่าน?', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></h2>

        <?php if ( $lp_success ) : ?>
          <p class="jn-login-body-text"><?= esc_html__( 'ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว กรุณาตรวจสอบกล่องขาเข้า', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></p>
          <p class="jn-login-foot">
            <a href="<?= esc_url( home_url( JN_SHOP_LOGIN_PATH ) ) ?>">&larr; <?= esc_html__( 'กลับหน้าเข้าสู่ระบบ', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></a>
          </p>
        <?php else : ?>
          <p class="jn-login-body-text"><?= esc_html__( 'กรอก Username หรือ Email เพื่อรับลิงก์รีเซ็ตรหัสผ่าน', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></p>
          <?php if ( $lp_error ) : ?>
            <div class="jn-login-error"><?= esc_html( $lp_error ) ?></div>
          <?php endif; ?>

          <form method="post" action="" x-data="{ loading: false }" @submit="loading = true" @pageshow.window="if ($event.persisted) loading = false">
            <?php wp_nonce_field( 'jn_lostpassword', 'jn_lostpass_nonce' ); ?>
            <div class="jn-login-input-group">
              <input type="text" name="user_login" class="jn-login-input" placeholder="<?= esc_attr__( 'Username or Email', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>" autocomplete="username email" :readonly="loading" required>
            </div>
            <button type="submit" class="jn-login-btn" :class="{ 'is-loading': loading }" :disabled="loading">
              <svg class="jn-login-btn-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity:.75"></path></svg>
              <?= esc_html__( 'ส่งลิงก์รีเซ็ต', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>
            </button>
          </form>

          <p class="jn-login-foot">
            <a href="<?= esc_url( home_url( JN_SHOP_LOGIN_PATH ) ) ?>">&larr; <?= esc_html__( 'กลับหน้าเข้าสู่ระบบ', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></a>
          </p>
        <?php endif; ?>

      <?php elseif ( $action === 'rp' || $action === 'resetpass' ) : ?>
        <h1 class="jn-login-title"><?= esc_html__( 'jao nai chan', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></h1>
        <h2 class="jn-login-sub"><?= esc_html__( 'ตั้งรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></h2>
        <p class="jn-login-body-text"><?= esc_html__( 'กรอกรหัสผ่านใหม่สำหรับบัญชีของคุณ', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?></p>

        <?php if ( $rp_error ) : ?>
          <div class="jn-login-error"><?= esc_html( $rp_error ) ?></div>
        <?php endif; ?>

        <form method="post" action="" x-data="{ loading: false }" @submit="loading = true" @pageshow.window="if ($event.persisted) loading = false">
          <?php wp_nonce_field( 'jn_reset_password', 'jn_reset_nonce' ); ?>
          <div class="jn-login-input-group">
            <input type="password" name="pass1" class="jn-login-input" placeholder="<?= esc_attr__( 'รหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>" autocomplete="new-password" :readonly="loading" required>
          </div>
          <div class="jn-login-input-group">
            <input type="password" name="pass2" class="jn-login-input" placeholder="<?= esc_attr__( 'ยืนยันรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>" autocomplete="new-password" :readonly="loading" required>
          </div>
          <button type="submit" class="jn-login-btn" :class="{ 'is-loading': loading }" :disabled="loading">
            <svg class="jn-login-btn-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" style="opacity:.75"></path></svg>
            <?= esc_html__( 'ยืนยันรหัสผ่านใหม่', $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan' ) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <div class="jn-login-right" aria-hidden="<?= empty( $shop_login_image['src'] ) ? 'true' : 'false' ?>">
      <?php if ( ! empty( $shop_login_image['src'] ) ) : ?>
        <img class="jn-login-art" src="<?= esc_url( $shop_login_image['src'] ) ?>" alt="<?= esc_attr( $shop_login_image['alt'] ) ?>" style="width: <?= esc_attr( $shop_login_image['width'] ) ?>; height: <?= esc_attr( $shop_login_image['height'] ) ?>;">
        <div class="jn-login-art-overlay"></div>
      <?php endif; ?>
    </div>
  </div>
  <?php wp_footer(); ?>
</body>
</html>