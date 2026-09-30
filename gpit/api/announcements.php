<?php
/**
 * Govt Polytechnic Institute (GPI) - Announcements Public API
 * Provides live, published notifications to the frontend ticker and admissions portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

send_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->query("
        SELECT id, title, content, category, priority, publish_at
        FROM announcements
        WHERE is_published = 1 
          AND publish_at <= CURRENT_TIMESTAMP
          AND (expires_at IS NULL OR expires_at >= CURRENT_TIMESTAMP)
        ORDER BY 
          CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            ELSE 4 
          END ASC, 
          publish_at DESC
        LIMIT 10
    ");

    $announcements = $stmt->fetchAll();

    $data = array_map(function($ann) {
        return [
            'id'         => (int)$ann['id'],
            'title'      => e($ann['title']),
            'content'    => e($ann['content']),
            'category'   => e($ann['category']),
            'priority'   => e($ann['priority']),
            'publish_at' => date('d M Y', strtotime($ann['publish_at']))
        ];
    }, $announcements);

    header('Cache-Control: public, max-age=60');
    json_response([
        'success'       => true,
        'count'         => count($data),
        'announcements' => $data
    ]);

} catch (Throwable $e) {
    json_response([
        'success'       => false,
        'error'         => 'Failed to retrieve announcements.',
        'announcements' => []
    ], 500);
}
