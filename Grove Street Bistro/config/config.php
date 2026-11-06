<?php
/**
 * Application configuration.
 *
 * Every value falls back to an environment variable first, so the same file
 * works on XAMPP locally and on EC2 + RDS without editing code.
 * On EC2 set these in /etc/httpd/conf.d/restaurant.conf with SetEnv, or in
 * the systemd unit, or just edit the defaults below.
 */

declare(strict_types=1);

/**
 * Read a setting from the environment.
 *
 * Checks three places because different setups populate different ones:
 * getenv() covers the CLI and mod_php, $_SERVER covers PHP-FPM behind Apache
 * (where SetEnv arrives as a FastCGI parameter rather than a real environment
 * variable), and $_ENV covers the rest.
 */
function env(string $key, $default = null)
{
    $value = getenv($key);

    if ($value === false && isset($_SERVER[$key])) {
        $value = $_SERVER[$key];
    }
    if ($value === false && isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }
    return $value === false ? $default : $value;
}

/**
 * Optional per-server overrides. Copy config/local.example.php to
 * config/local.php and put the RDS credentials there. It is gitignored, so the
 * production password never ends up in the repository, and anything it defines
 * wins over the defaults below.
 */
if (is_file(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

// ---------- Database ----------
defined('DB_HOST') || define('DB_HOST', env('DB_HOST', '127.0.0.1'));
defined('DB_PORT') || define('DB_PORT', env('DB_PORT', '3306'));
defined('DB_NAME') || define('DB_NAME', env('DB_NAME', 'restaurant_db'));
defined('DB_USER') || define('DB_USER', env('DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', env('DB_PASS', ''));

// ---------- App ----------
defined('APP_NAME') || define('APP_NAME', env('APP_NAME', 'Grove Street Bistro'));
define('APP_ROOT', dirname(__DIR__));                 // filesystem path
defined('TAX_RATE') || define('TAX_RATE', 0.06);      // Michigan sales tax, 6%

// Show PHP errors locally, hide them in production.
defined('APP_DEBUG') || define('APP_DEBUG', (string)env('APP_DEBUG', '1') === '1');

// ---------- Image uploads ----------
// Local folder used when no S3 URL is supplied. Must be writable by Apache.
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);          // 2 MB

/**
 * Work out the URL prefix the app is served from.
 * Returns '' at a document root, '/restaurant' inside htdocs/restaurant.
 */
function detect_base_url(): string
{
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);

    if ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
        $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        return rtrim($base, '/');
    }
    return '';
}

define('BASE_URL', detect_base_url());

// ---------- Error reporting ----------
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set('America/Detroit');

// ---------- Compatibility ----------
/**
 * A stock `yum install php` on Amazon Linux does not always include mbstring,
 * and length checks on user input should not be the thing that takes the site
 * down. If the extension is missing, count UTF-8 characters by hand: in UTF-8
 * every continuation byte matches 10xxxxxx, so dropping those leaves exactly
 * one byte per character.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        return strlen(preg_replace('/[\x80-\xBF]/', '', $string));
    }
}
