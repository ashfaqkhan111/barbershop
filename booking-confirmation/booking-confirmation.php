<?php
declare(strict_types=1);

/*
 * Step 4: Submit Confirmation (JSON API endpoint, not a page).
 *
 * The review page (booking-review.php) POSTs the customer's selections here when
 * they press "Konfirmasi Reservasi". Nothing is saved before this request.
 *
 * The request is validated (session, selections, price against the server-side
 * catalog, slot availability) and then everything is written in ONE transaction:
 *   1. reservations                       (customer, date, time, status, total_price)
 *   2. reservation_additional_services    (selected add-ons)
 *   3. payments                           (linked via reservation_id)
 *   4. booking_time_slots.reserved_count  (slot marked as taken)
 * If the slot is gone or any write fails the transaction is rolled back, so no
 * partial reservation or payment record is kept. `request_token` makes a repeat
 * of the same request return the already saved reservation instead of a copy.
 *
 * Responses: 201 saved | 200 already saved (same token) | 4xx validation / 409
 * TIME_UNAVAILABLE or PRICE_CHANGED | 500 error.
 */

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

const CONFIRMATION_PAYMENT_METHODS = ['Bayar di Barbershop', 'E-Wallet', 'DANA', 'OVO', 'GoPay', 'ShopeePay', 'QRIS'];

function confirmationRespond(int $status, array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function confirmationDateIsValid(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
}

function confirmationPaymentStatusLabel(?string $status): string
{
    return match ($status) {
        'paid' => 'Lunas',
        'failed' => 'Gagal',
        'refunded' => 'Dikembalikan',
        default => 'Menunggu pembayaran',
    };
}

/** Finds a reservation (and its latest payment) by the confirmation request token. */
function reservationByRequestToken(PDO $pdo, string $token, bool $lock = false): ?array
{
    $statement = $pdo->prepare(
        'SELECT r.id, r.customer_id, r.reservation_code, r.status, r.appointment_date, r.start_time, r.total_price,
                p.payment_id, p.payment_method, p.payment_status, p.transaction_reference
         FROM reservations AS r
         LEFT JOIN payments AS p
                ON p.payment_id = (SELECT MAX(p2.payment_id) FROM payments AS p2 WHERE p2.reservation_id = r.id)
         WHERE r.request_token = :request_token
         LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['request_token' => $token]);
    $reservation = $statement->fetch(PDO::FETCH_ASSOC);
    return $reservation ?: null;
}

function confirmationReservationPayload(array $reservation): array
{
    $paymentStatus = $reservation['payment_status'] ?? null;
    return [
        'reservation_id' => (int) $reservation['id'],
        'reservation_code' => $reservation['reservation_code'],
        'status' => $reservation['status'],
        'date' => $reservation['appointment_date'],
        'time' => substr((string) $reservation['start_time'], 0, 5),
        'total_price' => (int) $reservation['total_price'],
        'amount' => (int) $reservation['total_price'], // kept for older clients
        'payment_id' => isset($reservation['payment_id']) ? (int) $reservation['payment_id'] : null,
        'payment_method' => $reservation['payment_method'] ?? null,
        'payment_status' => $paymentStatus,
        'payment_status_label' => confirmationPaymentStatusLabel($paymentStatus),
        'transaction_reference' => $reservation['transaction_reference'] ?? null,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    confirmationRespond(405, ['error' => 'Gunakan tombol konfirmasi pada halaman reservasi.']);
}

$customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$customerId) {
    confirmationRespond(401, ['error' => 'Silakan masuk sebelum mengonfirmasi reservasi']);
}

if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($decoded) ? $decoded : [];
} else {
    $input = $_POST;
}

