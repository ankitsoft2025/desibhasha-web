<?php
// Login: validate app version, expire old token, create new
require_once 'utils.php';
header('Content-Type: application/json');
require_once 'utils.php';

$conn = get_db_connection();
// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$password = $input['Password'] ?? '';
$otp = $input['Otp'] ?? '';
$email = $input['Username'] ?? '';

$platform = $input['DeviceType'] ?? 'android';
$version = $input['DeviceVersion'] ?? '0.9.0';
$device_key = $input['DeviceKey'] ?? '';


if (!check_app_version($conn, $platform, $version)) {
	http_response_code(426);
	echo json_encode([
		'error' => 'App version outdated. Please update to continue.',
		'updateUrl' => 'https://play.google.com/store/apps/details?id=com.example'
	]);
	exit;
}

$query = 'SELECT bhasha_user_id, first_name, last_name, password, is_email_verified FROM bhasha_users WHERE email_id = ?';
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $email);
// echo $query;
// echo $email;

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
if ($row = $result->fetch_assoc()) {
	// Verify password
	if($password!='' && $password!=null)
	{
		if (!password_verify($password, $row['password'])) {
			$conn->close();
		http_response_code(401);
		echo json_encode(['error' => 'Incorrect Password. Please check and try again.']);
		exit;
	    }
	}
	else if($otp!='' && $otp!=null)
	{
	
		$stmt_otp = $conn->prepare('SELECT otp, event_name FROM otps WHERE email = ? and is_used = 0 and created_at > Now()-INTERVAL 15 MINUTE ORDER BY created_at DESC LIMIT 1');
		$stmt_otp->bind_param('s', $email);
		$stmt_otp->execute();
	    $result_otp = $stmt_otp->get_result();
	    $stmt_otp->close();
	    if ($row_otp = $result_otp->fetch_assoc()) {
		    if ($row_otp['otp'] != $otp) {
			    $conn->close();
			    http_response_code(401);
			    echo json_encode(['error' => 'Incorrect OTP']);
			    exit;
		    } 
		    else{
			$event_name = $row_otp['event_name'];
			mark_otp_done($conn, $email, $otp);
			
			$stmt_update = $conn->prepare('UPDATE bhasha_users SET is_email_verified = 1 WHERE email_id = ?');
			$stmt_update->bind_param('s', $email);
			$stmt_update->execute();
			$stmt_update = $conn->prepare('UPDATE bhasha_money_transactions bmt JOIN bhasha_users bu ON bmt.referred_to = bu.bhasha_user_id SET bmt.bhasha_money_type = "RF" WHERE bu.email_id = ? AND bmt.bhasha_money_type="RFU"');
			$stmt_update->bind_param('s', $email);
			$stmt_update->execute();
			$stmt_update->close();
				 
	        }
	    }
	    else{
	    $conn->close();
			http_response_code(401);
			echo json_encode(['error' => 'Incorrect OTP']);
			exit;
	    }
    }
	else
	{
		http_response_code(400);
		echo json_encode(['error' => 'Password or OTP required']);
		exit;
	}
	
	// Expire old tokens
	$conn->query('DELETE FROM tokens WHERE user_id = ' . intval($row['bhasha_user_id']));
	// Create new token
	$token = bin2hex(random_bytes(32));
	$stmt2 = $conn->prepare('INSERT INTO tokens (user_id, token) VALUES (?, ?)');
	$stmt2->bind_param('is', $row['bhasha_user_id'], $token);
	$stmt2->execute();
	echo json_encode([
		'data' => [
			'token' => $token,
			'user' => [
				'bhasha_user_id' => $row['bhasha_user_id'],
				'first_name' => $row['first_name'],
				'last_name' => $row['last_name']
			],
			'is_verified' => $row['is_email_verified']?true:false
		],
		'message' => 'Login successful'
	]);
} else {
	$conn->close();
	http_response_code(401);
	echo json_encode(['error' => 'Incorrect credentials. Please check and try again.']);
}
