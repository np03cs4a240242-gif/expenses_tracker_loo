<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';
require_once __DIR__ . '/../Src/models/WalletModel.php';
require_once __DIR__ . '/../Src/models/WalletTransactionModel.php';

require_auth();
$user = auth_user();
$title = 'Wallet';

$wallet = WalletModel::getOrCreate((int)$user['id']);
$balance = (float)$wallet['balance'];
$currency = (string)$wallet['currency'];

$topupErrors = ['amount' => '', 'general' => ''];
$withdrawErrors = ['amount' => '', 'general' => ''];

if (is_post() && ($_POST['action'] ?? '') === 'topup') {
  csrf_verify();

  $amountRaw = str_trim($_POST['amount'] ?? '');
  $note = str_trim($_POST['note'] ?? '');
  $note = $note === '' ? null : mb_substr($note, 0, 255);

  if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
    $topupErrors['amount'] = 'Amount must be a positive number.';
  }

  if (!$topupErrors['amount']) {
    $result = WalletModel::topup((int)$user['id'], (float)$amountRaw, $note);
    if ($result['ok']) {
      flash_set('success', $result['message']);
      redirect(base_url('/wallet.php'));
    } else {
      $topupErrors['general'] = $result['message'];
    }
  }
}

if (is_post() && ($_POST['action'] ?? '') === 'withdraw') {
  csrf_verify();

  $amountRaw = str_trim($_POST['amount'] ?? '');
  $note = str_trim($_POST['note'] ?? '');
  $note = $note === '' ? null : mb_substr($note, 0, 255);

  if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
    $withdrawErrors['amount'] = 'Amount must be a positive number.';
  }

  if (!$withdrawErrors['amount']) {
    $result = WalletModel::withdraw((int)$user['id'], (float)$amountRaw, $note);
    if ($result['ok']) {
      flash_set('success', $result['message']);
      redirect(base_url('/wallet.php'));
    } else {
      $withdrawErrors['general'] = $result['message'];
    }
  }
}

$recentTx = WalletTransactionModel::recent((int)$user['id'], 10);

$monthKey = date('Y-m');
$monthStart = $monthKey . '-01';
$monthEnd = date('Y-m-t');
$monthTopup = WalletModel::getTotalTopup((int)$user['id'], $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59');
$monthSpent = WalletModel::getTotalSpent((int)$user['id'], $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59');

require_once __DIR__ . '/../Src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Wallet</h1>
    <p class="page-head-subtitle">Manage your e-wallet balance, top up, withdraw, and view transactions.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(base_url('/wallet_history.php')) ?>">View all transactions</a>
  </div>
</div>

<div class="grid stats-grid">
  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
    <div class="stat-label">Current balance</div>
    <div class="stat-value tabular"><?= e($currency) ?> <?= e(money_fmt($balance)) ?></div>
    <div class="stat-sub">Available for expenses</div>
  </div>

  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
    <div class="stat-label">Top-up this month</div>
    <div class="stat-value tabular"><?= e($currency) ?> <?= e(money_fmt($monthTopup)) ?></div>
    <div class="stat-sub"><?= e(date('F Y')) ?></div>
  </div>

  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    <div class="stat-label">Spent from wallet</div>
    <div class="stat-value tabular"><?= e($currency) ?> <?= e(money_fmt($monthSpent)) ?></div>
    <div class="stat-sub"><?= e(date('F Y')) ?></div>
  </div>

  <div class="stat-card">
    <svg class="stat-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    <div class="stat-label">Currency</div>
    <div class="stat-value tabular"><?= e($currency) ?></div>
    <div class="stat-sub">Nepalese Rupee</div>
  </div>
</div>

<div class="grid two-col">
  <section class="card">
    <div class="card-head">
      <h2>Top up wallet</h2>
    </div>

    <?php if ($topupErrors['general']): ?>
      <div class="alert"><?= e($topupErrors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="topup" />

      <div class="field">
        <label>Amount (<?= e($currency) ?>)</label>
        <input name="amount" inputmode="decimal" placeholder="e.g., 5000" required>
        <?php if ($topupErrors['amount']): ?><div class="hint error"><?= e($topupErrors['amount']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Note (optional)</label>
        <input name="note" maxlength="255" placeholder="e.g., Salary top-up">
      </div>

      <button class="btn btn-primary" type="submit">Top up</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Withdraw from wallet</h2>
    </div>

    <?php if ($withdrawErrors['general']): ?>
      <div class="alert"><?= e($withdrawErrors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="withdraw" />

      <div class="field">
        <label>Amount (<?= e($currency) ?>)</label>
        <input name="amount" inputmode="decimal" placeholder="e.g., 1000" required>
        <?php if ($withdrawErrors['amount']): ?><div class="hint error"><?= e($withdrawErrors['amount']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Note (optional)</label>
        <input name="note" maxlength="255" placeholder="e.g., Cash withdrawal">
      </div>

      <button class="btn btn-danger" type="submit">Withdraw</button>
    </form>
  </section>
</div>

<section class="card">
  <div class="card-head">
    <h2>Recent transactions</h2>
    <a class="btn btn-ghost" href="<?= e(base_url('/wallet_history.php')) ?>">View all</a>
  </div>

  <?php if (!$recentTx): ?>
    <p class="muted">No transactions yet. Top up your wallet to get started.</p>
  <?php else: ?>
    <div class="table">
      <div class="table-row head">
        <div>Date</div>
        <div>Type</div>
        <div>Note</div>
        <div class="right">Amount</div>
        <div class="right">Balance after</div>
      </div>

      <?php foreach ($recentTx as $tx): ?>
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
