const sampleListReservations = [
  {
    id: 101,
    reservation_code: "RB-20250917-0012",
    status: "pending_confirmation",
    customer_name: "Raffy Zaidan Ahmad",
    barber_name: "Raffy Zaidan Ahmad",
    service_name: "Potong Rambut (Gaya Klasik)",
    additional_service: "Cukur & Pijat Kepala",
    appointment_date: "2025-09-17",
    booked_at: "2025-09-17 08:12:00",
    start_time: "08:00",
    end_time: "09:00",
    duration_note: "(±1 jam 30 menit)",
    amount: 50000,
    payment_method: "Dompet Digital (DANA)"
  },
  {
    id: 102,
    reservation_code: "RB-20250910-0008",
    status: "confirmed",
    customer_name: "Raffy Zaidan Ahmad",
    barber_name: "Raffy Zaidan Ahmad",
    service_name: "Potong Rambut (Gaya Klasik)",
    additional_service: "-",
    appointment_date: "2025-09-10",
    booked_at: "2025-09-10 14:30:00",
    start_time: "14:30",
    end_time: "15:30",
    duration_note: "(±1 jam)",
    amount: 50000,
    payment_method: "Dompet Digital (DANA)"
  },
  {
    id: 103,
    reservation_code: "RB-20250903-0005",
    status: "completed",
    customer_name: "Raffy Zaidan Ahmad",
    barber_name: "Raffy Zaidan Ahmad",
    service_name: "Potong Rambut (Gaya Klasik)",
    additional_service: "-",
    appointment_date: "2025-09-03",
    booked_at: "2025-09-03 10:15:00",
    start_time: "10:00",
    end_time: "11:00",
    duration_note: "(±1 jam)",
    amount: 50000,
    payment_method: "Dompet Digital (DANA)"
  }
];

let listReservations = [...sampleListReservations];
let selectedListFilter = "all";
let listToastTimer;

const listIcon = name => `<svg aria-hidden="true"><use href="#list-${name}"></use></svg>`;
const escapeListHtml = value => String(value ?? "").replace(/[&<>"']/g, character => ({
  "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
}[character]));

function listStatus(reservation) {
  const value = String(reservation.status || "pending_confirmation").toLowerCase();
  if (["completed", "complete", "done", "finished", "selesai"].includes(value)) return "completed";
  if (["confirmed", "confirm", "approved", "dikonfirmasi"].includes(value)) return "confirmed";
  if (["cancelled", "canceled", "cancel", "rejected", "dibatalkan"].includes(value)) return "cancelled";
  return "pending_confirmation";
}

function listStatusLabel(status) {
  return {
    pending_confirmation: "Menunggu Konfirmasi",
    confirmed: "Dikonfirmasi",
    completed: "Selesai",
    cancelled: "Dibatalkan"
  }[status] || "Menunggu Konfirmasi";
}

function listStatusPill(status) {
  const icon = status === "completed" ? "check" : status === "confirmed" ? "check" : "wait";
  const style = status === "pending_confirmation" ? "pending" : status;
  return `<span class="list-status ${style}">${listIcon(icon)}${listStatusLabel(status)}</span>`;
}

function listFormatDate(value, options = { day: "2-digit", month: "short", year: "numeric" }) {
  if (!value) return "Tanggal belum ditentukan";
  const date = new Date(`${String(value).slice(0, 10)}T12:00:00`);
  if (Number.isNaN(date.getTime())) return escapeListHtml(value);
  return new Intl.DateTimeFormat("id-ID", options).format(date);
}

function listBookedAt(value) {
  if (!value) return "";
  const [date, time = ""] = String(value).replace("T", " ").split(" ");
  return `${listFormatDate(date)}, ${time.slice(0, 5)}`.trim();
}

function listTimeRange(reservation) {
  const start = String(reservation.start_time || "").slice(0, 5);
  const end = String(reservation.end_time || "").slice(0, 5);
  return `${start || "Waktu belum ditentukan"}${end ? ` - ${end}` : ""}`;
}

