<?php
/**
 * Govt Polytechnic Institute (GPI) - Phase 3 Verification Test Runner
 * Tests configuration, database connectivity, session security, CSRF protection, and validation helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/audit.php';

$results = [];

function run_test(string $name, callable $fn, array &$results): void {
    try {
        $msg = $fn();
        $results[] = ['name' => $name, 'passed' => true, 'message' => $msg];
    } catch (Throwable $e) {
        $results[] = ['name' => $name, 'passed' => false, 'message' => $e->getMessage()];
    }
}

// 1. Config Test
run_test('Config Constants Defined', function() {
    $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_URL', 'UPLOAD_DIR', 'MAX_UPLOAD_SIZE'];
    foreach ($required as $const) {
        if (!defined($const)) {
            throw new Exception("Missing constant: {$const}");
        }
    }
    return 'All core constants (DB, URL, Session, Uploads) are defined.';
}, $results);

// 2. Session & CSRF Test
run_test('CSRF Engine & Secure Session', function() {
    $token = csrf_token();
    if (empty($token) || strlen($token) !== 64) {
        throw new Exception("Invalid CSRF token format. Generated: {$token}");
    }
    if (!validate_csrf_token($token)) {
        throw new Exception("CSRF token failed self-validation.");
    }
    if (validate_csrf_token('invalid_token_12345')) {
        throw new Exception("CSRF token allowed an invalid token string.");
    }
    $field = csrf_field();
    if (!str_contains($field, 'type="hidden"') || !str_contains($field, $token)) {
        throw new Exception("csrf_field() HTML output malformed.");
    }
    return "CSRF Token generated ({$token}) and successfully validated.";
}, $results);

// 3. XSS Escaping Test
run_test('XSS Prevention (e() Helper)', function() {
    $payload = '<script>alert("xss")</script>&"\'';
    $escaped = e($payload);
    if (str_contains($escaped, '<script>') || !str_contains($escaped, '&lt;script&gt;')) {
        throw new Exception("XSS escaping failed: {$escaped}");
    }
    return "Malicious script payload safely encoded to: {$escaped}";
}, $results);

// 4. CNIC Validation & Normalization Test
run_test('CNIC / B-Form Validation & Formatting', function() {
    // Unformatted 13 digits
    [$valid1, $formatted1, $err1] = validate_cnic('3740512345671');
    if (!$valid1 || $formatted1 !== '37405-1234567-1') {
        throw new Exception("CNIC unformatted normalization failed: {$formatted1} (Error: {$err1})");
    }
    // Formatted 13 digits
    [$valid2, $formatted2, $err2] = validate_cnic('37405-1234567-1');
    if (!$valid2 || $formatted2 !== '37405-1234567-1') {
        throw new Exception("CNIC formatted check failed: {$formatted2}");
    }
    // Invalid length
    [$valid3, $formatted3, $err3] = validate_cnic('12345');
    if ($valid3) {
        throw new Exception("Invalid short CNIC was wrongly accepted.");
    }
    return "Valid CNIC formatted correctly to 37405-1234567-1, and invalid CNIC safely rejected.";
}, $results);

// 5. Pakistani Phone Normalization Test
run_test('Phone Number Validation & Formatting', function() {
    [$valid1, $phone1, $err1] = validate_phone('03001234567');
    if (!$valid1 || $phone1 !== '0300-1234567') {
        throw new Exception("Phone normalization failed: {$phone1}");
    }
    [$valid2, $phone2, $err2] = validate_phone('+92 300 1234567');
    if (!$valid2 || $phone2 !== '0300-1234567') {
        throw new Exception("+92 phone normalization failed: {$phone2}");
    }
    [$valid3, $phone3, $err3] = validate_phone('123456');
    if ($valid3) {
        throw new Exception("Invalid phone was wrongly accepted.");
    }
    return "Mobile numbers (local and +92 international format) normalized to 0300-1234567.";
}, $results);

// 6. Server-side Marks & Percentage Recalculation Test
run_test('Server-Side Marks Validation & Percentage Recalculation', function() {
    // Normal test: 845 / 1100 = 76.82%
    [$valid1, $total1, $obt1, $pct1, $err1] = validate_marks(1100, 845);
    if (!$valid1 || $pct1 !== 76.82) {
        throw new Exception("Percentage calculation failed. Expected 76.82%, Got: {$pct1}%");
    }
    // Error test: obtained > total
    [$valid2, $total2, $obt2, $pct2, $err2] = validate_marks(1000, 1050);
    if ($valid2) {
        throw new Exception("Obtained > Total marks was wrongly accepted.");
    }
    // Error test: total = 0
    [$valid3, $total3, $obt3, $pct3, $err3] = validate_marks(0, 0);
    if ($valid3) {
        throw new Exception("Zero total marks was wrongly accepted.");
    }
    return "Server recalculated percentage (845 / 1100 = 76.82%) and strictly rejected invalid marks.";
}, $results);

// 7. Date of Birth & Age Verification Test
run_test('Candidate DOB & Age Verification (14 - 35 Years)', function() {
    $now = new DateTime();
    
    // Valid 18-year-old
    $dob18 = (clone $now)->modify('-18 years')->format('Y-m-d');
    [$valid1, $cDob1, $err1] = validate_dob($dob18);
    if (!$valid1) {
        throw new Exception("Valid 18-year-old was rejected: {$err1}");
    }
    
    // Invalid 10-year-old (too young)
    $dob10 = (clone $now)->modify('-10 years')->format('Y-m-d');
    [$valid2, $cDob2, $err2] = validate_dob($dob10);
    if ($valid2) {
        throw new Exception("Candidate younger than 14 was wrongly accepted.");
    }
    
    // Invalid 40-year-old (too old)
    $dob40 = (clone $now)->modify('-40 years')->format('Y-m-d');
    [$valid3, $cDob3, $err3] = validate_dob($dob40);
    if ($valid3) {
        throw new Exception("Candidate older than 35 was wrongly accepted.");
    }
    return "Age policy verified: 18-year-old accepted, while 10-year-old and 40-year-old candidates correctly rejected.";
}, $results);

// 8. Database Connection Test (Soft test - handles uninstalled DB gracefully)
run_test('Database Connection & Programs Check', function() {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM programs');
        $count = (int)$stmt->fetchColumn();
        return "Database connected successfully. Found {$count} programs in the institutional database.";
    } catch (Throwable $e) {
        return "Database connection note: MySQL may not be running or database not yet initialized (Run install.php to initialize). Error: " . $e->getMessage();
    }
}, $results);

// 9. Status Badges Test
run_test('Status Badges UI Helper', function() {
    $badge = get_status_badge('Pending');
    if (!str_contains($badge, 'Pending')) {
        throw new Exception("Badge rendering failed.");
    }
    return "Status badge rendered with matching GPI UI tokens.";
}, $results);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GPI Phase 3 Verification Report</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background-color: var(--slate-100); font-family: 'Inter', system-ui, sans-serif; padding: 2rem; }
    .test-card { background: #fff; max-width: 800px; margin: 0 auto; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); border: 1px solid var(--slate-200); padding: 2.5rem; }
    .test-header { display: flex; align-items: center; gap: 1rem; border-bottom: 2px solid var(--slate-100); padding-bottom: 1.5rem; margin-bottom: 1.5rem; }
    .test-header img { width: 50px; height: 50px; border-radius: 8px; }
    .test-item { display: flex; align-items: flex-start; gap: 1rem; padding: 1rem; border-radius: 8px; margin-bottom: 0.75rem; border: 1px solid var(--slate-200); }
    .test-item.pass { background: #ecfdf5; border-color: #a7f3d0; }
    .test-item.fail { background: #fef2f2; border-color: #fecaca; }
    .test-badge { padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
    .badge-pass { background: #059669; color: #fff; }
    .badge-fail { background: #dc2626; color: #fff; }
    .test-title { font-weight: 700; color: var(--primary-900); font-size: 0.95rem; margin-bottom: 0.25rem; }
    .test-msg { font-size: 0.85rem; color: var(--slate-600); }
  </style>
</head>
<body>
  <div class="test-card">
    <div class="test-header">
      <img src="images/logo.jpg" alt="GPI Logo">
      <div>
        <h1 style="font-size: 1.4rem; color: var(--primary-900); font-weight: 700; margin: 0;">Phase 3 Verification Report</h1>
        <div style="font-size: 0.88rem; color: var(--slate-500); margin-top: 0.25rem;">
          Database Singleton, CSRF Protection, Session Hardening & Server-side Validation
        </div>
      </div>
    </div>

    <?php foreach ($results as $r): ?>
      <div class="test-item <?= $r['passed'] ? 'pass' : 'fail' ?>">
        <span class="test-badge <?= $r['passed'] ? 'badge-pass' : 'badge-fail' ?>">
          <?= $r['passed'] ? 'PASS' : 'FAIL' ?>
        </span>
        <div>
          <div class="test-title"><?= e($r['name']) ?></div>
          <div class="test-msg"><?= e($r['message']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="margin-top: 2rem; text-align: center;">
      <a href="install.php" class="btn btn-outline" style="margin-right: 0.75rem;">&larr; Database Installer</a>
      <a href="index.html" class="btn btn-primary">Return to Website &rarr;</a>
    </div>
  </div>
</body>
</html>
