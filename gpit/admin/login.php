<?php
/**
 * Govt Polytechnic Institute (GPI) - Admin Portal Login
 * Secure authentication endpoint with rate limiting, CSRF defense, and session regeneration.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

// If already authenticated, route straight to dashboard
if (is_admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errorMessage = '';
$emailInput = '';
$returnUrl = sanitize_string($_GET['return_url'] ?? 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $emailInput = sanitize_string($_POST['email'] ?? '');
    $passwordInput = (string)($_POST['password'] ?? '');

    try {
        $pdo = get_db_connection();
        [$success, $admin, $loginError] = admin_login($pdo, $emailInput, $passwordInput);

        if ($success) {
            // Prevent open redirect vulnerabilities
            $destination = (str_starts_with($returnUrl, 'http') || str_contains($returnUrl, '//'))
                ? 'dashboard.php'
                : $returnUrl;

            header('Location: ' . $destination);
            exit;
        } else {
            $errorMessage = $loginError;
        }
    } catch (Throwable $e) {
        $errorMessage = 'A system error occurred. Please verify your database connection.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal Login &bull; Govt Polytechnic Institute</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body {
      background-color: var(--slate-100);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    .login-container {
      max-width: 440px;
      width: 100%;
      background: #fff;
      border-radius: var(--radius-lg);
      padding: 2.75rem 2.25rem;
      border: 1px solid var(--slate-200);
      box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
    .login-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      margin-bottom: 2rem;
    }
    .login-brand img {
      width: 60px;
      height: 60px;
      margin-bottom: 0.75rem;
      border-radius: 8px;
    }
    .login-brand h1 {
      font-size: 1.35rem;
      color: var(--primary-900);
      font-weight: 800;
      margin: 0;
      line-height: 1.2;
    }
    .login-brand p {
      font-size: 0.85rem;
      color: var(--slate-500);
      margin-top: 0.35rem;
    }
    .demo-credentials-box {
      background: var(--primary-50);
      border-left: 4px solid var(--primary-700);
      padding: 0.85rem 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      color: var(--primary-950);
      margin-top: 2rem;
      line-height: 1.5;
    }
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
      padding: 0.85rem 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.88rem;
      margin-bottom: 1.5rem;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-brand">
      <img src="../images/logo.jpg" alt="GPI Crest">
      <h1>GPI Admin Portal</h1>
      <p>Online Admissions &amp; Application Management System</p>
    </div>

    <?php if (!empty($errorMessage)): ?>
      <div class="alert-error">
        <strong>Login Notice:</strong> <?= e($errorMessage) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['logged_out'])): ?>
      <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.85rem 1rem; border-radius:var(--radius-sm); font-size:0.88rem; margin-bottom:1.5rem;">
        You have successfully logged out of your session.
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= $returnUrl !== 'dashboard.php' ? '?return_url=' . urlencode($returnUrl) : '' ?>">
      <?= csrf_field() ?>

      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="adminEmail">Official Email Address <span class="required">*</span></label>
        <input type="email" id="adminEmail" name="email" class="form-control" 
               placeholder="admin@gpi.edu.pk" 
               value="<?= e($emailInput) ?>" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom: 1.75rem;">
        <label for="adminPassword">Account Password <span class="required">*</span></label>
        <input type="password" id="adminPassword" name="password" class="form-control" 
               placeholder="Enter your password" required>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
        Sign In to Portal &rarr;
      </button>
    </form>

    <div class="demo-credentials-box">
      <strong>Default Administrative Logins:</strong><br>
      • <strong>Super Admin:</strong> <code>admin@gpi.edu.pk</code><br>
      • <strong>Staff Member:</strong> <code>staff@gpi.edu.pk</code><br>
      • <strong>Password:</strong> <code>Admin@GPI2026!</code>
    </div>

    <div style="text-align: center; margin-top: 1.5rem;">
      <a href="../index.html" style="font-size: 0.85rem; color: var(--slate-500); text-decoration: none;">
        &larr; Return to Public Website
      </a>
    </div>
  </div>
</body>
</html>
