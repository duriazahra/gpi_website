<?php
/**
 * Govt Polytechnic Institute (GPI) - Institutional System Settings
 * Master control panel for admissions toggles, deadlines, contact information, and upload thresholds.
 * Restricted to Super Administrators.
 */

declare(strict_types=1);

$pageTitle = 'Institutional Settings';
$activeNav = 'settings';

require_once __DIR__ . '/header.php';

// Enforce Super Admin Role
$currentAdmin = require_super_admin();
$pdo = get_db_connection();

// Handle Save POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $admissionsOpen = isset($_POST['admissions_open']) ? '1' : '0';
    $deadline       = sanitize_string($_POST['admission_deadline'] ?? '');
    $sessionYear    = sanitize_string($_POST['admission_session_year'] ?? '2026');
    $instituteName  = sanitize_string($_POST['institute_name'] ?? 'Government Polytechnic Institute');
    $institutePhone = sanitize_string($_POST['institute_phone'] ?? '(058159) 41113');
    $instituteEmail = sanitize_string($_POST['institute_email'] ?? 'info@gpi.edu.pk');
    $instituteAddr  = sanitize_string($_POST['institute_address'] ?? 'Main Shigar Road, Thorgu, Skardu, Gilgit-Baltistan');
    $bannerText     = sanitize_string($_POST['notification_banner_text'] ?? '');
    $maxUploadMb    = max(1, min(20, (int)($_POST['max_upload_size_mb'] ?? 5)));

    $settingsToUpdate = [
        'admissions_open'          => $admissionsOpen,
        'admission_deadline'       => $deadline ? date('Y-m-d H:i:s', strtotime($deadline)) : '',
        'admission_session_year'   => $sessionYear,
        'institute_name'           => $instituteName,
        'institute_phone'          => $institutePhone,
        'institute_email'          => $instituteEmail,
        'institute_address'        => $instituteAddr,
        'notification_banner_text' => $bannerText,
        'max_upload_size_mb'       => (string)$maxUploadMb
    ];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (:key, :val, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([
                ':key'  => $key,
                ':val'  => $val,
                ':val2' => $val
            ]);
        }

        log_admin_action($pdo, $currentAdmin['id'], 'UPDATE_SETTINGS', 'settings', null, $settingsToUpdate);

        $pdo->commit();
        $_SESSION['flash_success'] = 'Institutional settings and admissions parameters updated successfully.';
        header('Location: settings.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Fetch all current settings
$settings = [];
try {
    $res = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    foreach ($res as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Throwable $e) {}

$admissionsOpen = ($settings['admissions_open'] ?? '1') === '1';
$deadlineVal    = $settings['admission_deadline'] ?? '2026-09-30 23:59:59';
$formattedDeadline = !empty($deadlineVal) ? date('Y-m-d\TH:i', strtotime($deadlineVal)) : '';
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <h2>Institutional Portal Configuration</h2>
    <p>Manage admissions availability, application deadlines, campus credentials, and upload rules</p>
  </div>
</div>

<form method="POST" action="settings.php" style="max-width: 900px;">
  <?= csrf_field() ?>

  <!-- Admissions Controls Card -->
  <div class="stat-card" style="margin-bottom: 1.75rem; padding: 1.75rem;">
    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.25rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.75rem;">
      <span style="font-size:1.3rem;">🎓</span>
      <h3 style="font-size:1.2rem; margin:0;">Admissions &amp; Intake Controls</h3>
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem; background:#f8fafc; padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--slate-200);">
      <div style="display:flex; align-items:center; gap:0.75rem;">
        <input type="checkbox" id="admissionsOpenCheck" name="admissions_open" value="1" 
               <?= $admissionsOpen ? 'checked' : '' ?> style="width:20px; height:20px; cursor:pointer;">
        <div>
          <label for="admissionsOpenCheck" style="font-weight:700; font-size:1rem; margin:0; cursor:pointer;">
            Enable Online Application Intake
          </label>
          <div style="font-size:0.82rem; color:var(--slate-500); margin-top:0.2rem;">
            When unchecked, prospective applicants will receive a notice that admissions are closed and form submission will be blocked.
          </div>
        </div>
      </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
      <div class="form-group">
        <label for="deadlineInput">Intake Deadline Date &amp; Time</label>
        <input type="datetime-local" id="deadlineInput" name="admission_deadline" class="form-control"
               value="<?= e($formattedDeadline) ?>">
        <div style="font-size:0.78rem; color:var(--slate-500); margin-top:0.25rem;">
          Submissions automatically halt when this timestamp passes.
        </div>
      </div>

      <div class="form-group">
        <label for="sessionYearInput">Active Session Year</label>
        <input type="text" id="sessionYearInput" name="admission_session_year" class="form-control"
               value="<?= e($settings['admission_session_year'] ?? '2026') ?>" required>
        <div style="font-size:0.78rem; color:var(--slate-500); margin-top:0.25rem;">
          Used for application numbering prefix (e.g. GPI-2026-XXXXXX).
        </div>
      </div>
    </div>

    <div class="form-group">
      <label for="bannerTextInput">Portal Announcement / Alert Banner</label>
      <input type="text" id="bannerTextInput" name="notification_banner_text" class="form-control"
             value="<?= e($settings['notification_banner_text'] ?? '') ?>"
             placeholder="e.g. Online admissions for session 2026-2027 are officially underway.">
      <div style="font-size:0.78rem; color:var(--slate-500); margin-top:0.25rem;">
        Displayed as a priority headline banner at the top of the admissions page.
      </div>
    </div>
  </div>

  <!-- Institutional Identity Card -->
  <div class="stat-card" style="margin-bottom: 1.75rem; padding: 1.75rem;">
    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.25rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.75rem;">
      <span style="font-size:1.3rem;">🏛️</span>
      <h3 style="font-size:1.2rem; margin:0;">Institute Contact Information</h3>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
      <div class="form-group">
        <label for="instName">Official Institute Name <span class="required">*</span></label>
        <input type="text" id="instName" name="institute_name" class="form-control"
               value="<?= e($settings['institute_name'] ?? 'Government Polytechnic Institute') ?>" required>
      </div>

      <div class="form-group">
        <label for="instPhone">Helpline Phone Number <span class="required">*</span></label>
        <input type="text" id="instPhone" name="institute_phone" class="form-control"
               value="<?= e($settings['institute_phone'] ?? '(058159) 41113') ?>" required>
      </div>

      <div class="form-group">
        <label for="instEmail">Official Inquiries Email <span class="required">*</span></label>
        <input type="email" id="instEmail" name="institute_email" class="form-control"
               value="<?= e($settings['institute_email'] ?? 'info@gpi.edu.pk') ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label for="instAddr">Physical Campus Location Address <span class="required">*</span></label>
      <textarea id="instAddr" name="institute_address" class="form-control" rows="2" required><?= e($settings['institute_address'] ?? 'Main Shigar Road, Thorgu, Skardu, Gilgit-Baltistan') ?></textarea>
    </div>
  </div>

  <!-- Upload Constraints Card -->
  <div class="stat-card" style="margin-bottom: 2rem; padding: 1.75rem;">
    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.25rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.75rem;">
      <span style="font-size:1.3rem;">🔒</span>
      <h3 style="font-size:1.2rem; margin:0;">Upload Thresholds &amp; File Constraints</h3>
    </div>

    <div style="max-width:300px;">
      <div class="form-group">
        <label for="maxUploadSize">Max File Upload Size per Document (MB)</label>
        <input type="number" id="maxUploadSize" name="max_upload_size_mb" class="form-control" min="1" max="20"
               value="<?= (int)($settings['max_upload_size_mb'] ?? 5) ?>">
        <div style="font-size:0.78rem; color:var(--slate-500); margin-top:0.25rem;">
          Allowed types: JPG, JPEG, PNG, PDF (Enforced via MIME inspection).
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-lg" style="padding:0.9rem 2.25rem;">
    Save Institutional Configuration &rarr;
  </button>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
