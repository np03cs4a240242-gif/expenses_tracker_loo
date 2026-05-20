<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';
require_once __DIR__ . '/../Src/models/UserPreferencesModel.php';

require_auth();
$user = auth_user();
$title = 'Notification Settings';

$prefs = UserPreferencesModel::getOrCreate((int)$user['id']);

$errors = ['general' => ''];

if (is_post()) {
  csrf_verify();

  $newPrefs = [
    'budget_exceeded' => isset($_POST['budget_exceeded']) ? 1 : 0,
    'budget_warning_threshold' => max(50, min(99, (float)($_POST['budget_warning_threshold'] ?? 80))),
    'unusual_spending' => isset($_POST['unusual_spending']) ? 1 : 0,
    'unusual_spending_multiplier' => max(1.5, min(5.0, (float)($_POST['unusual_spending_multiplier'] ?? 2.0))),
    'expense_reminder' => isset($_POST['expense_reminder']) ? 1 : 0,
    'reminder_frequency' => in_array($_POST['reminder_frequency'] ?? '', ['daily', 'weekly', 'none'], true) ? $_POST['reminder_frequency'] : 'daily',
    'reminder_time' => preg_match('/^\d{2}:\d{2}$/', $_POST['reminder_time'] ?? '') ? $_POST['reminder_time'] : '20:00',
    'weekly_summary' => isset($_POST['weekly_summary']) ? 1 : 0,
    'weekly_summary_day' => in_array($_POST['weekly_summary_day'] ?? '', ['monday', 'sunday'], true) ? $_POST['weekly_summary_day'] : 'monday',
  ];

  try {
    UserPreferencesModel::update((int)$user['id'], $newPrefs);
    flash_set('success', 'Notification settings saved.');
    redirect(base_url('/notification_settings.php'));
  } catch (Throwable $e) {
    $errors['general'] = 'Failed to save settings.';
  }
}

require_once __DIR__ . '/../Src/partials/header.php';
?>

<div class="page-head">
  <div>
    <h1>Notification Settings</h1>
    <p class="muted">Customize which alerts and reminders you want to receive.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(base_url('/notifications.php')) ?>">← Back to Notifications</a>
  </div>
</div>

<?php if ($errors['general']): ?>
  <div class="alert alert-error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form method="post" class="form">
  <?= csrf_field() ?>

  <section class="card" style="margin-bottom: var(--space-6);">
    <div class="card-head">
      <h2>Budget Alerts</h2>
    </div>

    <div class="field" style="margin-bottom: var(--space-4);">
      <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
        <input type="checkbox" name="budget_exceeded" value="1" <?= (bool)$prefs['budget_exceeded'] ? 'checked' : '' ?>>
        <span>Notify when budget is exceeded or approaching limit</span>
      </label>
    </div>

    <div class="field">
      <label>Warning threshold (%)</label>
      <input type="number" name="budget_warning_threshold" value="<?= e((string)$prefs['budget_warning_threshold']) ?>" min="50" max="99" step="5">
      <div class="hint muted">Get a warning when spending reaches this percentage of your budget.</div>
    </div>
  </section>

  <section class="card" style="margin-bottom: var(--space-6);">
    <div class="card-head">
      <h2>Unusual Spending Alerts</h2>
    </div>

    <div class="field" style="margin-bottom: var(--space-4);">
      <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
        <input type="checkbox" name="unusual_spending" value="1" <?= (bool)$prefs['unusual_spending'] ? 'checked' : '' ?>>
        <span>Alert when spending is unusually high</span>
      </label>
    </div>

    <div class="field">
      <label>Multiplier threshold</label>
      <input type="number" name="unusual_spending_multiplier" value="<?= e((string)$prefs['unusual_spending_multiplier']) ?>" min="1.5" max="5.0" step="0.5">
      <div class="hint muted">Alert when an expense is this many times higher than your average for that category.</div>
    </div>
  </section>

  <section class="card" style="margin-bottom: var(--space-6);">
    <div class="card-head">
      <h2>Expense Reminders</h2>
    </div>

    <div class="grid form-grid" style="margin-bottom: var(--space-4);">
      <div class="field">
        <label>Frequency</label>
        <select name="reminder_frequency">
          <option value="daily" <?= $prefs['reminder_frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
          <option value="weekly" <?= $prefs['reminder_frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
          <option value="none" <?= $prefs['reminder_frequency'] === 'none' ? 'selected' : '' ?>>None</option>
        </select>
      </div>

      <div class="field">
        <label>Reminder time</label>
        <input type="time" name="reminder_time" value="<?= e($prefs['reminder_time']) ?>">
      </div>
    </div>
  </section>

  <section class="card" style="margin-bottom: var(--space-6);">
    <div class="card-head">
      <h2>Weekly Summary</h2>
    </div>

    <div class="field" style="margin-bottom: var(--space-4);">
      <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
        <input type="checkbox" name="weekly_summary" value="1" <?= (bool)$prefs['weekly_summary'] ? 'checked' : '' ?>>
        <span>Send me a weekly spending summary</span>
      </label>
    </div>

    <div class="field">
      <label>Summary day</label>
      <select name="weekly_summary_day">
        <option value="monday" <?= $prefs['weekly_summary_day'] === 'monday' ? 'selected' : '' ?>>Monday</option>
        <option value="sunday" <?= $prefs['weekly_summary_day'] === 'sunday' ? 'selected' : '' ?>>Sunday</option>
      </select>
      <div class="hint muted">Receive your weekly spending summary on this day.</div>
    </div>
  </section>

  <button class="btn btn-primary" type="submit">Save Settings</button>
</form>

<?php require_once __DIR__ . '/../Src/partials/footer.php'; ?>
