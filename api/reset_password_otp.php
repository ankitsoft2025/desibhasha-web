<?php

// Verify OTP



require_once 'utils.php';

header('Content-Type: application/json');

$conn = get_db_connection();

$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? '';

$otp = $input['otp'] ?? '';

$new_password = $input['password'] ?? '';

if (!$email || !$otp || !$new_password) {

	http_response_code(400);

	echo json_encode(['error' => 'Email, OTP, and password required']);

	exit;

}



$query = 'SELECT otp FROM otps WHERE email = ? and is_used = 0 and created_at > Now()-INTERVAL 15 MINUTE ORDER BY created_at DESC LIMIT 1';

// echo $query; // Print the query

$stmt_otp = $conn->prepare($query);

$stmt_otp->bind_param('s', $email);

$stmt_otp->execute();

$result_otp = $stmt_otp->get_result();

if ($row_otp = $result_otp->fetch_assoc()) {

	if ($row_otp['otp'] != $otp) {

		http_response_code(400);

		echo json_encode(['error' => 'Invalid OTP']);

		exit;

	}

	// OTP valid, update password

	$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

	$stmt = $conn->prepare('UPDATE bhasha_users SET is_email_verified = 1, password = ?  WHERE email_id = ?');

	$stmt->bind_param('ss', $password_hash, $email);

	if ($stmt->execute()) {

		mark_otp_done($conn, $email, $otp);		

		echo json_encode(['message' => 'User verified and password set']);

	} else {

		http_response_code(500);

		echo json_encode(['error' => 'Verification failed']);

	}

} else {

	http_response_code(400);

	echo json_encode(['error' => 'OTP not found']);

}

