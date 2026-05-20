<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function ai_is_enabled(): bool
{
  return (bool)config('ai.enabled', false);
}

function ai_gemini_api_key(): string
{
  return str_trim((string)config('ai.gemini_api_key', ''));
}

function ai_gemini_model(): string
{
  return str_trim((string)config('ai.gemini_model', 'gemini-2.0-flash'));
}

function ai_gemini_timeout(): int
{
  return (int)config('ai.gemini_timeout_seconds', 30);
}

function ai_generate_insight(string $prompt): array
{
  if (!ai_is_enabled()) {
    return ['ok' => false, 'message' => 'AI is not enabled. Set AI_ENABLED=true and add your GEMINI_API_KEY.'];
  }

  $apiKey = ai_gemini_api_key();
  if ($apiKey === '') {
    return ['ok' => false, 'message' => 'Gemini API key is missing. Add it in Src/config.local.php or set GEMINI_API_KEY env var.'];
  }

  if (!function_exists('curl_init')) {
    return ['ok' => false, 'message' => 'The PHP cURL extension is required for AI.'];
  }

  $model = ai_gemini_model();
  $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

  $payload = json_encode([
    'contents' => [
      [
        'parts' => [
          ['text' => $prompt],
        ],
      ],
    ],
    'generationConfig' => [
      'temperature' => 0.4,
      'maxOutputTokens' => 500,
      'topP' => 0.8,
    ],
  ]);

  if ($payload === false) {
    return ['ok' => false, 'message' => 'Failed to build AI request payload.'];
  }

  $ch = curl_init($apiUrl);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => ai_gemini_timeout(),
  ]);

  $response = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    $detail = $curlError !== '' ? ' ' . $curlError : '';
    return ['ok' => false, 'message' => 'AI API request failed.' . $detail];
  }

  if ($status >= 200 && $status < 300) {
    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
      return ['ok' => false, 'message' => 'Invalid AI API response.'];
    }

    $text = '';
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
      $text = (string)$decoded['candidates'][0]['content']['parts'][0]['text'];
    }

    if ($text === '') {
      return ['ok' => false, 'message' => 'AI returned an empty response.'];
    }

    return ['ok' => true, 'text' => trim($text)];
  }

  $detail = '';
  $decoded = json_decode((string)$response, true);
  if (is_array($decoded)) {
    $detail = (string)($decoded['error']['message'] ?? $decoded['message'] ?? '');
  }
  if ($detail === '') {
    $detail = 'HTTP ' . $status;
  }

  return ['ok' => false, 'message' => 'AI API rejected the request: ' . $detail];
}

function ai_build_spending_prompt(array $spendingData): string
{
  $prompt = "You are a personal finance advisor analyzing expense data. ";
  $prompt .= "Provide 2-3 specific, actionable insights in plain text. ";
  $prompt .= "Be concise — each insight should be one short sentence. ";
  $prompt .= "Do NOT use markdown, bullet points, or formatting. Just plain text with each insight on a new line.\n\n";

  $prompt .= "Spending data:\n";

  if (!empty($spendingData['month_total'])) {
    $prompt .= "- Total spent this month: Rs. " . money_fmt((float)$spendingData['month_total']) . "\n";
  }

  if (!empty($spendingData['budget'])) {
    $prompt .= "- Monthly budget: Rs. " . money_fmt((float)$spendingData['budget']) . "\n";
  }

  if (!empty($spendingData['categories'])) {
    $prompt .= "- Top categories:\n";
    foreach ($spendingData['categories'] as $cat) {
      $prompt .= "  * " . (string)$cat['category'] . ": Rs. " . money_fmt((float)$cat['total']) . "\n";
    }
  }

  if (!empty($spendingData['recent_expenses'])) {
    $prompt .= "- Recent expenses:\n";
    foreach (array_slice($spendingData['recent_expenses'], 0, 10) as $exp) {
      $prompt .= "  * " . (string)$exp['expense_date'] . " | " . (string)($exp['category_name'] ?? 'Uncategorized') . " | Rs. " . money_fmt((float)$exp['amount']) . " | " . (string)($exp['note'] ?? '') . "\n";
    }
  }

  if (!empty($spendingData['wallet_balance'])) {
    $prompt .= "- Wallet balance: Rs. " . money_fmt((float)$spendingData['wallet_balance']) . "\n";
  }

  if (!empty($spendingData['monthly_trend'])) {
    $prompt .= "- Monthly trend:\n";
    foreach ($spendingData['monthly_trend'] as $month) {
      $prompt .= "  * " . (string)$month['month_key'] . ": Rs. " . money_fmt((float)$month['total']) . "\n";
    }
  }

  $prompt .= "\nAnalyze this data and give insights about:\n";
  $prompt .= "1. Spending patterns and anomalies\n";
  $prompt .= "2. Budget health and warnings\n";
  $prompt .= "3. Actionable savings tips specific to this user's data\n";

  return $prompt;
}
