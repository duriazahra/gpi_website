<?php
/**
 * Govt Polytechnic Institute (GPI) - Admin Portal Header Layout
 * Shared navigation, security headers, role badge, and UI shell.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/audit.php';

start_secure_session();
$currentAdmin = require_admin();

// Ensure page titles and active nav states have fallbacks
$pageTitle = $pageTitle ?? 'Admin Portal';
$activeNav = $activeNav ?? 'dashboard';

// Quick badges for badge counts
$pendingCount = 0;
$unreadMsgCount = 0;
try {
    $pdoBadge = get_db_connection();
    $pendingCount = (int)$pdoBadge->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
    $unreadMsgCount = (int)$pdoBadge->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
} catch (Throwable $e) {
    // Gracefully continue if tables are still initializing
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> &bull; GPI Admin Portal</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    :root {
      --admin-sidebar-w: 260px;
      --admin-topbar-h: 64px;
    }
    body {
      background-color: #f1f5f9;
      color: #1e293b;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      font-family: var(--font-main);
    }
    /* Layout wrapper */
    .admin-wrapper {
      display: flex;
      min-height: calc(100vh - var(--admin-topbar-h));
    }
    /* Topbar */
    .admin-topbar {
      height: var(--admin-topbar-h);
      background: var(--primary-950);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
      position: sticky;
      top: 0;
      z-index: 1020;
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }
    .admin-brand {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      text-decoration: none;
      color: #fff;
    }
    .admin-brand img {
      width: 38px;
      height: 38px;
      border-radius: 6px;
      background: #fff;
    }
    .admin-brand-text h1 {
      font-size: 1.05rem;
      font-weight: 800;
      color: #fff;
      margin: 0;
      line-height: 1.2;
    }
    .admin-brand-text span {
      font-size: 0.75rem;
      color: var(--accent-400);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 600;
    }
    .admin-user-pill {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .user-info {
      text-align: right;
      line-height: 1.2;
    }
    .user-name {
      font-weight: 600;
      font-size: 0.88rem;
      color: #fff;
    }
    .user-role {
      font-size: 0.72rem;
      color: var(--slate-400);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .role-badge {
      display: inline-block;
      padding: 0.15rem 0.5rem;
      border-radius: 4px;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
    }
    .role-super {
      background: #f59e0b;
      color: #78350f;
    }
    .role-staff {
      background: #3b82f6;
      color: #1e3a8a;
    }
    /* Sidebar */
    .admin-sidebar {
      width: var(--admin-sidebar-w);
      background: var(--primary-900);
      color: #cbd5e1;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-right: 1px solid rgba(255,255,255,0.06);
      transition: transform var(--transition-fast);
    }
    .sidebar-menu {
      padding: 1.25rem 0.75rem;
      list-style: none;
      margin: 0;
    }
    .menu-heading {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--slate-500);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 0.5rem 0.85rem;
      margin-top: 0.75rem;
    }
    .menu-heading:first-child {
      margin-top: 0;
    }
    .menu-item a {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.65rem 0.85rem;
      color: #cbd5e1;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      border-radius: var(--radius-md);
      transition: all var(--transition-fast);
      margin-bottom: 0.25rem;
    }
    .menu-item a:hover {
      background: rgba(255,255,255,0.08);
      color: #fff;
    }
    .menu-item.active a {
      background: var(--primary-700);
      color: #fff;
      font-weight: 700;
    }
    .menu-icon-label {
      display: flex;
      align-items: center;
      gap: 0.65rem;
    }
    .menu-badge {
      background: var(--accent-500);
      color: #fff;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.15rem 0.45rem;
      border-radius: 9999px;
    }
    .menu-badge-unread {
      background: #ef4444;
      color: #fff;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.15rem 0.45rem;
      border-radius: 9999px;
    }
    .sidebar-footer {
      padding: 1rem 0.85rem;
      border-top: 1px solid rgba(255,255,255,0.08);
      font-size: 0.8rem;
    }
    .sidebar-footer a {
      color: var(--slate-400);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      padding: 0.4rem 0;
      transition: color var(--transition-fast);
    }
    .sidebar-footer a:hover {
      color: #fff;
    }
    /* Content Area */
    .admin-main {
      flex: 1;
      padding: 2rem 2rem 4rem 2rem;
      overflow-y: auto;
      max-width: 1600px;
    }
    .admin-page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .admin-page-title h2 {
      font-size: 1.65rem;
      color: var(--primary-950);
      margin: 0;
    }
    .admin-page-title p {
      font-size: 0.88rem;
      color: var(--slate-600);
      margin: 0.25rem 0 0 0;
    }
    /* Cards & Stats */
    .stat-card {
      background: #fff;
      border: 1px solid var(--slate-200);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      box-shadow: var(--shadow-subtle);
      transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    }
    .stat-card:hover {
      box-shadow: var(--shadow-md);
    }
    .stat-label {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--slate-500);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .stat-value {
      font-size: 2rem;
      font-weight: 800;
      color: var(--primary-900);
      line-height: 1.2;
      margin: 0.4rem 0;
    }
    .stat-sub {
      font-size: 0.82rem;
      color: var(--slate-600);
    }
    /* Tables */
    .table-container {
      background: #fff;
      border: 1px solid var(--slate-200);
      border-radius: var(--radius-lg);
      overflow-x: auto;
      box-shadow: var(--shadow-subtle);
    }
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.9rem;
    }
    .admin-table th {
      background: #f8fafc;
      color: var(--slate-700);
      font-weight: 700;
      padding: 0.85rem 1rem;
      border-bottom: 1px solid var(--slate-200);
      white-space: nowrap;
    }
    .admin-table td {
      padding: 0.85rem 1rem;
      border-bottom: 1px solid var(--slate-100);
      vertical-align: middle;
    }
    .admin-table tr:last-child td {
      border-bottom: none;
    }
    .admin-table tr:hover td {
      background-color: #f8fafc;
    }
    /* Status Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.65rem;
      border-radius: var(--radius-full);
      font-size: 0.76rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .badge-pending { background: #fef3c7; color: #b45309; }
    .badge-under_review { background: #e0f2fe; color: #0369a1; }
    .badge-verified { background: #dcfce7; color: #15803d; }
    .badge-accepted { background: #bbf7d0; color: #166534; }
    .badge-rejected { background: #fee2e2; color: #b91c1c; }
    .badge-active { background: #dcfce7; color: #15803d; }
    .badge-inactive { background: #f1f5f9; color: #64748b; }

    /* Flash Alerts */
    .flash-alert {
      padding: 1rem 1.25rem;
      border-radius: var(--radius-md);
      margin-bottom: 1.5rem;
      font-size: 0.92rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .flash-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .flash-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

    /* Mobile toggle */
    .sidebar-toggle-btn {
      display: none;
      background: none;
      border: none;
      color: #fff;
      font-size: 1.5rem;
      cursor: pointer;
      padding: 0.25rem;
    }

    @media (max-width: 991px) {
      .sidebar-toggle-btn { display: block; }
      .admin-sidebar {
        position: fixed;
        top: var(--admin-topbar-h);
        bottom: 0;
        left: 0;
        z-index: 1010;
        transform: translateX(-100%);
      }
      .admin-sidebar.open {
        transform: translateX(0);
      }
      .admin-main {
        padding: 1.25rem;
      }
    }
  </style>
</head>
<body>

  <!-- Topbar -->
  <header class="admin-topbar">
    <div style="display:flex; align-items:center; gap:1rem;">
      <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation">
        &#9776;
      </button>
      <a href="dashboard.php" class="admin-brand">
        <img src="../images/logo.jpg" alt="GPI Logo">
        <div class="admin-brand-text">
          <h1>GPI Admin Portal</h1>
          <span>Admissions Management</span>
        </div>
      </a>
    </div>

    <div class="admin-user-pill">
      <div class="user-info">
        <div class="user-name"><?= e($currentAdmin['name']) ?></div>
        <div class="user-role">
          <span class="role-badge <?= $currentAdmin['role'] === 'super_admin' ? 'role-super' : 'role-staff' ?>">
            <?= e(str_replace('_', ' ', $currentAdmin['role'])) ?>
          </span>
        </div>
      </div>
      <a href="logout.php" class="btn btn-outline-white btn-sm" style="padding:0.4rem 0.8rem; font-size:0.8rem;" title="Sign out of portal">
        Logout &rarr;
      </a>
    </div>
  </header>

  <div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
      <div>
        <ul class="sidebar-menu">
          <li class="menu-heading">Main Navigation</li>
          <li class="menu-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
            <a href="dashboard.php">
              <span class="menu-icon-label">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
              </span>
            </a>
          </li>

          <li class="menu-item <?= $activeNav === 'applications' ? 'active' : '' ?>">
            <a href="applications.php">
              <span class="menu-icon-label">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Applications
              </span>
              <?php if ($pendingCount > 0): ?>
                <span class="menu-badge" title="<?= $pendingCount ?> pending review"><?= $pendingCount ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="menu-item <?= $activeNav === 'programs' ? 'active' : '' ?>">
            <a href="programs.php">
              <span class="menu-icon-label">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                Programs
              </span>
            </a>
          </li>

          <li class="menu-heading">Content &amp; Inquiries</li>
          <li class="menu-item <?= $activeNav === 'announcements' ? 'active' : '' ?>">
            <a href="announcements.php">
              <span class="menu-icon-label">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                Announcements
              </span>
            </a>
          </li>

          <li class="menu-item <?= $activeNav === 'messages' ? 'active' : '' ?>">
            <a href="messages.php">
              <span class="menu-icon-label">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Contact Messages
              </span>
              <?php if ($unreadMsgCount > 0): ?>
                <span class="menu-badge-unread" title="<?= $unreadMsgCount ?> unread messages"><?= $unreadMsgCount ?></span>
              <?php endif; ?>
            </a>
          </li>

          <?php if ($currentAdmin['role'] === 'super_admin'): ?>
            <li class="menu-heading">Administration</li>
            <li class="menu-item <?= $activeNav === 'settings' ? 'active' : '' ?>">
              <a href="settings.php">
                <span class="menu-icon-label">
                  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  Settings
                </span>
              </a>
            </li>

            <li class="menu-item <?= $activeNav === 'audit_logs' ? 'active' : '' ?>">
              <a href="audit_logs.php">
                <span class="menu-icon-label">
                  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                  Audit Logs
                </span>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="sidebar-footer">
        <a href="../index.html" target="_blank" rel="noopener">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          View Public Site &nearr;
        </a>
        <a href="logout.php">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Sign Out
        </a>
      </div>
    </aside>

    <!-- Main Content Container -->
    <main class="admin-main">
      <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash-alert flash-success">
          <div><strong>Success:</strong> <?= e($_SESSION['flash_success']) ?></div>
          <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#065f46;cursor:pointer;font-size:1.1rem;">&times;</button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash-alert flash-error">
          <div><strong>Notice:</strong> <?= e($_SESSION['flash_error']) ?></div>
          <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#991b1b;cursor:pointer;font-size:1.1rem;">&times;</button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
      <?php endif; ?>
