<?php
/**
 * Codebridge AI Chat Widget — Backend
 * Loads Gemini API key + model from .env, forwards user messages to Gemini,
 * returns the reply as JSON.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['reply' => 'Method not allowed.']);
    exit;
}

// --- Manual .env loader (no Composer needed) ---
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

loadEnv(__DIR__ . '/.env');

$apiKey = getenv('GEMINI_API_KEY');
$model  = getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash';

if (!$apiKey || $apiKey === 'your_actual_key_here') {
    echo json_encode(['reply' => 'Chat is not configured yet — GEMINI_API_KEY missing in .env.']);
    exit;
}

// --- Read the incoming message ---
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$userMessage = trim($input['message'] ?? '');

if ($userMessage === '') {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

if (mb_strlen($userMessage) > 2000) {
    echo json_encode(['reply' => 'Message is too long. Please shorten it.']);
    exit;
}

// --- Build request to Gemini ---
$systemContext = "You are the Codebridge website assistant. Codebridge is a Kigali, Rwanda based "
    . "software company offering web/mobile development, AI/ML, cybersecurity, cloud infrastructure, "
    . "IT consulting, and tech training programmes. Answer helpfully, briefly, and in a friendly, "
    . "professional tone. If asked something unrelated to Codebridge or general help, answer normally "
    . "but keep responses concise.";

$payload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $systemContext . "\n\nUser: " . $userMessage]
            ]
        ]
    ]
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    error_log('chat.php cURL error: ' . $curlError);
    echo json_encode(['reply' => "I'm having trouble connecting right now. Please try again shortly."]);
    exit;
}

if ($httpCode !== 200) {
    error_log('chat.php Gemini API error. HTTP ' . $httpCode . ': ' . $response);
    echo json_encode(['reply' => 'Sorry, I ran into an issue reaching the AI service. (HTTP ' . $httpCode . ')']);
    exit;
}

$data = json_decode($response, true);
$reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$reply) {
    echo json_encode(['reply' => "Sorry, I couldn't generate a response. Please try rephrasing."]);
    exit;
}

echo json_encode(['reply' => trim($reply)]);