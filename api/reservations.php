<?php
declare(strict_types=1);

/*
 * Reservation history API (JSON).
 *
 * Returns the signed-in customer's reservations together with their payment
 * information, joining `reservations` and `payments`. A reservation can have
 * several payment rows (attempts, refunds); the most recent one is returned.
 */

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

function reservationsApiRespond(int $status, array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    reservationsApiRespond(405, ['error' => 'Metode tidak didukung']);
}

$customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$customerId) {
    reservationsApiRespond(401, ['error' => 'Silakan masuk untuk melihat riwayat reservasi']);
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT r.id, r.reservation_code, r.status, r.service_name, r.hairstyle_name, r.additional_service,
                r.barber_name, r.barber_level, r.appointment_date, r.start_time, r.end_time,
                r.total_price, r.booked_at,
                p.payment_id, p.payment_date, p.payment_method, p.payment_status, p.transaction_reference
         FROM reservations AS r
         LEFT JOIN payments AS p
                ON p.payment_id = (SELECT MAX(p2.payment_id) FROM payments AS p2 WHERE p2.reservation_id = r.id)
         WHERE r.customer_id = :customer_id
         ORDER BY r.appointment_date DESC, r.start_time DESC'
    );
    $statement->execute(['customer_id' => $customerId]);

    $paymentLabels = [
        'pending' => 'Menunggu pembayaran',
        'paid' => 'Lunas',
        'failed' => 'Gagal',
        'refunded' => 'Dikembalikan',
    ];

    $reservations = array_map(static function (array $row) use ($paymentLabels): array {
        $row['id'] = (int) $row['id'];
        $row['total_price'] = (int) $row['total_price'];
        $row['amount'] = $row['total_price']; // kept for the existing history script
        $row['payment_id'] = $row['payment_id'] !== null ? (int) $row['payment_id'] : null;
        $row['payment_status_label'] = $paymentLabels[$row['payment_status'] ?? 'pending'] ?? $paymentLabels['pending'];
        return $row;
    }, $statement->fetchAll(PDO::FETCH_ASSOC));

    reservationsApiRespond(200, ['reservations' => $reservations]);
} catch (Throwable $error) {
    error_log('Reservation history API error: ' . $error->getMessage());
    reservationsApiRespond(500, ['error' => 'Reservasi tidak dapat dimuat']);
}
