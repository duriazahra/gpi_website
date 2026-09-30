<?php
/**
 * Govt Polytechnic Institute (GPI) - Contact Inquiries Inbox
 * Scrutinize prospective student questions, feedback, and general public inquiries.
 */

declare(strict_types=1);

$pageTitle = 'Contact Messages Inbox';
$activeNav = 'messages';

require_once __DIR__ . '/header.php';

$pdo = get_db_connection();

// Handle POST actions: Mark Read/Unread, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = sanitize_string($_POST['action'] ?? '');
    $msgId  = (int)($_POST['id'] ?? 0);

    if ($action === 'toggle_read' && $msgId > 0) {
        $newRead = (int)($_POST['is_read'] ?? 0) === 1 ? 1 : 0;
        try {
            $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = :rd WHERE id = :id");
            $stmt->execute([':rd' => $newRead, ':id' => $msgId]);
            $_SESSION['flash_success'] = 'Message status updated.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
        }
        header('Location: messages.php');
        exit;
    }

    if ($action === 'delete_message' && $msgId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = :id");
            $stmt->execute([':id' => $msgId]);
            log_admin_action($pdo, $currentAdmin['id'], 'DELETE_MESSAGE', 'contact_messages', $msgId);
            $_SESSION['flash_success'] = 'Inquiry message deleted.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to delete message: ' . $e->getMessage();
        }
        header('Location: messages.php');
        exit;
    }
}

// Filters & Search
$filter = sanitize_string($_GET['filter'] ?? 'all');
$search = sanitize_string($_GET['search'] ?? '');

$where = [];
$params = [];

