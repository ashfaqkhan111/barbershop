const demoReservations = [
  {
    id: 1,
    reservation_code: "PB-20260909-0042",
    status: "upcoming",
    service_name: "Potong Rambut Klasik",
    additional_service: "",
    barber_name: "Chandra Cak",
    barber_level: "Ahli Cukur",
    appointment_date: "2026-09-09",
    start_time: "10:00",
    end_time: "10:45",
    amount: 50000,
    payment_method: "Dompet Digital (DANA)",
    payment_status: "",
    booked_at: "2026-09-05 14:15:00"
  },
  {
    id: 2,
    reservation_code: "PB-20260917-0012",
    status: "completed",
    service_name: "Potong Rambut Klasik",
    additional_service: "Cukur & Pijat",
    barber_name: "Chandra Cak",
    barber_level: "",
    appointment_date: "2026-09-17",
    start_time: "08:00",
    end_time: "09:00",
    amount: 100000,
    payment_method: "QRIS BCA",
    payment_status: "Lunas",
    booked_at: "2026-09-17 08:12:00"
  },
  {
    id: 3,
    reservation_code: "PB-20260805-0008",
    status: "completed",
    service_name: "Potong Rambut + Jenggot",
    additional_service: "",
    barber_name: "Petrus",
    barber_level: "",
    appointment_date: "2026-08-05",
    start_time: "13:00",
    end_time: "13:45",
    amount: 70000,
    payment_method: "Dompet Digital (GoPay)",
    payment_status: "",
    booked_at: "2026-08-05 11:30:00"
  },
  {
    id: 4,
    reservation_code: "PB-20260720-0003",
    status: "cancelled",
    service_name: "Potong Rambut",
    additional_service: "",
    barber_name: "Dimas",
    barber_level: "",
    appointment_date: "2026-07-20",
    start_time: "15:00",
    end_time: "15:30",
    amount: 50000,
    payment_method: "Dikembalikan / Belum Dibayar",
    payment_status: "Dibatalkan oleh Pengguna",
    booked_at: "2026-07-20 12:04:00"
  }
];

let reservations = [...demoReservations];
let activeFilter = "all";
let toastTimer;

const icon = (name, className = "") => `<svg class="${className}" aria-hidden="true"><use href="#icon-${name}"></use></svg>`;
const escapeHtml = (value = "") => String(value).replace(/[&<>"']/g, character => ({
  "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
}[character]));

function normalizedStatus(reservation) {
  const status = String(reservation.status || "upcoming").toLowerCase();
  if (["completed", "complete", "done", "finished"].includes(status)) return "completed";
  if (["cancelled", "canceled", "cancel", "rejected"].includes(status)) return "cancelled";
  return "upcoming";
}

function formatDate(value, options = { day: "numeric", month: "long", year: "numeric" }) {
  if (!value) return "Tanggal belum ditentukan";
  const date = new Date(`${String(value).slice(0, 10)}T12:00:00`);
  if (Number.isNaN(date.getTime())) return escapeHtml(value);
  return new Intl.DateTimeFormat("id-ID", options).format(date);
}

function formatDateTime(value) {
  if (!value) return "";
  const [datePart, timePart = ""] = String(value).replace("T", " ").split(" ");
  if (!datePart) return "";
  const time = timePart.slice(0, 5);
  return `${formatDate(datePart, { day: "2-digit", month: "short", year: "numeric" })}, ${time} WIB`.trim();
}

function formatMoney(value) {
  const numeric = Number(value || 0);
  return `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(numeric)}`;
}

function timeRange(reservation) {
  const start = String(reservation.start_time || "").slice(0, 5);
  const end = String(reservation.end_time || "").slice(0, 5);
  if (!start) return "Waktu belum ditentukan";
  return `${start}${end ? ` - ${end}` : ""} WIB`;
}

function statusPill(status) {
  const settings = {
    upcoming: { label: "Mendatang", icon: "" },
    completed: { label: "Selesai", icon: "check" },
    cancelled: { label: "Dibatalkan", icon: "cross" }
  }[status];
  return `<span class="status-pill ${status}">${settings.icon ? icon(settings.icon) : '<span class="status-dot"></span>'}${settings.label}</span>`;
}

function serviceText(reservation) {
  return [reservation.service_name, reservation.additional_service].filter(Boolean).join(" · ");
}

