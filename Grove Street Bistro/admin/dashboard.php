<?php
/**
 * Staff dashboard: today at a glance plus the newest tickets.
 */

declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pdo = db();

$stats = $pdo->query(
    "SELECT
       COUNT(*)                                                    AS orders_today,
       COALESCE(SUM(CASE WHEN status IN ('Received','Preparing','Ready') THEN 1 ELSE 0 END), 0) AS open_today,
       COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total ELSE 0 END), 0)                  AS revenue_today
     FROM orders
     WHERE DATE(created_at) = CURDATE()"
)->fetch();

$openAll = (int)$pdo->query(
    "SELECT COUNT(*) FROM orders WHERE status IN ('Received','Preparing','Ready')"
)->fetchColumn();

$menuCount = (int)$pdo->query('SELECT COUNT(*) FROM menu_items WHERE available = 1')->fetchColumn();

$recent = $pdo->query(
    "SELECT o.id, o.status, o.total, o.created_at, u.name AS customer_name,
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
       FROM orders o
       JOIN users u ON u.id = o.user_id
      ORDER BY o.id DESC
      LIMIT 10"
)->fetchAll();

$topItems = $pdo->query(
    "SELECT m.name, SUM(oi.quantity) AS sold
       FROM order_items oi
       JOIN menu_items m ON m.id = oi.menu_item_id
       JOIN orders o     ON o.id = oi.order_id
      WHERE o.status <> 'Cancelled'
      GROUP BY m.id, m.name
      ORDER BY sold DESC
      LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="wrap section">

  <div class="page-head">
    <div>
      <p class="eyebrow">Staff</p>
      <h1>Today</h1>
    </div>
    <div style="display:flex;gap:.6rem">
      <a class="btn btn-ghost btn-sm" href="<?= url('admin/menu.php') ?>">Manage menu</a>
      <a class="btn btn-primary btn-sm" href="<?= url('admin/orders.php') ?>">Order queue</a>
    </div>
  </div>

  <div class="stat-grid">
    <div class="stat">
      <p class="stat-label">Orders today</p>
      <p class="stat-value mono"><?= (int)$stats['orders_today'] ?></p>
    </div>
    <div class="stat">
      <p class="stat-label">Still open</p>
      <p class="stat-value mono"><?= $openAll ?></p>
    </div>
    <div class="stat">
      <p class="stat-label">Sales today</p>
      <p class="stat-value mono"><?= money($stats['revenue_today']) ?></p>
    </div>
    <div class="stat">
      <p class="stat-label">Dishes live</p>
      <p class="stat-value mono"><?= $menuCount ?></p>
    </div>
  </div>

  <div class="grid-2">
    <div>
      <h2 style="font-size:1.3rem">Latest tickets</h2>

      <?php if (!$recent): ?>
        <div class="empty-state">
          <h3>No orders yet</h3>
          <p>Once a customer checks out, their ticket lands here.</p>
        </div>
      <?php else: ?>
        <div class="data-table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Placed</th>
                <th>Status</th>
                <th class="col-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $o): ?>
                <tr>
                  <td class="mono">#<?= (int)$o['id'] ?></td>
                  <td><?= e($o['customer_name']) ?></td>
                  <td class="mono"><?= (int)$o['item_count'] ?></td>
                  <td class="muted"><?= e(pretty_time($o['created_at'])) ?></td>
                  <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                  <td class="col-right mono"><?= money($o['total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p style="margin-top:1rem">
          <a href="<?= url('admin/orders.php') ?>">Open the full queue</a>
        </p>
      <?php endif; ?>
    </div>

    <aside class="summary-card">
      <h3>Best sellers</h3>
      <?php if (!$topItems): ?>
        <p class="muted" style="font-size:.9rem;margin:0">Nothing sold yet.</p>
      <?php else: ?>
        <?php foreach ($topItems as $item): ?>
          <div class="summary-row">
            <span><?= e($item['name']) ?></span>
            <span class="mono"><?= (int)$item['sold'] ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
