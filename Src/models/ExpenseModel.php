<?php
// src/models/ExpenseModel.php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class ExpenseModel
{
  public static function create(
    int $userId,
    float $amount,
    string $date,
    ?int $categoryId,
    ?string $note,
    ?int $walletId = null,
    string $paymentMethod = 'cash'
  ): int {
    $pdo = db();
    $stmt = $pdo->prepare('
      INSERT INTO expenses (user_id, wallet_id, payment_method, category_id, amount, expense_date, note)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $walletId, $paymentMethod, $categoryId, $amount, $date, $note]);
    return (int)$pdo->lastInsertId();
  }

  public static function update(
    int $userId,
    int $expenseId,
    float $amount,
    string $date,
    ?int $categoryId,
    ?string $note,
    ?int $walletId = null,
    string $paymentMethod = 'cash'
  ): void {
    $pdo = db();
    $stmt = $pdo->prepare('
      UPDATE expenses
      SET category_id = ?, amount = ?, expense_date = ?, note = ?, wallet_id = ?, payment_method = ?
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ');
    $stmt->execute([$categoryId, $amount, $date, $note, $walletId, $paymentMethod, $expenseId, $userId]);
  }

  public static function delete(int $userId, int $expenseId): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$expenseId, $userId]);
  }

  public static function find(int $userId, int $expenseId): ?array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT e.*, c.name AS category_name
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.id = ? AND e.user_id = ?
      LIMIT 1
    ');
    $stmt->execute([$expenseId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function findWithWallet(int $userId, int $expenseId): ?array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT e.*, c.name AS category_name, w.balance AS wallet_balance_at_time
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      LEFT JOIN wallets w ON w.id = e.wallet_id
      WHERE e.id = ? AND e.user_id = ?
      LIMIT 1
    ');
    $stmt->execute([$expenseId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  // ✅ UPDATED LIST METHOD
  public static function list(int $userId, array $filters, int $limit = 250): array
  {
    $pdo = db();

    $where = ['e.user_id = :uid'];
    $params = [':uid' => $userId];

    if (!empty($filters['q'])) {
      // IMPORTANT: Do NOT reuse same named parameter twice with emulation OFF
      $where[] = '(e.note LIKE :q1 OR c.name LIKE :q2)';
      $params[':q1'] = '%' . $filters['q'] . '%';
      $params[':q2'] = '%' . $filters['q'] . '%';
    }

    if (!empty($filters['category_id'])) {
      $where[] = 'e.category_id = :cid';
      $params[':cid'] = (int)$filters['category_id'];
    }

    if (!empty($filters['from'])) {
      $where[] = 'e.expense_date >= :fromDate';
      $params[':fromDate'] = $filters['from'];
    }

    if (!empty($filters['to'])) {
      $where[] = 'e.expense_date <= :toDate';
      $params[':toDate'] = $filters['to'];
    }

    if (!empty($filters['payment_method'])) {
      $allowed = ['cash', 'card', 'wallet', 'other'];
      if (in_array($filters['payment_method'], $allowed, true)) {
        $where[] = 'e.payment_method = :pm';
        $params[':pm'] = $filters['payment_method'];
      }
    }

    if (!empty($filters['min_amount'])) {
      $where[] = 'e.amount >= :minAmt';
      $params[':minAmt'] = (float)$filters['min_amount'];
    }

    if (!empty($filters['max_amount'])) {
      $where[] = 'e.amount <= :maxAmt';
      $params[':maxAmt'] = (float)$filters['max_amount'];
    }

    $sql = '
      SELECT e.id, e.amount, e.expense_date, e.note, e.payment_method, c.name AS category_name
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY e.expense_date DESC, e.id DESC
      LIMIT ' . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  public static function sumForDate(int $userId, string $date): float
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE user_id=? AND expense_date=?');
    $stmt->execute([$userId, $date]);
    return (float)$stmt->fetchColumn();
  }

  public static function sumForMonth(int $userId, string $monthKey): float
  {
    // monthKey: YYYY-MM
    $start = $monthKey . '-01';
    $end = date('Y-m-t', strtotime($start));

    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COALESCE(SUM(amount),0) AS total
      FROM expenses
      WHERE user_id = ? AND expense_date BETWEEN ? AND ?
    ');
    $stmt->execute([$userId, $start, $end]);
    return (float)$stmt->fetchColumn();
  }

  public static function sumAll(int $userId): float
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (float)$stmt->fetchColumn();
  }

  public static function recent(int $userId, int $limit = 7): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT e.id, e.amount, e.expense_date, e.note, e.payment_method, c.name AS category_name
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ?
      ORDER BY e.expense_date DESC, e.id DESC
      LIMIT ?
    ');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }

  public static function categoryBreakdown(int $userId, string $monthKey): array
  {
    $start = $monthKey . '-01';
    $end = date('Y-m-t', strtotime($start));

    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT c.name, COALESCE(SUM(e.amount), 0) AS total
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      GROUP BY c.name
      ORDER BY total DESC
    ');
    $stmt->execute([$userId, $start, $end]);
    return $stmt->fetchAll();
  }
}
