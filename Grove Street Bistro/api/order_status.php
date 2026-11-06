<?php
/**
 * GET /api/order_status.php
 * GET /api/order_status.php?id=12
 *
 * Returns the signed in customer's orders with their line items. The customer
 * order history page polls this so a status change made by the kitchen shows
 * up without a reload.
 *
 * A customer only ever sees their own orders; the WHERE clause is bound to the
 * session user id, not to anything the browser sends.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_fail('Use GET for this endpoint.', 405);
}

$user    = api_require_login();
$orderId = (int)param('id', 0);

$sql    = 'SELECT id, status, total, notes, created_at, updated_at
             FROM orders
            WHERE user_id = ?';
$params = [$user['id']];

if ($orderId > 0) {
    $sql     .= ' AND id = ?';
    $params[] = $orderId;
}
$sql .= ' ORDER BY id DESC LIMIT 50';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

if (!$orders) {
    json_ok(['orders' => []]);
}

// One extra query for all line items instead of one per order
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

$linesByOrder = [];
foreach ($lineStmt->fetchAll() as $line) {
    $linesByOrder[(int)$line['order_id']][] = [
        'name'           => $line['name'],
        'quantity'       => (int)$line['quantity'],
        'price_at_order' => (float)$line['price_at_order'],
        'line_total'     => round((int)$line['quantity'] * (float)$line['price_at_order'], 2),
    ];
}

$out = [];
foreach ($orders as $order) {
    $id    = (int)$order['id'];
    $out[] = [
        'id'           => $id,
        'status'       => $order['status'],
        'status_index' => status_index($order['status']),
        'total'        => (float)$order['total'],
        'notes'        => $order['notes'],
        'created_at'   => pretty_time($order['created_at']),
        'updated_at'   => pretty_time($order['updated_at']),
        'items'        => $linesByOrder[$id] ?? [],
    ];
}

json_ok(['orders' => $out, 'flow' => ORDER_FLOW]);
