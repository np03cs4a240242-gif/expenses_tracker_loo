<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/models/CategoryModel.php';

$user = api_user();
$rows = CategoryModel::allForUser((int)$user['id']);

api_success([
  'count' => count($rows),
  'items' => $rows,
]);
