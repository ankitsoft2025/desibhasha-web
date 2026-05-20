<?php 

require_once 'utils.php';

header('Content-Type: application/json');
$conn = get_db_connection();

// ==================== CONSTANTS ====================
// ankit key https://console.cloud.google.com/apis/credentials?project=gen-lang-client-0747392353
// const GEMINI_API_KEY = "AIzaSyAS8ZkjNiNzZug0NfFAkkH17K0N6b2ghKk";
// desi bhasha key
// const GEMINI_API_KEY = "AIzaSyBslLyR9-kByuVDtUJ9PcW9NjrmBepIpAI"; // first project
const GEMINI_API_KEY = "AIzaSyAscmXHjE9Qadgf70fqfgdYcciJ7GtihpA"; // gemini project

const GEMINI_API_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent";
const GOOGLE_TTS_API_KEY = "AIzaSyBWW6cpMGjFiGU6iq1qO67sB2FqF3SQCtk";
const GOOGLE_TTS_API_URL = "https://texttospeech.googleapis.com/v1/text:synthesize";
const DB_TABLE_TRANSLATION_GLOBAL = "translation_global";
const DB_TABLE_TRANSLATION_USER = "translation_user";
const AUDIO_STORAGE_PATH = __DIR__ . "/../assets/translation/";

const LANGUAGES = [
    '1'  => 'Hindi',
    '2'  => 'Punjabi',
    '3'  => 'Telugu',
    '4'  => 'Marathi',
    '5'  => 'Gujarati',
    '6'  => 'Tamil',
    '7'  => 'Malayalam',
    '8'  => 'Kannada',
    '9'  => 'Bangla',
    '10' => 'Odiya',
];

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
    '10' => 'or-IN',      // Odiya
];

// ==================== UTILITY FUNCTIONS ====================

/**
 * Normalize text for comparison by removing spaces, tabs, and quotes, then converting to lowercase
 */
function normalize_text_for_comparison(string $text): string
{
    return preg_replace('/[ \t\'\"]+/', '', strtolower($text));
}

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
 * Check if translation already exists in global table
 */
