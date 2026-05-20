<?php
declare(strict_types=1);

if (!isset($_SESSION) || !is_array($_SESSION)) {
  $_SESSION = [];
}

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notification_service.php';

date_default_timezone_set((string)config('app.timezone', 'UTC'));
