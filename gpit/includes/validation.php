<?php
/**
 * Govt Polytechnic Institute (GPI) - Server-side Validation Engine
 * Strict verification of personal, academic, program, and declaration data.
 */

declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * Validates candidate's full name.
 *
 * @param string $name
 * @return array [bool $valid, string $cleaned, string $error]
 */
function validate_full_name(string $name): array
{
    $cleaned = sanitize_string($name);
    if (mb_strlen($cleaned) < 3 || mb_strlen($cleaned) > 100) {
        return [false, $cleaned, 'Full name must be between 3 and 100 characters.'];
    }
    if (!preg_match('/^[a-zA-Z\s\.\'\-]+$/u', $cleaned)) {
        return [false, $cleaned, 'Full name can only contain letters, spaces, dots, and hyphens.'];
    }
    return [true, $cleaned, ''];
}

/**
 * Validates candidate's father's name.
 *
 * @param string $fatherName
 * @return array [bool $valid, string $cleaned, string $error]
 */
function validate_father_name(string $fatherName): array
{
    $cleaned = sanitize_string($fatherName);
    if (mb_strlen($cleaned) < 3 || mb_strlen($cleaned) > 100) {
        return [false, $cleaned, "Father's name must be between 3 and 100 characters."];
    }
    if (!preg_match('/^[a-zA-Z\s\.\'\-]+$/u', $cleaned)) {
        return [false, $cleaned, "Father's name can only contain letters, spaces, dots, and hyphens."];
    }
    return [true, $cleaned, ''];
}

/**
 * Validates candidate's Date of Birth and institutional age criteria (14 - 35 years).
 *
 * @param string $dob Date string YYYY-MM-DD
 * @param int $minAge
 * @param int $maxAge
 * @return array [bool $valid, string $dob, string $error]
 */
function validate_dob(string $dob, int $minAge = 14, int $maxAge = 35): array
{
    $cleaned = sanitize_string($dob);
    $d = DateTime::createFromFormat('Y-m-d', $cleaned);
    if (!$d || $d->format('Y-m-d') !== $cleaned) {
        return [false, '', 'Please provide a valid date of birth in YYYY-MM-DD format.'];
    }

    $now = new DateTime('now');
    $age = $now->diff($d)->y;

    if ($age < $minAge || $age > $maxAge) {
        return [false, $cleaned, "Candidate age must be between {$minAge} and {$maxAge} years on application date (Current: {$age})."];
    }

    return [true, $cleaned, ''];
}

/**
 * Validates gender selection.
 *
 * @param string $gender
 * @return array [bool $valid, string $gender, string $error]
 */
function validate_gender(string $gender): array
{
    $cleaned = sanitize_string($gender);
    $allowed = ['Male', 'Female', 'Other'];
    if (!in_array($cleaned, $allowed, true)) {
        return [false, '', 'Please select a valid gender option (Male, Female, or Other).'];
    }
    return [true, $cleaned, ''];
}

/**
 * Validates and standardizes Pakistani CNIC or NADRA Computerized B-Form number.
 * Accepts formats: 12345-1234567-1 or 1234512345671.
 * Standardizes to: 12345-1234567-1.
 *
 * @param string $cnic
 * @return array [bool $valid, string $standardizedCnic, string $error]
 */
function validate_cnic(string $cnic): array
{
    $digits = preg_replace('/\D/', '', $cnic);
    if (strlen($digits) !== 13) {
        return [false, '', 'CNIC / B-Form must contain exactly 13 numeric digits (e.g. 12345-1234567-1).'];
    }

    $formatted = sprintf(
        '%s-%s-%s',
        substr($digits, 0, 5),
        substr($digits, 5, 7),
        substr($digits, 12, 1)
    );

    return [true, $formatted, ''];
}

/**
 * Validates Pakistani Mobile / WhatsApp phone number.
 * Accepts formats: 0300-1234567, 03001234567, +92 300 1234567.
 * Standardizes to: 0300-1234567.
 *
 * @param string $phone
 * @return array [bool $valid, string $formattedPhone, string $error]
 */
function validate_phone(string $phone): array
{
    $cleaned = sanitize_string($phone);
    $digits = preg_replace('/\D/', '', $cleaned);

    // Normalize +92 format to leading 0
    if (str_starts_with($digits, '92') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    }

    if (strlen($digits) !== 11 || !str_starts_with($digits, '03')) {
        return [false, '', 'Please enter a valid 11-digit Pakistani mobile number starting with 03 (e.g. 0300-1234567).'];
    }

    $formatted = substr($digits, 0, 4) . '-' . substr($digits, 4);
    return [true, $formatted, ''];
}

/**
 * Validates candidate's email address.
 *
 * @param string $email
 * @return array [bool $valid, string $email, string $error]
 */
function validate_email(string $email): array
{
    $cleaned = sanitize_string($email);
    if (!filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
        return [false, '', 'Please provide a valid, deliverable email address.'];
    }
    return [true, strtolower($cleaned), ''];
}

/**
 * Validates residential address and city.
 *
 * @param string $address
 * @param string $city
 * @return array [bool $valid, string $cleanAddress, string $cleanCity, string $error]
 */
