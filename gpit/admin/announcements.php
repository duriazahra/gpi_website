<?php
/**
 * Govt Polytechnic Institute (GPI) - Announcements Management
 * Publish notifications, admission deadlines, examination circulars, and merit lists.
 */

declare(strict_types=1);

$pageTitle = 'Institutional Announcements';
$activeNav = 'announcements';

require_once __DIR__ . '/header.php';

$pdo = get_db_connection();

// Handle Actions (Save, Toggle Publish, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = sanitize_string($_POST['action'] ?? '');

    if ($action === 'toggle_publish') {
        $annId = (int)($_POST['id'] ?? 0);
        $newPub = (int)($_POST['is_published'] ?? 0) === 1 ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE announcements SET is_published = :pub, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':pub' => $newPub, ':id' => $annId]);

            log_admin_action($pdo, $currentAdmin['id'], 'UPDATE_ANNOUNCEMENT_STATUS', 'announcements', $annId, [
                'is_published' => $newPub
            ]);

            $_SESSION['flash_success'] = 'Announcement visibility updated.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
        }

        header('Location: announcements.php');
        exit;
    }

    if ($action === 'delete_announcement') {
        $annId = (int)($_POST['id'] ?? 0);

        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id");
            $stmt->execute([':id' => $annId]);

            log_admin_action($pdo, $currentAdmin['id'], 'DELETE_ANNOUNCEMENT', 'announcements', $annId);
            $_SESSION['flash_success'] = 'Announcement deleted successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to delete announcement: ' . $e->getMessage();
        }

        header('Location: announcements.php');
        exit;
    }

    if ($action === 'save_announcement') {
        $annId     = (int)($_POST['id'] ?? 0);
        $title     = sanitize_string($_POST['title'] ?? '');
        $category  = sanitize_string($_POST['category'] ?? 'Admissions');
        $priority  = sanitize_string($_POST['priority'] ?? 'medium');
        $content   = sanitize_string($_POST['content'] ?? '');
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $publishAt = !empty($_POST['publish_at']) ? sanitize_string($_POST['publish_at']) : date('Y-m-d H:i:s');
        $expiresAt = !empty($_POST['expires_at']) ? sanitize_string($_POST['expires_at']) : null;

        if (empty($title) || empty($content)) {
            $_SESSION['flash_error'] = 'Title and content cannot be empty.';
            header('Location: announcements.php');
            exit;
        }

        try {
            if ($annId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE announcements 
                    SET title = :title, category = :category, priority = :priority,
                        content = :content, is_published = :pub, publish_at = :pub_at,
                        expires_at = :exp_at, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title'    => $title,
                    ':category' => $category,
                    ':priority' => $priority,
                    ':content'  => $content,
                    ':pub'      => $isPublished,
                    ':pub_at'   => $publishAt,
                    ':exp_at'   => $expiresAt,
                    ':id'       => $annId
                ]);

                log_admin_action($pdo, $currentAdmin['id'], 'UPDATE_ANNOUNCEMENT', 'announcements', $annId, ['title' => $title]);
                $_SESSION['flash_success'] = 'Announcement updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements 
                        (title, content, category, priority, is_published, publish_at, expires_at, created_by)
                    VALUES 
                        (:title, :content, :category, :priority, :pub, :pub_at, :exp_at, :created_by)
                ");
                $stmt->execute([
                    ':title'      => $title,
                    ':content'    => $content,
                    ':category'   => $category,
                    ':priority'   => $priority,
                    ':pub'        => $isPublished,
                    ':pub_at'     => $publishAt,
                    ':exp_at'     => $expiresAt,
                    ':created_by' => $currentAdmin['id']
                ]);

                $newId = (int)$pdo->lastInsertId();
                log_admin_action($pdo, $currentAdmin['id'], 'CREATE_ANNOUNCEMENT', 'announcements', $newId, ['title' => $title]);
                $_SESSION['flash_success'] = 'New announcement published successfully.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
        }

        header('Location: announcements.php');
        exit;
    }
}

