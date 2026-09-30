<?php
/**
 * Govt Polytechnic Institute (GPI) - CSRF Protection Engine
 * Prevents Cross-Site Request Forgery on all state-changing forms.
 */

declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * Returns the current session CSRF token, generating one if it doesn't exist.
 *
 * @return string
 */
function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Generates an HTML hidden input field containing the CSRF token.
 *
 * @return string
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validates the provided token against the session CSRF token.
 *
 * @param string|null $token
 * @return bool
 */
function validate_csrf_token(?string $token): bool
{
    start_secure_session();

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enforces CSRF token check on incoming POST/PUT/DELETE requests.
 * Aborts with HTTP 403 if token is missing or mismatched.
 *
 * @return void
 */
function verify_csrf_or_die(): void
{
    // Retrieve token from POST body or X-CSRF-Token header (for AJAX/fetch)
    $token = $_POST['csrf_token'] 
          ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
          ?? $_SERVER['HTTP_X_XSRF_TOKEN'] 
          ?? null;

    if (!validate_csrf_token($token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Your security session has expired or the request was invalid. Please refresh the page and try again.'
            ]);
        } else {
            echo '<!DOCTYPE html><html><head><title>Security Error</title><link rel="stylesheet" href="css/style.css"></head><body style="padding:2rem;text-align:center;">';
            echo '<h2>Security Validation Failed</h2>';
            echo '<p>Your security token has expired or is invalid. Please return to the previous page, refresh, and submit again.</p>';
            echo '<a href="javascript:history.back()" class="btn btn-primary">Go Back</a>';
            echo '</body></html>';
        }
        exit;
    }
}
