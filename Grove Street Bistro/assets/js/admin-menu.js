/* ============================================================
   admin-menu.js — dish and category management
   Loads everything once, filters in the browser, and writes back
   through api/admin_menu.php.
   ============================================================ */

(function () {
  'use strict';

  const { api, toast, esc, money, busy } = window.App;

  const itemsBody      = document.getElementById('items-body');
  const categoriesBody = document.getElementById('categories-body');
  const itemCountLabel = document.getElementById('item-count');
  const filterCategory = document.getElementById('filter-category');
  const filterSearch   = document.getElementById('filter-search');

  const itemModal    = document.getElementById('item-modal');
  const itemForm     = document.getElementById('item-form');
  const itemTitle    = document.getElementById('item-modal-title');
  const itemSave     = document.getElementById('item-save');

  const categoryModal = document.getElementById('category-modal');
  const categoryForm  = document.getElementById('category-form');
  const categoryTitle = document.getElementById('category-modal-title');
  const categorySave  = document.getElementById('category-save');

  if (!itemsBody) return;

  let items       = [];
  let categories  = [];
  let lastFocused = null;

  // ---------------------------------------------------------------- modals
  function openModal(modal) {
    lastFocused = document.activeElement;
    modal.hidden = false;

    const firstField = modal.querySelector('input:not([type=hidden]), select, textarea');
    if (firstField) firstField.focus();
  }

  function closeModal(modal) {
    modal.hidden = true;
    if (lastFocused) lastFocused.focus();
  }

  [itemModal, categoryModal].forEach((modal) => {
    if (!modal) return;

    modal.addEventListener('click', (event) => {
      // Click the backdrop or a cancel button to dismiss
      if (event.target === modal || event.target.closest('[data-close]')) {
        closeModal(modal);
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    [itemModal, categoryModal].forEach((modal) => {
      if (modal && !modal.hidden) closeModal(modal);
    });
  });

  function clearFieldErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
      el.hidden = true;
      el.textContent = '';
    });
  }

  function showFieldErrors(form, fields) {
    Object.entries(fields || {}).forEach(([name, message]) => {
      const el = form.querySelector(`[data-error-for="${name}"]`);
      if (el) {
        el.textContent = message;
        el.hidden = false;
      }
    });
  }

  // ---------------------------------------------------------------- render
  function renderCategoryOptions() {
    const options = categories
      .map((c) => `<option value="${c.id}">${esc(c.name)}</option>`)
      .join('');

    document.getElementById('item-category').innerHTML = options;
    filterCategory.innerHTML = '<option value="0">All categories</option>' + options;
  }

  function visibleItems() {
    const categoryId = Number(filterCategory.value) || 0;
    const term       = filterSearch.value.trim().toLowerCase();

    return items.filter((item) => {
      if (categoryId && item.category_id !== categoryId) return false;
      if (!term) return true;

      return item.name.toLowerCase().includes(term)
        || (item.description || '').toLowerCase().includes(term);
    });
  }

  function renderItems() {
    const list = visibleItems();

    itemCountLabel.textContent =
      `${list.length} of ${items.length} ${items.length === 1 ? 'dish' : 'dishes'}`;

    if (!list.length) {
      itemsBody.innerHTML = '<tr><td colspan="6" class="muted">No dishes match those filters.</td></tr>';
      return;
    }

    itemsBody.innerHTML = list.map((item) => `
      <tr data-id="${item.id}">
        <td>${item.image_src
              ? `<img class="table-thumb" src="${esc(item.image_src)}" alt="">`
              : '<div class="table-thumb"></div>'}</td>
        <td>
          <strong>${esc(item.name)}</strong>
          ${item.description ? `<div class="muted" style="font-size:.82rem">${esc(item.description)}</div>` : ''}
        </td>
        <td>${esc(item.category_name)}</td>
        <td class="col-right mono">${money(item.price)}</td>
        <td>
          <button class="tag ${item.available ? 'tag-on' : 'tag-off'}" type="button"
                  data-toggle title="Click to ${item.available ? 'hide' : 'show'} this dish">
            ${item.available ? 'On menu' : 'Hidden'}
          </button>
        </td>
        <td>
          <div class="row-actions">
            <button class="btn btn-ghost btn-sm" type="button" data-edit>Edit</button>
            <button class="btn btn-danger btn-sm" type="button" data-delete>Delete</button>
          </div>
        </td>
      </tr>`).join('');
  }

  function renderCategories() {
    if (!categories.length) {
      categoriesBody.innerHTML = '<tr><td colspan="4" class="muted">No categories yet.</td></tr>';
      return;
    }

    categoriesBody.innerHTML = categories.map((cat) => `
      <tr data-id="${cat.id}">
        <td><strong>${esc(cat.name)}</strong></td>
        <td class="muted">${esc(cat.description || '')}</td>
        <td class="col-right mono">${cat.sort_order}</td>
        <td>
          <div class="row-actions">
            <button class="btn btn-ghost btn-sm" type="button" data-edit-category>Edit</button>
            <button class="btn btn-danger btn-sm" type="button" data-delete-category>Delete</button>
          </div>
        </td>
      </tr>`).join('');
  }

  async function load() {
    try {
      const data = await api('api/admin_menu.php');
      items      = data.items;
      categories = data.categories;

      renderCategoryOptions();
      renderItems();
      renderCategories();
    } catch (err) {
      itemsBody.innerHTML = `<tr><td colspan="6" class="muted">${esc(err.message)}</td></tr>`;
    }
  }

  // ---------------------------------------------------------------- dishes
  document.getElementById('add-item').addEventListener('click', () => {
    itemForm.reset();
    clearFieldErrors(itemForm);
    itemForm.elements.id.value = '';
    itemForm.elements.available.checked = true;
    itemTitle.textContent = 'Add a dish';
    openModal(itemModal);
  });

  itemsBody.addEventListener('click', async (event) => {
    const row = event.target.closest('tr[data-id]');
    if (!row) return;

    const id   = Number(row.dataset.id);
    const item = items.find((i) => i.id === id);
    if (!item) return;

    if (event.target.closest('[data-edit]')) {
      clearFieldErrors(itemForm);
      itemForm.elements.id.value          = item.id;
      itemForm.elements.name.value        = item.name;
      itemForm.elements.price.value       = item.price.toFixed(2);
      itemForm.elements.category_id.value = item.category_id;
      itemForm.elements.description.value = item.description || '';
      itemForm.elements.image_url.value   = /^https?:\/\//i.test(item.image_url || '') ? item.image_url : '';
      itemForm.elements.image.value       = '';
      itemForm.elements.available.checked = item.available;

      itemTitle.textContent = 'Edit ' + item.name;
      openModal(itemModal);
      return;
    }

    if (event.target.closest('[data-toggle]')) {
      try {
        const result = await api('api/admin_menu.php', {
          method: 'POST',
          body: { action: 'toggle_item', id },
        });
        item.available = result.available;
        renderItems();
        toast(result.message);
      } catch (err) {
        toast(err.message, 'error');
      }
      return;
    }

    if (event.target.closest('[data-delete]')) {
      if (!window.confirm(`Delete ${item.name}? This cannot be undone.`)) return;

      try {
        const result = await api('api/admin_menu.php', {
          method: 'POST',
          body: { action: 'delete_item', id },
        });
        toast(result.message);
        await load();
      } catch (err) {
        toast(err.message, 'error');
      }
    }
  });

  itemForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors(itemForm);

    // FormData carries the file; an unchecked box sends nothing, so set it here
    const formData = new FormData(itemForm);
    formData.set('action', 'save_item');
    formData.set('available', itemForm.elements.available.checked ? '1' : '0');

    busy(itemSave, true, 'Saving…');

    try {
      const result = await api('api/admin_menu.php', { method: 'POST', formData });
      toast(result.message);
      closeModal(itemModal);
      await load();
    } catch (err) {
      // 422 responses carry per field messages
      if (err.fields) showFieldErrors(itemForm, err.fields);
      toast(err.message, 'error');
    } finally {
      busy(itemSave, false);
    }
  });

  // ------------------------------------------------------------ categories
  document.getElementById('add-category').addEventListener('click', () => {
    categoryForm.reset();
    categoryForm.elements.id.value = '';
    categoryTitle.textContent = 'Add a category';
    openModal(categoryModal);
  });

  categoriesBody.addEventListener('click', async (event) => {
    const row = event.target.closest('tr[data-id]');
    if (!row) return;

    const id  = Number(row.dataset.id);
    const cat = categories.find((c) => c.id === id);
    if (!cat) return;

    if (event.target.closest('[data-edit-category]')) {
      categoryForm.elements.id.value          = cat.id;
      categoryForm.elements.name.value        = cat.name;
      categoryForm.elements.description.value = cat.description || '';
      categoryForm.elements.sort_order.value  = cat.sort_order;

      categoryTitle.textContent = 'Edit ' + cat.name;
      openModal(categoryModal);
      return;
    }

    if (event.target.closest('[data-delete-category]')) {
      if (!window.confirm(`Delete the ${cat.name} category?`)) return;

      try {
        const result = await api('api/admin_menu.php', {
          method: 'POST',
          body: { action: 'delete_category', id },
        });
        toast(result.message);
        await load();
      } catch (err) {
        toast(err.message, 'error');
      }
    }
  });

  categoryForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const payload = {
      action:      'save_category',
      id:          Number(categoryForm.elements.id.value) || 0,
      name:        categoryForm.elements.name.value.trim(),
      description: categoryForm.elements.description.value.trim(),
      sort_order:  Number(categoryForm.elements.sort_order.value) || 0,
    };

    busy(categorySave, true, 'Saving…');

    try {
      const result = await api('api/admin_menu.php', { method: 'POST', body: payload });
      toast(result.message);
      closeModal(categoryModal);
      await load();
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      busy(categorySave, false);
    }
  });

  // ---------------------------------------------------------------- filters
  filterCategory.addEventListener('change', renderItems);
  filterSearch.addEventListener('input', renderItems);

  load();
})();
