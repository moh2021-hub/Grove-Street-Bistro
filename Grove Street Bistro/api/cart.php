<?php
/**
 * Cart endpoint. The cart lives in the PHP session, so it survives page
 * navigation and cannot be tampered with from the browser.
 *
 * GET                       -> current cart
 * POST { action: "add",    item_id, quantity }
 * POST { action: "update", item_id, quantity }   quantity 0 removes the line
 * POST { action: "remove", item_id }
 * POST { action: "clear" }
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

const MAX_QTY_PER_ITEM = 20;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_ok(cart_detailed());
}

$action   = (string)param('action', '');
$itemId   = (int)param('item_id', 0);
$quantity = (int)param('quantity', 1);
$cart     = cart();

switch ($action) {

    case 'add':
        if ($itemId <= 0) {
            json_fail('Pick an item first.');
        }
        if ($quantity < 1 || $quantity > MAX_QTY_PER_ITEM) {
            json_fail('Choose between 1 and ' . MAX_QTY_PER_ITEM . ' of an item.');
        }

        // Confirm the item exists and is on the menu right now
        $stmt = db()->prepare('SELECT name, available FROM menu_items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            json_fail('That item is no longer on the menu.', 404);
        }
        if (!(int)$item['available']) {
            json_fail($item['name'] . ' is sold out today.');
        }

        $newQty = min(MAX_QTY_PER_ITEM, ($cart[$itemId] ?? 0) + $quantity);
        $cart[$itemId] = $newQty;
        cart_save($cart);

        $detail = cart_detailed();
        $detail['message'] = $item['name'] . ' added to your cart.';
        json_ok($detail);

    case 'update':
        if (!isset($cart[$itemId])) {
            json_fail('That item is not in your cart.', 404);
        }
        if ($quantity < 0 || $quantity > MAX_QTY_PER_ITEM) {
            json_fail('Choose between 0 and ' . MAX_QTY_PER_ITEM . '.');
        }

        if ($quantity === 0) {
            unset($cart[$itemId]);
        } else {
            $cart[$itemId] = $quantity;
        }
        cart_save($cart);
        json_ok(cart_detailed());

    case 'remove':
        unset($cart[$itemId]);
        cart_save($cart);
        json_ok(cart_detailed());

    case 'clear':
        cart_clear();
        json_ok(cart_detailed());

    default:
        json_fail('Unknown cart action.');
}
