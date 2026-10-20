let bookingDraft = readBookingDraft() || {
  service: { id: "", name: "Belum dipilih", price: 0, duration_minutes: 0 },
  hairstyle: { id: "", name: "Belum dipilih", price: 0 },
  addons: []
};
const hasBookingDraft = Boolean(bookingDraft.service?.id && bookingDraft.hairstyle?.id);
if (!hasBookingDraft) window.location.replace("booking-services.html");
if (bookingDraft?.reservation) window.location.replace("booking-confirmation.html");
let bookingSubmitting = false;
let bookingToken = bookingDraft?.request_token || createBookingToken();
const today = new Date();
let selectedBookingDate = bookingDraft?.appointment_date || "";
let selectedBookingTime = bookingDraft?.start_time || "";
let visibleMonth = new Date(`${selectedBookingDate || dateKey(today.getFullYear(), today.getMonth(), today.getDate())}T12:00:00`);
visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
let monthAvailability = null;
let availableTimeSlots = [];
let slotRequestNumber = 0;
let monthRequestNumber = 0;
let toastTimer;

function readBookingDraft() {
  try {
    const value = sessionStorage.getItem("pemonBookingDraft");
    return value ? JSON.parse(value) : null;
  } catch (error) {
    return null;
  }
}

function createBookingToken() {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, character => {
    const random = Math.floor(Math.random() * 16);
    return (character === "x" ? random : (random & 0x3) | 0x8).toString(16);
  });
}

function saveBookingDraft(draft) {
  try {
    sessionStorage.setItem("pemonBookingDraft", JSON.stringify(draft));
    return true;
  } catch (error) {
    return false;
  }
}

function dateKey(year, month, day) {
  return `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

function bookingDateLabel(value, options = { day: "2-digit", month: "long", year: "numeric" }) {
  if (!value) return "Pilih tanggal";
  const date = new Date(`${value}T12:00:00`);
  return new Intl.DateTimeFormat("id-ID", options).format(date);
}

function bookingCurrency(value) {
  return `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
}

function availableDayStatus(date) {
  return monthAvailability ? monthAvailability[date] || "unavailable" : "unavailable";
}

function renderCalendar() {
  const year = visibleMonth.getFullYear();
  const month = visibleMonth.getMonth();
  document.querySelector("#calendar-month").textContent = new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(visibleMonth);
  const firstDayOffset = (new Date(year, month, 1).getDay() + 6) % 7;
  const dayCount = new Date(year, month + 1, 0).getDate();
  const cells = [];

  for (let index = 0; index < firstDayOffset; index += 1) {
    cells.push('<span class="calendar-day is-empty" aria-hidden="true"></span>');
  }
  for (let day = 1; day <= dayCount; day += 1) {
    const date = dateKey(year, month, day);
    const status = availableDayStatus(date);
    const selected = date === selectedBookingDate;
    const disabled = status !== "available";
    const classes = ["calendar-day", selected ? "is-selected" : "", status === "unavailable" ? "is-unavailable" : "", status === "full" ? "is-full" : ""].filter(Boolean).join(" ");
    const statusLabel = status === "full" ? "Penuh" : status === "unavailable" ? "Tidak tersedia" : "Tersedia";
    cells.push(`<button type="button" class="${classes}" role="gridcell" data-date="${date}" aria-label="${day} ${bookingDateLabel(date, { month: "long" })}, ${statusLabel}" ${selected ? 'aria-selected="true"' : ""} ${disabled ? "disabled" : ""}>${day}</button>`);
  }
  document.querySelector("#calendar-days").innerHTML = cells.join("");
}

function renderTimeSlots() {
  const container = document.querySelector("#time-slots");
  if (!selectedBookingDate) {
    container.innerHTML = '<p class="no-time-slots">Pilih tanggal terlebih dahulu untuk melihat slot waktu.</p>';
    return;
  }
  if (!availableTimeSlots.length) {
    container.innerHTML = '<p class="no-time-slots">Tidak ada waktu yang tersedia pada tanggal ini.</p>';
    return;
  }
  container.innerHTML = availableTimeSlots.map(slot => {
    const time = String(slot.time || slot.start_time || "").slice(0, 5);
    const status = slot.status || (Number(slot.remaining_capacity ?? 1) <= 0 ? "unavailable" : "available");
    const unavailable = status !== "available";
    const label = time.replace(":", ".");
    const unavailableLabel = "Slot ini sudah dipesan dan tidak tersedia";
    return `<button type="button" class="time-slot ${time === selectedBookingTime && !unavailable ? "is-selected" : ""} ${unavailable ? "is-unavailable" : ""}" data-time="${time}" aria-label="${label}${unavailable ? `, ${unavailableLabel}` : ", tersedia"}" ${unavailable ? `title="${unavailableLabel}" disabled` : ""}>${label}</button>`;
  }).join("");
}

