<?php
/**
 * Govt Polytechnic Institute (GPI) - Programs Management
 * Manage diploma offerings, seat capacities, fee structures, and active/inactive status.
 */

declare(strict_types=1);

$pageTitle = 'Academic Programs';
$activeNav = 'programs';

require_once __DIR__ . '/header.php';

$pdo = get_db_connection();

// Handle Status Toggle or Program Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = sanitize_string($_POST['action'] ?? '');

    if ($action === 'toggle_status') {
        $progId = (int)($_POST['id'] ?? 0);
        $newActive = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE programs SET is_active = :act, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':act' => $newActive, ':id' => $progId]);

            log_admin_action($pdo, $currentAdmin['id'], 'UPDATE_PROGRAM_STATUS', 'programs', $progId, [
                'is_active' => $newActive
            ]);

            $_SESSION['flash_success'] = 'Program status updated successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to update program status: ' . $e->getMessage();
        }

        header('Location: programs.php');
        exit;
    }

    if ($action === 'save_program') {
        $progId        = (int)($_POST['id'] ?? 0);
        $code          = strtoupper(sanitize_string($_POST['code'] ?? ''));
        $slug          = strtolower(sanitize_string($_POST['slug'] ?? ''));
        $title         = sanitize_string($_POST['title'] ?? '');
        $description   = sanitize_string($_POST['description'] ?? '');
        $duration      = sanitize_string($_POST['duration'] ?? '3 Years (6 Semesters)');
        $shift         = sanitize_string($_POST['shift'] ?? 'Morning');
        $eligibility   = sanitize_string($_POST['eligibility'] ?? 'Matriculation (Science) with minimum 45% marks');
        $totalSeats    = max(1, (int)($_POST['total_seats'] ?? 50));
        $feeSemester   = max(0.0, (float)($_POST['fee_per_semester'] ?? 0.0));
        $sortOrder     = (int)($_POST['sort_order'] ?? 0);
        $isActive      = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code) || empty($title) || empty($slug)) {
            $_SESSION['flash_error'] = 'Program Code, Slug, and Title are required.';
            header('Location: programs.php');
            exit;
        }

        try {
            if ($progId > 0) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE programs 
                    SET code = :code, slug = :slug, title = :title, description = :description,
                        duration = :duration, shift = :shift, eligibility = :eligibility,
                        total_seats = :total_seats, fee_per_semester = :fee, sort_order = :sort_order,
                        is_active = :is_active, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':code'        => $code,
                    ':slug'        => $slug,
                    ':title'       => $title,
                    ':description' => $description,
                    ':duration'    => $duration,
                    ':shift'       => $shift,
                    ':eligibility' => $eligibility,
                    ':total_seats' => $totalSeats,
                    ':fee'         => $feeSemester,
                    ':sort_order'  => $sortOrder,
                    ':is_active'   => $isActive,
                    ':id'          => $progId
                ]);

                log_admin_action($pdo, $currentAdmin['id'], 'UPDATE_PROGRAM', 'programs', $progId, [
                    'code' => $code, 'title' => $title
                ]);

                $_SESSION['flash_success'] = "Program '{$title}' updated successfully.";
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO programs 
                        (code, slug, title, description, duration, shift, eligibility, total_seats, fee_per_semester, sort_order, is_active)
                    VALUES 
                        (:code, :slug, :title, :description, :duration, :shift, :eligibility, :total_seats, :fee, :sort_order, :is_active)
                ");
                $stmt->execute([
                    ':code'        => $code,
                    ':slug'        => $slug,
                    ':title'       => $title,
                    ':description' => $description,
                    ':duration'    => $duration,
                    ':shift'       => $shift,
                    ':eligibility' => $eligibility,
                    ':total_seats' => $totalSeats,
                    ':fee'         => $feeSemester,
                    ':sort_order'  => $sortOrder,
                    ':is_active'   => $isActive
                ]);

                $newId = (int)$pdo->lastInsertId();
                log_admin_action($pdo, $currentAdmin['id'], 'CREATE_PROGRAM', 'programs', $newId, [
                    'code' => $code, 'title' => $title
                ]);

                $_SESSION['flash_success'] = "New program '{$title}' added successfully.";
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
        }

        header('Location: programs.php');
        exit;
    }
}

