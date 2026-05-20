<?php
declare(strict_types=1);

require_once __DIR__ . '/../Src/bootstrap.php';

if (!headers_sent()) {
  header('Content-Type: application/json; charset=UTF-8');
}

function api_json(array $payload, int $status = 200): never
{
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function api_success(array $data = [], int $status = 200): never
{
  api_json([
    'success' => true,
    'data' => $data,
  ], $status);
}

function api_error(string $message, int $status = 400, array $extra = []): never
{
  api_json([
    'success' => false,
    'message' => $message,
    'errors' => $extra,
  ], $status);
}

function api_user(): array
{
  $user = auth_user();
  if (!$user) {
    api_error('Unauthorized request.', 401);
  }

  return $user;
}

function api_require_post(): void
{
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('POST method is required.', 405);
  }
}
