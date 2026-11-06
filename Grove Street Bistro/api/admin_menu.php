<?php
/**
 * Admin menu management. Every action here goes through api_require_admin(),
 * so the role is checked on the request itself rather than trusted because the
 * browser happened to load an admin page.
 *
 * GET                                  -> all items (including hidden) + categories
 * POST action=save_item                -> create when id is empty, update otherwise
 * POST action=delete_item   { id }
 * POST action=toggle_item   { id }
 * POST action=save_category { id?, name, description }
 * POST action=delete_category { id }
 *
 * save_item is sent as multipart/form-data so an image file can ride along.
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

api_require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $items = $pdo->query(
        "SELECT m.id, m.name, m.description, m.price, m.image_url, m.available,
                m.category_id, c.name AS category_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.menu_item_id = m.id) AS times_ordered
           FROM menu_items m
           JOIN categories c ON c.id = m.category_id
          ORDER BY c.sort_order, c.name, m.name"
    )->fetchAll();

    foreach ($items as &$row) {
        $row['id']            = (int)$row['id'];
        $row['category_id']   = (int)$row['category_id'];
        $row['price']         = (float)$row['price'];
        $row['available']     = (bool)$row['available'];
        $row['times_ordered'] = (int)$row['times_ordered'];
        $row['image_src']     = image_src($row['image_url']);
    }
    unset($row);

    $categories = $pdo->query(
        'SELECT id, name, description, sort_order FROM categories ORDER BY sort_order, name'
    )->fetchAll();

    foreach ($categories as &$cat) {
        $cat['id'] = (int)$cat['id'];
    }
    unset($cat);

    json_ok(['items' => $items, 'categories' => $categories]);
}

/**
 * Validate and store an uploaded image.
 * Returns a path relative to the app root, or null when no file was sent.
 */
function store_uploaded_image(): ?string
{
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_fail('The image did not upload. Try a smaller file.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        json_fail('Images must be under ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
    }

    // Trust the file's actual contents, not its name or the browser's mime type
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        json_fail('That file is not an image.');
    }

    $extensions = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!isset($extensions[$info[2]])) {
        json_fail('Use a JPG, PNG, GIF, or WEBP image.');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) {
        json_fail('The uploads folder is missing and could not be created.', 500);
    }
    if (!is_writable(UPLOAD_DIR)) {
        json_fail('The uploads folder is not writable by the web server.', 500);
    }

    // Random name: never reuse the client supplied filename
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$info[2]];
    $dest = UPLOAD_DIR . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_fail('The image could not be saved.', 500);
    }
    @chmod($dest, 0644);

    return 'uploads/' . $name;
}

$action = (string)param('action', '');

