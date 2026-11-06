<?php
/**
 * Customer order history and live status.
 *
 * PHP renders the orders once, then orders.js polls api/order_status.php and
 * updates the status pill and progress rail in place, so a customer watching
 * this page sees the kitchen move their ticket along.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

require_login();

if (is_admin()) {
    redirect('admin/orders.php');
}

$user = current_user();

$stmt = db()->prepare(
    'SELECT id, status, total, notes, created_at, updated_at
       FROM orders
      WHERE user_id = ?
      ORDER BY id DESC
      LIMIT 50'
);
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$linesByOrder = [];
if ($orders) {
    $ids          = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $lineStmt = db()->prepare(
        "SELECT oi.order_id, oi.quantity, oi.price_at_order, m.name
           FROM order_items oi
           JOIN menu_items m ON m.id = oi.menu_item_id
          WHERE oi.order_id IN ($placeholders)
          ORDER BY oi.id"
    );
    $lineStmt->execute($ids);

    foreach ($lineStmt->fetchAll() as $line) {
        $linesByOrder[(int)$line['order_id']][] = $line;
    }
}

$pageTitle   = 'My orders';
$pageScripts = ['orders.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section">

  <div class="page-head">
    <div>
      <p class="eyebrow">Updates every few seconds</p>
      <h1>My orders</h1>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= url('menu.php') ?>">Order again</a>
  </div>

  <?php if (!$orders): ?>
    <div class="empty-state">
      <h3>No orders yet</h3>
      <p>When you place one, it will show up here with live status.</p>
      <a class="btn btn-primary" href="<?= url('menu.php') ?>">Browse the menu</a>
    </div>
  <?php else: ?>

    <div id="orders-root">
      <?php foreach ($orders as $order): ?>
        <?php
          $id      = (int)$order['id'];
          $status  = $order['status'];
          $current = status_index($status);
          $lines   = $linesByOrder[$id] ?? [];
        ?>
        <article class="order-card" data-order-id="<?= $id ?>" data-status="<?= e($status) ?>">

          <div class="order-card-head">
            <div class="order-number">
              Order <strong>#<?= $id ?></strong>
              <span class="muted"> &middot; placed <?= e(pretty_time($order['created_at'])) ?></span>
            </div>
            <span class="status-pill status-<?= e($status) ?>" data-status-pill><?= e($status) ?></span>
          </div>

          <?php if ($status === 'Cancelled'): ?>
            <p class="muted" style="margin:0 0 1rem">
              This order was cancelled. Call the restaurant if that looks wrong.
            </p>
          <?php else: ?>
            <div class="tracker" data-tracker>
              <?php foreach (ORDER_FLOW as $i => $step): ?>
                <?php
                  $cls = 'tracker-step';
                  if ($i < $current)      { $cls .= ' is-done'; }
                  elseif ($i === $current) { $cls .= ' is-done is-current'; }
                ?>
                <div class="<?= $cls ?>" data-step="<?= $i ?>">
                  <div class="tracker-dot"></div>
                  <?= e($step) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <ul class="order-lines">
            <?php foreach ($lines as $line): ?>
              <li>
                <span>
                  <span class="qty"><?= (int)$line['quantity'] ?>&times;</span>
                  <?= e($line['name']) ?>
                </span>
                <span class="mono"><?= money((int)$line['quantity'] * (float)$line['price_at_order']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="order-total-row">
            <span>Total</span>
            <span class="mono"><?= money($order['total']) ?></span>
          </div>

          <?php if (!empty($order['notes'])): ?>
            <div class="order-note">
              <strong>Note for the kitchen</strong>
              <?= e($order['notes']) ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
