<?php
// Update profile: validate token, update name

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();
// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token();
$first_name = $input['first_name'] ?? '';
$last_name = $input['last_name'] ?? '';

$user_id = validate_token($conn, $token);
if (!$user_id) {
	http_response_code(401);
	echo json_encode(['error' => 'Token Expired']);
	exit;
}

// fetch existig user details if not provided  use existing one in update
if (empty($last_name)) {
	$stmt = $conn->prepare('SELECT last_name FROM bhasha_users WHERE bhasha_user_id = ?');
	$stmt->bind_param('i', $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	if ($row = $result->fetch_assoc()) {
		$last_name = $row['last_name'];
	} else {
		$last_name = '';
	}
}
if (empty($first_name)) {
	$stmt = $conn->prepare('SELECT first_name FROM bhasha_users WHERE bhasha_user_id = ?');
	$stmt->bind_param('i', $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	if ($row = $result->fetch_assoc()) {
		$first_name = $row['first_name'];
	} else {
		$first_name = '';
	}
}
$stmt = $conn->prepare('UPDATE bhasha_users SET first_name = ?, last_name = ? WHERE bhasha_user_id = ?');
$stmt->bind_param('ssi', $first_name, $last_name, $user_id);
if ($stmt->execute()) {
	echo json_encode(['message' => 'Profile updated']);
} else {
	http_response_code(500);
	echo json_encode(['error' => 'Update failed']);
}
