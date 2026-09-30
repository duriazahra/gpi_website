<?php
/**
 * Govt Polytechnic Institute (GPI) - Administrative Audit Logger
 * Records security-sensitive operations (login, status changes, document downloads, exports).
 */

declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * Records an administrative audit trail event into the database.
 *
 * @param PDO $pdo
 * @param int|null $adminId
 * @param string $action e.g. 'LOGIN', 'VIEW_DOCUMENT', 'UPDATE_STATUS', 'EXPORT_APPLICATIONS'
 * @param string|null $targetId e.g. Application Number or Program ID
 * @return bool
 */
function log_admin_action(PDO $pdo, ?int $adminId, string $action, ?string $targetId = null): bool
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO admin_audit_logs (admin_id, action, target_id, ip_address, user_agent, created_at)
             VALUES (:admin_id, :action, :target_id, :ip, :ua, NOW())'
        );

        $ip = get_client_ip();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        return $stmt->execute([
            ':admin_id'  => $adminId,
            ':action'    => $action,
            ':target_id' => $targetId,
            ':ip'        => $ip,
            ':ua'        => $ua
        ]);
    } catch (Throwable $e) {
        error_log('[GPI Audit Error] ' . $e->getMessage());
        return false;
    }
}
