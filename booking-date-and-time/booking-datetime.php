<?php
declare(strict_types=1);

session_start();

// Handle AJAX availability requests if query parameters 'month' or 'date' are provided via GET
if (isset($_GET['month']) || isset($_GET['date'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    function validDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }

    $month = isset($_GET['month']) && is_string($_GET['month']) ? $_GET['month'] : '';
    $date = isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : '';
    if (($month === '' && $date === '') || ($month !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) || ($date !== '' && !validDate($date))) {
        respond(400, ['error' => 'Pilih bulan atau tanggal yang valid']);
    }

    require_once __DIR__ . '/config/database.php';

    try {
        $pdo = getDatabaseConnection();

        if ($month !== '') {
            [$year, $monthNumber] = array_map('intval', explode('-', $month));
            if (!checkdate($monthNumber, 1, $year)) respond(400, ['error' => 'Bulan tidak valid']);
            $firstDate = sprintf('%04d-%02d-01', $year, $monthNumber);
            $lastDate = date('Y-m-t', strtotime($firstDate));
            $statement = $pdo->prepare(
                "SELECT s.slot_date,
                        SUM(CASE WHEN GREATEST(s.reserved_count, COALESCE(booked.booked_count, 0)) < s.capacity THEN 1 ELSE 0 END) AS available_count,
                        SUM(CASE WHEN GREATEST(s.reserved_count, COALESCE(booked.booked_count, 0)) >= s.capacity THEN 1 ELSE 0 END) AS full_count
                 FROM booking_time_slots AS s
                 LEFT JOIN (
                     SELECT appointment_date, start_time, COUNT(*) AS booked_count
                     FROM reservations
                     WHERE status IN ('upcoming', 'pending_confirmation', 'confirmed')
                     GROUP BY appointment_date, start_time
                 ) AS booked ON booked.appointment_date = s.slot_date AND booked.start_time = s.start_time
                 WHERE s.is_active = 1 AND s.slot_date BETWEEN :first_date AND :last_date
                 GROUP BY s.slot_date
                 ORDER BY s.slot_date"
            );
            $statement->execute(['first_date' => $firstDate, 'last_date' => $lastDate]);
            $dates = array_map(static function (array $row): array {
                $availableCount = (int) $row['available_count'];
                $fullCount = (int) $row['full_count'];
                return [
                    'date' => $row['slot_date'],
                    'status' => $availableCount > 0 ? 'available' : ($fullCount > 0 ? 'full' : 'unavailable'),
                ];
            }, $statement->fetchAll());
            respond(200, ['dates' => $dates]);
        }

        $statement = $pdo->prepare(
            "SELECT s.start_time, s.capacity, s.reserved_count,
                    COALESCE(booked.booked_count, 0) AS booked_count
             FROM booking_time_slots AS s
             LEFT JOIN (
                 SELECT appointment_date, start_time, COUNT(*) AS booked_count
                 FROM reservations
                 WHERE status IN ('upcoming', 'pending_confirmation', 'confirmed')
                 GROUP BY appointment_date, start_time
             ) AS booked ON booked.appointment_date = s.slot_date AND booked.start_time = s.start_time
             WHERE s.slot_date = :slot_date AND s.is_active = 1
             ORDER BY s.start_time"
        );
        $statement->execute(['slot_date' => $date]);
        $slots = array_map(static function (array $row): array {
            $capacity = (int) $row['capacity'];
            $reserved = max((int) $row['reserved_count'], (int) $row['booked_count']);
            return [
                'time' => substr((string) $row['start_time'], 0, 5),
                'remaining_capacity' => max(0, $capacity - $reserved),
                'status' => $reserved >= $capacity ? 'unavailable' : 'available',
            ];
        }, $statement->fetchAll());
        respond(200, ['slots' => $slots]);
    } catch (Throwable $error) {
        error_log('Booking availability API error: ' . $error->getMessage());
        respond(500, ['error' => 'Ketersediaan jadwal belum dapat dimuat']);
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06483d">
  <meta name="description" content="Pilih tanggal dan waktu reservasi Pemon Barbershop.">
  <title>Pilih Tanggal &amp; Jam — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="booking-datetime.css">
  <script src="booking-datetime.js" defer></script>
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

    <main class="datetime-main">
      <section class="datetime-heading" aria-labelledby="datetime-title">
        <p class="datetime-eyebrow">PEMESANAN LAYANAN</p>
        <h1 id="datetime-title">2. Pilih Tanggal &amp; Jam</h1>
        <p>Pilih tanggal dan waktu yang sesuai dengan jadwalmu. Pastikan kamu datang tepat waktu agar layanan berjalan maksimal.</p>
      </section>

      <div class="datetime-layout">
        <aside class="datetime-steps" aria-label="Tahapan pemesanan">
          <ol>
            <li class="datetime-step is-complete"><span class="datetime-step-number">1</span><div><strong>Pilih Layanan</strong><span>Pilih layanan yang kamu inginkan</span></div></li>
            <li class="datetime-step is-current" aria-current="step"><span class="datetime-step-number">2</span><div><strong>Pilih Tanggal &amp; Jam</strong><span>Pilih waktu reservasi</span></div></li>
            <li class="datetime-step"><span class="datetime-step-number">3</span><div><strong>Konfirmasi Pemesanan</strong><span>Pilih pembayaran</span></div></li>
            <li class="datetime-step"><span class="datetime-step-number">4</span><div><strong>Selesai</strong><span>Nota langsung tersedia</span></div></li>
          </ol>
        </aside>

        <section class="datetime-card calendar-card" aria-labelledby="calendar-heading">
          <div class="datetime-card-heading">
            <span class="datetime-card-icon"><svg aria-hidden="true"><use href="#dt-calendar"></use></svg></span>
            <div><h2 id="calendar-heading">Tanggal &amp; Waktu</h2><p>Pilih tanggal dan waktu reservasi kamu.</p></div>
          </div>
          <div class="calendar-widget">
            <div class="calendar-month-bar">
              <button type="button" id="previous-month" aria-label="Bulan sebelumnya"><svg aria-hidden="true"><use href="#dt-chevron-left"></use></svg></button>
              <strong id="calendar-month">September 2026</strong>
              <button type="button" id="next-month" aria-label="Bulan berikutnya"><svg aria-hidden="true"><use href="#dt-chevron-right"></use></svg></button>
            </div>
            <div class="calendar-weekdays" aria-hidden="true"><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span></div>
            <div class="calendar-days" id="calendar-days" role="grid" aria-label="Kalender"></div>
          </div>
          <div class="calendar-legend">
            <span><i class="legend-available"></i>Tersedia</span>
            <span><i class="legend-unavailable"></i>Tidak Tersedia</span>
            <span><i class="legend-full"></i>Penuh</span>
          </div>
        </section>

        <section class="datetime-card time-card" aria-labelledby="time-heading">
          <div class="datetime-card-heading">
            <span class="datetime-card-icon clock-icon"><svg aria-hidden="true"><use href="#dt-clock"></use></svg></span>
            <div><h2 id="time-heading">Pilih Waktu</h2><p>Pilih tanggal terlebih dahulu, lalu pilih slot yang masih tersedia.</p></div>
          </div>
          <div class="selected-date-banner"><svg aria-hidden="true"><use href="#dt-calendar"></use></svg><strong id="selected-date-label">Pilih tanggal terlebih dahulu</strong></div>
          <div class="time-slots" id="time-slots" aria-label="Pilihan waktu"></div>
          <div class="time-legend"><span><i class="legend-time-available"></i>Tersedia</span><span><i class="legend-unavailable dark"></i>Tidak Tersedia</span></div>
        </section>

        <aside class="datetime-card booking-recap" aria-labelledby="recap-heading">
          <div class="recap-title"><svg aria-hidden="true"><use href="#dt-receipt"></use></svg><h2 id="recap-heading">Ringkasan Reservasi</h2></div>
          <div class="recap-row recap-editable">
            <span class="recap-icon"><svg aria-hidden="true"><use href="#dt-scissors"></use></svg></span>
            <span class="recap-copy"><strong>Layanan</strong><span id="recap-service-name">Potong Rambut</span><span class="recap-subvalue" id="recap-service-price">Rp 50.000</span></span>
            <button type="button" data-edit="service">Ubah</button>
          </div>
          <div class="recap-row recap-editable">
            <span class="recap-icon"><svg aria-hidden="true"><use href="#dt-person"></use></svg></span>
            <span class="recap-copy"><strong>Gaya Rambut</strong><span id="recap-hairstyle-name">Potongan Klasik</span></span>
            <button type="button" data-edit="service">Ubah</button>
          </div>
          <div class="recap-row recap-editable">
            <span class="recap-icon"><svg aria-hidden="true"><use href="#dt-plus"></use></svg></span>
            <span class="recap-copy"><strong>Layanan Tambahan</strong><span id="recap-addons-name">-</span><span class="recap-subvalue" id="recap-addons-price"></span></span>
            <button type="button" data-edit="service">Ubah</button>
          </div>
          <div class="recap-row recap-editable">
            <span class="recap-icon"><svg aria-hidden="true"><use href="#dt-calendar"></use></svg></span>
            <span class="recap-copy"><strong>Tanggal</strong><span id="recap-date">Pilih tanggal</span></span>
            <button type="button" data-edit="date">Ubah</button>
          </div>
          <div class="recap-row recap-editable">
            <span class="recap-icon"><svg aria-hidden="true"><use href="#dt-clock"></use></svg></span>
            <span class="recap-copy"><strong>Waktu</strong><span id="recap-time">-</span></span>
            <button type="button" data-edit="time">Ubah</button>
          </div>
          <div class="recap-total"><strong>Total Harga</strong><strong id="recap-total">Rp 50.000</strong></div>
          <p class="draft-note">Pilihan ini masih berupa draf. Reservasi baru disimpan setelah kamu menekan <strong>Konfirmasi Reservasi</strong> di langkah terakhir.</p>
          <button class="recap-continue" type="button" id="continue-booking"><span id="continue-booking-label">Lanjut ke Konfirmasi</span><svg aria-hidden="true"><use href="#dt-arrow"></use></svg></button>
        </aside>
      </div>

      <a class="datetime-back" href="booking-services.html"><svg aria-hidden="true"><use href="#dt-arrow-left"></use></svg>Kembali</a>
      <button class="datetime-cancel" type="button" id="cancel-booking">Batalkan pemesanan</button>
    </main>
  </div>

  <div class="datetime-toast" id="datetime-toast" role="status" aria-live="polite"></div>
  <svg class="datetime-icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="dt-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></symbol>
    <symbol id="dt-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
    <symbol id="dt-receipt" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14l-3-2-3 2-3-2-4 2z"/><path d="M14 3v5h5M9 12h6m-6 4h5"/></symbol>
    <symbol id="dt-scissors" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.2 8.2 12 12M8.2 15.8 20 4M13 12l-2.2 2.2"/></symbol>
    <symbol id="dt-person" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3"/><path d="M5 20c.5-4 2.8-6 7-6s6.5 2 7 6z"/></symbol>
    <symbol id="dt-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></symbol>
    <symbol id="dt-arrow" viewBox="0 0 24 24"><path d="M4 12h15m-6-6 6 6-6 6"/></symbol>
    <symbol id="dt-arrow-left" viewBox="0 0 24 24"><path d="M20 12H5m6 6-6-6 6-6"/></symbol>
    <symbol id="dt-chevron-left" viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></symbol>
    <symbol id="dt-chevron-right" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
  </svg>
</body>
</html>
