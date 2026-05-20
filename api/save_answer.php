<?php
// This endpoint is responsible for saving the user's selected answer for a specific test question.
// input json sample: { "test_detail_id": 123, "selected_option": "Monday" }

require_once 'utils.php';

header('Content-Type: application/json');

function respond_and_exit(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$conn = get_db_connection();
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);

$user_id = validate_token($conn, $token);
if (!$user_id) {
    respond_and_exit(401, ['error' => 'Token Expired']);
}

$test_detail_id = trim($input['test_detail_id'] ?? '');
$selected_option = trim($input['selected_option'] ?? '');

//validate both input to be mandatory not empty
if (empty($test_detail_id) || empty($selected_option)) {
    respond_and_exit(400, ['error' => 'Both test_detail_id and selected_option are required and cannot be empty']);
}

//validate test_detail id present in database and corresponding test is active
$stmt = $conn->prepare("SELECT td.test_detail_id, td.test_result_id, tr.bhasha_user_id FROM test_details td JOIN test_results tr ON td.test_result_id = tr.test_result_id WHERE td.test_detail_id = ? AND tr.date_completed IS NULL");
if (!$stmt) {
    respond_and_exit(500, ['error' => 'Failed to prepare test detail query', 'details' => $conn->error]);
}       
$stmt->bind_param("i", $test_detail_id); 
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    respond_and_exit(404, ['error' => 'Test detail not found or test is already completed']);
}
$row = $result->fetch_assoc();
if ($row['bhasha_user_id'] !== $user_id) {
    respond_and_exit(403, ['error' => 'Unauthorized to update this test detail']);
}
//update selected option for the test detail
$update_stmt = $conn->prepare("UPDATE test_details SET selected_option = ? WHERE test_detail_id = ?");
if (!$update_stmt) {
    respond_and_exit(500, ['error' => 'Failed to prepare update query', 'details' => $conn->error]);
}           
$update_stmt->bind_param("si", $selected_option, $test_detail_id);
$update_stmt->execute();
if (!$update_stmt) {
    respond_and_exit(500, ['error' => 'Failed to update test detail']);
}
respond_and_exit(200, ['message' => 'Test detail updated successfully']);
?>