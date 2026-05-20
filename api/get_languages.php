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
$query="SELECT language_id, name_in_english, name_in_bhasha, date_created
        FROM languages where language_id < 100;";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();  
$languages = [];
while ($row = $result->fetch_assoc()) {
    $languages[] = $row;
}       
echo json_encode(['data' => $languages]);   
$stmt->close(); 
$conn->close();
?>