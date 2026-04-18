/* ============================================================
   ReservInn — script.js
   UI JavaScript: tabs, rating, confirm, alerts, date validation,
   date calendar highlighting, image preview, sidebar nav
   ============================================================ */
'use strict';

/* ── Tab Navigation ─────────────────────────────────────────── */
function initTabs() {
  document.querySelectorAll('[data-tab-nav]').forEach(nav => {
    const group = nav.dataset.tabNav;
    const items = nav.querySelectorAll('.tab-nav__item');
    items.forEach(item => {
      item.addEventListener('click', () => {
        const target = item.dataset.tab;
        items.forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        document.querySelectorAll(`[data-tab-panel="${group}"]`).forEach(panel => {
          panel.classList.toggle('active', panel.dataset.panel === target);
        });
      });
    });
  });
}

/* ── Star Rating ─────────────────────────────────────────────── */
function initStarRating() {
  document.querySelectorAll('.rating-stars').forEach(container => {
    const input = document.getElementById(container.dataset.input);
    const stars = container.querySelectorAll('.rating-stars__star');
    if (!input || !stars.length) return;
    stars.forEach(star => {
      star.addEventListener('mouseenter', () => {
        const val = +star.dataset.value;
        stars.forEach(s => s.classList.toggle('lit', +s.dataset.value <= val));
      });
      star.addEventListener('mouseleave', () => {
        const val = +input.value || 0;
        stars.forEach(s => s.classList.toggle('lit', +s.dataset.value <= val));
      });
      star.addEventListener('click', () => {
        input.value = star.dataset.value;
        stars.forEach(s => s.classList.toggle('lit', +s.dataset.value <= +star.dataset.value));
      });
    });
  });
}

/* ── Confirm Dialogs ─────────────────────────────────────────── */
function initConfirm() {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
}

/* ── Auto-Dismiss Alerts ─────────────────────────────────────── */
function initAlerts() {
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    const delay = +alert.dataset.autoDismiss || 4000;
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, delay);
  });
}

/* ── Sidebar Dashboard Nav ───────────────────────────────────── */
function initSidebarNav() {
  document.querySelectorAll('[data-dash-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.dashTab;
      document.querySelectorAll('[data-dash-tab]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('[data-dash-panel]').forEach(p => {
        const match = p.dataset.dashPanel === target;
        p.classList.toggle('active', match);
        p.style.display = match ? 'block' : 'none';
      });
    });
  });
}

/* ── Image Upload Preview ────────────────────────────────────── */
function initImageUpload() {
  const fileInput = document.getElementById('resort_image');
  if (!fileInput) return;
  const previewWrap = document.getElementById('image-preview-wrap');
  const previewImg  = document.getElementById('image-preview-img');
  const removeBtn   = document.getElementById('image-remove-btn');

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      alert('Image must be under 5MB.'); fileInput.value = ''; return;
    }
    const allowed = ['image/jpeg','image/png','image/webp'];
    if (!allowed.includes(file.type)) {
      alert('Only JPG, PNG, and WEBP images are allowed.'); fileInput.value = ''; return;
    }
    const reader = new FileReader();
    reader.onload = e => {
      previewImg.src = e.target.result;
      previewWrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });

  if (removeBtn) {
    removeBtn.addEventListener('click', () => {
      fileInput.value = '';
      previewWrap.style.display = 'none';
      previewImg.src = '';
    });
  }
}

/* ── Admin User Search / Filter ──────────────────────────────── */
function initUserFilter() {
  const searchInput = document.getElementById('user-search');
  const roleFilter  = document.getElementById('role-filter');
  const sortSelect  = document.getElementById('sort-users');
  if (!searchInput) return;

  function filterUsers() {
    const q    = searchInput.value.toLowerCase();
    const role = roleFilter ? roleFilter.value : 'all';

    document.querySelectorAll('.user-row').forEach(row => {
      const name  = (row.dataset.name  || '').toLowerCase();
      const email = (row.dataset.email || '').toLowerCase();
      const r     = row.dataset.role   || '';
      const matchQ    = !q || name.includes(q) || email.includes(q);
      const matchRole = role === 'all' || r === role;
      row.style.display = matchQ && matchRole ? '' : 'none';
    });
  }

  searchInput.addEventListener('input', filterUsers);
  if (roleFilter)  roleFilter.addEventListener('change', filterUsers);
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      ['owners-list','customers-list'].forEach(id => {
        const list = document.getElementById(id);
        if (!list) return;
        const rows = Array.from(list.querySelectorAll('.user-row'));
        rows.sort((a, b) => {
          if (sortSelect.value === 'name')
            return (a.dataset.name||'').localeCompare(b.dataset.name||'');
          return (b.dataset.joined||'').localeCompare(a.dataset.joined||'');
        });
        rows.forEach(r => list.appendChild(r));
      });
    });
  }
}