function updateBookingRecap() {
  const service = bookingDraft.service;
  const hairstyle = bookingDraft.hairstyle;
  const addons = Array.isArray(bookingDraft.addons) ? bookingDraft.addons : [];
  const addonsPrice = addons.reduce((sum, item) => sum + Number(item.price || 0), 0);
  const total = Number(service.price || 0) + addonsPrice;
  const dateLabel = bookingDateLabel(selectedBookingDate);
  const dateLongLabel = bookingDateLabel(selectedBookingDate, { weekday: "long", day: "2-digit", month: "long", year: "numeric" });

  document.querySelector("#selected-date-label").textContent = dateLongLabel;
  document.querySelector("#recap-service-name").textContent = service.name || "-";
  document.querySelector("#recap-service-price").textContent = bookingCurrency(service.price);
  document.querySelector("#recap-hairstyle-name").textContent = hairstyle.name || "-";
  document.querySelector("#recap-addons-name").textContent = addons.length ? addons.map(item => item.name).join(", ") : "-";
  document.querySelector("#recap-addons-price").textContent = addons.length ? bookingCurrency(addonsPrice) : "";
  document.querySelector("#recap-date").textContent = dateLabel;
  document.querySelector("#recap-time").textContent = selectedBookingTime ? selectedBookingTime.replace(":", ".") : "-";
  document.querySelector("#recap-total").textContent = bookingCurrency(total);
}

function showDateTimeToast(message) {
  const toast = document.querySelector("#datetime-toast");
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 3500);
}

async function loadMonthAvailability() {
  const requestNumber = ++monthRequestNumber;
  monthAvailability = null;
  availableTimeSlots = [];
  renderCalendar();
  renderTimeSlots();
  const month = `${visibleMonth.getFullYear()}-${String(visibleMonth.getMonth() + 1).padStart(2, "0")}`;
  try {
    const response = await fetch(`./booking-datetime.php?month=${encodeURIComponent(month)}`, { headers: { Accept: "application/json" }, cache: "no-store" });
    if (!response.ok) throw new Error("Jadwal belum tersedia");
    const payload = await response.json();
    if (requestNumber !== monthRequestNumber) return;
    const map = {};
    for (const day of payload.dates || []) map[day.date] = day.status;
    monthAvailability = map;
    if (selectedBookingDate && availableDayStatus(selectedBookingDate) !== "available") {
      selectedBookingDate = "";
      selectedBookingTime = "";
    }
    renderCalendar();
    if (selectedBookingDate) {
      await loadTimeSlotsForDate(selectedBookingDate);
      return;
    }
    availableTimeSlots = [];
    renderTimeSlots();
    updateBookingRecap();
  } catch (error) {
    if (requestNumber !== monthRequestNumber) return;
    monthAvailability = {};
    selectedBookingDate = "";
    selectedBookingTime = "";
    availableTimeSlots = [];
    renderCalendar();
    renderTimeSlots();
    updateBookingRecap();
  }
}

function slotTime(slot) {
  return String(slot.time || slot.start_time || "").slice(0, 5);
}

function slotIsAvailable(slot) {
  const status = slot.status || (Number(slot.remaining_capacity ?? 1) <= 0 ? "unavailable" : "available");
  return status === "available";
}

async function loadTimeSlotsForDate(date, { notifyIfTaken = false } = {}) {
  const requestNumber = ++slotRequestNumber;
  const previousTime = selectedBookingTime;
  let requestSucceeded = false;
  try {
    const response = await fetch(`./booking-datetime.php?date=${encodeURIComponent(date)}`, { headers: { Accept: "application/json" }, cache: "no-store" });
    if (!response.ok) throw new Error("Slot waktu belum tersedia");
    const payload = await response.json();
    if (requestNumber !== slotRequestNumber) return false;
    availableTimeSlots = Array.isArray(payload.slots) ? payload.slots : [];
    requestSucceeded = true;
  } catch (error) {
    if (requestNumber !== slotRequestNumber) return false;
    availableTimeSlots = [];
  }

  const currentSlotAvailable = availableTimeSlots.some(slot => slotTime(slot) === selectedBookingTime && slotIsAvailable(slot));
  if (!currentSlotAvailable) {
    selectedBookingTime = "";
    if (requestSucceeded && notifyIfTaken && previousTime) {
      showDateTimeToast(`Pukul ${previousTime.replace(":", ".")} baru saja dipesan. Silakan pilih waktu lain.`);
    }
  }
  renderTimeSlots();
  updateBookingRecap();
  return Boolean(selectedBookingTime && availableTimeSlots.some(slot => slotTime(slot) === selectedBookingTime && slotIsAvailable(slot)));
}

