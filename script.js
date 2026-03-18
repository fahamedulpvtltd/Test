/* ============================================
   EID SALAMI COLLECTION — script.js
   ============================================ */

const GOAL = 10000;
const DATA_URL = 'data.json';

/* ── Star Field ── */
(function generateStars() {
  const container = document.getElementById('stars');
  if (!container) return;
  const count = window.innerWidth < 600 ? 60 : 120;
  for (let i = 0; i < count; i++) {
    const s = document.createElement('div');
    s.className = 'star';
    const size = Math.random() * 2.5 + 0.5;
    s.style.cssText = `
      left: ${Math.random() * 100}%;
      top: ${Math.random() * 100}%;
      width: ${size}px;
      height: ${size}px;
      --dur: ${(Math.random() * 4 + 2).toFixed(1)}s;
      --delay: ${(Math.random() * 5).toFixed(1)}s;
    `;
    container.appendChild(s);
  }
})();

/* ── Data Fetching ── */
async function fetchData() {
  try {
    const res = await fetch(DATA_URL + '?_=' + Date.now());
    if (!res.ok) return [];
    const data = await res.json();
    return Array.isArray(data) ? data : [];
  } catch (e) {
    console.warn('Could not load data.json:', e);
    return [];
  }
}

/* ── Render Dashboard ── */
async function renderDashboard() {
  const all = await fetchData();
  const approved = all.filter(e => e.status === 'approved');

  // Total & Progress
  const total = approved.reduce((s, e) => s + parseFloat(e.amount || 0), 0);
  const pct   = Math.min((total / GOAL) * 100, 100);

  const totalEl = document.getElementById('totalAmount');
  const barEl   = document.getElementById('progressBar');
  const pctEl   = document.getElementById('progressPercent');

  if (totalEl) animateCount(totalEl, total);
  if (barEl)   setTimeout(() => barEl.style.width = pct.toFixed(1) + '%', 100);
  if (pctEl)   pctEl.textContent = pct.toFixed(1) + '% reached';

  renderLeaderboard(approved);
  renderMessageWall(approved);
  checkClosed(all);
}

