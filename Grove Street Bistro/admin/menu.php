<?php
/**
 * Menu management. The table and the editor are driven entirely by
 * api/admin_menu.php over Ajax, so adding a dish never reloads the page.
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle   = 'Manage menu';
$pageScripts = ['admin-menu.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="wrap section">

  <div class="page-head">
    <div>
      <p class="eyebrow">Staff</p>
      <h1>Menu</h1>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
      <button class="btn btn-ghost btn-sm" type="button" id="add-category">Add category</button>
      <button class="btn btn-primary btn-sm" type="button" id="add-item">Add dish</button>
    </div>
  </div>

  <div class="toolbar">
    <label class="sr-only" for="filter-category">Filter by category</label>
    <select id="filter-category">
      <option value="0">All categories</option>
    </select>

    <label class="sr-only" for="filter-search">Search dishes</label>
    <input type="search" id="filter-search" placeholder="Search dishes" autocomplete="off">

    <span class="spacer"></span>
    <span class="muted" id="item-count" style="font-size:.88rem"></span>
  </div>

  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:60px"><span class="sr-only">Photo</span></th>
          <th>Dish</th>
          <th>Category</th>
          <th class="col-right">Price</th>
          <th>On menu</th>
          <th class="col-right">Actions</th>
        </tr>
      </thead>
      <tbody id="items-body">
        <tr><td colspan="6" class="muted">Loading dishes…</td></tr>
      </tbody>
    </table>
  </div>

  <section style="margin-top:3rem">
    <h2 style="font-size:1.3rem">Categories</h2>
    <div class="data-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th class="col-right">Order</th>
            <th class="col-right">Actions</th>
          </tr>
        </thead>
        <tbody id="categories-body">
          <tr><td colspan="4" class="muted">Loading categories…</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- Dish editor -->
<div class="modal-backdrop" id="item-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="item-modal-title">
    <div class="modal-head">
      <h2 id="item-modal-title">Add a dish</h2>
      <button class="modal-close" type="button" data-close aria-label="Close">&times;</button>
    </div>

    <form id="item-form" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="id" value="">

        <div class="field">
          <label for="item-name">Name</label>
          <input type="text" id="item-name" name="name" maxlength="120" required>
          <p class="field-error" data-error-for="name" hidden></p>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="item-price">Price</label>
            <input type="number" id="item-price" name="price" step="0.01" min="0" required>
            <p class="field-error" data-error-for="price" hidden></p>
          </div>
          <div class="field">
            <label for="item-category">Category</label>
            <select id="item-category" name="category_id" required></select>
            <p class="field-error" data-error-for="category_id" hidden></p>
          </div>
        </div>

        <div class="field">
          <label for="item-description">Description</label>
          <textarea id="item-description" name="description" maxlength="500"></textarea>
        </div>

        <div class="field">
          <label for="item-image">Photo</label>
          <input type="file" id="item-image" name="image" accept="image/*">
          <p class="hint">JPG, PNG, GIF, or WEBP up to 2 MB. Stored in the uploads folder.</p>
        </div>

        <div class="field">
          <label for="item-image-url">…or paste an image URL</label>
          <input type="url" id="item-image-url" name="image_url" placeholder="https://your-bucket.s3.amazonaws.com/dish.jpg">
          <p class="hint">Use this for an S3 object URL. A chosen file wins over this field.</p>
          <p class="field-error" data-error-for="image_url" hidden></p>
        </div>

        <div class="checkbox-row">
          <input type="checkbox" id="item-available" name="available" value="1" checked>
          <label for="item-available">Show this dish on the menu</label>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
        <button class="btn btn-primary" type="submit" id="item-save">Save dish</button>
      </div>
    </form>
  </div>
</div>

<!-- Category editor -->
<div class="modal-backdrop" id="category-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="category-modal-title" style="max-width:440px">
    <div class="modal-head">
      <h2 id="category-modal-title">Add a category</h2>
      <button class="modal-close" type="button" data-close aria-label="Close">&times;</button>
    </div>

    <form id="category-form">
      <div class="modal-body">
        <input type="hidden" name="id" value="">

        <div class="field">
          <label for="category-name">Name</label>
          <input type="text" id="category-name" name="name" maxlength="80" required>
        </div>

        <div class="field">
          <label for="category-description">Description</label>
          <input type="text" id="category-description" name="description" maxlength="255">
        </div>

        <div class="field">
          <label for="category-sort">Sort order</label>
          <input type="number" id="category-sort" name="sort_order" value="0" step="1">
          <p class="hint">Lower numbers appear first on the menu.</p>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
        <button class="btn btn-primary" type="submit" id="category-save">Save category</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
