<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';
require_once __DIR__ . '/../Src/models/WalletTransactionModel.php';
require_once __DIR__ . '/../Src/models/WalletModel.php';

require_auth();
$user = auth_user();
$title = 'Wallet Transactions';

$wallet = WalletModel::getOrCreate((int)$user['id']);
$currency = (string)$wallet['currency'];

$filters = [
  'type' => str_trim($_GET['type'] ?? ''),
  'from' => str_trim($_GET['from'] ?? ''),
  'to' => str_trim($_GET['to'] ?? ''),
];

$allowedTypes = ['topup', 'withdrawal', 'expense', 'refund'];
if (!in_array($filters['type'], $allowedTypes, true)) {
  $filters['type'] = '';
}

if ($filters['from'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) $filters['from'] = '';
if ($filters['to'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) $filters['to'] = '';

$rows = WalletTransactionModel::list((int)$user['id'], $filters, 250);
$totalCount = WalletTransactionModel::count((int)$user['id'], $filters);

require_once __DIR__ . '/../Src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Wallet Transactions</h1>
    <p class="muted">Full history of all wallet movements.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(base_url('/wallet.php')) ?>">← Back to Wallet</a>
  </div>
</div>

<section class="card">
  <div class="card-head">
    <h2>Filters</h2>
  </div>

  <form method="get" class="form">
    <div class="grid form-grid">
      <div class="field">
        <label>Type</label>
        <select name="type">
          <option value="">All</option>
          <?php foreach ($allowedTypes as $t): ?>
            <option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>>
              <?= e(ucfirst($t)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>From</label>
        <input type="date" name="from" value="<?= e($filters['from']) ?>">
      </div>

      <div class="field">
        <label>To</label>
        <input type="date" name="to" value="<?= e($filters['to']) ?>">
      </div>
    </div>

    <div class="row-actions">
      <button class="btn btn-primary" type="submit">Apply</button>
      <a class="btn btn-ghost" href="<?= e(base_url('/wallet_history.php')) ?>">Reset</a>
    </div>
  </form>
</section>

<section class="card">
  <div class="card-head">
    <h2>Transaction history</h2>
    <div class="muted small"><?= $totalCount ?> result(s)</div>
  </div>

  <?php if (!$rows): ?>
    <p class="muted">No transactions found.</p>
  <?php else: ?>
    <div class="table">
      <div class="table-row head">
        <div>Date</div>
        <div>Type</div>
        <div>Note</div>
        <div class="right">Amount</div>
        <div class="right">Balance after</div>
      </div>

      <?php foreach ($rows as $tx): ?>
        <div class="table-row">
          <div><?= e(date('Y-m-d H:i', strtotime((string)$tx['created_at']))) ?></div>
          <div>
            <span class="badge badge-<?= e($tx['type'] === 'topup' ? 'success' : ($tx['type'] === 'refund' ? 'info' : 'danger')) ?>">
              <?= e(ucfirst((string)$tx['type'])) ?>
            </span>
          </div>
          <div class="truncate"><?= e($tx['note'] ?? '') ?></div>
          <div class="right" style="color: <?= $tx['type'] === 'topup' || $tx['type'] === 'refund' ? 'var(--success)' : 'var(--danger)' ?>">
            <?= $tx['type'] === 'topup' || $tx['type'] === 'refund' ? '+' : '-' ?><?= e($currency) ?> <?= e(money_fmt((float)$tx['amount'])) ?>
          </div>
          <div class="right strong"><?= e($currency) ?> <?= e(money_fmt((float)$tx['balance_after'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../Src/partials/footer.php'; ?>
