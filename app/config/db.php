<?php
// Konfigurasi koneksi PostgreSQL (PDO) + helper umum. Tanpa framework.
session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('pgsql:host=localhost;port=5432;dbname=pemon_barbershop', 'postgres', 'GANTI_PASSWORD', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { return $_SESSION['t'] ??= bin2hex(random_bytes(16)); }
function csrf_ok(): bool { return hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? ''); }
function rupiah(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
function normalisasi_wa(string $s): string { return preg_replace('/[^0-9+]/', '', $s); }
