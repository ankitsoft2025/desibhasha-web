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

$chapter_id = $input['chapter_id'] ?? '';
$language_id = $input['language_id'] ?? '';
$is_trial=$input['is_trial'] ?? '1';
if(!$chapter_id || !$language_id){
    http_response_code(400);
    echo json_encode(['error' => 'chapter_id and language_id are mandatory']);
    exit;
}
$query = "
select l.letter_id,l.letter_in_bhasha, l.common_romanization, l.english_equivelant_sound, l.image_id,l.practice_document_id
from letters l
where l.chapter_id = ? and l.language_id=?
and l.is_trial = ?
order by l.display_order";

$stmt = $conn->prepare($query);
$stmt->bind_param('sss', $chapter_id, $language_id, $is_trial);
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