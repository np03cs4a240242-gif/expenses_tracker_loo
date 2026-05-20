<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';
require_once __DIR__ . '/../src/models/ExpenseModel.php';
require_once __DIR__ . '/../src/models/CategoryModel.php';
require_once __DIR__ . '/../src/models/WalletModel.php';

require_auth();
$user = auth_user();

$title = 'Expenses';

$categories = CategoryModel::allForUser((int)$user['id']);
$wallet = WalletModel::getOrCreate((int)$user['id']);
$walletBalance = (float)$wallet['balance'];

$addErrors = ['amount' => '', 'expense_date' => '', 'general' => ''];

// Handle add expense
if (is_post() && ($_POST['action'] ?? '') === 'add') {
  csrf_verify();

  $amountRaw = str_trim($_POST['amount'] ?? '');
  $date = str_trim($_POST['expense_date'] ?? '');
  $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
  $note = str_trim($_POST['note'] ?? '');
  $note = $note === '' ? null : mb_substr($note, 0, 255);
  $paymentMethod = str_trim($_POST['payment_method'] ?? 'cash');
  $walletId = null;

  $allowedMethods = ['cash', 'card', 'wallet', 'other'];
  if (!in_array($paymentMethod, $allowedMethods, true)) $paymentMethod = 'cash';

  if ($paymentMethod === 'wallet') {
    $walletId = (int)$wallet['id'];
    $freshWallet = WalletModel::get((int)$user['id']);
    $currentBalance = $freshWallet ? (float)$freshWallet['balance'] : 0.0;
    if ($currentBalance < (float)$amountRaw) {
      $addErrors['general'] = 'Insufficient wallet balance. Current: ' . money_fmt($currentBalance);
    }
  }

  if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) $addErrors['amount'] = 'Amount must be a positive number.';
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $addErrors['expense_date'] = 'Valid date is required (YYYY-MM-DD).';

  // Validate category ownership if provided
  if ($categoryId !== null) {
    $ownedIds = array_map(fn($c) => (int)$c['id'], $categories);
    if (!in_array($categoryId, $ownedIds, true)) $categoryId = null;
  }

  if (!$addErrors['amount'] && !$addErrors['expense_date'] && !$addErrors['general']) {
    try {
      $expenseId = ExpenseModel::create((int)$user['id'], (float)$amountRaw, $date, $categoryId, $note, $walletId, $paymentMethod);

      if ($paymentMethod === 'wallet') {
        $payResult = WalletModel::payFromWallet((int)$user['id'], (float)$amountRaw, $expenseId, $note);
        if (!$payResult['ok']) {
          ExpenseModel::delete((int)$user['id'], $expenseId);
          $addErrors['general'] = $payResult['message'];
        }
      }

      if (!$addErrors['general']) {
        flash_set('success', 'Expense added.');
        redirect(base_url('/expenses.php'));
      }
    } catch (Throwable $e) {
      $addErrors['general'] = 'Failed to add expense.';
    }
  }
}

// Handle delete expense
if (is_post() && ($_POST['action'] ?? '') === 'delete') {
  csrf_verify();
  $expenseId = (int)($_POST['expense_id'] ?? 0);
  if ($expenseId > 0) {
    $expense = ExpenseModel::find((int)$user['id'], $expenseId);
    if ($expense && (string)$expense['payment_method'] === 'wallet' && (int)$expense['wallet_id'] > 0) {
      WalletModel::refundToWallet((int)$user['id'], (float)$expense['amount'], $expenseId, 'Refund for deleted expense');
    }
    ExpenseModel::delete((int)$user['id'], $expenseId);
    flash_set('success', 'Expense deleted.');
  }
  redirect(base_url('/expenses.php'));
}

// Filters (GET)
$rawQuery = str_trim($_GET['q'] ?? '');
$parsedFilters = parseSmartQuery($rawQuery);

