<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

require_once 'utils.php';

header('Content-Type: application/json');

$conn = get_db_connection();

$token = get_bearer_token();



/*

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {

    http_response_code(400);

    echo json_encode(['error' => 'Invalid JSON']);

    exit;

}

*/



// Generate user_id from token

$user_id = validate_token($conn, $token);

if (!$user_id) {    

    http_response_code(401);

    echo json_encode(['error' => 'Token Expired']);

    exit;

}





// check total_count in Cart table for a user



$query="SELECT count(*) as cart_total FROM plan_purchase_cart ppc

where ppc.bhasha_user_id = $user_id and ppc.order_id is null;";



$stmt = $conn->prepare($query);

$stmt->execute();

$result = $stmt->get_result();  

$plans = [];

while ($row = $result->fetch_assoc()) {

    $plans[] = $row;

}       

echo json_encode(['data' => $plans]);   



$stmt->close(); 

$conn->close();



