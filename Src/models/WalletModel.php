<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class WalletModel
{
  public static function getOrCreate(int $userId): array
  {
    $wallet = self::get($userId);
    if ($wallet) return $wallet;

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO wallets (user_id) VALUES (?)');
    $stmt->execute([$userId]);

    return self::get($userId);
  }

  public static function get(int $userId): ?array
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function getBalance(int $userId): float
  {
    $wallet = self::get($userId);
    return $wallet ? (float)$wallet['balance'] : 0.0;
  }

  public static function topup(int $userId, float $amount, ?string $note = null): array
  {
    if ($amount <= 0) {
      return ['ok' => false, 'message' => 'Top-up amount must be positive.'];
    }

    $wallet = self::getOrCreate($userId);
    $newBalance = (float)$wallet['balance'] + $amount;

    require_once __DIR__ . '/WalletTransactionModel.php';

    $pdo = db();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?');
      $stmt->execute([$newBalance, (int)$wallet['id']]);

      WalletTransactionModel::record($userId, 'topup', $amount, $newBalance, 'manual', null, $note);

      $pdo->commit();
      return ['ok' => true, 'message' => 'Top-up successful.', 'balance' => $newBalance];
    } catch (Throwable $e) {
      $pdo->rollBack();
      return ['ok' => false, 'message' => 'Top-up failed. Please try again.'];
    }
  }

  public static function withdraw(int $userId, float $amount, ?string $note = null): array
  {
    if ($amount <= 0) {
      return ['ok' => false, 'message' => 'Withdrawal amount must be positive.'];
    }

    $wallet = self::getOrCreate($userId);
    if ((float)$wallet['balance'] < $amount) {
      return ['ok' => false, 'message' => 'Insufficient wallet balance.'];
    }

    $newBalance = (float)$wallet['balance'] - $amount;

    require_once __DIR__ . '/WalletTransactionModel.php';

    $pdo = db();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?');
      $stmt->execute([$newBalance, (int)$wallet['id']]);

      WalletTransactionModel::record($userId, 'withdrawal', $amount, $newBalance, 'manual', null, $note);

      $pdo->commit();
      return ['ok' => true, 'message' => 'Withdrawal successful.', 'balance' => $newBalance];
    } catch (Throwable $e) {
      $pdo->rollBack();
      return ['ok' => false, 'message' => 'Withdrawal failed. Please try again.'];
    }
  }

  public static function payFromWallet(int $userId, float $amount, int $expenseId, ?string $note = null): array
  {
    if ($amount <= 0) {
      return ['ok' => false, 'message' => 'Payment amount must be positive.'];
    }

    $wallet = self::getOrCreate($userId);
    if ((float)$wallet['balance'] < $amount) {
      return ['ok' => false, 'message' => 'Insufficient wallet balance.'];
    }

    $newBalance = (float)$wallet['balance'] - $amount;

    require_once __DIR__ . '/WalletTransactionModel.php';

    $pdo = db();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?');
      $stmt->execute([$newBalance, (int)$wallet['id']]);

      WalletTransactionModel::record($userId, 'expense', $amount, $newBalance, 'expense', $expenseId, $note);

      $pdo->commit();
      return ['ok' => true, 'message' => 'Payment from wallet successful.', 'balance' => $newBalance];
    } catch (Throwable $e) {
      $pdo->rollBack();
      return ['ok' => false, 'message' => 'Wallet payment failed.'];
    }
  }

  public static function refundToWallet(int $userId, float $amount, int $expenseId, ?string $note = null): array
  {
    if ($amount <= 0) {
      return ['ok' => false, 'message' => 'Refund amount must be positive.'];
    }

    $wallet = self::getOrCreate($userId);
    $newBalance = (float)$wallet['balance'] + $amount;

    require_once __DIR__ . '/WalletTransactionModel.php';

    $pdo = db();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?');
      $stmt->execute([$newBalance, (int)$wallet['id']]);

      WalletTransactionModel::record($userId, 'refund', $amount, $newBalance, 'expense', $expenseId, $note);

      $pdo->commit();
      return ['ok' => true, 'message' => 'Refund to wallet successful.', 'balance' => $newBalance];
    } catch (Throwable $e) {
      $pdo->rollBack();
      return ['ok' => false, 'message' => 'Wallet refund failed.'];
    }
  }

  public static function updatePaymentMethod(int $userId, int $expenseId, string $paymentMethod, ?int $walletId = null): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      UPDATE expenses SET payment_method = ?, wallet_id = ?
      WHERE id = ? AND user_id = ? LIMIT 1
    ');
    $stmt->execute([$paymentMethod, $walletId, $expenseId, $userId]);
  }

  public static function getWalletForExpense(int $userId, int $expenseId): ?array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT w.* FROM expenses e
      JOIN wallets w ON w.id = e.wallet_id
      WHERE e.id = ? AND e.user_id = ? LIMIT 1
    ');
    $stmt->execute([$expenseId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function getTotalTopup(int $userId, string $from, string $to): float
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COALESCE(SUM(amount), 0) AS total
      FROM wallet_transactions
      WHERE user_id = ? AND type = "topup" AND created_at BETWEEN ? AND ?
    ');
    $stmt->execute([$userId, $from, $to]);
    return (float)$stmt->fetchColumn();
  }

  public static function getTotalSpent(int $userId, string $from, string $to): float
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COALESCE(SUM(amount), 0) AS total
      FROM wallet_transactions
      WHERE user_id = ? AND type = "expense" AND created_at BETWEEN ? AND ?
    ');
    $stmt->execute([$userId, $from, $to]);
    return (float)$stmt->fetchColumn();
  }
}
