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

$category_id = $input['category_id'] ?? '';
$teaching_level_id = $input['teaching_level_id'] ?? '';
$language_id = $input['language_id'] ?? '';

        $query="SELECT letter_id, letter_in_bhasha, common_romanization, approximate_pronunciation, english_equivelant_sound, 
                image_id, practice_document_id, is_trial 
            from letters 
            where category_id = ? 
            order by display_order;";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $letters_submenu_data = [];
        while ($row = $result->fetch_assoc()) 
        {
            $letters_submenu_data[] = $row;
        }       
        echo json_encode(['data' => $letters_submenu_data,'category'=>'letters']);

        $stmt->close(); 
        $conn->close();


?>