// Fetch announcements
$announcements = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, adm.name as author_name 
        FROM announcements a
        LEFT JOIN admins adm ON a.created_by = adm.id
        ORDER BY a.created_at DESC
    ");
    $announcements = $stmt->fetchAll();
} catch (Throwable $e) {}

// Edit mode check
$editAnn = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($announcements as $a) {
        if ((int)$a['id'] === $editId) {
            $editAnn = $a;
            break;
        }
    }
}
?>

<div class="admin-page-header">
  <div class="admin-page-title">
    <h2>Public Notices &amp; Announcements</h2>
    <p>Broadcast updates to applicants, students, and website visitors</p>
  </div>
  <div>
    <a href="#annFormCard" class="btn btn-primary btn-sm" onclick="document.getElementById('annFormCard').scrollIntoView({behavior:'smooth'});">
      + Post New Announcement
    </a>
  </div>
</div>

<!-- Announcements Table -->
<div class="table-container" style="margin-bottom: 2rem;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Title / Headline</th>
        <th>Category</th>
        <th>Priority</th>
        <th>Status</th>
        <th>Publish Date</th>
        <th>Author</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($announcements)): ?>
        <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--slate-500);">No announcements published yet.</td></tr>
      <?php else: ?>
        <?php foreach ($announcements as $ann): ?>
          <tr>
            <td>
              <div style="font-weight:700; color:var(--slate-900);"><?= e($ann['title']) ?></div>
              <div style="font-size:0.78rem; color:var(--slate-500); max-width:400px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                <?= e($ann['content']) ?>
              </div>
            </td>
            <td>
              <span style="font-size:0.8rem; font-weight:600; background:#f1f5f9; padding:0.2rem 0.6rem; border-radius:4px;">
                <?= e($ann['category']) ?>
              </span>
            </td>
            <td>
              <?php
                $pColors = [
                  'urgent' => '#dc2626',
                  'high'   => '#ea580c',
                  'medium' => '#0284c7',
                  'low'    => '#64748b'
                ];
              ?>
              <span style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:<?= $pColors[$ann['priority']] ?? '#64748b' ?>;">
                ● <?= e($ann['priority']) ?>
              </span>
            </td>
            <td>
              <form method="POST" action="announcements.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="id" value="<?= (int)$ann['id'] ?>">
                <input type="hidden" name="is_published" value="<?= $ann['is_published'] ? '0' : '1' ?>">
                <button type="submit" class="badge <?= $ann['is_published'] ? 'badge-active' : 'badge-inactive' ?>"
                        style="border:none; cursor:pointer;" title="Click to toggle publish status">
                  <?= $ann['is_published'] ? 'Published' : 'Draft' ?>
                </button>
              </form>
            </td>
            <td style="font-size:0.82rem; color:var(--slate-600); white-space:nowrap;">
              <?= date('d M Y', strtotime($ann['publish_at'])) ?>
            </td>
            <td style="font-size:0.82rem; color:var(--slate-600);">
              <?= e($ann['author_name'] ?: 'Staff') ?>
            </td>
            <td style="text-align:right; white-space:nowrap;">
              <a href="announcements.php?edit=<?= (int)$ann['id'] ?>#annFormCard" class="btn btn-outline btn-sm" style="padding:0.3rem 0.6rem; font-size:0.75rem;">
                Edit
              </a>
              <form method="POST" action="announcements.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_announcement">
                <input type="hidden" name="id" value="<?= (int)$ann['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="padding:0.3rem 0.6rem; font-size:0.75rem; color:#dc2626; border-color:#fca5a5;">
                  Delete
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add / Edit Announcement Form -->
<div id="annFormCard" class="stat-card" style="padding: 1.75rem; max-width: 800px; margin: 0 auto;">
  <h3 style="font-size:1.25rem; margin-bottom:0.35rem;">
    <?= $editAnn ? 'Edit Announcement' : 'Create New Announcement' ?>
  </h3>
  <p style="font-size:0.84rem; color:var(--slate-500); margin-bottom:1.5rem;">
    Announcements appear on the homepage notifications ticker and the admissions portal.
  </p>

  <form method="POST" action="announcements.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_announcement">
    <input type="hidden" name="id" value="<?= $editAnn ? (int)$editAnn['id'] : 0 ?>">

    <div class="form-group" style="margin-bottom: 1.25rem;">
      <label for="annTitle">Headline / Announcement Title <span class="required">*</span></label>
      <input type="text" id="annTitle" name="title" class="form-control" 
             placeholder="e.g. Admissions Open for Academic Session 2026-2027"
             value="<?= e($editAnn['title'] ?? '') ?>" required>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
      <div class="form-group">
        <label for="annCat">Category <span class="required">*</span></label>
        <select id="annCat" name="category" class="form-control" required>
          <?php $cat = $editAnn['category'] ?? 'Admissions'; ?>
          <option value="Admissions" <?= $cat === 'Admissions' ? 'selected' : '' ?>>Admissions</option>
          <option value="Examinations" <?= $cat === 'Examinations' ? 'selected' : '' ?>>Examinations</option>
          <option value="Merit Lists" <?= $cat === 'Merit Lists' ? 'selected' : '' ?>>Merit Lists</option>
          <option value="Scholarships" <?= $cat === 'Scholarships' ? 'selected' : '' ?>>Scholarships</option>
          <option value="General" <?= $cat === 'General' ? 'selected' : '' ?>>General Notice</option>
        </select>
      </div>

      <div class="form-group">
        <label for="annPriority">Display Priority <span class="required">*</span></label>
        <select id="annPriority" name="priority" class="form-control" required>
          <?php $pri = $editAnn['priority'] ?? 'medium'; ?>
          <option value="urgent" <?= $pri === 'urgent' ? 'selected' : '' ?>>Urgent (Red Alert)</option>
          <option value="high" <?= $pri === 'high' ? 'selected' : '' ?>>High Priority</option>
          <option value="medium" <?= $pri === 'medium' ? 'selected' : '' ?>>Medium Priority</option>
          <option value="low" <?= $pri === 'low' ? 'selected' : '' ?>>Low Priority</option>
        </select>
      </div>

      <div class="form-group">
        <label for="annPubDate">Publish Date</label>
        <input type="datetime-local" id="annPubDate" name="publish_at" class="form-control"
               value="<?= !empty($editAnn['publish_at']) ? date('Y-m-d\TH:i', strtotime($editAnn['publish_at'])) : date('Y-m-d\TH:i') ?>">
      </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.25rem;">
      <label for="annContent">Announcement Details &amp; Message Body <span class="required">*</span></label>
      <textarea id="annContent" name="content" class="form-control" rows="5"
                placeholder="Full notice content, instructions, or deadlines..." required><?= e($editAnn['content'] ?? '') ?></textarea>
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem; display:flex; align-items:center; gap:0.5rem;">
      <input type="checkbox" id="annPub" name="is_published" value="1"
             <?= (!$editAnn || !empty($editAnn['is_published'])) ? 'checked' : '' ?> style="width:18px; height:18px;">
      <label for="annPub" style="margin:0; font-weight:600; cursor:pointer;">
        Publish Immediately (Visible to public)
      </label>
    </div>

    <div style="display:flex; gap:0.75rem;">
      <button type="submit" class="btn btn-primary btn-sm" style="padding:0.75rem 1.5rem;">
        <?= $editAnn ? 'Save Announcement' : 'Post Announcement' ?>
      </button>
      <?php if ($editAnn): ?>
        <a href="announcements.php" class="btn btn-outline btn-sm" style="padding:0.75rem 1.25rem;">
          Cancel Edit
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
