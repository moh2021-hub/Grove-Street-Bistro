<?php
/**
 * Shared helpers: sessions, auth, CSRF, cart, formatting.
 * Every page and every API endpoint starts by requiring this file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// ------------------------------------------------------------------
// Session
// ------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL,
        'httponly' => true,          // JavaScript cannot read the session cookie
        'samesite' => 'Lax',         // blocks the simplest CSRF vector
        'secure'   => $https,
    ]);
    session_start();
}

// ------------------------------------------------------------------
// Output + formatting
// ------------------------------------------------------------------

/** Escape anything before it touches HTML. Guards against stored XSS. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format a number as a price string. */
function money($amount): string
{
    return '$' . number_format((float)$amount, 2);
}

/** Build a URL that works whether the app sits at / or in a subfolder. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Human friendly timestamp, e.g. "Aug 6, 3:42 PM". */
function pretty_time(?string $mysqlDatetime): string
{
    if (!$mysqlDatetime) {
        return '';
    }
    $ts = strtotime($mysqlDatetime);
    return $ts ? date('M j, g:i A', $ts) : '';
}

// ------------------------------------------------------------------
// CSRF
// ------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input for classic form posts. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Accepts the token from a form field or the X-CSRF-Token header used by fetch().
 * hash_equals avoids leaking information through timing.
 */
function csrf_valid(): bool
{
    $sent = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
    return is_string($sent)
        && $sent !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

// ------------------------------------------------------------------
// Authentication + roles
// ------------------------------------------------------------------

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => (int)$_SESSION['user_id'],
        'name'  => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role'  => $_SESSION['role'] ?? 'customer',
    ];
}

/** Store the signed-in user and rotate the session id to stop fixation. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Page guard: send anonymous visitors to the login form. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? url('index.php');
        flash('Sign in to continue.', 'info');
        redirect('login.php');
    }
}

/** Page guard for every file under /admin. Checked on each request, not once at login. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('That area is for restaurant staff.', 'error');
        redirect('index.php');
    }
}

// ------------------------------------------------------------------
// Flash messages
// ------------------------------------------------------------------

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flashes'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $out = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $out;
}

// ------------------------------------------------------------------
// Cart
//
// The cart lives in the session as [menu_item_id => quantity]. Prices are
// never stored client side; they are re-read from the database every time the
// cart is priced, so editing the page in devtools cannot change what you pay.
// ------------------------------------------------------------------

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_save(array $cart): void
{
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/** Total number of units in the cart, for the header badge. */
function cart_count(): int
{
    return array_sum(cart());
}

/**
 * Join the session cart against menu_items and compute the money.
 * Unavailable or deleted items are dropped and reported back to the caller.
 */
function cart_detailed(): array
{
    $cart = cart();
    if (!$cart) {
        return ['items' => [], 'subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0, 'count' => 0, 'removed' => []];
    }

    $ids          = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = db()->prepare(
        "SELECT m.id, m.name, m.price, m.image_url, m.available, c.name AS category
           FROM menu_items m
           JOIN categories c ON c.id = m.category_id
          WHERE m.id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $byId    = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r;
    }

    $items    = [];
    $removed  = [];
    $subtotal = 0.0;
    $dirty    = false;

    foreach ($cart as $id => $qty) {
        $id  = (int)$id;
        $qty = max(0, (int)$qty);

        if (!isset($byId[$id]) || !(int)$byId[$id]['available'] || $qty === 0) {
            $removed[] = $byId[$id]['name'] ?? 'An item';
            unset($cart[$id]);
            $dirty = true;
            continue;
        }

        $row       = $byId[$id];
        $price     = (float)$row['price'];
        $lineTotal = round($price * $qty, 2);
        $subtotal += $lineTotal;

        $items[] = [
            'id'         => $id,
            'name'       => $row['name'],
            'category'   => $row['category'],
            'image_url'  => $row['image_url'],
            'price'      => $price,
            'quantity'   => $qty,
            'line_total' => $lineTotal,
        ];
    }

    if ($dirty) {
        cart_save($cart);
    }

    $subtotal = round($subtotal, 2);
    $tax      = round($subtotal * TAX_RATE, 2);

    return [
        'items'    => $items,
        'subtotal' => $subtotal,
        'tax'      => $tax,
        'total'    => round($subtotal + $tax, 2),
        'count'    => array_sum($cart),
        'removed'  => $removed,
    ];
}

// ------------------------------------------------------------------
// Order status
// ------------------------------------------------------------------

const ORDER_STATUSES = ['Received', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

/** Steps shown in the customer progress tracker. Cancelled is handled separately. */
const ORDER_FLOW = ['Received', 'Preparing', 'Ready', 'Completed'];

function status_index(string $status): int
{
    $i = array_search($status, ORDER_FLOW, true);
    return $i === false ? -1 : (int)$i;
}

// ------------------------------------------------------------------
// Images
// ------------------------------------------------------------------

/**
 * Resolve a stored image_url for use in a src attribute.
 * Values starting with http are used as is, which is what makes S3 URLs work
 * without any code change. Anything else is treated as a local upload path.
 */
function image_src(?string $imageUrl): ?string
{
    if (!$imageUrl) {
        return null;
    }
    if (preg_match('#^https?://#i', $imageUrl)) {
        return $imageUrl;
    }
    return url($imageUrl);
}
