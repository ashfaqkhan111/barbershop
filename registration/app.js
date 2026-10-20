const registerForm = document.querySelector("#register-form");
const registerButton = document.querySelector("#register-submit");
const registerToast = document.querySelector("#auth-toast");
let registerToastTimer;

function showRegisterToast(message) {
  registerToast.textContent = message;
  registerToast.classList.add("is-visible");
  window.clearTimeout(registerToastTimer);
  registerToastTimer = window.setTimeout(() => registerToast.classList.remove("is-visible"), 4200);
}

function registerDestination() {
  const allowed = new Set(["index.html", "reservation-list.html", "booking-services.html", "booking-datetime.html"]);
  const requested = new URLSearchParams(window.location.search).get("next") || "index.html";
  return allowed.has(requested) ? requested : "index.html";
}

document.querySelector("#login-link").href = `login.html?next=${encodeURIComponent(registerDestination())}`;
document.querySelectorAll("[data-info]").forEach(link => {
  link.addEventListener("click", event => {
    event.preventDefault();
    showRegisterToast(link.dataset.info);
  });
});

registerForm.addEventListener("submit", async event => {
  event.preventDefault();
  const name = document.querySelector("#register-name").value.trim();
  const identifier = document.querySelector("#register-identifier").value.trim();
  const password = document.querySelector("#register-password").value;
  const confirmPassword = document.querySelector("#register-confirm").value;
  if (!name || !identifier || !password || !confirmPassword) {
    showRegisterToast("Lengkapi semua kolom terlebih dahulu.");
    return;
  }
  if (password.length < 8) {
    showRegisterToast("Kata sandi harus memiliki minimal 8 karakter.");
    return;
  }
  if (password !== confirmPassword) {
    showRegisterToast("Kata sandi dan ulangannya belum sama.");
    return;
  }
  if (!document.querySelector("#accept-terms").checked) {
    showRegisterToast("Setujui Syarat & Ketentuan untuk membuat akun.");
    return;
  }

  registerButton.disabled = true;
  registerButton.querySelector("span").textContent = "Membuat akun...";
  try {
    const response = await fetch("./register.php", {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ name, identifier, password, confirm_password: confirmPassword, terms_accepted: true, next: registerDestination() })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      showRegisterToast(payload.error || "Akun belum dapat dibuat.");
      return;
    }
    showRegisterToast("Akun berhasil dibuat. Mengalihkan...");
    window.setTimeout(() => { window.location.href = payload.redirect || registerDestination(); }, 450);
  } catch (error) {
    showRegisterToast("Server belum terhubung. Buka halaman ini melalui server PHP dan MySQL.");
  } finally {
    registerButton.disabled = false;
    registerButton.querySelector("span").textContent = "Buat Akun";
  }
});
