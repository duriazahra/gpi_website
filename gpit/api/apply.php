<?php
/**
 * Govt Polytechnic Institute (GPI) - Application Submission API Endpoint
 * Handles multipart form submissions, server-side validation, document uploads,
 * duplicate CNIC detection, and atomic transactional database writes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/uploader.php';
require_once __DIR__ . '/../includes/helpers.php';

// Handle CORS for requests from GitHub Pages or localhost
send_cors_headers();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed. Use POST.'], 405);
}

// 1. Rate Limiting Check (Max 15 submissions per 10 minutes per IP)
if (!check_rate_limit('apply_submission', 15, 600)) {
    json_response([
        'success' => false,
        'message' => 'Too many application attempts from this network. Please wait a few minutes before trying again.'
    ], 429);
}

// 2. Connect to Database
try {
    $pdo = get_db_connection();
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'The admissions service is temporarily unavailable. Please try again shortly.'
    ], 500);
}

// 3. Verify Institutional Admissions Open / Closed Status
[$isOpen, $closureNotice] = check_admissions_open($pdo);
if (!$isOpen) {
    json_response([
        'success' => false,
        'message' => $closureNotice ?: 'Admissions for this academic session are currently closed.'
    ], 403);
}

// 4. Validate CSRF Token (when token is provided)
$csrfToken = $_POST['csrf_token'] 
          ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
          ?? null;

if (!empty($_SESSION['csrf_token']) && !validate_csrf_token($csrfToken)) {
    json_response([
        'success' => false,
        'message' => 'Your security session has expired. Please refresh the page and submit your form again.'
    ], 403);
}

// 5. Server-Side Field Validations
$errors = [];

// Full Name
[$validName, $fullName, $nameErr] = validate_full_name($_POST['full_name'] ?? '');
if (!$validName) $errors[] = $nameErr;

// Father's Name
[$validFName, $fatherName, $fNameErr] = validate_father_name($_POST['father_name'] ?? '');
if (!$validFName) $errors[] = $fNameErr;

// Date of Birth & Age (14 - 35)
[$validDob, $dob, $dobErr] = validate_dob($_POST['date_of_birth'] ?? '');
if (!$validDob) $errors[] = $dobErr;

// Gender
[$validGender, $gender, $genderErr] = validate_gender($_POST['gender'] ?? '');
if (!$validGender) $errors[] = $genderErr;

// CNIC / B-Form Number (Strict 13 digits)
[$validCnic, $cnic, $cnicErr] = validate_cnic($_POST['cnic_bform'] ?? '');
if (!$validCnic) $errors[] = $cnicErr;

// Mobile / WhatsApp Phone Number
[$validPhone, $phone, $phoneErr] = validate_phone($_POST['mobile'] ?? '');
if (!$validPhone) $errors[] = $phoneErr;

// Email Address
[$validEmail, $email, $emailErr] = validate_email($_POST['email'] ?? '');
if (!$validEmail) $errors[] = $emailErr;

// Permanent Address & City
[$validAddr, $address, $city, $addrErr] = validate_address($_POST['address'] ?? '', $_POST['city'] ?? '');
if (!$validAddr) $errors[] = $addrErr;

// Academic Qualification
[$validQual, $qualification, $qualErr] = validate_qualification($_POST['qualification'] ?? '');
if (!$validQual) $errors[] = $qualErr;

// Passing Year (2010 - 2026)
[$validPYear, $passingYear, $pYearErr] = validate_passing_year($_POST['passing_year'] ?? 0);
if (!$validPYear) $errors[] = $pYearErr;

// Examination Board
$board = sanitize_string($_POST['board'] ?? '');
if (mb_strlen($board) > 150) {
    $errors[] = 'Board name cannot exceed 150 characters.';
}

// Marks & Strict Server-Side Recalculated Percentage
[$validMarks, $totalMarks, $obtainedMarks, $serverPercentage, $marksErr] = validate_marks(
    $_POST['total_marks'] ?? 0,
    $_POST['obtained_marks'] ?? 0
);
if (!$validMarks) $errors[] = $marksErr;

// Previous School / College
$previousSchool = sanitize_string($_POST['previous_institution'] ?? '');
if (mb_strlen($previousSchool) < 3 || mb_strlen($previousSchool) > 200) {
    $errors[] = 'Please enter your previous school or college name (between 3 and 200 characters).';
}

// Program Preference
$programInput = $_POST['program_id'] ?? $_POST['selected_program'] ?? '';
[$validProg, $programData, $progErr] = validate_program_selection($pdo, $programInput);
if (!$validProg) $errors[] = $progErr;

// Second Preference (Optional)
$secondProgId = null;
if (!empty($_POST['second_preference_id'])) {
    [$validProg2, $prog2Data] = validate_program_selection($pdo, $_POST['second_preference_id']);
    if ($validProg2 && $prog2Data && $prog2Data['id'] !== $programData['id']) {
        $secondProgId = (int)$prog2Data['id'];
    }
}

// Statement of Purpose / Reason for joining GPI
$reason = sanitize_string($_POST['reason'] ?? $_POST['statement_of_purpose'] ?? '');

// Mandatory Institutional Declaration
[$validDecl, $declarationVal, $declErr] = validate_declaration($_POST['declaration'] ?? '');
if (!$validDecl) $errors[] = $declErr;

// Return validation errors if any field failed
if (!empty($errors)) {
    json_response([
        'success' => false,
        'message' => implode(' ', $errors),
        'errors'  => $errors
    ], 422);
}

// 6. Duplicate Application Protection for Session 2026-27
$existing = find_existing_application($pdo, $cnic, CURRENT_SESSION);
if ($existing) {
    json_response([
        'success' => false,
        'message' => "An admission application already exists for CNIC/B-Form {$cnic} for " . CURRENT_SESSION . ". " .
                     "Reference ID: {$existing['application_no']} (Status: {$existing['status']}). " .
                     "Please use the Application Status tracker to view your current admission status.",
        'duplicate' => true,
        'existing_application_no' => $existing['application_no']
    ], 409);
}

// 7. Secure Document Uploads Handling
$uploadedPaths = [];
$documentsData = [];

// Require passport photograph
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
    json_response(['success' => false, 'message' => 'Please upload a recent passport size photograph.'], 422);
}
[$photoOk, $photoMeta, $photoErr] = handle_document_upload($_FILES['photo'], 'photo', $uploadedPaths);
if (!$photoOk) {
    cleanup_uploaded_files($uploadedPaths);
    json_response(['success' => false, 'message' => $photoErr], 422);
}
$documentsData[] = $photoMeta;

// Require matric result card
if (!isset($_FILES['marksheet']) || $_FILES['marksheet']['error'] === UPLOAD_ERR_NO_FILE) {
    cleanup_uploaded_files($uploadedPaths);
    json_response(['success' => false, 'message' => 'Please upload your Matric result card or certificate.'], 422);
}
[$sheetOk, $sheetMeta, $sheetErr] = handle_document_upload($_FILES['marksheet'], 'marksheet', $uploadedPaths);
if (!$sheetOk) {
    cleanup_uploaded_files($uploadedPaths);
    json_response(['success' => false, 'message' => $sheetErr], 422);
}
$documentsData[] = $sheetMeta;

// Require CNIC or B-Form copy
if (!isset($_FILES['cnic_copy']) || $_FILES['cnic_copy']['error'] === UPLOAD_ERR_NO_FILE) {
    cleanup_uploaded_files($uploadedPaths);
    json_response(['success' => false, 'message' => 'Please upload a clear scanned copy or photo of your CNIC or B-Form.'], 422);
}
[$cnicOk, $cnicMeta, $cnicErr] = handle_document_upload($_FILES['cnic_copy'], 'cnic', $uploadedPaths);
if (!$cnicOk) {
    cleanup_uploaded_files($uploadedPaths);
    json_response(['success' => false, 'message' => $cnicErr], 422);
}
$documentsData[] = $cnicMeta;

// 8. Atomic Database Transaction
try {
    $pdo->beginTransaction();

    // Generate guaranteed unique sequential application number (GPI-2026-XXXXXX)
    $applicationNo = generate_application_number($pdo, SESSION_YEAR_CODE);

    // Insert application record
    $appSql = 'INSERT INTO applications (
        application_no, session_year, full_name, father_name, date_of_birth, gender,
        cnic_bform, mobile, email, address, city, qualification, passing_year, board,
        total_marks, obtained_marks, percentage, previous_institution, program_id,
        second_preference_id, reason, declaration, status, created_at
    ) VALUES (
        :app_no, :session_year, :full_name, :father_name, :dob, :gender,
        :cnic, :mobile, :email, :address, :city, :qualification, :passing_year, :board,
        :total_marks, :obtained_marks, :percentage, :previous_school, :program_id,
        :second_prog_id, :reason, :declaration, :status, NOW()
    )';

    $appStmt = $pdo->prepare($appSql);
    $appStmt->execute([
        ':app_no'          => $applicationNo,
        ':session_year'    => CURRENT_SESSION,
        ':full_name'       => $fullName,
        ':father_name'     => $fatherName,
        ':dob'             => $dob,
        ':gender'          => $gender,
        ':cnic'            => $cnic,
        ':mobile'          => $phone,
        ':email'           => $email,
        ':address'         => $address,
        ':city'            => $city,
        ':qualification'   => $qualification,
        ':passing_year'    => $passingYear,
        ':board'           => $board ?: null,
        ':total_marks'     => $totalMarks,
        ':obtained_marks'  => $obtainedMarks,
        ':percentage'      => $serverPercentage,
        ':previous_school' => $previousSchool,
        ':program_id'      => (int)$programData['id'],
        ':second_prog_id'  => $secondProgId,
        ':reason'          => $reason ?: null,
        ':declaration'     => 1,
        ':status'          => 'Pending'
    ]);

    $applicationId = (int)$pdo->lastInsertId();

    // Insert document records
    $docSql = 'INSERT INTO application_documents (
        application_id, document_type, original_name, stored_name, file_path, mime_type, file_size, created_at
    ) VALUES (
        :app_id, :doc_type, :orig_name, :stored_name, :file_path, :mime_type, :file_size, NOW()
    )';
    $docStmt = $pdo->prepare($docSql);

    foreach ($documentsData as $doc) {
        $docStmt->execute([
            ':app_id'      => $applicationId,
            ':doc_type'    => $doc['document_type'],
            ':orig_name'   => $doc['original_name'],
            ':stored_name' => $doc['stored_name'],
            ':file_path'   => $doc['file_path'],
            ':mime_type'   => $doc['mime_type'],
            ':file_size'   => $doc['file_size']
        ]);
    }

    // Insert initial status history log (system initial submission)
    $histSql = 'INSERT INTO application_status_history (
        application_id, admin_id, old_status, new_status, note, created_at
    ) VALUES (
        :app_id, NULL, NULL, :status, :note, NOW()
    )';
    $histStmt = $pdo->prepare($histSql);
    $histStmt->execute([
        ':app_id' => $applicationId,
        ':status' => 'Pending',
        ':note'   => 'Application submitted online by candidate via GPI Admissions Portal.'
    ]);

    // Commit Transaction
    $pdo->commit();

    // Return success response to user
    json_response([
        'success'         => true,
        'message'         => 'Application Submitted Successfully',
        'application_no'  => $applicationNo,
        'applicant_name'  => $fullName,
        'program_name'    => $programData['name'],
        'phone'           => $phone,
        'percentage'      => number_format($serverPercentage, 2) . '%',
        'submission_date' => date('d M Y'),
        'status'          => 'Pending',
        'pdf_download_url'=> "download_pdf.php?app_no=" . urlencode($applicationNo) . "&cnic=" . urlencode($cnic),
        'status_url'      => "status.php?app_no=" . urlencode($applicationNo) . "&cnic=" . urlencode($cnic)
    ], 201);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Clean up uploaded files so they don't remain orphaned
    cleanup_uploaded_files($uploadedPaths);

    error_log('[GPI Apply Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    json_response([
        'success' => false,
        'message' => 'Something went wrong while submitting your application. Please verify your details and try again.'
    ], 500);
}