function upcomingCard(reservation) {
  const barber = escapeHtml(reservation.barber_name || "Tukang cukur belum ditentukan");
  const level = reservation.barber_level ? `<span class="fact-label">(${escapeHtml(reservation.barber_level)})</span>` : "";
  return `<article class="reservation-card upcoming-card" data-id="${escapeHtml(reservation.id)}">
    <div class="reservation-icon">${icon("scissors")}</div>
    <div class="booking-main">
      <div class="booking-code-row"><span class="booking-code">${escapeHtml(reservation.reservation_code)}</span></div>
      ${statusPill("upcoming")}
      <p class="booking-note">Dipesan pada ${escapeHtml(formatDateTime(reservation.booked_at))}</p>
    </div>
    <div class="reservation-facts">
      <div class="fact">${icon("person")}<span>${barber}${level}</span></div>
      <div class="fact">${icon("calendar")}<span>${escapeHtml(formatDate(reservation.appointment_date, { weekday: "long", day: "numeric", month: "long", year: "numeric" }))}</span></div>
    </div>
    <div class="reservation-facts">
      <div class="fact">${icon("scissors")}<span>${escapeHtml(reservation.service_name || "Layanan belum ditentukan")}</span></div>
      <div class="fact fact-emphasis">${icon("clock")}<span>${escapeHtml(timeRange(reservation))}</span></div>
    </div>
    <div class="reservation-price"><strong>${escapeHtml(formatMoney(reservation.amount))}</strong><span>${escapeHtml(reservation.payment_method || "Menunggu pembayaran")}</span></div>
    <div class="card-actions">
      <button class="button button-dark" type="button" data-action="details" data-id="${escapeHtml(reservation.id)}">Lihat Detail <span class="button-icon" aria-hidden="true">→</span></button>
      <button class="button button-light" type="button" data-action="reschedule" data-id="${escapeHtml(reservation.id)}">Jadwalkan Ulang</button>
      <button class="cancel-action" type="button" aria-label="Ajukan pembatalan ${escapeHtml(reservation.reservation_code)}" data-action="cancel" data-id="${escapeHtml(reservation.id)}">${icon("cross")}</button>
    </div>
  </article>`;
}

function pastCard(reservation) {
  const status = normalizedStatus(reservation);
  const payment = status === "cancelled"
    ? (reservation.payment_method || reservation.payment_status || "Informasi pembayaran tidak tersedia")
    : ([reservation.payment_method, reservation.payment_status]
      .filter(Boolean)
      .join(reservation.payment_method && reservation.payment_status ? " • " : "") || "Informasi pembayaran tidak tersedia");
  const secondaryService = reservation.additional_service
    ? `<div class="fact">${icon("shave")}<span>${escapeHtml(reservation.additional_service)}</span></div>` : "";
  const actions = status === "cancelled"
    ? `<button class="button button-light" type="button" data-action="details" data-id="${escapeHtml(reservation.id)}">Lihat Detail</button><button class="button button-light" type="button" data-action="rebook" data-id="${escapeHtml(reservation.id)}">${icon("repeat")} Pesan Lagi</button>`
    : `<button class="button button-light" type="button" data-action="details" data-id="${escapeHtml(reservation.id)}">Lihat Detail</button><button class="button button-dark" type="button" data-action="rebook" data-id="${escapeHtml(reservation.id)}">${icon("repeat")} Pesan Lagi</button>`;
  return `<article class="reservation-card past-card ${status === "cancelled" ? "cancelled-card" : ""}" data-id="${escapeHtml(reservation.id)}">
    <div class="reservation-icon">${icon("scissors")}</div>
    <div class="booking-main">
      <div class="booking-code-row"><span class="booking-code">${escapeHtml(reservation.reservation_code)}</span>${statusPill(status)}</div>
      <p class="reservation-date">${escapeHtml(formatDateTime(reservation.booked_at))}${status === "cancelled" && reservation.payment_status ? ` (${escapeHtml(reservation.payment_status)})` : ""}</p>
    </div>
    <div class="reservation-facts">
      <div class="fact">${icon("person")}<span>${escapeHtml(reservation.barber_name || "Tukang cukur")}</span></div>
      <div class="fact">${icon("scissors")}<span>${escapeHtml(reservation.service_name || "Layanan")}</span></div>
    </div>
    <div class="reservation-facts">
      <div class="fact">${icon("clock")}<span>${escapeHtml(timeRange(reservation))}</span></div>
      <div class="fact">${icon("calendar")}<span>${escapeHtml(formatDate(reservation.appointment_date))}</span></div>
      ${secondaryService}
    </div>
    <div class="reservation-price"><strong>${escapeHtml(formatMoney(reservation.amount))}</strong><span>${escapeHtml(payment)}</span></div>
    <div class="card-actions">${actions}</div>
  </article>`;
}

function sortUpcoming(items) {
  return [...items].sort((a, b) => `${a.appointment_date} ${a.start_time}`.localeCompare(`${b.appointment_date} ${b.start_time}`));
}

function sortPast(items) {
  return [...items].sort((a, b) => `${b.appointment_date} ${b.start_time}`.localeCompare(`${a.appointment_date} ${a.start_time}`));
}

