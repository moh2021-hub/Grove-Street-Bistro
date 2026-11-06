/* ============================================================
   checkout.js — note counter and the order submit
   ============================================================ */

(function () {
  'use strict';

  const { api, toast, setCartCount, url } = window.App;

  const notes  = document.getElementById('order-notes');
  const count  = document.getElementById('notes-count');
  const submit = document.getElementById('place-order');

  if (notes && count) {
    const update = () => { count.textContent = String(notes.value.length); };
    notes.addEventListener('input', update);
    update();
  }

  if (!submit) return;

  submit.addEventListener('click', async () => {
    window.App.busy(submit, true, 'Sending to the kitchen…');

    try {
      const result = await api('api/place_order.php', {
        method: 'POST',
        body: { notes: notes ? notes.value.trim() : '' },
      });

      setCartCount(0);
      toast(result.message);

      // Land on the tracking page with the new order at the top
      window.location.href = url('orders.php?placed=' + result.order_id);

    } catch (err) {
      toast(err.message, 'error');
      window.App.busy(submit, false);
    }
  });
})();
