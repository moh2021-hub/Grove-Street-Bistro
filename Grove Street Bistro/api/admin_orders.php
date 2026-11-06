<?php
/**
 * Kitchen side order management.
 *
 * GET  ?status=Received&since=<order id>  -> orders with their line items
 * POST action=update_status { id, status }
 *
 * The admin orders page polls the GET endpoint so new tickets appear without
 * anyone refreshing.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

api_require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = (string)param('status', '');

    $sql = "SELECT o.id, o.status, o.total, o.notes, o.created_at, o.updated_at,
                   u.name AS customer_name, u.email AS customer_email
              FROM orders o
              JOIN users u ON u.id = o.user_id";
    $params = [];

    if ($status !== '' && in_array($status, ORDER_STATUSES, true)) {
        $sql     .= ' WHERE o.status = ?';
        $params[] = $status;
    } elseif ($status === 'active') {
        $sql .= " WHERE o.status IN ('Received','Preparing','Ready')";
    }

    $sql .= ' ORDER BY o.id DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $linesByOrder = [];
    if ($orders) {
        $ids          = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $lineStmt = $pdo->prepare(
            "SELECT oi.order_id, oi.quantity, oi.price_at_order, m.name
               FROM order_items oi
               JOIN menu_items m ON m.id = oi.menu_item_id
              WHERE oi.order_id IN ($placeholders)
              ORDER BY oi.id"
        );
        $lineStmt->execute($ids);

        foreach ($lineStmt->fetchAll() as $line) {
            $linesByOrder[(int)$line['order_id']][] = [
                'name'     => $line['name'],
                'quantity' => (int)$line['quantity'],
                'price'    => (float)$line['price_at_order'],
            ];
        }
    }

    $out = [];
    foreach ($orders as $o) {
        $id    = (int)$o['id'];
        $out[] = [
            'id'             => $id,
            'status'         => $o['status'],
            'total'          => (float)$o['total'],
            'notes'          => $o['notes'],
            'customer_name'  => $o['customer_name'],
            'customer_email' => $o['customer_email'],
            'created_at'     => pretty_time($o['created_at']),
            'updated_at'     => pretty_time($o['updated_at']),
            'items'          => $linesByOrder[$id] ?? [],
        ];
    }

    // Counts for the dashboard tiles
    $counts = ['Received' => 0, 'Preparing' => 0, 'Ready' => 0, 'Completed' => 0, 'Cancelled' => 0];
    foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
        $counts[$row['status']] = (int)$row['n'];
    }

    json_ok(['orders' => $out, 'counts' => $counts, 'statuses' => ORDER_STATUSES]);
}

$action = (string)param('action', '');

if ($action !== 'update_status') {
    json_fail('Unknown action.');
}

$id     = (int)param('id', 0);
$status = (string)param('status', '');

if ($id <= 0) {
    json_fail('Pick an order.');
}
if (!in_array($status, ORDER_STATUSES, true)) {
    json_fail('That is not a valid order status.');
}

$stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
$stmt->execute([$status, $id]);

$check = $pdo->prepare('SELECT status, updated_at FROM orders WHERE id = ?');
$check->execute([$id]);
$row = $check->fetch();

if (!$row) {
    json_fail('That order no longer exists.', 404);
}

json_ok([
    'id'         => $id,
    'status'     => $row['status'],
    'updated_at' => pretty_time($row['updated_at']),
    'message'    => 'Order #' . $id . ' marked ' . $status . '.',
]);
