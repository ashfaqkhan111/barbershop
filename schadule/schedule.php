<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_start();

require_once __DIR__ . '/config/database.php';

$authenticated = false;
$reservations = [];
$errorMessage = '';

$customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);
$requestedMonth = filter_input(INPUT_GET, 'month', FILTER_SANITIZE_STRING) ?: date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth)) {
    $requestedMonth = date('Y-m');
}

if ($customerId) {
    try {
        $pdo = getDatabaseConnection();
        $customerQuery = $pdo->prepare('SELECT id FROM customers WHERE id = :customer_id LIMIT 1');
        $customerQuery->execute(['customer_id' => $customerId]);
        if (!$customerQuery->fetch()) {
            $_SESSION = [];
            session_destroy();
        } else {
            $authenticated = true;
            
            $timezone = new DateTimeZone('Asia/Jakarta');
            $monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedMonth . '-01', $timezone);
            $nextMonth = $monthStart->modify('+1 month');

            $reservationsQuery = $pdo->prepare(
                "SELECT reservation_code, status, service_name, hairstyle_name, additional_service,
                        barber_name, appointment_date, start_time, end_time
                 FROM reservations
                 WHERE customer_id = :customer_id
                   AND appointment_date >= :month_start
                   AND appointment_date < :next_month
                   AND status <> 'cancelled'
                 ORDER BY appointment_date ASC, start_time ASC"
            );
            $reservationsQuery->execute([
                'customer_id' => $customerId,
                'month_start' => $monthStart->format('Y-m-d'),
                'next_month' => $nextMonth->format('Y-m-d'),
            ]);
            $reservations = $reservationsQuery->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reservations as &$reservation) {
                $reservation['status_label'] = match ($reservation['status']) {
                    'confirmed' => 'Dikonfirmasi',
                    'pending_confirmation' => 'Menunggu konfirmasi',
                    'completed' => 'Selesai',
                    default => 'Terjadwal',
                };
            }
            unset($reservation);
        }
    } catch (Throwable $error) {
        error_log('Pemon schedule error: ' . $error->getMessage());
        $errorMessage = 'Jadwal belum dapat dimuat.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06473d">
  <meta name="description" content="Lihat jam operasional Pemon Barbershop dan jadwal reservasi akunmu.">
  <title>Jadwal — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="schedule.css">
  <script src="schedule.js" defer></script>
</head>
<body>
  <div class="site-frame">
    <header class="site-header">
      <a class="brand" href="dashboard.php" aria-label="Dasbor Pemon Barbershop">
        <span class="brand-script">pemon</span>
        <span class="brand-subtitle">BARBERSHOP</span>
      </a>
      <button class="menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false"><span></span><span></span><span></span></button>
      <nav class="main-nav" aria-label="Navigasi utama">
        <a href="dashboard.php">Beranda</a>
        <a href="dashboard.php">Dasbor</a>
        <a class="active" href="schedule.php" aria-current="page">Jadwal</a>
        <a href="reservation-list.php">Riwayat Reservasi</a>
        <a href="dashboard.php#lookbook">Tentang Kami</a>
      </nav>
      <div class="header-actions"><a href="dashboard.php" aria-label="Akun pelanggan"><svg aria-hidden="true"><use href="#icon-user"></use></svg></a></div>
    </header>

    <main>
      <section class="schedule-hero">
        <div>
          <p class="eyebrow">JAM OPERASIONAL &amp; KALENDER</p>
          <h1>Jadwal</h1>
          <p class="hero-description">Lihat jam operasional dan rencanakan kunjunganmu ke Pemon Barbershop.</p>
        </div>
        <div class="open-status"><span></span><strong id="open-status-text">Buka setiap hari · 09.00–21.00 WIB</strong></div>
      </section>

      <?php if (!empty($errorMessage)): ?>
        <div class="auth-error-banner" style="color: #b91c1c; background: #fee2e2; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
          <?= htmlspecialchars($errorMessage) ?>
        </div>
      <?php endif; ?>

      <div class="schedule-layout">
        <section class="calendar-card card" aria-labelledby="calendar-title">
          <div class="calendar-heading">
            <div>
              <h2 id="calendar-title">Kalender Operasional</h2>
              <p>Ringkasan hari buka studio dan jadwal reservasi akunmu.</p>
            </div>
            <div class="month-controls" aria-label="Pilih bulan">
              <button type="button" id="month-previous" aria-label="Bulan sebelumnya">‹</button>
              <strong id="month-label"></strong>
              <button type="button" id="month-next" aria-label="Bulan berikutnya">›</button>
            </div>
          </div>

          <div class="info-strip"><span class="info-icon" aria-hidden="true">i</span><span>Kalender ini menampilkan jam operasional studio. Untuk memilih kursi dan jam layanan, lanjutkan ke halaman pemesanan.</span></div>

          <div class="calendar-grid" id="calendar-grid" aria-label="Kalender bulanan"></div>
          <div class="calendar-legend">
            <span><i class="legend-dot open"></i>Jam operasional reguler</span>
            <span><i class="legend-dot today"></i>Hari ini</span>
            <span><i class="legend-dot appointment"></i>Reservasi akunmu</span>
          </div>

          <section class="personal-schedule" aria-labelledby="personal-title">
            <div class="personal-heading">
              <div><p class="mini-label">KHUSUS AKUNMU</p><h3 id="personal-title">Jadwal Reservasi Saya</h3></div>
              <span class="booking-count" id="booking-count" <?= !$authenticated ? 'hidden' : '' ?>><?= count($reservations) ?> reservasi</span>
            </div>
            
            <div class="personal-loading" id="personal-loading" role="status" style="display:none;">Memeriksa sesi akun…</div>
            
            <div class="personal-login" id="personal-login" <?= $authenticated ? 'hidden' : '' ?>>
              <div class="lock-mark" aria-hidden="true">◌</div>
              <div><strong>Jadwal pribadi belum ditampilkan</strong><p>Masuk atau <a href="register.php?next=schedule.php">buat akun</a> untuk melihat reservasimu.</p></div>
              <a class="button button-small" href="login.php?next=schedule.php">Masuk</a>
            </div>

            <div class="personal-bookings" id="personal-bookings" <?= !$authenticated ? 'hidden' : '' ?>>
              <?php if ($authenticated): ?>
                <?php if (empty($reservations)): ?>
                  <p style="padding: 10px 0; color: #6b7280; font-size: 14px;">Tidak ada reservasi pada bulan ini.</p>
                <?php else: ?>
                  <?php foreach ($reservations as $res): ?>
                    <div class="booking-item" style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 10px; background: #fafafa;">
                      <strong><?= htmlspecialchars($res['service_name']) ?></strong>
                      <p style="font-size: 13px; color: #4b5563; margin: 4px 0;">Tanggal: <?= htmlspecialchars($res['appointment_date']) ?> (<?= htmlspecialchars(substr($res['start_time'], 0, 5)) ?>)</p>
                      <span style="font-size: 12px; padding: 2px 6px; background: #e0f2fe; color: #0369a1; border-radius: 4px;"><?= htmlspecialchars($res['status_label']) ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </section>
        </section>

        <aside class="schedule-sidebar">
          <section class="card hours-card" aria-labelledby="hours-title">
            <div class="card-title"><span class="icon-tile"><svg aria-hidden="true"><use href="#icon-clock"></use></svg></span><h2 id="hours-title">Jam Buka Reguler</h2><span class="open-tag">7 Hari Buka</span></div>
            <div class="hours-list" id="hours-list">
              <div data-weekday="1"><span>Senin</span><strong>09.00–21.00 WIB</strong></div>
              <div data-weekday="2"><span>Selasa</span><strong>09.00–21.00 WIB</strong></div>
              <div data-weekday="3"><span>Rabu</span><strong>09.00–21.00 WIB</strong></div>
              <div data-weekday="4"><span>Kamis</span><strong>09.00–21.00 WIB</strong></div>
              <div data-weekday="5"><span>Jumat</span><strong>09.00–21.00 WIB</strong></div>
              <div data-weekday="6"><span>Sabtu</span><strong>09.00–21.00 WIB</strong></div>
              <div data-weekday="0"><span>Minggu</span><strong>09.00–21.00 WIB</strong></div>
            </div>
            <p class="hours-note"><svg aria-hidden="true"><use href="#icon-clock"></use></svg><span>Reservasi terakhir dimulai paling lambat pukul <strong>20.15 WIB</strong>.</span></p>
          </section>

          <section class="card periods-card" aria-labelledby="periods-title">
            <div class="card-title"><span class="icon-tile"><svg aria-hidden="true"><use href="#icon-chair"></use></svg></span><h2 id="periods-title">Periode Layanan Barber</h2></div>
            <div class="period-row"><div><strong>Sesi Pagi</strong><span>Layanan potong dan konsultasi</span></div><b>09.00–15.00</b></div>
            <div class="period-row"><div><strong>Sesi Siang &amp; Sore</strong><span>Grooming lengkap dan penataan</span></div><b>15.00–21.00</b></div>
            <p class="sidebar-note"><svg aria-hidden="true"><use href="#icon-sparkle"></use></svg>Barber bertugas bergantian agar layanan tetap nyaman sepanjang hari.</p>
          </section>

          <section class="card notices-card" aria-labelledby="notices-title">
            <div class="card-title"><span class="icon-tile"><svg aria-hidden="true"><use href="#icon-bell"></use></svg></span><h2 id="notices-title">Informasi Jadwal</h2></div>
            <ul>
              <li><svg aria-hidden="true"><use href="#icon-check"></use></svg><span>Perubahan jam pada hari libur nasional akan diumumkan oleh studio.</span></li>
              <li><svg aria-hidden="true"><use href="#icon-check"></use></svg><span>Jam operasional dapat berubah saat perawatan studio atau pelatihan barber.</span></li>
              <li><svg aria-hidden="true"><use href="#icon-check"></use></svg><span>Reservasi privat dapat ditanyakan lebih awal kepada layanan pelanggan.</span></li>
            </ul>
          </section>
        </aside>
      </div>

      <section class="booking-banner">
        <div><p class="eyebrow">RESERVASI ONLINE</p><h2>Siap membuat reservasi?</h2><p>Pilih layanan, tentukan barber, lalu pesan tanggal dan waktu kunjungan dalam beberapa langkah.</p></div>
        <div class="banner-action"><a class="button button-light" href="booking-services.html">Pesan Jadwal <span aria-hidden="true">→</span></a><small>1. Layanan <i>•</i> 2. Tanggal &amp; Waktu <i>•</i> 3. Konfirmasi</small></div>
      </section>
    </main>

    <footer class="site-footer">
      <div class="footer-grid">
        <div class="footer-brand-column"><a class="brand footer-brand" href="dashboard.php"><span class="brand-script">pemon</span><span class="brand-subtitle">BARBERSHOP</span></a><p>Perawatan diri modern dan berkelas dengan keahlian, keramahan, dan ketelitian.</p></div>
        <div><h2>Navigasi Cepat</h2><a href="dashboard.php">Dasbor</a><a href="schedule.php">Jadwal</a><a href="booking-services.html">Layanan</a><a href="reservation-list.php">Riwayat Reservasi</a></div>
        <div><h2>Hubungi Kami</h2><a href="https://wa.me/6281234567890">WhatsApp: +62 812-3456-7890</a><a href="tel:+62217892041">Telepon: (021) 789-2041</a><span>Setiap hari: 09.00–21.00 WIB</span></div>
        <div><h2>Kunjungi Kami</h2><p>Senopati Suites No. 14<br>Jakarta Selatan</p><a href="mailto:hello@pemonbarbershop.com">hello@pemonbarbershop.com</a></div>
      </div>
      <div class="footer-bottom"><span>© 2025 Pemon Barbershop. Hak cipta dilindungi.</span><span>Perawatan rambut dengan keahlian dan ketelitian.</span></div>
    </footer>
  </div>

  <svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c.5-3.7 2.8-5.5 7-5.5s6.5 1.8 7 5.5z"/></symbol>
    <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
    <symbol id="icon-chair" viewBox="0 0 24 24"><path d="M7 4h10v6H7zM5 10h14v5H5zM8 15v5m8-5v5M3 20h18"/><path d="M9 4V2m6 2V2"/></symbol>
    <symbol id="icon-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol>
    <symbol id="icon-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></symbol>
    <symbol id="icon-sparkle" viewBox="0 0 24 24"><path d="m12 2 1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></symbol>
    <symbol id="icon-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></symbol>
  </svg>
</body>
</html>