function listPrice(value) {
  return `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
}

function listCard(reservation) {
  const status = listStatus(reservation);
  const additional = reservation.additional_service && reservation.additional_service !== "-"
    ? reservation.additional_service : "-";
  const isPast = status === "completed" || status === "cancelled";
  const secondaryAction = isPast
    ? `<button class="list-button list-button-outline" type="button" data-list-action="rebook" data-id="${escapeListHtml(reservation.id)}">${listIcon("repeat")} Pesan Lagi</button>`
    : `<button class="list-button list-button-outline" type="button" data-list-action="cancel" data-id="${escapeListHtml(reservation.id)}">${listIcon("cross")} Batalkan</button>`;
  return `<article class="list-card" data-reservation-id="${escapeListHtml(reservation.id)}">
    <div class="list-card-icon">${listIcon("scissors")}</div>
    <div class="list-card-main">
      <p class="list-code">${escapeListHtml(reservation.reservation_code)}</p>
      <p class="list-booked-at">${escapeListHtml(listBookedAt(reservation.booked_at))}</p>
      ${listStatusPill(status)}
    </div>
    <div class="list-card-facts">
      <div class="list-fact">${listIcon("person")}<span>${escapeListHtml(reservation.customer_name || reservation.barber_name || "Pelanggan")}</span></div>
      <div class="list-fact">${listIcon("scissors")}<span>${escapeListHtml(reservation.service_name || "Layanan belum ditentukan")}</span></div>
      <div class="list-fact">${listIcon("sparkle")}<span>${escapeListHtml(additional)}</span></div>
      <div class="list-fact">${listIcon("clock")}<span>${escapeListHtml(listTimeRange(reservation))} <span class="list-duration">${escapeListHtml(reservation.duration_note || "(±1 jam)")}</span></span></div>
    </div>
    <div class="list-card-price"><strong>${escapeListHtml(listPrice(reservation.amount))}</strong><span>${escapeListHtml(reservation.payment_method || "Menunggu pembayaran")}</span></div>
    <div class="list-card-actions">
      <button class="list-button list-button-primary" type="button" data-list-action="details" data-id="${escapeListHtml(reservation.id)}">Lihat Detail ${listIcon("arrow")}</button>
      ${secondaryAction}
    </div>
  </article>`;
}

function renderListReservations() {
  const visible = listReservations
    .filter(item => selectedListFilter === "all" || listStatus(item) === selectedListFilter)
    .sort((a, b) => `${b.appointment_date} ${b.start_time}`.localeCompare(`${a.appointment_date} ${a.start_time}`));
  document.querySelector("#list-reservations").innerHTML = visible.length
    ? visible.map(listCard).join("")
    : '<div class="list-empty">Tidak ada reservasi untuk status ini.</div>';

  document.querySelector('[data-count="all"]').textContent = listReservations.length;
  for (const status of ["pending_confirmation", "confirmed", "completed"]) {
    const count = listReservations.filter(item => listStatus(item) === status).length;
    document.querySelector(`[data-count="${status}"]`).textContent = count;
  }
  document.querySelector("#list-showing").textContent = `Menampilkan ${visible.length} dari ${listReservations.length} reservasi`;
}

function showListToast(message) {
  const toast = document.querySelector("#list-toast");
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(listToastTimer);
  listToastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 3200);
}

function showListDetails(reservation) {
  const status = listStatus(reservation);
  const details = [
    ["Kode reservasi", reservation.reservation_code],
    ["Status", listStatusLabel(status)],
    ["Pelanggan", reservation.customer_name || reservation.barber_name || "Pelanggan"],
    ["Tukang cukur", reservation.barber_name || "Belum ditentukan"],
    ["Layanan", reservation.service_name || "Belum ditentukan"],
    ["Layanan tambahan", reservation.additional_service || "Tidak ada"],
    ["Tanggal", listFormatDate(reservation.appointment_date, { weekday: "long", day: "numeric", month: "long", year: "numeric" })],
    ["Waktu", listTimeRange(reservation)],
    ["Total biaya", listPrice(reservation.amount)],
    ["Pembayaran", reservation.payment_method || "Menunggu pembayaran"]
  ];
  document.querySelector("#list-modal-content").innerHTML = `<dl class="list-modal-details">${details.map(([label, value]) => `<div><dt>${escapeListHtml(label)}</dt><dd>${escapeListHtml(value)}</dd></div>`).join("")}</dl>`;
  document.querySelector("#list-modal-backdrop").hidden = false;
  document.querySelector(".list-modal-close").focus();
}

function closeListDetails() {
  document.querySelector("#list-modal-backdrop").hidden = true;
}

async function loadListReservations() {
  try {
    const response = await fetch("./reservation-list.php", { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error(`Endpoint reservasi memberikan respons ${response.status}`);
    const payload = await response.json();
    const rows = Array.isArray(payload) ? payload : payload.reservations;
    if (Array.isArray(rows)) listReservations = rows.map(row => ({ ...row, amount: row.total_price ?? row.amount }));
  } catch (error) {
    // Gunakan data contoh jika PHP atau basis data belum tersedia.
    listReservations = [...sampleListReservations];
  }
  renderListReservations();
}

document.querySelectorAll(".list-filter").forEach(tab => {
  tab.addEventListener("click", () => {
    selectedListFilter = tab.dataset.filter;
    document.querySelectorAll(".list-filter").forEach(item => {
      const active = item === tab;
      item.classList.toggle("is-active", active);
      item.setAttribute("aria-selected", String(active));
    });
    renderListReservations();
  });
});

document.addEventListener("click", event => {
  const button = event.target.closest("[data-list-action]");
  if (!button) return;
  const reservation = listReservations.find(item => String(item.id) === button.dataset.id);
  if (!reservation) return;
  switch (button.dataset.listAction) {
    case "details": showListDetails(reservation); break;
    case "cancel": showListToast(`Permintaan pembatalan ${reservation.reservation_code} dapat diajukan melalui Layanan Pelanggan.`); break;
    case "rebook": showListToast("Fitur pemesanan ulang akan tersedia di sini."); break;
  }
});

document.querySelector(".list-modal-close").addEventListener("click", closeListDetails);
document.querySelector("#list-modal-backdrop").addEventListener("click", event => {
  if (event.target.id === "list-modal-backdrop") closeListDetails();
});
document.addEventListener("keydown", event => {
  if (event.key === "Escape") closeListDetails();
});
document.querySelector(".list-menu-toggle").addEventListener("click", event => {
  const button = event.currentTarget;
  const nav = document.querySelector(".list-nav");
  const expanded = button.getAttribute("aria-expanded") === "true";
  button.setAttribute("aria-expanded", String(!expanded));
  button.setAttribute("aria-label", expanded ? "Buka menu navigasi" : "Tutup menu navigasi");
  nav.classList.toggle("is-open", !expanded);
});

renderListReservations();
loadListReservations();
