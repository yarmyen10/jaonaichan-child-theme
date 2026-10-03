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
    // wp_validate_redirect() returns '' for '' — its fallback only applies to URLs that fail validation — so pass the default in ourselves
    $redirect_to = wp_validate_redirect( $redirect_to ?: $default_redirect, $default_redirect );

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
        && isset( $_GET['jn_error'] )   // NOT "error": that query var never reaches the template (stripped before it)
        && sanitize_key( $_GET['jn_error'] ) === 'expiredkey'
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
            [ 'action' => 'lostpassword', 'jn_error' => 'expiredkey' ],
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
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Login page design — from the designer's package (jaonaichan-login). Design tokens + component styles; the page
       dequeues every site stylesheet, so these generic class names cannot clash with anything else. */
    :root {
      color-scheme: light;

      --cream:      #FDF5EC;
      --cream-2:    #FFF9F0;

      --pink-50:    #FFF4F1;
      --pink-100:   #FFE4E9;
      --pink-200:   #FFCFDA;
      --pink-300:   #FFB0C4;
      --pink-400:   #FF8FAF;
      --pink-500:   #F26E96;
      --pink-600:   #D9497A;

      --yellow-50:  #FFFBEE;
      --yellow-100: #FFF3C9;
      --yellow-200: #FFE79A;
      --yellow-300: #FFD866;

      --peach-100:  #FFE1D0;

      --ink:        #4A2E33;
      --ink-soft:   #7E5F65;
      --ink-mute:   #B79FA4;

      --cat-line:   #B89B87;

      --radius-lg:  32px;
      --radius-md:  16px;
      --radius-sm:  14px;

      --shadow-card:
        0 1px 0 rgba(255,255,255,.9) inset,
        0 0 0 1px rgba(255, 216, 102, 0.18),
        0 30px 60px -20px rgba(217, 73, 122, 0.22),
        0 12px 32px -12px rgba(184, 155, 135, 0.18);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    /* wp_footer() still prints plugin markup (ModernCart's drawer, …) and this page loads none of their CSS → hide everything that is not the page */
    body > *:not(.bg):not(.cat-bg):not(.deco):not(.card) { display: none !important; }

    body {
      font-family: 'IBM Plex Sans Thai', 'Fredoka', system-ui, -apple-system, sans-serif;
      color: var(--ink);
      background: var(--cream);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 20px;
      position: relative;
      overflow-x: hidden;
    }

    /* ---------- background ---------- */
    .bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .blob { position: absolute; border-radius: 50%; filter: blur(70px); }
    .blob.b1 { width: 520px; height: 520px; top: -140px; left: -120px; background: radial-gradient(circle, var(--yellow-200) 0%, transparent 65%); opacity: .85; }
    .blob.b2 { width: 560px; height: 560px; bottom: -180px; right: -160px; background: radial-gradient(circle, var(--pink-200) 0%, transparent 65%); opacity: .8; }
    .blob.b3 { width: 380px; height: 380px; top: 10%; right: 10%; background: radial-gradient(circle, var(--yellow-100) 0%, transparent 70%); opacity: .9; }
    .blob.b4 { width: 340px; height: 340px; bottom: 10%; left: 8%; background: radial-gradient(circle, var(--peach-100) 0%, transparent 70%); opacity: .8; }

    .cat-bg { position: fixed; inset: 0; width: 100vw; height: 100vh; z-index: 1; pointer-events: none; }
    .cat-bg .stroke { fill: none; stroke: var(--cat-line); stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; opacity: .78; }
    .cat-bg .thin { stroke-width: 1.6; opacity: .6; }

    .deco { position: fixed; z-index: 2; pointer-events: none; animation: bob 4s ease-in-out infinite; }
    .d1 { top: 12%; left: 12%; animation-delay: 0s; }
    .d2 { top: 20%; right: 14%; animation-delay: 1.2s; }
    .d3 { bottom: 18%; left: 10%; animation-delay: 0.6s; }
    .d4 { bottom: 22%; right: 12%; animation-delay: 1.8s; }
    .d5 { top: 40%; left: 6%;  animation-delay: 2.4s; }
    .d6 { top: 45%; right: 5%; animation-delay: 0.3s; }
    @keyframes bob { 0%, 100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-10px) rotate(8deg); } }

    /* ---------- card ---------- */
    .card {
      position: relative; z-index: 5;
      width: min(560px, 100%);
      padding: 48px 56px 40px;
      border-radius: var(--radius-lg);
      background: rgba(255, 255, 255, 0.55);
      backdrop-filter: blur(24px) saturate(1.4);
      -webkit-backdrop-filter: blur(24px) saturate(1.4);
      border: 1.5px solid rgba(255, 255, 255, 0.8);
      box-shadow: var(--shadow-card);
    }
    .card::before {
      content: ''; position: absolute; inset: 0; border-radius: var(--radius-lg);
      background: radial-gradient(circle at 50% 0%, rgba(255,232,168,.35) 0%, transparent 55%);
      pointer-events: none;
    }

    .header { text-align: center; margin-bottom: 30px; position: relative; }
    .brand-mark {
      width: 52px; height: 52px; margin: 0 auto 16px; border-radius: var(--radius-md);
      background: linear-gradient(135deg, var(--pink-400) 0%, var(--yellow-300) 100%);
      display: grid; place-items: center;
      box-shadow: 0 10px 22px -4px rgba(242, 110, 150, .45), 0 0 0 5px rgba(255, 255, 255, .55);
    }
    .brand-mark svg { width: 24px; height: 24px; color: white; }

    h1 { font-family: 'Fredoka', 'IBM Plex Sans Thai', sans-serif; font-size: 22px; font-weight: 600; line-height: 1.35; letter-spacing: 0.2px; color: var(--ink); }
    h1 .accent {
      background: linear-gradient(120deg, var(--pink-600) 0%, var(--pink-400) 50%, #E9A94C 100%);
      -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .subtitle { color: var(--ink-soft); font-size: 13.5px; font-weight: 400; margin-top: 10px; line-height: 1.55; }

    /* ---------- form ---------- */
    form { display: flex; flex-direction: column; gap: 16px; }

    .form-alert {
      padding: 12px 14px; border-radius: var(--radius-sm); font-size: 13px; line-height: 1.4;
      background: rgba(255, 220, 220, 0.6); border: 1.5px solid rgba(217, 73, 122, 0.35); color: var(--pink-600);
      backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    .form-alert.success { background: rgba(220, 255, 230, 0.6); border-color: rgba(76, 175, 80, 0.45); color: #2e7d32; }
    .header + .form-alert, .form-alert + form, .form-alert + .form-alert { margin-bottom: 16px; }   /* alert rendered outside a form */

    .field label { display: block; font-size: 13px; color: var(--ink-soft); margin-bottom: 7px; font-weight: 500; letter-spacing: 0.2px; }
    .field-input { position: relative; }
    .field input {
      width: 100%; padding: 14px 18px 14px 46px;
      background: rgba(255, 255, 255, .72); border: 1.5px solid rgba(184, 155, 135, .22); border-radius: var(--radius-sm);
      font-family: inherit; font-size: 14px; color: var(--ink); outline: none; transition: all .2s ease;
      backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    .field input.has-toggle { padding-right: 50px; }
    .field input::placeholder { color: var(--ink-mute); font-weight: 300; }
    .field input:focus { border-color: var(--pink-400); background: rgba(255, 255, 255, .92); box-shadow: 0 0 0 4px rgba(255, 143, 175, .18); }
    .field input[aria-invalid="true"] { border-color: var(--pink-500); box-shadow: 0 0 0 4px rgba(242, 110, 150, .15); }
    .field input[readonly] { opacity: .75; }

    .field-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--ink-mute); width: 18px; height: 18px; transition: color .2s ease; pointer-events: none; }
    .field input:focus ~ .field-icon { color: var(--pink-500); }

    .password-toggle {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: transparent; border: none; padding: 6px; cursor: pointer; color: var(--ink-mute);
      border-radius: 8px; transition: all .2s ease; display: grid; place-items: center;
    }
    .password-toggle:hover { color: var(--pink-500); background: rgba(255, 143, 175, .1); }
    .password-toggle:focus-visible { outline: 2px solid var(--pink-400); outline-offset: 1px; }
    .password-toggle svg { width: 18px; height: 18px; }
    .password-toggle.showing .icon-eye { opacity: .55; }

    /* ---------- submit ---------- */
    .login-btn {
      margin-top: 8px; width: 100%; padding: 15px; border: none; border-radius: var(--radius-sm);
      background: linear-gradient(120deg, var(--pink-500) 0%, var(--pink-400) 55%, #FFC078 100%);
      color: white; font-family: inherit; font-size: 15px; font-weight: 600; letter-spacing: 0.5px; cursor: pointer;
      transition: all .25s ease; position: relative; overflow: hidden;
      box-shadow: 0 12px 26px -6px rgba(242, 110, 150, .5);
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .login-btn::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,.35) 50%, transparent 70%);
      transform: translateX(-100%); transition: transform .6s ease;
    }
    .login-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 16px 32px -6px rgba(242, 110, 150, .6); }
    .login-btn:hover:not(:disabled)::before { transform: translateX(100%); }
    .login-btn:active:not(:disabled) { transform: translateY(0); }
    .login-btn:focus-visible { outline: 3px solid rgba(242, 110, 150, .45); outline-offset: 2px; }
    .login-btn:disabled { opacity: .7; cursor: not-allowed; }

    .btn-spinner { display: none; width: 16px; height: 16px; border: 2.5px solid rgba(255,255,255,.4); border-top-color: white; border-radius: 50%; animation: spin .7s linear infinite; }
    .login-btn.loading .btn-label { opacity: .7; }
    .login-btn.loading .btn-spinner { display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ---------- help links ---------- */
    .help-note { text-align: center; font-size: 12.5px; color: var(--ink-soft); margin-top: 16px; line-height: 1.55; }
    .help-note + .help-note { margin-top: 8px; }
    .help-note a { color: var(--pink-600); text-decoration: none; font-weight: 600; border-bottom: 1.5px dashed var(--pink-300); padding-bottom: 1px; transition: all .2s ease; }
    .help-note a:hover { color: var(--pink-500); border-bottom-style: solid; }

    /* ---------- divider + social ---------- */
    .divider { display: flex; align-items: center; gap: 12px; margin: 24px 0 16px; color: var(--ink-mute); font-size: 12px; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(184, 155, 135, .25), transparent); }

    .social-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }   /* 2 buttons side by side on the card, stacked on a phone; copes with 1 or 3 providers */
    .social-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;
      background: rgba(255, 255, 255, .72); border: 1.5px solid rgba(184, 155, 135, .20); border-radius: var(--radius-sm);
      font-family: inherit; font-size: 13.5px; font-weight: 500; color: var(--ink); text-decoration: none; cursor: pointer;
      transition: all .2s ease; backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    .social-btn:hover { border-color: var(--pink-300); background: rgba(255, 255, 255, .92); transform: translateY(-1px); box-shadow: 0 6px 14px -4px rgba(242, 110, 150, .2); }
    .social-btn:active { transform: translateY(0); }
    .social-btn:focus-visible { outline: 2px solid var(--pink-400); outline-offset: 2px; }
    .social-btn svg { width: 18px; height: 18px; }

    @media (max-width: 640px) {
      .card { padding: 40px 30px 32px; border-radius: 26px; }
      h1 { font-size: 19px; }
      .deco { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
      .deco { animation: none; }
      .login-btn::before { transition: none; }
      .btn-spinner { animation-duration: 1.5s; }
    }
  </style>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body>

  <!-- decorative background -->
  <div class="bg" aria-hidden="true">
    <div class="blob b1"></div>
    <div class="blob b2"></div>
    <div class="blob b3"></div>
    <div class="blob b4"></div>
  </div>

  <svg class="cat-bg" viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <path class="stroke" d="M 0 620 C 100 620, 200 620, 320 620 C 380 620, 430 618, 470 610"/>
    <path class="stroke" d="M 470 610 C 465 585, 452 555, 460 528 C 468 500, 490 480, 518 478 C 545 478, 565 495, 568 520 C 570 540, 555 555, 535 555 C 520 555, 510 545, 512 532 C 514 522, 525 518, 532 525 C 538 532, 532 545, 522 542 C 518 540, 518 535, 522 532 C 528 545, 545 548, 555 538 C 568 525, 566 505, 550 495 C 532 486, 510 490, 500 508 C 494 522, 500 540, 512 552 C 528 572, 555 590, 585 600 C 640 618, 720 618, 800 610 C 880 602, 960 588, 1030 570 C 1080 558, 1120 545, 1145 528 C 1160 518, 1165 505, 1160 490 C 1155 475, 1155 460, 1160 445 L 1155 405 L 1180 445 L 1210 400 L 1230 448 C 1250 462, 1265 480, 1275 500 C 1285 520, 1288 540, 1282 558 C 1275 578, 1258 590, 1235 596 C 1210 602, 1180 604, 1150 606"/>
    <path class="stroke" d="M 1150 606 C 1120 612, 1080 616, 1040 618 C 960 622, 860 622, 780 622 C 700 622, 620 620, 550 618"/>
    <path class="stroke" d="M 1200 618 C 1300 620, 1400 620, 1500 620 C 1550 620, 1600 620, 1700 620"/>
    <path class="stroke thin" d="M 1170 478 q 8 5 16 -1"/>
    <path class="stroke thin" d="M 1220 500 q 3 4 -2 6"/>
    <path class="stroke thin" d="M 1245 505 q 20 -3 42 -8" opacity=".45"/>
    <path class="stroke thin" d="M 1245 512 q 20 2 42 2"  opacity=".45"/>
    <path class="stroke thin" d="M 1130 618 q -4 -14 -14 -18 q -12 -3 -18 6 q -4 8 2 14" opacity=".55"/>
    <path class="stroke thin" d="M 100 220 q 40 -20 90 -10 q 30 8 50 30" opacity=".25"/>
    <path class="stroke thin" d="M 1500 200 q -40 -18 -90 -8 q -30 8 -50 28" opacity=".25"/>
  </svg>

  <svg class="deco d1" width="20" height="20" viewBox="0 0 24 24" fill="#FFD866" aria-hidden="true"><path d="M12 2l1.6 6.6L20 10l-6.4 1.4L12 18l-1.6-6.6L4 10l6.4-1.4z"/></svg>
  <svg class="deco d2" width="22" height="22" viewBox="0 0 24 24" fill="#FFB0C4" aria-hidden="true"><path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10z"/></svg>
  <svg class="deco d3" width="26" height="26" viewBox="0 0 32 32" aria-hidden="true">
    <g fill="#FFE79A"><circle cx="16" cy="7" r="4"/><circle cx="16" cy="25" r="4"/><circle cx="7" cy="16" r="4"/><circle cx="25" cy="16" r="4"/></g>
    <circle cx="16" cy="16" r="3" fill="#FFB0C4"/>
  </svg>
  <svg class="deco d4" width="16" height="16" viewBox="0 0 24 24" fill="#F26E96" aria-hidden="true"><path d="M12 2l1.6 6.6L20 10l-6.4 1.4L12 18l-1.6-6.6L4 10l6.4-1.4z"/></svg>
  <svg class="deco d5" width="14" height="14" viewBox="0 0 24 24" fill="#FFD866" aria-hidden="true"><path d="M12 2l1.6 6.6L20 10l-6.4 1.4L12 18l-1.6-6.6L4 10l6.4-1.4z"/></svg>
  <svg class="deco d6" width="18" height="18" viewBox="0 0 24 24" fill="#FFB0C4" aria-hidden="true"><path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10z"/></svg>

  <?php
  // icons shared by the three views
  $jn_icon_user = '<svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>';
  $jn_icon_lock = '<svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>';
  $jn_toggle    = '<button type="button" class="password-toggle" :class="{ showing: show }" @click="show = !show" :aria-label="show ? \'Hide password\' : \'Show password\'" :aria-pressed="show.toString()" aria-label="Show password"><svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>';
  $jn_td        = $_ENV['TEXTDOMAIN_NAME'] ?? 'jaonaichan';
  ?>

  <main class="card" role="main">

    <?php if ( $action === 'login' ) : ?>
      <div class="header">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10z"/></svg>
        </div>
        <h1>Welcome to<br><span class="accent">JAONAICHAN × JAONAITOOB</span></h1>
        <p class="subtitle"><?= esc_html__( 'ยินดีต้อนรับสมาชิกคนสำคัญของเรา ✿', $jn_td ) ?></p>
      </div>

      <form method="post" action="" x-data="{ loading: false, show: false }" @submit="loading = true" @pageshow.window="if ($event.persisted) loading = false">
        <?php wp_nonce_field( 'shop_login', 'shop_login_nonce' ); ?>

        <?php if ( $jn_notice ) : ?>
          <div class="form-alert success" role="status"><?= esc_html( $jn_notice ) ?></div>
        <?php endif; ?>
        <?php if ( $login_error ) : ?>
          <div class="form-alert" role="alert"><?= esc_html( $login_error ) ?></div>
        <?php endif; ?>

        <div class="field">
          <label for="jn-log"><?= esc_html__( 'Username หรือ Email', $jn_td ) ?></label>
          <div class="field-input">
            <input id="jn-log" type="text" name="log" placeholder="<?= esc_attr__( 'ระบุรหัสสมาชิก เช่น JNC0000', $jn_td ) ?>" autocomplete="username" :readonly="loading" required>
            <?= $jn_icon_user ?>
          </div>
        </div>

        <div class="field">
          <label for="jn-pwd">Password</label>
          <div class="field-input">
            <input id="jn-pwd" :type="show ? 'text' : 'password'" type="password" name="pwd" class="has-toggle" placeholder="••••••••" autocomplete="current-password" :readonly="loading" required>
            <?= $jn_icon_lock ?>
            <?= $jn_toggle ?>
          </div>
        </div>

        <input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_to ) ?>">

        <button class="login-btn" type="submit" :class="{ loading: loading }" :disabled="loading">
          <span class="btn-label"><?= esc_html__( 'เข้าสู่ระบบ', $jn_td ) ?></span>
          <span class="btn-spinner" aria-hidden="true"></span>
        </button>
      </form>

      <p class="help-note"><a href="<?= esc_url( wp_lostpassword_url() ) ?>"><?= esc_html__( 'ลืมรหัสผ่านใช่หรือไม่?', $jn_td ) ?></a></p>
      <p class="help-note">
        <?= esc_html__( 'พบปัญหาการเข้าสู่ระบบ?', $jn_td ) ?>
        <a href="https://lin.ee/g5rPVek" target="_blank" rel="noopener noreferrer"><?= esc_html__( 'คลิกที่นี่', $jn_td ) ?></a>
      </p>

      <?php
      $jsl_line_url     = class_exists( 'JSL_Provider_Line' )     ? JSL_Provider_Line::auth_url()     : '';
      $jsl_google_url   = class_exists( 'JSL_Provider_Google' )   ? JSL_Provider_Google::auth_url()   : '';
      $jsl_facebook_url = class_exists( 'JSL_Provider_Facebook' ) ? JSL_Provider_Facebook::auth_url() : '';
      if ( $jsl_line_url || $jsl_google_url || $jsl_facebook_url ) : ?>
        <div class="divider"><span><?= esc_html__( 'หรือเข้าใช้งานด้วย', $jn_td ) ?></span></div>
        <div class="social-row">
          <?php if ( $jsl_line_url ) : ?>
          <a href="<?= esc_url( $jsl_line_url ) ?>" class="social-btn" title="เข้าสู่ระบบด้วย LINE">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#06C755"/><path d="M12 5.5c-4.14 0-7.5 2.69-7.5 6 0 2.97 2.73 5.46 6.42 5.93.25.05.59.16.68.37.08.19.05.48.03.68l-.11.66c-.03.19-.15.76.67.41.82-.35 4.43-2.61 6.05-4.47 1.12-1.23 1.66-2.48 1.66-3.87 0-3.31-3.36-6-7.5-6z" fill="white"/></svg>
            <span>LINE</span>
          </a>
          <?php endif; ?>
          <?php if ( $jsl_google_url ) : ?>
          <a href="<?= esc_url( $jsl_google_url ) ?>" class="social-btn" title="เข้าสู่ระบบด้วย Google">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            <span>Google</span>
          </a>
          <?php endif; ?>
          <?php if ( $jsl_facebook_url ) : ?>
          <a href="<?= esc_url( $jsl_facebook_url ) ?>" class="social-btn" title="เข้าสู่ระบบด้วย Facebook">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            <span>Facebook</span>
          </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php elseif ( $action === 'lostpassword' ) : ?>
      <div class="header">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10z"/></svg>
        </div>
        <h1><?= esc_html__( 'ลืมรหัสผ่าน?', $jn_td ) ?></h1>
        <?php if ( ! $lp_success ) : ?><p class="subtitle"><?= esc_html__( 'กรอก Username หรือ Email เพื่อรับลิงก์รีเซ็ตรหัสผ่าน', $jn_td ) ?></p><?php endif; ?>
      </div>

      <?php if ( $lp_success ) : ?>
        <div class="form-alert success" role="status"><?= esc_html__( 'ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว กรุณาตรวจสอบกล่องขาเข้า', $jn_td ) ?></div>
      <?php else : ?>
        <form method="post" action="" x-data="{ loading: false }" @submit="loading = true" @pageshow.window="if ($event.persisted) loading = false">
          <?php wp_nonce_field( 'jn_lostpassword', 'jn_lostpass_nonce' ); ?>
          <?php if ( $lp_error ) : ?>
            <div class="form-alert" role="alert"><?= esc_html( $lp_error ) ?></div>
          <?php endif; ?>
          <div class="field">
            <label for="jn-user-login"><?= esc_html__( 'Username หรือ Email', $jn_td ) ?></label>
            <div class="field-input">
              <input id="jn-user-login" type="text" name="user_login" placeholder="<?= esc_attr__( 'ระบุรหัสสมาชิก เช่น JNC0000', $jn_td ) ?>" autocomplete="username email" :readonly="loading" required>
              <?= $jn_icon_user ?>
            </div>
          </div>
          <button class="login-btn" type="submit" :class="{ loading: loading }" :disabled="loading">
            <span class="btn-label"><?= esc_html__( 'ส่งลิงก์รีเซ็ต', $jn_td ) ?></span>
            <span class="btn-spinner" aria-hidden="true"></span>
          </button>
        </form>
      <?php endif; ?>

      <p class="help-note"><a href="<?= esc_url( home_url( JN_SHOP_LOGIN_PATH ) ) ?>">&larr; <?= esc_html__( 'กลับหน้าเข้าสู่ระบบ', $jn_td ) ?></a></p>

    <?php elseif ( $action === 'rp' || $action === 'resetpass' ) : ?>
      <div class="header">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10z"/></svg>
        </div>
        <h1><?= esc_html__( 'ตั้งรหัสผ่านใหม่', $jn_td ) ?></h1>
        <p class="subtitle"><?= esc_html__( 'กรอกรหัสผ่านใหม่สำหรับบัญชีของคุณ', $jn_td ) ?></p>
      </div>

      <form method="post" action="" x-data="{ loading: false, show: false }" @submit="loading = true" @pageshow.window="if ($event.persisted) loading = false">
        <?php wp_nonce_field( 'jn_reset_password', 'jn_reset_nonce' ); ?>
        <?php if ( $rp_error ) : ?>
          <div class="form-alert" role="alert"><?= esc_html( $rp_error ) ?></div>
        <?php endif; ?>
        <div class="field">
          <label for="jn-pass1"><?= esc_html__( 'รหัสผ่านใหม่', $jn_td ) ?></label>
          <div class="field-input">
            <input id="jn-pass1" :type="show ? 'text' : 'password'" type="password" name="pass1" class="has-toggle" placeholder="<?= esc_attr__( 'รหัสผ่านใหม่', $jn_td ) ?>" autocomplete="new-password" :readonly="loading" required>
            <?= $jn_icon_lock ?>
            <?= $jn_toggle ?>
          </div>
        </div>
        <div class="field">
          <label for="jn-pass2"><?= esc_html__( 'ยืนยันรหัสผ่านใหม่', $jn_td ) ?></label>
          <div class="field-input">
            <input id="jn-pass2" :type="show ? 'text' : 'password'" type="password" name="pass2" class="has-toggle" placeholder="<?= esc_attr__( 'ยืนยันรหัสผ่านใหม่', $jn_td ) ?>" autocomplete="new-password" :readonly="loading" required>
            <?= $jn_icon_lock ?>
          </div>
        </div>
        <button class="login-btn" type="submit" :class="{ loading: loading }" :disabled="loading">
          <span class="btn-label"><?= esc_html__( 'ยืนยันรหัสผ่านใหม่', $jn_td ) ?></span>
          <span class="btn-spinner" aria-hidden="true"></span>
        </button>
      </form>
    <?php endif; ?>

  </main>
  <?php wp_footer(); ?>
</body>
</html>
