<?php
/**
 * Govt Polytechnic Institute (GPI) - Protected Document Delivery
 * Securely streams sensitive applicant documents (CNIC, Result Cards, Domicile, Photo)
 * to authenticated staff. Prevents directory traversal, path leaks, and unauthenticated access.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();
require_admin();

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$appId = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;
$docType = isset($_GET['type']) ? sanitize_string($_GET['type']) : '';

if ($docId <= 0 && ($appId <= 0 || empty($docType))) {
    http_response_code(400);
    exit('Invalid document request parameters.');
}

try {
    $pdo = get_db_connection();

    if ($docId > 0) {
        $stmt = $pdo->prepare('SELECT id, application_id, document_type, file_path, file_name, mime_type, file_size FROM application_documents WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $docId]);
    } else {
        $stmt = $pdo->prepare('SELECT id, application_id, document_type, file_path, file_name, mime_type, file_size FROM application_documents WHERE application_id = :app_id AND document_type = :doc_type LIMIT 1');
        $stmt->execute([':app_id' => $appId, ':doc_type' => $docType]);
    }

    $doc = $stmt->fetch();

    if (!$doc) {
        http_response_code(404);
        exit('Requested document record not found in system.');
    }

    $filePath = realpath($doc['file_path']);
    $uploadsDir = realpath(UPLOADS_DIR);

    // Guard against path traversal attacks: file MUST reside within UPLOADS_DIR
    if (!$filePath || !$uploadsDir || !str_starts_with($filePath, $uploadsDir) || !file_exists($filePath)) {
        http_response_code(404);
        exit('The physical file for this document could not be located on the server.');
    }

    // Determine safe mime type
    $mimeType = $doc['mime_type'] ?: mime_content_type($filePath);
    $safeMimeTypes = [
        'image/jpeg' => 'image/jpeg',
        'image/jpg'  => 'image/jpeg',
        'image/png'  => 'image/png',
        'application/pdf' => 'application/pdf'
    ];

    $contentMime = $safeMimeTypes[$mimeType] ?? 'application/octet-stream';
    $fileBasename = basename($doc['file_name'] ?: basename($filePath));

    // Clear any previous output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Send security and delivery headers
    header('Content-Type: ' . $contentMime);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: inline; filename="' . addslashes($fileBasename) . '"');
    header('Cache-Control: private, no-transform, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    readfile($filePath);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    exit('A server error occurred while retrieving the requested file.');
}