async function selectBookingDate(date) {
  selectedBookingDate = date;
  selectedBookingTime = "";
  renderCalendar();
  updateBookingRecap();
  await loadTimeSlotsForDate(date);
}

document.querySelector("#calendar-days").addEventListener("click", event => {
  const dayButton = event.target.closest("[data-date]");
  if (!dayButton || dayButton.disabled) return;
  selectBookingDate(dayButton.dataset.date);
});

document.querySelector("#time-slots").addEventListener("click", event => {
  const button = event.target.closest("[data-time]");
  if (!button || button.disabled) return;
  selectedBookingTime = button.dataset.time;
  renderTimeSlots();
  updateBookingRecap();
});

document.querySelector("#previous-month").addEventListener("click", () => {
  const nextMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
  visibleMonth = nextMonth;
  selectedBookingDate = "";
  selectedBookingTime = "";
  updateBookingRecap();
  loadMonthAvailability();
});
document.querySelector("#next-month").addEventListener("click", () => {
  const nextMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
  visibleMonth = nextMonth;
  selectedBookingDate = "";
  selectedBookingTime = "";
  updateBookingRecap();
  loadMonthAvailability();
});

document.querySelectorAll("[data-edit]").forEach(button => {
  button.addEventListener("click", () => {
    if (button.dataset.edit === "service") window.location.href = "booking-services.html";
    else document.querySelector(button.dataset.edit === "date" ? ".calendar-card" : ".time-card").scrollIntoView({ behavior: "smooth", block: "center" });
  });
});

document.querySelector("#cancel-booking").addEventListener("click", () => {
  try { sessionStorage.removeItem("pemonBookingDraft"); } catch (error) { /* No server reservation exists at this step. */ }
  window.location.href = "index.html";
});

document.querySelector("#continue-booking").addEventListener("click", async () => {
  if (bookingSubmitting) return;
  if (!hasBookingDraft) {
    window.location.href = "booking-services.html";
    return;
  }
  if (!selectedBookingDate) {
    showDateTimeToast("Pilih tanggal terlebih dahulu.");
    return;
  }
  if (!selectedBookingTime) {
    showDateTimeToast("Pilih waktu yang masih tersedia terlebih dahulu.");
    return;
  }

  const button = document.querySelector("#continue-booking");
  const buttonLabel = document.querySelector("#continue-booking-label");
  bookingSubmitting = true;
  button.disabled = true;
  button.setAttribute("aria-busy", "true");
  buttonLabel.textContent = "Memeriksa ketersediaan…";
  try {
    const selectedTimeIsAvailable = await loadTimeSlotsForDate(selectedBookingDate, { notifyIfTaken: true });
    if (!selectedTimeIsAvailable || !selectedBookingTime) {
      showDateTimeToast("Waktu yang dipilih sudah tidak tersedia. Silakan pilih slot lain.");
      return;
    }

    const service = bookingDraft.service;
    const addons = Array.isArray(bookingDraft.addons) ? bookingDraft.addons : [];
    const updatedDraft = {
      ...bookingDraft,
      request_token: bookingToken,
      appointment_date: selectedBookingDate,
      start_time: selectedBookingTime,
      total: Number(service.price || 0) + addons.reduce((sum, item) => sum + Number(item.price || 0), 0)
    };
    if (!saveBookingDraft(updatedDraft)) {
      showDateTimeToast("Pilihan belum tersimpan di browser. Aktifkan penyimpanan sesi lalu coba lagi.");
      return;
    }
    buttonLabel.textContent = "Membuka konfirmasi…";
    window.location.href = "booking-confirmation.html";
  } catch (error) {
    showDateTimeToast("Jadwal belum dapat diperiksa. Coba lagi sebentar.");
  } finally {
    bookingSubmitting = false;
    button.removeAttribute("aria-busy");
    if (window.location.pathname.endsWith("booking-datetime.html")) {
      button.disabled = false;
      buttonLabel.textContent = "Lanjut ke Konfirmasi";
    }
  }
});

renderCalendar();
renderTimeSlots();
updateBookingRecap();
loadMonthAvailability();

window.setInterval(() => {
  if (!document.hidden && selectedBookingDate) loadTimeSlotsForDate(selectedBookingDate, { notifyIfTaken: true });
}, 20000);
window.addEventListener("focus", () => {
  if (selectedBookingDate) loadTimeSlotsForDate(selectedBookingDate, { notifyIfTaken: true });
});
document.addEventListener("visibilitychange", () => {
  if (!document.hidden && selectedBookingDate) loadTimeSlotsForDate(selectedBookingDate, { notifyIfTaken: true });
});
