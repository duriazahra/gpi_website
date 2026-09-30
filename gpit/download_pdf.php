<?php
/**
 * Govt Polytechnic Institute (GPI) - Official Admission Dossier & PDF Generator
 * Renders an official printable/downloadable institutional admission document.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/helpers.php';

start_secure_session();

$appNo = sanitize_string($_GET['app_no'] ?? '');
$cnic = sanitize_string($_GET['cnic'] ?? '');

if (empty($appNo)) {
    http_response_code(400);
    exit('Application Number is required.');
}

try {
    $pdo = get_db_connection();

    // Query application details
    $stmt = $pdo->prepare(
        'SELECT a.*, p.name AS program_name, p.duration AS program_duration,
                p2.name AS second_program_name
         FROM applications a
         LEFT JOIN programs p ON a.program_id = p.id
         LEFT JOIN programs p2 ON a.second_preference_id = p2.id
         WHERE a.application_no = :app_no LIMIT 1'
    );
    $stmt->execute([':app_no' => $appNo]);
    $app = $stmt->fetch();

    if (!$app) {
        http_response_code(404);
        exit('Application record not found.');
    }

    // Access control: if not admin, verify CNIC
    $isAdmin = !empty($_SESSION['admin_id']);
    if (!$isAdmin) {
        // Standardize CNIC comparison
        $cleanParamCnic = preg_replace('/\D/', '', $cnic);
        $cleanDbCnic = preg_replace('/\D/', '', $app['cnic_bform']);

        if (empty($cleanParamCnic) || $cleanParamCnic !== $cleanDbCnic) {
            http_response_code(403);
            exit('Access Denied. CNIC verification is required to download this application dossier.');
        }
    }

    // Retrieve candidate photo
    $docStmt = $pdo->prepare(
        'SELECT file_path FROM application_documents 
         WHERE application_id = :app_id AND document_type = "photo" 
         LIMIT 1'
    );
    $docStmt->execute([':app_id' => $app['id']]);
    $photoPath = $docStmt->fetchColumn();

} catch (Throwable $e) {
    http_response_code(500);
    exit('An error occurred while generating the application dossier.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Official Admission Form &bull; <?= e($app['application_no']) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background-color: #f1f5f9; color: #0f172a; font-family: 'Inter', system-ui, -apple-system, sans-serif; padding: 2rem 1rem; }
    .dossier-page { max-width: 850px; margin: 0 auto; background: #fff; padding: 3rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #cbd5e1; }
    .dossier-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double #1e3a8a; padding-bottom: 1.25rem; margin-bottom: 1.5rem; }
    .dossier-header-left { display: flex; align-items: center; gap: 1.25rem; }
    .dossier-logo { width: 75px; height: 75px; object-fit: contain; }
    .dossier-inst-title { font-size: 1.35rem; font-weight: 800; color: #1e3a8a; margin: 0; line-height: 1.2; }
    .dossier-sub { font-size: 0.85rem; color: #475569; margin-top: 0.25rem; font-weight: 600; }
    .dossier-meta-box { text-align: right; }
    .dossier-app-no { font-size: 1.2rem; font-weight: 800; color: #b45309; font-family: monospace; }
    
    .dossier-photo-wrap { width: 120px; height: 145px; border: 2px dashed #94a3b8; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden; }
    .dossier-photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
    
    .section-banner { background: #1e3a8a; color: #fff; padding: 0.35rem 0.75rem; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 4px; margin: 1.5rem 0 0.75rem 0; }
    
    .dossier-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 0.9rem; }
    .dossier-table th, .dossier-table td { border: 1px solid #cbd5e1; padding: 0.55rem 0.75rem; text-align: left; }
    .dossier-table th { background: #f8fafc; color: #334155; font-weight: 600; width: 28%; }
    
    .declaration-box { background: #f8fafc; border: 1px solid #cbd5e1; border-left: 4px solid #1e3a8a; padding: 1rem; border-radius: 4px; font-size: 0.82rem; color: #334155; line-height: 1.5; margin-top: 1.5rem; }
    .signature-row { display: flex; justify-content: space-between; margin-top: 3.5rem; padding-top: 1rem; }
    .signature-block { width: 220px; text-align: center; border-top: 1px solid #64748b; font-size: 0.85rem; color: #475569; font-weight: 600; padding-top: 0.35rem; }
    
    .action-bar { max-width: 850px; margin: 0 auto 1.5rem auto; display: flex; justify-content: space-between; align-items: center; }
    
    @media print {
      body { background: #fff; padding: 0; }
      .action-bar { display: none !important; }
      .dossier-page { box-shadow: none; border: none; padding: 0; max-width: 100%; }
      .section-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #1e3a8a !important; color: #fff !important; }
      .dossier-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #f1f5f9 !important; }
    }
  </style>
</head>
<body>
  <div class="action-bar">
    <a href="admissions.html" class="btn btn-outline">&larr; Return to Admissions</a>
    <div style="display: flex; gap: 0.75rem;">
      <a href="status.php?app_no=<?= urlencode($app['application_no']) ?>&cnic=<?= urlencode($app['cnic_bform']) ?>" class="btn btn-outline">Check Status</a>
      <button onclick="window.print()" class="btn btn-gold">&#128438; Print / Save as PDF</button>
    </div>
  </div>

  <article class="dossier-page">
    <header class="dossier-header">
      <div class="dossier-header-left">
        <img src="images/logo.jpg" alt="GPI Logo" class="dossier-logo">
        <div>
          <h1 class="dossier-inst-title">GOVERNMENT POLYTECHNIC INSTITUTE</h1>
          <div class="dossier-sub">Directorate of Technical Education &bull; Gilgit-Baltistan</div>
          <div style="font-size: 0.8rem; color: #64748b;">Accredited by PBTE &bull; NAVTTC Recognized &bull; <?= e($app['session_year']) ?></div>
        </div>
      </div>
      <div class="dossier-meta-box">
        <div style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700;">Application Ref ID</div>
        <div class="dossier-app-no"><?= e($app['application_no']) ?></div>
        <div style="margin-top: 0.4rem;">
          <?= get_status_badge($app['status']) ?>
        </div>
      </div>
    </header>

    <div style="display: flex; gap: 1.5rem; align-items: flex-start;">
      <div style="flex-grow: 1;">
        <div class="section-banner" style="margin-top: 0;">Applied Engineering Program</div>
        <table class="dossier-table">
          <tr>
            <th>Primary Preference</th>
            <td><strong style="color:#1e3a8a; font-size:1rem;"><?= e($app['program_name']) ?></strong> (<?= e($app['program_duration']) ?>)</td>
          </tr>
          <?php if (!empty($app['second_program_name'])): ?>
          <tr>
            <th>Second Preference</th>
            <td><?= e($app['second_program_name']) ?></td>
          </tr>
          <?php endif; ?>
          <tr>
            <th>Submission Date</th>
            <td><?= format_date($app['created_at'], 'd F Y, h:i A') ?></td>
          </tr>
        </table>
      </div>

      <div class="dossier-photo-wrap">
        <?php if ($photoPath && file_exists(__DIR__ . '/' . $photoPath)): ?>
          <?php 
            // Encode image as base64 data URI for 100% reliable standalone printing / offline PDF viewing
            $mime = mime_content_type(__DIR__ . '/' . $photoPath);
            $b64 = base64_encode(file_get_contents(__DIR__ . '/' . $photoPath));
          ?>
          <img src="data:<?= $mime ?>;base64,<?= $b64 ?>" alt="Candidate Photograph">
        <?php else: ?>
          <div style="text-align: center; color: #94a3b8; font-size: 0.75rem; padding: 0.5rem;">
            Affix Passport Size Photo
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Personal Information -->
    <div class="section-banner">1. Personal Information</div>
    <table class="dossier-table">
      <tr>
        <th>Full Name</th>
        <td><strong><?= e($app['full_name']) ?></strong></td>
        <th>Father's Name</th>
        <td><?= e($app['father_name']) ?></td>
      </tr>
      <tr>
        <th>Date of Birth</th>
        <td><?= format_date($app['date_of_birth'], 'd F Y') ?></td>
        <th>Gender</th>
        <td><?= e($app['gender']) ?></td>
      </tr>
      <tr>
        <th>CNIC / B-Form No.</th>
        <td><strong style="font-family: monospace; letter-spacing:0.05em;"><?= e($app['cnic_bform']) ?></strong></td>
        <th>Mobile / WhatsApp</th>
        <td><?= e($app['mobile']) ?></td>
      </tr>
      <tr>
        <th>Email Address</th>
        <td><?= e($app['email']) ?></td>
        <th>City / District</th>
        <td><?= e($app['city']) ?></td>
      </tr>
      <tr>
        <th>Permanent Address</th>
        <td colspan="3"><?= e($app['address']) ?></td>
      </tr>
    </table>

    <!-- Academic Information -->
    <div class="section-banner">2. Academic Record (SSC / Matriculation)</div>
    <table class="dossier-table">
      <tr>
        <th>Last Qualification</th>
        <td><?= e($app['qualification']) ?></td>
        <th>Passing Year</th>
        <td><?= e((string)$app['passing_year']) ?></td>
      </tr>
      <tr>
        <th>Board / Body</th>
        <td><?= e($app['board'] ?: 'FBISE / Relevant BISE') ?></td>
        <th>Previous School</th>
        <td><?= e($app['previous_institution']) ?></td>
      </tr>
      <tr>
        <th>Total Marks</th>
        <td><?= e((string)number_format((float)$app['total_marks'], 0)) ?></td>
        <th>Obtained Marks</th>
        <td><?= e((string)number_format((float)$app['obtained_marks'], 0)) ?></td>
      </tr>
      <tr>
        <th>Verified Percentage</th>
        <td colspan="3"><strong style="color:#047857; font-size:1.05rem;"><?= number_format((float)$app['percentage'], 2) ?>%</strong> (Calculated by Institutional System)</td>
      </tr>
    </table>

    <!-- Statement of Purpose -->
    <?php if (!empty($app['reason'])): ?>
      <div class="section-banner">3. Reason for Joining GPI</div>
      <div style="font-size: 0.88rem; color: #334155; line-height: 1.5; padding: 0.5rem 0.25rem;">
        <?= nl2br(e($app['reason'])) ?>
      </div>
    <?php endif; ?>

    <!-- Candidate Declaration -->
    <div class="declaration-box">
      <strong>Institutional Declaration:</strong><br>
      I hereby solemnly declare that all information furnished in this admission application is true, correct, and complete matching my matric result card and NADRA computerized identity records. I agree to strictly abide by the rules, regulations, and code of conduct of Government Polytechnic Institute.
    </div>

    <!-- Institutional Signatures -->
    <div class="signature-row">
      <div class="signature-block">
        Candidate's Signature
      </div>
      <div class="signature-block">
        Scrutiny Officer / Verifier
      </div>
      <div class="signature-block">
        Principal / Chairman Admissions
      </div>
    </div>

    <footer style="margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 0.75rem; font-size: 0.75rem; color: #94a3b8; display: flex; justify-content: space-between;">
      <div>Generated electronically on <?= date('d M Y, h:i A') ?> &bull; System Verified</div>
      <div>Government Polytechnic Institute Admissions Management Portal</div>
    </footer>
  </article>
</body>
</html>
