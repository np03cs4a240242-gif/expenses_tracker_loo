<?php
// src/partials/header.php
declare(strict_types=1);

$user = auth_user();
$flash = flash_get();

$current_page = basename($_SERVER['PHP_SELF'] ?? '', '.php');

function nav_item(string $page, string $label, string $current_page, string $icon_path): string {
  $active = $page === $current_page ? ' active' : '';
  return '<a class="sidebar-link' . $active . '" href="' . e(base_url('/' . $page . '.php')) . '">'
    . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $icon_path . '</svg>'
    . '<span>' . $label . '</span></a>';
}

$icons = [
  'dashboard'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  'expenses'      => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
  'categories'    => '<path d="M4 4h16v16H4z"/><path d="M4 10h16"/><path d="M10 4v16"/>',
  'budgets'       => '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>',
  'wallet'        => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
  'recommendations' => '<path d="M12 2a7 7 0 0 1 7 7c0 3-2 5.5-4 7.5L12 20l-3-3.5C7 14.5 5 12 5 9a7 7 0 0 1 7-7z"/><circle cx="12" cy="9" r="2.5"/>',
  'reports'       => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($title ?? 'Expense Tracker') ?></title>
  <link rel="stylesheet" href="<?= e(base_url('/assets/styles/main.css')) ?>">
  <script defer src="<?= e(base_url('/assets/scripts/app.js')) ?>"></script>
</head>
<body>
  <div class="app-shell">

    <?php if ($user): ?>
    <button class="sidebar-toggle" aria-label="Toggle navigation" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('show');">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('show');"></div>

    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="sidebar-logo">ET</div>
        <div class="sidebar-brand-text">
          <div class="sidebar-brand-title">ExpenseTracker</div>
          <div class="sidebar-brand-sub">Manage your budget</div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <?= nav_item('dashboard', 'Dashboard', $current_page, $icons['dashboard']) ?>
        <?= nav_item('expenses', 'Expenses', $current_page, $icons['expenses']) ?>
        <?= nav_item('categories', 'Categories', $current_page, $icons['categories']) ?>
        <?= nav_item('budgets', 'Budgets', $current_page, $icons['budgets']) ?>
        <?= nav_item('wallet', 'Wallet', $current_page, $icons['wallet']) ?>
        <?= nav_item('recommendations', 'AI Insights', $current_page, $icons['recommendations']) ?>
        <?= nav_item('reports', 'Reports', $current_page, $icons['reports']) ?>
      </nav>

      <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= e($user['name']) ?></div>
          <div class="sidebar-user-email"><?= e($user['email']) ?></div>
        </div>
        <a class="sidebar-logout" href="<?= e(base_url('/logout.php')) ?>">Logout</a>
      </div>

      <div class="sidebar-footer">
        &copy; <?= date('Y') ?> ExpenseTracker
      </div>
    </aside>
    <?php endif; ?>

    <main class="main">
      <?php if (!empty($flash)): ?>
        <div class="flash-stack">
          <?php foreach ($flash as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>">
              <div class="flash-dot"></div>
              <div><?= e($f['message']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
