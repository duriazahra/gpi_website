<?php
/**
 * Govt Polytechnic Institute (GPI) - Application Gateway
 * Unified entry point that routes candidates directly to the Online Admission Application Form.
 * Checks institutional admissions open/closed status and preserves selected program parameters.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

// Check if database is initialized
$admissionsOpen = true;
$closureMessage = '';

try {
    $pdo = get_db_connection();
    [$admissionsOpen, $closureMessage] = check_admissions_open($pdo);
} catch (Throwable $e) {
    // If DB is not yet installed or connection failed, allow access to static form
    $admissionsOpen = true;
}

// Extract query parameters (e.g. ?program=electrical)
$queryParams = $_GET;

// If admissions are closed, pass notice parameter to admissions page
if (!$admissionsOpen) {
    $queryParams['status'] = 'closed';
    $queryParams['msg'] = urlencode($closureMessage);
}

$queryString = http_build_query($queryParams);
$target = 'admissions.html' . ($queryString ? '?' . $queryString : '') . '#admission-form-section';

// Redirect user seamlessly to the admission application form
header('Location: ' . $target, true, 302);
exit;
