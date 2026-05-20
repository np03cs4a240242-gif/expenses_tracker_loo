<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap_form.php';
require_once __DIR__ . '/../src/models/ExpenseModel.php';
require_once __DIR__ . '/../src/models/CategoryModel.php';
require_once __DIR__ . '/../src/models/WalletModel.php';

require_auth();
$user = auth_user();

$expenseId = (int)($_GET['id'] ?? 0);
if ($expenseId <= 0) {
  flash_set('warning', 'Invalid expense.');
  redirect(base_url('/expenses.php'));
}

$row = ExpenseModel::find((int)$user['id'], $expenseId);
if (!$row) {
  flash_set('warning', 'Expense not found.');
  redirect(base_url('/expenses.php'));
}

$title = 'Edit Expense';
$categories = CategoryModel::allForUser((int)$user['id']);
$wallet = WalletModel::getOrCreate((int)$user['id']);
$walletBalance = (float)$wallet['balance'];
$errors = ['amount' => '', 'expense_date' => '', 'general' => ''];

$oldPaymentMethod = (string)($row['payment_method'] ?? 'cash');
$oldAmount = (float)$row['amount'];

if (is_post()) {
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
  }

  if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) $errors['amount'] = 'Amount must be a positive number.';
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors['expense_date'] = 'Valid date is required (YYYY-MM-DD).';

  if ($categoryId !== null) {
    $ownedIds = array_map(fn($c) => (int)$c['id'], $categories);
    if (!in_array($categoryId, $ownedIds, true)) $categoryId = null;
  }

  if (!$errors['amount'] && !$errors['expense_date']) {
    try {
      if ($oldPaymentMethod === 'wallet' && $paymentMethod !== 'wallet') {
        $refundResult = WalletModel::refundToWallet((int)$user['id'], $oldAmount, $expenseId, 'Refund: payment method changed from wallet');
        if (!$refundResult['ok']) {
          $errors['general'] = $refundResult['message'];
        }
      }

      if (!$errors['general'] && $paymentMethod === 'wallet' && $oldPaymentMethod !== 'wallet') {
        $freshWallet = WalletModel::getOrCreate((int)$user['id']);
        if ((float)$freshWallet['balance'] < (float)$amountRaw) {
          $errors['general'] = 'Insufficient wallet balance. Current: ' . money_fmt((float)$freshWallet['balance']);
        } else {
          $payResult = WalletModel::payFromWallet((int)$user['id'], (float)$amountRaw, $expenseId, $note);
          if (!$payResult['ok']) {
            $errors['general'] = $payResult['message'];
          }
        }
      }

      if (!$errors['general'] && $paymentMethod === 'wallet' && $oldPaymentMethod === 'wallet' && (float)$amountRaw !== $oldAmount) {
        $diff = (float)$amountRaw - $oldAmount;
        if ($diff > 0) {
          $freshWallet = WalletModel::getOrCreate((int)$user['id']);
          if ((float)$freshWallet['balance'] < $diff) {
            $errors['general'] = 'Insufficient wallet balance for increased amount.';
          } else {
            WalletModel::payFromWallet((int)$user['id'], $diff, $expenseId, 'Expense amount increased');
          }
        } elseif ($diff < 0) {
          WalletModel::refundToWallet((int)$user['id'], abs($diff), $expenseId, 'Expense amount decreased');
        }
      }

      if (!$errors['general']) {
        ExpenseModel::update((int)$user['id'], $expenseId, (float)$amountRaw, $date, $categoryId, $note, $walletId, $paymentMethod);
        flash_set('success', 'Expense updated.');
        redirect(base_url('/expenses.php'));
      }
    } catch (Throwable $e) {
      $errors['general'] = 'Failed to update expense.';
    }
  }
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Edit expense</h1>
    <p class="muted">Update details and keep your records accurate.</p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= e(base_url('/expenses.php')) ?>">← Back</a>
  </div>
</div>

<section class="card">
  <div class="card-head">
    <h2>Expense #<?= (int)$expenseId ?></h2>
  </div>

  <?php if ($errors['general']): ?>
    <div class="alert"><?= e($errors['general']) ?></div>
  <?php endif; ?>

  <form method="post" class="form">
    <?= csrf_field() ?>

      <div class="grid form-grid">
        <div class="field">
          <label>Amount (₹)</label>
          <input name="amount" value="<?= e($_POST['amount'] ?? (string)$row['amount']) ?>" required>
          <?php if ($errors['amount']): ?><div class="hint error"><?= e($errors['amount']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label>Date</label>
          <input type="date" name="expense_date" value="<?= e($_POST['expense_date'] ?? (string)$row['expense_date']) ?>" required>
          <?php if ($errors['expense_date']): ?><div class="hint error"><?= e($errors['expense_date']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label>Category</label>
          <select name="category_id">
            <option value="">Uncategorized</option>
            <?php
              $selected = $_POST['category_id'] ?? (string)($row['category_id'] ?? '');
            ?>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (string)$c['id'] === (string)$selected ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Payment method</label>
          <select name="payment_method">
            <?php $pm = $_POST['payment_method'] ?? (string)($row['payment_method'] ?? 'cash'); ?>
            <option value="cash" <?= $pm === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="card" <?= $pm === 'card' ? 'selected' : '' ?>>Card</option>
            <option value="wallet" <?= $pm === 'wallet' ? 'selected' : '' ?>>Wallet (<?= e(money_fmt($walletBalance)) ?>)</option>
            <option value="other" <?= $pm === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Note</label>
        <input name="note" maxlength="255" value="<?= e($_POST['note'] ?? (string)($row['note'] ?? '')) ?>">
      </div>

    <div class="row-actions">
      <button class="btn" type="submit">Save changes</button>
      <a class="btn btn-ghost" href="<?= e(base_url('/expenses.php')) ?>">Cancel</a>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
