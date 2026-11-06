/* ============================================================
   cart.js — quantity changes and removals without a page reload
   The server recalculates the money and sends it back; the page
   never does its own arithmetic on prices.
   ============================================================ */

(function () {
  'use strict';

  const { api, toast, money, setCartCount } = window.App;

  const root  = document.getElementById('cart-root');
  const table = document.getElementById('cart-table');

  if (!root || !table) return;

  /** Paint totals and line totals from a fresh cart payload. */
  function applyCart(cart) {
    setCartCount(cart.count);

    const subtotal = root.querySelector('[data-subtotal]');
    const tax      = root.querySelector('[data-tax]');
    const total    = root.querySelector('[data-total]');

    if (subtotal) subtotal.textContent = money(cart.subtotal);
    if (tax)      tax.textContent      = money(cart.tax);
    if (total)    total.textContent    = money(cart.total);

    const byId = new Map(cart.items.map((item) => [item.id, item]));

    table.querySelectorAll('tr[data-item-id]').forEach((row) => {
      const item = byId.get(Number(row.dataset.itemId));

      if (!item) {
        row.remove();
        return;
      }
      row.querySelector('[data-qty]').value = item.quantity;
      row.querySelector('[data-line-total]').textContent = money(item.line_total);
    });

    if (!cart.items.length) {
      showEmpty();
    }
  }

  function showEmpty() {
    root.dataset.empty = '1';
    root.innerHTML = `
      <div class="empty-state">
        <h3>Nothing in the cart yet</h3>
        <p>Pick something from the menu and it will show up here.</p>
        <a class="btn btn-primary" href="${window.App.url('menu.php')}">Browse the menu</a>
      </div>`;
  }

  async function send(payload, button) {
    window.App.busy(button, true, '…');
    try {
      const cart = await api('api/cart.php', { method: 'POST', body: payload });
      applyCart(cart);
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      window.App.busy(button, false);
    }
  }

  // ---- plus / minus / remove ----
  table.addEventListener('click', (event) => {
    const row = event.target.closest('tr[data-item-id]');
    if (!row) return;

    const itemId = Number(row.dataset.itemId);

    const stepButton = event.target.closest('button[data-step]');
    if (stepButton) {
      const input    = row.querySelector('[data-qty]');
      const quantity = Math.min(20, Math.max(0, Number(input.value) + Number(stepButton.dataset.step)));
      input.value    = quantity;
      send({ action: 'update', item_id: itemId, quantity }, stepButton);
      return;
    }

    const removeButton = event.target.closest('button[data-remove]');
    if (removeButton) {
      send({ action: 'remove', item_id: itemId }, removeButton);
    }
  });

  // ---- typing a quantity directly ----
  table.addEventListener('change', (event) => {
    const input = event.target.closest('[data-qty]');
    if (!input) return;

    const row      = input.closest('tr[data-item-id]');
    const quantity = Math.min(20, Math.max(0, Number(input.value) || 0));
    input.value    = quantity;

    send({ action: 'update', item_id: Number(row.dataset.itemId), quantity }, null);
  });

  // ---- empty the whole cart ----
  const clearButton = document.getElementById('clear-cart');
  if (clearButton) {
    clearButton.addEventListener('click', async () => {
      if (!window.confirm('Remove everything from your cart?')) return;

      window.App.busy(clearButton, true, 'Emptying…');
      try {
        const cart = await api('api/cart.php', { method: 'POST', body: { action: 'clear' } });
        setCartCount(cart.count);
        showEmpty();
      } catch (err) {
        toast(err.message, 'error');
        window.App.busy(clearButton, false);
      }
    });
  }
})();
