<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class WalletTransactionModel
{
  public static function record(
    int $userId,
    string $type,
    float $amount,
    float $balanceAfter,
    ?string $referenceType = null,
    ?int $referenceId = null,
    ?string $note = null
  ): void {
    $pdo = db();
    $stmt = $pdo->prepare('
      INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_type, reference_id, note)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $type, $amount, $balanceAfter, $referenceType, $referenceId, $note]);
  }

  public static function list(int $userId, array $filters = [], int $limit = 50): array
  {
    $pdo = db();

    $where = ['user_id = :uid'];
    $params = [':uid' => $userId];

    if (!empty($filters['type'])) {
      $allowed = ['topup', 'withdrawal', 'expense', 'refund'];
      if (in_array($filters['type'], $allowed, true)) {
        $where[] = 'type = :type';
        $params[':type'] = $filters['type'];
      }
    }

    if (!empty($filters['from'])) {
      $where[] = 'created_at >= :fromDate';
      $params[':fromDate'] = $filters['from'] . ' 00:00:00';
    }

    if (!empty($filters['to'])) {
      $where[] = 'created_at <= :toDate';
      $params[':toDate'] = $filters['to'] . ' 23:59:59';
    }

    $sql = '
      SELECT id, type, amount, balance_after, reference_type, reference_id, note, created_at
      FROM wallet_transactions
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY created_at DESC, id DESC
      LIMIT ' . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  public static function recent(int $userId, int $limit = 10): array
  {
    return self::list($userId, [], $limit);
  }

  public static function count(int $userId, array $filters = []): int
  {
    $pdo = db();

    $where = ['user_id = :uid'];
    $params = [':uid' => $userId];

    if (!empty($filters['type'])) {
      $allowed = ['topup', 'withdrawal', 'expense', 'refund'];
      if (in_array($filters['type'], $allowed, true)) {
        $where[] = 'type = :type';
        $params[':type'] = $filters['type'];
      }
    }

    if (!empty($filters['from'])) {
      $where[] = 'created_at >= :fromDate';
      $params[':fromDate'] = $filters['from'] . ' 00:00:00';
    }

    if (!empty($filters['to'])) {
      $where[] = 'created_at <= :toDate';
      $params[':toDate'] = $filters['to'] . ' 23:59:59';
    }

    $sql = 'SELECT COUNT(*) FROM wallet_transactions WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
  }
}
