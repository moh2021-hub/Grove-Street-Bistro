/* ============================================================
   menu.js — browse, filter, search, and add to cart
   Every one of those happens over Ajax; the page never reloads.
   ============================================================ */

(function () {
  'use strict';

  const { api, toast, esc, money, setCartCount, url } = window.App;

  const results   = document.getElementById('menu-results');
  const filters   = document.getElementById('category-filters');
  const searchBox = document.getElementById('menu-search');

  if (!results) return;

  const canOrder = results.dataset.canOrder !== '0';

  let activeCategory = 0;
  let searchTerm     = '';
  let searchTimer    = null;

  /** One dish, laid out like a line on a printed menu. */
  function renderItem(item) {
    const image = item.image_url
      ? (/^https?:\/\//i.test(item.image_url) ? item.image_url : url(item.image_url))
      : null;

    const thumb = image
      ? `<img class="menu-row-thumb" src="${esc(image)}" alt="${esc(item.name)}" loading="lazy">`
      : '';

    const controls = canOrder
      ? `<div class="menu-row-actions">
           <div class="qty-stepper">
             <button type="button" data-step="-1" aria-label="One fewer ${esc(item.name)}">&minus;</button>
             <input type="number" min="1" max="20" value="1" data-qty
                    aria-label="Quantity of ${esc(item.name)}">
             <button type="button" data-step="1" aria-label="One more ${esc(item.name)}">+</button>
           </div>
           <button class="btn btn-primary btn-sm" type="button" data-add>Add to cart</button>
         </div>`
      : '';

    return `
      <article class="menu-row" data-item-id="${item.id}">
        ${thumb}
        <div class="menu-row-main">
          <div class="menu-row-top">
            <span class="menu-row-name">${esc(item.name)}</span>
            <span class="menu-row-dots" aria-hidden="true"></span>
            <span class="menu-row-price">${money(item.price)}</span>
          </div>
          ${item.description ? `<p class="menu-row-desc">${esc(item.description)}</p>` : ''}
          ${controls}
        </div>
      </article>`;
  }

  /** Group the flat item list under its category headings. */
  function render(items) {
    if (!items.length) {
      results.innerHTML = `
        <div class="empty-state">
          <h3>Nothing matches that</h3>
          <p>Try a different word, or clear the filters to see the whole menu.</p>
        </div>`;
      return;
    }

    const groups = [];
    const seen   = new Map();

    items.forEach((item) => {
      if (!seen.has(item.category_id)) {
        const group = { name: item.category_name, items: [] };
        seen.set(item.category_id, group);
        groups.push(group);
      }
      seen.get(item.category_id).items.push(item);
    });

    results.innerHTML = groups.map((group) => `
      <section class="menu-section">
        <h2 class="menu-section-head">
          <span>${esc(group.name)}</span>
          <span class="note">${group.items.length} ${group.items.length === 1 ? 'dish' : 'dishes'}</span>
        </h2>
        ${group.items.map(renderItem).join('')}
      </section>`).join('');
  }

  async function load() {
    results.setAttribute('aria-busy', 'true');

    const query = new URLSearchParams();
    if (activeCategory) query.set('category_id', String(activeCategory));
    if (searchTerm)     query.set('q', searchTerm);

    try {
      const data = await api('api/menu_items.php?' + query.toString());
      render(data.items);
    } catch (err) {
      results.innerHTML = `
        <div class="empty-state">
          <h3>The menu did not load</h3>
          <p>${esc(err.message)}</p>
        </div>`;
    } finally {
      results.setAttribute('aria-busy', 'false');
    }
  }

  // ---- category filter ----
  if (filters) {
    filters.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-category]');
      if (!button) return;

      activeCategory = Number(button.dataset.category);

      filters.querySelectorAll('button[data-category]').forEach((b) => {
        b.setAttribute('aria-pressed', String(b === button));
      });
      load();
    });
  }

  // ---- search, debounced so typing does not fire a request per keystroke ----
  if (searchBox) {
    searchBox.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        searchTerm = searchBox.value.trim();
        load();
      }, 280);
    });
  }

  // ---- quantity steppers and add to cart (delegated: rows are re-rendered) ----
  results.addEventListener('click', async (event) => {
    const stepButton = event.target.closest('button[data-step]');
    if (stepButton) {
      const input = stepButton.parentElement.querySelector('[data-qty]');
      const next  = Number(input.value) + Number(stepButton.dataset.step);
      input.value = Math.min(20, Math.max(1, next));
      return;
    }

    const addButton = event.target.closest('button[data-add]');
    if (!addButton) return;

    const row      = addButton.closest('.menu-row');
    const itemId   = Number(row.dataset.itemId);
    const quantity = Number(row.querySelector('[data-qty]').value) || 1;

    window.App.busy(addButton, true, 'Adding…');

    try {
      const cart = await api('api/cart.php', {
        method: 'POST',
        body: { action: 'add', item_id: itemId, quantity },
      });
      setCartCount(cart.count);
      toast(cart.message || 'Added to your cart.');
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      window.App.busy(addButton, false);
    }
  });

  load();
})();
