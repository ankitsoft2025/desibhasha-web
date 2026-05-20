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

    $stmt = $conn->prepare("SELECT pt.plan_type_id,  pt.language_id, pt.plan_language, pt.plan_name, pt.teaching_level_id
                            FROM plan_purchase_history pph, plan_types pt
                            where pph.bhasha_user_id = ?
                            and pph.plan_expiry_date > now()
                            and pph.plan_type_id = pt.plan_type_id
                            ORDER BY pt.plan_type_id;");

    $stmt->bind_param('i', $user_id);
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