<?php
/**
 * Govt Polytechnic Institute (GPI) - Database Connection Singleton
 * Uses PDO with strict prepared statements and error suppression.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Returns a shared PDO database instance.
 *
 * @return PDO
 * @throws RuntimeException If database connection fails
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_TIMEOUT            => 5,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log the detailed technical error securely on the server
        error_log('[GPI DB Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        // Never expose raw database credentials or stack traces to normal visitors
        if (defined('APP_ENV') && APP_ENV === 'development') {
            throw new RuntimeException(
                'Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            );
        } else {
            throw new RuntimeException(
                'A database connection error occurred. Please contact the institute administration or try again later.'
            );
        }
    }
}
