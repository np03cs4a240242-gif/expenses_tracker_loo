<?php
declare(strict_types=1);

require_once __DIR__ . '/models/NotificationModel.php';
require_once __DIR__ . '/models/UserPreferencesModel.php';
require_once __DIR__ . '/models/ExpenseModel.php';
require_once __DIR__ . '/models/BudgetModel.php';

final class NotificationService
{
  public static function checkBudgetExceeded(int $userId, string $monthKey): void
  {
    $prefs = UserPreferencesModel::getOrCreate($userId);

    if (!(bool)$prefs['budget_exceeded']) {
      return;
    }

    $budgetRow = BudgetModel::get($userId, $monthKey);
    if (!$budgetRow) {
      return;
    }

    $budget = (float)$budgetRow['amount'];
    $spent = ExpenseModel::sumForMonth($userId, $monthKey);
    $threshold = (float)$prefs['budget_warning_threshold'] / 100;

    if ($spent >= $budget) {
      NotificationModel::create(
        $userId,
        'budget_exceeded',
        'Budget Exceeded!',
        sprintf('You have spent Rs. %s against your budget of Rs. %s for %s.', money_fmt($spent), money_fmt($budget), $monthKey),
        'critical',
        base_url('/budgets.php')
      );
    } elseif ($spent >= $budget * $threshold) {
      $pct = round(($spent / $budget) * 100);
      NotificationModel::create(
        $userId,
        'budget_warning',
        'Budget Warning',
        sprintf('You have used %d%% of your monthly budget (Rs. %s of Rs. %s).', $pct, money_fmt($spent), money_fmt($budget)),
        'warning',
        base_url('/budgets.php')
      );
    }
  }

  public static function checkUnusualSpending(int $userId, int $expenseId): void
  {
    $prefs = UserPreferencesModel::getOrCreate($userId);

    if (!(bool)$prefs['unusual_spending']) {
      return;
    }

    $expense = ExpenseModel::find($userId, $expenseId);
    if (!$expense || !$expense['category_id']) {
      return;
    }

    $pdo = db();
    $monthKey = date('Y-m', strtotime((string)$expense['expense_date']));
    $start = $monthKey . '-01';
    $end = date('Y-m-t', strtotime($start));

    $stmt = $pdo->prepare('
      SELECT AVG(amount) AS avg_amount, COUNT(*) AS tx_count
      FROM expenses
      WHERE user_id = ? AND category_id = ? AND expense_date BETWEEN ? AND ? AND id != ?
    ');
    $stmt->execute([$userId, $expense['category_id'], $start, $end, $expenseId]);
    $stats = $stmt->fetch();

    if (!$stats || (int)$stats['tx_count'] < 2) {
      return;
    }

    $avgAmount = (float)$stats['avg_amount'];
    $multiplier = (float)$prefs['unusual_spending_multiplier'];
    $amount = (float)$expense['amount'];

    if ($amount >= $avgAmount * $multiplier) {
      $ratio = round($amount / $avgAmount, 1);
      NotificationModel::create(
        $userId,
        'unusual_spending',
        'Unusual Spending Detected',
        sprintf('Your expense of Rs. %s in "%s" is %sx higher than your average (Rs. %s).', money_fmt($amount), $expense['category_name'], $ratio, money_fmt($avgAmount)),
        'warning',
        base_url('/expenses.php')
      );
    }
  }

  public static function generateWeeklySummary(int $userId): void
  {
    $prefs = UserPreferencesModel::getOrCreate($userId);

    if (!(bool)$prefs['weekly_summary']) {
      return;
    }

    $today = new DateTime();
    $dayOfWeek = (int)$today->format('N');
    $summaryDay = $prefs['weekly_summary_day'] === 'sunday' ? 7 : 1;

    if ($dayOfWeek !== $summaryDay) {
      return;
    }

    $weekEnd = clone $today;
    $weekStart = clone $today;
    $weekStart->modify('-6 days');

    $startDate = $weekStart->format('Y-m-d');
    $endDate = $weekEnd->format('Y-m-d');

    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COUNT(*) AS tx_count, COALESCE(SUM(amount), 0) AS total
      FROM expenses
      WHERE user_id = ? AND expense_date BETWEEN ? AND ?
    ');
    $stmt->execute([$userId, $startDate, $endDate]);
    $summary = $stmt->fetch();

    $stmt = $pdo->prepare('
      SELECT c.name, COALESCE(SUM(e.amount), 0) AS total
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      GROUP BY c.name
      ORDER BY total DESC
      LIMIT 5
    ');
    $stmt->execute([$userId, $startDate, $endDate]);
    $topCategories = $stmt->fetchAll();

    $message = sprintf(
      "Weekly Summary (%s to %s):\nTotal: Rs. %s across %d transactions.",
      $startDate,
      $endDate,
      money_fmt((float)$summary['total']),
      (int)$summary['tx_count']
    );

    if ($topCategories) {
      $message .= "\nTop categories: ";
      $cats = [];
      foreach ($topCategories as $cat) {
        $cats[] = sprintf('%s (Rs. %s)', $cat['name'], money_fmt((float)$cat['total']));
      }
      $message .= implode(', ', $cats);
    }

    NotificationModel::create(
      $userId,
      'weekly_summary',
      'Weekly Spending Summary',
      $message,
      'info',
      base_url('/reports.php')
    );
  }

  public static function checkExpenseReminder(int $userId): void
  {
    $prefs = UserPreferencesModel::getOrCreate($userId);

    if (!(bool)$prefs['expense_reminder'] || $prefs['reminder_frequency'] === 'none') {
      return;
    }

    $today = date('Y-m-d');

    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT id FROM expense_reminder_log
      WHERE user_id = ? AND reminder_date = ?
      LIMIT 1
    ');
    $stmt->execute([$userId, $today]);
    if ($stmt->fetch()) {
      return;
    }

    $stmt = $pdo->prepare('
      SELECT COUNT(*) FROM expenses
      WHERE user_id = ? AND expense_date = ?
    ');
    $stmt->execute([$userId, $today]);
    $todayExpenses = (int)$stmt->fetchColumn();

    if ($todayExpenses > 0) {
      return;
    }

    $pdo->prepare('
      INSERT IGNORE INTO expense_reminder_log (user_id, reminder_date)
      VALUES (?, ?)
    ')->execute([$userId, $today]);

    NotificationModel::create(
      $userId,
      'reminder',
      'Log Your Expenses',
      'You haven\'t logged any expenses today. Take a moment to track your spending!',
      'info',
      base_url('/expenses.php')
    );
  }
}
