<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/ExpenseModel.php';
require_once __DIR__ . '/BudgetModel.php';
require_once __DIR__ . '/CategoryModel.php';
require_once __DIR__ . '/WalletModel.php';
require_once __DIR__ . '/../ai_gemini.php';

final class AIRecommendationModel
{
  public static function generateAll(int $userId): array
  {
    $insights = [];

    $monthKey = date('Y-m');
    $monthStart = $monthKey . '-01';
    $monthEnd = date('Y-m-t');

    $insights = array_merge($insights, self::getSpendingSpikes($userId, $monthStart, $monthEnd));
    $insights = array_merge($insights, self::getBudgetAlerts($userId, $monthKey));
    $insights = array_merge($insights, self::getTrends($userId));
    $insights = array_merge($insights, self::getAnomalies($userId, $monthStart, $monthEnd));
    $insights = array_merge($insights, self::getSavingsTips($userId, $monthStart, $monthEnd));
    $insights = array_merge($insights, self::getWalletAdvice($userId));

    usort($insights, function ($a, $b) {
      $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];
      $aVal = $severityOrder[$a['severity']] ?? 99;
      $bVal = $severityOrder[$b['severity']] ?? 99;
      return $aVal <=> $bVal;
    });

    return $insights;
  }

  public static function getSpendingSpikes(int $userId, string $monthStart, string $monthEnd): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT c.name AS category, COALESCE(SUM(e.amount), 0) AS total
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      GROUP BY c.name
      ORDER BY total DESC
    ');
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $currentMonthByCategory = $stmt->fetchAll();

    $threeMonthsAgo = date('Y-m-d', strtotime($monthStart . ' -3 months'));
    $stmt = $pdo->prepare('
      SELECT c.name AS category, COALESCE(SUM(e.amount), 0) AS total, COUNT(DISTINCT DATE_FORMAT(e.expense_date, "%Y-%m")) AS months
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      GROUP BY c.name
    ');
    $stmt->execute([$userId, $threeMonthsAgo, date('Y-m-d', strtotime($monthStart . ' -1 day'))]);
    $historicalByCategory = [];
    foreach ($stmt->fetchAll() as $row) {
      $historicalByCategory[(string)$row['category']] = [
        'total' => (float)$row['total'],
        'months' => (int)$row['months'],
      ];
    }

    $insights = [];
    foreach ($currentMonthByCategory as $row) {
      $category = (string)($row['category'] ?? 'Uncategorized');
      $currentTotal = (float)$row['total'];
      if ($currentTotal <= 0) continue;

      $hist = $historicalByCategory[$category] ?? null;
      if (!$hist || $hist['months'] < 1) continue;

      $avgMonthly = $hist['total'] / $hist['months'];
      if ($avgMonthly <= 0) continue;

      $pctIncrease = (($currentTotal - $avgMonthly) / $avgMonthly) * 100;
      if ($pctIncrease > 30) {
        $severity = $pctIncrease > 75 ? 'critical' : ($pctIncrease > 50 ? 'warning' : 'info');
        $insights[] = [
          'type' => 'spending_spike',
          'severity' => $severity,
          'badge' => 'Spike',
          'message' => sprintf(
            '%s spending is %.0f%% above your monthly average (%s vs %s avg).',
            $category,
            $pctIncrease,
            money_fmt($currentTotal),
            money_fmt($avgMonthly)
          ),
        ];
      }
    }

    return $insights;
  }

  public static function getBudgetAlerts(int $userId, string $monthKey): array
  {
    $budgetRow = BudgetModel::get($userId, $monthKey);
    if (!$budgetRow) return [];

    $budget = (float)$budgetRow['amount'];
    if ($budget <= 0) return [];

    $monthTotal = ExpenseModel::sumForMonth($userId, $monthKey);
    $pctUsed = ($monthTotal / $budget) * 100;

    $daysInMonth = (int)date('t');
    $currentDay = (int)date('j');
    $daysLeft = $daysInMonth - $currentDay;

    $insights = [];

    if ($pctUsed >= 100) {
      $over = $monthTotal - $budget;
      $insights[] = [
        'type' => 'budget_alert',
        'severity' => 'critical',
        'badge' => 'Over budget',
        'message' => sprintf(
          'You have exceeded your monthly budget by %s. Total spent: %s, Budget: %s.',
          money_fmt($over),
          money_fmt($monthTotal),
          money_fmt($budget)
        ),
      ];
    } elseif ($pctUsed >= 80 && $daysLeft > 5) {
      $insights[] = [
        'type' => 'budget_alert',
        'severity' => 'warning',
        'badge' => 'Budget warning',
        'message' => sprintf(
          'You have used %.0f%% of your budget with %d days remaining. Slow down to stay within %s.',
          $pctUsed,
          $daysLeft,
          money_fmt($budget)
        ),
      ];
    } elseif ($pctUsed >= 60 && $daysLeft > 10) {
      $dailyRemaining = ($budget - $monthTotal) / $daysLeft;
      $insights[] = [
        'type' => 'budget_alert',
        'severity' => 'info',
        'badge' => 'Budget check',
        'message' => sprintf(
          'You have used %.0f%% of budget. You can spend about %s per day for the remaining %d days.',
          $pctUsed,
          money_fmt($dailyRemaining),
          $daysLeft
        ),
      ];
    }

    return $insights;
  }

  public static function getTrends(int $userId, int $months = 6): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT DATE_FORMAT(expense_date, "%Y-%m") AS month_key, COALESCE(SUM(amount), 0) AS total
      FROM expenses
      WHERE user_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
      GROUP BY month_key
      ORDER BY month_key ASC
    ');
    $stmt->execute([$userId, $months]);
    $monthlyTotals = $stmt->fetchAll();

    if (count($monthlyTotals) < 3) return [];

    $values = array_map(fn($r) => (float)$r['total'], $monthlyTotals);
    $n = count($values);

    $lastThree = array_slice($values, -3);
    $increasing = true;
    for ($i = 1; $i < count($lastThree); $i++) {
      if ($lastThree[$i] <= $lastThree[$i - 1]) {
        $increasing = false;
        break;
      }
    }

    $decreasing = true;
    for ($i = 1; $i < count($lastThree); $i++) {
      if ($lastThree[$i] >= $lastThree[$i - 1]) {
        $decreasing = false;
        break;
      }
    }

    $insights = [];
    $latest = end($values);
    $first = reset($values);
    $overallChange = $first > 0 ? (($latest - $first) / $first) * 100 : 0;

    if ($increasing && $overallChange > 10) {
      $insights[] = [
        'type' => 'trend',
        'severity' => 'warning',
        'badge' => 'Trending up',
        'message' => sprintf(
          'Your spending has been increasing for the last 3 months. Overall change: +%.0f%% (%s to %s).',
          $overallChange,
          money_fmt($first),
          money_fmt($latest)
        ),
      ];
    } elseif ($decreasing && $overallChange < -10) {
      $insights[] = [
        'type' => 'trend',
        'severity' => 'success',
        'badge' => 'Trending down',
        'message' => sprintf(
          'Great job! Your spending has been decreasing for the last 3 months. Overall change: %.0f%% (%s to %s).',
          $overallChange,
          money_fmt($first),
          money_fmt($latest)
        ),
      ];
    }

    return $insights;
  }

  public static function getAnomalies(int $userId, string $monthStart, string $monthEnd): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT e.*, c.name AS category_name
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      ORDER BY e.amount DESC
    ');
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $expenses = $stmt->fetchAll();

    if (count($expenses) < 5) return [];

    $byCategory = [];
    foreach ($expenses as $e) {
      $cat = (string)($e['category_name'] ?? 'Uncategorized');
      if (!isset($byCategory[$cat])) $byCategory[$cat] = [];
      $byCategory[$cat][] = (float)$e['amount'];
    }

    $insights = [];
    foreach ($byCategory as $cat => $amounts) {
      if (count($amounts) < 3) continue;

      $avg = array_sum($amounts) / count($amounts);
      if ($avg <= 0) continue;

      foreach ($amounts as $amount) {
        if ($amount > $avg * 2.5) {
          $insights[] = [
            'type' => 'anomaly',
            'severity' => 'warning',
            'badge' => 'Unusual',
            'message' => sprintf(
              'Unusual %s expense: %s is %.1fx your average of %s for this category.',
              $cat,
              money_fmt($amount),
              $amount / $avg,
              money_fmt($avg)
            ),
          ];
        }
      }
    }

    return $insights;
  }

  public static function getSavingsTips(int $userId, string $monthStart, string $monthEnd): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COALESCE(c.name, "Uncategorized") AS category, COALESCE(SUM(e.amount), 0) AS total, COUNT(*) AS count
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      GROUP BY category
      ORDER BY total DESC
    ');
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $byCategory = $stmt->fetchAll();

    if (!$byCategory) return [];

    $totalSpending = array_sum(array_map(fn($r) => (float)$r['total'], $byCategory));
    if ($totalSpending <= 0) return [];

    $insights = [];
    $topCategory = $byCategory[0];
    $topPct = ((float)$topCategory['total'] / $totalSpending) * 100;

    if ($topPct > 30) {
      $suggestedLimit = (float)$topCategory['total'] * 0.8;
      $insights[] = [
        'type' => 'savings_tip',
        'severity' => 'info',
        'badge' => 'Savings tip',
        'message' => sprintf(
          '%s is %.0f%% of your spending (%s). Consider setting a %s limit this month.',
          (string)$topCategory['category'],
          $topPct,
          money_fmt((float)$topCategory['total']),
          money_fmt($suggestedLimit)
        ),
      ];
    }

    $totalExpenses = array_sum(array_map(fn($r) => (int)$r['count'], $byCategory));
    $avgPerExpense = $totalSpending / max(1, $totalExpenses);
    $smallExpenses = array_filter($byCategory, fn($r) => (float)$r['total'] < $avgPerExpense * 0.3);
    if ($smallExpenses) {
      $smallTotal = array_sum(array_map(fn($r) => (float)$r['total'], $smallExpenses));
      if ($smallTotal > $totalSpending * 0.1) {
        $insights[] = [
          'type' => 'savings_tip',
          'severity' => 'info',
          'badge' => 'Savings tip',
          'message' => sprintf(
            'Small expenses add up to %s (%.0f%% of total). Track them closely to find savings.',
            money_fmt($smallTotal),
            ($smallTotal / $totalSpending) * 100
          ),
        ];
      }
    }

    return $insights;
  }

  public static function getWalletAdvice(int $userId): array
  {
    $wallet = WalletModel::getOrCreate($userId);
    if (!$wallet) return [];

    $balance = (float)$wallet['balance'];
    if ($balance <= 0) {
      return [[
        'type' => 'wallet_advice',
        'severity' => 'warning',
        'badge' => 'Wallet empty',
        'message' => 'Your wallet balance is zero. Consider topping up to use wallet payments.',
      ]];
    }

    $today = date('Y-m-d');
    $avgDaily = 0;
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COALESCE(AVG(daily_total), 0) AS avg_daily
      FROM (
        SELECT expense_date, SUM(amount) AS daily_total
        FROM expenses
        WHERE user_id = ? AND expense_date >= DATE_SUB(?, INTERVAL 30 DAY)
        GROUP BY expense_date
      ) AS daily
    ');
    $stmt->execute([$userId, $today]);
    $avgDaily = (float)$stmt->fetchColumn();

    $insights = [];
    if ($avgDaily > 0) {
      $daysCovered = $balance / $avgDaily;
      if ($daysCovered < 3) {
        $insights[] = [
          'type' => 'wallet_advice',
          'severity' => 'warning',
          'badge' => 'Low wallet',
          'message' => sprintf(
            'Wallet balance covers about %.0f days of average spending (%s/day). Consider a top-up.',
            $daysCovered,
            money_fmt($avgDaily)
          ),
        ];
      } elseif ($daysCovered > 30) {
        $insights[] = [
          'type' => 'wallet_advice',
          'severity' => 'success',
          'badge' => 'Healthy wallet',
          'message' => sprintf(
            'Your wallet balance covers %.0f days of average spending. Well funded!',
            $daysCovered
          ),
        ];
      }
    }

    return $insights;
  }

  public static function getGeminiInsights(int $userId): array
  {
    if (!ai_is_enabled()) return [];

    $monthKey = date('Y-m');
    $monthStart = $monthKey . '-01';
    $monthEnd = date('Y-m-t');

    $pdo = db();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?');
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $monthTotal = (float)$stmt->fetchColumn();

    $budgetRow = BudgetModel::get($userId, $monthKey);
    $budget = $budgetRow ? (float)$budgetRow['amount'] : 0.0;

    $stmt = $pdo->prepare('
      SELECT COALESCE(c.name, "Uncategorized") AS category, COALESCE(SUM(e.amount), 0) AS total
      FROM expenses e LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      GROUP BY category ORDER BY total DESC LIMIT 5
    ');
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $categories = $stmt->fetchAll();

    $stmt = $pdo->prepare('
      SELECT e.amount, e.expense_date, e.note, c.name AS category_name
      FROM expenses e LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? ORDER BY e.expense_date DESC LIMIT 15
    ');
    $stmt->execute([$userId]);
    $recent = $stmt->fetchAll();

    $wallet = WalletModel::get($userId);
    $walletBalance = $wallet ? (float)$wallet['balance'] : 0.0;

    $stmt = $pdo->prepare('
      SELECT DATE_FORMAT(expense_date, "%Y-%m") AS month_key, COALESCE(SUM(amount), 0) AS total
      FROM expenses WHERE user_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY month_key ORDER BY month_key ASC
    ');
    $stmt->execute([$userId]);
    $trend = $stmt->fetchAll();

    $spendingData = [
      'month_total' => $monthTotal,
      'budget' => $budget,
      'categories' => $categories,
      'recent_expenses' => $recent,
      'wallet_balance' => $walletBalance,
      'monthly_trend' => $trend,
    ];

    $prompt = ai_build_spending_prompt($spendingData);
    $result = ai_generate_insight($prompt);

    if (!$result['ok']) return [];

    $lines = array_filter(array_map('trim', explode("\n", (string)$result['text'])));

    $insights = [];
    foreach ($lines as $line) {
      if ($line === '') continue;

      $severity = 'info';
      $badge = 'AI Insight';
      $lower = strtolower($line);
      if (stripos($lower, 'warning') !== false || stripos($lower, 'alert') !== false || stripos($lower, 'over budget') !== false || stripos($lower, 'exceed') !== false) {
        $severity = 'warning';
        $badge = 'AI Alert';
      } elseif (stripos($lower, 'great') !== false || stripos($lower, 'good') !== false || stripos($lower, 'well') !== false || stripos($lower, 'saving') !== false) {
        $severity = 'success';
        $badge = 'AI Tip';
      } elseif (stripos($lower, 'unusual') !== false || stripos($lower, 'spike') !== false || stripos($lower, 'high') !== false) {
        $severity = 'warning';
        $badge = 'AI Alert';
      }

      $insights[] = [
        'type' => 'ai_gemini',
        'severity' => $severity,
        'badge' => $badge,
        'message' => $line,
      ];
    }

    return $insights;
  }
}
