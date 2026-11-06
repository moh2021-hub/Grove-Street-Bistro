/* ============================================================
   admin-orders.js — the kitchen queue
   Lists tickets, changes their status, and refreshes on a timer
   so new orders arrive on their own.
   ============================================================ */

(function () {
  'use strict';

  const { api, toast, esc, money } = window.App;

  const body         = document.getElementById('orders-body');
  const countsBox    = document.getElementById('status-counts');
  const filterStatus = document.getElementById('filter-status');
  const refreshNow   = document.getElementById('refresh-now');
  const autoRefresh  = document.getElementById('auto-refresh');
  const lastRefresh  = document.getElementById('last-refresh');

  if (!body) return;

  const POLL_MS  = 15000;
  const STATUSES = ['Received', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

  let timer = null;

  function renderCounts(counts) {
    countsBox.innerHTML = ['Received', 'Preparing', 'Ready', 'Completed']
      .map((status) => `
        <div class="stat">
          <p class="stat-label">${status}</p>
          <p class="stat-value mono">${counts[status] || 0}</p>
        </div>`)
      .join('');
  }

  function renderOrders(orders) {
    if (!orders.length) {
      body.innerHTML = '<tr><td colspan="6" class="muted">No tickets match that filter.</td></tr>';
      return;
    }

    body.innerHTML = orders.map((order) => {
      const lines = order.items
        .map((item) => `<div><span class="mono muted">${item.quantity}&times;</span> ${esc(item.name)}</div>`)
        .join('');

      const note = order.notes
        ? `<div class="order-note" style="margin-top:.5rem"><strong>Note</strong>${esc(order.notes)}</div>`
        : '';

      const options = STATUSES
        .map((s) => `<option value="${s}"${s === order.status ? ' selected' : ''}>${s}</option>`)
        .join('');

      return `
        <tr data-id="${order.id}">
          <td class="mono"><strong>#${order.id}</strong></td>
          <td>
            ${esc(order.customer_name)}
            <div class="muted" style="font-size:.8rem">${esc(order.customer_email)}</div>
          </td>
          <td style="font-size:.86rem">${lines}${note}</td>
          <td class="muted" style="white-space:nowrap">${esc(order.created_at)}</td>
          <td class="col-right mono">${money(order.total)}</td>
          <td>
            <select class="status-select" data-status aria-label="Status for order ${order.id}">
              ${options}
            </select>
          </td>
        </tr>`;
    }).join('');
  }

  async function load() {
    // Skip the refresh while someone has a status dropdown open
    if (document.activeElement && document.activeElement.matches('select[data-status]')) {
      return;
    }

    try {
      const data = await api('api/admin_orders.php?status=' + encodeURIComponent(filterStatus.value));
      renderOrders(data.orders);
      renderCounts(data.counts);

      lastRefresh.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
    } catch (err) {
      body.innerHTML = `<tr><td colspan="6" class="muted">${esc(err.message)}</td></tr>`;
    }
  }

  // ---- change a ticket's status ----
  body.addEventListener('change', async (event) => {
    const select = event.target.closest('select[data-status]');
    if (!select) return;

    const row      = select.closest('tr[data-id]');
    const id       = Number(row.dataset.id);
    const status   = select.value;
    const previous = select.dataset.previous || '';

    select.disabled = true;

    try {
      const result = await api('api/admin_orders.php', {
        method: 'POST',
        body: { action: 'update_status', id, status },
      });
      toast(result.message);
      await load();
    } catch (err) {
      toast(err.message, 'error');
      if (previous) select.value = previous;   // put the dropdown back
    } finally {
      select.disabled = false;
    }
  });

  // Remember the value before a change so a failed update can roll back
  body.addEventListener('focusin', (event) => {
    const select = event.target.closest('select[data-status]');
    if (select) select.dataset.previous = select.value;
  });

  // ---- controls ----
  filterStatus.addEventListener('change', load);
  refreshNow.addEventListener('click', load);

  autoRefresh.addEventListener('change', () => {
    if (autoRefresh.checked) {
      start();
    } else {
      stop();
    }
  });

  function start() {
    stop();
    timer = setInterval(load, POLL_MS);
  }

  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stop();
    } else if (autoRefresh.checked) {
      load();
      start();
    }
  });

  load();
  start();
})();
