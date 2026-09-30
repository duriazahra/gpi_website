<?php
/**
 * Govt Polytechnic Institute (GPI) - Applications CSV Export Engine
 * Securely streams filtered candidate records in Excel-compatible UTF-8 CSV format.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

start_secure_session();
$currentAdmin = require_admin();

$pdo = get_db_connection();

// Read query params for filtering
$search    = sanitize_string($_GET['search'] ?? '');
$programId = isset($_GET['program_id']) && $_GET['program_id'] !== '' ? (int)$_GET['program_id'] : null;
$status    = sanitize_string($_GET['status'] ?? '');
$gender    = sanitize_string($_GET['gender'] ?? '');
$sortBy    = sanitize_string($_GET['sort'] ?? 'created_at_desc');

// Build WHERE query
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(a.application_number LIKE :search OR a.full_name LIKE :search OR a.cnic LIKE :search OR a.phone LIKE :search OR a.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($programId !== null && $programId > 0) {
    $whereClauses[] = "a.program_id = :program_id";
    $params[':program_id'] = $programId;
}

if (!empty($status)) {
    $whereClauses[] = "a.status = :status";
    $params[':status'] = $status;
}

if (!empty($gender)) {
    $whereClauses[] = "a.gender = :gender";
    $params[':gender'] = $gender;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$orderMap = [
    'created_at_desc' => 'a.created_at DESC',
    'created_at_asc'  => 'a.created_at ASC',
    'percentage_desc' => 'a.matric_percentage DESC, a.created_at ASC',
    'percentage_asc'  => 'a.matric_percentage ASC',
    'name_asc'        => 'a.full_name ASC',
    'name_desc'       => 'a.full_name DESC'
];
$orderBy = $orderMap[$sortBy] ?? 'a.created_at DESC';

// Log export action
log_admin_action($pdo, $currentAdmin['id'], 'EXPORT_APPLICATIONS_CSV', 'applications', null, [
    'search'     => $search,
    'program_id' => $programId,
    'status'     => $status,
    'gender'     => $gender
]);

// Clear output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'GPI_Admissions_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header Row
fputcsv($output, [
    'Application Number',
    'Full Name',
    'Father Name',
    'CNIC / B-Form',
    'Gender',
    'Date of Birth',
    'Mobile Phone',
    'Guardian Phone',
    'Email Address',
    'City',
    'Permanent Address',
    'Domicile District',
    'Program Code',
    'Program Title',
    'Session Year',
    'Matric Board',
    'Matric Roll Number',
    'Matric Passing Year',
    'Matric Total Marks',
    'Matric Obtained Marks',
    'Matric Percentage (%)',
    'Application Status',
    'Submission Date',
    'Admin Scrutiny Notes'
]);

try {
    $sql = "
        SELECT a.*, p.title as program_title, p.code as program_code
        FROM applications a
        JOIN programs p ON a.program_id = p.id
        $whereSql
        ORDER BY $orderBy
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['application_number'],
            $row['full_name'],
            $row['father_name'],
            $row['cnic'],
            ucfirst($row['gender']),
            $row['date_of_birth'],
            $row['phone'],
            $row['guardian_phone'] ?? '',
            $row['email'] ?? '',
            $row['city'],
            $row['address'],
            $row['domicile_district'] ?? '',
            $row['program_code'],
            $row['program_title'],
            $row['session_year'],
            $row['matric_board'],
            $row['matric_roll_number'],
            $row['matric_passing_year'],
            $row['matric_total_marks'],
            $row['matric_obtained_marks'],
            number_format((float)$row['matric_percentage'], 2),
            strtoupper(str_replace('_', ' ', $row['status'])),
            $row['created_at'],
            $row['admin_notes'] ?? ''
        ]);
    }
} catch (Throwable $e) {
    fputcsv($output, ['Error generating export', $e->getMessage()]);
}

fclose($output);
exit;
