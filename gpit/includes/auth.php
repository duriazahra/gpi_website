<?php
/**
 * Govt Polytechnic Institute (GPI) - Administrative Authentication & RBAC Middleware
 * Handles session verification, password verification, and role-based access control.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/audit.php';

start_secure_session();

/**
 * Checks if an administrator is currently logged in with an active session.
 *
 * @return bool
 */
function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_role']);
}

/**
 * Returns the currently authenticated admin record from session.
 *
 * @return array|null
 */
function current_admin(): ?array
{
    if (!is_admin_logged_in()) {
        return null;
    }

    return [
        'id'    => (int)$_SESSION['admin_id'],
        'name'  => (string)$_SESSION['admin_name'],
        'email' => (string)$_SESSION['admin_email'],
        'role'  => (string)$_SESSION['admin_role']
    ];
}

/**
 * Middleware: Requires an active administrator session.
 * Redirects unauthenticated users to the admin login page.
 *
 * @return array Authenticated admin details
 */
function require_admin(): array
{
    if (!is_admin_logged_in()) {
        $returnUrl = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header('Location: login.php?return_url=' . urlencode($returnUrl));
        exit;
    }

    return current_admin() ?? [];
}

/**
 * Middleware: Requires 'super_admin' role.
 * Terminate with HTTP 403 if current admin has standard staff privileges.
 *
 * @return array
 */
function require_super_admin(): array
{
    $admin = require_admin();

    if ($admin['role'] !== 'super_admin') {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access Forbidden</title><link rel="stylesheet" href="../css/style.css"></head><body style="padding:3rem;text-align:center;">';
        echo '<div style="max-width:500px;margin:0 auto;background:#fff;padding:2rem;border-radius:8px;border:1px solid #cbd5e1;">';
        echo '<h2 style="color:#991b1b;">Access Denied</h2>';
        echo '<p style="color:#475569;">You do not have sufficient permissions to access this administrative module. This area is reserved for Super Administrators.</p>';
        echo '<a href="dashboard.php" class="btn btn-primary" style="margin-top:1rem;">Return to Dashboard</a>';
        echo '</div></body></html>';
        exit;
    }

    return $admin;
}

/**
 * Authenticates an administrator using secure password verification.
 *
 * @param PDO $pdo
 * @param string $email
 * @param string $password
 * @return array [bool $success, array|null $admin, string $error]
 */
function admin_login(PDO $pdo, string $email, string $password): array
{
    $cleanEmail = strtolower(sanitize_string($email));

    // Rate-limiting to prevent brute force
    if (!check_rate_limit('admin_login', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
        return [false, null, 'Too many failed login attempts. Please wait 15 minutes before trying again.'];
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password, role, is_active FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $cleanEmail]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, (string)$admin['password'])) {
        return [false, null, 'Invalid email address or password.'];
    }

    if (empty($admin['is_active'])) {
        return [false, null, 'Your administrative account has been deactivated. Please contact the Super Admin.'];
    }

    // Protect against session fixation
    session_regenerate_id(true);

    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_name'] = (string)$admin['name'];
    $_SESSION['admin_email'] = (string)$admin['email'];
    $_SESSION['admin_role'] = (string)$admin['role'];
    $_SESSION['admin_logged_in_at'] = time();

    // Reset rate-limit after successful login
    reset_rate_limit('admin_login');

    // Audit log
    log_admin_action($pdo, (int)$admin['id'], 'LOGIN');

    return [true, $admin, ''];
}

/**
 * Logs out the administrator and invalidates the session.
 *
 * @param PDO|null $pdo
 * @return void
 */
function admin_logout(?PDO $pdo = null): void
{
    if ($pdo && is_admin_logged_in()) {
        log_admin_action($pdo, (int)$_SESSION['admin_id'], 'LOGOUT');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
