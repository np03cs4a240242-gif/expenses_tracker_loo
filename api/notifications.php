<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/models/NotificationModel.php';

$user = api_user();
$userId = (int)$user['id'];

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
  $limit = min((int)($_GET['limit'] ?? 10), 50);
  $notifications = NotificationModel::listForUser($userId, $limit);
  api_success(['count' => count($notifications), 'items' => $notifications]);
}

if ($action === 'unread_count') {
  $count = NotificationModel::unreadCount($userId);
  api_success(['count' => $count]);
}

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    NotificationModel::markAsRead($userId, $id);
    api_success(['message' => 'Notification marked as read.']);
  }
  api_error('Invalid notification ID.');
}

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  NotificationModel::markAllAsRead($userId);
  api_success(['message' => 'All notifications marked as read.']);
}

api_error('Unknown action.', 400);
