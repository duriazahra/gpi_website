<?php
/**
 * Govt Polytechnic Institute (GPI) - Application Review Dossier
 * Comprehensive candidate scrutiny, document inspection, status transition with mandatory notes,
 * and immutable status audit timeline.
 */

declare(strict_types=1);

$pageTitle = 'Application Review Dossier';
$activeNav = 'applications';

require_once __DIR__ . '/header.php';

$appId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($appId <= 0) {
    header('Location: applications.php');
    exit;
}

$pdo = get_db_connection();

// Handle Status Change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    verify_csrf_or_die();

    $newStatus = sanitize_string($_POST['new_status'] ?? '');
    $adminNotes = sanitize_string($_POST['admin_notes'] ?? '');

    $allowedStatuses = ['pending', 'under_review', 'verified', 'accepted', 'rejected'];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        $_SESSION['flash_error'] = 'Invalid status selected.';
        header("Location: application.php?id=$appId");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Fetch current status
        $curStmt = $pdo->prepare("SELECT status, application_number FROM applications WHERE id = :id FOR UPDATE");
        $curStmt->execute([':id' => $appId]);
        $currentRecord = $curStmt->fetch();

        if (!$currentRecord) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Application record not found.';
            header('Location: applications.php');
            exit;
        }

        $oldStatus = (string)$currentRecord['status'];
        $appNumber = (string)$currentRecord['application_number'];

        // Update application
        $updateStmt = $pdo->prepare("
            UPDATE applications 
            SET status = :status, 
                admin_notes = :notes, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':status' => $newStatus,
            ':notes'  => $adminNotes,
            ':id'     => $appId
        ]);

        // Insert into history table
        $histStmt = $pdo->prepare("
            INSERT INTO application_status_history 
                (application_id, old_status, new_status, changed_by_admin_id, notes, created_at)
            VALUES 
                (:app_id, :old_status, :new_status, :admin_id, :notes, CURRENT_TIMESTAMP)
        ");
        $histStmt->execute([
            ':app_id'     => $appId,
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':admin_id'   => $currentAdmin['id'],
            ':notes'      => $adminNotes ?: 'Status changed by administrative staff.'
        ]);

        // System audit log
        log_admin_action($pdo, $currentAdmin['id'], 'UPDATE_STATUS', 'applications', $appId, [
            'app_number' => $appNumber,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes'      => $adminNotes
        ]);

        $pdo->commit();
        $_SESSION['flash_success'] = "Application {$appNumber} status successfully updated to " . strtoupper(str_replace('_', ' ', $newStatus)) . ".";
        header("Location: application.php?id=$appId");
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = 'Failed to update application status: ' . $e->getMessage();
        header("Location: application.php?id=$appId");
        exit;
    }
}

// Fetch application record
$app = null;
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.title as program_title, p.code as program_code, p.duration as program_duration, p.shift as program_shift
        FROM applications a
        JOIN programs p ON a.program_id = p.id
        WHERE a.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $appId]);
    $app = $stmt->fetch();
} catch (Throwable $e) {}

if (!$app) {
    $_SESSION['flash_error'] = 'Application record not found.';
    header('Location: applications.php');
    exit;
}

