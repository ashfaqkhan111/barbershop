<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

$customerId = $_SESSION['customer_id'] ?? null;
$reservations = [];
$errorMessage = '';

if ($customerId) {
    require_once __DIR__ . '/../config/database.php';
    try {
        $pdo = getDatabaseConnection();
        $statement = $pdo->prepare(
            'SELECT r.id, r.reservation_code, r.status, r.service_name, r.additional_service,
                    r.barber_name, r.barber_level, r.appointment_date, r.start_time, r.end_time,
                    r.total_price, r.booked_at,
                    p.payment_method, p.payment_status
             FROM reservations AS r
             LEFT JOIN payments AS p
                    ON p.payment_id = (SELECT MAX(p2.payment_id) FROM payments AS p2 WHERE p2.reservation_id = r.id)
             WHERE r.customer_id = :customer_id
             ORDER BY r.appointment_date DESC, r.start_time DESC'
        );
        $statement->execute(['customer_id' => $customerId]);
        $reservations = $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $error) {
        error_log('Reservation history API error: ' . $error->getMessage());
        $errorMessage = 'Reservasi tidak dapat dimuat.';
    }
}

// Pisahkan berdasarkan kategori untuk render server-side jika diinginkan
$upcomingReservations = array_filter($reservations, fn($r) => in_array($r['status'], ['upcoming', 'pending_confirmation', 'confirmed'], true));
$pastReservations = array_filter($reservations, fn($r) => !in_array($r['status'], ['upcoming', 'pending_confirmation', 'confirmed'], true));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06473d">
  <meta name="description" content="Lihat jadwal reservasi mendatang dan riwayat kunjungan Pemon Barbershop.">
  <title>Riwayat Reservasi — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <script src="app.js" defer></script>
