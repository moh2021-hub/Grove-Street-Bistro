<?php
/**
 * GET /api/menu_items.php
 *
 * Query parameters
 *   category_id  int    limit to one category
 *   q            string search name and description
 *
 * Returns { categories: [...], items: [...] } grouped client side.
 * Public: anyone can browse the menu without an account.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_fail('Use GET for this endpoint.', 405);
}

$categoryId = (int)param('category_id', 0);
$search     = trim((string)param('q', ''));

$sql    = "SELECT m.id, m.name, m.description, m.price, m.image_url, m.available,
                  m.category_id, c.name AS category_name
             FROM menu_items m
             JOIN categories c ON c.id = m.category_id
            WHERE m.available = 1";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND m.category_id = ?";
    $params[] = $categoryId;
}

if ($search !== '') {
    $sql .= " AND (m.name LIKE ? OR m.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY c.sort_order, c.name, m.name";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Normalise types so the JSON is clean for JavaScript
foreach ($items as &$row) {
    $row['id']          = (int)$row['id'];
    $row['category_id'] = (int)$row['category_id'];
    $row['price']       = (float)$row['price'];
    $row['available']   = (bool)$row['available'];
}
unset($row);

$categories = db()->query(
    "SELECT c.id, c.name, c.description,
            SUM(m.available = 1) AS item_count
       FROM categories c
       LEFT JOIN menu_items m ON m.category_id = c.id
      GROUP BY c.id, c.name, c.description, c.sort_order
      ORDER BY c.sort_order, c.name"
)->fetchAll();

foreach ($categories as &$cat) {
    $cat['id']         = (int)$cat['id'];
    $cat['item_count'] = (int)$cat['item_count'];
}
unset($cat);

json_ok(['categories' => $categories, 'items' => $items]);