switch ($action) {

    // ---------------------------------------------------------- menu items
    case 'save_item': {
        $id          = (int)param('id', 0);
        $name        = trim((string)param('name', ''));
        $description = trim((string)param('description', ''));
        $priceRaw    = (string)param('price', '');
        $categoryId  = (int)param('category_id', 0);
        $available   = (int)param('available', 1) === 1 ? 1 : 0;
        $imageUrlIn  = trim((string)param('image_url', ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Give the item a name of 120 characters or fewer.';
        }
        if (!is_numeric($priceRaw) || (float)$priceRaw < 0 || (float)$priceRaw > 999999) {
            $errors['price'] = 'Enter a price like 12.50.';
        }
        if ($categoryId <= 0) {
            $errors['category_id'] = 'Pick a category.';
        } else {
            $check = $pdo->prepare('SELECT 1 FROM categories WHERE id = ?');
            $check->execute([$categoryId]);
            if (!$check->fetchColumn()) {
                $errors['category_id'] = 'That category no longer exists.';
            }
        }
        if ($imageUrlIn !== '' && !filter_var($imageUrlIn, FILTER_VALIDATE_URL)) {
            $errors['image_url'] = 'Enter a full URL starting with https://';
        }
        if ($errors) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => reset($errors), 'fields' => $errors]);
            exit;
        }

        $price    = round((float)$priceRaw, 2);
        $uploaded = store_uploaded_image();

        // Priority: a new file beats a pasted URL, which beats what is already stored.
        $imageUrl = $uploaded ?? ($imageUrlIn !== '' ? $imageUrlIn : null);

        if ($id > 0) {
            if ($imageUrl === null) {
                $stmt = $pdo->prepare(
                    'UPDATE menu_items
                        SET category_id = ?, name = ?, description = ?, price = ?, available = ?
                      WHERE id = ?'
                );
                $stmt->execute([$categoryId, $name, $description ?: null, $price, $available, $id]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE menu_items
                        SET category_id = ?, name = ?, description = ?, price = ?, available = ?, image_url = ?
                      WHERE id = ?'
                );
                $stmt->execute([$categoryId, $name, $description ?: null, $price, $available, $imageUrl, $id]);
            }

            if ($stmt->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM menu_items WHERE id = ?');
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) {
                    json_fail('That item no longer exists.', 404);
                }
            }
            json_ok(['id' => $id, 'message' => $name . ' updated.']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO menu_items (category_id, name, description, price, image_url, available)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$categoryId, $name, $description ?: null, $price, $imageUrl, $available]);

        json_ok(['id' => (int)$pdo->lastInsertId(), 'message' => $name . ' added to the menu.']);
    }

    case 'delete_item': {
        $id = (int)param('id', 0);
        if ($id <= 0) {
            json_fail('Pick an item to remove.');
        }

        $stmt = $pdo->prepare('SELECT name FROM menu_items WHERE id = ?');
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        if ($name === false) {
            json_fail('That item no longer exists.', 404);
        }

        // An item that appears in past orders is hidden rather than deleted,
        // otherwise the order history would lose what the customer bought.
        $used = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE menu_item_id = ?');
        $used->execute([$id]);

        if ((int)$used->fetchColumn() > 0) {
            $pdo->prepare('UPDATE menu_items SET available = 0 WHERE id = ?')->execute([$id]);
            json_ok([
                'hidden'  => true,
                'message' => $name . ' appears in past orders, so it was hidden from the menu instead of deleted.',
            ]);
        }

        $pdo->prepare('DELETE FROM menu_items WHERE id = ?')->execute([$id]);
        json_ok(['hidden' => false, 'message' => $name . ' deleted.']);
    }

    case 'toggle_item': {
        $id = (int)param('id', 0);
        $stmt = $pdo->prepare('UPDATE menu_items SET available = 1 - available WHERE id = ?');
        $stmt->execute([$id]);

        $now = $pdo->prepare('SELECT name, available FROM menu_items WHERE id = ?');
        $now->execute([$id]);
        $row = $now->fetch();

        if (!$row) {
            json_fail('That item no longer exists.', 404);
        }

        json_ok([
            'available' => (bool)$row['available'],
            'message'   => $row['name'] . ((int)$row['available'] ? ' is back on the menu.' : ' is now hidden.'),
        ]);
    }

    // ---------------------------------------------------------- categories
    case 'save_category': {
        $id          = (int)param('id', 0);
        $name        = trim((string)param('name', ''));
        $description = trim((string)param('description', ''));
        $sortOrder   = (int)param('sort_order', 0);

        if ($name === '' || mb_strlen($name) > 80) {
            json_fail('Give the category a name of 80 characters or fewer.', 422);
        }

        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE categories SET name = ?, description = ?, sort_order = ? WHERE id = ?')
                    ->execute([$name, $description ?: null, $sortOrder, $id]);
                json_ok(['id' => $id, 'message' => $name . ' updated.']);
            }

            $pdo->prepare('INSERT INTO categories (name, description, sort_order) VALUES (?, ?, ?)')
                ->execute([$name, $description ?: null, $sortOrder]);
            json_ok(['id' => (int)$pdo->lastInsertId(), 'message' => $name . ' added.']);

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                json_fail('A category called ' . $name . ' already exists.', 409);
            }
            throw $e;
        }
    }

    case 'delete_category': {
        $id = (int)param('id', 0);

        $count = $pdo->prepare('SELECT COUNT(*) FROM menu_items WHERE category_id = ?');
        $count->execute([$id]);
        $itemCount = (int)$count->fetchColumn();

        if ($itemCount > 0) {
            json_fail("Move or delete this category's $itemCount item(s) first.", 409);
        }

        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        json_ok(['message' => 'Category deleted.']);
    }

    default:
        json_fail('Unknown action.');
}