function validate_address(string $address, string $city): array
{
    $cleanAddress = sanitize_string($address);
    $cleanCity = sanitize_string($city);

    if (mb_strlen($cleanAddress) < 8 || mb_strlen($cleanAddress) > 500) {
        return [false, '', '', 'Permanent address must be between 8 and 500 characters.'];
    }
    if (mb_strlen($cleanCity) < 2 || mb_strlen($cleanCity) > 100) {
        return [false, '', '', 'Please enter your city or district name.'];
    }

    return [true, $cleanAddress, $cleanCity, ''];
}

/**
 * Validates academic qualification category.
 *
 * @param string $qualification
 * @return array [bool $valid, string $cleanQualification, string $error]
 */
function validate_qualification(string $qualification): array
{
    $cleaned = sanitize_string($qualification);
    $allowed = [
        'Matriculation (Science)',
        'Matriculation (Computer Science)',
        'O-Levels (Science Equivalence)',
        'Technical Matric / Vocational',
        // Support frontend values as well
        'Matric Science',
        'Matric Computer',
        'O-Level',
        'Technical Matric'
    ];

    if (!in_array($cleaned, $allowed, true)) {
        return [false, '', 'Please select a valid qualification group.'];
    }

    // Standardize to official label
    $map = [
        'Matric Science'   => 'Matriculation (Science)',
        'Matric Computer'  => 'Matriculation (Computer Science)',
        'O-Level'          => 'O-Levels (Science Equivalence)',
        'Technical Matric' => 'Technical Matric / Vocational'
    ];
    $normalized = $map[$cleaned] ?? $cleaned;

    return [true, $normalized, ''];
}

/**
 * Validates passing year.
 *
 * @param mixed $year
 * @param int $minYear
 * @param int $maxYear
 * @return array [bool $valid, int $year, string $error]
 */
function validate_passing_year(mixed $year, int $minYear = 2010, int $maxYear = 2026): array
{
    $yearInt = (int)$year;
    if ($yearInt < $minYear || $yearInt > $maxYear) {
        return [false, 0, "Passing year must be between {$minYear} and {$maxYear}."];
    }
    return [true, $yearInt, ''];
}

/**
 * Validates total and obtained marks, and strictly calculates percentage on server.
 * Never trusts any percentage sent from browser.
 *
 * @param mixed $totalMarks
 * @param mixed $obtainedMarks
 * @return array [bool $valid, float $total, float $obtained, float $percentage, string $error]
 */
function validate_marks(mixed $totalMarks, mixed $obtainedMarks): array
{
    if (!is_numeric($totalMarks) || !is_numeric($obtainedMarks)) {
        return [false, 0.0, 0.0, 0.0, 'Total and obtained marks must be valid numbers.'];
    }

    $total = (float)$totalMarks;
    $obtained = (float)$obtainedMarks;

    if ($total <= 0) {
        return [false, 0.0, 0.0, 0.0, 'Total marks must be greater than zero.'];
    }
    if ($obtained < 0) {
        return [false, 0.0, 0.0, 0.0, 'Obtained marks cannot be negative.'];
    }
    if ($obtained > $total) {
        return [false, 0.0, 0.0, 0.0, 'Obtained marks cannot exceed total marks.'];
    }

    // Server-side recalculation of percentage
    $percentage = round(($obtained / $total) * 100, 2);

    return [true, $total, $obtained, $percentage, ''];
}

/**
 * Validates that the selected program exists in database and is currently active.
 *
 * @param PDO $pdo
 * @param mixed $programIdentifier Program ID or code
 * @return array [bool $valid, array|null $program, string $error]
 */
function validate_program_selection(PDO $pdo, mixed $programIdentifier): array
{
    if (empty($programIdentifier)) {
        return [false, null, 'Please select your preferred technical program.'];
    }

    if (is_numeric($programIdentifier)) {
        $stmt = $pdo->prepare('SELECT id, code, name, duration, active FROM programs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$programIdentifier]);
    } else {
        $stmt = $pdo->prepare('SELECT id, code, name, duration, active FROM programs WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => sanitize_string((string)$programIdentifier)]);
    }

    $program = $stmt->fetch();
    if (!$program) {
        return [false, null, 'The selected program is not recognized in the institutional catalog.'];
    }
    if (empty($program['active'])) {
        return [false, null, 'Admissions for the selected program are currently inactive.'];
    }

    return [true, $program, ''];
}

/**
 * Checks if candidate has accepted institutional declaration.
 *
 * @param mixed $declaration
 * @return array [bool $valid, int $value, string $error]
 */
function validate_declaration(mixed $declaration): array
{
    if (empty($declaration) || !in_array($declaration, ['1', 1, 'on', 'true', true], true)) {
        return [false, 0, 'You must accept the institutional declaration to proceed with your application.'];
    }
    return [true, 1, ''];
}

/**
 * Checks whether an application already exists for the given CNIC in the given academic session.
 *
 * @param PDO $pdo
 * @param string $cnic Formatted CNIC
 * @param string $sessionYear
 * @return array|null Existing application record if found, or null
 */
function find_existing_application(PDO $pdo, string $cnic, string $sessionYear = '2026-27'): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, application_no, full_name, status, created_at 
         FROM applications 
         WHERE cnic_bform = :cnic AND session_year = :session_year 
         LIMIT 1'
    );
    $stmt->execute([
        ':cnic'         => $cnic,
        ':session_year' => $sessionYear
    ]);

    $result = $stmt->fetch();
    return $result ?: null;
}
