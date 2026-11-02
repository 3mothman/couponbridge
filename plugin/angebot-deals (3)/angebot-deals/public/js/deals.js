(function () {
  'use strict';

  const cfg = window.AngebotDeals || {};

  document.querySelectorAll('input[name="angebot_account_type"]').forEach(function (radio) {
    function updateAccountType() {
      radio.form.querySelectorAll('[data-account-note]').forEach(function (note) {
        note.hidden = note.dataset.accountNote !== radio.value;
        note.querySelectorAll('select[name="angebot_business_category"]').forEach(function (select) {
          select.disabled = note.hidden;
          select.required = !note.hidden;
        });
      });
    }
    radio.addEventListener('change', updateAccountType);
    if (radio.checked) updateAccountType();
  });

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.from((root || document).querySelectorAll(sel));
  }

  /* Countdown */
  function tickCountdowns() {
    qsa('.angebot-countdown[data-expires]').forEach(function (el) {
      const end = new Date(el.getAttribute('data-expires')).getTime();
      const now = Date.now();
      const span = qs('.angebot-countdown__time', el);
      if (!span) return;
      const diff = end - now;
      if (diff <= 0) {
        span.textContent = 'expired';
        return;
      }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      span.textContent = d > 0 ? d + 'd ' + h + 'h' : h + 'h ' + m + 'm';
    });
  }
  tickCountdowns();
  setInterval(tickCountdowns, 30000);

  /* Location picker */
  const picker = qs('.angebot-location-picker');
  if (picker) {
    const trigger = qs('.angebot-location-trigger', picker);
    const dropdown = qs('.angebot-location-dropdown', picker);
    const search = qs('.angebot-location-search', picker);

    trigger?.addEventListener('click', function () {
      const open = dropdown.hasAttribute('hidden');
      if (open) dropdown.removeAttribute('hidden');
      else dropdown.setAttribute('hidden', '');
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!picker.contains(e.target)) {
        dropdown?.setAttribute('hidden', '');
        trigger?.setAttribute('aria-expanded', 'false');
      }
    });

    search?.addEventListener('input', function () {
      const q = search.value.toLowerCase();
      qsa('li', dropdown).forEach(function (li) {
        const text = li.textContent.toLowerCase();
        li.hidden = q && !text.includes(q);
      });
    });

    qsa('[data-location]', picker).forEach(function (btn) {
      btn.addEventListener('click', async function () {
        const location = btn.getAttribute('data-location');
        const body = new URLSearchParams({
          action: 'angebot_set_location',
          nonce: cfg.nonce,
          location: location,
        });
        await fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' });
        const label = qs('.angebot-loc-label', picker);
        if (label) label.textContent = btn.textContent.trim();
        dropdown.setAttribute('hidden', '');
        // Reload grid / page so deals match city.
        window.location.reload();
      });
    });
  }

  /* AJAX filters */
  const filterForm = qs('#angebot-filters');
  const grid = qs('#angebot-deals-grid');

  async function runFilter(form) {
    if (!grid || !cfg.ajaxUrl) return;
    const fd = new FormData(form);
    const body = new URLSearchParams({
      action: 'angebot_filter_deals',
      nonce: cfg.nonce,
      category: fd.get('deal_category') || '',
      location: fd.get('deal_location') || '',
      min_price: fd.get('min_price') || '',
      max_price: fd.get('max_price') || '',
      orderby: fd.get('orderby') || 'date',
      columns: grid.getAttribute('data-columns') || '3',
      limit: '24',
    });
    grid.style.opacity = '0.5';
    try {
      const res = await fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' });
      const json = await res.json();
      if (json.success && json.data.html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = json.data.html;
        const newGrid = tmp.querySelector('#angebot-deals-grid') || tmp.querySelector('.angebot-deals-grid');
        if (newGrid) {
          grid.innerHTML = newGrid.innerHTML;
          tickCountdowns();
        }
      }
    } catch (e) {
      console.error(e);
    } finally {
      grid.style.opacity = '1';
    }
  }

  filterForm?.addEventListener('submit', function (e) {
    if (grid) {
      e.preventDefault();
      runFilter(filterForm);
    }
  });

  /* Reviews */
  const reviewForm = qs('#angebot-review-form');
  reviewForm?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(reviewForm);
    fd.append('action', 'angebot_submit_review');
    fd.append('nonce', cfg.nonce);
    const msg = qs('.angebot-review-form__msg', reviewForm);
    try {
      const res = await fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
      const json = await res.json();
      if (msg) {
        msg.hidden = false;
        msg.textContent = json.data?.message || (json.success ? 'OK' : cfg.i18n?.error);
      }
      if (json.success) reviewForm.reset();
    } catch (err) {
      if (msg) {
        msg.hidden = false;
        msg.textContent = cfg.i18n?.error || 'Error';
      }
    }
  });

  /* Frontend merchant portal */
  const merchantForm = qs('#angebot-merchant-lookup');
  merchantForm?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const code = new FormData(merchantForm).get('code');
    const result = qs('#angebot-merchant-result');
    const body = new URLSearchParams({
      action: 'angebot_lookup_voucher',
      nonce: cfg.merchantNonce || cfg.nonce,
      code: String(code || ''),
    });
    const res = await fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' });
    const json = await res.json();
    if (!result) return;
    result.hidden = false;
    if (!json.success) {
      result.innerHTML = '<p>' + (json.data?.message || 'Error') + '</p>';
      return;
    }
    const d = json.data;
    result.innerHTML =
      '<p><strong>' + d.deal_title + '</strong></p>' +
      '<p>Code: <code>' + d.code + '</code></p>' +
      '<p>Status: ' + d.status_label + '</p>' +
      '<p>Customer: ' + d.customer + '</p>' +
      (d.expires_at ? '<p>Expires: ' + d.expires_at + '</p>' : '') +
      (d.can_redeem
        ? '<button type="button" class="angebot-btn angebot-btn--primary" data-redeem="' + d.id + '">Redeem</button>'
        : '');

    qs('[data-redeem]', result)?.addEventListener('click', async function () {
      const redeemBody = new URLSearchParams({
        action: 'angebot_redeem_voucher',
        nonce: cfg.merchantNonce || cfg.nonce,
        voucher_id: this.getAttribute('data-redeem'),
      });
      const r2 = await fetch(cfg.ajaxUrl, { method: 'POST', body: redeemBody, credentials: 'same-origin' });
      const j2 = await r2.json();
      result.innerHTML = '<p>' + (j2.data?.message || '') + '</p>';
    });
  });
})();
