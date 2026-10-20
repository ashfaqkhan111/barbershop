<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

$customerId = $_SESSION['customer_id'] ?? null;
$customerName = $_SESSION['customer_name'] ?? null;
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
        
        foreach ($reservations as &$res) {
            $res['customer_name'] = $customerName;
        }
        unset($res);
    } catch (Throwable $error) {
        error_log('Reservation list API error: ' . $error->getMessage());
        $errorMessage = 'Daftar reservasi tidak dapat dimuat.';
    }
}

$filterAll = count($reservations);
$filterPending = count(array_filter($reservations, fn($r) => $r['status'] === 'pending_confirmation'));
$filterConfirmed = count(array_filter($reservations, fn($r) => $r['status'] === 'confirmed'));
$filterCompleted = count(array_filter($reservations, fn($r) => $r['status'] === 'completed'));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06483d">
  <meta name="description" content="Lihat dan kelola reservasi Anda di Pemon Barbershop.">
  <title>Daftar Reservasi — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="reservation-list.css">
  <script src="reservation-list.js" defer></script>
</head>
<body>
  <div class="list-page-frame">
    <div class="list-browser-caption">Daftar Reservasi</div>
    <header class="list-header">
      <a class="list-brand" href="dashboard.php" aria-label="Beranda Pemon Barbershop">
        <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
        <span>BARBERSHOP</span>
      </a>
      <button class="list-menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false">
        <svg aria-hidden="true"><use href="#list-menu"></use></svg>
      </button>
      <nav class="list-nav" aria-label="Navigasi utama">
        <a href="dashboard.php">Dasbor</a>
        <a href="../booking-service/booking_service.php">Layanan</a>
        <a href="schedule.html">Jadwal</a>
        <a class="is-current" href="reservation-list.php" aria-current="page">Reservasi Saya</a>
      </nav>
      <div class="list-header-actions">
        <button type="button" aria-label="Cari"><svg aria-hidden="true"><use href="#list-search"></use></svg></button>
        <button type="button" aria-label="Notifikasi" class="list-notification"><svg aria-hidden="true"><use href="#list-bell"></use></svg><i></i></button>
      </div>
    </header>

    <main class="list-main">
      <section class="list-intro" aria-labelledby="list-title">
        <p class="list-eyebrow">RESERVASI SAYA</p>
        <h1 id="list-title">Daftar Reservasi</h1>
        <p class="list-subtitle">Lihat dan kelola semua reservasi Anda di Pemon Barbershop.</p>
      </section>

      <?php if (!empty($errorMessage)): ?>
        <div class="auth-error-banner" style="color: #b91c1c; background: #fee2e2; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
          <?= htmlspecialchars($errorMessage) ?>
        </div>
      <?php endif; ?>

      <?php if (!$customerId): ?>
        <div class="auth-prompt" style="padding: 40px; text-align: center; background: #f9fafb; border-radius: 8px; margin-bottom: 30px;">
          <h2>Silakan Masuk</h2>
          <p style="margin: 10px 0 20px;">Anda harus masuk terlebih dahulu untuk melihat daftar reservasi Anda.</p>
          <a class="button button-dark" href="login.html?next=reservation-list.php">Masuk ke Akun <span aria-hidden="true">→</span></a>
        </div>
      <?php else: ?>

        <div class="list-filters" role="tablist" aria-label="Filter reservasi">
          <button class="list-filter is-active" type="button" role="tab" aria-selected="true" data-filter="all">Semua <span data-count="all"><?= $filterAll ?></span></button>
          <button class="list-filter" type="button" role="tab" aria-selected="false" data-filter="pending_confirmation">Menunggu Konfirmasi <span data-count="pending_confirmation"><?= $filterPending ?></span></button>
          <button class="list-filter" type="button" role="tab" aria-selected="false" data-filter="confirmed">Dikonfirmasi <span data-count="confirmed"><?= $filterConfirmed ?></span></button>
          <button class="list-filter" type="button" role="tab" aria-selected="false" data-filter="completed">Selesai <span data-count="completed"><?= $filterCompleted ?></span></button>
        </div>

        <section class="list-results" aria-label="Daftar reservasi">
          <div class="list-reservations" id="list-reservations" aria-live="polite">
            <?php if (empty($reservations)): ?>
              <p style="padding: 20px 0; color: #6b7280; text-align: center;">Belum ada reservasi yang tercatat.</p>
            <?php else: ?>
              <?php foreach ($reservations as $res): ?>
                <div class="reservation-card" data-status="<?= htmlspecialchars($res['status']) ?>" style="padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 15px; background: #fff;">
                  <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <strong>Kode: <?= htmlspecialchars($res['reservation_code']) ?></strong>
                    <span class="status-badge" style="padding: 2px 8px; border-radius: 4px; font-size: 12px; background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars($res['status']) ?></span>
                  </div>
                  <p><strong>Layanan:</strong> <?= htmlspecialchars($res['service_name']) ?></p>
                  <?php if (!empty($res['barber_name'])): ?>
                    <p><strong>Barber:</strong> <?= htmlspecialchars($res['barber_name']) ?></p>
                  <?php endif; ?>
                  <p><strong>Tanggal:</strong> <?= htmlspecialchars($res['appointment_date']) ?> (<?= htmlspecialchars(substr($res['start_time'], 0, 5)) ?>)</p>
                  <p><strong>Total:</strong> Rp <?= number_format((float)$res['total_price'], 0, ',', '.') ?></p>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <p class="list-showing" id="list-showing">Menampilkan <?= count($reservations) ?> dari <?= count($reservations) ?> reservasi</p>
        </section>

      <?php endif; ?>
    </main>
  </div>

  <div class="list-modal-backdrop" id="list-modal-backdrop" hidden>
    <section class="list-modal" role="dialog" aria-modal="true" aria-labelledby="list-modal-title">
      <button class="list-modal-close" type="button" aria-label="Tutup detail">×</button>
      <p class="list-eyebrow">PEMON BARBERSHOP</p>
      <h2 id="list-modal-title">Detail Reservasi</h2>
      <div id="list-modal-content"></div>
    </section>
  </div>
  <div class="list-toast" id="list-toast" role="status" aria-live="polite"></div>

  <svg class="list-icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="list-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="list-search" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></symbol>
    <symbol id="list-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol>
    <symbol id="list-scissors" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.2 8.2 12 12M8.2 15.8 20 4M13 12l-2.2 2.2"/></symbol>
    <symbol id="list-person" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3"/><path d="M5 20c.5-4 2.8-6 7-6s6.5 2 7 6z"/></symbol>
    <symbol id="list-sparkle" viewBox="0 0 24 24"><path d="m12 2 1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></symbol>
    <symbol id="list-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
    <symbol id="list-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></symbol>
    <symbol id="list-wait" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="list-cross" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></symbol>
    <symbol id="list-arrow" viewBox="0 0 24 24"><path d="M4 12h15m-6-6 6 6-6 6"/></symbol>
    <symbol id="list-repeat" viewBox="0 0 24 24"><path d="M17 2l4 4-4 4M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4m14-1v2a3 3 0 0 1-3 3H3"/></symbol>
  </svg>
</body>
</html>
