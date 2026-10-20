const defaultBookingCatalog = [
  { id: "haircut", category: "service", name: "Potong Rambut", price: 50000, duration_minutes: 30, description: "Potongan rambut sesuai gaya yang kamu inginkan.", is_default: true },
  { id: "color", category: "service", name: "Pewarnaan Rambut", price: 150000, duration_minutes: 90, description: "Memberikan warna rambut sesuai keinginan." },
  { id: "styling", category: "service", name: "Penataan Rambut", price: 150000, duration_minutes: 45, description: "Penataan rambut sesuai kebutuhan." },
  { id: "peti-cut", category: "service", name: "Potong Rambut oleh Peti", price: 50000, duration_minutes: 40, description: "Potongan rambut premium oleh Peti, ahli cukur berpengalaman." },
  { id: "keratin", category: "service", name: "Perawatan Keratin", price: 150000, duration_minutes: 90, description: "Menutrisi dan merawat rambut agar terasa lebih sehat." },
  { id: "women-cut", category: "service", name: "Potong Rambut Wanita", price: 150000, duration_minutes: 60, description: "Potongan sesuai bentuk wajah dan gaya." },
  { id: "kids-cut", category: "service", name: "Potong Rambut Anak", price: 180000, duration_minutes: 45, description: "Potongan rapi dan nyaman untuk anak." },
  { id: "down-perm", category: "service", name: "Down Perm", price: 180000, duration_minutes: 90, description: "Memberi efek rambut lebih lurus dengan hasil tahan lama." },

  { id: "classic-cut", category: "hairstyle", name: "Potongan Klasik", price: 0, duration_minutes: 0, is_default: true },
  { id: "undercut", category: "hairstyle", name: "Undercut", price: 0, duration_minutes: 0 },
  { id: "pompadour", category: "hairstyle", name: "Pompadour", price: 0, duration_minutes: 0 },
  { id: "fade", category: "hairstyle", name: "Fade", price: 0, duration_minutes: 0 },
  { id: "crew-cut", category: "hairstyle", name: "Crew Cut", price: 0, duration_minutes: 0 },
  { id: "others", category: "hairstyle", name: "Lainnya", price: 0, duration_minutes: 0 },

  { id: "shaving", category: "addon", name: "Cukur", price: 25000, duration_minutes: 15 },
  { id: "head-massage", category: "addon", name: "Pijat Kepala", price: 20000, duration_minutes: 20 },
  { id: "treatment", category: "addon", name: "Perawatan Rambut", price: 50000, duration_minutes: 30 },
  { id: "hair-mask", category: "addon", name: "Masker Rambut", price: 40000, duration_minutes: 15 }
];

let bookingCatalog = [...defaultBookingCatalog];
let selectedServiceId = "haircut";
let selectedHairstyleId = "classic-cut";
let selectedAddonIds = new Set();
let bookingToastTimer;

const bookingEscape = value => String(value ?? "").replace(/[&<>"']/g, character => ({
  "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
}[character]));

