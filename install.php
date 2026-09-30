<?php
/**
 * Govt Polytechnic Institute (GPI) - Database Installer
 * Automatically creates database tables, relationships, indexes, and initial seeds.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

// Load config if available, otherwise use standard XAMPP defaults
$configFile = __DIR__ . '/config/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbName = defined('DB_NAME') ? DB_NAME : 'gpi_admissions';
    $dbUser = defined('DB_USER') ? DB_USER : 'root';
    $dbPass = defined('DB_PASS') ? DB_PASS : '';
    $dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;
} else {
    $dbHost = 'localhost';
    $dbName = 'gpi_admissions';
    $dbUser = 'root';
    $dbPass = '';
    $dbPort = 3306;
}

$lockFile = __DIR__ . '/database/installed.lock';
$alreadyInstalled = file_exists($lockFile);

$message = '';
$status = 'idle';
$steps = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install'])) {
    if ($alreadyInstalled && empty($_POST['force_reinstall'])) {
        $status = 'warning';
        $message = 'Database is already initialized. Check the force reinstall checkbox if you want to rebuild all tables.';
    } else {
        try {
            // 1. Connect to MySQL Server (without selecting DB first)
            $pdo = new PDO(
                "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $steps[] = '✓ Connected to MySQL server successfully.';

            // 2. Create Database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            $steps[] = "✓ Database `{$dbName}` verified/created.";

            // 3. Execute Schema
            $schemaFile = __DIR__ . '/database/schema.sql';
            if (!file_exists($schemaFile)) {
                throw new RuntimeException("Schema file not found at: {$schemaFile}");
            }
            $schemaSql = file_get_contents($schemaFile);
            $pdo->exec($schemaSql);
            $steps[] = '✓ Created all 9 tables (admins, programs, applications, application_documents, application_status_history, contact_messages, announcements, settings, admin_audit_logs) with foreign keys and indexes.';

            // 4. Execute Seed Data
            $seedFile = __DIR__ . '/database/seed.sql';
            if (!file_exists($seedFile)) {
                throw new RuntimeException("Seed file not found at: {$seedFile}");
            }
            $seedSql = file_get_contents($seedFile);
            $pdo->exec($seedSql);
            $steps[] = '✓ Seeded 6 technical programs, default Super Admin & Staff accounts, existing announcements, and institutional settings.';

            // 5. Create lock file
            file_put_contents($lockFile, 'Installed on ' . date('c'));
            $alreadyInstalled = true;
            $status = 'success';
            $message = 'Database installation completed successfully!';

        } catch (Throwable $e) {
            $status = 'error';
            $message = 'Installation failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GPI Database Installer &bull; Phase 2</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background-color: var(--slate-100); font-family: 'Inter', system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .install-card { background: #fff; max-width: 680px; width: 100%; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); border: 1px solid var(--slate-200); padding: 2.5rem; }
    .install-header { display: flex; align-items: center; gap: 1rem; border-bottom: 2px solid var(--slate-100); padding-bottom: 1.5rem; margin-bottom: 1.5rem; }
    .install-header img { width: 50px; height: 50px; border-radius: 8px; }
    .install-title { font-size: 1.4rem; color: var(--primary-900); font-weight: 700; margin: 0; }
    .install-subtitle { font-size: 0.88rem; color: var(--slate-500); margin-top: 0.25rem; }
    .alert { padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.92rem; margin-bottom: 1.5rem; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .step-log { background: var(--slate-900); color: #10b981; font-family: monospace; font-size: 0.85rem; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; line-height: 1.6; }
    .config-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .config-table th, .config-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--slate-200); text-align: left; }
    .config-table th { background: var(--slate-50); color: var(--slate-700); font-weight: 600; }
    .credentials-box { background: var(--primary-50); border-left: 4px solid var(--primary-700); padding: 1rem; border-radius: 6px; font-size: 0.88rem; margin-bottom: 1.5rem; }
  </style>
</head>
<body>
  <div class="install-card">
    <div class="install-header">
      <img src="images/logo.jpg" alt="GPI Logo">
      <div>
        <h1 class="install-title">GPI Admissions Database Installer</h1>
        <div class="install-subtitle">Phase 2: Database Schema, Indexes, Constraints & Seed Data</div>
      </div>
    </div>

    <?php if ($status === 'success'): ?>
      <div class="alert alert-success">
        <strong>Success!</strong> <?= htmlspecialchars($message) ?>
      </div>
      <div class="step-log">
        <?php foreach ($steps as $step): ?>
          <div><?= htmlspecialchars($step) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="credentials-box">
        <strong>Default Admin Credentials Initialized:</strong><br>
        • <strong>Super Admin Email:</strong> <code>admin@gpi.edu.pk</code><br>
        • <strong>Admissions Staff Email:</strong> <code>staff@gpi.edu.pk</code><br>
        • <strong>Default Password:</strong> <code>Admin@GPI2026!</code>
      </div>
      <p style="font-size: 0.9rem; color: var(--slate-600);">You can now proceed to Phase 3 (PHP Configuration & Helpers).</p>
    <?php elseif ($status === 'error'): ?>
      <div class="alert alert-error">
        <strong>Error:</strong> <?= htmlspecialchars($message) ?>
      </div>
    <?php elseif ($status === 'warning'): ?>
      <div class="alert alert-warning">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <h3 style="font-size: 1rem; color: var(--primary-900); margin-bottom: 0.75rem;">Database Connection Parameters</h3>
    <table class="config-table">
      <tr><th>Host</th><td><code><?= htmlspecialchars($dbHost) ?>:<?= htmlspecialchars((string)$dbPort) ?></code></td></tr>
      <tr><th>Database Name</th><td><code><?= htmlspecialchars($dbName) ?></code></td></tr>
      <tr><th>User</th><td><code><?= htmlspecialchars($dbUser) ?></code></td></tr>
      <tr><th>Status</th><td><?= $alreadyInstalled ? '<span style="color:#059669; font-weight:700;">Installed (Lock Active)</span>' : '<span style="color:#d97706; font-weight:700;">Ready to Install</span>' ?></td></tr>
    </table>

    <form method="POST">
      <?php if ($alreadyInstalled): ?>
        <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem; font-size: 0.88rem; color: var(--slate-700);">
          <input type="checkbox" name="force_reinstall" value="1">
          <strong>Force Re-install:</strong> Drop and recreate all tables and seed fresh data.
        </label>
      <?php endif; ?>
      
      <button type="submit" name="run_install" value="1" class="btn btn-primary btn-lg" style="width: 100%;">
        <?= $alreadyInstalled ? 'Re-run Database Installation' : 'Execute Database Installation &rarr;' ?>
      </button>
    </form>
  </div>
</body>
</html>
