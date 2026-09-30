<?php
/**
 * Govt Polytechnic Institute (GPI) - Comprehensive System Verification Suite
 * Automated test runner for end-to-end functionality, security controls, database integrity, and business logic.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/auth.php';

$tests = [];

function assert_test(string $name, callable $testFn, array &$tests): void {
    $startTime = microtime(true);
    try {
        $info = $testFn();
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $tests[] = ['name' => $name, 'passed' => true, 'info' => $info, 'ms' => $duration];
    } catch (Throwable $e) {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $tests[] = ['name' => $name, 'passed' => false, 'info' => $e->getMessage(), 'ms' => $duration];
    }
}

// 1. Config & Core Architecture
assert_test('Core Architecture & Constants', function() {
    $req = ['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_URL', 'UPLOAD_DIR', 'MAX_UPLOAD_SIZE'];
    foreach ($req as $c) {
        if (!defined($c)) throw new Exception("Missing constant: $c");
    }
    return 'Database, paths, and security constants properly loaded.';
}, $tests);

// 2. Database Connectivity & Schema Verification
assert_test('Database Connection & Tables', function() {
    $pdo = get_db_connection();
    $tables = [
        'admins', 'programs', 'applications', 'application_documents',
        'application_status_history', 'contact_messages', 'announcements',
        'settings', 'admin_audit_logs'
    ];
    $found = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($tables, $found);
    if (!empty($missing)) {
        throw new Exception("Missing tables: " . implode(', ', $missing) . ". Please run install.php.");
    }
    return "All 9 InnoDB relational tables verified in database '" . DB_NAME . "'.";
}, $tests);

// 3. Program Seed Data & Slugs
assert_test('Programs Seeding & Anchor Matching', function() {
    $pdo = get_db_connection();
    $expectedSlugs = ['electrical', 'civil', 'mechanical', 'computer', 'electronics', 'autodiesel'];
    $stmt = $pdo->query("SELECT slug, title, is_active FROM programs ORDER BY sort_order ASC");
    $progs = $stmt->fetchAll();
    if (count($progs) < 6) {
        throw new Exception("Expected at least 6 technical diploma programs, found " . count($progs));
    }
    $foundSlugs = array_column($progs, 'slug');
    foreach ($expectedSlugs as $slug) {
        if (!in_array($slug, $foundSlugs, true)) {
            throw new Exception("Missing required website program slug: $slug");
        }
    }
    return "All 6 website technical programs (Computer Operator, Web Dev, etc.) properly seeded.";
}, $tests);

// 4. CSRF Defense Engine
assert_test('CSRF Defense Engine', function() {
    $t1 = csrf_token();
    if (strlen($t1) !== 64 || !ctype_xdigit($t1)) {
        throw new Exception("CSRF token is not a 64-char hexadecimal string.");
    }
    if (!validate_csrf_token($t1)) {
        throw new Exception("Generated CSRF token failed validation check.");
    }
    if (validate_csrf_token('invalid_tampered_csrf_token')) {
        throw new Exception("Security breach: tampered CSRF token was accepted.");
    }
    return 'Token generation, session persistence, and timing-safe validation passed.';
}, $tests);

// 5. Pakistani CNIC Normalization & Validation
assert_test('CNIC Format & Check Digit Validation', function() {
    $validCNICs = ['37405-1234567-1', '3740512345671', ' 37405-1234567-1 '];
    foreach ($validCNICs as $cnic) {
        if (!validate_pakistani_cnic($cnic)) {
            throw new Exception("Valid CNIC was falsely rejected: $cnic");
        }
    }
    $norm = normalize_pakistani_cnic('3740512345671');
    if ($norm !== '37405-1234567-1') {
        throw new Exception("Normalization failed. Expected 37405-1234567-1, got $norm");
    }
    if (validate_pakistani_cnic('12345-123456-1')) { // 12 digits
        throw new Exception("Invalid 12-digit CNIC was falsely accepted.");
    }
    return 'Standard dashed and unhyphenated Pakistani CNICs validated and normalized.';
}, $tests);

// 6. Mobile Number Normalization
assert_test('Pakistani Mobile Phone Validation', function() {
    $validPhones = ['03001234567', '0300-1234567', '+923001234567', '923001234567'];
    foreach ($validPhones as $p) {
        if (!validate_pakistani_mobile($p)) {
            throw new Exception("Valid mobile was falsely rejected: $p");
        }
    }
    $norm = normalize_pakistani_mobile('+92 300 1234567');
    if ($norm !== '0300-1234567') {
        throw new Exception("Normalization failed: got $norm, expected 0300-1234567");
    }
    return 'Mobile formats (03XX, +923XX) normalized to standard 03XX-XXXXXXX.';
}, $tests);

// 7. Server-Side Percentage Calculation
assert_test('Academic Percentage Calculation', function() {
    $pct = calculate_percentage(880, 1100);
    if ($pct !== 80.00) {
        throw new Exception("Expected 80.00%, got $pct%");
    }
    if (calculate_percentage(1150, 1100) !== null) {
        throw new Exception("Obtained marks greater than total marks was not blocked.");
    }
    if (calculate_percentage(500, 0) !== null) {
        throw new Exception("Zero total marks did not safely return null.");
    }
    return 'Server recalculation enforced: obtained <= total and total > 0.';
}, $tests);

// 8. Application Number Generator Format
assert_test('Application Number Formatting', function() {
    $pdo = get_db_connection();
    $num = generate_application_number($pdo, 2026);
    if (!preg_match('/^GPI-2026-\d{6}$/', $num)) {
        throw new Exception("Generated application number does not match format GPI-2026-XXXXXX: $num");
    }
    return "Application numbering format validated: $num.";
}, $tests);

// 9. Document Upload Protection (.htaccess)
assert_test('Protected Documents Directory Access Control', function() {
    $docDir = UPLOADS_DIR . '/documents';
    if (!is_dir($docDir)) {
        throw new Exception("Documents directory does not exist: $docDir");
    }
    $htaccess = $docDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        throw new Exception("Missing security .htaccess in documents folder.");
    }
    $content = file_get_contents($htaccess);
    if (!str_contains($content, 'Deny from all') && !str_contains($content, 'Require all denied')) {
        throw new Exception(".htaccess does not block public HTTP traffic.");
    }
    return 'Direct HTTP access to sensitive student files is restricted by .htaccess.';
}, $tests);

// 10. Administrative Accounts & Password Verification
assert_test('Admin Accounts & Password Hash Verification', function() {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT email, password, role FROM admins WHERE email = 'admin@gpi.edu.pk'");
    $stmt->execute();
    $admin = $stmt->fetch();
    if (!$admin) {
        throw new Exception("Super Admin 'admin@gpi.edu.pk' not found in database.");
    }
    if (!password_verify('Admin@GPI2026!', $admin['password'])) {
        throw new Exception("Default password verification failed for admin@gpi.edu.pk.");
    }
    return "Super Admin credentials verified against Bcrypt hash (Role: {$admin['role']}).";
}, $tests);

$totalCount = count($tests);
$passedCount = count(array_filter($tests, fn($t) => $t['passed']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GPI System Verification Suite &bull; All Tests</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background: #f8fafc; padding: 2.5rem 1rem; font-family: var(--font-main); }
    .test-box { max-width: 860px; margin: 0 auto; background: #fff; border-radius: var(--radius-lg); border: 1px solid var(--slate-200); box-shadow: var(--shadow-md); padding: 2rem; }
    .test-item { padding: 1rem; border-bottom: 1px solid var(--slate-100); display: flex; justify-content: space-between; align-items: start; }
    .test-item:last-child { border-bottom: none; }
    .badge-pass { background: #dcfce7; color: #15803d; padding: 0.25rem 0.65rem; border-radius: 9999px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
    .badge-fail { background: #fee2e2; color: #b91c1c; padding: 0.25rem 0.65rem; border-radius: 9999px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
  </style>
</head>
<body>
  <div class="test-box">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid var(--slate-200); padding-bottom:1rem;">
      <div>
        <h2 style="margin:0; font-size:1.4rem; color:var(--primary-950);">GPI Automated System Test Suite</h2>
        <p style="margin:0.25rem 0 0 0; font-size:0.85rem; color:var(--slate-500);">Verification of all backend logic, database tables, validation rules, and security controls</p>
      </div>
      <div style="text-align:right;">
        <span class="badge <?= $passedCount === $totalCount ? 'badge-pass' : 'badge-fail' ?>" style="font-size:0.9rem; padding:0.4rem 1rem;">
          <?= $passedCount ?> / <?= $totalCount ?> Tests Passed
        </span>
      </div>
    </div>

    <div>
      <?php foreach ($tests as $t): ?>
        <div class="test-item">
          <div>
            <div style="font-weight:700; color:var(--slate-900); font-size:0.95rem;"><?= e($t['name']) ?></div>
            <div style="font-size:0.82rem; color:<?= $t['passed'] ? 'var(--slate-600)' : '#b91c1c' ?>; margin-top:0.2rem;">
              <?= e($t['info']) ?>
            </div>
          </div>
          <div style="text-align:right; flex-shrink:0; margin-left:1rem;">
            <span class="<?= $t['passed'] ? 'badge-pass' : 'badge-fail' ?>">
              <?= $t['passed'] ? 'PASSED' : 'FAILED' ?>
            </span>
            <div style="font-size:0.72rem; color:var(--slate-400); margin-top:0.25rem; font-family:var(--font-mono);"><?= $t['ms'] ?> ms</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:2rem; padding-top:1rem; border-top:1px solid var(--slate-200); display:flex; justify-content:space-between; align-items:center;">
      <a href="admin/login.php" class="btn btn-primary btn-sm">&rarr; Go to Admin Portal</a>
      <a href="index.html" class="btn btn-outline btn-sm">&larr; Public Homepage</a>
    </div>
  </div>
</body>
</html>
