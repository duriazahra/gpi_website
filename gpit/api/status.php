<?php
/**
 * Govt Polytechnic Institute (GPI) - Application Status API Endpoint
 * Verifies Application Number + CNIC and returns sanitized status information.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

send_cors_headers();

header('Content-Type: application/json; charset=utf-8');

$appNo = sanitize_string($_REQUEST['app_no'] ?? $_REQUEST['application_no'] ?? '');
$cnic = sanitize_string($_REQUEST['cnic'] ?? $_REQUEST['cnic_bform'] ?? '');

if (empty($appNo) || empty($cnic)) {
    json_response([
        'success' => false,
        'message' => 'Both Application Number and CNIC / B-Form number are required.'
    ], 400);
}

// Strip hyphens for flexible, error-free matching
$cleanSearchCnic = preg_replace('/\D/', '', $cnic);

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT a.id, a.application_no, a.full_name, a.cnic_bform, a.status, a.created_at,
                p.name AS program_name
         FROM applications a
         LEFT JOIN programs p ON a.program_id = p.id
         WHERE a.application_no = :app_no 
         LIMIT 1'
    );
    $stmt->execute([':app_no' => $appNo]);
    $app = $stmt->fetch();

    if (!$app) {
        json_response([
            'success' => false,
            'message' => 'No application record found matching the provided Application Number.'
        ], 404);
    }

    $cleanDbCnic = preg_replace('/\D/', '', $app['cnic_bform']);

    if ($cleanSearchCnic !== $cleanDbCnic) {
        json_response([
            'success' => false,
            'message' => 'The CNIC / B-Form number does not match this Application Reference ID.'
        ], 403);
    }

    // Return limited, safe institutional status data (privacy preserving)
    json_response([
        'success'         => true,
        'application_no'  => $app['application_no'],
        'applicant_name'  => $app['full_name'],
        'program_name'    => $app['program_name'] ?? 'Technical Program',
        'submission_date' => format_date($app['created_at'], 'd M Y, h:i A'),
        'status'          => $app['status'],
        'status_badge'    => get_status_badge($app['status']),
        'pdf_url'         => 'download_pdf.php?app_no=' . urlencode($app['application_no']) . '&cnic=' . urlencode($app['cnic_bform'])
    ]);

} catch (Throwable $e) {
    error_log('[GPI Status API Error] ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'An error occurred while retrieving your application status. Please try again later.'
    ], 500);
}
