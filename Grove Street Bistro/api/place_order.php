<?php
/**
 * POST /api/place_order.php  { notes: string }
 *
 * Turns the session cart into an orders row plus order_items rows.
 * Prices are read from the database inside the transaction, never from the
 * browser, and copied into price_at_order so later menu edits cannot rewrite
 * what the customer was charged.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('Use POST for this endpoint.', 405);
}

$user = api_require_login();

if ($user['role'] === 'admin') {
    json_fail('Staff accounts cannot place customer orders.', 403);
}

$cart = cart();
if (!$cart) {
    json_fail('Your cart is empty.');
}

$notes = trim((string)param('notes', ''));
if (mb_strlen($notes) > 500) {
    json_fail('Keep order notes under 500 characters.');
}

$pdo = db();
$pdo->beginTransaction();

try {
    $ids          = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // FOR UPDATE holds the rows until commit, so a price change mid checkout
    // cannot land between reading the price and writing the line item.
    $stmt = $pdo->prepare(
        "SELECT id, name, price, available
           FROM menu_items
          WHERE id IN ($placeholders)
          FOR UPDATE"
    );
    $stmt->execute($ids);

    $menu = [];
    foreach ($stmt->fetchAll() as $row) {
        $menu[(int)$row['id']] = $row;
    }

    $lines     = [];
    $subtotal  = 0.0;
    $unavail   = [];

    foreach ($cart as $id => $qty) {
        $id  = (int)$id;
        $qty = (int)$qty;

        if (!isset($menu[$id]) || !(int)$menu[$id]['available']) {
            $unavail[] = $menu[$id]['name'] ?? 'An item in your cart';
            continue;
        }
        if ($qty < 1) {
            continue;
        }

        $price     = (float)$menu[$id]['price'];
        $subtotal += $price * $qty;
        $lines[]   = ['id' => $id, 'qty' => $qty, 'price' => $price];
    }

    if ($unavail) {
        $pdo->rollBack();
        json_fail(implode(', ', $unavail) . ' sold out while you were ordering. Update your cart and try again.', 409);
    }

    if (!$lines) {
        $pdo->rollBack();
        json_fail('Your cart is empty.');
    }

    $subtotal = round($subtotal, 2);
    $tax      = round($subtotal * TAX_RATE, 2);
    $total    = round($subtotal + $tax, 2);

    $insertOrder = $pdo->prepare(
        'INSERT INTO orders (user_id, status, total, notes) VALUES (?, ?, ?, ?)'
    );
    $insertOrder->execute([$user['id'], 'Received', $total, $notes !== '' ? $notes : null]);
    $orderId = (int)$pdo->lastInsertId();

    $insertLine = $pdo->prepare(
        'INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_order) VALUES (?, ?, ?, ?)'
    );
    foreach ($lines as $line) {
        $insertLine->execute([$orderId, $line['id'], $line['qty'], $line['price']]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

cart_clear();

json_ok([
    'order_id' => $orderId,
    'subtotal' => $subtotal,
    'tax'      => $tax,
    'total'    => $total,
    'message'  => 'Order #' . $orderId . ' is in. The kitchen has it.',
]);
