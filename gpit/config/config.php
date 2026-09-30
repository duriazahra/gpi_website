<?php
/**
 * Govt Polytechnic Institute (GPI) - Application Configuration
 * Centralized settings for database, security, sessions, and uploads.
 */

declare(strict_types=1);

// Prevent direct execution if accessed directly via URL with sensitive intent
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') {
    http_response_code(403);
    exit('Direct access forbidden.');
}

// ----------------------------------------------------------------------------
// 1. Environment & Error Reporting
// ----------------------------------------------------------------------------
// Set to 'development' during local building/testing; set to 'production' on live server
define('APP_ENV', 'development');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_error.log');
}

// ----------------------------------------------------------------------------
// 2. Database Connection Credentials
// ----------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'gpi_admissions');
define('DB_USER', 'root');
define('DB_PASS', ''); // Blank by default on standard XAMPP/WAMP setups
define('DB_CHARSET', 'utf8mb4');

// ----------------------------------------------------------------------------
// 3. Application URLs & Paths
// ----------------------------------------------------------------------------
// Base public URL path (adjust if your project folder is named differently on localhost)
define('APP_NAME', 'Government Polytechnic Institute');
define('APP_SHORT_NAME', 'GPI');
define('APP_URL', 'http://localhost/gpit');
define('ADMIN_URL', APP_URL . '/admin');

// Academic Session
define('CURRENT_SESSION', 'Session 2026-27');
define('SESSION_YEAR_CODE', '2026');

// ----------------------------------------------------------------------------
// 4. Document Storage & Upload Policy
// ----------------------------------------------------------------------------
// Sensitive uploads (CNIC, Matric transcripts, Photo) are saved in protected storage
define('UPLOAD_DIR', __DIR__ . '/../uploads/documents');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB maximum

// Allowed MIME types and extensions
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'application/pdf' => 'pdf'
]);

// ----------------------------------------------------------------------------
// 5. Session & Security Configuration
// ----------------------------------------------------------------------------
define('SESSION_LIFETIME', 3600); // 1 hour session lifetime for admins
define('CSRF_TOKEN_SECRET', 'gpi_secure_csrf_secret_2026_salt');

// Rate limiting: Max login attempts before temporary lock
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes lockout

// ----------------------------------------------------------------------------
// 6. Cross-Origin Resource Sharing (CORS) for GitHub Pages
// ----------------------------------------------------------------------------
// Allows static frontend hosted on GitHub Pages to communicate with this PHP backend
define('CORS_ALLOWED_ORIGINS', [
    'https://duriazahra.github.io',
    'http://localhost',
    'http://127.0.0.1'
]);
