<?php
declare(strict_types=1);

session_start();

function registerRespond(int $status, array $payload, bool $isAjax = false): void
{
    if ($isAjax || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

function registerWhatsapp(string $value): ?string
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

function registerRedirect(string $requested): string
{
    $allowed = ['index.html', 'reservation-list.html', 'booking-services.html', 'booking-datetime.html', 'dashboard.php', 'booking-confirmation.html'];
    return in_array($requested, $allowed, true) ? $requested : 'dashboard.php';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

$errorMessage = '';

if ($method === 'POST') {
    $input = [];
    if ($isAjaxRequest) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : [];
    } else {
        $input = $_POST;
        // Map checkbox state for direct form post
        $input['terms_accepted'] = isset($_POST['terms_accepted']) || isset($_POST['accept_terms']);
    }

    $name = trim((string) ($input['name'] ?? ''));
    $identifier = trim((string) ($input['identifier'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');
    $termsAccepted = !empty($input['terms_accepted']);

    if ($name === '' || strlen($name) > 150 || $identifier === '' || strlen($password) < 8 || strlen($password) > 4096 || $password !== $confirmPassword || !$termsAccepted) {
        $errorMessage = 'Periksa nama dan kontak, pastikan kata sandi minimal 8 karakter serta sama dengan ulangannya, lalu setujui Syarat & Ketentuan.';
        if ($isAjaxRequest) registerRespond(422, ['error' => $errorMessage], true);
    } else {
        $email = null;
        $whatsapp = null;
        if (str_contains($identifier, '@')) {
            if (!filter_var($identifier, FILTER_VALIDATE_EMAIL) || strlen($identifier) > 190) {
                $errorMessage = 'Format email tidak valid.';
                if ($isAjaxRequest) registerRespond(422, ['error' => $errorMessage], true);
            }
            $email = strtolower($identifier);
        } else {
            $whatsapp = registerWhatsapp($identifier);
            if (!$whatsapp) {
                $errorMessage = 'Masukkan email atau nomor WhatsApp yang valid.';
                if ($isAjaxRequest) registerRespond(422, ['error' => $errorMessage], true);
            }
        }

        if ($errorMessage === '') {
            require_once __DIR__ . '/config/database.php';
            try {
                $pdo = getDatabaseConnection();
                $statement = $pdo->prepare(
                    'INSERT INTO customers (name, email, whatsapp, password_hash)
                     VALUES (:name, :email, :whatsapp, :password_hash)'
                );
                $statement->execute([
                    'name' => $name,
                    'email' => $email,
                    'whatsapp' => $whatsapp,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                session_regenerate_id(true);
                $_SESSION['customer_id'] = (int) $pdo->lastInsertId();
                $_SESSION['customer_name'] = $name;

                $redirectTarget = registerRedirect((string) ($input['next'] ?? $_GET['next'] ?? 'dashboard.php'));
                
                if ($isAjaxRequest) {
                    registerRespond(201, [
                        'customer_name' => $name,
                        'redirect' => $redirectTarget,
                    ], true);
                } else {
                    header('Location: ' . $redirectTarget);
                    exit;
                }
            } catch (PDOException $error) {
                if ((string) $error->getCode() === '23000') {
                    $errorMessage = 'Email atau nomor WhatsApp tersebut sudah terdaftar.';
                    if ($isAjaxRequest) registerRespond(409, ['error' => $errorMessage], true);
                } else {
                    error_log('Pemon registration database error: ' . $error->getMessage());
                    $errorMessage = 'Akun belum dapat dibuat. Periksa koneksi basis data.';
                    if ($isAjaxRequest) registerRespond(500, ['error' => $errorMessage], true);
                }
            } catch (Throwable $error) {
                error_log('Pemon registration error: ' . $error->getMessage());
                $errorMessage = 'Akun belum dapat dibuat. Periksa koneksi basis data.';
                if ($isAjaxRequest) registerRespond(500, ['error' => $errorMessage], true);
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
  <meta name="description" content="Buat akun Pemon Barbershop untuk memesan layanan dan mengelola reservasi.">
  <title>Daftar Akun — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script src="register.js" defer></script>
</head>
<body>
  <main class="auth-page">
    <div class="auth-layout">
    <section class="auth-panel" aria-labelledby="register-title">
      <a class="auth-mark" href="index.html" aria-label="Pemon Barbershop, beranda">
        <span class="auth-mark-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.2 8.2 12 12M8.2 15.8 20 4M13 12l-2.2 2.2"/></svg></span>
        <span class="auth-brand">pemon <small>BARBERSHOP</small></span>
      </a>
      <header class="auth-heading">
        <p class="auth-eyebrow">AKUN PEMON BARBERSHOP</p>
        <h1 id="register-title">Daftar Akun</h1>
        <p>Buat akun untuk memesan layanan dan melihat riwayat reservasi.</p>
      </header>

      <?php if (!empty($errorMessage)): ?>
        <div class="auth-error-banner" style="color: #b91c1c; background: #fee2e2; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;">
          <?= htmlspecialchars($errorMessage) ?>
        </div>
      <?php endif; ?>

      <form id="register-form" class="auth-form" method="POST" action="register.php<?= isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : '' ?>" novalidate>
        <label class="field-label" for="register-name">Nama Lengkap</label>
        <div class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 21c.5-4.3 3-6.5 7.5-6.5s7 2.2 7.5 6.5"/></svg><input id="register-name" name="name" type="text" autocomplete="name" placeholder="Nama lengkap" maxlength="150" value="<?= htmlspecialchars($input['name'] ?? '') ?>" required></div>
        
        <label class="field-label register-label" for="register-identifier">Email atau Nomor WhatsApp</label>
        <div class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><input id="register-identifier" name="identifier" type="text" autocomplete="username" placeholder="nama@email.com / 0812xxxx" value="<?= htmlspecialchars($input['identifier'] ?? '') ?>" required></div>
        
        <label class="field-label register-label" for="register-password">Kata Sandi</label>
        <div class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg><input id="register-password" name="password" type="password" autocomplete="new-password" placeholder="Minimal 8 karakter" required></div>
        
        <label class="field-label register-label" for="register-confirm">Ulangi Kata Sandi</label>
        <div class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg><input id="register-confirm" name="confirm_password" type="password" autocomplete="new-password" placeholder="Masukkan kata sandi sekali lagi" required></div>
        
        <label class="remember-option terms-option"><input type="checkbox" name="terms_accepted" id="accept-terms" value="1" <?= !empty($input['terms_accepted']) ? 'checked' : '' ?>><span>Saya menyetujui Syarat &amp; Ketentuan.</span></label>
        <button class="primary-button" type="submit" id="register-submit"><span>Buat Akun</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
      </form>
      <p class="auth-switch register-switch">Sudah punya akun? <a id="login-link" href="login.html">Masuk</a></p>
    </section>
    <nav class="auth-footer" aria-label="Tautan bantuan"><a href="#bantuan" data-info="Hubungi layanan pelanggan Pemon Barbershop untuk mendapatkan bantuan.">Bantuan Layanan</a><i aria-hidden="true"></i><a href="#privasi" data-info="Kebijakan privasi akan tersedia di halaman informasi Pemon Barbershop.">Kebijakan Privasi</a><i aria-hidden="true"></i><a href="#ketentuan" data-info="Syarat dan ketentuan akan tersedia di halaman informasi Pemon Barbershop.">Syarat &amp; Ketentuan</a></nav>
    </div>
  </main>
  <div class="auth-toast" id="auth-toast" role="status" aria-live="polite"></div>
</body>
</html>
