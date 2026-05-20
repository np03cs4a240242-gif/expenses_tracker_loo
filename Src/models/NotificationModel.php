<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class NotificationModel
{
  public static function create(
    int $userId,
    string $type,
    string $title,
    string $message,
    string $severity = 'info',
    ?string $actionUrl = null
  ): int {
    $pdo = db();
    $stmt = $pdo->prepare('
      INSERT INTO notifications (user_id, type, title, message, severity, action_url)
      VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $type, $title, $message, $severity, $actionUrl]);
    return (int)$pdo->lastInsertId();
  }

  public static function listForUser(int $userId, int $limit = 50): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT * FROM notifications
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT ?
    ');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }

  public static function unreadCount(int $userId): int
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
  }

  public static function markAsRead(int $userId, int $notificationId): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      UPDATE notifications SET is_read = 1
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ');
    $stmt->execute([$notificationId, $userId]);
  }

  public static function markAllAsRead(int $userId): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      UPDATE notifications SET is_read = 1
      WHERE user_id = ? AND is_read = 0
    ');
    $stmt->execute([$userId]);
  }

  public static function delete(int $userId, int $notificationId): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      DELETE FROM notifications WHERE id = ? AND user_id = ? LIMIT 1
    ');
    $stmt->execute([$notificationId, $userId]);
  }

  public static function getRecentUnread(int $userId, int $limit = 5): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT * FROM notifications
      WHERE user_id = ? AND is_read = 0
      ORDER BY created_at DESC
      LIMIT ?
    ');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }
}
