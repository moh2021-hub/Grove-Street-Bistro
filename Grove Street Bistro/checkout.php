<?php
/**
 * Checkout: confirm the order, leave a note for the kitchen, submit.
 * The submit itself is an Ajax POST to api/place_order.php.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

require_login();

if (is_admin()) {
    flash('Staff accounts cannot place customer orders.', 'info');
    redirect('admin/orders.php');
}

$cart = cart_detailed();

if (!$cart['items']) {
    flash('Your cart is empty, so there is nothing to check out.', 'info');
    redirect('menu.php');
}

$user = current_user();

$pageTitle   = 'Checkout';
$pageScripts = ['checkout.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section">

  <div class="page-head">
    <div>
      <p class="eyebrow">Last step</p>
      <h1>Confirm your order</h1>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= url('cart.php') ?>">Edit the cart</a>
  </div>

  <div class="grid-2">
    <div>
      <div class="summary-card" style="position:static">
        <h3>Picking up as</h3>
        <p style="margin:0"><?= e($user['name']) ?></p>
        <p class="muted" style="font-size:.88rem;margin:.15rem 0 0"><?= e($user['email']) ?></p>
      </div>

      <div style="margin-top:1.5rem">
        <h3>Your items</h3>
        <ul class="review-list">
          <?php foreach ($cart['items'] as $item): ?>
            <li>
              <span>
                <span class="qty"><?= (int)$item['quantity'] ?>&times;</span>
                <?= e($item['name']) ?>
              </span>
              <span class="mono"><?= money($item['line_total']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="field" style="margin-top:1.5rem">
        <label for="order-notes">Anything the kitchen should know?</label>
        <textarea id="order-notes" maxlength="500"
                  placeholder="Allergies, no onions, extra napkins, pickup time"></textarea>
        <p class="hint"><span id="notes-count">0</span> of 500 characters</p>
      </div>
    </div>

    <aside class="summary-card">
      <h3>Order summary</h3>
      <div class="summary-row">
        <span>Items</span>
        <span class="mono"><?= (int)$cart['count'] ?></span>
      </div>
      <div class="summary-row">
        <span>Subtotal</span>
        <span class="mono"><?= money($cart['subtotal']) ?></span>
      </div>
      <div class="summary-row">
        <span>Sales tax (<?= (int)round(TAX_RATE * 100) ?>%)</span>
        <span class="mono"><?= money($cart['tax']) ?></span>
      </div>
      <div class="summary-row summary-total">
        <span>Total</span>
        <span class="mono"><?= money($cart['total']) ?></span>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="button" id="place-order">Place order</button>
      <p class="hint" style="text-align:center;margin:.75rem 0 0">
        Pay at the counter when you collect.
      </p>
    </aside>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
