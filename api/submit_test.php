<?php
// input json sample: { "test_result_id": 1 }

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
$test_result_id = trim($input['test_result_id'] ?? '');

//validate test_result_id to be mandatory not empty
if (empty($test_result_id)) {
    respond_and_exit(400, ['error' => 'test_result_id is required and cannot be empty']);
}

$stmt = $conn->prepare("SELECT test_result_id FROM test_results WHERE test_result_id = ? AND bhasha_user_id = ? AND date_completed IS NULL");
if (!$stmt) {
    respond_and_exit(500, ['error' => 'Failed to prepare test result query', 'details' => $conn->error]);
}   
$stmt->bind_param("ii", $test_result_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    respond_and_exit(404, ['error' => 'Active test result not found']);
}
//update test_detail result column related to test_id
$update_detail_stmt = $conn->prepare("UPDATE test_details SET result = (selected_option = correct_answer_option) WHERE test_result_id = ?");
if (!$update_detail_stmt) {
    respond_and_exit(500, ['error' => 'Failed to prepare update detail query', 'details' => $conn->error]);
}
$update_detail_stmt->bind_param("i", $test_result_id);
$update_detail_stmt->execute();
if ($update_detail_stmt->affected_rows === 0) {
    respond_and_exit(500, ['error' => 'Failed to update test details']);
}
// update test_results to set date_completed, total_correct_answers and total_wrong_answers based on result in test_details
$update_result_stmt = $conn->prepare("UPDATE test_results tr JOIN (SELECT test_result_id, SUM(result) AS total_correct_answers, COUNT(*) - SUM(result) AS total_wrong_answers FROM test_details WHERE test_result_id = ? GROUP BY test_result_id) td ON tr.test_result_id = td.test_result_id SET tr.total_correct_answers = td.total_correct_answers, tr.total_wrong_answers = td.total_wrong_answers, date_completed=NOW() WHERE tr.test_result_id = ?");
if (!$update_result_stmt) {
    respond_and_exit(500, ['error' => 'Failed to prepare update result query', 'details' => $conn->error]);
}   
$update_result_stmt->bind_param("ii", $test_result_id, $test_result_id);
$update_result_stmt->execute();
if ($update_result_stmt->errno !== 0) {
    respond_and_exit(500, ['error' => 'Failed to update test results', 'details' => $update_result_stmt->error]);
}
$update_result_stmt->close();
// in data return complete test details with result for each question and total correct and wrong answers

function fetch_test_completion_data(mysqli $conn, int $test_result_id): array
{
    $stmt = $conn->prepare("SELECT * FROM test_results WHERE test_result_id = ?");
    if (!$stmt) {
        respond_and_exit(500, ['error' => 'Failed to prepare test result fetch query', 'details' => $conn->error]);
    }
    $stmt->bind_param("i", $test_result_id);
    $stmt->execute();
    $active_test = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'test_result_id' => (int)$active_test['test_result_id'],
        'total_questions' => (int)$active_test['total_questions'],
        'total_correct_answers' => (int)$active_test['total_correct_answers'],
        'total_wrong_answers' => (int)$active_test['total_wrong_answers'],
        'date_created' => $active_test['date_created'],
        'date_completed' => $active_test['date_completed'],
        'questions' => fetch_saved_questions($conn, (int)$active_test['test_result_id']),
    ];
}

function fetch_saved_questions(mysqli $conn, int $test_result_id): array
{
    $stmt = $conn->prepare("SELECT question, option1, option1_audio_location, option2, option2_audio_location, option3, option3_audio_location, option4, option4_audio_location, selected_option, result, correct_answer_option FROM test_details WHERE test_result_id = ?");
    if (!$stmt) {
        respond_and_exit(500, ['error' => 'Failed to prepare test_details fetch query', 'details' => $conn->error]);
    }
    $stmt->bind_param("i", $test_result_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = [
            'question' => $row['question'],
            'options' => [$row['option1'], $row['option2'], $row['option3'], $row['option4']],
            'option_audio_locations' => [$row['option1_audio_location'], $row['option2_audio_location'], $row['option3_audio_location'], $row['option4_audio_location']],
            'selected_option' => $row['selected_option'],
            'result' => $row['result'],
            'correct_answer_option' => $row['correct_answer_option'],
        ];

    }
    $stmt->close();
    return $questions;
}

$response = fetch_test_completion_data($conn, $test_result_id);

respond_and_exit(200, ['message' => 'Test completed successfully', 'data' => $response]);
$conn->close();
?>