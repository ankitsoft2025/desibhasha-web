<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

require_once 'utils.php';

header('Content-Type: application/json');

$conn = get_db_connection();

//$token = get_bearer_token();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {

    http_response_code(400);

    echo json_encode(['error' => 'Invalid JSON']);

    exit;

}



// Generate user_id from token

//$user_id = validate_token($conn, $token);

//if (!$user_id) {    

//    http_response_code(401);

//    echo json_encode(['error' => 'Token Expired']);

//    exit;

//}



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

//$attachment_json = $attachments_arr ? json_encode($attachments_arr) : null;

$stmt = $conn->prepare('INSERT INTO contact_us (user_email, subject, name, message) VALUES (?, ?, ?, ?)');

$stmt->bind_param('ssss', $user_email, $subject, $name, $message);

if ($stmt->execute()) {

    $id = $conn->insert_id;

    echo json_encode(['message'=>'Your request has been saved, our team will connect shortly']);

} else {

    http_response_code(500);

    echo json_encode(['error' => 'Failed to save contact message']);

}