$filters = [
  'q' => $parsedFilters['q'],
  'category_id' => str_trim($_GET['category_id'] ?? ''),
  'from' => str_trim($_GET['from'] ?? ''),
  'to' => str_trim($_GET['to'] ?? ''),
  'payment_method' => str_trim($_GET['payment_method'] ?? $parsedFilters['payment_method']),
  'min_amount' => $parsedFilters['min_amount'],
  'max_amount' => $parsedFilters['max_amount'],
];

// Override from/to if smart query provided date range
if ($parsedFilters['from']) $filters['from'] = $parsedFilters['from'];
if ($parsedFilters['to']) $filters['to'] = $parsedFilters['to'];

if ($filters['from'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) $filters['from'] = '';
if ($filters['to'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) $filters['to'] = '';
if ($filters['category_id'] !== '' && !ctype_digit($filters['category_id'])) $filters['category_id'] = '';

$allowedMethods = ['cash', 'card', 'wallet', 'other'];
if (!in_array($filters['payment_method'], $allowedMethods, true)) $filters['payment_method'] = '';

$rows = ExpenseModel::list((int)$user['id'], $filters, 250);

function parseSmartQuery(string $query): array
{
  $result = ['q' => '', 'from' => '', 'to' => '', 'payment_method' => '', 'min_amount' => null, 'max_amount' => null];
  if ($query === '') return $result;

  $parts = preg_split('/\s+/', $query);
  $textParts = [];

  foreach ($parts as $part) {
    $lower = strtolower($part);

    if ($lower === 'today') {
      $result['from'] = date('Y-m-d');
      $result['to'] = date('Y-m-d');
      continue;
    }

    if ($lower === 'week' || $lower === 'this') {
      continue;
    }

    if (in_array($lower, ['this', 'last'], true)) {
      continue;
    }

    if ($lower === 'month') {
      if (!empty($result['from'])) continue;
      $result['from'] = date('Y-m-01');
      $result['to'] = date('Y-m-t');
      continue;
    }

    if ($lower === 'week') {
      if (!empty($result['from'])) continue;
      $result['from'] = date('Y-m-d', strtotime('monday this week'));
      $result['to'] = date('Y-m-d');
      continue;
    }

    if (in_array($lower, ['wallet', 'cash', 'card', 'other'], true)) {
      $result['payment_method'] = $lower;
      continue;
    }

    if (preg_match('/^over:(\d+)$/', $part, $m)) {
      $result['min_amount'] = (int)$m[1];
      continue;
    }

    if (preg_match('/^under:(\d+)$/', $part, $m)) {
      $result['max_amount'] = (int)$m[1];
      continue;
    }

    if (preg_match('/^category:(.+)$/', $part, $m)) {
      $textParts[] = $m[1];
      continue;
    }

    $textParts[] = $part;
  }

  // Handle "this week" and "this month" multi-word
  $fullLower = strtolower($query);
  if (stripos($fullLower, 'this week') !== false && !$result['from']) {
    $result['from'] = date('Y-m-d', strtotime('monday this week'));
    $result['to'] = date('Y-m-d');
  }
  if (stripos($fullLower, 'this month') !== false && !$result['from']) {
    $result['from'] = date('Y-m-01');
    $result['to'] = date('Y-m-t');
  }
  if (stripos($fullLower, 'last month') !== false && !$result['from']) {
    $result['from'] = date('Y-m-01', strtotime('first day of last month'));
    $result['to'] = date('Y-m-t', strtotime('last day of last month'));
  }

  $result['q'] = implode(' ', $textParts);
  return $result;
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Expenses</h1>
    <p class="muted">Add, filter, edit, and manage your daily spending.</p>
  </div>
</div>

<div class="grid two-col">
  <section id="add" class="card">
    <div class="card-head">
      <h2>Add expense</h2>
    </div>

    <?php if ($addErrors['general']): ?>
      <div class="alert"><?= e($addErrors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add" />

      <div class="grid form-grid">
        <div class="field">
          <label>Amount (₹)</label>
          <input name="amount" inputmode="decimal" placeholder="e.g., 120.50" value="<?= e($_POST['amount'] ?? '') ?>" required>
          <?php if ($addErrors['amount']): ?><div class="hint error"><?= e($addErrors['amount']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label>Date</label>
          <input type="date" name="expense_date" value="<?= e($_POST['expense_date'] ?? date('Y-m-d')) ?>" required>
          <?php if ($addErrors['expense_date']): ?><div class="hint error"><?= e($addErrors['expense_date']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label>Category</label>
          <select name="category_id">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (string)($c['id']) === (string)($_POST['category_id'] ?? '') ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint muted">Manage in <a href="<?= e(base_url('/categories.php')) ?>">Categories</a></div>
        </div>

        <div class="field">
          <label>Payment method</label>
          <select name="payment_method">
            <option value="cash" <?= ($_POST['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="card" <?= ($_POST['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>Card</option>
            <option value="wallet" <?= ($_POST['payment_method'] ?? '') === 'wallet' ? 'selected' : '' ?>>Wallet (<?= e(money_fmt($walletBalance)) ?>)</option>
            <option value="other" <?= ($_POST['payment_method'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Note</label>
        <input name="note" maxlength="255" placeholder="e.g., lunch / taxi / groceries" value="<?= e($_POST['note'] ?? '') ?>">
        <div class="hint muted">Optional. Max 255 chars.</div>
      </div>

      <button class="btn" type="submit">Add expense</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Search & filters</h2>
    </div>

    <div class="smart-search-wrap">
      <div class="smart-search-input-wrap">
        <span class="smart-search-icon">🔍</span>
        <input
          id="smart-search"
          type="text"
          name="q"
          value="<?= e($rawQuery) ?>"
          placeholder="Search expenses... try &quot;food&quot;, &quot;this month&quot;, &quot;over 500&quot;"
          data-api-url="<?= e(base_url('/api/search_suggestions.php')) ?>"
          autocomplete="off"
        >
      </div>
      <div id="search-dropdown" class="search-dropdown" role="listbox"></div>
    </div>

    <form method="get" class="form" id="filter-form">
      <input type="hidden" name="q" value="<?= e($rawQuery) ?>">
      <div class="grid form-grid">
        <div class="field">
          <label>Category</label>
          <select name="category_id">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (string)$filters['category_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Payment method</label>
          <select name="payment_method">
            <option value="">All</option>
            <option value="cash" <?= ($filters['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="card" <?= ($filters['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>Card</option>
            <option value="wallet" <?= ($filters['payment_method'] ?? '') === 'wallet' ? 'selected' : '' ?>>Wallet</option>
            <option value="other" <?= ($filters['payment_method'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
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
        <button class="btn" type="submit">Apply</button>
        <a class="btn btn-ghost" href="<?= e(base_url('/expenses.php')) ?>">Reset</a>
      </div>
    </form>
  </section>
</div>

<section class="card">
  <div class="card-head">
    <h2>Expense list</h2>
    <div class="muted small"><?= count($rows) ?> result(s)</div>
  </div>

  <?php if (!$rows): ?>
    <p class="muted">No expenses found. Try adding one or adjusting filters.</p>
  <?php else: ?>
    <div class="table">
      <div class="row head">
        <div>Date</div>
        <div>Category</div>
        <div>Note</div>
        <div>Payment</div>
        <div class="right">Amount</div>
        <div class="right">Actions</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="row">
          <div><?= e($r['expense_date']) ?></div>
          <div><?= e($r['category_name'] ?? 'Uncategorized') ?></div>
          <div class="truncate"><?= e($r['note'] ?? '') ?></div>
          <div>
            <span class="badge badge-<?= e($r['payment_method'] === 'wallet' ? 'success' : ($r['payment_method'] === 'card' ? 'info' : 'secondary')) ?>">
              <?= e(ucfirst((string)($r['payment_method'] ?? 'cash'))) ?>
            </span>
          </div>
          <div class="right strong">₹ <?= e(money_fmt((float)$r['amount'])) ?></div>
          <div class="right">
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/expense_edit.php?id=' . (int)$r['id'])) ?>">Edit</a>

            <form method="post" class="inline" data-confirm="Delete this expense?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="expense_id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