/* ── Booking Date Picker with Availability Calendar ──────────── */
function initBookingCalendar() {
  const checkIn  = document.getElementById('check_in_date');
  const checkOut = document.getElementById('check_out_date');
  if (!checkIn || !checkOut) return;

  const today = new Date().toISOString().split('T')[0];
  checkIn.min = today;

  // Blocked/booked dates injected by PHP as JSON in window.unavailableDates
  // Format: [{start:'YYYY-MM-DD', end:'YYYY-MM-DD', type:'blocked'|'booked'}]
  const unavailable = window.unavailableDates || [];

  function parseDateStr(s) { return new Date(s + 'T00:00:00'); }

  function isDateUnavailable(dateStr) {
    const d = parseDateStr(dateStr);
    for (const range of unavailable) {
      const s = parseDateStr(range.start);
      const e = parseDateStr(range.end);
      if (d >= s && d <= e) return true;
    }
    return false;
  }

  function rangeHasUnavailable(startStr, endStr) {
    const s = parseDateStr(startStr);
    const e = parseDateStr(endStr);
    const cur = new Date(s);
    while (cur <= e) {
      const ds = cur.toISOString().split('T')[0];
      if (isDateUnavailable(ds)) return true;
      cur.setDate(cur.getDate() + 1);
    }
    return false;
  }

  // Update check-out min and validate range
  checkIn.addEventListener('change', () => {
    if (!checkIn.value) return;
    const nextDay = new Date(checkIn.value);
    nextDay.setDate(nextDay.getDate() + 1);
    checkOut.min = nextDay.toISOString().split('T')[0];
    if (checkOut.value && checkOut.value <= checkIn.value) checkOut.value = '';
    validateRange();
    updateNightsPreview();
    updateCalendarHints();
  });

  checkOut.addEventListener('change', () => {
    validateRange();
    updateNightsPreview();
  });

  function validateRange() {
    const rangeError = document.getElementById('date-range-error');
    if (!rangeError) return;
    if (checkIn.value && checkOut.value) {
      if (rangeHasUnavailable(checkIn.value, checkOut.value)) {
        rangeError.style.display = 'flex';
        document.querySelector('[data-booking-submit]')?.setAttribute('disabled','disabled');
      } else {
        rangeError.style.display = 'none';
        document.querySelector('[data-booking-submit]')?.removeAttribute('disabled');
      }
    } else {
      rangeError.style.display = 'none';
      document.querySelector('[data-booking-submit]')?.removeAttribute('disabled');
    }
  }

  function updateNightsPreview() {
    const preview = document.getElementById('nights-preview');
    const priceEl = document.getElementById('price-preview');
    const calcEl  = document.getElementById('price-calc');
    const summaryNights = document.getElementById('summary-nights');
    const summaryPrice  = document.getElementById('summary-price');

    if (!checkIn.value || !checkOut.value) return;
    const nights = Math.round((new Date(checkOut.value) - new Date(checkIn.value)) / 86400000);
    if (nights <= 0) return;

    const rate = parseFloat(document.getElementById('price-per-night')?.value || 0);
    const total = nights * rate;
    const fmt = '\u20B1' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (preview) preview.textContent = nights + (nights === 1 ? ' night' : ' nights');
    if (priceEl && rate) priceEl.textContent = fmt;
    if (calcEl) calcEl.style.display = 'block';
    if (summaryNights) summaryNights.textContent = nights + (nights === 1 ? ' night' : ' nights');
    if (summaryPrice)  {
      summaryPrice.textContent = fmt;
      summaryPrice.style.fontFamily = "var(--font-display)";
      summaryPrice.style.fontSize   = "2rem";
    }
  }

  // Visual hints below date inputs
  function updateCalendarHints() {
    const hint = document.getElementById('checkin-hint');
    if (!hint || !checkIn.value) return;
    if (isDateUnavailable(checkIn.value)) {
      hint.textContent = '⚠ This date is unavailable';
      hint.style.color = '#dc3545';
    } else {
      hint.textContent = '✓ Available';
      hint.style.color = '#28a745';
    }
  }
}

/* ── Init All ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initStarRating();
  initConfirm();
  initAlerts();
  initSidebarNav();
  initImageUpload();
  initUserFilter();
  initBookingCalendar();
});

/* ── Admin Stats Hide on Scroll ──────────────────────────────── */
function initAdminStatsScroll() {
  const statsGrid = document.querySelector('.dash-main .stats-grid');
  if (!statsGrid) return;
  let hidden = false;
  window.addEventListener('scroll', () => {
    const scrollY = window.scrollY || document.documentElement.scrollTop;
    if (scrollY > 80 && !hidden) {
      statsGrid.classList.add('stats--hidden');
      hidden = true;
    } else if (scrollY <= 40 && hidden) {
      statsGrid.classList.remove('stats--hidden');
      hidden = false;
    }
  }, { passive: true });
}

document.addEventListener('DOMContentLoaded', () => {
  initAdminStatsScroll();
});
