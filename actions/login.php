<?php
declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = [];
if ($method === 'POST') {
    // Support both JSON body and standard form POST requests
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : [];
    } else {
        $input = $_POST;
    }
}

$remember = !empty($input['remember']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
if ($remember) ini_set('session.gc_maxlifetime', '2592000');
$cookie = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $remember ? 2592000 : 0,
    'path' => $cookie['path'] ?: '/',
    'domain' => $cookie['domain'],
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

function loginRespond(int $status, array $payload, bool $isAjax = false): void
{
    if ($isAjax || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Non-AJAX fallback (direct form submit)
    if ($status === 200 && isset($payload['redirect'])) {
        header('Location: ' . $payload['redirect']);
        exit;
    }
}

function loginWhatsapp(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') return null;
    if (substr($digits, 0, 1) === '0') {
        $digits = '62' . substr($digits, 1);
    } elseif (substr($digits, 0, 2) !== '62') {
        $digits = '62' . $digits;
    }
    return strlen($digits) >= 10 && strlen($digits) <= 15 ? '+' . $digits : null;
}

function loginRedirect(string $requested): string
{
    $allowed = ['index.html', 'reservation-list.html', 'booking-services.html', 'booking-datetime.html', 'dashboard.php'];
    return in_array($requested, $allowed, true) ? $requested : 'index.html';
}

$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

if ($method === 'GET' && isset($_GET['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'authenticated' => !empty($_SESSION['customer_id']),
        'customer_name' => $_SESSION['customer_name'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$errorMessage = '';

if ($method === 'POST') {
    $identifier = trim((string) ($input['identifier'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    
    if ($identifier === '' || $password === '' || strlen($password) > 4096) {
        $errorMessage = 'Masukkan email atau nomor WhatsApp dan kata sandi.';
        if ($isAjaxRequest) loginRespond(422, ['error' => $errorMessage], true);
    } else {
        $email = null;
        $whatsapp = null;
        if (str_contains($identifier, '@')) {
            if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'Format email tidak valid.';
                if ($isAjaxRequest) loginRespond(422, ['error' => $errorMessage], true);
            }
            $email = strtolower($identifier);
        } else {
            $whatsapp = loginWhatsapp($identifier);
            if (!$whatsapp) {
                $errorMessage = 'Masukkan email atau nomor WhatsApp yang valid.';
                if ($isAjaxRequest) loginRespond(422, ['error' => $errorMessage], true);
            }
        }

        if ($errorMessage === '') {
            require_once __DIR__ . '/config/database.php';
            try {
                $pdo = getDatabaseConnection();
                $statement = $pdo->prepare(
                    'SELECT id, name, password_hash
                     FROM customers
                     WHERE email = :email_identifier OR whatsapp = :whatsapp_identifier
                     LIMIT 1'
                );
                $statement->execute([
                    'email_identifier' => $email,
                    'whatsapp_identifier' => $whatsapp,
                ]);
                $customer = $statement->fetch();
                
                if (!$customer || !password_verify($password, $customer['password_hash'])) {
                    $errorMessage = 'Email/nomor WhatsApp atau kata sandi tidak cocok.';
                    if ($isAjaxRequest) loginRespond(401, ['error' => $errorMessage], true);
                } else {
                    session_regenerate_id(true);
                    $_SESSION['customer_id'] = (int) $customer['id'];
                    $_SESSION['customer_name'] = $customer['name'];
                    
                    $redirectTarget = loginRedirect((string) ($input['next'] ?? $_GET['next'] ?? 'index.html'));
                    loginRespond(200, [
                        'customer_name' => $customer['name'],
                        'redirect' => $redirectTarget,
                    ], $isAjaxRequest);
                }
            } catch (Throwable $error) {
                error_log('Pemon login error: ' . $error->getMessage());
                $errorMessage = 'Akun belum dapat diperiksa. Periksa koneksi basis data.';
                if ($isAjaxRequest) loginRespond(500, ['error' => $errorMessage], true);
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <meta name="description" content="Masuk ke akun Pemon Barbershop untuk mengelola reservasi dan riwayat pangkas rambut.">
  <title>Masuk — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script src="login.js" defer></script>
</head>
<body>
  <main class="auth-page">
    <div class="auth-layout">
    <section class="auth-panel" aria-labelledby="login-title">
      <a class="auth-mark" href="index.html" aria-label="Pemon Barbershop, beranda">
        <span class="auth-mark-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.2 8.2 12 12M8.2 15.8 20 4M13 12l-2.2 2.2"/></svg>
        </span>
        <span class="auth-brand">pemon <small>BARBERSHOP</small></span>
      </a>

      <header class="auth-heading">
        <p class="auth-eyebrow">AKUN PEMON BARBERSHOP</p>
        <h1 id="login-title">Masuk ke Akun Anda</h1>
        <p>Masuk untuk mengelola reservasi dan riwayat pangkas rambut.</p>
      </header>

      <?php if (!empty($errorMessage)): ?>
        <div class="auth-error-banner" style="color: #b91c1c; background: #fee2e2; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;">
          <?= htmlspecialchars($errorMessage) ?>
        </div>
      <?php endif; ?>

      <form id="login-form" class="auth-form" method="POST" action="login.php<?= isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : '' ?>" novalidate>
        <label class="field-label" for="login-identifier">Email atau Nomor WhatsApp</label>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
          <input id="login-identifier" name="identifier" type="text" autocomplete="username" placeholder="nama@email.com / 0812xxxx" value="<?= htmlspecialchars($input['identifier'] ?? '') ?>" required>
        </div>

        <div class="password-label-row">
          <label class="field-label" for="login-password">Kata Sandi</label>
          <button class="text-link" id="forgot-password" type="button">Lupa kata sandi?</button>
        </div>
        <div class="input-wrap">
          <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg>
          <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
          <button class="password-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c5.2 0 8.8 4.8 9.5 6-.3.5-1.4 2.1-3.6 3.6M6.2 6.2C3.9 7.8 2.8 9.5 2.5 11c.7 1.2 4.3 6 9.5 6 1 0 2-.2 2.8-.5"/></svg>
          </button>
        </div>

        <label class="remember-option"><input type="checkbox" name="remember" id="remember-me" <?= !empty($input['remember']) ? 'checked' : '' ?>><span>Ingat saya</span></label>
        <button class="primary-button" type="submit" id="login-submit"><span>Masuk</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
      </form>

      <div class="auth-divider"><span>atau masuk dengan</span></div>
      <button class="google-button" type="button" id="google-login"><span class="google-g" aria-hidden="true">G</span>Lanjutkan dengan Google</button>

      <p class="auth-switch">Belum punya akun? <a id="register-link" href="register.html">Daftar Sekarang</a></p>
    </section>
    <nav class="auth-footer" aria-label="Tautan bantuan">
      <a href="#bantuan" data-info="Hubungi layanan pelanggan Pemon Barbershop untuk mendapatkan bantuan.">Bantuan Layanan</a>
      <i aria-hidden="true"></i>
      <a href="#privasi" data-info="Kebijakan privasi akan tersedia di halaman informasi Pemon Barbershop.">Kebijakan Privasi</a>
      <i aria-hidden="true"></i>
      <a href="#ketentuan" data-info="Syarat dan ketentuan akan tersedia di halaman informasi Pemon Barbershop.">Syarat &amp; Ketentuan</a>
    </nav>
    </div>
  </main>
  <div class="auth-toast" id="auth-toast" role="status" aria-live="polite"></div>
</body>
</html>
