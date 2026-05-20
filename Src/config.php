<?php
// src/config.php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
  $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
  if ($value === false || $value === null || $value === '') {
    return $default;
  }

  return $value;
};

$config = [
  'db' => [
    'host' => (string)$env('DB_HOST', '127.0.0.1'),
    'name' => (string)$env('DB_NAME', 'expense_tracker'),
    'user' => (string)$env('DB_USER', 'root'),
    'pass' => (string)$env('DB_PASS', ''), // XAMPP default is empty
    'charset' => (string)$env('DB_CHARSET', 'utf8mb4'),
  ],
  'app' => [
    // IMPORTANT:
    // If you access the project like: http://localhost/expense-tracker/public/
    // then base_url should be '/expense-tracker/public'
    'base_url' => (string)$env('APP_BASE_URL', '/expense-tracker/public'),
    'timezone' => (string)$env('APP_TIMEZONE', 'Asia/Kathmandu'),
  ],
  'otp' => [
    'length' => (int)$env('OTP_LENGTH', 6),
    'expires_minutes' => (int)$env('OTP_EXPIRES_MINUTES', 10),
    'resend_cooldown_seconds' => (int)$env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'debug_show_code' => filter_var($env('OTP_DEBUG_SHOW_CODE', true), FILTER_VALIDATE_BOOL),
    'from_email' => (string)$env('OTP_FROM_EMAIL', 'no-reply@expense-tracker.local'),
    'from_name' => (string)$env('OTP_FROM_NAME', 'Expense Tracker'),
    'provider' => (string)$env('OTP_PROVIDER', 'mail'),
    'api_key' => (string)$env('OTP_API_KEY', ''),
    'api_url' => (string)$env('OTP_API_URL', 'https://api.resend.com/emails'),
    'api_timeout_seconds' => (int)$env('OTP_API_TIMEOUT_SECONDS', 15),
  ],
  'smtp' => [
    'host' => (string)$env('SMTP_HOST', ''),
    'port' => (int)$env('SMTP_PORT', 587),
    'username' => (string)$env('SMTP_USERNAME', ''),
    'password' => (string)$env('SMTP_PASSWORD', ''),
    'encryption' => (string)$env('SMTP_ENCRYPTION', 'tls'),
    'auth' => filter_var($env('SMTP_AUTH', true), FILTER_VALIDATE_BOOL),
  ],
  'ai' => [
    'provider' => (string)$env('AI_PROVIDER', 'gemini'),
    'gemini_api_key' => (string)$env('GEMINI_API_KEY', ''),
    'gemini_model' => (string)$env('GEMINI_MODEL', 'gemini-2.0-flash'),
    'gemini_timeout_seconds' => (int)$env('GEMINI_TIMEOUT_SECONDS', 30),
    'enabled' => filter_var($env('AI_ENABLED', false), FILTER_VALIDATE_BOOL),
  ],
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
  $localConfig = require $localConfigPath;
  if (is_array($localConfig)) {
    $config = array_replace_recursive($config, $localConfig);
  }
}

return $config;