// Fetch all programs joined with application count
$programs = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, COUNT(a.id) as app_count
        FROM programs p
        LEFT JOIN applications a ON p.id = a.program_id
        GROUP BY p.id
        ORDER BY p.sort_order ASC, p.title ASC
    ");
    $programs = $stmt->fetchAll();
} catch (Throwable $e) {}

// Edit mode check
$editProgram = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($programs as $p) {
        if ((int)$p['id'] === $editId) {
            $editProgram = $p;
            break;
        }
    }
}
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <h2>Academic Programs Management</h2>
    <p>Configure course offerings, duration, capacities, and active admissions visibility</p>
  </div>
  <div>
    <a href="#programForm" class="btn btn-primary btn-sm" onclick="document.getElementById('programFormModal').scrollIntoView({behavior:'smooth'});">
      + Add New Program
    </a>
  </div>
</div>

<!-- Programs Table -->
<div class="table-container" style="margin-bottom: 2rem;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Sort</th>
        <th>Code / Slug</th>
        <th>Program Title</th>
        <th>Duration / Shift</th>
        <th>Seats</th>
        <th>Fee / Sem</th>
        <th>Applicants</th>
        <th>Status</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($programs)): ?>
        <tr><td colspan="9" style="text-align:center; padding:3rem; color:var(--slate-500);">No programs found.</td></tr>
      <?php else: ?>
        <?php foreach ($programs as $prog): ?>
          <tr>
            <td style="font-family:var(--font-mono); font-weight:700; color:var(--slate-400);"><?= (int)$prog['sort_order'] ?></td>
            <td>
              <div style="font-family:var(--font-mono); font-weight:700;"><?= e($prog['code']) ?></div>
              <div style="font-size:0.75rem; color:var(--slate-500);"><?= e($prog['slug']) ?></div>
            </td>
            <td>
              <div style="font-weight:700; color:var(--slate-900);"><?= e($prog['title']) ?></div>
              <div style="font-size:0.78rem; color:var(--slate-500); max-width:320px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                <?= e($prog['description']) ?>
              </div>
            </td>
            <td>
              <div style="font-size:0.85rem; font-weight:600;"><?= e($prog['duration']) ?></div>
              <div style="font-size:0.75rem; color:var(--slate-500);"><?= e($prog['shift']) ?></div>
            </td>
            <td><strong><?= (int)$prog['total_seats'] ?></strong></td>
            <td>Rs. <?= number_format((float)$prog['fee_per_semester']) ?></td>
            <td>
              <a href="applications.php?program_id=<?= (int)$prog['id'] ?>" style="font-weight:700; color:var(--primary-700);">
                <?= (int)$prog['app_count'] ?> Applicants
              </a>
            </td>
            <td>
              <form method="POST" action="programs.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?= (int)$prog['id'] ?>">
                <input type="hidden" name="is_active" value="<?= $prog['is_active'] ? '0' : '1' ?>">
                <button type="submit" class="badge <?= $prog['is_active'] ? 'badge-active' : 'badge-inactive' ?>" 
                        style="border:none; cursor:pointer;" title="Click to toggle status">
                  <?= $prog['is_active'] ? 'Active' : 'Inactive' ?>
                </button>
              </form>
            </td>
            <td style="text-align:right;">
              <a href="programs.php?edit=<?= (int)$prog['id'] ?>#programFormModal" class="btn btn-outline btn-sm" style="padding:0.35rem 0.75rem; font-size:0.78rem;">
                Edit Details
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add / Edit Program Form -->
<div id="programFormModal" class="stat-card" style="padding: 1.75rem; max-width: 900px; margin: 0 auto;">
  <h3 style="font-size:1.25rem; margin-bottom:0.35rem;">
    <?= $editProgram ? 'Edit Program: ' . e($editProgram['title']) : 'Add New Academic Program' ?>
  </h3>
  <p style="font-size:0.84rem; color:var(--slate-500); margin-bottom:1.5rem;">
    <?= $editProgram ? 'Update specifications, eligibility criteria, and fee structure.' : 'Define a new technical program for applicant admissions.' ?>
  </p>

  <form method="POST" action="programs.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_program">
    <input type="hidden" name="id" value="<?= $editProgram ? (int)$editProgram['id'] : 0 ?>">

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
      <div class="form-group">
        <label for="pTitle">Program Title <span class="required">*</span></label>
        <input type="text" id="pTitle" name="title" class="form-control" 
               placeholder="e.g. Diploma in Computer Operator" 
               value="<?= e($editProgram['title'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label for="pCode">Program Code <span class="required">*</span></label>
        <input type="text" id="pCode" name="code" class="form-control" 
               placeholder="e.g. CIT, DIT, ECE" 
               value="<?= e($editProgram['code'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label for="pSlug">URL / Form Slug <span class="required">*</span></label>
        <input type="text" id="pSlug" name="slug" class="form-control" 
               placeholder="e.g. electrical, computer" 
               value="<?= e($editProgram['slug'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label for="pDuration">Duration <span class="required">*</span></label>
        <input type="text" id="pDuration" name="duration" class="form-control" 
               placeholder="e.g. 3 Years (6 Semesters)" 
               value="<?= e($editProgram['duration'] ?? '3 Years (6 Semesters)') ?>" required>
      </div>

      <div class="form-group">
        <label for="pShift">Shift</label>
        <input type="text" id="pShift" name="shift" class="form-control" 
               placeholder="e.g. Morning / Evening" 
               value="<?= e($editProgram['shift'] ?? 'Morning') ?>">
      </div>

      <div class="form-group">
        <label for="pSeats">Total Seats</label>
        <input type="number" id="pSeats" name="total_seats" class="form-control" min="1" max="1000"
               value="<?= (int)($editProgram['total_seats'] ?? 50) ?>">
      </div>

      <div class="form-group">
        <label for="pFee">Fee Per Semester (PKR)</label>
        <input type="number" id="pFee" name="fee_per_semester" class="form-control" min="0" step="100"
               value="<?= (float)($editProgram['fee_per_semester'] ?? 0) ?>">
      </div>

      <div class="form-group">
        <label for="pSort">Sort Order</label>
        <input type="number" id="pSort" name="sort_order" class="form-control" 
               value="<?= (int)($editProgram['sort_order'] ?? 1) ?>">
      </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.25rem;">
      <label for="pEligibility">Eligibility Criteria</label>
      <input type="text" id="pEligibility" name="eligibility" class="form-control" 
             value="<?= e($editProgram['eligibility'] ?? 'Matriculation (Science) with minimum 45% marks') ?>">
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem;">
      <label for="pDescription">Program Description &amp; Career Scope</label>
      <textarea id="pDescription" name="description" class="form-control" rows="3"
                placeholder="Comprehensive description of the curriculum and trade..."><?= e($editProgram['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem; display:flex; align-items:center; gap:0.5rem;">
      <input type="checkbox" id="pActive" name="is_active" value="1" 
             <?= (!$editProgram || !empty($editProgram['is_active'])) ? 'checked' : '' ?> style="width:18px; height:18px;">
      <label for="pActive" style="margin:0; font-weight:600; cursor:pointer;">
        Program is Active &amp; Open for Admissions
      </label>
    </div>

    <div style="display:flex; gap:0.75rem;">
      <button type="submit" class="btn btn-primary btn-sm" style="padding:0.75rem 1.5rem;">
        <?= $editProgram ? 'Save Changes' : 'Create Program' ?>
      </button>
      <?php if ($editProgram): ?>
        <a href="programs.php" class="btn btn-outline btn-sm" style="padding:0.75rem 1.25rem;">
          Cancel Edit
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