if ($filter === 'unread') {
    $where[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where[] = "is_read = 1";
}

if (!empty($search)) {
    $where[] = "(name LIKE :search OR email LIKE :search OR phone LIKE :search OR subject LIKE :search OR message LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$messages = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM contact_messages 
        $whereSql
        ORDER BY is_read ASC, created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
} catch (Throwable $e) {}

// Detail view modal check
$selectedMsg = null;
if (isset($_GET['view'])) {
    $vId = (int)$_GET['view'];
    foreach ($messages as $m) {
        if ((int)$m['id'] === $vId) {
            $selectedMsg = $m;
            // Mark as read automatically when viewed
            if ((int)$m['is_read'] === 0) {
                $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute([':id' => $vId]);
            }
            break;
        }
    }
}
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <h2>Contact &amp; Public Inquiries</h2>
    <p>Incoming correspondence submitted through the website contact form</p>
  </div>
  <div style="display:flex; gap:0.5rem;">
    <a href="messages.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>">All Inquiries</a>
    <a href="messages.php?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline' ?>">Unread</a>
    <a href="messages.php?filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-primary' : 'btn-outline' ?>">Read</a>
  </div>
</div>

<!-- Search Bar -->
<div class="stat-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
  <form method="GET" action="messages.php" style="display:flex; gap:0.75rem; align-items:center;">
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <input type="text" name="search" class="form-control" style="font-size:0.88rem; padding:0.5rem 0.85rem;" 
           placeholder="Search sender name, email, phone, subject, or message text..." value="<?= e($search) ?>">
    <button type="submit" class="btn btn-primary btn-sm" style="height:38px; white-space:nowrap;">Search Inbox</button>
    <?php if (!empty($search)): ?>
      <a href="messages.php?filter=<?= e($filter) ?>" class="btn btn-outline btn-sm" style="height:38px;">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Messages Table -->
<div class="table-container" style="margin-bottom: 2rem;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Ticket #</th>
        <th>Sender Details</th>
        <th>Subject</th>
        <th>Date Received</th>
        <th>Status</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($messages)): ?>
        <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--slate-500);">No inquiries matching your criteria.</td></tr>
      <?php else: ?>
        <?php foreach ($messages as $msg): ?>
          <tr style="<?= empty($msg['is_read']) ? 'font-weight:600; background:#f8fafc;' : '' ?>">
            <td style="font-family:var(--font-mono); font-size:0.85rem; color:var(--primary-700);">
              MSG-<?= str_pad((string)$msg['id'], 5, '0', STR_PAD_LEFT) ?>
            </td>
            <td>
              <div style="color:var(--slate-900);"><?= e($msg['name']) ?></div>
              <div style="font-size:0.78rem; color:var(--slate-500); font-weight:normal;">
                <a href="mailto:<?= e($msg['email']) ?>" style="color:var(--primary-600);"><?= e($msg['email']) ?></a>
                <?php if (!empty($msg['phone'])): ?>
                  &bull; <?= e($msg['phone']) ?>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <a href="messages.php?filter=<?= e($filter) ?>&view=<?= (int)$msg['id'] ?>" style="color:var(--slate-900); text-decoration:none;">
                <?= e($msg['subject']) ?>
              </a>
              <div style="font-size:0.75rem; color:var(--slate-500); font-weight:normal; max-width:350px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                <?= e($msg['message']) ?>
              </div>
            </td>
            <td style="font-size:0.82rem; color:var(--slate-600); white-space:nowrap; font-weight:normal;">
              <?= date('d M Y, h:i A', strtotime($msg['created_at'])) ?>
            </td>
            <td>
              <form method="POST" action="messages.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_read">
                <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
                <input type="hidden" name="is_read" value="<?= $msg['is_read'] ? '0' : '1' ?>">
                <button type="submit" class="badge <?= $msg['is_read'] ? 'badge-inactive' : 'badge-under_review' ?>"
                        style="border:none; cursor:pointer;" title="Click to toggle read/unread">
                  <?= $msg['is_read'] ? 'Read' : '● Unread' ?>
                </button>
              </form>
            </td>
            <td style="text-align:right; white-space:nowrap;">
              <a href="messages.php?filter=<?= e($filter) ?>&view=<?= (int)$msg['id'] ?>" class="btn btn-outline btn-sm" style="padding:0.3rem 0.6rem; font-size:0.78rem;">
                View Message
              </a>
              <form method="POST" action="messages.php" style="display:inline;" onsubmit="return confirm('Delete this inquiry message?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_message">
                <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="padding:0.3rem 0.6rem; font-size:0.78rem; color:#dc2626; border-color:#fca5a5;">
                  &times;
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Message Detail Modal / Flyout if view is selected -->
<?php if ($selectedMsg): ?>
  <div class="stat-card" style="border-left: 4px solid var(--primary-700); padding: 1.75rem; margin-bottom: 2rem; background:#fff;">
    <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.75rem;">
      <div>
        <span class="badge badge-under_review" style="margin-bottom:0.5rem;">
          Ticket: MSG-<?= str_pad((string)$selectedMsg['id'], 5, '0', STR_PAD_LEFT) ?>
        </span>
        <h3 style="font-size:1.35rem; margin:0.25rem 0 0 0;"><?= e($selectedMsg['subject']) ?></h3>
      </div>
      <a href="messages.php?filter=<?= e($filter) ?>" class="btn btn-outline btn-sm">&times; Close View</a>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom:1.5rem; background:#f8fafc; padding:1rem; border-radius:var(--radius-md);">
      <div>
        <div style="font-size:0.72rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Sender Name</div>
        <div style="font-weight:700;"><?= e($selectedMsg['name']) ?></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Email Address</div>
        <div><a href="mailto:<?= e($selectedMsg['email']) ?>" style="color:var(--primary-600);"><?= e($selectedMsg['email']) ?></a></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Phone Number</div>
        <div><?= e($selectedMsg['phone'] ?: 'None provided') ?></div>
      </div>
      <div>
        <div style="font-size:0.72rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Received Date</div>
        <div><?= date('d M Y, h:i A', strtotime($selectedMsg['created_at'])) ?></div>
      </div>
    </div>

    <div style="margin-bottom:1.5rem;">
      <div style="font-size:0.76rem; color:var(--slate-500); text-transform:uppercase; font-weight:700; margin-bottom:0.5rem;">Inquiry Message</div>
      <div style="background:#fff; border:1px solid var(--slate-200); border-radius:var(--radius-md); padding:1.25rem; font-size:0.95rem; line-height:1.7; white-space:pre-line;">
        <?= e($selectedMsg['message']) ?>
      </div>
    </div>

    <div style="display:flex; gap:0.75rem;">
      <a href="mailto:<?= e($selectedMsg['email']) ?>?subject=RE: <?= urlencode($selectedMsg['subject']) ?>" class="btn btn-primary btn-sm">
        Reply via Email &rarr;
      </a>
      <a href="messages.php?filter=<?= e($filter) ?>" class="btn btn-outline btn-sm">
        Done Reading
      </a>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
