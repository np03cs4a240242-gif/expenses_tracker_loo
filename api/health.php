<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_success([
  'app' => 'Expense Tracker',
  'status' => 'ok',
  'time' => date('c'),
]);
