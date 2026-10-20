const loginForm = document.querySelector("#login-form");
const loginButton = document.querySelector("#login-submit");
const loginToast = document.querySelector("#auth-toast");
let loginToastTimer;

function showAuthToast(message) {
  loginToast.textContent = message;
  loginToast.classList.add("is-visible");
  window.clearTimeout(loginToastTimer);
  loginToastTimer = window.setTimeout(() => loginToast.classList.remove("is-visible"), 4200);
}

function loginDestination() {
  const allowedPages = new Set(["index.html", "reservation-list.html", "booking-services.html", "booking-datetime.html"]);
  const requested = new URLSearchParams(window.location.search).get("next") || "index.html";
  return allowedPages.has(requested) ? requested : "index.html";
}

document.querySelector("#register-link").href = `register.html?next=${encodeURIComponent(loginDestination())}`;

document.querySelector(".password-toggle").addEventListener("click", event => {
  const button = event.currentTarget;
  const password = document.querySelector("#login-password");
  const show = password.type === "password";
  password.type = show ? "text" : "password";
  button.setAttribute("aria-pressed", String(show));
  button.setAttribute("aria-label", show ? "Sembunyikan kata sandi" : "Tampilkan kata sandi");
});

document.querySelector("#forgot-password").addEventListener("click", () => {
  showAuthToast("Untuk mengatur ulang kata sandi, silakan hubungi Bantuan Layanan Pemon Barbershop.");
});

document.querySelector("#google-login").addEventListener("click", () => {
  showAuthToast("Masuk dengan Google belum dihubungkan. Silakan gunakan email atau nomor WhatsApp.");
});

document.querySelectorAll("[data-info]").forEach(link => {
  link.addEventListener("click", event => {
    event.preventDefault();
    showAuthToast(link.dataset.info);
  });
});

loginForm.addEventListener("submit", async event => {
  event.preventDefault();
  const identifier = document.querySelector("#login-identifier").value.trim();
  const password = document.querySelector("#login-password").value;
  if (!identifier || !password) {
    showAuthToast("Masukkan email atau nomor WhatsApp dan kata sandi.");
    return;
  }

  loginButton.disabled = true;
  loginButton.querySelector("span").textContent = "Memeriksa akun...";
  try {
    const response = await fetch("./login.php", {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({
        identifier,
        password,
        remember: document.querySelector("#remember-me").checked,
        next: loginDestination()
      })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      showAuthToast(payload.error || "Email/nomor WhatsApp atau kata sandi tidak cocok.");
      return;
    }
    showAuthToast(`Selamat datang, ${payload.customer_name || "pelanggan"}.`);
    window.setTimeout(() => { window.location.href = payload.redirect || loginDestination(); }, 450);
  } catch (error) {
    showAuthToast("Server belum terhubung. Buka halaman ini melalui server PHP dan MySQL.");
  } finally {
    loginButton.disabled = false;
    loginButton.querySelector("span").textContent = "Masuk";
  }
});

fetch("./login.php?status=1", { headers: { Accept: "application/json" } })
  .then(response => response.ok ? response.json() : null)
  .then(payload => {
    if (payload?.authenticated) window.location.replace(loginDestination());
  })
  .catch(() => {});

if (new URLSearchParams(window.location.search).get("registered") === "1") {
  showAuthToast("Akun berhasil dibuat. Silakan masuk.");
}
