<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/models/ExpenseModel.php';

$user = api_user();

$filters = [
  'q' => str_trim($_GET['q'] ?? ''),
  'category_id' => str_trim($_GET['category_id'] ?? ''),
  'from' => str_trim($_GET['from'] ?? ''),
  'to' => str_trim($_GET['to'] ?? ''),
];

if ($filters['from'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) $filters['from'] = '';
if ($filters['to'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) $filters['to'] = '';
if ($filters['category_id'] !== '' && !ctype_digit($filters['category_id'])) $filters['category_id'] = '';

$rows = ExpenseModel::list((int)$user['id'], $filters, 250);

foreach ($rows as &$row) {
  $row['payment_method'] = (string)($row['payment_method'] ?? 'cash');
}
unset($row);

api_success([
  'count' => count($rows),
  'items' => $rows,
]);