</head>
<body>
  <div class="site-frame">
    <div class="browser-caption">Riwayat Reservasi - Pemon Barbershop</div>

    <header class="site-header">
      <a class="brand" href="#top" aria-label="Beranda Pemon Barbershop">
        <span class="brand-script">pemon</span>
        <span class="brand-subtitle">BARBERSHOP</span>
      </a>

      <button class="menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false">
        <svg aria-hidden="true"><use href="#icon-menu"></use></svg>
      </button>

      <nav class="main-nav" aria-label="Navigasi utama">
        <a href="dashboard.php">Dasbor</a>
        <a href="schedule.html">Jadwal</a>
        <a class="active" href="reservation_history.php" aria-current="page">Riwayat Reservasi</a>
        <a href="#about">Tentang Kami</a>
      </nav>

      <div class="header-actions">
        <button class="icon-button" type="button" aria-label="Cari"><svg aria-hidden="true"><use href="#icon-search"></use></svg></button>
        <button class="icon-button notification-button" type="button" aria-label="Notifikasi"><svg aria-hidden="true"><use href="#icon-bell"></use></svg><span class="notification-dot"></span></button>
        <a class="profile-button" href="dashboard.php" aria-label="Profil"><svg aria-hidden="true"><use href="#icon-user"></use></svg></a>
      </div>
    </header>

    <main id="reservations" class="page-main">
      <section class="intro" aria-labelledby="page-title">
        <div class="intro-copy">
          <p class="eyebrow">RESERVASI SAYA</p>
          <h1 id="page-title">Riwayat Reservasi</h1>
          <p class="intro-description">Lihat jadwal reservasi mendatang<br class="desktop-break"> dan kunjungan Anda sebelumnya.</p>
        </div>
        <div class="intro-actions">
          <span class="location-pill"><span class="location-dot"></span>Pemon Senopati Suites</span>
          <a class="button button-dark button-book-new" href="../booking-service/booking_service.php"><span aria-hidden="true">＋</span> Pesan Baru</a>
        </div>
      </section>

      <?php if (!empty($errorMessage)): ?>
        <div class="auth-error-banner" style="color: #b91c1c; background: #fee2e2; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
          <?= htmlspecialchars($errorMessage) ?>
        </div>
      <?php endif; ?>

      <?php if (!$customerId): ?>
        <div class="auth-prompt" style="padding: 30px; text-align: center; background: #f9fafb; border-radius: 8px; margin-bottom: 30px;">
          <h2>Silakan Masuk</h2>
          <p style="margin: 10px 0 20px;">Anda harus masuk terlebih dahulu untuk melihat riwayat reservasi Anda.</p>
          <a class="button button-dark" href="login.html?next=reservation_history.php">Masuk ke Akun <span aria-hidden="true">→</span></a>
        </div>
      <?php else: ?>

        <div class="filters" role="tablist" aria-label="Filter reservasi">
          <button class="filter-tab is-selected" type="button" role="tab" aria-selected="true" data-filter="all">Semua <span class="count-badge" data-count="all"><?= count($reservations) ?></span></button>
          <button class="filter-tab" type="button" role="tab" aria-selected="false" data-filter="upcoming">Mendatang <span class="count-badge" data-count="upcoming"><?= count($upcomingReservations) ?></span></button>
          <button class="filter-tab" type="button" role="tab" aria-selected="false" data-filter="completed">Selesai <span class="count-badge" data-count="completed"><?= count(array_filter($reservations, fn($r) => $r['status'] === 'completed')) ?></span></button>
          <button class="filter-tab" type="button" role="tab" aria-selected="false" data-filter="cancelled">Dibatalkan <span class="count-badge" data-count="cancelled"><?= count(array_filter($reservations, fn($r) => $r['status'] === 'cancelled')) ?></span></button>
        </div>

        <section class="reservation-section upcoming-section" aria-labelledby="upcoming-title">
          <div class="section-heading">
            <h2 id="upcoming-title"><span class="heading-dot"></span>Reservasi Aktif Mendatang</h2>
            <span class="section-note" id="scheduled-count"><?= count($upcomingReservations) ?> JANJI TEMU TERJADWAL</span>
          </div>
          <div class="reservation-list upcoming-list" id="upcoming-list" aria-live="polite">
            <?php if (empty($upcomingReservations)): ?>
              <p style="padding: 20px 0; color: #6b7280;">Tidak ada reservasi mendatang.</p>
            <?php else: ?>
              <?php foreach ($upcomingReservations as $res): ?>
                <div class="reservation-card" style="padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 15px; background: #fff;">
                  <strong>Kode: <?= htmlspecialchars($res['reservation_code']) ?></strong>
                  <p>Layanan: <?= htmlspecialchars($res['service_name']) ?></p>
                  <p>Tanggal: <?= htmlspecialchars($res['appointment_date']) ?> (<?= htmlspecialchars(substr($res['start_time'], 0, 5)) ?>)</p>
                  <p>Status: <strong><?= htmlspecialchars($res['status']) ?></strong></p>
                  <p>Total: Rp <?= number_format((float)$res['total_price'], 0, ',', '.') ?></p>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section class="reservation-section past-section" aria-labelledby="past-title">
          <div class="section-heading">
            <h2 id="past-title">Reservasi Sebelumnya</h2>
            <span class="section-note">Riwayat kunjungan Anda</span>
          </div>
          <div class="reservation-list past-list" id="past-list" aria-live="polite">
            <?php if (empty($pastReservations)): ?>
              <p style="padding: 20px 0; color: #6b7280;">Belum ada riwayat reservasi sebelumnya.</p>
            <?php else: ?>
              <?php foreach ($pastReservations as $res): ?>
                <div class="reservation-card" style="padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 15px; background: #f9fafb;">
                  <strong>Kode: <?= htmlspecialchars($res['reservation_code']) ?></strong>
                  <p>Layanan: <?= htmlspecialchars($res['service_name']) ?></p>
                  <p>Tanggal: <?= htmlspecialchars($res['appointment_date']) ?> (<?= htmlspecialchars(substr($res['start_time'], 0, 5)) ?>)</p>
                  <p>Status: <strong><?= htmlspecialchars($res['status']) ?></strong></p>
                  <p>Total: Rp <?= number_format((float)$res['total_price'], 0, ',', '.') ?></p>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <div class="history-footerline">
          <span class="showing-count"><svg aria-hidden="true"><use href="#icon-history"></use></svg><span id="showing-count">Menampilkan <?= count($reservations) ?> dari <?= count($reservations) ?> reservasi</span></span>
          <span>Butuh bantuan terkait kunjungan? <a href="mailto:hello@pemonbarbershop.com">Hubungi Layanan Pelanggan <svg aria-hidden="true"><use href="#icon-external"></use></svg></a></span>
        </div>

      <?php endif; ?>
    </main>

    <footer class="site-footer">
      <div class="footer-grid">
        <div class="footer-brand-column">
          <a class="brand footer-brand" href="#top" aria-label="Beranda Pemon Barbershop"><span class="brand-script">pemon</span><span class="brand-subtitle">BARBERSHOP</span></a>
          <p>Perawatan diri modern dan berkelas untuk pria urban. Keahlian terampil, keramahan hangat, dan perhatian pada setiap detail.</p>
        </div>
        <div>
          <h2>Navigasi Cepat</h2>
          <ul class="footer-links">
            <li><a href="dashboard.php">Dasbor</a></li><li><a href="schedule.html">Jadwal</a></li><li><a href="reservation_history.php">Riwayat Reservasi</a></li><li><a href="#about">Tentang Kami</a></li>
          </ul>
        </div>
        <div>
          <h2>Hubungi Kami</h2>
          <ul class="contact-list">
            <li><svg aria-hidden="true"><use href="#icon-message"></use></svg><a href="https://wa.me/6281234567890">WhatsApp: +62 812-3456-7890</a></li>
            <li><svg aria-hidden="true"><use href="#icon-phone"></use></svg><a href="tel:+62217892041">Telepon: (021) 789-2041</a></li>
            <li><svg aria-hidden="true"><use href="#icon-clock"></use></svg><span>Setiap hari: 09.00–21.00 WIB</span></li>
            <li><svg aria-hidden="true"><use href="#icon-pin"></use></svg><span>Senopati Suites No. 14, Jakarta Selatan</span></li>
          </ul>
        </div>
        <div class="connect-column">
          <h2>Ikuti Kami</h2>
          <p>Ikuti inspirasi gaya rambut dan penawaran musiman melalui kanal sosial kami.</p>
          <div class="social-links">
            <a href="#instagram" aria-label="Instagram"><svg aria-hidden="true"><use href="#icon-camera"></use></svg></a>
            <a href="#location" aria-label="Lokasi"><svg aria-hidden="true"><use href="#icon-map"></use></svg></a>
            <a href="mailto:hello@pemonbarbershop.com" aria-label="Surel"><svg aria-hidden="true"><use href="#icon-mail"></use></svg></a>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© 2025 Pemon Barbershop. Hak cipta dilindungi. Perawatan rambut dengan keahlian dan ketelitian.</span>
        <div><a href="#privacy">Kebijakan Privasi</a><a href="#terms">Ketentuan Layanan</a></div>
      </div>
    </footer>
  </div>

  <div class="modal-backdrop" id="modal-backdrop" hidden>
    <section class="details-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <button class="modal-close" type="button" aria-label="Tutup detail">×</button>
      <p class="eyebrow">PEMON BARBERSHOP</p>
      <h2 id="modal-title">Detail Reservasi</h2>
      <div id="modal-content"></div>
    </section>
  </div>
  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="icon-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="icon-search" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></symbol>
    <symbol id="icon-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/><circle cx="19" cy="4" r="2" class="icon-fill"/></symbol>
    <symbol id="icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c.5-3.7 2.8-5.5 7-5.5s6.5 1.8 7 5.5z"/></symbol>
    <symbol id="icon-scissors" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.2 8.2 12 12M8.2 15.8 20 4M13 12l-2.2 2.2"/></symbol>
    <symbol id="icon-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></symbol>
    <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
    <symbol id="icon-history" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8m0-5v5h5m4-1v5l3 2"/></symbol>
    <symbol id="icon-external" viewBox="0 0 24 24"><path d="M14 4h6v6m0-6-9 9"/><path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></symbol>
    <symbol id="icon-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></symbol>
    <symbol id="icon-cross" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></symbol>
    <symbol id="icon-arrow" viewBox="0 0 24 24"><path d="M4 12h15m-6-6 6 6-6 6"/></symbol>
    <symbol id="icon-repeat" viewBox="0 0 24 24"><path d="M17 2l4 4-4 4M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4m14-1v2a3 3 0 0 1-3 3H3"/></symbol>
    <symbol id="icon-person" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3"/><path d="M5 20c.5-4 2.8-6 7-6s6.5 2 7 6z"/></symbol>
    <symbol id="icon-shave" viewBox="0 0 24 24"><path d="M12 21c-5-5-8-8-8-12a4 4 0 0 1 8 0 4 4 0 0 1 8 0c0 4-3 7-8 12Z"/><path d="M12 7v9m-3-4 3-3 3 3"/></symbol>
    <symbol id="icon-message" viewBox="0 0 24 24"><path d="M3 4h18v13H8l-5 4z"/><path d="M7 8h10M7 12h7"/></symbol>
    <symbol id="icon-phone" viewBox="0 0 24 24"><path d="M5 3h4l2 5-3 2a16 16 0 0 0 6 6l2-3 5 2v4c0 1-1 2-2 2C10 20 4 14 3 5c0-1 1-2 2-2Z"/></symbol>
    <symbol id="icon-pin" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></symbol>
    <symbol id="icon-camera" viewBox="0 0 24 24"><path d="M4 7h4l2-3h5l2 3h3a2 2 0 0 1 2 2v10H2V9a2 2 0 0 1 2-2Z"/><circle cx="12" cy="13" r="3.5"/></symbol>
    <symbol id="icon-map" viewBox="0 0 24 24"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15m6-12v15"/></symbol>
    <symbol id="icon-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></symbol>
  </svg>
</body>
</html>
