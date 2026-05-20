<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap.php';

if (auth_user()) {
  redirect(base_url('/dashboard.php'));
}
redirect(base_url('/login.php'));
