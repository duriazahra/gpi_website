<?php
/**
 * Govt Polytechnic Institute (GPI) - Secure Document Uploader
 * Strict MIME validation, extension whitelisting, file size limits, and unguessable renaming.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/security.php';

/**
 * Validates and securely stores an uploaded institutional document.
 *
 * @param array $file $_FILES['field_name'] array
 * @param string $docType 'photo', 'marksheet', or 'cnic'
 * @param array &$uploadedPaths Reference array tracking created files for rollback cleanup
 * @return array [bool $success, array $documentData, string $error]
 */
function handle_document_upload(array $file, string $docType, array &$uploadedPaths): array
{
    // 1. Verify basic upload status
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, [], "Invalid file upload parameter for {$docType}."];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return [false, [], "Please select a file to upload for {$docType}."];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return [false, [], "The uploaded file for {$docType} exceeds the maximum allowed limit of 5MB."];
        default:
            return [false, [], "An error occurred while uploading {$docType} (Code: {$file['error']})."];
    }

    // 2. Validate file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $sizeMb = round($file['size'] / (1024 * 1024), 2);
        return [false, [], "The {$docType} file ({$sizeMb}MB) exceeds the maximum allowed limit of 5MB."];
    }

    if ($file['size'] <= 0) {
        return [false, [], "The uploaded file for {$docType} appears to be empty."];
    }

    // 3. Validate file extension
    $originalName = basename((string)$file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return [false, [], "Invalid file extension (.{$ext}) for {$docType}. Allowed: JPG, JPEG, PNG, PDF."];
    }

    // Photographs should be image files only (no PDF for passport photos)
    if ($docType === 'photo' && $ext === 'pdf') {
        return [false, [], 'Passport photograph must be an image file (JPG, JPEG, or PNG).'];
    }

    // 4. Validate true MIME type using PHP fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return [false, [], 'Server fileinfo extension is required for secure MIME validation.'];
    }

    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
        'application/pdf' => ['pdf'],
    ];

    if (!isset($allowedMimes[$mimeType]) || !in_array($ext, $allowedMimes[$mimeType], true)) {
        return [false, [], "MIME type verification failed for {$docType} ({$mimeType}). The file content does not match its extension."];
    }

    // 5. Ensure upload directory exists
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
            return [false, [], 'Upload storage directory could not be initialized on the server.'];
        }
    }

    // 6. Generate unguessable random server-side filename (never trust original filename)
    $storedName = sprintf('doc_%s_%s_%s.%s', $docType, bin2hex(random_bytes(12)), time(), $ext);
    $targetPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $storedName;

    // 7. Move file from temporary location to protected storage
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [false, [], "Failed to move uploaded {$docType} to secure destination."];
    }

    // Track path for automated cleanup if transaction subsequently rolls back
    $uploadedPaths[] = $targetPath;

    $docData = [
        'document_type' => $docType,
        'original_name' => sanitize_string($originalName),
        'stored_name'   => $storedName,
        'file_path'     => 'uploads/documents/' . $storedName,
        'mime_type'     => $mimeType,
        'file_size'     => (int)$file['size']
    ];

    return [true, $docData, ''];
}

/**
 * Deletes all files tracked during a failed transaction to prevent orphan disk bloat.
 *
 * @param array $filePaths List of filesystem paths
 * @return void
 */
function cleanup_uploaded_files(array $filePaths): void
{
    foreach ($filePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            @unlink($path);
        }
    }
}
