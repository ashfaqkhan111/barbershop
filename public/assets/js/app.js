// Menu mobile
var b = document.querySelector('.burger'), m = document.querySelector('.menu');
if (b && m) {
  b.addEventListener('click', function () {
    var o = m.classList.toggle('open');
    b.setAttribute('aria-expanded', o);
  });
  m.addEventListener('click', function (e) { if (e.target.tagName === 'A') m.classList.remove('open'); });
}
// Tampil/sembunyikan kata sandi
document.querySelectorAll('.toggle').forEach(function (t) {
  t.addEventListener('click', function () {
    var i = document.getElementById(t.dataset.target), show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    t.textContent = show ? '🙈' : '👁';
  });
});
// Validasi sisi klien (server tetap memvalidasi ulang)
var r = document.getElementById('registerForm');
if (r) r.addEventListener('submit', function (e) {
  if (r.pw.value !== r.pw2.value) { e.preventDefault(); alert('Konfirmasi kata sandi tidak cocok.'); }
});