function render() {
  const all = reservations;
  const upcoming = sortUpcoming(all.filter(item => normalizedStatus(item) === "upcoming"));
  const past = sortPast(all.filter(item => normalizedStatus(item) !== "upcoming"));
  const visibleUpcoming = activeFilter === "all" || activeFilter === "upcoming"
    ? upcoming.filter(item => activeFilter === "all" || normalizedStatus(item) === activeFilter) : [];
  const visiblePast = activeFilter === "all" ? past : past.filter(item => normalizedStatus(item) === activeFilter);

  document.querySelector("#upcoming-list").innerHTML = visibleUpcoming.length
    ? visibleUpcoming.map(upcomingCard).join("")
    : '<div class="empty-state">Belum ada reservasi mendatang.</div>';
  document.querySelector("#past-list").innerHTML = visiblePast.length
    ? visiblePast.map(pastCard).join("")
    : '<div class="empty-state">Belum ada riwayat reservasi.</div>';

  const upcomingSection = document.querySelector(".upcoming-section");
  const pastSection = document.querySelector(".past-section");
  upcomingSection.hidden = !(activeFilter === "all" || activeFilter === "upcoming");
  pastSection.hidden = activeFilter === "upcoming";
  const scheduled = upcoming.length;
  document.querySelector("#scheduled-count").textContent = `${scheduled} JANJI TEMU TERJADWAL`;
  document.querySelector('[data-count="all"]').textContent = all.length;
  for (const status of ["upcoming", "completed", "cancelled"]) {
    document.querySelector(`[data-count="${status}"]`).textContent = all.filter(item => normalizedStatus(item) === status).length;
  }
  const shown = activeFilter === "all" ? all.length : all.filter(item => normalizedStatus(item) === activeFilter).length;
  document.querySelector("#showing-count").textContent = `Menampilkan ${shown} dari ${all.length} reservasi`;
}

function showToast(message) {
  const toast = document.querySelector("#toast");
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 3200);
}

function showDetails(reservation) {
  const details = [
    ["Kode reservasi", reservation.reservation_code],
    ["Status", { upcoming: "Mendatang", completed: "Selesai", cancelled: "Dibatalkan" }[normalizedStatus(reservation)]],
    ["Layanan", serviceText(reservation) || "—"],
    ["Tukang cukur", reservation.barber_name || "Belum ditentukan"],
    ["Tanggal", formatDate(reservation.appointment_date, { weekday: "long", day: "numeric", month: "long", year: "numeric" })],
    ["Waktu", timeRange(reservation)],
    ["Total biaya", formatMoney(reservation.amount)],
    ["Pembayaran", reservation.payment_method || "Menunggu pembayaran"]
  ];
  document.querySelector("#modal-content").innerHTML = `<dl class="modal-details">${details.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join("")}</dl>`;
  document.querySelector("#modal-backdrop").hidden = false;
  document.querySelector(".modal-close").focus();
}

function closeDetails() {
  document.querySelector("#modal-backdrop").hidden = true;
}

async function loadReservations() {
  try {
    const response = await fetch("./api/reservations.php", { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error(`Reservation endpoint returned ${response.status}`);
    const payload = await response.json();
    const rows = Array.isArray(payload) ? payload : payload.reservations;
    if (Array.isArray(rows)) reservations = rows;
  } catch (error) {
    // The local preview uses the sample reservations when PHP or a database is not configured.
    reservations = [...demoReservations];
  }
  render();
}

document.querySelectorAll(".filter-tab").forEach(tab => {
  tab.addEventListener("click", () => {
    activeFilter = tab.dataset.filter;
    document.querySelectorAll(".filter-tab").forEach(item => {
      const selected = item === tab;
      item.classList.toggle("is-selected", selected);
      item.setAttribute("aria-selected", String(selected));
    });
    render();
  });
});

document.addEventListener("click", event => {
  const actionButton = event.target.closest("[data-action]");
  if (!actionButton) return;
  const reservation = reservations.find(item => String(item.id) === actionButton.dataset.id);
  if (!reservation) return;
  switch (actionButton.dataset.action) {
    case "details": showDetails(reservation); break;
    case "reschedule": showToast(`Untuk menjadwalkan ulang ${reservation.reservation_code}, silakan hubungi Layanan Pelanggan.`); break;
    case "cancel": showToast(`Silakan hubungi Layanan Pelanggan untuk mengajukan pembatalan ${reservation.reservation_code}.`); break;
    case "rebook": showToast("Fitur pemesanan akan tersedia di sini."); break;
  }
});

document.querySelector(".button-book-new").addEventListener("click", () => showToast("Fitur pemesanan akan tersedia di sini."));
document.querySelector(".modal-close").addEventListener("click", closeDetails);
document.querySelector("#modal-backdrop").addEventListener("click", event => {
  if (event.target.id === "modal-backdrop") closeDetails();
});
document.addEventListener("keydown", event => {
  if (event.key === "Escape") closeDetails();
});
document.querySelector(".menu-toggle").addEventListener("click", event => {
  const button = event.currentTarget;
  const nav = document.querySelector(".main-nav");
  const expanded = button.getAttribute("aria-expanded") === "true";
  button.setAttribute("aria-expanded", String(!expanded));
  button.setAttribute("aria-label", expanded ? "Buka menu navigasi" : "Tutup menu navigasi");
  nav.classList.toggle("is-open", !expanded);
});

render();
loadReservations();
