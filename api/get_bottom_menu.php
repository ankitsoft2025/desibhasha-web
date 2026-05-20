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

    if(!$teaching_level_id){
        http_response_code(400);
        echo json_encode(['error' => 'TeachingLevelId is mandatory.']);
        exit;
    }

    $query="select bottom_menu_id, menu_name    
            from bottom_menus
            where teaching_level_id = ?
            order by bottom_menu_id;";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $teaching_level_id);
    $stmt->execute();
    $result = $stmt->get_result();  
    $bottom_menus = [];
    while ($row = $result->fetch_assoc()) {
        $bottom_menus[] = $row;
    }       
    echo json_encode(['data' => $bottom_menus]);   

$stmt->close(); 
$conn->close();

?>