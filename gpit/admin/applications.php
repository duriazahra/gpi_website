<?php
/**
 * Govt Polytechnic Institute (GPI) - Applications Management
 * Advanced applicant search, multi-criteria filtering, merit rank sorting, and pagination.
 */

declare(strict_types=1);

$pageTitle = 'Manage Applications';
$activeNav = 'applications';

require_once __DIR__ . '/header.php';

$pdo = get_db_connection();

// Read query params
$search     = sanitize_string($_GET['search'] ?? '');
$programId  = isset($_GET['program_id']) && $_GET['program_id'] !== '' ? (int)$_GET['program_id'] : null;
$status     = sanitize_string($_GET['status'] ?? '');
$gender     = sanitize_string($_GET['gender'] ?? '');
$sortBy     = sanitize_string($_GET['sort'] ?? 'created_at_desc');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

// Fetch programs for filter dropdown
$allPrograms = [];
try {
    $allPrograms = $pdo->query("SELECT id, title, code FROM programs ORDER BY sort_order ASC, title ASC")->fetchAll();
} catch (Throwable $e) {}

// Build WHERE query
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(a.application_number LIKE :search OR a.full_name LIKE :search OR a.cnic LIKE :search OR a.phone LIKE :search OR a.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($programId !== null && $programId > 0) {
    $whereClauses[] = "a.program_id = :program_id";
    $params[':program_id'] = $programId;
}

if (!empty($status)) {
    $whereClauses[] = "a.status = :status";
    $params[':status'] = $status;
}

if (!empty($gender)) {
    $whereClauses[] = "a.gender = :gender";
    $params[':gender'] = $gender;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Sorting map
$orderMap = [
    'created_at_desc' => 'a.created_at DESC',
    'created_at_asc'  => 'a.created_at ASC',
    'percentage_desc' => 'a.matric_percentage DESC, a.created_at ASC',
    'percentage_asc'  => 'a.matric_percentage ASC',
    'name_asc'        => 'a.full_name ASC',
    'name_desc'       => 'a.full_name DESC'
];
$orderBy = $orderMap[$sortBy] ?? 'a.created_at DESC';

// Total count for pagination
$totalRecords = 0;
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications a
        $whereSql
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
} catch (Throwable $e) {}

$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Fetch page records
$applications = [];
try {
    $sql = "
        SELECT a.id, a.application_number, a.full_name, a.father_name, a.cnic, a.phone, 
               a.gender, a.city, a.matric_percentage, a.matric_obtained_marks, a.matric_total_marks,
               a.status, a.created_at,
               p.title as program_title, p.code as program_code
        FROM applications a
        JOIN programs p ON a.program_id = p.id
        $whereSql
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $applications = $stmt->fetchAll();
} catch (Throwable $e) {}

// Build export query string matching active filters
$filterQueryArgs = http_build_query(array_filter([
    'search'     => $search,
    'program_id' => $programId,
    'status'     => $status,
    'gender'     => $gender,
    'sort'       => $sortBy
]));
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <h2>Student Applications Directory</h2>
    <p>Showing <strong><?= count($applications) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> filtered applications</p>
  </div>
  <div style="display:flex; gap:0.75rem;">
    <a href="export.php?<?= $filterQueryArgs ?>" class="btn btn-outline btn-sm" style="background:#fff;">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Export Current View (CSV)
    </a>
  </div>
</div>

<!-- Search & Filter Bar -->
<div class="stat-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
  <form method="GET" action="applications.php" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 100px; gap: 0.85rem; align-items:end;">
    <div class="form-group" style="margin:0;">
      <label for="filterSearch" style="font-size:0.78rem; font-weight:700; color:var(--slate-600); text-transform:uppercase;">Search Query</label>
      <input type="text" id="filterSearch" name="search" class="form-control" style="padding:0.5rem 0.75rem; font-size:0.85rem;" 
             placeholder="Name, App#, CNIC, Phone..." value="<?= e($search) ?>">
    </div>

    <div class="form-group" style="margin:0;">
      <label for="filterProgram" style="font-size:0.78rem; font-weight:700; color:var(--slate-600); text-transform:uppercase;">Program</label>
      <select id="filterProgram" name="program_id" class="form-control" style="padding:0.5rem 0.75rem; font-size:0.85rem;">
        <option value="">All Programs</option>
        <?php foreach ($allPrograms as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>>
            <?= e($p['title']) ?> (<?= e($p['code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin:0;">
      <label for="filterStatus" style="font-size:0.78rem; font-weight:700; color:var(--slate-600); text-transform:uppercase;">Status</label>
      <select id="filterStatus" name="status" class="form-control" style="padding:0.5rem 0.75rem; font-size:0.85rem;">
        <option value="">All Statuses</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
        <option value="under_review" <?= $status === 'under_review' ? 'selected' : '' ?>>Under Review</option>
        <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Documents Verified</option>
        <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted / Merit</option>
        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>

    <div class="form-group" style="margin:0;">
      <label for="filterGender" style="font-size:0.78rem; font-weight:700; color:var(--slate-600); text-transform:uppercase;">Gender</label>
      <select id="filterGender" name="gender" class="form-control" style="padding:0.5rem 0.75rem; font-size:0.85rem;">
        <option value="">All Genders</option>
        <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Male</option>
        <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Female</option>
        <option value="other" <?= $gender === 'other' ? 'selected' : '' ?>>Other</option>
      </select>
    </div>

    <div class="form-group" style="margin:0;">
      <label for="filterSort" style="font-size:0.78rem; font-weight:700; color:var(--slate-600); text-transform:uppercase;">Sort Order</label>
      <select id="filterSort" name="sort" class="form-control" style="padding:0.5rem 0.75rem; font-size:0.85rem;">
        <option value="created_at_desc" <?= $sortBy === 'created_at_desc' ? 'selected' : '' ?>>Newest Submissions</option>
        <option value="created_at_asc" <?= $sortBy === 'created_at_asc' ? 'selected' : '' ?>>Oldest Submissions</option>
        <option value="percentage_desc" <?= $sortBy === 'percentage_desc' ? 'selected' : '' ?>>Highest Marks % (Merit)</option>
        <option value="percentage_asc" <?= $sortBy === 'percentage_asc' ? 'selected' : '' ?>>Lowest Marks %</option>
        <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Candidate Name (A-Z)</option>
      </select>
    </div>

    <div style="display:flex; gap:0.4rem;">
      <button type="submit" class="btn btn-primary btn-sm" style="height:38px; width:100%;">
        Filter
      </button>
      <a href="applications.php" class="btn btn-outline btn-sm" style="height:38px; padding:0 0.6rem;" title="Reset Filters">
        &times;
      </a>
    </div>
  </form>
</div>

<!-- Applications Table -->
<div class="table-container">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Application #</th>
        <th>Candidate Details</th>
        <th>Applied Program</th>
        <th>Academic Score</th>
        <th>Contact</th>
        <th>Status</th>
        <th>Submitted Date</th>
        <th style="text-align:right;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($applications)): ?>
        <tr>
          <td colspan="8" style="text-align:center; padding:3rem; color:var(--slate-500);">
            No applications match your filter criteria.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($applications as $app): ?>
          <tr>
            <td>
              <a href="application.php?id=<?= (int)$app['id'] ?>" style="font-family:var(--font-mono); font-weight:700; color:var(--primary-700);">
                <?= e($app['application_number']) ?>
              </a>
            </td>
            <td>
              <div style="font-weight:700; color:var(--slate-900);"><?= e($app['full_name']) ?></div>
              <div style="font-size:0.78rem; color:var(--slate-500);">S/O: <?= e($app['father_name']) ?></div>
              <div style="font-family:var(--font-mono); font-size:0.76rem; color:var(--slate-600);"><?= e($app['cnic']) ?></div>
            </td>
            <td>
              <div style="font-weight:600; font-size:0.86rem;"><?= e($app['program_title']) ?></div>
              <span style="font-size:0.72rem; color:var(--slate-500);"><?= e($app['program_code']) ?></span>
            </td>
            <td>
              <div style="font-weight:800; color:var(--primary-900); font-size:0.95rem;">
                <?= number_format((float)$app['matric_percentage'], 2) ?>%
              </div>
              <div style="font-size:0.75rem; color:var(--slate-500);">
                <?= (int)$app['matric_obtained_marks'] ?> / <?= (int)$app['matric_total_marks'] ?>
              </div>
            </td>
            <td>
              <div style="font-size:0.84rem; font-family:var(--font-mono);"><?= e($app['phone']) ?></div>
              <div style="font-size:0.75rem; color:var(--slate-500);"><?= e($app['city']) ?></div>
            </td>
            <td>
              <span class="badge badge-<?= e($app['status']) ?>">
                <?= e(str_replace('_', ' ', $app['status'])) ?>
              </span>
            </td>
            <td style="font-size:0.8rem; color:var(--slate-600); white-space:nowrap;">
              <?= date('d M Y, h:i A', strtotime($app['created_at'])) ?>
            </td>
            <td style="text-align:right;">
              <a href="application.php?id=<?= (int)$app['id'] ?>" class="btn btn-primary btn-sm" style="padding:0.4rem 0.8rem; font-size:0.8rem;">
                Review Dossier &rarr;
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div style="font-size:0.85rem; color:var(--slate-600);">
      Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
    </div>

    <div style="display:flex; gap:0.35rem;">
      <?php if ($page > 1): ?>
        <a href="applications.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-outline btn-sm">
          &larr; Prev
        </a>
      <?php endif; ?>

      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <a href="applications.php?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" 
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>" style="min-width:36px; padding:0.4rem 0.6rem;">
          <?= $p ?>
        </a>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a href="applications.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-outline btn-sm">
          Next &rarr;
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
