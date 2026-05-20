<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/models/ExpenseModel.php';
require_once __DIR__ . '/../Src/models/BudgetModel.php';

$user = api_user();
$today = date('Y-m-d');
$monthKey = date('Y-m');
$todayTotal = ExpenseModel::sumForDate((int)$user['id'], $today);
$monthTotal = ExpenseModel::sumForMonth((int)$user['id'], $monthKey);
$budgetRow = BudgetModel::get((int)$user['id'], $monthKey);
$budget = $budgetRow ? (float)$budgetRow['amount'] : 0.0;
$remaining = $budget > 0 ? max(0.0, $budget - $monthTotal) : 0.0;

api_success([
  'today' => $today,
  'month_key' => $monthKey,
  'today_total' => $todayTotal,
  'month_total' => $monthTotal,
  'budget' => $budget,
  'remaining' => $remaining,
]);
