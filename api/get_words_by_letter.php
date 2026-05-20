<?php
// Update profile: validate token, update name

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();
// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);

$user_id = validate_token($conn, $token);
if (!$user_id) {    
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}

$letter_id = $input['letter_id'] ?? '';
$is_trial=$input['is_trial'] ?? '1';

if(!$letter_id){
    http_response_code(400);
    echo json_encode(['error' => 'letter_id is mandatory']);
    exit;
}
$query = "select lw.letter_word_id, lw.word_in_bhasha, lw.common_romanization, lw.english_meaning, lw.image_location, lw.practice_document, lw.is_trial
            from letter_words lw
            where lw.letter_id = ?;";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $letter_id);
    $stmt->execute();
    $result = $stmt->get_result();  
    $letters = [];
    while ($row = $result->fetch_assoc()) {
        $letters[] = $row;
    }       
    echo json_encode(['data' => $letters]);  
    
$stmt->close(); 
$conn->close();
?>