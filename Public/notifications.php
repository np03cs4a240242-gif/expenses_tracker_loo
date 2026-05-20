<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';
require_once __DIR__ . '/../Src/models/NotificationModel.php';

require_auth();
$user = auth_user();
$title = 'Notifications';

if (is_post()) {
  csrf_verify();

  $action = $_POST['action'] ?? '';

  if ($action === 'mark_all_read') {
    NotificationModel::markAllAsRead((int)$user['id']);
    flash_set('success', 'All notifications marked as read.');
    redirect(base_url('/notifications.php'));
  }

  if ($action === 'mark_read' && !empty($_POST['id'])) {
    NotificationModel::markAsRead((int)$user['id'], (int)$_POST['id']);
    flash_set('success', 'Notification marked as read.');
    redirect(base_url('/notifications.php'));
  }

  if ($action === 'delete' && !empty($_POST['id'])) {
    NotificationModel::delete((int)$user['id'], (int)$_POST['id']);
    flash_set('success', 'Notification deleted.');
    redirect(base_url('/notifications.php'));
  }
}

$notifications = NotificationModel::listForUser((int)$user['id'], 100);
$unreadCount = NotificationModel::unreadCount((int)$user['id']);

require_once __DIR__ . '/../Src/partials/header.php';
?>

<div class="page-head">
  <div>
    <h1>Notifications</h1>
    <p class="muted">
      <?php if ($unreadCount > 0): ?>
        <?= $unreadCount ?> unread notification(s)
      <?php else: ?>
        All caught up!
      <?php endif; ?>
    </p>
  </div>
  <div class="actions">
    <?php if ($unreadCount > 0): ?>
      <form method="post" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button class="btn btn-ghost" type="submit">Mark all as read</button>
      </form>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(base_url('/notification_settings.php')) ?>">Settings</a>
  </div>
</div>

<?php if (!$notifications): ?>
  <section class="card">
    <p class="muted" style="text-align:center; padding: var(--space-8) 0;">No notifications yet.</p>
  </section>
<?php else: ?>
  <section class="card">
    <div class="table">
      <div class="table-row head">
        <div style="width:40px;"></div>
        <div>Notification</div>
        <div style="width:120px;">Time</div>
        <div style="width:120px;" class="right">Actions</div>
      </div>

      <?php foreach ($notifications as $n): ?>
        <div class="table-row <?= !$n['is_read'] ? 'unread' : '' ?>">
          <div>
            <?php if (!$n['is_read']): ?>
              <span class="notif-dot"></span>
            <?php endif; ?>
          </div>
          <div>
            <div class="notif-title">
              <span class="badge badge-<?= e($n['severity']) ?>"><?= e(ucfirst($n['type'])) ?></span>
              <?= e($n['title']) ?>
            </div>
            <div class="notif-message muted small"><?= nl2br(e($n['message'])) ?></div>
            <?php if ($n['action_url']): ?>
              <a href="<?= e($n['action_url']) ?>" class="small">View details →</a>
            <?php endif; ?>
          </div>
          <div class="muted small"><?= e(date('M d, H:i', strtotime((string)$n['created_at']))) ?></div>
          <div class="right">
            <?php if (!$n['is_read']): ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Mark read</button>
              </form>
            <?php endif; ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
              <button class="btn btn-sm btn-ghost text-danger" type="submit">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<style>
.unread { background: var(--surface); }
.notif-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: var(--accent);
  border-radius: 50%;
}
.notif-title {
  font-weight: 600;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.notif-message {
  margin-top: 4px;
  line-height: 1.4;
}
.badge-info { background: #dbeafe; color: #1e40af; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-critical { background: #fee2e2; color: #991b1b; }
.badge-success { background: #d1fae5; color: #065f46; }
.text-danger { color: #dc2626; }
</style>

<?php require_once __DIR__ . '/../Src/partials/footer.php'; ?>
