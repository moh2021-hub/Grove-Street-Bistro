<?php
/**
 * Single shared PDO connection.
 *
 * PDO with emulated prepares turned off means every query in this project runs
 * as a real server-side prepared statement, which is what actually stops SQL
 * injection rather than escaping strings by hand.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        if (APP_DEBUG) {
            exit('Database connection failed: ' . $e->getMessage());
        }
        error_log('DB connection failed: ' . $e->getMessage());
        exit('The site cannot reach its database right now. Try again in a minute.');
    }

    return $pdo;
}
