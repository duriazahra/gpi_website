<?php
/**
 * Govt Polytechnic Institute (GPI) - Application Status Portal
 * Public interface allowing applicants to verify their admission application status.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/helpers.php';

$appNoParam = sanitize_string($_GET['app_no'] ?? '');
$cnicParam = sanitize_string($_GET['cnic'] ?? '');

$appData = null;
$searchError = '';

if (!empty($appNoParam) && !empty($cnicParam)) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            'SELECT a.*, p.name AS program_name 
             FROM applications a
             LEFT JOIN programs p ON a.program_id = p.id
             WHERE a.application_no = :app_no 
             LIMIT 1'
        );
        $stmt->execute([':app_no' => $appNoParam]);
        $found = $stmt->fetch();

        if ($found) {
            $cleanParamCnic = preg_replace('/\D/', '', $cnicParam);
            $cleanDbCnic = preg_replace('/\D/', '', $found['cnic_bform']);

            if ($cleanParamCnic === $cleanDbCnic) {
                $appData = $found;
            } else {
                $searchError = 'The CNIC / B-Form number does not match this Application Reference ID.';
            }
        } else {
            $searchError = 'No application record found for Application Number: ' . e($appNoParam);
        }
    } catch (Throwable $e) {
        $searchError = 'Unable to check status at this moment. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check Application Status &bull; Govt Polytechnic Institute</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .status-search-card {
      background: #fff;
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      border: 1px solid var(--slate-200);
      box-shadow: var(--shadow-sm);
      max-width: 650px;
      margin: 0 auto 3rem auto;
    }
    .status-result-box {
      background: #fff;
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      border: 1px solid var(--slate-200);
      border-left: 5px solid var(--primary-700);
      box-shadow: var(--shadow-md);
      max-width: 650px;
      margin: 0 auto;
    }
    .result-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.85rem 0;
      border-bottom: 1px solid var(--slate-100);
    }
    .result-row:last-child {
      border-bottom: none;
    }
    .result-label {
      color: var(--slate-600);
      font-size: 0.92rem;
      font-weight: 500;
    }
    .result-value {
      font-weight: 700;
      color: var(--primary-900);
      font-size: 0.98rem;
    }
    .status-guidance-box {
      margin-top: 1.5rem;
      padding: 1rem 1.25rem;
      border-radius: var(--radius-sm);
      font-size: 0.88rem;
      line-height: 1.5;
    }
  </style>
</head>
<body>
  <!-- Header matching existing website -->
  <header class="site-header">
    <div class="header-top-bar">
      <div class="container top-bar-inner">
        <div class="top-bar-contact">
          <span>&#128222; Admissions Cell: (058159) 41113</span>
          <span>&#9993; gbdtesd@gmail.com</span>
        </div>
        <div class="top-bar-notice">
          <span>Official Portal &bull; Session 2026-27</span>
        </div>
      </div>
    </div>

    <nav class="main-navigation" aria-label="Primary Navigation">
      <div class="container nav-container">
        <a href="index.html" class="brand-logo" aria-label="GPI Home">
          <img src="images/logo.jpg" alt="Govt Polytechnic Institute Logo" width="52" height="52">
          <div class="brand-text">
            <span class="brand-title">Govt Polytechnic Institute</span>
            <span class="brand-sub">Technical Education Directorate &bull; GB</span>
          </div>
        </a>

        <ul class="nav-menu">
          <li><a href="index.html" class="nav-link">Home</a></li>
          <li><a href="about.html" class="nav-link">About Us</a></li>
          <li><a href="programs.html" class="nav-link">Programs</a></li>
          <li><a href="admissions.html" class="nav-link">Admissions</a></li>
          <li><a href="gallery.html" class="nav-link">Gallery</a></li>
          <li><a href="contact.html" class="nav-link">Contact</a></li>
        </ul>

        <div class="nav-actions">
          <a href="admissions.html#admission-form-section" class="btn btn-gold btn-sm btn-apply-nav">Apply Now</a>
          <button type="button" class="mobile-toggle" aria-label="Open mobile navigation" aria-expanded="false">
            <svg viewBox="0 0 24 24">
              <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z" />
            </svg>
          </button>
        </div>
      </div>
    </nav>
  </header>

  <!-- Mobile Navigation Drawer -->
  <div class="mobile-nav-overlay" aria-hidden="true"></div>
  <aside class="mobile-nav-drawer" aria-label="Mobile Navigation Menu">
    <div class="mobile-drawer-header">
      <div class="brand">
        <img src="images/logo.jpg" alt="GPI Logo" width="40" height="40">
        <span class="brand-title" style="font-size: 1.05rem;">GPI Skardu</span>
      </div>
      <button type="button" class="mobile-drawer-close" aria-label="Close menu">&times;</button>
    </div>
    <ul class="mobile-nav-links">
      <li><a href="index.html" class="nav-link">Home</a></li>
      <li><a href="about.html" class="nav-link">About Us</a></li>
      <li><a href="programs.html" class="nav-link">Technical Programs</a></li>
      <li><a href="admissions.html" class="nav-link">Admissions 2026</a></li>
      <li><a href="gallery.html" class="nav-link">Campus Gallery</a></li>
      <li><a href="contact.html" class="nav-link">Contact &amp; Location</a></li>
    </ul>
    <div class="mobile-drawer-footer">
      <a href="admissions.html#admission-form-section" class="btn btn-gold" style="width: 100%;">Apply for Admission</a>
    </div>
  </aside>

  <main id="main-content">
    <!-- Breadcrumb Header -->
    <section class="page-hero">
      <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="index.html">Home</a>
          <span class="breadcrumb-separator">&rsaquo;</span>
          <a href="admissions.html">Admissions</a>
          <span class="breadcrumb-separator">&rsaquo;</span>
          <span class="breadcrumb-current">Application Status</span>
        </nav>
        <h1 class="page-hero-title">Check Application Status</h1>
        <p class="page-hero-lead">
          Track the verification progress, scrutiny review, and merit status of your online admission application.
        </p>
      </div>
    </section>

    <section class="section section-light" aria-label="Status Verification Form">
      <div class="container">
        
        <!-- Status Search Form -->
        <div class="status-search-card reveal-on-scroll">
          <div style="text-align: center; margin-bottom: 2rem;">
            <span class="section-badge badge-gold">Candidate Portal</span>
            <h2 style="font-size: 1.5rem; color: var(--primary-900); margin-top: 0.5rem;">Track Admission Status</h2>
            <p style="font-size: 0.92rem; color: var(--slate-600); margin-top: 0.25rem;">
              Enter your official Application Reference ID and your NADRA CNIC or B-Form number.
            </p>
          </div>

          <?php if (!empty($searchError)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 6px; font-size: 0.9rem; margin-bottom: 1.5rem;">
              <strong>Notice:</strong> <?= e($searchError) ?>
            </div>
          <?php endif; ?>

          <form id="statusSearchForm" method="GET" action="status.php" novalidate>
            <div class="form-group" style="margin-bottom: 1.25rem;">
              <label for="inputAppNo">Application Reference Number <span class="required">*</span></label>
              <input type="text" id="inputAppNo" name="app_no" class="form-control" 
                     placeholder="e.g. GPI-2026-000124" 
                     value="<?= e($appNoParam) ?>" required>
              <div class="form-error-msg">Please enter your application number (e.g. GPI-2026-000124).</div>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
              <label for="inputCnic">CNIC / B-Form Number <span class="required">*</span></label>
              <input type="text" id="inputCnic" name="cnic" class="form-control" 
                     placeholder="12345-1234567-1" 
                     value="<?= e($cnicParam) ?>" maxlength="15" required>
              <div class="form-error-msg">Please enter your 13-digit CNIC or B-Form number.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
              Check Application Status &rarr;
            </button>
          </form>
        </div>

        <!-- Status Result Display -->
        <?php if ($appData): ?>
          <div class="status-result-box reveal-on-scroll">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--slate-100); padding-bottom: 1rem; margin-bottom: 1.25rem;">
              <div>
                <span class="section-badge">Verified Record</span>
                <h3 style="font-size: 1.35rem; color: var(--primary-900); margin: 0.25rem 0 0 0;">Application Status</h3>
              </div>
              <div>
                <?= get_status_badge($appData['status']) ?>
              </div>
            </div>

            <div class="result-row">
              <span class="result-label">Application Number:</span>
              <span class="result-value" style="font-family: monospace; font-size: 1.1rem; color: var(--primary-800);"><?= e($appData['application_no']) ?></span>
            </div>

            <div class="result-row">
              <span class="result-label">Applicant Full Name:</span>
              <span class="result-value"><?= e($appData['full_name']) ?></span>
            </div>

            <div class="result-row">
              <span class="result-label">Applied Program:</span>
              <span class="result-value"><?= e($appData['program_name']) ?></span>
            </div>

            <div class="result-row">
              <span class="result-label">Submission Date:</span>
              <span class="result-value"><?= format_date($appData['created_at'], 'd F Y, h:i A') ?></span>
            </div>

            <div class="result-row">
              <span class="result-label">Current Admission Status:</span>
              <span class="result-value"><?= e($appData['status']) ?></span>
            </div>

            <!-- Guidance message based on status -->
            <?php
              $statusGuidance = match ($appData['status']) {
                'Pending' => [
                  'bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#fde68a',
                  'text' => 'Your application has been received and is queued for document scrutiny by the admissions committee. Please check back for updates.'
                ],
                'Under Review' => [
                  'bg' => '#e0f2fe', 'color' => '#0369a1', 'border' => '#bae6fd',
                  'text' => 'Your academic records and credentials are currently being verified against board records.'
                ],
                'Documents Required' => [
                  'bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fecaca',
                  'text' => 'Additional or clearer copies of your credentials are required. Please visit or contact the admissions cell immediately.'
                ],
                'Verified' => [
                  'bg' => '#ecfdf5', 'color' => '#065f46', 'border' => '#a7f3d0',
                  'text' => 'Your documents have been verified and your merit calculation is complete. Await the merit list notification.'
                ],
                'Accepted' => [
                  'bg' => '#fef9c3', 'color' => '#854d0e', 'border' => '#facc15',
                  'text' => 'Congratulations! Your admission has been accepted. Please report to the GPI student affairs office for orientation and fee clearance.'
                ],
                'Rejected' => [
                  'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1',
                  'text' => 'Your application could not be accommodated due to eligibility requirements or seat quotas.'
                ],
                default => [
                  'bg' => '#f8fafc', 'color' => '#334155', 'border' => '#e2e8f0',
                  'text' => 'Your application is progressing through standard administrative evaluation.'
                ]
              };
            ?>
            <div class="status-guidance-box" style="background: <?= $statusGuidance['bg'] ?>; color: <?= $statusGuidance['color'] ?>; border: 1px solid <?= $statusGuidance['border'] ?>;">
              <strong>Status Guidance:</strong> <?= $statusGuidance['text'] ?>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.75rem; flex-wrap: wrap;">
              <a href="download_pdf.php?app_no=<?= urlencode($appData['application_no']) ?>&cnic=<?= urlencode($appData['cnic_bform']) ?>" target="_blank" class="btn btn-gold" style="flex: 1; text-align: center;">
                &#128196; Download Application PDF
              </a>
              <button onclick="window.print()" class="btn btn-outline">Print Status</button>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </section>
  </main>

  <!-- Footer matching existing website -->
  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-col">
        <div class="footer-brand">
          <img src="images/logo.jpg" alt="GPI Logo" width="46" height="46">
          <span class="brand-title" style="font-size: 1.15rem;">Govt Polytechnic Institute</span>
        </div>
        <p class="footer-desc">
          Govt Polytechnic Institute is committed to nurturing technical expertise, professional integrity, and
          practical engineering skills for national progress.
        </p>
      </div>

      <div class="footer-col">
        <h4 class="footer-heading">Quick Links</h4>
        <ul class="footer-links-list">
          <li class="footer-link-item"><a href="index.html">&rsaquo; Home Page</a></li>
          <li class="footer-link-item"><a href="about.html">&rsaquo; About Institute</a></li>
          <li class="footer-link-item"><a href="programs.html">&rsaquo; Technical Programs</a></li>
          <li class="footer-link-item"><a href="admissions.html">&rsaquo; Admissions 2026</a></li>
          <li class="footer-link-item"><a href="status.php">&rsaquo; Check Application Status</a></li>
          <li class="footer-link-item"><a href="contact.html">&rsaquo; Contact &amp; Location</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4 class="footer-heading">Programs</h4>
        <ul class="footer-links-list">
          <li class="footer-link-item"><a href="programs.html#electrical">&rsaquo; Computer Operator</a></li>
          <li class="footer-link-item"><a href="programs.html#civil">&rsaquo; Ecommerce Digital Marketing</a></li>
          <li class="footer-link-item"><a href="programs.html#mechanical">&rsaquo; Web Development</a></li>
          <li class="footer-link-item"><a href="programs.html#computer">&rsaquo; Automobile Mechanic</a></li>
          <li class="footer-link-item"><a href="programs.html#electronics">&rsaquo; Fashion Design &amp; Beautician</a></li>
          <li class="footer-link-item"><a href="programs.html#autodiesel">&rsaquo; Hospitality</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4 class="footer-heading">Contact Information</h4>
        <div class="footer-contact-list">
          <div class="footer-contact-item">
            <span>Main Shigar Road, Thorgu, Skardu, Gilgit-Baltistan</span>
          </div>
          <div class="footer-contact-item">
            <span>(058159) 41113</span>
          </div>
          <div class="footer-contact-item">
            <span>gbdtesd@gmail.com</span>
          </div>
        </div>
      </div>
    </div>

    <div class="container footer-bottom">
      <div>&copy; 2026 Govt Polytechnic Institute. All Rights Reserved. Govt Technical Education Directorate.</div>
      <div>Accredited by PBTE &bull; NAVTTC Recognized</div>
    </div>
  </footer>

  <script src="js/script.js"></script>
</body>
</html>
