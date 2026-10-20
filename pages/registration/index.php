<?php
require 'config/db.php';
try { $layanan = db()->query('SELECT * FROM services ORDER BY id')->fetchAll(); }
catch (PDOException $ex) { $layanan = []; }
$judul = 'Beranda';
include 'includes/head.php';
?>
<body>
<nav class="nav"><div class="container">
  <a class="brand" href="index.php">pemon<small>BARBERSHOP</small></a>
  <button class="burger" aria-label="Buka menu" aria-expanded="false">☰</button>
  <div class="menu">
    <a class="on" href="index.php">Home</a><a href="#services">Layanan</a><a href="#cara">Cara Kerja</a><a href="#kontak">Kontak</a>
    <?php if (isset($_SESSION['uid'])): ?>
      <span class="user">👤 <?= e($_SESSION['nama']) ?></span><a href="logout.php">Keluar</a>
    <?php else: ?>
      <a href="login.php">Masuk</a><a class="btn mint" href="register.php" style="padding:.5rem 1.2rem;color:var(--g9)">Daftar</a>
    <?php endif; ?>
  </div>
</div></nav>

<header class="hero"><div class="container">
  <span class="pill">Premium Grooming Experience</span>
  <h1 class="serif"><em>Your Style,</em> Our Craft.</h1>
  <p>Layanan grooming premium dari barber profesional, disesuaikan dengan kepribadian dan gaya hidup modern Anda.</p>
  <div class="actions"><a href="#services" class="btn">Book an Appointment →</a><a href="#services" class="btn light">Explore Services</a></div>
  <div class="stats">
    <div><strong>✂ 3 Barber Masters</strong><small>Certified Artisans</small></div>
    <div><strong><span style="color:#d9731a">★</span> 4.9 / 5.0</strong><small>1.200+ ulasan pelanggan</small></div>
    <div><strong>🕘 09:00 – 21:00</strong><small>Buka setiap hari</small></div>
  </div>
</div></header>

<section class="section" id="services"><div class="container">
  <div class="head"><div class="eyebrow">Our Services</div><h2 class="serif"><em>Tailored Grooming</em> for Modern Gentlemen</h2></div>
  <div class="grid">
    <?php foreach ($layanan as $l): ?>
    <article class="card" style="margin-top:12px">
      <?php if ($l['populer']): ?><span class="tag">Most Popular</span><?php endif; ?>
      <div class="tile <?= $l['populer'] ? 'dark' : '' ?>"><?= e($l['ikon']) ?></div>
      <h3><?= e($l['nama']) ?></h3><p><?= e($l['deskripsi']) ?></p>
      <div class="meta">⏱ <?= (int)$l['durasi_menit'] ?> menit · <b><?= rupiah((int)$l['harga']) ?></b></div>
      <a class="book" href="<?= isset($_SESSION['uid']) ? '#' : 'login.php' ?>">Book This Service →</a>
    </article>
    <?php endforeach; ?>
    <?php if (!$layanan): ?><p class="muted">Daftar layanan belum tersedia.</p><?php endif; ?>
  </div>
</div></section>

<section class="section"><div class="container">
  <div class="head center"><div class="eyebrow">Why Pemon</div><h2 class="serif"><em>A Thoughtful Approach</em> to Grooming</h2></div>
  <div class="grid">
    <div class="card soft"><div class="tile">🛠</div><h3>Professional Barbers</h3><p>Ditangani barber berpengalaman yang fokus pada potongan presisi dan konsultasi.</p></div>
    <div class="card soft"><div class="tile">📅</div><h3>Flexible Scheduling</h3><p>Pilih tanggal dan slot 30 menit dalam hitungan detik, langsung duduk tanpa menunggu.</p></div>
    <div class="card soft"><div class="tile">☕</div><h3>Comfortable Experience</h3><p>Nikmati suasana tenang dengan kopi dan musik santai selama sesi grooming.</p></div>
  </div>
</div></section>

<section class="section" id="cara"><div class="container">
  <div class="head center"><div class="eyebrow">Easy Booking</div><h2 class="serif"><em>How It Works</em></h2></div>
  <div class="grid center">
    <div><div class="step-num">1</div><h3>Choose Your Service</h3><p class="muted">Pilih layanan, gaya rambut, dan tambahan perawatan.</p></div>
    <div><div class="step-num">2</div><h3>Pick Date &amp; Time</h3><p class="muted">Pilih tanggal dan jam yang sesuai jadwal Anda.</p></div>
    <div><div class="step-num">3</div><h3>Enjoy Your Appointment</h3><p class="muted">Datang tepat waktu dan biarkan barber kami bekerja.</p></div>
  </div>
</div></section>

<section><div class="container"><div class="cta">
  <div><div class="eyebrow">Private Chair Reservation</div><h2 class="serif"><em>Ready for your</em> next look?</h2><p>Reservasi kursi Anda dalam hitungan detik.</p></div>
  <div class="end"><a href="register.php" class="btn mint">Book an Appointment →</a></div>
</div></div></section>

<footer id="kontak"><div class="container">
  <div class="foot">
    <div><a class="brand" href="index.php">pemon<small>BARBERSHOP</small></a><p>Ritual grooming modern untuk pria urban, dengan keahlian dan keramahan.</p></div>
    <div><h4>Navigasi</h4><ul><li><a href="index.php">Home</a></li><li><a href="#services">Layanan</a></li><li><a href="#cara">Cara Kerja</a></li></ul></div>
    <div><h4>Hubungi Kami</h4><ul><li>💬 WhatsApp: +62 812-3456-7890</li><li>📞 (021) 789-2041</li><li>🕘 Setiap hari 09:00 – 21:00 WIB</li><li>📍 Senopati Suites No. 14, Jakarta Selatan</li></ul></div>
  </div>
  <div class="legal"><span>© <?= date('Y') ?> Pemon Barbershop.</span><span>Privacy Policy · Terms of Service</span></div>
</div></footer>
<script src="assets/js/app.js"></script>
</body>
</html>
