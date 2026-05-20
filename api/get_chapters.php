<?php
// Update profile: validate token, update name

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();
// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);

// return is_active plans when language_id is '' or not provided then all languages plans
$user_id = validate_token($conn, $token);
if (!$user_id) {    
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}

$teaching_level_id = $input['teaching_level_id'] ?? '';
$language_id = $input['language_id'] ?? '';
$category=$input['category'] ?? 'alphabets';

if(!$teaching_level_id || !$language_id){
    http_response_code(400);
    echo json_encode(['error' => 'teaching_level_id and language_id are mandatory']);
    exit;
}
if($category =='alphabets'){
    $query="select DISTINCT c.chapter_id, c.description    
    from chapters as c, letters as l
    where c.chapter_id = l.chapter_id
    and c.teaching_level_id = ?
    and l.language_id= ?
    order by c.chapter_id asc;";
}


$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $teaching_level_id, $language_id);
$stmt->execute();
$result = $stmt->get_result();  
$chapters = [];
while ($row = $result->fetch_assoc()) {
    $chapters[] = $row;
}       
echo json_encode(['data' => $chapters]);   
$stmt->close(); 
$conn->close();
?>