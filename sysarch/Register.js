// ── PASSWORD TOGGLE ──
function togglePassword(fieldId, btnId) {
  const input = document.getElementById(fieldId);
  const icon  = document.getElementById(btnId);
  const show  = input.type === 'password';
  input.type  = show ? 'text' : 'password';

  icon.innerHTML = show
    ? `<path stroke-linecap="round" stroke-linejoin="round"
        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7
           a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243
           M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29
           m7.532 7.532l3.29 3.29M3 3l3.59 3.59
           m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7
           a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`
    : `<path stroke-linecap="round" stroke-linejoin="round"
        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
       <path stroke-linecap="round" stroke-linejoin="round"
        d="M2.458 12C3.732 7.943 7.523 5 12 5
           c4.478 0 8.268 2.943 9.542 7
           -1.274 4.057-5.064 7-9.542 7
           -4.477 0-8.268-2.943-9.542-7z"/>`;
}

// ── PASSWORD STRENGTH METER ──
function checkStrength(value) {
  const segs  = document.querySelectorAll('.strength-seg');
  const label = document.getElementById('strength-label');

  segs.forEach(s => s.className = 'strength-seg');

  if (!value) { label.textContent = ''; return; }

  let score = 0;
  if (value.length >= 8)             score++;
  if (/[A-Z]/.test(value))           score++;
  if (/[0-9]/.test(value))           score++;
  if (/[^A-Za-z0-9]/.test(value))   score++;

  const levels = ['', 'weak', 'fair', 'good', 'strong'];
  const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

  for (let i = 0; i < score; i++) {
    segs[i].classList.add(levels[score]);
  }

  label.textContent = score > 0 ? `Strength: ${labels[score]}` : '';
}

// ── CLIENT-SIDE VALIDATION ──
document.getElementById('register-form').addEventListener('submit', function (e) {
  const pw  = document.getElementById('password').value;
  const pw2 = document.getElementById('repeat_password').value;
  const alertBox = document.getElementById('form-alert');

  if (pw !== pw2) {
    e.preventDefault();
    alertBox.className = 'alert alert-error';
    alertBox.innerHTML = '<span class="alert-icon">⚠</span><span>Passwords do not match. Please try again.</span>';
    alertBox.style.display = 'flex';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
});