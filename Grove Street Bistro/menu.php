<?php
/**
 * Menu browsing.
 *
 * Categories are rendered by PHP so the page has structure immediately; the
 * dishes themselves come from api/menu_items.php over Ajax, which is what lets
 * filtering and search happen without a reload.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$categories = db()->query(
    "SELECT c.id, c.name, COUNT(m.id) AS item_count
       FROM categories c
       LEFT JOIN menu_items m ON m.category_id = c.id AND m.available = 1
      GROUP BY c.id, c.name, c.sort_order
      ORDER BY c.sort_order, c.name"
)->fetchAll();

$totalItems = array_sum(array_column($categories, 'item_count'));

$pageTitle   = 'Menu';
$pageScripts = ['menu.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section">

  <div class="page-head">
    <div>
      <p class="eyebrow">Served 11am to 10pm</p>
      <h1>Menu</h1>
    </div>
    <p class="muted" style="margin:0"><?= (int)$totalItems ?> dishes available today</p>
  </div>

  <div class="menu-layout">

    <aside class="menu-filters">
      <div class="filter-search">
        <label class="sr-only" for="menu-search">Search the menu</label>
        <input type="search" id="menu-search" placeholder="Search dishes" autocomplete="off">
      </div>

      <ul class="filter-list" id="category-filters">
        <li>
          <button type="button" data-category="0" aria-pressed="true">
            <span>Everything</span>
            <span class="count"><?= (int)$totalItems ?></span>
          </button>
        </li>
        <?php foreach ($categories as $cat): ?>
          <li>
            <button type="button" data-category="<?= (int)$cat['id'] ?>" aria-pressed="false">
              <span><?= e($cat['name']) ?></span>
              <span class="count"><?= (int)$cat['item_count'] ?></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <!-- data-can-order tells menu.js whether to draw the add-to-cart controls -->
    <div id="menu-results" aria-live="polite" aria-busy="true"
         data-can-order="<?= is_admin() ? '0' : '1' ?>">
      <p class="muted">Loading the menu…</p>
    </div>

  </div>

  <noscript>
    <div class="flash flash-info" style="margin-top:2rem">
      This menu loads its dishes with JavaScript. Turn JavaScript on to browse and order.
    </div>
  </noscript>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
