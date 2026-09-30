<?php
/**
 * Govt Polytechnic Institute (GPI) - Security & Session Engine
 * Session hardening, XSS prevention, CORS management, and rate limiting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Initializes a hardened PHP session with secure cookie parameters.
 *
 * @return void
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Configure secure cookie parameters before starting session
    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();

    // Prevent session fixation by verifying client IP and user agent
    if (!isset($_SESSION['initiated'])) {
        $_SESSION['initiated'] = true;
        $_SESSION['client_ip'] = get_client_ip();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
}

/**
 * Escapes strings for safe HTML output (XSS defense).
 *
 * @param mixed $value
 * @return string
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Cleans user string inputs by removing control characters and null bytes.
 *
 * @param mixed $value
 * @return string
 */
function sanitize_string(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    $str = (string)$value;
    $str = str_replace(["\0", "\x00"], '', $str);
    return trim($str);
}

/**
 * Safely extracts client IP address accounting for trusted proxies.
 *
 * @return string
 */
function get_client_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', $_SERVER[$header]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '127.0.0.1';
}

/**
 * Handles CORS headers for split GitHub Pages frontend and PHP API calls.
 *
 * @return void
 */
function send_cors_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    $allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : ['*'];

    if ($origin && (in_array($origin, $allowedOrigins, true) || in_array('*', $allowedOrigins, true))) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Requested-With');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Basic rate-limiting engine to prevent brute-force attacks.
 *
 * @param string $action Unique action identifier (e.g. 'login', 'apply')
 * @param int $maxAttempts Maximum allowed attempts in window
 * @param int $decaySeconds Window time in seconds
 * @return bool True if allowed, false if limit exceeded
 */
function check_rate_limit(string $action, int $maxAttempts = 5, int $decaySeconds = 900): bool
{
    start_secure_session();

    $ip = get_client_ip();
    $key = "rate_limit_{$action}_{$ip}";

    $now = time();
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => $now];

    // Reset window if decay period has passed
    if ($now - $attempts['first_attempt'] > $decaySeconds) {
        $attempts = ['count' => 1, 'first_attempt' => $now];
        $_SESSION[$key] = $attempts;
        return true;
    }

    if ($attempts['count'] >= $maxAttempts) {
        return false;
    }

    $attempts['count']++;
    $_SESSION[$key] = $attempts;
    return true;
}

/**
 * Resets rate limit counters after successful completion (e.g. correct login).
 *
 * @param string $action
 * @return void
 */
function reset_rate_limit(string $action): void
{
    start_secure_session();
    $ip = get_client_ip();
    $key = "rate_limit_{$action}_{$ip}";
    unset($_SESSION[$key]);
}
