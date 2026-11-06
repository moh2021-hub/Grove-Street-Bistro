<?php
/**
 * Shared bootstrap for every JSON endpoint.
 *
 * Guarantees for anything that includes this file:
 *   - a JSON content type and no HTML error output
 *   - CSRF checked on every state changing request
 *   - json_ok() / json_fail() as the only two ways to reply
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** Success response. */
function json_ok($data = null): never
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Error response. The message is shown to the user, so keep it plain. */
function json_fail(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Decode a JSON request body into an array. */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Combined view of JSON body, form fields, and query string. */
function input(): array
{
    static $merged = null;
    if ($merged === null) {
        $merged = array_merge($_GET, $_POST, json_body());
    }
    return $merged;
}

function param(string $key, $default = null)
{
    return input()[$key] ?? $default;
}

function api_require_login(): array
{
    $user = current_user();
    if (!$user) {
        json_fail('Sign in to continue.', 401);
    }
    return $user;
}

function api_require_admin(): array
{
    $user = api_require_login();
    if ($user['role'] !== 'admin') {
        json_fail('That action is limited to restaurant staff.', 403);
    }
    return $user;
}

// Any request that changes data must carry a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    if (!csrf_valid()) {
        json_fail('Your session token is stale. Reload the page and try again.', 419);
    }
}

// Convert uncaught errors into JSON instead of a half rendered HTML page.
set_exception_handler(function (Throwable $e): void {
    error_log('API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => APP_DEBUG ? $e->getMessage() : 'Something went wrong on our side.',
    ]);
    exit;
});
