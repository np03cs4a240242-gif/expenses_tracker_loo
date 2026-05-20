<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap.php';
require_once __DIR__ . '/../Src/models/ExpenseModel.php';
require_once __DIR__ . '/../Src/models/BudgetModel.php';
require_once __DIR__ . '/../Src/models/WalletModel.php';
require_once __DIR__ . '/../Src/models/AIRecommendationModel.php';
require_once __DIR__ . '/../Src/notification_service.php';

require_auth();
$user = auth_user();

$title = 'Dashboard';

NotificationService::checkExpenseReminder((int)$user['id']);
NotificationService::generateWeeklySummary((int)$user['id']);

$today = date('Y-m-d');
$monthKey = date('Y-m');
$todayTotal = ExpenseModel::sumForDate((int)$user['id'], $today);
$monthTotal = ExpenseModel::sumForMonth((int)$user['id'], $monthKey);
$allTimeTotal = ExpenseModel::sumAll((int)$user['id']);

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

// Category breakdown for chart
$categoryBreakdown = ExpenseModel::categoryBreakdown((int)$user['id'], $monthKey);

require_once __DIR__ . '/../Src/partials/header.php';
?>

<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="page-head-subtitle">Overview of your spending habits and financial activity</p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="<?= e(base_url('/expenses.php')) ?>#add">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add expense
    </a>
  </div>
</div>

<div class="grid stats-grid">
  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    <div class="stat-label">Total Expenses</div>
    <div class="stat-value tabular">Rs. <?= e(money_fmt($allTimeTotal)) ?></div>
    <div class="stat-sub">All time spending</div>
  </div>

  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <div class="stat-label">This Month</div>
    <div class="stat-value tabular">Rs. <?= e(money_fmt($monthTotal)) ?></div>
    <div class="stat-sub"><?= e($monthKey) ?></div>
  </div>

  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
    <div class="stat-label">Average Expense</div>
    <div class="stat-value tabular">Rs. <?= e(money_fmt($monthTotal > 0 ? $monthTotal / max(1, count($recent)) : 0)) ?></div>
    <div class="stat-sub">Per transaction</div>
  </div>

  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    <div class="stat-label">Total Transactions</div>
    <div class="stat-value tabular"><?= e((string)count($recent)) ?></div>
    <div class="stat-sub">Tracked expenses</div>
  </div>
</div>

<div class="grid two-col" style="margin-top: var(--space-6);">
  <section class="card">
    <div class="card-head">
      <h2>Spending by Category</h2>
    </div>
    <?php if (!$categoryBreakdown): ?>
      <p class="muted" style="text-align:center; padding: var(--space-6) 0;">No spending data yet this month.</p>
    <?php else: ?>
      <div class="chart-container" style="position:relative; height:280px; display:flex; align-items:center; justify-content:center;">
        <canvas id="categoryPieChart"></canvas>
      </div>
      <script>
        (function() {
          var ctx = document.getElementById('categoryPieChart');
          if (!ctx) return;
          var data = <?= json_encode($categoryBreakdown) ?>;
          var chartColors = {
            'Food & Dining': '#EF4444', 'Food': '#EF4444', 'food': '#EF4444',
            'Shopping': '#EC4899', 'shopping': '#EC4899',
            'Transportation': '#3B82F6', 'Transport': '#3B82F6', 'petrol': '#3B82F6', 'fuel': '#3B82F6',
            'Entertainment': '#8B5CF6', 'Entertain': '#8B5CF6',
            'Bills & Utilities': '#10B981', 'Bills': '#10B981', 'Utilities': '#10B981',
            'Healthcare': '#F59E0B', 'Health': '#F59E0B',
            'Education': '#14B8A6', 'School': '#14B8A6',
          };
          var fallbackColors = ['#EF4444','#EC4899','#3B82F6','#8B5CF6','#10B981','#F59E0B','#14B8A6','#F97316','#6366F1','#84CC16'];
          var labels = data.map(function(d) { return d.name || 'Uncategorized'; });
          var values = data.map(function(d) { return parseFloat(d.total); });
          var colors = labels.map(function(l, i) {
            if (chartColors[l]) return chartColors[l];
            var lower = l.toLowerCase();
            for (var key in chartColors) {
              if (lower.includes(key.toLowerCase()) || key.toLowerCase().includes(lower)) return chartColors[key];
            }
            return fallbackColors[i % fallbackColors.length];
          });
          new Chart(ctx, {
            type: 'doughnut',
            data: {
              labels: labels,
              datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 8,
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              cutout: '60%',
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: {
                    padding: 16,
                    usePointStyle: true,
                    pointStyleWidth: 10,
                    font: { size: 12, family: '-apple-system, BlinkMacSystemFont, Inter, sans-serif' },
                    color: '#6B7280',
                  }
                },
                tooltip: {
                  backgroundColor: '#111827',
                  titleFont: { size: 13 },
                  bodyFont: { size: 12 },
                  padding: 10,
                  cornerRadius: 8,
                  callbacks: {
                    label: function(ctx) {
                      var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                      var pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                      return ' Rs. ' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
                    }
                  }
                }
              }
            }
          });
        })();
      </script>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Spending Trend (Last 7 Days)</h2>
    </div>
    <div class="chart-container" style="display:flex; align-items:center; justify-content:center; min-height:260px;">
      <p class="muted">Chart coming soon — daily spending visualization.</p>
    </div>
  </section>
</div>

<div class="grid two-col" style="margin-top: var(--space-6);">
  <section class="card">
    <div class="card-head">
      <h2>Recent Expenses</h2>
      <a class="btn btn-ghost" href="<?= e(base_url('/expenses.php')) ?>">View all</a>
    </div>

    <?php if (!$recent): ?>
      <p class="muted" style="text-align:center; padding: var(--space-6) 0;">You have not added any expenses yet. Start with the first one.</p>
    <?php else: ?>
      <div class="table">
        <div class="table-row head">
          <div>Date</div>
          <div>Category</div>
          <div>Note</div>
          <div class="right">Amount</div>
        </div>
        <?php foreach ($recent as $r): ?>
          <div class="table-row">
            <div><?= e($r['expense_date']) ?></div>
            <div><?= e($r['category_name'] ?? 'Uncategorized') ?></div>
            <div class="truncate"><?= e($r['note'] ?? '—') ?></div>
            <div class="right strong tabular">Rs. <?= e(money_fmt((float)$r['amount'])) ?></div>
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
      <p class="muted" style="text-align:center; padding: var(--space-6) 0;">Not enough data yet. Add more expenses to get personalized insights.</p>
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

<?php require_once __DIR__ . '/../Src/partials/footer.php'; ?>
