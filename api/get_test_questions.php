<?php

require_once 'utils.php';

header('Content-Type: application/json');

function respond_and_exit(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function prepare_or_fail(mysqli $conn, string $sql, string $error): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond_and_exit(500, ['error' => $error, 'details' => $conn->error]);
    }
    return $stmt;
}

function find_active_test(mysqli $conn, int $user_id, int $category_id): ?array
{
    $sql = "SELECT * FROM test_results WHERE bhasha_user_id = ? AND category_id = ? AND date_completed IS NULL";

    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond_and_exit(500, ['error' => 'Failed to prepare active test query', 'details' => $conn->error]);
    }
    $stmt->bind_param("ii", $user_id, $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
    }

function fetch_saved_questions(mysqli $conn, int $test_result_id): array
{
    $stmt = prepare_or_fail(
        $conn,
        "SELECT question, 
            option1, 
            CASE
                WHEN option1_audio_location is not null THEN concat('https://www.desibhasha.com/', option1_audio_location)
                ELSE option1_audio_location
            END AS option1_audio_location, 
            option2, 
            CASE
                WHEN option2_audio_location is not null THEN concat('https://www.desibhasha.com/', option2_audio_location)
                ELSE option2_audio_location
            END AS option2_audio_location, 
            option3, 
            CASE
                WHEN option3_audio_location is not null THEN concat('https://www.desibhasha.com/', option3_audio_location)
                ELSE option3_audio_location
            END AS option3_audio_location, 
            option4, 
            CASE
                WHEN option4_audio_location is not null THEN concat('https://www.desibhasha.com/', option4_audio_location)
                ELSE option4_audio_location
            END AS option4_audio_location, 
            selected_option, test_detail_id FROM test_details WHERE test_result_id = ?",
        'Failed to prepare test_details fetch query'
    );
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
            'test_detail_id' => (int)$row['test_detail_id'],
        ];
    }

    $stmt->close();
    return $questions;
}

function fetch_category_words(mysqli $conn, int $category_id): array
{
    $stmt = prepare_or_fail(
        $conn,
        "SELECT general_word_id AS id, english_meaning AS question, word_in_bhasha AS answer, audio_location FROM general_words WHERE category_id = ? ORDER BY RAND() LIMIT 50",
        'Failed to prepare general_words query'
    );
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_counting(mysqli $conn, int $category_id): array
{
    $stmt = prepare_or_fail(
        $conn,
        "SELECT counting_id AS id, number_in_english AS question, number_in_bhasha AS answer, audio_location FROM counting WHERE category_id = ? ORDER BY RAND() LIMIT 50",
        'Failed to prepare counting query'
    );
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function build_questions(array $rows): array
{
    // $no_of_questions = count($rows) > 10 ? 10 : 5;
    $no_of_questions = 5;
    $option_size = 3;
    $questions = [];

    foreach ($rows as $row) {
        $distractors = [];
        foreach ($rows as $candidate) {
            if ((int)$candidate['id'] === (int)$row['id']) {
                continue;
            }
            if ($candidate['answer'] === $row['answer']) {
                continue;
            }
            $distractors[$candidate['answer']] = $candidate['audio_location'];
        }

        $distractorItems = [];
        foreach ($distractors as $text => $audio_location) {
            $distractorItems[] = [
                'text' => $text,
                'audio_location' => $audio_location,
            ];
        }

        shuffle($distractorItems);
        $options = [];
        $optionAudioLocations = [];
        foreach (array_slice($distractorItems, 0, $option_size) as $item) {
            $options[] = $item['text'];
            $optionAudioLocations[] = $item['audio_location'];
        }

        $options[] = $row['answer'];
        $optionAudioLocations[] = $row['audio_location'];

        $combinedOptions = [];
        foreach ($options as $index => $optionText) {
            $combinedOptions[] = [
                'text' => $optionText,
                'audio_location' => $optionAudioLocations[$index] ?? null,
            ];
        }

        shuffle($combinedOptions);

        $questions[] = [
            'question' => $row['question'],
            'answer' => $row['answer'],
            'options' => array_column($combinedOptions, 'text'),
            'option_audio_locations' => array_column($combinedOptions, 'audio_location'),
        ];

        if (count($questions) >= $no_of_questions) {
            break;
        }
    }

    return $questions;
}

function create_test_result(mysqli $conn, int $user_id, int $category_id, int $total_questions): int
{
    $stmt = prepare_or_fail(
        $conn,
        "INSERT INTO test_results (bhasha_user_id, category_id, total_questions, total_correct_answers, total_wrong_answers, date_created) VALUES (?, ?, ?, ?, ?, NOW())",
        'Failed to prepare test_result insert query'
    );
    $total_correct_answers = 0;
    $total_wrong_answers = 0;
    $stmt->bind_param("iiiii", $user_id, $category_id, $total_questions, $total_correct_answers, $total_wrong_answers);
    $stmt->execute();
    $test_result_id = (int)$stmt->insert_id;
    $stmt->close();

    return $test_result_id;
}

function save_test_questions(mysqli $conn, int $test_result_id, array $questions): void
{
    $stmt = prepare_or_fail(
        $conn,
        "INSERT INTO test_details (test_result_id, question, option1, option1_audio_location, option2, option2_audio_location, option3, option3_audio_location, option4, option4_audio_location, correct_answer_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'Failed to prepare test_details insert query'
    );

    foreach ($questions as $question) {
        $stmt->bind_param(
            "issssssssss",
            $test_result_id,
            $question['question'],
            $question['options'][0],
            $question['option_audio_locations'][0],
            $question['options'][1],
            $question['option_audio_locations'][1],
            $question['options'][2],
            $question['option_audio_locations'][2],
            $question['options'][3],
            $question['option_audio_locations'][3],
            $question['answer']
        );
        $stmt->execute();
    }

    $stmt->close();
}

$conn = get_db_connection();
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);

$user_id = validate_token($conn, $token);
$category_id = trim($input['category_id'] ?? '');

if (!$user_id) {
    respond_and_exit(401, ['error' => 'Token Expired']);
}

if ($category_id === '' || !is_numeric($category_id)) {
    respond_and_exit(400, ['error' => 'Invalid category_id']);
}

$category_id = (int)$category_id;
$active_test = find_active_test($conn, (int)$user_id, $category_id);

if ($active_test !== null) {
    $response = [
        'test_result_id' => (int)$active_test['test_result_id'],
        'total_questions' => (int)$active_test['total_questions'],
        'total_correct_answers' => (int)$active_test['total_correct_answers'],
        'total_wrong_answers' => (int)$active_test['total_wrong_answers'],
        'date_created' => $active_test['date_created'],
        'questions' => fetch_saved_questions($conn, (int)$active_test['test_result_id']),
    ];
    echo json_encode($response);
    $conn->close();
    exit;
}
if ($category_id>200) 
    $rows = fetch_category_words($conn, $category_id);
else
$rows = fetch_counting($conn, $category_id);
$questions = build_questions($rows);
$test_result_id = create_test_result($conn, (int)$user_id, $category_id, count($questions));
save_test_questions($conn, $test_result_id, $questions);

$response = [
    'test_result_id' => $test_result_id,
    'total_questions' => count($questions),
    'total_correct_answers' => 0,
    'total_wrong_answers' => 0,
    'date_created' => date("Y-m-d H:i:s"),
    'questions' => fetch_saved_questions($conn, (int)$test_result_id)
];

echo json_encode($response);
$conn->close();

?>