/* ── Animated Counter ── */
function animateCount(el, target) {
  const start    = 0;
  const duration = 1400;
  const startT   = performance.now();
  function step(now) {
    const t = Math.min((now - startT) / duration, 1);
    const ease = 1 - Math.pow(1 - t, 3);
    el.textContent = '৳' + Math.floor(ease * target).toLocaleString();
    if (t < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

/* ── Leaderboard ── */
function renderLeaderboard(approved) {
  const el = document.getElementById('leaderboard');
  if (!el) return;

  // Aggregate by name
  const totals = {};
  approved.forEach(e => {
    const name = e.name || 'Anonymous';
    totals[name] = (totals[name] || 0) + parseFloat(e.amount || 0);
  });

  const sorted = Object.entries(totals)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5);

  if (!sorted.length) {
    el.innerHTML = '<div class="loading-placeholder" style="opacity:0.5">No approved submissions yet 🌙</div>';
    return;
  }

  const medals = ['🥇', '🥈', '🥉'];
  el.innerHTML = sorted.map(([name, amt], i) => `
    <div class="lb-item">
      <div class="lb-rank ${i < 3 ? 'lb-rank-' + (i + 1) : 'lb-rank-other'}">
        ${medals[i] || '#' + (i + 1)}
      </div>
      <div class="lb-name">${escapeHtml(name)}</div>
      <div class="lb-amount">৳${amt.toLocaleString()}</div>
    </div>
  `).join('');
}

/* ── Message Wall ── */
function renderMessageWall(approved) {
  const el = document.getElementById('messageWall');
  if (!el) return;

  const withMsg = approved
    .filter(e => e.message && e.message.trim())
    .slice(-10)
    .reverse();

  if (!withMsg.length) {
    el.innerHTML = '<div class="loading-placeholder" style="opacity:0.5">No messages yet — be the first! 🌙</div>';
    return;
  }

  el.innerHTML = withMsg.map((e, i) => `
    <div class="msg-item" style="--i:${i + 1}">
      <div class="msg-author">— ${escapeHtml(e.name || 'Anonymous')} · ${formatDate(e.timestamp)}</div>
      <div class="msg-text">"${escapeHtml(e.message)}"</div>
    </div>
  `).join('');
}

/* ── Check Collection Closed ── */
function checkClosed(all) {
  const banner = document.getElementById('closedBanner');
  if (!banner) return;
  const meta = all.find(e => e._meta);
  if (meta && meta._meta.closed) {
    banner.style.display = 'block';
    document.getElementById('openModalBtn').disabled = true;
    document.getElementById('openModalBtn').style.opacity = '0.5';
    document.getElementById('openModalBtn').title = 'Collection closed';
  }
}

/* ── Modal ── */
function openModal() {
  const overlay = document.getElementById('modalOverlay');
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  const overlay = document.getElementById('modalOverlay');
  overlay.classList.remove('open');
  document.body.style.overflow = '';
}

function closeModalOnBg(e) {
  if (e.target === document.getElementById('modalOverlay')) closeModal();
}

/* ── Image Preview ── */
function previewImage(e) {
  const file = e.target.files[0];
  if (!file) return;

  // Validate type
  if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
    showError('Only JPG, JPEG, PNG files are allowed.');
    e.target.value = '';
    return;
  }
  // Validate size (3MB)
  if (file.size > 3 * 1024 * 1024) {
    showError('File too large. Max size is 3MB.');
    e.target.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = ev => {
    document.getElementById('imgPreview').src = ev.target.result;
    document.getElementById('imgPreviewWrap').style.display = 'block';
    document.getElementById('fileDrop').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function removeImage() {
  document.getElementById('screenshot').value = '';
  document.getElementById('imgPreview').src = '';
  document.getElementById('imgPreviewWrap').style.display = 'none';
  document.getElementById('fileDrop').style.display = 'block';
}

/* ── Form Submit ── */
let lastSubmit = 0;

async function submitForm(e) {
  e.preventDefault();
  hideError();

  // Spam protection: 10s cooldown
  const now = Date.now();
  if (now - lastSubmit < 10000) {
    showError('Please wait a moment before submitting again.');
    return;
  }

  const name       = document.getElementById('name').value.trim();
  const amount     = document.getElementById('amount').value.trim();
  const method     = document.getElementById('method').value;
  const message    = document.getElementById('message').value.trim();
  const screenshot = document.getElementById('screenshot').files[0];

  // Validation
  if (!name)       return showError('Please enter your name.');
  if (!amount || isNaN(amount) || Number(amount) < 1) return showError('Please enter a valid amount.');
  if (!method)     return showError('Please select a payment method.');
  if (!screenshot) return showError('Please upload your payment screenshot.');

  // Show loading
  const btn     = document.getElementById('submitBtn');
  const btnText = document.getElementById('submitBtnText');
  const spinner = document.getElementById('btnSpinner');
  btn.disabled  = true;
  btnText.style.display = 'none';
  spinner.style.display = 'block';

  const formData = new FormData();
  formData.append('name',       name);
  formData.append('amount',     amount);
  formData.append('method',     method);
  formData.append('message',    message);
  formData.append('screenshot', screenshot);

  try {
    const res  = await fetch('upload.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
      lastSubmit = Date.now();
      document.getElementById('salamiForm').style.display  = 'none';
      document.getElementById('successState').style.display = 'block';
    } else {
      showError(data.error || 'Something went wrong. Please try again.');
      btn.disabled = false;
      btnText.style.display = 'block';
      spinner.style.display = 'none';
    }
  } catch (err) {
    showError('Network error. Make sure you are running on a PHP server.');
    btn.disabled = false;
    btnText.style.display = 'block';
    spinner.style.display = 'none';
  }
}

function resetAndClose() {
  document.getElementById('salamiForm').reset();
  document.getElementById('salamiForm').style.display = 'block';
  document.getElementById('successState').style.display = 'none';
  document.getElementById('imgPreviewWrap').style.display = 'none';
  document.getElementById('fileDrop').style.display = 'block';
  document.getElementById('submitBtn').disabled = false;
  document.getElementById('submitBtnText').style.display = 'block';
  document.getElementById('btnSpinner').style.display = 'none';
  hideError();
  closeModal();
  renderDashboard();
}

/* ── Helpers ── */
function showError(msg) {
  const el = document.getElementById('formError');
  if (!el) return;
  el.textContent = msg;
  el.style.display = 'block';
}
function hideError() {
  const el = document.getElementById('formError');
  if (el) el.style.display = 'none';
}

function escapeHtml(str) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  return String(str).replace(/[&<>"']/g, c => map[c]);
}

function formatDate(ts) {
  if (!ts) return '';
  try {
    return new Date(ts).toLocaleDateString('en-BD', { day: 'numeric', month: 'short' });
  } catch { return ''; }
}

function copyNumber(num, btn) {
  navigator.clipboard.writeText(num).then(() => {
    btn.textContent = 'Copied!';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
  });
}

/* ── Init ── */
renderDashboard();
// Refresh every 45s
setInterval(renderDashboard, 45000);
