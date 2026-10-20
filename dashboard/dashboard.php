<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_start();

require_once __DIR__ . '/config/database.php';

$authenticated = false;
$customer = null;
$stats = ['completed_visits' => 0];
$favoriteBarber = null;
$upcoming = null;

$customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);

if ($customerId) {
    try {
        $pdo = getDatabaseConnection();
        $profileStatement = $pdo->prepare('SELECT id, name, email, whatsapp FROM customers WHERE id = :customer_id LIMIT 1');
        $profileStatement->execute(['customer_id' => $customerId]);
        $customer = $profileStatement->fetch();
        
        if (!$customer) {
            $_SESSION = [];
            session_destroy();
        } else {
            $authenticated = true;

            $statsStatement = $pdo->prepare(
                "SELECT COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_visits
                 FROM reservations WHERE customer_id = :customer_id"
            );
            $statsStatement->execute(['customer_id' => $customerId]);
            $stats = $statsStatement->fetch() ?: ['completed_visits' => 0];

            $barberStatement = $pdo->prepare(
                "SELECT barber_name
                 FROM reservations
                 WHERE customer_id = :customer_id AND status = 'completed' AND barber_name IS NOT NULL AND barber_name <> ''
                 GROUP BY barber_name
                 ORDER BY COUNT(*) DESC, MAX(appointment_date) DESC
                 LIMIT 1"
            );
            $barberStatement->execute(['customer_id' => $customerId]);
            $favoriteBarber = $barberStatement->fetchColumn();

            $jakartaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
            $upcomingStatement = $pdo->prepare(
                "SELECT reservation_code, status, service_name, hairstyle_name, barber_name,
                        appointment_date, start_time, end_time, amount
                 FROM reservations
                 WHERE customer_id = :customer_id
                   AND status IN ('upcoming', 'pending_confirmation', 'confirmed')
                   AND (appointment_date > :today_after OR (appointment_date = :today_same AND start_time >= :current_time))
                 ORDER BY appointment_date ASC, start_time ASC
                 LIMIT 1"
            );
            $upcomingStatement->execute([
                'customer_id' => $customerId,
                'today_after' => $jakartaNow->format('Y-m-d'),
                'today_same' => $jakartaNow->format('Y-m-d'),
                'current_time' => $jakartaNow->format('H:i:s'),
            ]);
            $upcoming = $upcomingStatement->fetch() ?: null;

            if ($upcoming) {
                $upcoming['amount'] = (int) $upcoming['amount'];
                $upcoming['status_label'] = match ($upcoming['status']) {
                    'confirmed' => 'Dikonfirmasi',
                    'pending_confirmation' => 'Menunggu konfirmasi',
                    default => 'Terjadwal',
                };
            }
        }
    } catch (Throwable $error) {
        error_log('Pemon dashboard error: ' . $error->getMessage());
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06473d">
  <meta name="description" content="Dasbor pelanggan Pemon Barbershop untuk melihat reservasi dan inspirasi gaya rambut.">
  <title>Dasbor — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
  <script src="dashboard.js" defer></script>
</head>
<body>
  <div class="site-frame">
    <header class="site-header">
      <a class="brand" href="dashboard.php" aria-label="Pemon Barbershop, dasbor">
        <span class="brand-script">pemon</span>
        <span class="brand-subtitle">BARBERSHOP</span>
      </a>
      <button class="menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false"><span></span><span></span><span></span></button>
      <nav class="main-nav" aria-label="Navigasi utama">
        <a href="index.html">Beranda</a>
        <a class="active" href="dashboard.php" aria-current="page">Dasbor</a>
        <a href="schedule.html">Jadwal</a>
        <a href="index.html">Riwayat Reservasi</a>
        <a href="#lookbook">Tentang Kami</a>
      </nav>
      <div class="header-actions">
        <?php if ($authenticated): ?>
          <a class="header-login" href="dashboard.php" id="header-login" style="display:none;">Masuk</a>
          <button class="header-logout" id="header-logout" type="button">Keluar</button>
        <?php else: ?>
          <a class="header-login" href="login.html?next=dashboard.php" id="header-login">Masuk</a>
          <button class="header-logout" id="header-logout" type="button" hidden>Keluar</button>
        <?php endif; ?>
      </div>
    </header>

    <main>
      <section class="hero" aria-labelledby="welcome-title">
        <div class="hero-copy">
          <p class="eyebrow"><span class="shield-mark" aria-hidden="true">✓</span> PORTAL PELANGGAN</p>
          <h1 id="welcome-title">Selamat datang<?= $authenticated && $customer ? ', ' . htmlspecialchars($customer['name']) : '' ?> di Pemon.</h1>
          <p id="welcome-description">Inspirasi gaya dan layanan pangkas untuk kunjunganmu berikutnya.</p>
        </div>
        <div class="hero-status" id="hero-status"><?= $authenticated ? 'Akun terhubung' : 'Informasi umum' ?></div>
      </section>

      <?php if (!$authenticated): ?>
        <section class="auth-prompt" id="auth-prompt" aria-labelledby="auth-title">
          <div class="prompt-mark" aria-hidden="true">✂</div>
          <div class="prompt-copy">
            <p class="eyebrow">DASBOR PELANGGAN</p>
            <h2 id="auth-title">Masuk untuk melihat dasbor kamu</h2>
            <p>Jadwal, riwayat reservasi, dan ringkasan akun hanya ditampilkan setelah kamu masuk.</p>
          </div>
          <div class="prompt-actions">
            <a class="button button-dark" href="login.html?next=dashboard.php">Masuk ke Akun <span aria-hidden="true">→</span></a>
            <a class="text-link" href="register.html?next=dashboard.php">Belum punya akun? Daftar</a>
          </div>
        </section>
      <?php endif; ?>

      <div class="load-message" id="load-message" role="status" aria-live="polite" <?= $authenticated ? 'style="display:none;"' : '' ?>>Memuat dasbor…</div>

      <section class="private-dashboard" id="private-dashboard" <?= !$authenticated ? 'hidden' : '' ?> aria-label="Informasi akun pelanggan">
        <div class="dashboard-grid">
          <article class="panel appointment-panel">
            <div class="panel-heading">
              <span class="icon-tile"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
              <div><p class="mini-label">RESERVASI TERJADWAL</p><h2>Janji Temu Berikutnya</h2></div>
              <span class="status-pill" id="appointment-status"><?= htmlspecialchars($upcoming['status_label'] ?? '') ?></span>
            </div>
            <div class="appointment-content" id="appointment-content">
              <?php if ($upcoming): ?>
                <p><strong>Layanan:</strong> <?= htmlspecialchars($upcoming['service_name']) ?></p>
                <?php if (!empty($upcoming['hairstyle_name'])): ?>
                  <p><strong>Gaya Rambut:</strong> <?= htmlspecialchars($upcoming['hairstyle_name']) ?></p>
                <?php endif; ?>
                <?php if (!empty($upcoming['barber_name'])): ?>
                  <p><strong>Barber:</strong> <?= htmlspecialchars($upcoming['barber_name']) ?></p>
                <?php endif; ?>
                <p><strong>Tanggal:</strong> <?= htmlspecialchars($upcoming['appointment_date']) ?> (<?= htmlspecialchars($upcoming['start_time']) ?> - <?= htmlspecialchars($upcoming['end_time']) ?>)</p>
                <p><strong>Kode Reservasi:</strong> <?= htmlspecialchars($upcoming['reservation_code']) ?></p>
                <p><strong>Jumlah:</strong> Rp <?= number_format($upcoming['amount'], 0, ',', '.') ?></p>
              <?php else: ?>
                <p>Tidak ada reservasi aktif saat ini.</p>
              <?php endif; ?>
            </div>
          </article>

          <article class="panel profile-panel">
            <div class="panel-heading">
              <span class="icon-tile"><svg aria-hidden="true"><use href="#icon-user"></use></svg></span>
              <div><p class="mini-label">AKUN PELANGGAN</p><h2>Ringkasan Profil</h2></div>
            </div>
            <div class="profile-highlight">
              <span class="avatar" id="profile-avatar" aria-hidden="true"><?= $authenticated && $customer ? mb_strtoupper(mb_substr($customer['name'], 0, 1)) : '' ?></span>
              <div>
                <strong id="profile-name"><?= htmlspecialchars($customer['name'] ?? '') ?></strong>
                <span id="profile-contact"><?= htmlspecialchars($customer['whatsapp'] ?? $customer['email'] ?? '') ?></span>
                <small>Akun aktif</small>
              </div>
            </div>
            <dl class="profile-facts">
              <div><dt><svg aria-hidden="true"><use href="#icon-scissors"></use></svg>Barber favorit</dt><dd id="profile-barber"><?= htmlspecialchars($favoriteBarber ?? '-') ?></dd></div>
              <div><dt><svg aria-hidden="true"><use href="#icon-check"></use></svg>Kunjungan selesai</dt><dd id="profile-visits"><?= (int) $stats['completed_visits'] ?></dd></div>
            </dl>
            <p class="privacy-note"><span aria-hidden="true">●</span> Informasi ini hanya untuk akun yang sedang masuk.</p>
          </article>
        </div>

        <nav class="quick-links" aria-label="Pintasan akun">
          <a class="quick-link primary" href="booking-services.html"><span class="quick-icon">＋</span><span><strong>Pesan Reservasi Baru</strong><small>Pilih layanan dan waktu kunjungan</small></span><span class="quick-arrow">→</span></a>
          <a class="quick-link" href="schedule.html"><span class="quick-icon"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span><span><strong>Lihat Jadwal</strong><small>Jam buka dan reservasi akunmu</small></span><span class="quick-arrow">→</span></a>
          <a class="quick-link" href="index.html"><span class="quick-icon"><svg aria-hidden="true"><use href="#icon-history"></use></svg></span><span><strong>Riwayat Reservasi</strong><small>Lihat kunjungan sebelumnya</small></span><span class="quick-arrow">→</span></a>
        </nav>
      </section>

      <section class="lookbook" id="lookbook" aria-labelledby="lookbook-title">
        <div class="section-intro">
          <div>
            <p class="eyebrow">INSPIRASI GAYA</p>
            <h2 id="lookbook-title">Jelajahi Gaya Andalan</h2>
            <p>Inspirasi visual untuk kunjungan berikutnya. Gaya dapat disesuaikan bersama barber.</p>
          </div>
          <span class="lookbook-note">Inspirasi saja · Pilihan dibuat saat pemesanan</span>
        </div>
        <div class="style-grid">
          <article class="style-card"><div class="style-art art-classic"><span class="style-number">GAYA 01</span><span class="head-shape"></span></div><div class="style-copy"><div><h3>Potongan Klasik</h3><span class="style-tag">Abadi</span></div><p>Tampilan rapi dengan belahan alami untuk keseharian.</p></div></article>
          <article class="style-card"><div class="style-art art-fade"><span class="style-number">GAYA 02</span><span class="head-shape"></span></div><div class="style-copy"><div><h3>Skin Fade</h3><span class="style-tag">Modern</span></div><p>Gradasi halus di sisi dengan bagian atas yang tetap bertekstur.</p></div></article>
          <article class="style-card"><div class="style-art art-pompadour"><span class="style-number">GAYA 03</span><span class="head-shape"></span></div><div class="style-copy"><div><h3>Pompadour</h3><span class="style-tag">Elegan</span></div><p>Volume bagian atas untuk kesan klasik yang menonjol.</p></div></article>
          <article class="style-card"><div class="style-art art-undercut"><span class="style-number">GAYA 04</span><span class="head-shape"></span></div><div class="style-copy"><div><h3>Undercut</h3><span class="style-tag">Tegas</span></div><p>Kontras sisi pendek dan bagian atas yang lebih panjang.</p></div></article>
          <article class="style-card"><div class="style-art art-crew"><span class="style-number">GAYA 05</span><span class="head-shape"></span></div><div class="style-copy"><div><h3>Crew Cut</h3><span class="style-tag">Praktis</span></div><p>Potongan ringkas dengan tekstur sederhana dan mudah dirawat.</p></div></article>
          <article class="style-card"><div class="style-art art-caesar"><span class="style-number">GAYA 06</span><span class="head-shape"></span></div><div class="style-copy"><div><h3>Caesar Cut</h3><span class="style-tag">Urban</span></div><p>Poni pendek lurus dengan bentuk yang bersih dan modern.</p></div></article>
        </div>
        <aside class="barber-help"><span class="help-icon">✂</span><div><strong>Butuh penyesuaian gaya?</strong><p>Sampaikan keinginanmu kepada barber saat berkunjung.</p></div><a class="button button-soft" href="https://wa.me/6281234567890">Hubungi Barber <span aria-hidden="true">↗</span></a></aside>
      </section>
    </main>

    <footer class="site-footer">
      <div class="footer-grid">
        <div class="footer-brand-column"><a class="brand footer-brand" href="dashboard.php"><span class="brand-script">pemon</span><span class="brand-subtitle">BARBERSHOP</span></a><p>Perawatan diri modern dengan keahlian, keramahan, dan perhatian pada setiap detail.</p></div>
        <div><h2>Navigasi Cepat</h2><a href="dashboard.php">Dasbor</a><a href="schedule.html">Jadwal</a><a href="index.html">Riwayat Reservasi</a></div>
        <div><h2>Hubungi Kami</h2><a href="https://wa.me/6281234567890">WhatsApp: +62 812-3456-7890</a><a href="tel:+62217892041">Telepon: (021) 789-2041</a><span>Setiap hari: 09.00–21.00 WIB</span></div>
        <div><h2>Kunjungi Kami</h2><p>Senopati Suites No. 14<br>Jakarta Selatan</p><a href="mailto:hello@pemonbarbershop.com">hello@pemonbarbershop.com</a></div>
      </div>
      <div class="footer-bottom"><span>© 2025 Pemon Barbershop. Hak cipta dilindungi.</span><span>Perawatan rambut dengan keahlian dan ketelitian.</span></div>
    </footer>
  </div>

  <div class="toast" id="dashboard-toast" role="status" aria-live="polite"></div>
  <svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c.5-3.7 2.8-5.5 7-5.5s6.5 1.8 7 5.5z"/></symbol>
    <symbol id="icon-scissors" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.2 8.2 12 12M8.2 15.8 20 4M13 12l-2.2 2.2"/></symbol>
    <symbol id="icon-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></symbol>
    <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
    <symbol id="icon-history" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8m0-5v5h5m4-1v5l3 2"/></symbol>
    <symbol id="icon-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></symbol>
  </svg>
</body>
</html>
