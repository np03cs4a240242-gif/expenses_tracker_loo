<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class UserPreferencesModel
{
  public static function getOrCreate(int $userId): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM user_notification_prefs WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
      $stmt = $pdo->prepare('
        INSERT INTO user_notification_prefs (user_id)
        VALUES (?)
      ');
      $stmt->execute([$userId]);
      return self::getOrCreate($userId);
    }

    return $row;
  }

  public static function update(int $userId, array $prefs): void
  {
    $pdo = db();
    $allowed = [
      'budget_exceeded',
      'budget_warning_threshold',
      'unusual_spending',
      'unusual_spending_multiplier',
      'expense_reminder',
      'reminder_frequency',
      'reminder_time',
      'weekly_summary',
      'weekly_summary_day',
    ];

    $updates = [];
    $values = [];

    foreach ($allowed as $key) {
      if (array_key_exists($key, $prefs)) {
        $updates[] = "$key = ?";
        $values[] = $prefs[$key];
      }
    }

    if (empty($updates)) {
      return;
    }

    $values[] = $userId;
    $sql = 'UPDATE user_notification_prefs SET ' . implode(', ', $updates) . ' WHERE user_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
  }
}
