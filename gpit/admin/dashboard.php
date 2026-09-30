<?php
/**
 * Govt Polytechnic Institute (GPI) - Admin Dashboard
 * Real-time institutional overview: application KPIs, program breakdowns, and recent submissions.
 */

declare(strict_types=1);

$pageTitle = 'Dashboard Overview';
$activeNav = 'dashboard';

require_once __DIR__ . '/header.php';

$pdo = get_db_connection();

// 1. Fetch counts by status
$statusCounts = [
    'total' => 0,
    'pending' => 0,
    'under_review' => 0,
    'verified' => 0,
    'accepted' => 0,
    'rejected' => 0
];

try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM applications GROUP BY status");
    while ($row = $stmt->fetch()) {
        $st = $row['status'];
        $c = (int)$row['cnt'];
        if (isset($statusCounts[$st])) {
            $statusCounts[$st] = $c;
        }
        $statusCounts['total'] += $c;
    }
} catch (Throwable $e) {
    // If table is empty or error
}

// 2. Admissions Status & Deadline
$admissionsOpen = '1';
$deadline = '2026-09-30 23:59:59';
$sessionYear = '2026';
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('admissions_open', 'admission_deadline', 'admission_session_year')");
    while ($s = $settingsStmt->fetch()) {
        if ($s['setting_key'] === 'admissions_open') $admissionsOpen = $s['setting_value'];
        if ($s['setting_key'] === 'admission_deadline') $deadline = $s['setting_value'];
        if ($s['setting_key'] === 'admission_session_year') $sessionYear = $s['setting_value'];
    }
} catch (Throwable $e) {}

$isAdmissionsActive = ($admissionsOpen === '1' && (empty($deadline) || strtotime($deadline) > time()));

// 3. Applications by Program
$programStats = [];
try {
    $pStmt = $pdo->query("
        SELECT p.id, p.title, p.code, p.total_seats,
               COUNT(a.id) as total_apps,
               SUM(CASE WHEN a.status = 'accepted' THEN 1 ELSE 0 END) as accepted_apps
        FROM programs p
        LEFT JOIN applications a ON p.id = a.program_id
        GROUP BY p.id, p.title, p.code, p.total_seats
        ORDER BY p.sort_order ASC, p.title ASC
    ");
    $programStats = $pStmt->fetchAll();
} catch (Throwable $e) {}

// 4. Recent Applications (Last 8)
$recentApps = [];
try {
    $rStmt = $pdo->query("
        SELECT a.id, a.application_number, a.full_name, a.cnic, a.matric_percentage, a.status, a.created_at,
               p.title as program_title
        FROM applications a
        JOIN programs p ON a.program_id = p.id
        ORDER BY a.created_at DESC
        LIMIT 8
    ");
    $recentApps = $rStmt->fetchAll();
} catch (Throwable $e) {}
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <h2>Institutional Admissions Dashboard</h2>
    <p>Session Year: <strong><?= e($sessionYear) ?></strong> &bull; Admissions Portal Status: 
      <span style="font-weight:700; color: <?= $isAdmissionsActive ? '#15803d' : '#b91c1c' ?>;">
        <?= $isAdmissionsActive ? '● OPEN FOR SUBMISSIONS' : '○ CLOSED' ?>
      </span>
    </p>
  </div>
  <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
    <a href="export.php" class="btn btn-outline btn-sm" style="background:#fff;">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Export CSV
    </a>
    <a href="applications.php" class="btn btn-primary btn-sm">
      View All Applications &rarr;
    </a>
  </div>
</div>

<!-- KPI Cards Grid -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
  <div class="stat-card">
    <div class="stat-label">Total Applications</div>
    <div class="stat-value"><?= number_format($statusCounts['total']) ?></div>
    <div class="stat-sub">Across all 6 programs</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #f59e0b;">
    <div class="stat-label" style="color:#b45309;">Pending Review</div>
    <div class="stat-value" style="color:#b45309;"><?= number_format($statusCounts['pending']) ?></div>
    <div class="stat-sub">Awaiting verification</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #0284c7;">
    <div class="stat-label" style="color:#0369a1;">Under Review</div>
    <div class="stat-value" style="color:#0369a1;"><?= number_format($statusCounts['under_review']) ?></div>
    <div class="stat-sub">Scrutiny in progress</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #059669;">
    <div class="stat-label" style="color:#065f46;">Verified Documents</div>
    <div class="stat-value" style="color:#065f46;"><?= number_format($statusCounts['verified']) ?></div>
    <div class="stat-sub">Credentials confirmed</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #16a34a;">
    <div class="stat-label" style="color:#166534;">Accepted / Merit</div>
    <div class="stat-value" style="color:#166534;"><?= number_format($statusCounts['accepted']) ?></div>
    <div class="stat-sub">Approved for admission</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #dc2626;">
    <div class="stat-label" style="color:#991b1b;">Rejected / Ineligible</div>
    <div class="stat-value" style="color:#991b1b;"><?= number_format($statusCounts['rejected']) ?></div>
    <div class="stat-sub">Disqualified / Incomplete</div>
  </div>
</div>

<!-- Two Column Section: Program Breakdown & System Quick Overview -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
  
  <!-- Program Applications Breakdown -->
  <div class="table-container">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--slate-200); display:flex; justify-content:space-between; align-items:center;">
      <h3 style="font-size:1.15rem; margin:0;">Applications by Program</h3>
      <a href="programs.php" style="font-size:0.85rem; color:var(--primary-700); font-weight:600;">Manage Programs &rarr;</a>
    </div>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Program Title</th>
          <th>Code</th>
          <th>Total Seats</th>
          <th>Applicants</th>
          <th>Accepted</th>
          <th>Seat Fill</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($programStats)): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--slate-500); padding:2rem;">No programs configured yet.</td></tr>
        <?php else: ?>
          <?php foreach ($programStats as $prog): ?>
            <?php 
              $fillPercent = $prog['total_seats'] > 0 ? round(($prog['accepted_apps'] / $prog['total_seats']) * 100, 1) : 0;
            ?>
            <tr>
              <td><strong><?= e($prog['title']) ?></strong></td>
              <td><code><?= e($prog['code']) ?></code></td>
              <td><?= (int)$prog['total_seats'] ?></td>
              <td><strong><?= (int)$prog['total_apps'] ?></strong></td>
              <td><span style="color:#16a34a; font-weight:700;"><?= (int)$prog['accepted_apps'] ?></span></td>
              <td style="min-width: 120px;">
                <div style="font-size:0.75rem; font-weight:600; margin-bottom:2px;"><?= $fillPercent ?>%</div>
                <div style="background:#e2e8f0; border-radius:999px; height:6px; overflow:hidden;">
                  <div style="background: <?= $fillPercent > 100 ? '#dc2626' : '#16a34a' ?>; width: <?= min(100, $fillPercent) ?>%; height:100%;"></div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Admissions Setting Card -->
  <div class="stat-card" style="display:flex; flex-direction:column; justify-content:space-between;">
    <div>
      <h3 style="font-size:1.15rem; margin-bottom:1rem;">Admissions Controls</h3>
      <div style="margin-bottom:1.25rem;">
        <div style="font-size:0.8rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Submission Portal</div>
        <div style="font-size:1.1rem; font-weight:800; margin-top:0.25rem;">
          <?= $admissionsOpen === '1' ? '🟢 Submissions Allowed' : '🔴 Submissions Disabled' ?>
        </div>
      </div>

      <div style="margin-bottom:1.25rem;">
        <div style="font-size:0.8rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Application Deadline</div>
        <div style="font-size:0.95rem; font-weight:600; margin-top:0.25rem;">
          <?= !empty($deadline) ? date('d M Y, h:i A', strtotime($deadline)) : 'No deadline specified' ?>
        </div>
      </div>

      <div style="margin-bottom:1.25rem;">
        <div style="font-size:0.8rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Academic Session</div>
        <div style="font-size:0.95rem; font-weight:600; margin-top:0.25rem;">
          Fall / Winter <?= e($sessionYear) ?>
        </div>
      </div>
    </div>

    <?php if ($currentAdmin['role'] === 'super_admin'): ?>
      <a href="settings.php" class="btn btn-outline btn-sm" style="width:100%; text-align:center;">
        Adjust Portal Deadlines &amp; Status &rarr;
      </a>
    <?php endif; ?>
  </div>

