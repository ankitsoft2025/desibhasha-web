<?php
// Get OTP: generate, store, send mail
require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();
// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$event_name = $input['event_name'] ?? 'signup'; // default to signup
if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Email required']);
    exit;
}
// check user exist in bhasha_users table
$stmt = $conn->prepare('SELECT bhasha_user_id FROM `mqyvhbte_desibhasha`.`bhasha_users` WHERE email_id = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}
$otp = generate_otp($email);
$stmt_insert = $conn->prepare('INSERT INTO otps (email, otp, event_name) VALUES (?, ?, ?)');
$stmt_insert->bind_param('sis', $email, $otp, $event_name);
$stmt_insert->execute();
if (send_otp_mail($email, $otp)) {
    echo json_encode(['message' => 'OTP sent']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send OTP']);
}
?>