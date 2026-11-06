<?php
/**
 * The cart. PHP paints the first view; every change after that is Ajax against
 * api/cart.php, which returns the recalculated totals.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if (is_admin()) {
    flash('Staff accounts do not have a cart.', 'info');
    redirect('admin/orders.php');
}

$cart = cart_detailed();

// cart_detailed() drops anything that left the menu; tell the customer why.
if ($cart['removed']) {
    flash(implode(', ', $cart['removed']) . ' came off the menu and was removed from your cart.', 'info');
}

$pageTitle   = 'Your cart';
$pageScripts = ['cart.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section">

  <div class="page-head">
    <h1>Your cart</h1>
    <a class="btn btn-ghost btn-sm" href="<?= url('menu.php') ?>">Keep browsing</a>
  </div>

  <div id="cart-root" data-empty="<?= $cart['items'] ? '0' : '1' ?>">

    <?php if (!$cart['items']): ?>
      <div class="empty-state" id="cart-empty">
        <h3>Nothing in the cart yet</h3>
        <p>Pick something from the menu and it will show up here.</p>
        <a class="btn btn-primary" href="<?= url('menu.php') ?>">Browse the menu</a>
      </div>
    <?php else: ?>

      <div class="grid-2">
        <div>
          <table class="cart-table" id="cart-table">
            <thead>
              <tr>
                <th>Item</th>
                <th style="width:150px">Quantity</th>
                <th class="col-right" style="width:110px">Line total</th>
                <th style="width:44px"><span class="sr-only">Remove</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cart['items'] as $item): ?>
                <tr data-item-id="<?= (int)$item['id'] ?>">
                  <td data-label="Item">
                    <div class="cart-item-name"><?= e($item['name']) ?></div>
                    <div class="cart-item-cat"><?= e($item['category']) ?> &middot; <?= money($item['price']) ?> each</div>
                  </td>
                  <td data-label="Quantity">
                    <div class="qty-stepper">
                      <button type="button" data-step="-1" aria-label="One fewer <?= e($item['name']) ?>">&minus;</button>
                      <input type="number" min="0" max="20" value="<?= (int)$item['quantity'] ?>"
                             data-qty aria-label="Quantity of <?= e($item['name']) ?>">
                      <button type="button" data-step="1" aria-label="One more <?= e($item['name']) ?>">+</button>
                    </div>
                  </td>
                  <td class="col-right mono" data-label="Line total" data-line-total>
                    <?= money($item['line_total']) ?>
                  </td>
                  <td class="col-right">
                    <button class="btn btn-ghost btn-sm" type="button" data-remove
                            aria-label="Remove <?= e($item['name']) ?>">&times;</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div style="margin-top:1.25rem">
            <button class="btn btn-ghost btn-sm" type="button" id="clear-cart">Empty the cart</button>
          </div>
        </div>

        <aside class="summary-card">
          <h3>Order summary</h3>
          <div class="summary-row">
            <span>Subtotal</span>
            <span class="mono" data-subtotal><?= money($cart['subtotal']) ?></span>
          </div>
          <div class="summary-row">
            <span>Sales tax (<?= (int)round(TAX_RATE * 100) ?>%)</span>
            <span class="mono" data-tax><?= money($cart['tax']) ?></span>
          </div>
          <div class="summary-row summary-total">
            <span>Total</span>
            <span class="mono" data-total><?= money($cart['total']) ?></span>
          </div>

          <a class="btn btn-primary btn-block" href="<?= url('checkout.php') ?>">Go to checkout</a>
          <p class="hint" style="text-align:center;margin:.75rem 0 0">You pay when you pick up.</p>
        </aside>
      </div>

    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
