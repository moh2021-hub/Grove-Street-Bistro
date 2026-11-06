/* ============================================================
   app.js — shared front-end helpers
   Loaded on every page. Everything else builds on window.App.
   ============================================================ */

window.App = (function () {
  'use strict';

  const meta = (name) => {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.getAttribute('content') : '';
  };

  const BASE_URL = meta('base-url');
  const CSRF     = meta('csrf-token');

  /** Build an absolute path that respects the folder the app is installed in. */
  function url(path) {
    return BASE_URL + '/' + String(path).replace(/^\//, '');
  }

  /**
   * Thin wrapper around fetch for the JSON API.
   * Sends the CSRF token on every write, and turns a failed response into a
   * thrown Error so callers can just use try/catch.
   */
  async function api(path, { method = 'GET', body = null, formData = null } = {}) {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    let payload;

    if (formData) {
      payload = formData;                       // browser sets the multipart boundary
      headers['X-CSRF-Token'] = CSRF;
    } else if (body) {
      payload = JSON.stringify(body);
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = CSRF;
    }

    const response = await fetch(url(path), {
      method,
      headers,
      body: payload,
      credentials: 'same-origin',
    });

    if (response.status === 401) {
      window.location.href = url('login.php');
      throw new Error('Your session expired. Sign in again.');
    }

    let data;
    try {
      data = await response.json();
    } catch (err) {
      throw new Error('The server sent something the page could not read. Reload and try again.');
    }

    if (!response.ok || data.ok === false) {
      const error = new Error(data.error || 'That did not work. Try again.');
      error.status = response.status;
      if (data.fields) error.fields = data.fields;   // 422 validation detail
      throw error;
    }
    return data.data !== undefined ? data.data : data;
  }

  /** Small message in the bottom corner. Announced to screen readers. */
  function toast(message, type = 'success') {
    const stack = document.getElementById('toast-stack');
    if (!stack) return;

    const el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' toast-error' : '');
    el.textContent = message;
    stack.appendChild(el);

    setTimeout(() => {
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 200);
    }, 3200);
  }

  /** Keep the header cart count in step with the server. */
  function setCartCount(count) {
    document.querySelectorAll('[data-cart-badge]').forEach((badge) => {
      badge.textContent = count;
      badge.classList.toggle('is-empty', Number(count) === 0);
    });
  }

  const money = (value) => '$' + Number(value).toFixed(2);

  /** Escape untrusted text before putting it into innerHTML. */
  function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  /** Disable a button while its request is in flight. */
  function busy(button, isBusy, busyLabel = 'Working…') {
    if (!button) return;
    if (isBusy) {
      button.dataset.label = button.textContent;
      button.textContent = busyLabel;
      button.disabled = true;
    } else {
      if (button.dataset.label) button.textContent = button.dataset.label;
      button.disabled = false;
    }
  }

  // ---- mobile navigation ----
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.nav-toggle');
    const nav    = document.getElementById('site-nav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  });

  return { url, api, toast, setCartCount, money, esc, busy, BASE_URL };
})();
