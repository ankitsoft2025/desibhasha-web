<?php
// Verify OTP

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$otp = $input['otp'] ?? '';
if (!$email || !$otp) {
	http_response_code(400);
	echo json_encode(['error' => 'Email and OTP required.']);
	exit;
}

$stmt_otp = $conn->prepare('SELECT otp, event_name FROM otps WHERE email = ? and is_used = 0 and created_at > Now()-INTERVAL 15 MINUTE ORDER BY created_at DESC LIMIT 1');
$stmt_otp->bind_param('s', $email);
$stmt_otp->execute();
$result_otp = $stmt_otp->get_result();
if ($row_otp = $result_otp->fetch_assoc()) {
	if ($row_otp['otp'] != $otp) {
		http_response_code(401);
		echo json_encode(['error' => 'Incorrect OTP.']);
		exit;
	} else{
		$event_name = $row_otp['event_name'];
		mark_otp_done($conn, $email, $otp);
		if($event_name == 'signup')
		{
			$stmt_update = $conn->prepare('UPDATE bhasha_users SET is_email_verified = 1 WHERE email_id = ?');
			$stmt_update->bind_param('s', $email);
			$stmt_update->execute();
			$stmt_update = $conn->prepare('UPDATE bhasha_money_transactions bmt JOIN bhasha_users bu ON bmt.referred_to = bu.bhasha_user_id SET bmt.bhasha_money_type = "RF" WHERE bu.email_id = ? AND bmt.bhasha_money_type="RFU"');
			$stmt_update->bind_param('s', $email);
			$stmt_update->execute();
			echo json_encode(['message' => 'User verified.']);
		} 
	}
} else {
	http_response_code(401);
	echo json_encode(['error' => 'OTP not found.']);
}