</div>

<!-- Recent Applications Table -->
<div class="table-container">
  <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--slate-200); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
    <div>
      <h3 style="font-size:1.15rem; margin:0;">Recent Applications Submitted</h3>
      <p style="margin:0.25rem 0 0 0; font-size:0.82rem; color:var(--slate-500);">Latest submissions awaiting institutional review</p>
    </div>
    <a href="applications.php" class="btn btn-primary btn-sm">Browse All Records &rarr;</a>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th>Application #</th>
        <th>Candidate Name</th>
        <th>Applied Program</th>
        <th>CNIC</th>
        <th>Matric %</th>
        <th>Status</th>
        <th>Submitted Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($recentApps)): ?>
        <tr>
          <td colspan="8" style="text-align:center; color:var(--slate-500); padding:3rem 1rem;">
            No applications found in the database. Test by submitting one from the <a href="../admissions.html#admission-form-section" target="_blank">Admissions Form</a>.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($recentApps as $app): ?>
          <tr>
            <td>
              <a href="application.php?id=<?= (int)$app['id'] ?>" style="font-family:var(--font-mono); font-weight:700; color:var(--primary-700);">
                <?= e($app['application_number']) ?>
              </a>
            </td>
            <td><strong><?= e($app['full_name']) ?></strong></td>
            <td><?= e($app['program_title']) ?></td>
            <td style="font-family:var(--font-mono); font-size:0.85rem;"><?= e($app['cnic']) ?></td>
            <td><strong><?= number_format((float)$app['matric_percentage'], 2) ?>%</strong></td>
            <td>
              <span class="badge badge-<?= e($app['status']) ?>">
                <?= e(str_replace('_', ' ', $app['status'])) ?>
              </span>
            </td>
            <td style="font-size:0.82rem; color:var(--slate-600);">
              <?= date('d M Y, h:i A', strtotime($app['created_at'])) ?>
            </td>
            <td>
              <a href="application.php?id=<?= (int)$app['id'] ?>" class="btn btn-outline btn-sm" style="padding:0.35rem 0.75rem; font-size:0.78rem;">
                View Dossier &rarr;
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
