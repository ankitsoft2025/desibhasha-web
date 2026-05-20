<?php
$assets_dir = "../assets/audio_files_android/";
// add file upload functionality here
require_once 'utils.php';
header('Content-Type: application/json');
function respond_and_exit(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['audio_file'])) {
        respond_and_exit(400, ['error' => 'Missing audio file']);
    }

    $file = $_FILES['audio_file'];
    $requested_file_name = trim($_POST['file_name'] ?? '');
    $original_file_name = $file['name'] ?? '';
    $file_name = basename($requested_file_name !== '' ? $requested_file_name : $original_file_name);

    if ($file_name === '') {
        respond_and_exit(400, ['error' => 'Unable to determine file name']);
    }

    $target_path = $assets_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        respond_and_exit(200, ['message' => 'File uploaded successfully', 'file_name' => $file_name]);
    } else {
        respond_and_exit(500, ['error' => 'Failed to move uploaded file']);
    }
} else {
    respond_and_exit(405, ['error' => 'Method not allowed']);
}
?>