<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

function confirmationRespond(int $status, array $payload, bool $isAjax = false): void
{
    if ($isAjax || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

function confirmationDateIsValid(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
}

function reservationByRequestToken(PDO $pdo, string $token, bool $lock = false): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, customer_id, reservation_code, status, appointment_date, start_time, amount, payment_method
         FROM reservations WHERE request_token = :request_token LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['request_token' => $token]);
    $reservation = $statement->fetch();
    return $reservation ?: null;
}

function confirmationReservationPayload(array $reservation): array
{
    return [
        'reservation_id' => (int) $reservation['id'],
        'reservation_code' => $reservation['reservation_code'],
        'status' => $reservation['status'],
        'date' => $reservation['appointment_date'],
        'time' => substr((string) $reservation['start_time'], 0, 5),
        'amount' => (int) $reservation['amount'],
        'payment_method' => $reservation['payment_method'] ?? null,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

$customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$customerName = null;
$customerWhatsapp = null;
$errorMessage = '';
$successData = null;

require_once __DIR__ . '/config/database.php';

if ($customerId) {
    try {
        $pdoDb = getDatabaseConnection();
        $custStmt = $pdoDb->prepare('SELECT name, whatsapp FROM customers WHERE id = :id LIMIT 1');
        $custStmt->execute(['id' => $customerId]);
        $custData = $custStmt->fetch();
        if ($custData) {
            $customerName = $custData['name'];
            $customerWhatsapp = $custData['whatsapp'];
        }
    } catch (Throwable $e) {
        error_log('Customer info fetch error: ' . $e->getMessage());
    }
}

if ($method === 'POST') {
    $input = [];
    if ($isAjaxRequest) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : [];
    } else {
        $input = $_POST;
    }

    if (!$customerId) {
        if ($isAjaxRequest) confirmationRespond(401, ['error' => 'Silakan masuk sebelum mengonfirmasi reservasi'], true);
        $errorMessage = 'Silakan masuk sebelum mengonfirmasi reservasi.';
    } else {
        $date = is_string($input['date'] ?? null) ? $input['date'] : '';
        $time = is_string($input['time'] ?? null) ? $input['time'] : '';
        $token = strtolower(is_string($input['request_token'] ?? null) ? $input['request_token'] : '');
        $serviceId = filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $hairstyleId = filter_var($input['hairstyle_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $addonIds = $input['addon_ids'] ?? [];
        $paymentMethod = is_string($input['payment_method'] ?? null) ? $input['payment_method'] : '';
        $allowedPaymentMethods = ['Bayar di Barbershop', 'E-Wallet', 'DANA', 'OVO', 'GoPay', 'ShopeePay', 'QRIS'];

        if (!confirmationDateIsValid($date) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $errorMessage = 'Pilih tanggal dan waktu yang valid';
            if ($isAjaxRequest) confirmationRespond(400, ['error' => $errorMessage], true);
        } elseif (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $token) || !$serviceId || !$hairstyleId || !is_array($addonIds) || count($addonIds) > 10) {
            $errorMessage = 'Pilihan layanan atau kode reservasi tidak valid';
            if ($isAjaxRequest) confirmationRespond(400, ['error' => $errorMessage], true);
        } elseif (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
            $errorMessage = 'Pilih metode pembayaran yang tersedia';
            if ($isAjaxRequest) confirmationRespond(422, ['error' => $errorMessage], true);
        } else {
            $addonIds = array_values(array_unique(array_map(static function ($id) {
                return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            }, $addonIds)));
            
            if (in_array(null, $addonIds, true)) {
                $errorMessage = 'Pilihan layanan tambahan tidak valid';
                if ($isAjaxRequest) confirmationRespond(400, ['error' => $errorMessage], true);
            } else {
                try {
                    $pdo = getDatabaseConnection();
                    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                    $pdo->beginTransaction();

                    $existing = reservationByRequestToken($pdo, $token, true);
                    if ($existing) {
                        $pdo->commit();
                        if ((int) $existing['customer_id'] !== (int) $customerId) {
                            $errorMessage = 'Kode konfirmasi ini sudah digunakan. Muat ulang halaman dan coba lagi.';
                            if ($isAjaxRequest) confirmationRespond(409, ['code' => 'REQUEST_CONFLICT', 'error' => $errorMessage], true);
                        } else {
                            $successData = confirmationReservationPayload($existing);
                            if ($isAjaxRequest) confirmationRespond(200, ['reservation' => $successData, 'already_saved' => true], true);
                        }
                    } else {
                        $slotStatement = $pdo->prepare(
                            'SELECT id, capacity, reserved_count
                             FROM booking_time_slots
                             WHERE slot_date = :slot_date AND start_time = :start_time AND is_active = 1
                             FOR UPDATE'
                        );
                        $slotStatement->execute(['slot_date' => $date, 'start_time' => $time . ':00']);
                        $slot = $slotStatement->fetch();
                        
                        if (!$slot) {
                            $pdo->rollBack();
                            $errorMessage = 'Waktu ini tidak tersedia. Silakan pilih jadwal lain.';
                            if ($isAjaxRequest) confirmationRespond(409, ['code' => 'TIME_UNAVAILABLE', 'error' => $errorMessage], true);
                        } else {
                            $activeStatement = $pdo->prepare(
                                "SELECT COUNT(*) FROM reservations
                                 WHERE appointment_date = :appointment_date AND start_time = :start_time
                                   AND status IN ('upcoming', 'pending_confirmation', 'confirmed')"
                            );
                            $activeStatement->execute(['appointment_date' => $date, 'start_time' => $time . ':00']);
                            $activeReservations = (int) $activeStatement->fetchColumn();
                            $capacity = (int) $slot['capacity'];
                            $occupied = max((int) $slot['reserved_count'], $activeReservations);
                            
                            if ($occupied >= $capacity) {
                                $pdo->rollBack();
                                $errorMessage = 'Waktu ini baru saja dipesan pelanggan lain. Silakan pilih waktu lain.';
                                if ($isAjaxRequest) confirmationRespond(409, ['code' => 'TIME_UNAVAILABLE', 'error' => $errorMessage], true);
                            } else {
                                $catalogIds = array_values(array_unique(array_merge([(int) $serviceId, (int) $hairstyleId], $addonIds)));
                                $placeholders = implode(',', array_fill(0, count($catalogIds), '?'));
                                $catalogStatement = $pdo->prepare(
                                    "SELECT id, category, name, price, duration_minutes
                                     FROM booking_catalog_items
                                     WHERE id IN ($placeholders) AND is_active = 1"
                                );
                                $catalogStatement->execute($catalogIds);
                                $catalog = [];
                                foreach ($catalogStatement->fetchAll() as $item) $catalog[(int) $item['id']] = $item;

                                $service = $catalog[(int) $serviceId] ?? null;
                                $hairstyle = $catalog[(int) $hairstyleId] ?? null;
                                
                                if (!$service || $service['category'] !== 'service' || !$hairstyle || $hairstyle['category'] !== 'hairstyle') {
                                    $pdo->rollBack();
                                    $errorMessage = 'Layanan atau gaya rambut sudah tidak tersedia. Muat ulang pilihan layanan.';
                                    if ($isAjaxRequest) confirmationRespond(422, ['error' => $errorMessage], true);
                                } else {
                                    $addons = [];
                                    foreach ($addonIds as $addonId) {
                                        $addon = $catalog[(int) $addonId] ?? null;
                                        if (!$addon || $addon['category'] !== 'addon') {
                                            $pdo->rollBack();
                                            $errorMessage = 'Layanan tambahan sudah tidak tersedia. Muat ulang pilihan layanan.';
                                            if ($isAjaxRequest) confirmationRespond(422, ['error' => $errorMessage], true);
                                            break;
                                        }
                                        $addons[] = $addon;
                                    }

                                    if ($errorMessage === '') {
                                        $amount = (int) $service['price'];
                                        $duration = max(1, (int) $service['duration_minutes']);
                                        foreach ($addons as $addon) {
                                            $amount += (int) $addon['price'];
                                            $duration += (int) $addon['duration_minutes'];
                                        }
                                        $startAt = DateTimeImmutable::createFromFormat('!H:i', $time);
                                        $endTime = $startAt->modify('+' . $duration . ' minutes')->format('H:i:s');
                                        $reservationCode = sprintf('PB-%s-%s', str_replace('-', '', $date), strtoupper(bin2hex(random_bytes(4))));
                                        $addonNames = $addons ? implode(', ', array_column($addons, 'name')) : null;

                                        $insert = $pdo->prepare(
                                            "INSERT INTO reservations
                                                (customer_id, reservation_code, request_token, status, service_name, hairstyle_name,
                                                 additional_service, appointment_date, start_time, end_time, amount, payment_method, payment_status)
                                             VALUES
                                                (:customer_id, :reservation_code, :request_token, 'pending_confirmation', :service_name, :hairstyle_name,
                                                 :additional_service, :appointment_date, :start_time, :end_time, :amount, :payment_method, 'Menunggu pembayaran')"
                                        );
                                        $insert->execute([
                                            'customer_id' => $customerId,
                                            'reservation_code' => $reservationCode,
                                            'request_token' => $token,
                                            'service_name' => $service['name'],
                                            'hairstyle_name' => $hairstyle['name'],
                                            'additional_service' => $addonNames,
                                            'appointment_date' => $date,
                                            'start_time' => $time . ':00',
                                            'end_time' => $endTime,
                                            'amount' => $amount,
                                            'payment_method' => $paymentMethod,
                                        ]);

                                        $reservationId = (int) $pdo->lastInsertId();
                                        $pdo->commit();
                                        
                                        $successData = confirmationReservationPayload([
                                            'id' => $reservationId,
                                            'reservation_code' => $reservationCode,
                                            'status' => 'pending_confirmation',
                                            'appointment_date' => $date,
                                            'start_time' => $time . ':00',
                                            'amount' => $amount,
                                            'payment_method' => $paymentMethod,
                                        ]);

                                        if ($isAjaxRequest) confirmationRespond(201, ['reservation' => $successData], true);
                                    }
                                }
                            }
                        }
                    }
                } catch (Throwable $error) {
                    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                    error_log('Reservation confirmation error: ' . $error->getMessage());
                    $errorMessage = 'Reservasi belum dapat dikonfirmasi. Coba lagi sebentar.';
                    if ($isAjaxRequest) confirmationRespond(500, ['error' => $errorMessage], true);
                }
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
  <meta name="theme-color" content="#06483d">
  <meta name="description" content="Periksa detail layanan dan konfirmasikan reservasi Pemon Barbershop.">
  <title>Konfirmasi Reservasi — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="booking-datetime.css">
  <link rel="stylesheet" href="booking-confirmation.css">
  <script src="booking-confirmation.js" defer></script>
</head>
<body>
  <div class="datetime-frame">
    <header class="datetime-header">
      <a class="datetime-brand" href="index.html" aria-label="Beranda Pemon Barbershop">
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

      <?php if (!empty($errorMessage)): ?>
        <div class="auth-error-banner" style="color: #b91c1c; background: #fee2e2; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
          <?= htmlspecialchars($errorMessage) ?>
        </div>
      <?php endif; ?>

      <div class="datetime-layout confirmation-layout" id="reservation-review" <?= $successData ? 'hidden' : '' ?>>
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
              <div><a class="secondary-action" href="login.html?next=booking-confirmation.html">Masuk</a><a class="secondary-action" href="register.html?next=booking-confirmation.html">Daftar</a></div>
            </div>
          </section>

          <section class="review-section" aria-labelledby="service-heading">
            <div class="section-title-row"><h3 id="service-heading">Layanan yang dipilih</h3><a href="booking-services.html">Ubah</a></div>
            <div class="review-detail-row"><span>Layanan</span><strong id="review-service">-</strong><strong id="review-service-price">-</strong></div>
            <div class="review-detail-row"><span>Gaya rambut</span><strong id="review-hairstyle">-</strong><span></span></div>
            <div class="review-detail-row"><span>Layanan tambahan</span><strong id="review-addons">-</strong><strong id="review-addons-price">-</strong></div>
            <div class="review-detail-row"><span>Estimasi durasi</span><strong id="review-duration">-</strong><span></span></div>
          </section>

          <section class="review-section" aria-labelledby="schedule-heading">
            <div class="section-title-row"><h3 id="schedule-heading">Jadwal</h3><a href="booking-datetime.html">Ubah</a></div>
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
            <a class="secondary-action back-to-date" href="booking-datetime.html">Kembali</a>
            <button class="confirm-button" id="confirm-reservation" type="button" <?= $customerId ? '' : 'disabled' ?>><span id="confirm-label">Konfirmasi Pesanan</span><svg aria-hidden="true"><use href="#cf-arrow"></use></svg></button>
          </div>
          <p class="final-check-note">Slot diperiksa kembali saat konfirmasi. Jika pelanggan lain sudah mengambilnya, transaksi dibatalkan dan pesanan tidak disimpan.</p>
          <button class="cancel-reservation" id="cancel-reservation" type="button">Batalkan pemesanan</button>
        </section>

        <aside class="datetime-card final-summary" aria-labelledby="summary-heading">
          <div class="recap-title"><svg aria-hidden="true"><use href="#cf-receipt"></use></svg><h2 id="summary-heading">Ringkasan Reservasi</h2></div>
          <div class="summary-block"><span class="summary-label">LAYANAN</span><strong id="summary-service">-</strong><span id="summary-hairstyle">-</span></div>
          <div class="summary-block"><span class="summary-label">TANGGAL &amp; WAKTU</span><strong id="summary-date">-</strong><span id="summary-time">-</span></div>
          <div class="summary-block"><span class="summary-label">TAMBAHAN</span><strong id="summary-addons">Tidak ada</strong></div>
          <div class="summary-block"><span class="summary-label">ESTIMASI DURASI</span><strong id="summary-duration">-</strong></div>
          <div class="summary-total"><span>Total harga</span><strong id="summary-total">-</strong></div>
          <p class="summary-reminder"><span aria-hidden="true">i</span>Data pilihanmu belum disimpan sebagai reservasi.</p>
        </aside>
      </div>

      <section class="datetime-card confirmation-success" id="reservation-success" <?= $successData ? '' : 'hidden' ?> aria-labelledby="success-heading">
        <span class="success-mark" aria-hidden="true">✓</span>
        <p class="datetime-eyebrow">RESERVASI TERSIMPAN</p>
        <h2 id="success-heading">Reservasimu berhasil dibuat</h2>
        <p>Terima kasih, <strong id="success-customer"><?= htmlspecialchars($customerName ?? 'pelanggan') ?></strong>. Tunjukkan kode ini saat datang ke Pemon Barbershop.</p>
        <div class="success-code"><span>Kode reservasi</span><strong id="success-code-value"><?= htmlspecialchars($successData['reservation_code'] ?? '-') ?></strong></div>
        <div class="success-details"><span id="success-date"><?= htmlspecialchars($successData['date'] ?? '-') ?></span><span id="success-time"><?= htmlspecialchars($successData['time'] ?? '-') ?></span><strong id="success-total"><?= isset($successData['amount']) ? 'Rp ' . number_format($successData['amount'], 0, ',', '.') : '-' ?></strong></div>
        <p class="success-payment">Status pembayaran: menunggu pembayaran. Metode pilihan: <strong id="success-payment-method"><?= htmlspecialchars($successData['payment_method'] ?? '-') ?></strong>.</p>
        <a class="confirm-button success-home" href="dashboard.php">Lihat Dasbor <svg aria-hidden="true"><use href="#cf-arrow"></use></svg></a>
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
