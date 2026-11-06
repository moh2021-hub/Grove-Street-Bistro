<?php
/**
 * Landing page: what the restaurant is, and a few things to order.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

// Six items to feature, cheapest first within each category for variety
$featured = db()->query(
    "SELECT m.id, m.name, m.description, m.price, m.image_url, c.name AS category
       FROM menu_items m
       JOIN categories c ON c.id = m.category_id
      WHERE m.available = 1
      ORDER BY c.sort_order, m.price DESC
      LIMIT 6"
)->fetchAll();

$itemCount = (int)db()->query('SELECT COUNT(*) FROM menu_items WHERE available = 1')->fetchColumn();

$pageTitle = 'Order online';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="wrap">
    <div class="hero-inner">
      <p class="eyebrow">Grove Street &middot; Detroit</p>
      <h1>Dinner, ordered ahead and <em>ready when you are</em>.</h1>
      <p class="lede">
        Browse the full menu, build your order, and follow it from the kitchen to the
        pass. No phone call, no waiting on hold.
      </p>
      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= url('menu.php') ?>">See the menu</a>
        <?php if (!is_logged_in()): ?>
          <a class="btn btn-ghost btn-lg" href="<?= url('register.php') ?>">Create an account</a>
        <?php else: ?>
          <a class="btn btn-ghost btn-lg" href="<?= url('orders.php') ?>">Track an order</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="hours-strip">
      <span><strong>Kitchen</strong> 11am to 10pm, every day</span>
      <span><strong>Pickup</strong> usually 20 to 30 minutes</span>
      <span><strong>On the menu</strong> <?= $itemCount ?> dishes today</span>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="page-head">
      <div>
        <p class="eyebrow">From the kitchen</p>
        <h2>What people order</h2>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= url('menu.php') ?>">Full menu</a>
    </div>

    <?php if (!$featured): ?>
      <div class="empty-state">
        <h3>The menu is being written</h3>
        <p>Check back shortly, or sign in as staff to add the first dish.</p>
      </div>
    <?php else: ?>
      <div class="featured-grid">
        <?php foreach ($featured as $item): ?>
          <?php $src = image_src($item['image_url']); ?>
          <article class="dish-card">
            <?php if ($src): ?>
              <img class="dish-thumb" src="<?= e($src) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="dish-card-body">
              <p class="eyebrow" style="margin-bottom:.4rem"><?= e($item['category']) ?></p>
              <h3><?= e($item['name']) ?></h3>
              <p><?= e($item['description']) ?></p>
              <span class="price"><?= money($item['price']) ?></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
