<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap.php';
require_once __DIR__ . '/../src/models/ExpenseModel.php';
require_once __DIR__ . '/../src/models/BudgetModel.php';
require_once __DIR__ . '/../src/models/WalletModel.php';
require_once __DIR__ . '/../src/models/AIRecommendationModel.php';

require_auth();
$user = auth_user();

$title = 'Dashboard';

$today = date('Y-m-d');
$monthKey = date('Y-m');
$todayTotal = ExpenseModel::sumForDate((int)$user['id'], $today);
$monthTotal = ExpenseModel::sumForMonth((int)$user['id'], $monthKey);

$budgetRow = BudgetModel::get((int)$user['id'], $monthKey);
$budget = $budgetRow ? (float)$budgetRow['amount'] : 0.0;
$remaining = $budget > 0 ? max(0.0, $budget - $monthTotal) : 0.0;

$wallet = WalletModel::getOrCreate((int)$user['id']);
$walletBalance = (float)$wallet['balance'];

$recent = ExpenseModel::recent((int)$user['id'], 8);

$insights = AIRecommendationModel::generateAll((int)$user['id']);
$geminiInsights = AIRecommendationModel::getGeminiInsights((int)$user['id']);

$allInsights = array_merge($geminiInsights, $insights);
usort($allInsights, function ($a, $b) {
  $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];
  $aVal = $severityOrder[$a['severity']] ?? 99;
  $bVal = $severityOrder[$b['severity']] ?? 99;
  return $aVal <=> $bVal;
});

$topInsights = array_slice($allInsights, 0, 4);

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted">Here is a quick look at your spending for <?= e(date('F Y')) ?>.</p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(base_url('/expenses.php')) ?>#add">Add expense</a>
  </div>
</div>

<div class="grid stats-grid">
  <div class="card stat">
    <div class="stat-label">Spent today</div>
    <div class="stat-value">Rs. <?= e(money_fmt($todayTotal)) ?></div>
    <div class="stat-sub muted">Date: <?= e($today) ?></div>
  </div>

  <div class="card stat">
    <div class="stat-label">Spent this month</div>
    <div class="stat-value">Rs. <?= e(money_fmt($monthTotal)) ?></div>
    <div class="stat-sub muted"><?= e($monthKey) ?></div>
  </div>

  <div class="card stat">
    <div class="stat-label">Monthly budget</div>
    <div class="stat-value"><?= $budget > 0 ? 'Rs. ' . e(money_fmt($budget)) : '<span class="muted">Not set yet</span>' ?></div>
    <div class="stat-sub muted">
      <a href="<?= e(base_url('/budgets.php')) ?>">Set budget</a>
    </div>
  </div>

  <div class="card stat">
    <div class="stat-label">Budget left</div>
    <div class="stat-value"><?= $budget > 0 ? 'Rs. ' . e(money_fmt($remaining)) : '<span class="muted">Add a budget first</span>' ?></div>
    <div class="stat-sub muted">Your budget minus this month&apos;s spending</div>
  </div>

  <div class="card stat">
    <div class="stat-label">Wallet balance</div>
    <div class="stat-value">Rs. <?= e(money_fmt($walletBalance)) ?></div>
    <div class="stat-sub muted">
      <a href="<?= e(base_url('/wallet.php')) ?>">Manage wallet</a>
    </div>
  </div>
</div>

<div class="grid two-col">
  <section class="card">
    <div class="card-head">
      <h2>Recent expenses</h2>
      <a class="btn btn-ghost" href="<?= e(base_url('/expenses.php')) ?>">View all</a>
    </div>

    <?php if (!$recent): ?>
      <p class="muted">You have not added any expenses yet. Start with the first one.</p>
    <?php else: ?>
      <div class="table">
        <div class="row head">
          <div>Date</div>
          <div>Category</div>
          <div>Note</div>
          <div class="right">Amount</div>
        </div>
        <?php foreach ($recent as $r): ?>
          <div class="row">
            <div><?= e($r['expense_date']) ?></div>
            <div><?= e($r['category_name'] ?? 'Uncategorized') ?></div>
            <div class="truncate"><?= e($r['note'] ?? '') ?></div>
            <div class="right strong">Rs. <?= e(money_fmt((float)$r['amount'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>AI Insights</h2>
      <a class="btn btn-ghost" href="<?= e(base_url('/recommendations.php')) ?>">View all</a>
    </div>

    <?php if (!$topInsights): ?>
      <p class="muted">Not enough data yet. Add more expenses to get personalized insights.</p>
    <?php else: ?>
      <div class="insights-list">
        <?php foreach ($topInsights as $insight): ?>
          <div class="insight-item insight-<?= e($insight['severity']) ?>">
            <div class="insight-badge"><?= e($insight['badge']) ?></div>
            <div class="insight-text"><?= e($insight['message']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
