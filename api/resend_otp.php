<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/csrf.php';

api_require_post();
csrf_verify();

$result = resend_otp_for_current_user();
if (!$result['ok']) {
  api_error($result['message'], 400);
}

api_success([
  'message' => $result['message'],
]);
