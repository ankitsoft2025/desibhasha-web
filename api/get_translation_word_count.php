<?php
// Get Plans

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);

// plan_type = T (for Trial only),  P (Paid) 
$plan_type = $input['plan_type'] ?? '';
$token = get_bearer_token($input);

// return is_active plans when language_id is '' or not provided then all languages plans
$user_id = validate_token($conn, $token);
if (!$user_id) {    
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}

    if($plan_type == 'T' ) 
    {
       $query="SELECT 50 as max_word_allowed, CAST(SUM(tg.word_count) AS UNSIGNED) as translated_words
            FROM translation_user tu, translation_global tg 
            where tu.bhasha_user_id = ?
            and tu.translation_global_id = tg.translation_global_id";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        echo json_encode($row);

      $stmt->close();     
      $conn->close();
    }

?>