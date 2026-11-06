/* ============================================================
   orders.js — keep the customer's order status current
   Polls the status endpoint and edits the pill and progress rail
   in place, so the page does not flicker or lose scroll position.
   ============================================================ */

(function () {
  'use strict';

  const { api } = window.App;

  const root = document.getElementById('orders-root');
  if (!root) return;

  const POLL_MS = 10000;
  const FLOW    = ['Received', 'Preparing', 'Ready', 'Completed'];

  let timer = null;

  function updateCard(card, order) {
    if (card.dataset.status === order.status) return;

    card.dataset.status = order.status;

    const pill = card.querySelector('[data-status-pill]');
    if (pill) {
      pill.textContent = order.status;
      pill.className   = 'status-pill status-' + order.status;
    }

    const tracker = card.querySelector('[data-tracker]');

    // A cancelled order has no progress to show
    if (order.status === 'Cancelled') {
      if (tracker) tracker.remove();
      return;
    }
    if (!tracker) return;

    const current = FLOW.indexOf(order.status);

    tracker.querySelectorAll('[data-step]').forEach((step) => {
      const index = Number(step.dataset.step);
      step.classList.toggle('is-done', index <= current);
      step.classList.toggle('is-current', index === current);
    });
  }

  async function poll() {
    try {
      const data   = await api('api/order_status.php');
      const orders = data.orders || [];

      const known = new Set(
        [...root.querySelectorAll('[data-order-id]')].map((el) => Number(el.dataset.orderId))
      );

      // A brand new order needs markup this script does not build, so let the
      // server render it.
      if (orders.some((order) => !known.has(order.id))) {
        window.location.reload();
        return;
      }

      orders.forEach((order) => {
        const card = root.querySelector(`[data-order-id="${order.id}"]`);
        if (card) updateCard(card, order);
      });

    } catch (err) {
      // A failed poll is not worth interrupting anyone over; try again later.
      console.warn('Status poll failed:', err.message);
    }
  }

  function start() {
    stop();
    timer = setInterval(poll, POLL_MS);
  }

  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  // Stop polling while the tab is hidden, catch up as soon as it is visible
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stop();
    } else {
      poll();
      start();
    }
  });

  start();
})();