function bookingMoney(value) {
  return `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
}

function catalogByCategory(category) {
  return bookingCatalog.filter(item => item.category === category && Number(item.is_active ?? 1) === 1);
}

function optionCard(item, group, kind) {
  const isAddon = kind === "addon";
  const selected = isAddon ? selectedAddonIds.has(String(item.id)) : String(item.id) === String(group === "service" ? selectedServiceId : selectedHairstyleId);
  const inputType = isAddon ? "checkbox" : "radio";
  const controlKind = "choice-control";
  const titleRow = `<span class="choice-title-row"><span class="${controlKind}"></span><span class="choice-title">${bookingEscape(item.name)}</span>${kind !== "hairstyle" ? `<span class="choice-price">${bookingMoney(item.price)}</span>` : ""}</span>`;
  const description = kind === "service" && item.description ? `<span class="choice-description">${bookingEscape(item.description)}</span>` : "";
  const cardClass = kind === "service" ? "choice-card service-choice-card" : "choice-card";
  const optionClass = kind === "service" ? "choice-option service-option" : kind === "hairstyle" ? "choice-option hairstyle-option" : "choice-option addon-option";
  return `<label class="${optionClass}">
    <input type="${inputType}" name="${bookingEscape(group)}" value="${bookingEscape(item.id)}" data-category="${bookingEscape(kind)}" ${selected ? "checked" : ""}>
    <span class="${cardClass}">${titleRow}${description}</span>
  </label>`;
}

function renderCatalog() {
  const services = catalogByCategory("service");
  const styles = catalogByCategory("hairstyle");
  const addons = catalogByCategory("addon");

  if (!services.some(item => String(item.id) === String(selectedServiceId))) {
    selectedServiceId = String(services.find(item => Number(item.is_default) === 1)?.id ?? services[0]?.id ?? "");
  }
  if (!styles.some(item => String(item.id) === String(selectedHairstyleId))) {
    selectedHairstyleId = String(styles.find(item => Number(item.is_default) === 1)?.id ?? styles[0]?.id ?? "");
  }
  selectedAddonIds = new Set([...selectedAddonIds].filter(id => addons.some(item => String(item.id) === id)));

  document.querySelector("#service-options").innerHTML = services.map(item => optionCard(item, "service", "service")).join("");
  document.querySelector("#hairstyle-options").innerHTML = styles.map(item => optionCard(item, "hairstyle", "hairstyle")).join("");
  document.querySelector("#addon-options").innerHTML = addons.map(item => optionCard(item, "addon", "addon")).join("");
  updateBookingSummary();
}

function formatDuration(minutes) {
  if (!minutes) return "±30 menit";
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  if (hours && rest) return `±${hours} jam ${rest} menit`;
  if (hours) return `±${hours} jam`;
  return `±${minutes} menit`;
}

function updateBookingSummary() {
  const services = catalogByCategory("service");
  const styles = catalogByCategory("hairstyle");
  const addons = catalogByCategory("addon");
  const service = services.find(item => String(item.id) === String(selectedServiceId));
  const hairstyle = styles.find(item => String(item.id) === String(selectedHairstyleId));
  const selectedAddons = addons.filter(item => selectedAddonIds.has(String(item.id)));
  const addonsTotal = selectedAddons.reduce((sum, item) => sum + Number(item.price || 0), 0);
  const total = Number(service?.price || 0) + addonsTotal;
  const duration = Number(service?.duration_minutes || 30) + selectedAddons.reduce((sum, item) => sum + Number(item.duration_minutes || 0), 0);

  document.querySelector("#summary-service-name").textContent = service?.name || "-";
  document.querySelector("#summary-service-price").textContent = bookingMoney(service?.price || 0);
  document.querySelector("#summary-hairstyle-name").textContent = hairstyle?.name || "-";
  document.querySelector("#summary-addons-name").textContent = selectedAddons.length ? selectedAddons.map(item => item.name).join(", ") : "-";
  document.querySelector("#summary-addons-price").textContent = selectedAddons.length ? bookingMoney(addonsTotal) : "-";
  document.querySelector("#summary-duration").textContent = formatDuration(duration);
  document.querySelector("#summary-total").textContent = bookingMoney(total);
}

function showBookingToast(message) {
  const toast = document.querySelector("#booking-toast");
  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(bookingToastTimer);
  bookingToastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 3500);
}

document.querySelector("#service-picker").addEventListener("change", event => {
  const input = event.target;
  if (!(input instanceof HTMLInputElement)) return;
  if (input.dataset.category === "service") selectedServiceId = input.value;
  if (input.dataset.category === "hairstyle") selectedHairstyleId = input.value;
  if (input.dataset.category === "addon") {
    if (input.checked) selectedAddonIds.add(input.value);
    else selectedAddonIds.delete(input.value);
  }
  updateBookingSummary();
});

document.querySelector("#service-picker").addEventListener("submit", event => {
  event.preventDefault();
  const service = catalogByCategory("service").find(item => String(item.id) === String(selectedServiceId));
  const hairstyle = catalogByCategory("hairstyle").find(item => String(item.id) === String(selectedHairstyleId));
  const addons = catalogByCategory("addon").filter(item => selectedAddonIds.has(String(item.id)));
  const draft = {
    service,
    hairstyle,
    addons,
    estimate: Number(service?.price || 0) + addons.reduce((sum, item) => sum + Number(item.price || 0), 0)
  };
  try { sessionStorage.setItem("pemonBookingDraft", JSON.stringify(draft)); } catch (error) { /* Browser storage may be unavailable in file preview. */ }
  showBookingToast("Pilihan layanan tersimpan. Silakan lanjutkan dengan memilih tanggal dan jam.");
});

async function loadBookingCatalog() {
  try {
    const response = await fetch("./booking-services.php", { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error(`Katalog memberikan respons ${response.status}`);
    const payload = await response.json();
    if (Array.isArray(payload.catalog)) bookingCatalog = payload.catalog;
  } catch (error) {
    bookingCatalog = [...defaultBookingCatalog];
  }
  renderCatalog();
}

renderCatalog();
loadBookingCatalog();
