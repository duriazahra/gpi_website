<?php
/**
 * Govt Polytechnic Institute (GPI) - Application Helpers
 * Application number generation, status badges, JSON responses, and settings helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/security.php';

/**
 * Generates a strictly unique, sequential application reference number.
 * Format: GPI-2026-000001 (Padded to 6 digits).
 *
 * @param PDO $pdo
 * @param string $yearCode
 * @return string
 */
function generate_application_number(PDO $pdo, string $yearCode = '2026'): string
{
    $prefix = "GPI-{$yearCode}-";

    // Query highest current sequence number for this academic year
    $stmt = $pdo->prepare(
        'SELECT application_no 
         FROM applications 
         WHERE application_no LIKE :prefix 
         ORDER BY id DESC 
         LIMIT 1'
    );
    $stmt->execute([':prefix' => $prefix . '%']);
    $lastAppNo = $stmt->fetchColumn();

    $nextSequence = 1;
    if ($lastAppNo && is_string($lastAppNo)) {
        $parts = explode('-', $lastAppNo);
        $lastSeq = (int)end($parts);
        if ($lastSeq > 0) {
            $nextSequence = $lastSeq + 1;
        }
    }

    return sprintf('GPI-%s-%06d', $yearCode, $nextSequence);
}

/**
 * Renders an HTML status badge styled to match the GPI design system.
 *
 * @param string $status
 * @return string
 */
function get_status_badge(string $status): string
{
    $escaped = e($status);
    return match ($status) {
        'Pending' => '<span class="status-pill status-pending" style="display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.8rem; font-weight:700; background:#fef3c7; color:#92400e;">Pending</span>',
        'Under Review' => '<span class="status-pill status-review" style="display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.8rem; font-weight:700; background:#e0f2fe; color:#0369a1;">Under Review</span>',
        'Documents Required' => '<span class="status-pill status-docs" style="display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.8rem; font-weight:700; background:#fee2e2; color:#b91c1c;">Documents Required</span>',
        'Verified' => '<span class="status-pill status-verified" style="display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.8rem; font-weight:700; background:#ecfdf5; color:#047857;">Verified</span>',
        'Accepted' => '<span class="status-pill status-accepted" style="display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.8rem; font-weight:700; background:#fef9c3; color:#854d0e; border:1px solid #facc15;">Accepted</span>',
        'Rejected' => '<span class="status-pill status-rejected" style="display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.8rem; font-weight:700; background:#f1f5f9; color:#475569;">Rejected</span>',
        default => '<span class="status-pill" style="display:inline-flex; align-items:center; padding:0.25rem 0.75rem; border-radius:999px; font-size:0.8rem; font-weight:700; background:#f1f5f9; color:#334155;">' . $escaped . '</span>',
    };
}

/**
 * Returns a standardized JSON response and halts execution.
 *
 * @param array $data
 * @param int $statusCode
 * @return void
 */
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Formats a database date string into human-friendly format.
 *
 * @param string|null $dateStr
 * @param string $format Default: 'd M Y' (e.g. 19 Sep 2026)
 * @return string
 */
function format_date(?string $dateStr, string $format = 'd M Y'): string
{
    if (empty($dateStr)) {
        return '-';
    }
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception) {
        return (string)$dateStr;
    }
}

/**
 * Retrieves a key-value institutional setting from the database.
 *
 * @param PDO $pdo
 * @param string $key
 * @param string $default
 * @return string
 */
function get_setting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    } catch (Exception) {
        return $default;
    }
}

/**
 * Checks if online admissions are currently open and deadline has not passed.
 *
 * @param PDO $pdo
 * @return array [bool $isOpen, string $message]
 */
function check_admissions_open(PDO $pdo): array
{
    $status = get_setting($pdo, 'admissions_status', 'open');
    if (strtolower($status) !== 'open') {
        return [false, 'Admissions for this academic session are currently closed by institute administration.'];
    }

    $deadlineStr = get_setting($pdo, 'admissions_deadline', '');
    if (!empty($deadlineStr)) {
        try {
            $deadline = new DateTime($deadlineStr);
            $now = new DateTime('now');
            if ($now > $deadline) {
                return [false, 'The deadline for application submission (' . $deadline->format('d M Y, h:i A') . ') has passed.'];
            }
        } catch (Exception) {
            // If date unparseable, default to open
        }
    }

    return [true, ''];
}