function translation_exists_in_global(object $conn, string $text, int $language_id): bool
{
    $query = "SELECT 1 FROM " . DB_TABLE_TRANSLATION_GLOBAL . " 
              WHERE lower_compare_para = ? AND language_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    // to keep lower and remove tab and space and Quotes ', " for better matching
    $lower_text = normalize_text_for_comparison($text);
        // echo "Fetching existing translation with text: '$lower_text' and language_id: $language_id\n"; // Debug log

    $stmt->bind_param("si", $lower_text, $language_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

/**
 * Get existing translation from global table
 */
function get_existing_translation(object $conn, string $text, int $language_id): ?array
{
    $query = "SELECT translation_global_id, bhasha_para, ramanization_para, audio_location_bhasha 
              FROM " . DB_TABLE_TRANSLATION_GLOBAL . " 
              WHERE lower_compare_para = ? AND language_id = ? LIMIT 1";
     $stmt = $conn->prepare($query);
    if (!$stmt) {
        return null;
    }
    
    // to keep lower and remove tab and space
    $lower_text = normalize_text_for_comparison($text);
    $stmt->bind_param("si", $lower_text, $language_id);
    // print prepared query with parameters for debugging
 //   echo "Fetching existing translation with text: '$lower_text' and language_id: $language_id\n"; // Debug log
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $translation = $result->fetch_assoc();
        $stmt->close();
        return $translation;
    }
    
    $stmt->close();
    return null;
}

/**
 * Parse the Gemini API response to extract translation and romanization
 */
function parse_translation_response(string $response_text): ?array
{
    // Expected format:
    // Translated Text: [Translation]
    // Romanization: [Transliteration]
    
    $lines = array_filter(array_map('trim', explode("\n", $response_text)));
    $translation = null;
    $romanization = null;
    
    foreach ($lines as $line) {
        if (strpos($line, 'Translated Text:') === 0) {
            $translation = trim(substr($line, strlen('Translated Text:')));
        } elseif (strpos($line, 'Romanization:') === 0) {
            $romanization = trim(substr($line, strlen('Romanization:')));
        }
    }
    
    if ($translation && $romanization) {
        return [
            'bhasha_para' => $translation,
            'ramanization_para' => $romanization
        ];
    }
    
    return null;
}

/**
 * Generate audio using Google Text-to-Speech API
 * Returns array with audioContent and error info
 */
function generate_audio_via_google_tts(string $text, int $language_id): ?array
{
    $apiKey = GOOGLE_TTS_API_KEY;
    $languageCode = LANGUAGE_CODES[$language_id] ?? 'hi-IN';
    $url = GOOGLE_TTS_API_URL . "?key=" . $apiKey;
    
    $data = [
        "input" => [
            "text" => $text
        ],
        "voice" => [
            "languageCode" => $languageCode,
            "name" => $languageCode . "-Standard-A"
        ],
        "audioConfig" => [
            "audioEncoding" => "OGG_OPUS",
            "pitch" => 0.0,
            "speakingRate" => 1.0
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
        return ['error' => "CURL Error: $error"];
    }
    
    $result = json_decode($response, true);
    
    if ($http_code !== 200) {
        $error_msg = $result['error']['message'] ?? "HTTP $http_code";
        return ['error' => "TTS API Error: $error_msg"];
    }
    
    if (isset($result['audioContent'])) {
        return ['audioContent' => $result['audioContent']];
    }
    
    return ['error' => 'No audioContent in response'];
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
 * Save audio file and return the file path
 * Returns array with path and error info
 */
function save_audio_file(string $audioContent, int $translation_global_id): ?array
{
    if (!ensure_audio_directory_exists()) {
        return ['error' => 'Failed to create audio directory: ' . AUDIO_STORAGE_PATH];
    }
    
    $filename = $translation_global_id . ".ogg";
    $filepath = AUDIO_STORAGE_PATH . $filename;
    
    // Decode base64 audio content from API response
    $audioData = base64_decode($audioContent, true);
    
    if ($audioData === false) {
        return ['error' => 'Failed to decode audio content'];
    }
    
    if (!is_writable(dirname($filepath))) {
        return ['error' => 'Directory not writable: ' . dirname($filepath)];
    }
    
    $bytes_written = file_put_contents($filepath, $audioData);
    if ($bytes_written === false) {
        return ['error' => 'Failed to write audio file to: ' . $filepath];
    }
    
    if (!file_exists($filepath)) {
        return ['error' => 'Audio file not created: ' . $filepath];
    }
    
    // Return relative path for database storage
    return ['path' => 'assets/translation/' . $filename];
}

/**
 * Update translation with audio location
 */
function update_translation_audio_location(object $conn, int $translation_global_id, string $audio_location): bool
{
    $query = "UPDATE " . DB_TABLE_TRANSLATION_GLOBAL . " 
              SET audio_location_bhasha = ? 
              WHERE translation_global_id = ? LIMIT 1";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("si", $audio_location, $translation_global_id);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Insert user translation history
 */
function insert_user_history(object $conn, int $translation_global_id, int $user_id): bool
{
    $query = "INSERT INTO " . DB_TABLE_TRANSLATION_USER . " 
              (translation_global_id, bhasha_user_id, date_created) 
              VALUES (?, ?, CURRENT_TIMESTAMP)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ii", $translation_global_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Generate translation using Gemini API
 * Returns array with translation data or error info
 */
function generate_translation_via_api(string $text, string $language_name): ?array
{
    $apiKey = GEMINI_API_KEY;
    $url = GEMINI_API_URL . "?key=" . $apiKey;
    
    $prompt = "Translate '" . $text . "' into " . $language_name;
    $system_instruction = "Translate the provided text into the requested language and provide the romanization. Use a Formal and respectful way. Do not provide more detail. Be specific and output exactly in this format:\nTranslated Text: [Translation]\nRomanization: [Transliteration]";
    
    $data = [
        "systemInstruction" => [
            "parts" => [
                ["text" => $system_instruction]
            ]
        ],
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "maxOutputTokens" => 250,
            "responseMimeType" => "text/plain"
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
        return ['error' => "CURL Error: $error"];
    }
    
    if (empty($response)) {
        return ['error' => 'Empty response from Gemini API'];
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        return ['error' => "Failed to parse API response: $response"];
    }
    
    if ($http_code !== 200) {
        $error_msg = $result['error']['message'] ?? "HTTP $http_code";
        $error_details = $result['error'] ?? [];
        return ['error' => "Gemini API Error: $error_msg", 'api_details' => $error_details];
    }
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $translated_text = $result['candidates'][0]['content']['parts'][0]['text'];
        $parsed = parse_translation_response($translated_text);
        
        if (!$parsed) {
            return ['error' => "Failed to parse translation response: $translated_text"];
        }
        
        return $parsed;
    }
    
    return ['error' => "Missing expected fields in API response: " . json_encode($result)];
}

/**
 * Insert translation into global table and return the ID
 */
function insert_translation_to_global(object $conn, int $language_id, string $english_para, string $bhasha_para, string $ramanization_para, ?string $audio_location_bhasha = null): ?int
{
    $query = "INSERT INTO " . DB_TABLE_TRANSLATION_GLOBAL . " 
              (language_id, english_para, bhasha_para, ramanization_para, lower_compare_para, audio_location_bhasha, word_count, date_created) 
              VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return null;
    }
    
    $lower_text = normalize_text_for_comparison($english_para);
    $word_count = str_word_count($english_para); // OK for English only
    
    $stmt->bind_param("isssssi", $language_id, $english_para, $bhasha_para, $ramanization_para, $lower_text, $audio_location_bhasha, $word_count);
    
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    
    $translation_id = $conn->insert_id;
    $stmt->close();
    
    return $translation_id;
}

// ==================== MAIN LOGIC ====================

$input = json_decode(file_get_contents('php://input'), true);

$text = trim($input['text'] ?? '');
$language_id = trim($input['language_id'] ?? '');
$language_name = LANGUAGES[$language_id] ?? null;
$token = get_bearer_token($input);
$user_id = validate_token($conn, $token);
if (!$user_id) {    
    respond_and_exit(401, ['error' => 'Token Expired']);
    exit;
}
if (!$text) {
    respond_and_exit(400, ['error' => 'Text is mandatory']);
}

if (!$language_name) {
    respond_and_exit(400, ['error' => 'Language Name is mandatory']);
}
$exists_in_global = translation_exists_in_global($conn, $text, (int)$language_id);
// echo "Translation exists in global: " . ($exists_in_global ? "Yes" : "No") . "\n"; // Debug log
// Check if translation already exists in global table
if ($exists_in_global) {
    $existing = get_existing_translation($conn, $text, (int)$language_id);
    
    // Insert user history
    insert_user_history($conn, (int)$existing['translation_global_id'], $user_id);
    
    respond_and_exit(200, [
        'data' => [
            'source' => 'cache',
            'translation_global_id' => $existing['translation_global_id'],
            'bhasha_para' => $existing['bhasha_para'],
            'ramanization_para' => $existing['ramanization_para'],
            'audio_location_bhasha' => "https://www.desibhasha.com/".$existing['audio_location_bhasha']
        ]
    ]);
}

// Translation not found, generate via API
$translation_data = generate_translation_via_api($text, $language_name);

if (!$translation_data || isset($translation_data['error'])) {
    $error_msg = $translation_data['error'] ?? 'Failed to generate translation';
    $error_response = ['error' => $error_msg];
    
    // Include API details for debugging if available
    if (isset($translation_data['api_details'])) {
        $error_response['api_details'] = $translation_data['api_details'];
    }
    
    respond_and_exit(500, $error_response);
}

// Validate translation data has required fields
if (empty($translation_data['bhasha_para']) || empty($translation_data['ramanization_para'])) {
    respond_and_exit(500, [
        'error' => 'Invalid translation data structure',
        'details' => $translation_data
    ]);
}

// Insert the new translation into global table
$translation_id = insert_translation_to_global(
    $conn,
    (int)$language_id,
    $text,
    $translation_data['bhasha_para'],
    $translation_data['ramanization_para'],
    null
);

if (!$translation_id) {
    respond_and_exit(500, ['error' => 'Failed to save translation to database']);
}

// Generate audio for the translated text
$audio_result = generate_audio_via_google_tts($translation_data['bhasha_para'], (int)$language_id);
$audio_location = null;
$audio_error = null;

if (isset($audio_result['error'])) {
    $audio_error = $audio_result['error'];
} elseif (isset($audio_result['audioContent'])) {
    // Save audio file
    $save_result = save_audio_file($audio_result['audioContent'], $translation_id);
    
    if (isset($save_result['error'])) {
        $audio_error = $save_result['error'];
    } elseif (isset($save_result['path'])) {
        $audio_location = $save_result['path'];
        
        // Update database with audio location
        if (!update_translation_audio_location($conn, $translation_id, $audio_location)) {
            $audio_error = "Failed to update database with audio location";
        }
    }
}

// Return the newly generated translation
// Insert user history
insert_user_history($conn, $translation_id, $user_id);

respond_and_exit(200, [
    'data' => [
        'source' => 'generated',
        'translation_global_id' => $translation_id,
        'bhasha_para' => $translation_data['bhasha_para'],
        'ramanization_para' => $translation_data['ramanization_para'],
        'audio_location_bhasha' => "https://www.desibhasha.com/".$audio_location,
        'audio_error' => $audio_error
    ]
]);

?>