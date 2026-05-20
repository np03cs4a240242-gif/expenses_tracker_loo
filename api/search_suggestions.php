<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/models/CategoryModel.php';
require_once __DIR__ . '/../Src/models/ExpenseModel.php';
require_once __DIR__ . '/../Src/models/WalletModel.php';

$user = api_user();
$userId = (int)$user['id'];

$query = str_trim($_GET['q'] ?? '');

if ($query === '') {
  api_success(['suggestions' => getDefaultSuggestions($userId)]);
  exit;
}

$suggestions = [];
$lowerQuery = strtolower($query);

$suggestions = array_merge($suggestions, getCategorySuggestions($userId, $query, $lowerQuery));
$suggestions = array_merge($suggestions, getNoteSuggestions($userId, $query, $lowerQuery));
$suggestions = array_merge($suggestions, getQuickFilterSuggestions($query, $lowerQuery));
$suggestions = array_merge($suggestions, getRecentSearchSuggestions($query, $lowerQuery));

$suggestions = array_slice($suggestions, 0, 10);

api_success(['suggestions' => $suggestions]);

function getDefaultSuggestions(int $userId): array
{
  $suggestions = [];

  $suggestions[] = [
    'type' => 'shortcut',
    'label' => 'Today',
    'value' => 'today',
    'icon' => 'calendar',
  ];

  $suggestions[] = [
    'type' => 'shortcut',
    'label' => 'This week',
    'value' => 'this week',
    'icon' => 'calendar',
  ];

  $suggestions[] = [
    'type' => 'shortcut',
    'label' => 'This month',
    'value' => 'this month',
    'icon' => 'calendar',
  ];

  $categories = CategoryModel::allForUser($userId);
  foreach (array_slice($categories, 0, 5) as $cat) {
    $suggestions[] = [
      'type' => 'category',
      'label' => (string)$cat['name'],
      'value' => 'category:' . (string)$cat['name'],
      'icon' => 'tag',
    ];
  }

  $recentSearches = $_SESSION['recent_searches'] ?? [];
  foreach (array_slice($recentSearches, 0, 3) as $search) {
    $suggestions[] = [
      'type' => 'recent',
      'label' => (string)$search,
      'value' => (string)$search,
      'icon' => 'clock',
    ];
  }

  return $suggestions;
}

function getCategorySuggestions(int $userId, string $query, string $lowerQuery): array
{
  $suggestions = [];
  $categories = CategoryModel::allForUser($userId);

  foreach ($categories as $cat) {
    $name = (string)$cat['name'];
    if (stripos($name, $query) !== false) {
      $suggestions[] = [
        'type' => 'category',
        'label' => $name,
        'value' => 'category:' . $name,
        'icon' => 'tag',
      ];
    }
  }

  $categoryAliases = [
    'food' => ['food', 'lunch', 'dinner', 'breakfast', 'snack', 'meal', 'restaurant', 'cafe'],
    'transport' => ['transport', 'taxi', 'bus', 'ride', 'fuel', 'petrol', 'gas', 'parking'],
    'shopping' => ['shopping', 'clothes', 'shoes', 'electronics', 'gadget'],
    'entertainment' => ['entertainment', 'movie', 'game', 'music', 'concert'],
    'health' => ['health', 'medicine', 'doctor', 'hospital', 'pharmacy'],
    'bills' => ['bills', 'electricity', 'water', 'internet', 'phone', 'rent'],
    'education' => ['education', 'school', 'book', 'course', 'tuition'],
  ];

  foreach ($categoryAliases as $categoryName => $aliases) {
    foreach ($aliases as $alias) {
      if (stripos($alias, $lowerQuery) !== false || stripos($categoryName, $lowerQuery) !== false) {
        $suggestions[] = [
          'type' => 'category',
          'label' => ucfirst($categoryName) . ' (matches "' . $alias . '")',
          'value' => 'category:' . $categoryName,
          'icon' => 'tag',
        ];
        break;
      }
    }
  }

  return $suggestions;
}

function getNoteSuggestions(int $userId, string $query, string $lowerQuery): array
{
  $suggestions = [];
  $pdo = db();

  $stmt = $pdo->prepare('
    SELECT DISTINCT note FROM expenses
    WHERE user_id = ? AND note IS NOT NULL AND note != "" AND LOWER(note) LIKE ?
    ORDER BY expense_date DESC
    LIMIT 5
  ');
  $stmt->execute([$userId, '%' . $lowerQuery . '%']);
  $notes = $stmt->fetchAll();

  foreach ($notes as $note) {
    $noteText = (string)$note['note'];
    $suggestions[] = [
      'type' => 'note',
      'label' => $noteText,
      'value' => $noteText,
      'icon' => 'note',
    ];
  }

  return $suggestions;
}

function getQuickFilterSuggestions(string $query, string $lowerQuery): array
{
  $suggestions = [];

  $shortcuts = [
    'today' => ['today', 'tod'],
    'this week' => ['week', 'this week'],
    'this month' => ['month', 'this month'],
    'last month' => ['last month', 'last'],
    'wallet' => ['wallet'],
    'cash' => ['cash'],
    'card' => ['card'],
  ];

  foreach ($shortcuts as $label => $triggers) {
    foreach ($triggers as $trigger) {
      if (stripos($trigger, $lowerQuery) !== false || stripos($label, $lowerQuery) !== false) {
        $icon = in_array($label, ['wallet', 'cash', 'card']) ? 'payment' : 'calendar';
        $suggestions[] = [
          'type' => 'shortcut',
          'label' => ucfirst($label),
          'value' => $label,
          'icon' => $icon,
        ];
        break;
      }
    }
  }

  if (preg_match('/^(over|above|more than|>\s*)(\d+)/i', $query, $matches)) {
    $suggestions[] = [
      'type' => 'shortcut',
      'label' => 'Over Rs. ' . $matches[2],
      'value' => 'over:' . $matches[2],
      'icon' => 'amount',
    ];
  }

  if (preg_match('/^(under|below|less than|<\s*)(\d+)/i', $query, $matches)) {
    $suggestions[] = [
      'type' => 'shortcut',
      'label' => 'Under Rs. ' . $matches[2],
      'value' => 'under:' . $matches[2],
      'icon' => 'amount',
    ];
  }

  return $suggestions;
}

function getRecentSearchSuggestions(string $query, string $lowerQuery): array
{
  $suggestions = [];
  $recentSearches = $_SESSION['recent_searches'] ?? [];

  foreach ($recentSearches as $search) {
    $searchStr = (string)$search;
    if (stripos($searchStr, $query) !== false && stripos($searchStr, $query) !== 0) {
      $suggestions[] = [
        'type' => 'recent',
        'label' => $searchStr,
        'value' => $searchStr,
        'icon' => 'clock',
      ];
    }
  }

  return $suggestions;
}
