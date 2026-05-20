<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Get the user_id from the token
$token = get_bearer_token();
$user_id = validate_token($conn, $token);


$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$user_email = trim($input['user_email'] ?? $input['email'] ?? '');
$name = trim($input['name'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

// basic validation
if (!$user_email || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid user_email is required']);
    exit;
}
if (!$subject) {
    http_response_code(400);
    echo json_encode(['error' => 'Subject is required']);
    exit;
}
if (!$message) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}
if (!$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required']);
    exit;
}


// insert into contact_us

$stmt = $conn->prepare('INSERT INTO contact_us (user_email, subject, name, message) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $user_email, $subject, $name, $message);
if ($stmt->execute()) {
    $id = $conn->insert_id;
    echo json_encode(['contact_us_id' => $id, 'user_email' => $user_email, 'subject' => $subject, 'name' => $name, 'message' => $message]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save contact message']);
}

