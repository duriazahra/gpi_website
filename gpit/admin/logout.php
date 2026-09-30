<?php
/**
 * Govt Polytechnic Institute (GPI) - Admin Portal Logout
 * Safely invalidates the administrative session and audit-logs the sign-out.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

try {
    $pdo = get_db_connection();
    admin_logout($pdo);
} catch (Throwable $e) {
    admin_logout(null);
}

header('Location: login.php?logged_out=1');
exit;
