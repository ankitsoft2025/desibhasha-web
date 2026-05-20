<?php

require_once 'utils.php';

header('Content-Type: application/json');
$conn = get_db_connection();

// ==================== CONSTANTS ====================
const GOOGLE_TTS_API_KEY = "AIzaSyBWW6cpMGjFiGU6iq1qO67sB2FqF3SQCtk";
const GOOGLE_TTS_API_URL = "https://texttospeech.googleapis.com/v1/text:synthesize";
const GOOGLE_STT_API_KEY = "AIzaSyBWW6cpMGjFiGU6iq1qO67sB2FqF3SQCtk";
const GOOGLE_STT_API_URL = "https://speech.googleapis.com/v1/speech:recognize";
const AUDIO_STORAGE_PATH = __DIR__ . "/../assets/srt_test/";

const LANGUAGE_CODES = [
    '1'  => 'hi-IN',      // Hindi
    '2'  => 'pa-IN',      // Punjabi
    '3'  => 'te-IN',      // Telugu
    '4'  => 'mr-IN',      // Marathi
    '5'  => 'gu-IN',      // Gujarati
    '6'  => 'ta-IN',      // Tamil
    '7'  => 'ml-IN',      // Malayalam
    '8'  => 'kn-IN',      // Kannada
    '9'  => 'bn-IN',      // Bangla
    '10' => 'or-IN',      // Odia
];

// ==================== UTILITY FUNCTIONS ====================

/**
 * Send HTTP response and terminate execution
 */
function respond_and_exit(int $status, array $payload): void
{
    global $conn;
    http_response_code($status);
    echo json_encode($payload);
    $conn->close();
    exit;
}

/**
 * Ensure audio storage directory exists
 */
function ensure_audio_directory_exists(): bool
{
    $dir = AUDIO_STORAGE_PATH;
    
    if (!is_dir($dir)) {
        $old_umask = umask(0);
        $created = mkdir($dir, 0755, true);
        umask($old_umask);
        return $created;
    }
    
    return true;
}

/**
 * Generate TTS audio using Google Text-to-Speech API
 */
function generate_tts_audio(string $text, int $language_id): ?array
{
    $apiKey = GOOGLE_TTS_API_KEY;
    $languageCode = LANGUAGE_CODES[$language_id] ?? 'hi-IN';
    $url = GOOGLE_TTS_API_URL . "?key=" . $apiKey;
    
    // Use SSML for better control and pauses
    $ssmlText = create_ssml($text, $languageCode);
    
    $data = [
        "input" => [
            "ssml" => $ssmlText
        ],
        "voice" => [
            "languageCode" => $languageCode,
            "name" => $languageCode . "-Neural2-A"
        ],
        "audioConfig" => [
            "audioEncoding" => "LINEAR16",
            "pitch" => 0.0,
            "speakingRate" => 0.9  // Slower speech for better clarity
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => "TTS CURL Error: $error"];
    }
    
    if ($http_code !== 200) {
        $result = json_decode($response, true);
        $error_msg = $result['error']['message'] ?? "HTTP $http_code";
        return ['error' => "TTS API Error: $error_msg"];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['audioContent'])) {
        return ['audioContent' => $result['audioContent']];
    }
    
    return ['error' => 'No audioContent in TTS response'];
}

/**
 * Create SSML with pauses for better alignment
 */
function create_ssml(string $text, string $languageCode): string
{
    // Add breaks between natural word boundaries
    $ssml = '<speak>';
    $ssml .= '<prosody rate="90%">';
    
    // Split by spaces and add breaks
    $words = explode(' ', $text);
    foreach ($words as $i => $word) {
        $ssml .= $word;
        if ($i < count($words) - 1) {
            $ssml .= ' <break time="100ms"/> ';
        }
    }
    
    $ssml .= '</prosody>';
    $ssml .= '</speak>';
    
    return $ssml;
}

/**
 * Generate timestamps using Google Speech-to-Text API
 */
