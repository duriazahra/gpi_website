<?php
/**
 * Govt Polytechnic Institute (GPI) - Sample Application Configuration
 * Copy this file to config.php and update credentials for your hosting environment.
 */

declare(strict_types=1);

define('APP_ENV', 'production'); // 'development' or 'production'

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Government Polytechnic Institute');
define('APP_SHORT_NAME', 'GPI');
define('APP_URL', 'https://admissions.yourdomain.com');
define('ADMIN_URL', APP_URL . '/admin');

define('CURRENT_SESSION', 'Session 2026-27');
define('SESSION_YEAR_CODE', '2026');

define('UPLOAD_DIR', __DIR__ . '/../uploads/documents');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'application/pdf' => 'pdf'
]);

define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_SECRET', 'replace_with_a_secure_random_string');

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

define('CORS_ALLOWED_ORIGINS', [
    'https://duriazahra.github.io',
    'http://localhost'
]);
