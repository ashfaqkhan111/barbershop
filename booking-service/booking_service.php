<?php
declare(strict_types=1);

// Sertakan konfigurasi database Anda
require_once __DIR__ . '/../config/database.php';

// Ambil data katalog dari database
$catalog_items = [];
try {
    $pdo = getDatabaseConnection();
    $statement = $pdo->query(
        "SELECT id, category, name, description, price, duration_minutes, is_default, is_active
         FROM booking_catalog_items
         WHERE is_active = 1
         ORDER BY FIELD(category, 'service', 'hairstyle', 'addon'), sort_order, id"
    );
    $catalog_items = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('Booking catalog error: ' . $error->getMessage());
}

// JSON view of the catalog for the page script (booking-service/app.js).
// Prices here are display estimates; booking-confirmation.php recalculates them on the server.
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $catalog = array_map(static fn(array $item): array => [
        'id' => (int) $item['id'],
        'category' => $item['category'],
        'name' => $item['name'],
        'description' => $item['description'],
        'price' => (int) $item['price'],
        'duration_minutes' => (int) $item['duration_minutes'],
        'is_default' => (int) $item['is_default'],
        'is_active' => (int) $item['is_active'],
    ], $catalog_items);
    echo json_encode(['catalog' => $catalog], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Pisahkan data berdasarkan kategori untuk mempermudah render
$services = array_filter($catalog_items, fn($item) => $item['category'] === 'service');
$hairstyles = array_filter($catalog_items, fn($item) => $item['category'] === 'hairstyle');
$addons = array_filter($catalog_items, fn($item) => $item['category'] === 'addon');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06483d">
  <meta name="description" content="Pilih layanan barbershop dan sesuaikan reservasi Anda.">
  <title>Pilih Layanan — Pemon Barbershop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,600;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script src="app.js" defer></script>
</head>
<body>
  <div class="booking-frame">
    <header class="booking-header">
      <a class="booking-brand" href="index.html" aria-label="Beranda Pemon Barbershop">
        <span class="booking-brand-script">pemon</span>
        <span class="booking-brand-subtitle">BARBERSHOP</span>
      </a>
      <span class="booking-header-note"><span></span>RESERVASI ONLINE</span>
    </header>

    <main class="booking-main">
      <div class="booking-heading">
        <p class="booking-eyebrow">PEMESANAN LAYANAN</p>
        <h1>1. Pilih Layanan</h1>
        <p>Pilih layanan dan gaya rambut yang kamu inginkan. Lengkapi dengan layanan tambahan jika diperlukan.</p>
      </div>

      <div class="booking-layout">
        <aside class="booking-steps" aria-label="Tahapan pemesanan">
          <ol>
            <li class="step-item is-current" aria-current="step">
              <span class="step-number">1</span>
              <div><strong>Pilih Layanan</strong><span>Pilih layanan yang kamu inginkan</span></div>
            </li>
            <li class="step-item">
              <span class="step-number">2</span>
              <div><strong>Pilih Tanggal &amp; Jam</strong><span>Pilih waktu reservasi</span></div>
            </li>
            <li class="step-item">
              <span class="step-number">3</span>
              <div><strong>Konfirmasi Pemesanan</strong><span>Pilih pembayaran</span></div>
            </li>
            <li class="step-item">
              <span class="step-number">4</span>
              <div><strong>Selesai</strong><span>Nota langsung tersedia</span></div>
            </li>
          </ol>
        </aside>

        <form class="service-picker" id="service-picker">
          <section class="choice-section" aria-labelledby="service-heading">
            <h2 id="service-heading">Pilih Layanan</h2>
            <div class="service-options" id="service-options">
              <?php foreach ($services as $service): ?>
                <label class="option-card">
                  <input type="radio" name="service_id" value="<?= htmlspecialchars((string)$service['id']) ?>" <?= $service['is_default'] ? 'checked' : '' ?>>
                  <div class="option-content">
                    <strong><?= htmlspecialchars($service['name']) ?></strong>
                    <span><?= htmlspecialchars($service['description'] ?? '') ?></span>
                    <span class="option-price">Rp <?= number_format((float)$service['price'], 0, ',', '.') ?></span>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="choice-section hairstyle-section" aria-labelledby="hairstyle-heading">
            <h2 id="hairstyle-heading">Pilih Gaya Rambut</h2>
            <p class="section-hint">Pilih gaya rambut yang sesuai dengan keinginanmu.</p>
            <div class="hairstyle-options" id="hairstyle-options">
              <?php foreach ($hairstyles as $hairstyle): ?>
                <label class="option-card">
                  <input type="radio" name="hairstyle_id" value="<?= htmlspecialchars((string)$hairstyle['id']) ?>" <?= $hairstyle['is_default'] ? 'checked' : '' ?>>
                  <div class="option-content">
                    <strong><?= htmlspecialchars($hairstyle['name']) ?></strong>
                    <span><?= htmlspecialchars($hairstyle['description'] ?? '') ?></span>
                    <?php if ($hairstyle['price'] > 0): ?>
                      <span class="option-price">Rp <?= number_format((float)$hairstyle['price'], 0, ',', '.') ?></span>
                    <?php endif; ?>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="choice-section addon-section" aria-labelledby="addon-heading">
            <h2 id="addon-heading">Pilih Layanan Tambahan <span>(Opsional)</span></h2>
            <p class="section-hint">Tambahkan layanan jika diperlukan.</p>
            <div class="addon-options" id="addon-options">
              <?php foreach ($addons as $addon): ?>
                <label class="option-card">
                  <input type="checkbox" name="addon_ids[]" value="<?= htmlspecialchars((string)$addon['id']) ?>">
                  <div class="option-content">
                    <strong><?= htmlspecialchars($addon['name']) ?></strong>
                    <span><?= htmlspecialchars($addon['description'] ?? '') ?></span>
                    <span class="option-price">Rp <?= number_format((float)$addon['price'], 0, ',', '.') ?></span>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </section>

          <div class="booking-form-actions">
            <a class="booking-back-button" href="../reservations-list/reservation_list.php"><span aria-hidden="true">←</span> Kembali</a>
            <button class="booking-next-button" type="submit">Lanjut ke Pilih Tanggal &amp; Jam <span aria-hidden="true">→</span></button>
          </div>
        </form>

        <aside class="booking-summary" aria-labelledby="summary-heading">
          <h2 id="summary-heading">Ringkasan Reservasi</h2>
          <div class="summary-primary">
            <div class="summary-icon"><svg aria-hidden="true"><use href="#booking-scissors"></use></svg></div>
            <div class="summary-text"><strong>Layanan</strong><span id="summary-service-name">Potong Rambut</span></div>
            <strong class="summary-value" id="summary-service-price">Rp 50.000</strong>
          </div>
          <div class="summary-primary summary-secondary">
            <div class="summary-icon"><svg aria-hidden="true"><use href="#booking-person"></use></svg></div>
            <div class="summary-text"><strong>Gaya Rambut</strong><span id="summary-hairstyle-name">Potongan Klasik</span></div>
            <span class="summary-value" id="summary-hairstyle-price">-</span>
          </div>
          <div class="summary-primary summary-secondary addon-summary">
            <div class="summary-icon"><svg aria-hidden="true"><use href="#booking-plus"></use></svg></div>
            <div class="summary-text"><strong>Layanan Tambahan</strong><span id="summary-addons-name">-</span></div>
            <span class="summary-value" id="summary-addons-price">-</span>
          </div>
          <div class="summary-divider"></div>
          <div class="summary-detail"><span class="summary-icon"><svg aria-hidden="true"><use href="#booking-calendar"></use></svg></span><strong>Tanggal</strong><span>-</span></div>
          <div class="summary-detail"><span class="summary-icon"><svg aria-hidden="true"><use href="#booking-clock"></use></svg></span><strong>Waktu</strong><span>-</span></div>
          <div class="summary-detail"><span class="summary-icon"><svg aria-hidden="true"><use href="#booking-duration"></use></svg></span><strong>Estimasi Durasi</strong><span id="summary-duration">±30 menit</span></div>
          <div class="summary-detail summary-total"><span class="summary-icon"><svg aria-hidden="true"><use href="#booking-price"></use></svg></span><strong>Estimasi Harga</strong><strong id="summary-total">Rp 50.000</strong></div>
          <p class="summary-note"><svg aria-hidden="true"><use href="#booking-info"></use></svg>Harga dapat berubah tergantung kondisi rambut, jenis perawatan, dan permintaan khusus.</p>
        </aside>
      </div>
    </main>
  </div>

  <div class="booking-toast" id="booking-toast" role="status" aria-live="polite"></div>

  <svg class="booking-icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="booking-scissors" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.2 8.2 12 12M8.2 15.8 20 4M13 12l-2.2 2.2"/></symbol>
    <symbol id="booking-person" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3"/><path d="M5 20c.5-4 2.8-6 7-6s6.5 2 7 6z"/></symbol>
    <symbol id="booking-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></symbol>
    <symbol id="booking-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></symbol>
    <symbol id="booking-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
    <symbol id="booking-duration" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
    <symbol id="booking-price" viewBox="0 0 24 24"><path d="M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1"/></symbol>
    <symbol id="booking-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></symbol>
  </svg>
</body>
</html>
