<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap.php';

logout_user();
session_start(); // start again to set flash
flash_set('success', 'Logged out successfully.');
redirect(base_url('/login.php'));
