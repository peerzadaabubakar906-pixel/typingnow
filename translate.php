<?php
// Roman Urdu -> English using the Gemini API.
// 1) Paste your Gemini API key below.  2) Keep this file on your server only.
$GEMINI_KEY = 'PASTE_YOUR_GEMINI_API_KEY_HERE';
$MODEL      = 'gemini-3.8-flash';   // change here if Google renames the model

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Setup check: open  yourdomain.com/translate.php?test=1  in the browser.
$test = isset($_GET['test']);
if ($test) {
  $text = 'kia hal hai';
} else {
  $in   = json_decode(file_get_contents('php://input'), true);
  $text = trim($in['text'] ?? '');
}
if ($text === '') { echo json_encode(['translation' => '']); exit; }
if (mb_strlen($text) > 1500) { $text = mb_substr($text, 0, 1500); }

if (!function_exists('curl_init')) {
  http_response_code(500);
  echo json_encode(['error' => 'PHP curl extension is not enabled on this server']);
  exit;
}
if ($GEMINI_KEY === '' || strpos($GEMINI_KEY, 'PASTE_') === 0) {
  http_response_code(500);
  echo json_encode(['error' => 'API key is not set in translate.php']);
  exit;
}

$body = [
  'systemInstruction' => ['parts' => [['text' =>
    'You translate Roman Urdu (Urdu written in English letters) into natural, correct English. '
    . 'Reply with the English translation only: no notes, no quotes, no explanations. '
    . 'If the text is already English, return it unchanged.']]],
  'contents' => [['parts' => [['text' => $text]]]],
  'generationConfig' => ['temperature' => 0.2],
];

$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/$MODEL:generateContent");
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 25,
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $GEMINI_KEY],
  CURLOPT_POSTFIELDS     => json_encode($body),
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

$j   = json_decode($res, true);
$out = '';
foreach (($j['candidates'][0]['content']['parts'] ?? []) as $part) {
  if (empty($part['thought']) && isset($part['text'])) { $out .= $part['text']; }
}

if ($code !== 200 || trim($out) === '') {
  http_response_code(502);
  $detail = $cerr ?: ($j['error']['message'] ?? ('HTTP ' . $code . ', empty reply'));
  echo json_encode(['error' => $test ? $detail : 'translation_failed']);
  exit;
}
echo json_encode(['translation' => trim($out)], JSON_UNESCAPED_UNICODE);
