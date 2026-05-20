<?php
// Generic file upload service with validation for images, pdf, csv, xlsx
require_once 'utils.php';

//  error_reporting(E_ALL);
//  ini_set('display_errors', 1);
header('Content-Type: application/json');
$token = $input['token'] ?? '';
$conn = get_db_connection();
// $user_id = validate_token($token);
// if (!$user_id) {
// 	http_response_code(401);
// 	echo json_encode(['error' => 'Token Expired']);
// 	exit;
// }
$user_id=1;
function is_valid_file($file_name, $allowed_types) {
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    return in_array($ext, $allowed_types);
}

function handle_file_upload($field, $allowed_types = ['jpg','jpeg','png','pdf','csv','xlsx']) {
    global $conn;
    $response = [];
    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    if (!isset($_FILES[$field])) return $response;
    $files = $_FILES[$field];
    // Normalize files into an array of items for consistent handling
    $items = [];
    // print_r("ddfdsfds");
    // print_r($files);
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            // Skip entries with empty name (can happen)
            if (empty($files['name'][$i])) continue;
            $items[] = [
                'name' => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
        }
    } else {
        if (!empty($files['name'])) {
            $items[] = [
                'name' => $files['name'],
                'tmp_name' => $files['tmp_name'],
                'error' => $files['error'],
                'size' => $files['size']
            ];
        }
    }

    foreach ($items as $file) {
        // basic upload error check
        if ($file['error'] !== UPLOAD_ERR_OK) continue;
        $original_name = $file['name'];
        if (!is_valid_file($original_name, $allowed_types)) continue;

        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $base = pathinfo($original_name, PATHINFO_FILENAME);
        // generate a unique stored filename with suffix to avoid overwrites
        $unique_suffix = uniqid('_', true);
        // sanitize base name to remove problematic characters
        $safe_base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
        $stored_name = $safe_base . $unique_suffix . '.' . $ext;
        $target_path = $upload_dir . $stored_name;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $stmt = $conn->prepare('INSERT INTO upload_files (file_name, original_file_name, user_id) VALUES (?, ?, ?)');
            // store the stored filename in DB; return original in response
            $stmt->bind_param('ssi', $stored_name, $original_name, $user_id);
            $stmt->execute();
            $file_id = $conn->insert_id;
            $response[] = ['id' => $file_id, 'original_name' => $original_name, 'stored_name' => $stored_name];
        }
    }
    return $response;
}

// Usage example for API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process any uploaded fields that start with "files" (files, files[], files_1, files_2, etc.)
    function handle_all_uploads($allowed_types = ['jpg','jpeg','png','pdf','csv','xlsx']) {
        $all = [];
        foreach ($_FILES as $field => $value) {
            // match fields like files, files[], files_1, files_2, file1, file_foo
            if (preg_match('/^files?(?:\[.*\]|_.*|\d*)$/', $field)) {
                $all = array_merge($all, handle_file_upload($field, $allowed_types));
            }
        }
        return $all;
    }
    $all = handle_all_uploads();
    echo json_encode($all);
}
else{
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}