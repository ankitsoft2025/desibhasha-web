<?php

// Get Plans

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

    $query="SELECT pt.plan_type_id,
        pt.language_id,
        pt.plan_language,
        pt.plan_name,
        pt.valid_for_days,
        pt.plan_price,
        pt.discount_amount,
        pt.is_active,
        ppc.plan_purchase_cart_id as plan_purchase_cart_id
    FROM plan_purchase_cart ppc
    JOIN plan_types pt
    ON (pt.plan_type_id = ppc.plan_type_id  and ppc.bhasha_user_id = $user_id)
    WHERE pt.is_active=1 and ppc.order_id is null;";
    
$stmt = $conn->prepare($query);
$stmt->execute();

$result = $stmt->get_result();  
$cart_items = [];

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}       

$query_bhasha_money = "
 SELECT SUM(CASE WHEN bhasha_money_type IN ('RF', 'BP') THEN bhasha_money ELSE 0 END) AS bhasha_money_available FROM bhasha_money_transactions WHERE bhasha_user_id = $user_id and redeem_order_id IS NULL;";
$stmt = $conn->prepare($query_bhasha_money);

$stmt->execute();

$result = $stmt->get_result(); 
$bhasha_data = $result->fetch_assoc();

echo json_encode(['data' => [
    'cart_items'=> $cart_items,
    'bhasha_money_info' => $bhasha_data
]]);   


$stmt->close(); 
$conn->close();


?>