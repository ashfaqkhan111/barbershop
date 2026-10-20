const privateDashboard = document.querySelector("#private-dashboard");
const authPrompt = document.querySelector("#auth-prompt");
const loadMessage = document.querySelector("#load-message");
const welcomeTitle = document.querySelector("#welcome-title");
const welcomeDescription = document.querySelector("#welcome-description");
const heroStatus = document.querySelector("#hero-status");
const headerLogin = document.querySelector("#header-login");
const headerLogout = document.querySelector("#header-logout");
const toast = document.querySelector("#dashboard-toast");
let toastTimer;

function escapeHtml(value = "") {
  return String(value).replace(/[&<>"']/g, character => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
}

function money(value) {
  return `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
}

function dateLabel(value, options = { weekday: "long", day: "numeric", month: "long", year: "numeric" }) {
  if (!value) return "Tanggal belum ditentukan";
  const date = new Date(`${String(value).slice(0, 10)}T12:00:00+07:00`);
  return Number.isNaN(date.getTime()) ? escapeHtml(value) : new Intl.DateTimeFormat("id-ID", { ...options, timeZone: "Asia/Jakarta" }).format(date);
}

function initials(name) {
  return String(name || "").trim().split(/\s+/).slice(0, 2).map(part => part[0] || "").join("").toUpperCase();
}

function greeting() {
  const hour = Number(new Intl.DateTimeFormat("id-ID", { hour: "numeric", hour12: false, timeZone: "Asia/Jakarta" }).format(new Date()));
  if (hour < 11) return "Selamat pagi";
  if (hour < 15) return "Selamat siang";
  if (hour < 18) return "Selamat sore";
  return "Selamat malam";
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 3500);
}

function clearPrivateData() {
  document.querySelector("#profile-name").textContent = "";
  document.querySelector("#profile-contact").textContent = "";
  document.querySelector("#profile-avatar").textContent = "";
  document.querySelector("#profile-barber").textContent = "";
  document.querySelector("#profile-visits").textContent = "";
  document.querySelector("#appointment-status").textContent = "";
  document.querySelector("#appointment-content").replaceChildren();
}

function renderAppointment(appointment) {
  const status = document.querySelector("#appointment-status");
  const content = document.querySelector("#appointment-content");
  if (!appointment) {
    status.hidden = true;
    content.innerHTML = `<div class="empty-appointment"><h3>Belum ada reservasi mendatang</h3><p>Atur kunjungan berikutnya dan pilih waktu yang sesuai.</p><a href="../booking-service/booking_service.php">Pesan reservasi <span aria-hidden="true">→</span></a></div>`;
    return;
  }

  status.hidden = false;
  status.textContent = appointment.status_label || "Menunggu konfirmasi";
  const start = String(appointment.start_time || "").slice(0, 5);
  const end = String(appointment.end_time || "").slice(0, 5);
  content.innerHTML = `<div class="appointment-top"><div><span class="appointment-ref">${escapeHtml(appointment.reservation_code)}</span><h3>${escapeHtml(appointment.service_name || "Layanan Pemon")}</h3>${appointment.hairstyle_name ? `<span class="appointment-ref">Gaya: ${escapeHtml(appointment.hairstyle_name)}</span>` : ""}</div><div class="appointment-price"><span>Total biaya</span><strong>${money(appointment.total_price ?? appointment.amount)}</strong></div></div><div class="appointment-meta"><div class="meta-item"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg><div><strong>${escapeHtml(dateLabel(appointment.appointment_date))}</strong><span>Tanggal kunjungan</span></div></div><div class="meta-item"><svg aria-hidden="true"><use href="#icon-clock"></use></svg><div><strong>${escapeHtml(start)}${end ? `–${escapeHtml(end)}` : ""} WIB</strong><span>${escapeHtml(appointment.barber_name || "Barber akan ditentukan")}</span></div></div></div>`;
}

function renderDashboard(data) {
  const profile = data.profile;
  const firstName = String(profile.name || "").trim().split(/\s+/)[0] || "Pelanggan";
  welcomeTitle.textContent = `${greeting()}, ${firstName}.`;
  welcomeDescription.textContent = "Berikut ringkasan pengalamanmu di Pemon Barbershop.";
  heroStatus.textContent = "Akun pelanggan aktif";
  authPrompt.hidden = true;
  loadMessage.hidden = true;
  privateDashboard.hidden = false;
  headerLogin.hidden = true;
  headerLogout.hidden = false;

  document.querySelector("#profile-name").textContent = profile.name || "Pelanggan Pemon";
  document.querySelector("#profile-contact").textContent = profile.whatsapp || profile.email || "Kontak belum ditambahkan";
  document.querySelector("#profile-avatar").textContent = initials(profile.name);
  document.querySelector("#profile-barber").textContent = profile.favorite_barber || "Belum ada data";
  document.querySelector("#profile-visits").textContent = `${Number(data.stats.completed_visits || 0)} kunjungan`;
  renderAppointment(data.upcoming_reservation);
}

function renderGuest() {
  clearPrivateData();
  loadMessage.hidden = true;
  privateDashboard.hidden = true;
  authPrompt.hidden = false;
  headerLogin.hidden = false;
  headerLogout.hidden = true;
  welcomeTitle.textContent = "Selamat datang di Pemon.";
  welcomeDescription.textContent = "Inspirasi gaya dan layanan pangkas untuk kunjunganmu berikutnya.";
  heroStatus.textContent = "Informasi umum";
}

async function loadDashboard() {
  try {
    const response = await fetch("./dashboard.php", { headers: { Accept: "application/json" }, cache: "no-store" });
    if (response.status === 401) {
      renderGuest();
      return;
    }
    if (!response.ok) throw new Error("Dasbor tidak dapat dimuat");
    const payload = await response.json();
    if (!payload.authenticated || !payload.profile) {
      renderGuest();
      return;
    }
    renderDashboard(payload);
  } catch (error) {
    clearPrivateData();
    privateDashboard.hidden = true;
    authPrompt.hidden = true;
    loadMessage.hidden = false;
    loadMessage.textContent = "Dasbor belum dapat dimuat. Pastikan server PHP dan basis data sudah terhubung.";
    headerLogin.hidden = false;
    headerLogout.hidden = true;
  }
}

headerLogout.addEventListener("click", async () => {
  headerLogout.disabled = true;
  try {
    const response = await fetch("./logout.php", { method: "POST", headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error("Logout gagal");
    window.location.reload();
  } catch (error) {
    showToast("Belum berhasil keluar. Silakan coba lagi.");
  } finally {
    headerLogout.disabled = false;
  }
});

document.querySelector(".menu-toggle").addEventListener("click", event => {
  const button = event.currentTarget;
  const nav = document.querySelector(".main-nav");
  const open = button.getAttribute("aria-expanded") !== "true";
  button.setAttribute("aria-expanded", String(open));
  nav.classList.toggle("is-open", open);
});

window.addEventListener("pagehide", () => {
  renderGuest();
});
window.addEventListener("pageshow", event => {
  if (event.persisted) loadDashboard();
});

loadDashboard();
