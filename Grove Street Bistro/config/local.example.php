<?php
/**
 * Per-server settings.
 *
 * Copy this file to config/local.php on the server and fill in your values.
 * config/local.php is gitignored, so the production database password never
 * gets committed. Anything defined here overrides config/config.php.
 *
 *   cp config/local.example.php config/local.php
 *   nano config/local.php
 */

declare(strict_types=1);

// ---- Amazon RDS ----
define('DB_HOST', 'restaurant-db.abc123xyz.us-east-2.rds.amazonaws.com');
define('DB_PORT', '3306');
define('DB_NAME', 'restaurant_db');
define('DB_USER', 'admin');
define('DB_PASS', 'put-your-rds-password-here');

// ---- Site ----
define('APP_NAME', 'Grove Street Bistro');

// Turn this off once the site is live: it stops PHP printing stack traces,
// including database credentials, to anyone who triggers an error.
define('APP_DEBUG', false);
