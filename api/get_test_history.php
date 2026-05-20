<?php
require_once 'utils.php';

header('Content-Type: application/json');
function respond_and_exit(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function find_all_completed_test_for_user(mysqli $conn, int $user_id): array
{
    $stmt = $conn->prepare("SELECT tr.category_id, (select c.english_meaning from categories c where c.category_id=tr.category_id) as english_meaning, 
                        (select c.name_in_bhasha from categories c where c.category_id=tr.category_id) as name_in_bhasha, count(*) as total_tests, 
                        sum(tr.total_correct_answers) as total_correct_answers , sum(tr.total_wrong_answers) as total_wrong_answers
                        FROM test_results tr 
                        WHERE bhasha_user_id = ? AND date_completed IS NOT NULL 
                        group by tr.category_id");
    if (!$stmt) {
        respond_and_exit(500, ['error' => 'Failed to prepare completed test query', 'details' => $conn->error]);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tests = [];
    while ($row = $result->fetch_assoc()) {
        $tests[] = [
            'category_id' => (int)$row['category_id'],
            'english_meaning' => $row['english_meaning'],
            'name_in_bhasha' => $row['name_in_bhasha'],
            'total_tests' => (int)$row['total_tests'],
            'total_correct_answers' => (int)$row['total_correct_answers'],
            'total_wrong_answers' => (int)$row['total_wrong_answers'],
        ];
    }
    $stmt->close();
    return $tests;
}


function find_completed_test(mysqli $conn, int $user_id, int $category_id): array
{
    $stmt = $conn->prepare("SELECT * FROM test_results WHERE bhasha_user_id = ? AND category_id = ? AND date_completed IS NOT NULL ORDER BY date_completed DESC");
    if (!$stmt) {
        respond_and_exit(500, ['error' => 'Failed to prepare completed test query', 'details' => $conn->error]);
    }
    $stmt->bind_param("ii", $user_id, $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tests = [];
    while ($row = $result->fetch_assoc()) {
        $tests[] = [
            'test_result_id' => (int)$row['test_result_id'],
            'total_questions' => (int)$row['total_questions'],
            'total_correct_answers' => (int)$row['total_correct_answers'],
            'total_wrong_answers' => (int)$row['total_wrong_answers'],
            'date_created' => $row['date_created'],
            'date_completed' => $row['date_completed'],
        ];
    }
    $stmt->close();
    return $tests;
}

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

$conn = get_db_connection();
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);
$user_id = validate_token($conn, $token);
$category_id = trim($input['category_id'] ?? '');
$test_result_id = trim($input['test_result_id'] ?? '');

if (!$user_id) {
    respond_and_exit(401, ['error' => 'Token Expired']);
}

//either category_id or test_result_id should be provided to fetch the test history
//if ($category_id === '' && $test_result_id === '') {
//    respond_and_exit(400, ['error' => 'Either category_id or test_result_id must be provided']);
//}

if($test_result_id !== ''){
    $test_result_id = (int)$test_result_id;
    $response = fetch_test_completion_data($conn, $test_result_id);
}
elseif ($category_id !== ''){
    $category_id = (int)$category_id;
    $response = find_completed_test($conn, (int)$user_id, $category_id);
}
else {
    $response =  find_all_completed_test_for_user($conn, $user_id);
//    $category_id = (int)$category_id;
//    $response = find_completed_test($conn, (int)$user_id, $category_id);
}

respond_and_exit(200, ['data' => $response]);


?>