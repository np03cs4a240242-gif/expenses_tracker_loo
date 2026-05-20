<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Src/models/AIRecommendationModel.php';

$user = api_user();

$insights = AIRecommendationModel::generateAll((int)$user['id']);
$geminiInsights = AIRecommendationModel::getGeminiInsights((int)$user['id']);

$allInsights = array_merge($geminiInsights, $insights);

api_success([
  'count' => count($allInsights),
  'items' => $allInsights,
]);
