<?php

// Reset password: validate token, old/new password
error_reporting(E_ALL);

ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once 'utils.php';

$conn = get_db_connection();

$input = json_decode(file_get_contents('php://input'), true);


$token = get_bearer_token();

$old_password = $input['old_password'] ?? '';

$new_password = $input['new_password'] ?? '';



$user_id = validate_token($conn, $token);

if (!$user_id) {

	http_response_code(401);

	echo json_encode(['error' => 'Token Expired']);

	exit;

}



$stmt = $conn->prepare('SELECT password FROM bhasha_users WHERE bhasha_user_id = ?');

$stmt->bind_param('i', $user_id);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

	if (!password_verify($old_password, $row['password'])) {

		http_response_code(400);

		echo json_encode(['error' => 'Old password incorrect']);

		exit;

	}

	$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

	$stmt2 = $conn->prepare('UPDATE bhasha_users SET password = ? WHERE bhasha_user_id = ?');

	$stmt2->bind_param('si', $new_hash, $user_id);

	if ($stmt2->execute()) {

		echo json_encode(['message' => 'Password updated']);

	} else {

		http_response_code(500);

		echo json_encode(['error' => 'Update failed']);

	}

} else {

	http_response_code(400);

	echo json_encode(['error' => 'Incorrect credentials. Please check and try again.']);

}

