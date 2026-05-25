<?php
require_once __DIR__ . '/Src/bootstrap.php';
require_once __DIR__ . '/Src/db.php';
try {
  db();
  echo "Connected successfully to database!";
} catch (\Exception $e) {
  echo "Database connection failed: " . $e->getMessage();
}
