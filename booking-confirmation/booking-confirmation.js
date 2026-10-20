let confirmationDraft = readConfirmationDraft();
let activeCustomer = null;
let isConfirming = false;
let confirmationToastTimer;

function readConfirmationDraft() {
  try {
    const raw = sessionStorage.getItem("pemonBookingDraft");
    return raw ? JSON.parse(raw) : null;
  } catch (error) {
    return null;
  }
}

function saveConfirmationDraft() {
  try {
    sessionStorage.setItem("pemonBookingDraft", JSON.stringify(confirmationDraft));
    return true;
  } catch (error) {
    return false;
  }
}

function newConfirmationToken() {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, character => {
    const random = Math.floor(Math.random() * 16);
    return (character === "x" ? random : (random & 0x3) | 0x8).toString(16);
  });
}

function formatConfirmationMoney(value) {
  return `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
}

function formatConfirmationDate(value, options = { day: "2-digit", month: "long", year: "numeric" }) {
  if (!value) return "Belum dipilih";
  const parsed = new Date(`${value}T12:00:00`);
  if (Number.isNaN(parsed.getTime())) return "Belum dipilih";
  return new Intl.DateTimeFormat("id-ID", options).format(parsed);
}

function formatConfirmationDuration(minutes) {
  const duration = Math.max(0, Number(minutes || 0));
  const hours = Math.floor(duration / 60);
  const remainder = duration % 60;
  if (hours && remainder) return `±${hours} jam ${remainder} menit`;
  if (hours) return `±${hours} jam`;
  return `±${duration || 30} menit`;
}

function confirmationTotal() {
  const service = confirmationDraft?.service || {};
  const addons = Array.isArray(confirmationDraft?.addons) ? confirmationDraft.addons : [];
  return Number(service.price || 0) + addons.reduce((sum, item) => sum + Number(item.price || 0), 0);
}

function confirmationDuration() {
  const service = confirmationDraft?.service || {};
  const addons = Array.isArray(confirmationDraft?.addons) ? confirmationDraft.addons : [];
  return Number(service.duration_minutes || 30) + addons.reduce((sum, item) => sum + Number(item.duration_minutes || 0), 0);
}

function showConfirmationToast(message) {
  const toast = document.querySelector("#confirmation-toast");
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(confirmationToastTimer);
  confirmationToastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 4500);
}

function setConfirmationBusy(busy) {
  document.querySelectorAll(".section-title-row a, .back-to-date, #cancel-reservation").forEach(control => {
    control.setAttribute("aria-disabled", String(busy));
    if (control.matches("a")) {
      control.tabIndex = busy ? -1 : 0;
      control.classList.toggle("is-disabled", busy);
    } else {
      control.disabled = busy;
    }
  });
  document.querySelectorAll("input[name='payment-method']").forEach(input => { input.disabled = busy; });
}

function validateConfirmationDraft() {
  return Boolean(
    confirmationDraft &&
    confirmationDraft.service?.id &&
    confirmationDraft.hairstyle?.id &&
    /^\d{4}-\d{2}-\d{2}$/.test(confirmationDraft.appointment_date || "") &&
    /^\d{2}:\d{2}$/.test(confirmationDraft.start_time || "")
  );
}

function renderConfirmationDraft() {
  if (!validateConfirmationDraft()) {
    window.location.replace(confirmationDraft?.service?.id ? "booking-datetime.html" : "booking-services.html");
    return;
  }
  const service = confirmationDraft.service;
  const hairstyle = confirmationDraft.hairstyle;
  const addons = Array.isArray(confirmationDraft.addons) ? confirmationDraft.addons : [];
  const addonNames = addons.length ? addons.map(item => item.name).join(", ") : "Tidak ada";
  const addonTotal = addons.reduce((sum, item) => sum + Number(item.price || 0), 0);
  const fullDate = formatConfirmationDate(confirmationDraft.appointment_date, { weekday: "long", day: "2-digit", month: "long", year: "numeric" });
  const time = String(confirmationDraft.start_time || "").replace(":", ".");
  const total = confirmationTotal();
  const savedPaymentMethod = confirmationDraft.payment_method || "E-Wallet";
  const selectedPaymentInput = [...document.querySelectorAll("input[name='payment-method']")].find(input => input.value === savedPaymentMethod);
  if (selectedPaymentInput) {
    selectedPaymentInput.checked = true;
    document.querySelectorAll(".payment-option").forEach(option => option.classList.toggle("is-selected", option.contains(selectedPaymentInput)));
  }

  document.querySelector("#review-service").textContent = service.name || "-";
  document.querySelector("#review-service-price").textContent = formatConfirmationMoney(service.price);
  document.querySelector("#review-hairstyle").textContent = hairstyle.name || "-";
  document.querySelector("#review-addons").textContent = addonNames;
  document.querySelector("#review-addons-price").textContent = addons.length ? formatConfirmationMoney(addonTotal) : "-";
  document.querySelector("#review-duration").textContent = formatConfirmationDuration(confirmationDuration());
  document.querySelector("#review-date").textContent = fullDate;
  document.querySelector("#review-time").textContent = `${time} WIB`;

  document.querySelector("#summary-service").textContent = service.name || "-";
  document.querySelector("#summary-hairstyle").textContent = hairstyle.name || "-";
  document.querySelector("#summary-date").textContent = formatConfirmationDate(confirmationDraft.appointment_date);
  document.querySelector("#summary-time").textContent = `${time} WIB`;
  document.querySelector("#summary-addons").textContent = addonNames;
  document.querySelector("#summary-duration").textContent = formatConfirmationDuration(confirmationDuration());
  document.querySelector("#summary-total").textContent = formatConfirmationMoney(total);
}

function showReservationSuccess(reservation) {
  document.querySelector("#reservation-review").hidden = true;
  document.querySelector("#reservation-success").hidden = false;
  document.querySelector("#confirmation-title").textContent = "4. Reservasi Selesai";
  document.querySelector(".confirmation-main .datetime-heading > p:last-child").textContent = "Reservasimu telah tersimpan. Simpan kode reservasi untuk ditunjukkan saat datang.";
  document.querySelector("#success-customer").textContent = activeCustomer?.customer_name || "pelanggan";
  document.querySelector("#success-code-value").textContent = reservation.reservation_code || "-";
  document.querySelector("#success-date").textContent = formatConfirmationDate(reservation.date || confirmationDraft.appointment_date, { weekday: "long", day: "2-digit", month: "long", year: "numeric" });
  document.querySelector("#success-time").textContent = `${String(reservation.time || confirmationDraft.start_time).replace(":", ".")} WIB`;
  document.querySelector("#success-total").textContent = formatConfirmationMoney(reservation.amount ?? confirmationTotal());
  document.querySelector("#success-payment-method").textContent = reservation.payment_method || confirmationDraft.payment_method || "E-Wallet";
}

async function checkConfirmationSession() {
  const status = document.querySelector("#customer-status");
  const authRequired = document.querySelector("#auth-required");
  const confirmButton = document.querySelector("#confirm-reservation");
  const savedReservation = confirmationDraft?.reservation || null;
  try {
    const response = await fetch("./login.php?status=1", { headers: { Accept: "application/json" }, cache: "no-store" });
    if (!response.ok) throw new Error("Sesi tidak dapat diperiksa");
    activeCustomer = await response.json();
    if (!activeCustomer.authenticated) {
      status.classList.remove("is-loading");
      status.textContent = "Belum masuk ke akun.";
      document.querySelector("#customer-details").hidden = true;
      authRequired.hidden = false;
      confirmButton.disabled = true;
      return;
    }

    authRequired.hidden = true;
    status.classList.remove("is-loading");
    status.textContent = "Detail akun pemesan:";
    document.querySelector("#customer-details").hidden = false;
    document.querySelector("#customer-name").textContent = activeCustomer.customer_name || "-";
    document.querySelector("#customer-whatsapp").textContent = activeCustomer.customer_whatsapp || "Belum ditambahkan";

    if (savedReservation && Number(confirmationDraft.confirmed_customer_id) === Number(activeCustomer.customer_id)) {
      showReservationSuccess(savedReservation);
      return;
    }
    if (savedReservation) {
      // A different account is active in this tab; do not expose the prior account's booking.
      delete confirmationDraft.reservation;
      delete confirmationDraft.confirmed_customer_id;
      confirmationDraft.request_token = newConfirmationToken();
      saveConfirmationDraft();
    }
    confirmButton.disabled = false;
  } catch (error) {
    status.classList.remove("is-loading");
    status.textContent = "Sesi akun belum dapat diperiksa. Pastikan halaman dibuka melalui server PHP.";
    document.querySelector("#customer-details").hidden = true;
    authRequired.hidden = false;
    confirmButton.disabled = true;
  }
}

document.querySelectorAll("input[name='payment-method']").forEach(input => {
  input.addEventListener("change", () => {
    if (!confirmationDraft) return;
    document.querySelectorAll(".payment-option").forEach(option => option.classList.toggle("is-selected", option.contains(input) && input.checked));
    confirmationDraft.payment_method = input.value;
    saveConfirmationDraft();
  });
});

document.querySelector("#confirm-reservation").addEventListener("click", async () => {
  if (isConfirming || !activeCustomer?.authenticated || !validateConfirmationDraft()) return;
  const selectedPayment = document.querySelector("input[name='payment-method']:checked")?.value || "E-Wallet";
  confirmationDraft.request_token ||= newConfirmationToken();
  confirmationDraft.payment_method = selectedPayment;
  if (!saveConfirmationDraft()) {
    showConfirmationToast("Draf reservasi tidak dapat dibaca oleh browser. Muat ulang halaman dan coba lagi.");
    return;
  }

  isConfirming = true;
  const button = document.querySelector("#confirm-reservation");
  const label = document.querySelector("#confirm-label");
  button.disabled = true;
  button.setAttribute("aria-busy", "true");
  setConfirmationBusy(true);
  label.textContent = "Memeriksa jadwal dan menyimpan…";
  try {
    const response = await fetch("./booking-confirmation.php", {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({
        request_token: confirmationDraft.request_token,
        service_id: confirmationDraft.service.id,
        hairstyle_id: confirmationDraft.hairstyle.id,
        addon_ids: (confirmationDraft.addons || []).map(item => item.id),
        date: confirmationDraft.appointment_date,
        time: confirmationDraft.start_time,
        payment_method: selectedPayment
      })
    });
    const payload = await response.json().catch(() => ({}));
    if (response.status === 401) {
      window.location.href = "login.html?next=booking-confirmation.html";
      return;
    }
    if (!response.ok) {
      showConfirmationToast(payload.error || "Reservasi belum dapat dikonfirmasi. Silakan coba lagi.");
      if (payload.code === "TIME_UNAVAILABLE") {
        window.setTimeout(() => { window.location.href = "booking-datetime.html"; }, 1200);
      }
      return;
    }
    const reservation = { ...payload.reservation, payment_method: payload.reservation.payment_method || selectedPayment };
    confirmationDraft.reservation = reservation;
    confirmationDraft.confirmed_customer_id = activeCustomer.customer_id;
    saveConfirmationDraft();
    showReservationSuccess(reservation);
  } catch (error) {
    showConfirmationToast("Server belum terhubung. Pastikan PHP dan basis data aktif, lalu coba lagi.");
  } finally {
    isConfirming = false;
    button.removeAttribute("aria-busy");
    if (!document.querySelector("#reservation-success").hidden) return;
    setConfirmationBusy(false);
    button.disabled = !activeCustomer?.authenticated;
    label.textContent = "Konfirmasi Pesanan";
  }
});

document.querySelector("#cancel-reservation").addEventListener("click", () => {
  if (isConfirming) return;
  try { sessionStorage.removeItem("pemonBookingDraft"); } catch (error) { /* No reservation is written before final confirmation. */ }
  window.location.href = "index.html";
});

renderConfirmationDraft();
checkConfirmationSession();
