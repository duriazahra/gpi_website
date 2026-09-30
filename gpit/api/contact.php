<?php
/**
 * Govt Polytechnic Institute (GPI) - Public Contact Form API
 * Ingests prospective student and visitor inquiries into the database with rate limiting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/helpers.php';

send_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
}

// Rate limit: 5 messages per 5 minutes per IP
if (!check_rate_limit('contact_form', 5, 300)) {
    json_response([
        'success' => false,
        'error'   => 'You are sending messages too quickly. Please wait a few minutes before submitting again.'
    ], 429);
}

// Support both JSON body and standard Form POST
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$name    = sanitize_string($jsonData['name'] ?? $_POST['name'] ?? '');
$email   = sanitize_string($jsonData['email'] ?? $_POST['email'] ?? '');
$phone   = sanitize_string($jsonData['phone'] ?? $_POST['phone'] ?? '');
$subject = sanitize_string($jsonData['subject'] ?? $_POST['subject'] ?? '');
$message = sanitize_string($jsonData['message'] ?? $_POST['message'] ?? '');

$errors = [];

if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
    $errors['name'] = 'Full Name must be between 3 and 100 characters.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'A valid email address is required.';
}

if (!empty($phone) && !validate_pakistani_mobile($phone)) {
    $errors['phone'] = 'Mobile number must follow Pakistani format (e.g. 0300-1234567).';
}

if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
    $errors['subject'] = 'Subject must be between 3 and 150 characters.';
}

if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
    $errors['message'] = 'Inquiry message must be between 10 and 3,000 characters.';
}

if (!empty($errors)) {
    json_response([
        'success' => false,
        'error'   => 'Please correct the validation errors.',
        'errors'  => $errors
    ], 422);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        INSERT INTO contact_messages 
            (name, email, phone, subject, message, is_read, ip_address, user_agent, created_at)
        VALUES 
            (:name, :email, :phone, :subject, :message, 0, :ip, :ua, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        ':name'    => $name,
        ':email'   => strtolower($email),
        ':phone'   => $phone ?: null,
        ':subject' => $subject,
        ':message' => $message,
        ':ip'      => get_client_ip(),
        ':ua'      => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
    ]);

    $insertId = (int)$pdo->lastInsertId();
    $ticketRef = 'MSG-' . str_pad((string)$insertId, 5, '0', STR_PAD_LEFT);

    json_response([
        'success'   => true,
        'ref_code'  => $ticketRef,
        'message'   => "Your message has been received under Ticket {$ticketRef}. Institute administration will respond via email shortly."
    ]);

} catch (Throwable $e) {
    json_response([
        'success' => false,
        'error'   => 'An unexpected server error occurred. Please try again later.'
    ], 500);
}
