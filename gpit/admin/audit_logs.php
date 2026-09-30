<?php
/**
 * Govt Polytechnic Institute (GPI) - Administrative Security Audit Trail
 * Complete forensic ledger of all administrative logins, status transitions, exports, and edits.
 * Restricted to Super Administrators.
 */

declare(strict_types=1);

$pageTitle = 'Security Audit Logs';
$activeNav = 'audit_logs';

require_once __DIR__ . '/header.php';

// Super Admin Only
$currentAdmin = require_super_admin();
$pdo = get_db_connection();

// Filtering & Pagination
$actionFilter = sanitize_string($_GET['action'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where = [];
$params = [];

if (!empty($actionFilter)) {
    $where[] = "l.action = :act";
    $params[':act'] = $actionFilter;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$totalRecords = 0;
try {
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_logs l $whereSql");
    $cStmt->execute($params);
    $totalRecords = (int)$cStmt->fetchColumn();
} catch (Throwable $e) {}

$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Fetch Records
$logs = [];
try {
    $sql = "
        SELECT l.*, adm.name as admin_name, adm.email as admin_email, adm.role as admin_role
        FROM admin_audit_logs l
        LEFT JOIN admins adm ON l.admin_id = adm.id
        $whereSql
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (Throwable $e) {}

// Action Types for Filter
$distinctActions = [];
try {
    $distinctActions = $pdo->query("SELECT DISTINCT action FROM admin_audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <h2>System Security &amp; Audit Trail</h2>
    <p>Forensic accountability log of all portal access and administrative modifications</p>
  </div>
</div>

<!-- Filter Bar -->
<div class="stat-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
  <form method="GET" action="audit_logs.php" style="display:flex; gap:1rem; align-items:center;">
    <div style="flex:1; max-width:300px;">
      <select name="action" class="form-control" style="font-size:0.85rem;" onchange="this.form.submit()">
        <option value="">All Action Types</option>
        <?php foreach ($distinctActions as $act): ?>
          <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!empty($actionFilter)): ?>
      <a href="audit_logs.php" class="btn btn-outline btn-sm">Clear Filter</a>
    <?php endif; ?>
    <div style="font-size:0.85rem; color:var(--slate-500); margin-left:auto;">
      Total Log Entries: <strong><?= number_format($totalRecords) ?></strong>
    </div>
  </form>
</div>

<!-- Logs Table -->
<div class="table-container" style="margin-bottom: 2rem;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Timestamp</th>
        <th>Staff Member</th>
        <th>Action Code</th>
        <th>Target Module</th>
        <th>Event Details</th>
        <th>IP Address</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--slate-500);">No audit events recorded.</td></tr>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td style="font-size:0.8rem; color:var(--slate-600); white-space:nowrap; font-family:var(--font-mono);">
              <?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?>
            </td>
            <td>
              <div style="font-weight:600; color:var(--slate-900);"><?= e($log['admin_name'] ?: 'System / Automated') ?></div>
              <div style="font-size:0.75rem; color:var(--slate-500);"><?= e($log['admin_email'] ?: 'N/A') ?></div>
            </td>
            <td>
              <?php
                $actClass = 'badge-under_review';
                if (str_contains($log['action'], 'DELETE')) $actClass = 'badge-rejected';
                if (str_contains($log['action'], 'LOGIN'))  $actClass = 'badge-active';
                if (str_contains($log['action'], 'LOGOUT')) $actClass = 'badge-inactive';
              ?>
              <span class="badge <?= $actClass ?>" style="font-family:var(--font-mono); font-size:0.75rem;">
                <?= e($log['action']) ?>
              </span>
            </td>
            <td>
              <?php if (!empty($log['target_table'])): ?>
                <code><?= e($log['target_table']) ?><?= !empty($log['target_id']) ? '#' . (int)$log['target_id'] : '' ?></code>
              <?php else: ?>
                <span style="color:var(--slate-400);">&mdash;</span>
              <?php endif; ?>
            </td>
            <td style="font-size:0.8rem; max-width:400px; word-break:break-all;">
              <?php 
                $details = $log['details'];
                $decoded = json_decode($details ?? '', true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    echo '<pre style="margin:0; font-size:0.75rem; background:#f8fafc; padding:0.35rem 0.5rem; border-radius:4px; max-height:80px; overflow-y:auto;">' . e(json_encode($decoded, JSON_PRETTY_PRINT)) . '</pre>';
                } else {
                    echo e($details ?: 'No additional metadata.');
                }
              ?>
            </td>
            <td style="font-family:var(--font-mono); font-size:0.78rem; color:var(--slate-600); white-space:nowrap;">
              <?= e($log['ip_address']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="font-size:0.85rem; color:var(--slate-600);">
      Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
    </div>

    <div style="display:flex; gap:0.35rem;">
      <?php if ($page > 1): ?>
        <a href="audit_logs.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-outline btn-sm">
          &larr; Prev
        </a>
      <?php endif; ?>

      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <a href="audit_logs.php?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" 
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>" style="min-width:36px; padding:0.4rem 0.6rem;">
          <?= $p ?>
        </a>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a href="audit_logs.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-outline btn-sm">
          Next &rarr;
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