$date = is_string($input['date'] ?? null) ? $input['date'] : '';
$time = is_string($input['time'] ?? null) ? $input['time'] : '';
$token = strtolower(is_string($input['request_token'] ?? null) ? $input['request_token'] : '');
$serviceId = filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$hairstyleId = filter_var($input['hairstyle_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$addonIds = $input['addon_ids'] ?? [];
$paymentMethod = is_string($input['payment_method'] ?? null) ? $input['payment_method'] : '';
$expectedTotal = filter_var($input['expected_total'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$expectedTotal = $expectedTotal === false ? null : $expectedTotal;

if (!confirmationDateIsValid($date) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
    confirmationRespond(400, ['error' => 'Pilih tanggal dan waktu yang valid']);
}
if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $token) || !$serviceId || !$hairstyleId || !is_array($addonIds) || count($addonIds) > 10) {
    confirmationRespond(400, ['error' => 'Pilihan layanan atau kode reservasi tidak valid']);
}
if (!in_array($paymentMethod, CONFIRMATION_PAYMENT_METHODS, true)) {
    confirmationRespond(422, ['error' => 'Pilih metode pembayaran yang tersedia']);
}

$addonIds = array_values(array_unique(array_map(static function ($id) {
    return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
}, $addonIds)));
if (in_array(null, $addonIds, true)) {
    confirmationRespond(400, ['error' => 'Pilihan layanan tambahan tidak valid']);
}

if (!is_file(__DIR__ . '/../config/database.php')) {
    error_log('Reservation confirmation error: config/database.php is missing');
    confirmationRespond(500, ['error' => 'Reservasi belum dapat dikonfirmasi. Coba lagi sebentar.']);
}
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();

    $customerStatement = $pdo->prepare('SELECT id FROM customers WHERE id = :id LIMIT 1');
    $customerStatement->execute(['id' => $customerId]);
    if (!$customerStatement->fetchColumn()) {
        confirmationRespond(401, ['error' => 'Sesi akun tidak valid. Silakan masuk kembali.']);
    }

    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();

    // A repeat of an already saved request returns that reservation unchanged.
    $existing = reservationByRequestToken($pdo, $token, true);
    if ($existing) {
        $pdo->commit();
        if ((int) $existing['customer_id'] !== (int) $customerId) {
            confirmationRespond(409, ['code' => 'REQUEST_CONFLICT', 'error' => 'Kode konfirmasi ini sudah digunakan. Muat ulang halaman dan coba lagi.']);
        }
        confirmationRespond(200, ['reservation' => confirmationReservationPayload($existing), 'already_saved' => true]);
    }

    // Lock the slot row so concurrent confirmations for the same time are serialised.
    $slotStatement = $pdo->prepare(
        'SELECT id, capacity, reserved_count
         FROM booking_time_slots
         WHERE slot_date = :slot_date AND start_time = :start_time AND is_active = 1
         FOR UPDATE'
    );
    $slotStatement->execute(['slot_date' => $date, 'start_time' => $time . ':00']);
    $slot = $slotStatement->fetch(PDO::FETCH_ASSOC);
    if (!$slot) {
        $pdo->rollBack();
        confirmationRespond(409, ['code' => 'TIME_UNAVAILABLE', 'error' => 'Waktu ini tidak tersedia. Silakan pilih jadwal lain.']);
    }

    // The same request may have been saved while this one waited for the slot lock.
    $existing = reservationByRequestToken($pdo, $token, true);
    if ($existing) {
        $pdo->commit();
        if ((int) $existing['customer_id'] !== (int) $customerId) {
            confirmationRespond(409, ['code' => 'REQUEST_CONFLICT', 'error' => 'Kode konfirmasi ini sudah digunakan. Muat ulang halaman dan coba lagi.']);
        }
        confirmationRespond(200, ['reservation' => confirmationReservationPayload($existing), 'already_saved' => true]);
    }

    $activeStatement = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE appointment_date = :appointment_date AND start_time = :start_time
           AND status IN ('upcoming', 'pending_confirmation', 'confirmed')"
    );
    $activeStatement->execute(['appointment_date' => $date, 'start_time' => $time . ':00']);
    $occupied = max((int) $slot['reserved_count'], (int) $activeStatement->fetchColumn());
    if ($occupied >= (int) $slot['capacity']) {
        $pdo->rollBack();
        confirmationRespond(409, ['code' => 'TIME_UNAVAILABLE', 'error' => 'Waktu ini baru saja dipesan pelanggan lain. Silakan pilih waktu lain.']);
    }

    // Price and duration always come from the server-side catalog, never the browser.
    $catalogIds = array_values(array_unique(array_merge([(int) $serviceId, (int) $hairstyleId], $addonIds)));
    $placeholders = implode(',', array_fill(0, count($catalogIds), '?'));
    $catalogStatement = $pdo->prepare(
        "SELECT id, category, name, price, duration_minutes
         FROM booking_catalog_items
         WHERE id IN ($placeholders) AND is_active = 1"
    );
    $catalogStatement->execute($catalogIds);
    $catalog = [];
    foreach ($catalogStatement->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $catalog[(int) $item['id']] = $item;
    }

    $service = $catalog[(int) $serviceId] ?? null;
    $hairstyle = $catalog[(int) $hairstyleId] ?? null;
    if (!$service || $service['category'] !== 'service' || !$hairstyle || $hairstyle['category'] !== 'hairstyle') {
        $pdo->rollBack();
        confirmationRespond(422, ['error' => 'Layanan atau gaya rambut sudah tidak tersedia. Muat ulang pilihan layanan.']);
    }

    $addons = [];
    foreach ($addonIds as $addonId) {
        $addon = $catalog[(int) $addonId] ?? null;
        if (!$addon || $addon['category'] !== 'addon') {
            $pdo->rollBack();
            confirmationRespond(422, ['error' => 'Layanan tambahan sudah tidak tersedia. Muat ulang pilihan layanan.']);
        }
        $addons[] = $addon;
    }

    $totalPrice = (int) $service['price'];
    $duration = max(1, (int) $service['duration_minutes']);
    foreach ($addons as $addon) {
        $totalPrice += (int) $addon['price'];
        $duration += (int) $addon['duration_minutes'];
    }

    // The customer reviewed a price on screen; if the catalog changed since, make them review again.
    if ($expectedTotal !== null && $expectedTotal !== $totalPrice) {
        $pdo->rollBack();
        confirmationRespond(409, [
            'code' => 'PRICE_CHANGED',
            'error' => 'Harga layanan telah berubah. Silakan periksa kembali pilihan layananmu.',
            'current_total' => $totalPrice,
        ]);
    }

    $startAt = DateTimeImmutable::createFromFormat('!H:i', $time);
    $endTime = $startAt->modify('+' . $duration . ' minutes')->format('H:i:s');
    $reservationCode = sprintf('PB-%s-%s', str_replace('-', '', $date), strtoupper(bin2hex(random_bytes(4))));
    $addonNames = $addons ? implode(', ', array_column($addons, 'name')) : null;

    // 1. Reservation (price only; payment details live in `payments`).
    $insertReservation = $pdo->prepare(
        "INSERT INTO reservations
            (customer_id, reservation_code, request_token, status, service_name, hairstyle_name,
             additional_service, appointment_date, start_time, end_time, total_price)
         VALUES
            (:customer_id, :reservation_code, :request_token, 'pending_confirmation', :service_name, :hairstyle_name,
             :additional_service, :appointment_date, :start_time, :end_time, :total_price)"
    );
    $insertReservation->execute([
        'customer_id' => $customerId,
        'reservation_code' => $reservationCode,
        'request_token' => $token,
        'service_name' => $service['name'],
        'hairstyle_name' => $hairstyle['name'],
        'additional_service' => $addonNames,
        'appointment_date' => $date,
        'start_time' => $time . ':00',
        'end_time' => $endTime,
        'total_price' => $totalPrice,
    ]);
    $reservationId = (int) $pdo->lastInsertId();

    // 2. Selected add-ons.
    if ($addons) {
        $insertAddon = $pdo->prepare(
            'INSERT INTO reservation_additional_services (reservation_id, catalog_item_id, name, price)
             VALUES (:reservation_id, :catalog_item_id, :name, :price)'
        );
        foreach ($addons as $addon) {
            $insertAddon->execute([
                'reservation_id' => $reservationId,
                'catalog_item_id' => (int) $addon['id'],
                'name' => $addon['name'],
                'price' => (int) $addon['price'],
            ]);
        }
    }

    // 3. Payment record. Nothing is charged here, so it starts as pending with no payment_date.
    $transactionReference = 'PAY-' . $reservationCode;
    $insertPayment = $pdo->prepare(
        "INSERT INTO payments (reservation_id, payment_date, amount, payment_method, payment_status, transaction_reference)
         VALUES (:reservation_id, NULL, :amount, :payment_method, 'pending', :transaction_reference)"
    );
    $insertPayment->execute([
        'reservation_id' => $reservationId,
        'amount' => $totalPrice,
        'payment_method' => $paymentMethod,
        'transaction_reference' => $transactionReference,
    ]);
    $paymentId = (int) $pdo->lastInsertId();

    // 4. Mark the slot as taken.
    $pdo->prepare('UPDATE booking_time_slots SET reserved_count = reserved_count + 1 WHERE id = :id')
        ->execute(['id' => (int) $slot['id']]);

    $pdo->commit();

    confirmationRespond(201, ['reservation' => confirmationReservationPayload([
        'id' => $reservationId,
        'reservation_code' => $reservationCode,
        'status' => 'pending_confirmation',
        'appointment_date' => $date,
        'start_time' => $time . ':00',
        'total_price' => $totalPrice,
        'payment_id' => $paymentId,
        'payment_method' => $paymentMethod,
        'payment_status' => 'pending',
        'transaction_reference' => $transactionReference,
    ])]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Two identical requests raced past the token check: the unique index on
    // request_token rejected the second, so return the reservation the first saved.
    if ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062 && str_contains($error->getMessage(), 'request_token') && isset($pdo)) {
        try {
            $existing = reservationByRequestToken($pdo, $token);
            if ($existing && (int) $existing['customer_id'] === (int) $customerId) {
                confirmationRespond(200, ['reservation' => confirmationReservationPayload($existing), 'already_saved' => true]);
            }
        } catch (Throwable $lookupError) {
            error_log('Reservation duplicate lookup error: ' . $lookupError->getMessage());
        }
    }

    error_log('Reservation confirmation error: ' . $error->getMessage());
    confirmationRespond(500, ['error' => 'Reservasi belum dapat dikonfirmasi. Coba lagi sebentar.']);
}
