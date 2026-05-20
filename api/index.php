<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_success([
  'name' => 'Expense Tracker API',
  'endpoints' => [
    'GET /expense-tracker/api/health.php',
    'GET /expense-tracker/api/dashboard_summary.php',
    'GET /expense-tracker/api/expenses.php',
    'GET /expense-tracker/api/categories.php',
    'GET /expense-tracker/api/wallet.php?action=balance',
    'GET /expense-tracker/api/wallet.php?action=transactions',
    'GET /expense-tracker/api/wallet.php?action=summary',
    'POST /expense-tracker/api/wallet.php?action=topup',
    'POST /expense-tracker/api/wallet.php?action=withdraw',
    'GET /expense-tracker/api/recommendations.php',
    'GET /expense-tracker/api/search_suggestions.php?q=...',
    'POST /expense-tracker/api/resend_otp.php',
  ],
]);
