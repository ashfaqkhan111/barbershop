const grid = document.querySelector("#calendar-grid");
const monthLabel = document.querySelector("#month-label");
const bookingList = document.querySelector("#personal-bookings");
const loginPrompt = document.querySelector("#personal-login");
const loadingMessage = document.querySelector("#personal-loading");
const bookingCount = document.querySelector("#booking-count");
const weekDays = ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"];
const fullMonths = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
let activeMonth = jakartaMonth(new Date());
let today = jakartaDate(new Date());
let selectedDate = today;
let reservations = [];
let sessionChecked = false;
let authenticated = false;
let requestNumber = 0;

function jakartaParts(date) {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta", year: "numeric", month: "2-digit", day: "2-digit"
  }).formatToParts(date);
  return Object.fromEntries(parts.filter(part => part.type !== "literal").map(part => [part.type, part.value]));
}

function jakartaDate(date) {
  const parts = jakartaParts(date);
  return `${parts.year}-${parts.month}-${parts.day}`;
}

function jakartaMonth(date) {
  return jakartaDate(date).slice(0, 7);
}

function escapeHtml(value = "") {
  return String(value).replace(/[&<>"']/g, character => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
}

function formatDate(dateValue, options = { day: "numeric", month: "short" }) {
  const date = new Date(`${String(dateValue).slice(0, 10)}T12:00:00+07:00`);
  if (Number.isNaN(date.getTime())) return escapeHtml(dateValue);
  return new Intl.DateTimeFormat("id-ID", { ...options, timeZone: "Asia/Jakarta" }).format(date);
}

function monthParts() {
  const [year, month] = activeMonth.split("-").map(Number);
  return { year, month, index: month - 1 };
}

function monthTitle() {
  const { year, index } = monthParts();
  return `${fullMonths[index]} ${year}`;
}

function reservationsForDate(date) {
  return reservations.filter(reservation => String(reservation.appointment_date).slice(0, 10) === date);
}

function renderCalendar() {
  const { year, month, index } = monthParts();
  const firstWeekday = new Date(Date.UTC(year, index, 1)).getUTCDay();
  const dayCount = new Date(Date.UTC(year, month, 0)).getUTCDate();
  const leadingCells = (firstWeekday + 6) % 7;
  const totalCells = Math.ceil((leadingCells + dayCount) / 7) * 7;
  monthLabel.textContent = monthTitle();

  let markup = weekDays.map(day => `<div class="weekday" aria-hidden="true">${day}</div>`).join("");
  for (let cell = 0; cell < totalCells; cell += 1) {
    const dayNumber = cell - leadingCells + 1;
    if (dayNumber < 1 || dayNumber > dayCount) {
      markup += '<div class="calendar-empty" aria-hidden="true"></div>';
      continue;
    }
    const date = `${activeMonth}-${String(dayNumber).padStart(2, "0")}`;
    const bookings = reservationsForDate(date);
    const classes = ["calendar-day"];
    if (date === today) classes.push("is-today");
    if (date === selectedDate) classes.push("is-selected");
    if (bookings.length) classes.push("has-appointment");
    const aria = `${dayNumber} ${monthTitle()}, jam operasional 09.00 sampai 21.00 WIB${bookings.length ? `, ${bookings.length} reservasi pada akunmu` : ""}`;
    markup += `<button class="${classes.join(" ")}" type="button" data-date="${date}" aria-label="${escapeHtml(aria)}" aria-pressed="${date === selectedDate}"><span>${dayNumber}</span>${date === today ? '<small class="today-label">HARI INI</small>' : `<i class="day-dot" aria-hidden="true"></i>`}${bookings.length ? `<span class="appointment-count" aria-hidden="true">${bookings.length}</span>` : ""}</button>`;
  }
  grid.innerHTML = markup;
  renderBookings();
}

function statusClass(status) {
  return {
    confirmed: "Dikonfirmasi",
    pending_confirmation: "Menunggu konfirmasi",
    completed: "Selesai"
  }[status] || "Terjadwal";
}

function renderBookings() {
  if (!authenticated) return;
  bookingCount.hidden = false;
  bookingCount.textContent = `${reservations.length} reservasi bulan ini`;
  if (!reservations.length) {
    bookingList.innerHTML = '<div class="no-bookings">Belum ada reservasi pada bulan ini. Jadwal operasional tetap bisa dilihat di kalender.</div>';
    return;
  }
  bookingList.innerHTML = reservations.map(reservation => {
    const service = [reservation.service_name, reservation.hairstyle_name, reservation.additional_service].filter(Boolean).join(" · ");
    const start = String(reservation.start_time || "").slice(0, 5);
    const end = String(reservation.end_time || "").slice(0, 5);
    const details = [reservation.barber_name, statusClass(reservation.status)].filter(Boolean).join(" · ");
    return `<article class="booking-row"><div class="booking-date">${escapeHtml(formatDate(reservation.appointment_date, { day: "2-digit", month: "short" }))}</div><div class="booking-details"><strong>${escapeHtml(service || "Reservasi Pemon")}</strong><span>${escapeHtml(reservation.reservation_code)}${details ? ` · ${escapeHtml(details)}` : ""}</span></div><div class="booking-time">${escapeHtml(start)}${end ? `–${escapeHtml(end)}` : ""} WIB</div></article>`;
  }).join("");
}

function showGuestSchedule() {
  sessionChecked = true;
  authenticated = false;
  reservations = [];
  loadingMessage.hidden = true;
  loginPrompt.hidden = false;
  bookingList.hidden = true;
  bookingList.replaceChildren();
  bookingCount.hidden = true;
  renderCalendar();
}

function showPrivateSchedule() {
  loadingMessage.hidden = true;
  loginPrompt.hidden = true;
  bookingList.hidden = false;
  renderCalendar();
}

async function loadMonthSchedule() {
  if (sessionChecked && !authenticated) return;
  const currentRequest = ++requestNumber;
  loadingMessage.hidden = false;
  loadingMessage.textContent = authenticated ? "Memuat reservasi akunmu…" : "Memeriksa sesi akun…";
  if (authenticated) bookingList.hidden = true;

  try {
    const response = await fetch(`./schedule.php?month=${encodeURIComponent(activeMonth)}`, {
      headers: { Accept: "application/json" },
      cache: "no-store"
    });
    if (currentRequest !== requestNumber) return;
    if (response.status === 401) {
      showGuestSchedule();
      return;
    }
    if (!response.ok) throw new Error("Jadwal belum dapat dimuat");
    const payload = await response.json();
    if (!payload.authenticated || !Array.isArray(payload.reservations)) {
      showGuestSchedule();
      return;
    }
    sessionChecked = true;
    authenticated = true;
    reservations = payload.reservations;
    showPrivateSchedule();
  } catch (error) {
    if (currentRequest !== requestNumber) return;
    loadingMessage.hidden = false;
    loadingMessage.textContent = "Jadwal akun belum dapat dimuat. Periksa koneksi server dan basis data.";
    loginPrompt.hidden = true;
    bookingList.hidden = true;
    bookingList.replaceChildren();
    bookingCount.hidden = true;
    reservations = [];
    renderCalendar();
  }
}

function changeMonth(offset) {
  const { year, index } = monthParts();
  const next = new Date(Date.UTC(year, index + offset, 1));
  activeMonth = `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, "0")}`;
  selectedDate = `${activeMonth}-01`;
  renderCalendar();
  if (!sessionChecked || authenticated) loadMonthSchedule();
}

function markTodayHours() {
  const weekday = new Date(`${today}T12:00:00+07:00`).getUTCDay();
  document.querySelectorAll("#hours-list [data-weekday]").forEach(row => {
    const isToday = Number(row.dataset.weekday) === weekday;
    row.classList.toggle("is-today", isToday);
    if (isToday) row.querySelector("span").textContent += " (Hari ini) •";
  });

  const hour = Number(new Intl.DateTimeFormat("id-ID", { hour: "numeric", hour12: false, timeZone: "Asia/Jakarta" }).format(new Date()));
  document.querySelector("#open-status-text").textContent = hour >= 9 && hour < 21
    ? "Buka sekarang · tutup pukul 21.00 WIB"
    : "Buka setiap hari · 09.00–21.00 WIB";
}

grid.addEventListener("click", event => {
  const dayButton = event.target.closest("[data-date]");
  if (!dayButton) return;
  selectedDate = dayButton.dataset.date;
  renderCalendar();
});

document.querySelector("#month-previous").addEventListener("click", () => changeMonth(-1));
document.querySelector("#month-next").addEventListener("click", () => changeMonth(1));
document.querySelector(".menu-toggle").addEventListener("click", event => {
  const button = event.currentTarget;
  const nav = document.querySelector(".main-nav");
  const expanded = button.getAttribute("aria-expanded") === "true";
  button.setAttribute("aria-expanded", String(!expanded));
  nav.classList.toggle("is-open", !expanded);
});

window.addEventListener("pagehide", () => {
  reservations = [];
  bookingList.replaceChildren();
  bookingList.hidden = true;
  loginPrompt.hidden = false;
  loadingMessage.hidden = true;
  bookingCount.hidden = true;
  authenticated = false;
  sessionChecked = false;
  renderCalendar();
});
window.addEventListener("pageshow", event => {
  if (event.persisted) loadMonthSchedule();
});

markTodayHours();
renderCalendar();
loadMonthSchedule();
