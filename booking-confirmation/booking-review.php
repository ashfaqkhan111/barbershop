<?php
declare(strict_types=1);

/*
 * Step 3: Review and Confirm (page customers browse to).
 *
 * This page only displays the selections kept in the browser (sessionStorage)
 * and never writes a reservation. The Confirm button sends the selections to
 * the JSON endpoint booking-confirmation.php, which saves the reservation and
 * its payment record in one database transaction.
 */

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

$customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$customerName = null;
$customerWhatsapp = null;

if ($customerId) {
    require_once __DIR__ . '/../config/database.php';
    try {
        $pdo = getDatabaseConnection();
        $customerStatement = $pdo->prepare('SELECT name, whatsapp FROM customers WHERE id = :id LIMIT 1');
        $customerStatement->execute(['id' => $customerId]);
        $customer = $customerStatement->fetch();
        if ($customer) {
            $customerName = $customer['name'];
            $customerWhatsapp = $customer['whatsapp'];
        } else {
            $customerId = false;
        }
    } catch (Throwable $error) {
        error_log('Customer info fetch error: ' . $error->getMessage());
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06483d">
  <meta name="description" content="Periksa detail layanan dan konfirmasikan reservasi Pemon Barbershop.">
  <title>Konfirmasi Reservasi — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../booking-date-and-time/booking-datetime.css">
  <link rel="stylesheet" href="booking-confirmation.css">
  <script src="booking-confirmation.js" defer></script>
</head>
<body>
  <div class="datetime-frame">
    <header class="datetime-header">
      <a class="datetime-brand" href="../dashboard/dashboard.php" aria-label="Beranda Pemon Barbershop">
        <span class="datetime-brand-script">pemon</span>
        <span class="datetime-brand-subtitle">BARBERSHOP</span>
      </a>
      <span class="datetime-header-label">RESERVASI ONLINE</span>
    </header>

    <main class="datetime-main confirmation-main">
      <section class="datetime-heading" aria-labelledby="confirmation-title">
        <p class="datetime-eyebrow">PEMESANAN LAYANAN</p>
        <h1 id="confirmation-title">Konfirmasi Pesanan</h1>
        <p>Periksa kembali detail pesanan. Data baru disimpan setelah kamu menekan tombol konfirmasi.</p>
      </section>

      <div class="datetime-layout confirmation-layout" id="reservation-review">
        <aside class="datetime-steps" aria-label="Tahapan pemesanan">
          <ol>
            <li class="datetime-step is-complete"><span class="datetime-step-number">1</span><div><strong>Pilih Layanan</strong><span>Pilihan layananmu</span></div></li>
            <li class="datetime-step is-complete"><span class="datetime-step-number">2</span><div><strong>Pilih Tanggal &amp; Jam</strong><span>Waktu reservasi</span></div></li>
            <li class="datetime-step is-current" aria-current="step"><span class="datetime-step-number">3</span><div><strong>Konfirmasi Reservasi</strong><span>Periksa dan konfirmasi</span></div></li>
            <li class="datetime-step"><span class="datetime-step-number">4</span><div><strong>Selesai</strong><span>Reservasi tersimpan</span></div></li>
          </ol>
        </aside>

        <section class="datetime-card confirmation-card" aria-labelledby="review-heading">
          <div class="confirmation-card-heading">
            <span class="datetime-card-icon"><svg aria-hidden="true"><use href="#cf-receipt"></use></svg></span>
            <div><h2 id="review-heading">Detail Reservasi</h2><p>Pastikan semua informasi sudah benar.</p></div>
          </div>

          <section class="review-section" aria-labelledby="customer-heading">
            <h3 id="customer-heading">Pelanggan</h3>
            <div class="customer-status <?= $customerId ? '' : 'is-loading' ?>" id="customer-status" role="status" <?= $customerId ? 'hidden' : '' ?>>Memeriksa sesi akun…</div>
            <div class="customer-details" id="customer-details" <?= !$customerId ? 'hidden' : '' ?>>
              <div><span>Nama Pelanggan</span><strong id="customer-name"><?= htmlspecialchars($customerName ?? '-') ?></strong></div>
              <div><span>No. WhatsApp</span><strong id="customer-whatsapp"><?= htmlspecialchars($customerWhatsapp ?? '-') ?></strong></div>
            </div>
            <div class="auth-required" id="auth-required" <?= $customerId ? 'hidden' : '' ?>>
              <p>Masuk atau buat akun untuk menyimpan reservasi ini ke akunmu.</p>
              <div><a class="secondary-action" href="login.html?next=booking-review.php">Masuk</a><a class="secondary-action" href="register.html?next=booking-review.php">Daftar</a></div>
            </div>
          </section>

          <section class="review-section" aria-labelledby="service-heading">
            <div class="section-title-row"><h3 id="service-heading">Layanan yang dipilih</h3><a href="../booking-service/booking_service.php">Ubah</a></div>
            <div class="review-detail-row"><span>Layanan</span><strong id="review-service">-</strong><strong id="review-service-price">-</strong></div>
            <div class="review-detail-row"><span>Gaya rambut</span><strong id="review-hairstyle">-</strong><span></span></div>
            <div class="review-detail-row"><span>Layanan tambahan</span><strong id="review-addons">-</strong><strong id="review-addons-price">-</strong></div>
            <div class="review-detail-row"><span>Estimasi durasi</span><strong id="review-duration">-</strong><span></span></div>
          </section>

          <section class="review-section" aria-labelledby="schedule-heading">
            <div class="section-title-row"><h3 id="schedule-heading">Jadwal</h3><a href="../booking-date-and-time/booking-datetime.php">Ubah</a></div>
            <div class="review-detail-row"><span>Tanggal</span><strong id="review-date">-</strong><span></span></div>
            <div class="review-detail-row"><span>Waktu</span><strong id="review-time">-</strong><span></span></div>
          </section>

          <section class="review-section payment-section" aria-labelledby="payment-heading">
            <h3 id="payment-heading">Metode pembayaran</h3>
            <div class="payment-options" role="radiogroup" aria-label="Metode pembayaran">
              <label class="payment-option is-selected"><input type="radio" name="payment-method" value="E-Wallet" checked><span><strong>E-Wallet (DANA, OVO, GoPay, ShopeePay)</strong><small>Petunjuk pembayaran diberikan setelah reservasi tersimpan</small></span></label>
              <label class="payment-option"><input type="radio" name="payment-method" value="QRIS"><span><strong>QRIS</strong><small>Bayar setelah reservasi dikonfirmasi</small></span></label>
              <label class="payment-option"><input type="radio" name="payment-method" value="Bayar di Barbershop"><span><strong>Bayar di Barbershop</strong><small>Bayar setelah layanan</small></span></label>
            </div>
            <p class="payment-note">Pilihan metode ini belum memproses pembayaran. Pembayaran dilakukan setelah reservasi tersimpan.</p>
          </section>

          <div class="confirmation-actions">
            <a class="secondary-action back-to-date" href="../booking-date-and-time/booking-datetime.php">Kembali</a>
            <button class="confirm-button" id="confirm-reservation" type="button" <?= $customerId ? '' : 'disabled' ?>><span id="confirm-label">Konfirmasi Reservasi</span><svg aria-hidden="true"><use href="#cf-arrow"></use></svg></button>
          </div>
          <p class="final-check-note">Slot dan harga diperiksa kembali saat konfirmasi. Jika pelanggan lain sudah mengambil slot tersebut, transaksi dibatalkan dan pesanan tidak disimpan.</p>
          <button class="cancel-reservation" id="cancel-reservation" type="button">Batalkan pemesanan</button>
        </section>

        <aside class="datetime-card final-summary" aria-labelledby="summary-heading">
          <div class="recap-title"><svg aria-hidden="true"><use href="#cf-receipt"></use></svg><h2 id="summary-heading">Ringkasan Reservasi</h2></div>
          <div class="summary-block"><span class="summary-label">LAYANAN</span><strong id="summary-service">-</strong><span id="summary-hairstyle">-</span></div>
          <div class="summary-block"><span class="summary-label">TANGGAL &amp; WAKTU</span><strong id="summary-date">-</strong><span id="summary-time">-</span></div>
          <div class="summary-block"><span class="summary-label">TAMBAHAN</span><strong id="summary-addons">Tidak ada</strong></div>
          <div class="summary-block"><span class="summary-label">ESTIMASI DURASI</span><strong id="summary-duration">-</strong></div>
          <div class="summary-block"><span class="summary-label">METODE PEMBAYARAN</span><strong id="summary-payment">-</strong></div>
          <div class="summary-total"><span>Total harga</span><strong id="summary-total">-</strong></div>
          <p class="summary-reminder"><span aria-hidden="true">i</span>Data pilihanmu belum disimpan sebagai reservasi.</p>
        </aside>
      </div>

      <section class="datetime-card confirmation-success" id="reservation-success" hidden aria-labelledby="success-heading">
        <span class="success-mark" aria-hidden="true">✓</span>
        <p class="datetime-eyebrow">RESERVASI TERSIMPAN</p>
        <h2 id="success-heading">Reservasimu berhasil dibuat</h2>
        <p>Terima kasih, <strong id="success-customer"><?= htmlspecialchars($customerName ?? 'pelanggan') ?></strong>. Tunjukkan kode ini saat datang ke Pemon Barbershop.</p>
        <div class="success-code"><span>Kode reservasi</span><strong id="success-code-value">-</strong></div>
        <div class="success-details"><span id="success-date">-</span><span id="success-time">-</span><strong id="success-total">-</strong></div>
        <p class="success-payment">Status pembayaran: <strong id="success-payment-status">menunggu pembayaran</strong>. Metode pilihan: <strong id="success-payment-method">-</strong>.</p>
        <a class="confirm-button success-home" href="../reservation-history/reservation_history.php">Lihat Riwayat Reservasi <svg aria-hidden="true"><use href="#cf-arrow"></use></svg></a>
        <a class="secondary-action" href="../dashboard/dashboard.php">Ke Dasbor</a>
      </section>
    </main>
  </div>

  <div class="datetime-toast confirmation-toast" id="confirmation-toast" role="status" aria-live="polite"></div>
  <svg class="datetime-icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="cf-receipt" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14l-3-2-3 2-3-2-4 2z"/><path d="M14 3v5h5M9 12h6m-6 4h5"/></symbol>
    <symbol id="cf-arrow" viewBox="0 0 24 24"><path d="M4 12h15m-6-6 6 6-6 6"/></symbol>
  </svg>
</body>
</html>
