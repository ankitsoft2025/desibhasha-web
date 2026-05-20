<?php

 //error_reporting(E_ALL);
 //ini_set('display_errors', 1);
// error_reporting(0);
// ini_set('display_errors', 0);

// Signup: first_name, email mandatory, send OTP Email

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$first_name = ucfirst($input['first_name']) ?? '';
$email = strtolower($input['email_id']) ?? '';
$password = $input['password'] ?? 'dummy';
$fcm_key = $input['fcm_key'] ?? '';
$phone = $input['phone'] ?? '';
$last_name = ucfirst($input['last_name']) ?? '';
$gender = $input['gender'] ?? '';
$account_type = $input['account_type'] ?? 'U';
$country_code = $input['country_code'] ?? '';
$country_dial_code = $input['country_dial_code'] ?? '+1';

$refer_code = $input['refer_code'] ?? '';

if (!$first_name || !$email || !$phone) {
	http_response_code(400);
	echo json_encode(['error' => 'First name, email, and phone are mandatory']);
	exit;
}
$stmt = $conn->prepare('SELECT bhasha_user_id FROM `mqyvhbte_desibhasha`.`bhasha_users` WHERE email_id = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
	http_response_code(409);
	echo json_encode(['error' => 'User already exists with this email']);
	exit;
}
$stmt->close();

$stmt = $conn->prepare('SELECT bhasha_user_id FROM bhasha_users WHERE phone = ? AND country_code = ?');
$stmt->bind_param('ss', $phone, $country_code);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
	http_response_code(409);
	echo json_encode(['error' => 'User already exists with this phone number']);
	exit;
}
$stmt->close();

$otp = generate_otp($email);
$password_hash = password_hash($password, PASSWORD_DEFAULT); // Set dummy password until verified
$stmt = $conn->prepare('INSERT INTO bhasha_users
    (fcm_key, account_type, phone, email_id, first_name, last_name, gender, password, country_code, country_dial_code)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('ssssssssss', $fcm_key, $account_type, $phone, $email, $first_name, $last_name, $gender, $password_hash, $country_code, $country_dial_code);

if ($stmt->execute()) {
	// Store OTP in DB
	$stmt_otp = $conn->prepare('INSERT INTO otps (email, otp, event_name) VALUES (?, ?, ?)');
	$event_name = 'signup';
	$stmt_otp->bind_param('sss', $email, $otp, $event_name);
	$stmt_otp->execute();
	// Send OTP via mail (mock for testuser)
	send_otp_mail($email, $otp);
	
	if(!$refer_code or $refer_code == '') {
	$refer_code = get_refercode_from_download_request($conn, $email);
	}
	// If refer_code provided, add entry to referred_users
	if ($refer_code) {
		$new_user_id = $stmt->insert_id;
		add_refer_bhasha_money($conn, $new_user_id, $refer_code);
	}
	echo json_encode(['message' => 'OTP sent to email']);
} else {
	http_response_code(500);
	echo json_encode(['error' => 'Signup failed']);
}
