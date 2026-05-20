<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';
require_once __DIR__ . '/../Src/models/AIRecommendationModel.php';

require_auth();
$user = auth_user();
$title = 'AI Insights';

$insights = AIRecommendationModel::generateAll((int)$user['id']);
$geminiInsights = AIRecommendationModel::getGeminiInsights((int)$user['id']);

$allInsights = array_merge($geminiInsights, $insights);
usort($allInsights, function ($a, $b) {
  $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];
  $aVal = $severityOrder[$a['severity']] ?? 99;
  $bVal = $severityOrder[$b['severity']] ?? 99;
  return $aVal <=> $bVal;
});

$grouped = [];
foreach ($allInsights as $insight) {
  $type = (string)$insight['type'];
  if (!isset($grouped[$type])) $grouped[$type] = [];
  $grouped[$type][] = $insight;
}

$typeLabels = [
  'ai_gemini' => 'AI Analysis (Gemini)',
  'spending_spike' => 'Spending Spikes',
  'budget_alert' => 'Budget Alerts',
  'trend' => 'Spending Trends',
  'anomaly' => 'Unusual Expenses',
  'savings_tip' => 'Savings Tips',
  'wallet_advice' => 'Wallet Advice',
];

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>AI Insights</h1>
    <p class="muted">Personalized recommendations based on your spending patterns.</p>
  </div>
</div>

<?php if (!$allInsights): ?>
  <section class="card">
    <p class="muted">Not enough data yet. Add more expenses to get personalized insights and recommendations.</p>
  </section>
<?php else: ?>
  <?php foreach ($grouped as $type => $items): ?>
    <section class="card">
      <div class="card-head">
        <h2><?= e($typeLabels[$type] ?? ucfirst(str_replace('_', ' ', (string)$type))) ?></h2>
        <div class="muted small"><?= count($items) ?> insight(s)</div>
      </div>

      <div class="insights-list">
        <?php foreach ($items as $insight): ?>
          <div class="insight-item insight-<?= e($insight['severity']) ?>">
            <div class="insight-badge"><?= e($insight['badge']) ?></div>
            <div class="insight-text"><?= e($insight['message']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
