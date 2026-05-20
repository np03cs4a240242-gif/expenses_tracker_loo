<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/models/WalletModel.php';
require_once __DIR__ . '/../Src/models/WalletTransactionModel.php';

$user = api_user();
$userId = (int)$user['id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $action = $_GET['action'] ?? 'balance';

  if ($action === 'balance') {
    $wallet = WalletModel::getOrCreate($userId);
    api_success([
      'balance' => (float)$wallet['balance'],
      'currency' => (string)$wallet['currency'],
    ]);
  }

  if ($action === 'transactions') {
    $filters = [
      'type' => str_trim($_GET['type'] ?? ''),
      'from' => str_trim($_GET['from'] ?? ''),
      'to' => str_trim($_GET['to'] ?? ''),
    ];

    $allowedTypes = ['topup', 'withdrawal', 'expense', 'refund'];
    if (!in_array($filters['type'], $allowedTypes, true)) {
      $filters['type'] = '';
    }

    $limit = min((int)($_GET['limit'] ?? 50), 250);
    $rows = WalletTransactionModel::list($userId, $filters, $limit);
    api_success(['count' => count($rows), 'items' => $rows]);
  }

  if ($action === 'summary') {
    $monthKey = date('Y-m');
    $monthStart = $monthKey . '-01';
    $monthEnd = date('Y-m-t');

    api_success([
      'balance' => WalletModel::getBalance($userId),
      'month_topup' => WalletModel::getTotalTopup($userId, $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'),
      'month_spent' => WalletModel::getTotalSpent($userId, $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'),
    ]);
  }

  api_error('Unknown action.', 400);
}

if ($method === 'POST') {
  api_require_post();

  $action = $_POST['action'] ?? '';

  if ($action === 'topup') {
    $amountRaw = str_trim($_POST['amount'] ?? '');
    $note = str_trim($_POST['note'] ?? '');
    $note = $note === '' ? null : mb_substr($note, 0, 255);

    if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
      api_error('Amount must be a positive number.');
    }

    $result = WalletModel::topup($userId, (float)$amountRaw, $note);
    if ($result['ok']) {
      api_success(['balance' => $result['balance'], 'message' => $result['message']]);
    } else {
      api_error($result['message']);
    }
  }

  if ($action === 'withdraw') {
    $amountRaw = str_trim($_POST['amount'] ?? '');
    $note = str_trim($_POST['note'] ?? '');
    $note = $note === '' ? null : mb_substr($note, 0, 255);

    if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
      api_error('Amount must be a positive number.');
    }

    $result = WalletModel::withdraw($userId, (float)$amountRaw, $note);
    if ($result['ok']) {
      api_success(['balance' => $result['balance'], 'message' => $result['message']]);
    } else {
      api_error($result['message']);
    }
  }

  api_error('Unknown action.', 400);
}

api_error('Method not allowed.', 405);