// Fetch attached documents
$documents = [];
try {
    $docStmt = $pdo->prepare("
        SELECT id, document_type, file_name, file_size, mime_type, uploaded_at 
        FROM application_documents 
        WHERE application_id = :id 
        ORDER BY id ASC
    ");
    $docStmt->execute([':id' => $appId]);
    $documents = $docStmt->fetchAll();
} catch (Throwable $e) {}

// Fetch status history timeline
$history = [];
try {
    $histQuery = $pdo->prepare("
        SELECT h.*, adm.name as admin_name, adm.role as admin_role
        FROM application_status_history h
        LEFT JOIN admins adm ON h.changed_by_admin_id = adm.id
        WHERE h.application_id = :id
        ORDER BY h.created_at DESC
    ");
    $histQuery->execute([':id' => $appId]);
    $history = $histQuery->fetchAll();
} catch (Throwable $e) {}

// Human readable labels for document types
$docLabels = [
    'photo'       => 'Passport Size Photograph',
    'cnic_front'  => 'Applicant CNIC / B-Form (Front)',
    'cnic_back'   => 'Applicant CNIC (Back)',
    'matric_card' => 'Matric / SSC Result Card',
    'domicile'    => 'Domicile Certificate'
];
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
      <h2>Application <?= e($app['application_number']) ?></h2>
      <span class="badge badge-<?= e($app['status']) ?>" style="font-size:0.85rem; padding:0.35rem 0.85rem;">
        <?= e(str_replace('_', ' ', $app['status'])) ?>
      </span>
    </div>
    <p>Candidate: <strong><?= e($app['full_name']) ?></strong> &bull; Applied: <?= date('d M Y, h:i A', strtotime($app['created_at'])) ?></p>
  </div>

  <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
    <a href="../download_pdf.php?app_no=<?= urlencode($app['application_number']) ?>&cnic=<?= urlencode($app['cnic']) ?>" 
       target="_blank" class="btn btn-outline btn-sm" style="background:#fff;">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Print / Download Official PDF
    </a>
    <a href="applications.php" class="btn btn-primary btn-sm">
      &larr; Return to Applications
    </a>
  </div>
</div>

<div style="display:grid; grid-template-columns: 2.1fr 1fr; gap: 1.5rem;">
  
  <!-- Left Column: Dossier Details & Documents -->
  <div>
    <!-- Academic & Program Summary Card -->
    <div class="stat-card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
      <h3 style="font-size:1.15rem; margin-bottom:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.6rem;">
        Program &amp; Academic Qualification
      </h3>
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Applied Program</div>
          <div style="font-weight:700; font-size:1.05rem; color:var(--primary-900);"><?= e($app['program_title']) ?></div>
          <div style="font-size:0.8rem; color:var(--slate-600);"><?= e($app['program_code']) ?> &bull; <?= e($app['program_duration']) ?> &bull; <?= e($app['program_shift']) ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">SSC / Matric Score</div>
          <div style="font-weight:800; font-size:1.35rem; color:#15803d;">
            <?= number_format((float)$app['matric_percentage'], 2) ?>%
          </div>
          <div style="font-size:0.82rem; color:var(--slate-600);">
            <strong><?= (int)$app['matric_obtained_marks'] ?></strong> Marks out of <strong><?= (int)$app['matric_total_marks'] ?></strong>
          </div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Examination Board</div>
          <div style="font-weight:600; font-size:0.95rem;"><?= e($app['matric_board']) ?></div>
          <div style="font-size:0.8rem; color:var(--slate-600);">Roll #: <strong><?= e($app['matric_roll_number']) ?></strong> &bull; Year: <?= e($app['matric_passing_year']) ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Academic Session</div>
          <div style="font-weight:600; font-size:0.95rem;"><?= e($app['session_year']) ?></div>
          <div style="font-size:0.8rem; color:var(--slate-600);">Submitted: <?= date('d M Y', strtotime($app['created_at'])) ?></div>
        </div>
      </div>
    </div>

    <!-- Personal & Demographics Card -->
    <div class="stat-card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
      <h3 style="font-size:1.15rem; margin-bottom:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.6rem;">
        Candidate Demographics &amp; Contact Details
      </h3>
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Full Name</div>
          <div style="font-weight:700; font-size:0.95rem;"><?= e($app['full_name']) ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Father's Name</div>
          <div style="font-weight:600; font-size:0.95rem;"><?= e($app['father_name']) ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Candidate CNIC / B-Form</div>
          <div style="font-family:var(--font-mono); font-weight:700; color:var(--primary-800); font-size:0.95rem;"><?= e($app['cnic']) ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Date of Birth &amp; Gender</div>
          <div style="font-weight:600; font-size:0.95rem;"><?= date('d M Y', strtotime($app['date_of_birth'])) ?> (<?= ucfirst(e($app['gender'])) ?>)</div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Mobile Phone</div>
          <div style="font-family:var(--font-mono); font-weight:600; font-size:0.95rem;"><?= e($app['phone']) ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Guardian / Secondary Contact</div>
          <div style="font-family:var(--font-mono); font-size:0.95rem;"><?= e($app['guardian_phone'] ?: 'N/A') ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Email Address</div>
          <div style="font-size:0.92rem;"><?= e($app['email'] ?: 'None provided') ?></div>
        </div>

        <div>
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Domicile District</div>
          <div style="font-weight:600; font-size:0.92rem;"><?= e($app['domicile_district'] ?: 'N/A') ?></div>
        </div>

        <div style="grid-column: 1 / -1;">
          <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Permanent Address</div>
          <div style="font-size:0.92rem; color:var(--slate-800); margin-top:0.2rem;">
            <?= nl2br(e($app['address'])) ?>, <?= e($app['city']) ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Attached Documents Card -->
    <div class="stat-card" style="padding: 1.5rem;">
      <h3 style="font-size:1.15rem; margin-bottom:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.6rem;">
        Applicant Submitted Documents
      </h3>
      <p style="font-size:0.84rem; color:var(--slate-600); margin-bottom:1.25rem;">
        Click below to inspect credentials. All files are securely streamed via the administrative document gateway.
      </p>

      <?php if (empty($documents)): ?>
        <div style="padding:1.5rem; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px; text-align:center; color:var(--slate-500);">
          No digital documents attached to this application record.
        </div>
      <?php else: ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;">
          <?php foreach ($documents as $doc): ?>
            <div style="border:1px solid var(--slate-200); border-radius:var(--radius-md); padding:1rem; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div style="font-weight:700; font-size:0.88rem; color:var(--slate-900); margin-bottom:0.35rem;">
                  <?= e($docLabels[$doc['document_type']] ?? ucwords(str_replace('_', ' ', $doc['document_type']))) ?>
                </div>
                <div style="font-size:0.76rem; color:var(--slate-500); word-break:break-all;">
                  <?= e($doc['file_name']) ?>
                </div>
                <div style="font-size:0.72rem; color:var(--slate-400); margin-top:0.25rem;">
                  <?= strtoupper(e(explode('/', $doc['mime_type'])[1] ?? 'FILE')) ?> &bull; <?= round((int)$doc['file_size'] / 1024, 1) ?> KB
                </div>
              </div>

              <div style="margin-top:1rem;">
                <a href="document.php?id=<?= (int)$doc['id'] ?>" target="_blank" 
                   class="btn btn-outline btn-sm" style="width:100%; font-size:0.8rem; padding:0.4rem 0.6rem; background:#fff;">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  View Document &nearr;
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right Column: Status Transition Panel & Audit History -->
  <div>
    <!-- Status Update Form Card -->
    <div class="stat-card" style="margin-bottom: 1.5rem; padding: 1.5rem; border-top: 4px solid var(--primary-700);">
      <h3 style="font-size:1.15rem; margin-bottom:0.5rem;">Update Status</h3>
      <p style="font-size:0.82rem; color:var(--slate-500); margin-bottom:1.25rem;">
        Every status transition requires a recorded administrative note and is permanently audited.
      </p>

      <form method="POST" action="application.php?id=<?= $appId ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_status">

        <div class="form-group" style="margin-bottom:1.25rem;">
          <label for="newStatusSelect" style="font-size:0.82rem; font-weight:700; text-transform:uppercase; color:var(--slate-700);">
            Application Status <span class="required">*</span>
          </label>
          <select id="newStatusSelect" name="new_status" class="form-control" required style="font-weight:600;">
            <option value="pending" <?= $app['status'] === 'pending' ? 'selected' : '' ?>>Pending Review</option>
            <option value="under_review" <?= $app['status'] === 'under_review' ? 'selected' : '' ?>>Under Review</option>
            <option value="verified" <?= $app['status'] === 'verified' ? 'selected' : '' ?>>Documents Verified</option>
            <option value="accepted" <?= $app['status'] === 'accepted' ? 'selected' : '' ?>>Accepted / Merit List</option>
            <option value="rejected" <?= $app['status'] === 'rejected' ? 'selected' : '' ?>>Rejected / Ineligible</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom:1.5rem;">
          <label for="adminNotesInput" style="font-size:0.82rem; font-weight:700; text-transform:uppercase; color:var(--slate-700);">
            Administrative Scrutiny Note <span class="required">*</span>
          </label>
          <textarea id="adminNotesInput" name="admin_notes" class="form-control" rows="4" 
                    placeholder="Provide justification, e.g.: 'Original SSC result card verified against BISE portal. All admission criteria met.'" required><?= e($app['admin_notes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-sm" style="width:100%; padding:0.65rem 1rem;">
          Save Status Transition &rarr;
        </button>
      </form>
    </div>

    <!-- Status History Timeline Card -->
    <div class="stat-card" style="padding: 1.5rem;">
      <h3 style="font-size:1.15rem; margin-bottom:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.6rem;">
        Status Audit Trail
      </h3>

      <?php if (empty($history)): ?>
        <p style="font-size:0.85rem; color:var(--slate-500);">No previous status transitions recorded.</p>
      <?php else: ?>
        <div style="position:relative; padding-left:1.25rem; border-left:2px solid var(--slate-200);">
          <?php foreach ($history as $h): ?>
            <div style="position:relative; margin-bottom:1.5rem;">
              <!-- Timeline bullet -->
              <div style="position:absolute; left:-1.65rem; top:2px; width:12px; height:12px; border-radius:50%; background:var(--primary-700); border:2px solid #fff;"></div>
              
              <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.25rem;">
                <span class="badge badge-<?= e($h['new_status']) ?>" style="font-size:0.7rem;">
                  <?= e(str_replace('_', ' ', $h['new_status'])) ?>
                </span>
                <span style="font-size:0.72rem; color:var(--slate-500);">
                  <?= date('d M Y, h:i A', strtotime($h['created_at'])) ?>
                </span>
              </div>

              <div style="font-size:0.82rem; margin-top:0.4rem; color:var(--slate-800); line-height:1.4;">
                <?= nl2br(e($h['notes'])) ?>
              </div>

              <div style="font-size:0.72rem; color:var(--slate-500); margin-top:0.35rem;">
                By: <strong><?= e($h['admin_name'] ?: 'System') ?></strong> (<?= e($h['admin_role'] ?: 'Admin') ?>)
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