function generate_timestamps_via_stt(string $audioContent, int $language_id): ?array
{
    $apiKey = GOOGLE_STT_API_KEY;
    $languageCode = LANGUAGE_CODES[$language_id] ?? 'hi-IN';
    $url = GOOGLE_STT_API_URL . "?key=" . $apiKey;
    
    $data = [
        "audio" => [
            "content" => $audioContent  // base64 encoded
        ],
        "config" => [
            "encoding" => "LINEAR16",
            "languageCode" => $languageCode,
            "enableWordTimeOffsets" => true,
            "audioChannelCount" => 1
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => "STT CURL Error: $error"];
    }
    
    if ($http_code !== 200) {
        $result = json_decode($response, true);
        $error_msg = $result['error']['message'] ?? "HTTP $http_code";
        return ['error' => "STT API Error: $error_msg"];
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['results'][0]['alternatives'][0]['words'])) {
        return ['error' => 'No word timestamps in STT response'];
    }
    
    $words = $result['results'][0]['alternatives'][0]['words'];
    
    // Format timestamps with buffer for better sync
    $timestamps = [];
    foreach ($words as $word) {
        $startTime = $word['startTime'] ?? '';
        $endTime = $word['endTime'] ?? '';
        
        // Convert "1.234s" format to milliseconds
        $startMs = parse_duration($startTime);
        $endMs = parse_duration($endTime);
        
        // Add buffer for kid-friendly experience
        $startMs = max(0, $startMs - 20);
        $endMs = $endMs + 30;
        
        $timestamps[] = [
            'word' => $word['word'],
            'start' => $startMs,
            'end' => $endMs
        ];
    }
    
    return ['timestamps' => $timestamps];
}

/**
 * Parse duration string (e.g., "1.234s") to milliseconds
 */
function parse_duration(string $duration): int
{
    if (empty($duration)) {
        return 0;
    }
    
    $seconds = (float) str_replace('s', '', $duration);
    return (int) round($seconds * 1000);
}

/**
 * Save audio file
 */
function save_audio_file(string $audioContent, string $filename): ?string
{
    if (!ensure_audio_directory_exists()) {
        return null;
    }
    
    $filepath = AUDIO_STORAGE_PATH . $filename;
    
    // Decode base64 audio content
    $audioData = base64_decode($audioContent, true);
    
    if ($audioData === false) {
        return null;
    }
    
    if (file_put_contents($filepath, $audioData) === false) {
        return null;
    }
    
    return 'assets/srt_test/' . $filename;
}

// ==================== MAIN LOGIC ====================

$input = json_decode(file_get_contents('php://input'), true);

$text = trim($input['text'] ?? '');
$language_id = trim($input['language_id'] ?? '');

if (!$text) {
    respond_and_exit(400, ['error' => 'Text is mandatory']);
}

if (!isset(LANGUAGE_CODES[$language_id])) {
    respond_and_exit(400, ['error' => 'Invalid language_id']);
}

// Step 1: Generate TTS audio
$tts_result = generate_tts_audio($text, (int)$language_id);

if (isset($tts_result['error'])) {
    respond_and_exit(500, ['error' => $tts_result['error']]);
}

$audioContent = $tts_result['audioContent'];

// Step 2: Generate timestamps from audio
$stt_result = generate_timestamps_via_stt($audioContent, (int)$language_id);

if (isset($stt_result['error'])) {
    respond_and_exit(500, ['error' => $stt_result['error']]);
}

$timestamps = $stt_result['timestamps'];

// Step 3: Save audio file
$timestamp_id = uniqid('ts_');
$filename = $timestamp_id . "_" . time() . ".wav";
$audio_url = save_audio_file($audioContent, $filename);

if (!$audio_url) {
    respond_and_exit(500, ['error' => 'Failed to save audio file']);
}

// Return success response
respond_and_exit(200, [
    'data' => [
        'audio_url' => "https://www.desibhasha.com/".$audio_url,
        'timestamps' => $timestamps,
        'word_count' => count($timestamps),
        'total_duration_ms' => end($timestamps)['end'] ?? 0
    ]
]);

?>
